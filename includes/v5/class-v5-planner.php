<?php
defined('ABSPATH') || exit;

/**
 * V10 Feature 6+7: AI SEO Planner + Opportunity Detection
 * The autonomous brain — decides WHAT to write, WHEN, and WHY.
 * Combines: Topic Map + Memory + GSC + Keyword Intel
 */
class V5_Planner {

    /**
     * Full planning cycle — returns prioritized action list.
     * Cached 4 hours to avoid repeated API calls.
     */
    public static function plan(): array {
        $cached = get_transient('v5_plan');
        if ($cached) return $cached;

        $memory   = V5_Memory::get_all();
        $covered  = V5_Memory::get_topics_covered();
        $refresh  = V5_Memory::get_needs_refresh(5);
        $opts     = V4_Options::get();
        $actions  = [];

        // ── Source 1: Topic Map gaps (highest authority value) ────
        $seeds    = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $opts['seed_keywords'] ?? '')));
        $map      = V5_Semantic::build_topic_map($seeds, $covered);

        foreach (array_slice($map, 0, 8) as $item) {
            if (!V5_Semantic::is_semantic_duplicate($item['pillar'])) {
                $actions[] = [
                    'action'   => 'write',
                    'type'     => 'pillar',
                    'keyword'  => $item['pillar'],
                    'topic'    => $item['topic'],
                    'priority' => 90,
                    'reason'   => "Pillar page mancante per topic: '{$item['topic']}'",
                    'source'   => 'topic_map',
                ];
            }
            foreach (array_slice($item['clusters'], 0, 3) as $cluster_kw) {
                if (!V5_Semantic::is_semantic_duplicate($cluster_kw)) {
                    $actions[] = [
                        'action'   => 'write',
                        'type'     => 'cluster',
                        'keyword'  => $cluster_kw,
                        'topic'    => $item['topic'],
                        'priority' => 70,
                        'reason'   => "Cluster article per: '{$item['topic']}'",
                        'source'   => 'topic_map',
                    ];
                }
            }
        }

        // ── Source 2: GSC ranking gaps (real traffic opportunities) ──
        if (class_exists('V4_GSC') && V4_GSC::is_connected()) {
            $gsc_opps = V4_GSC::get_keyword_opportunities(V4_GSC::get_site_url());
            foreach (array_slice($gsc_opps, 0, 5) as $opp) {
                if (!$opp['covered'] && !V5_Semantic::is_semantic_duplicate($opp['keyword'])) {
                    $actions[] = [
                        'action'   => 'write',
                        'type'     => 'gsc_opportunity',
                        'keyword'  => $opp['keyword'],
                        'priority' => 60 + min(30, (int)($opp['impressions'] / 100)),
                        'reason'   => "GSC: {$opp['impressions']} impressioni, posizione {$opp['position']}",
                        'source'   => 'gsc',
                    ];
                }
            }
        }

        // ── Source 3: Content refresh ─────────────────────────────
        foreach ($refresh as $item) {
            $actions[] = [
                'action'   => 'refresh',
                'type'     => 'stale',
                'post_id'  => (int)$item['post_id'],
                'keyword'  => $item['keyword'],
                'priority' => 55 + (int)($item['gsc_impr'] / 100),
                'reason'   => "Pos: {$item['gsc_position']}, CTR: " . round($item['gsc_ctr']*100,1) . '%',
                'source'   => 'performance',
            ];
        }

        // ── Source 4: Keyword Intel fallback ─────────────────────
        $intel = get_transient('soser_v4_keyword_intel') ?: [];
        foreach (array_slice($intel, 0, 5) as $kw_data) {
            if (!$kw_data['covered'] && $kw_data['opportunity_score'] >= 50) {
                if (!V5_Semantic::is_semantic_duplicate($kw_data['keyword'])) {
                    $actions[] = [
                        'action'   => 'write',
                        'type'     => 'keyword_intel',
                        'keyword'  => $kw_data['keyword'],
                        'priority' => $kw_data['opportunity_score'],
                        'reason'   => "Score opportunità: {$kw_data['opportunity_score']}/100",
                        'source'   => 'keyword_intel',
                    ];
                }
            }
        }

        usort($actions, fn($a,$b) => $b['priority'] <=> $a['priority']);

        $plan = [
            'generated_at'  => current_time('mysql'),
            'total'         => count($actions),
            'next_write'    => self::first_write($actions),
            'next_refresh'  => self::first_refresh($actions),
            'actions'       => array_slice($actions, 0, 25),
            'stats'         => V5_Memory::stats(),
        ];

        set_transient('v5_plan', $plan, 4 * HOUR_IN_SECONDS);
        return $plan;
    }

    public static function get_next_keyword(): ?string {
        $plan = self::plan();
        foreach ($plan['actions'] as $a) {
            if ($a['action'] === 'write' && !empty($a['keyword'])) return $a['keyword'];
        }
        return null;
    }

    public static function invalidate(): void {
        delete_transient('v5_plan');
    }

    private static function first_write(array $actions): ?array {
        foreach ($actions as $a) { if ($a['action']==='write') return $a; }
        return null;
    }

    private static function first_refresh(array $actions): ?array {
        foreach ($actions as $a) { if ($a['action']==='refresh') return $a; }
        return null;
    }
}
