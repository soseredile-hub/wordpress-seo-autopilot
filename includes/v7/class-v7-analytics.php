<?php
defined('ABSPATH') || exit;

/**
 * V7_Analytics — Unified Analytics Aggregator
 * Pulls data from: GSC, SERP Tracker, Refresh Engine, WP posts
 * Single source of truth for the dashboard.
 */
class V7_Analytics {

    const CACHE_KEY  = 'soser_v7_analytics';
    const CACHE_TIME = 3 * HOUR_IN_SECONDS;

    // ── Main aggregator ───────────────────────────────────────────

    public static function get(bool $force = false): array {
        if (!$force) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached) return $cached;
        }

        $data = [
            'overview'     => self::overview(),
            'top_articles' => self::top_articles(),
            'opportunities'=> self::opportunities(),
            'serp_summary' => self::serp_summary(),
            'refresh_queue'=> self::refresh_queue_summary(),
            'recent_posts' => self::recent_plugin_posts(),
            'ga4_overview' => class_exists('V7_GA4') && V7_GA4::is_connected() ? V7_GA4::get_overview(28) : [],
            'ga4_trend'    => class_exists('V7_GA4') && V7_GA4::is_connected() ? V7_GA4::get_daily_trend(28) : [],
            'ga4_sources'  => class_exists('V7_GA4') && V7_GA4::is_connected() ? V7_GA4::get_conversions_by_source(28) : [],
            'gsc_connected'=> class_exists('V4_GSC') && V4_GSC::is_connected(),
            'ga4_connected'=> class_exists('V7_GA4') && V7_GA4::is_connected(),
            'generated_at' => current_time('mysql'),
        ];

        set_transient(self::CACHE_KEY, $data, self::CACHE_TIME);
        return $data;
    }

    public static function invalidate(): void {
        delete_transient(self::CACHE_KEY);
    }

    // ── Overview stats ────────────────────────────────────────────

    private static function overview(): array {
        global $wpdb;

        // Posts written by plugin
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_soser_focus_keyword'"
        );

        // Published only
        $published = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE m.meta_key = '_soser_focus_keyword'
             AND p.post_status = 'publish'"
        );

        // Refreshed count
        $refreshed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_v53_last_refresh'"
        );

        // Keywords tracked in SERP
        $tracked = 0;
        if (class_exists('V57_SERPTracker')) {
            $tracked = count(V57_SERPTracker::get_tracked_keywords());
        }

        // GSC data overview
        $gsc_totals = self::gsc_overview();

        return [
            'total_articles' => $total,
            'published'      => $published,
            'refreshed'      => $refreshed,
            'tracked_kws'    => $tracked,
            'total_clicks'   => $gsc_totals['clicks'],
            'total_impressions' => $gsc_totals['impressions'],
            'avg_ctr'        => $gsc_totals['ctr'],
            'avg_position'   => $gsc_totals['position'],
        ];
    }

    // ── GSC overview ─────────────────────────────────────────────

    private static function gsc_overview(): array {
        $empty = ['clicks'=>0,'impressions'=>0,'ctr'=>0,'position'=>0];
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) return $empty;

        $site = V4_GSC::get_site_url();
        if (!$site) return $empty;

        $opps = V4_GSC::get_keyword_opportunities($site, 28);
        if (empty($opps)) return $empty;

        $clicks      = array_sum(array_column($opps, 'clicks'));
        $impressions = array_sum(array_column($opps, 'impressions'));
        $positions   = array_column($opps, 'position');
        $avg_pos     = $positions ? round(array_sum($positions) / count($positions), 1) : 0;
        $avg_ctr     = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0;

        return [
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'ctr'         => $avg_ctr,
            'position'    => $avg_pos,
        ];
    }

    // ── Top articles (by GSC clicks) ──────────────────────────────

    private static function top_articles(int $limit = 10): array {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) {
            return self::top_articles_by_date($limit);
        }

        $site = V4_GSC::get_site_url();
        $opps = $site ? V4_GSC::get_keyword_opportunities($site, 90) : [];

        // Map GSC data to posts
        $results = [];
        foreach (array_slice($opps, 0, $limit * 2) as $opp) {
            $kw      = $opp['keyword'] ?? '';
            $post_id = self::keyword_to_post_id($kw);
            if (!$post_id) continue;

            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') continue;

            $results[$post_id] = [
                'id'          => $post_id,
                'title'       => $post->post_title,
                'url'         => get_permalink($post_id),
                'keyword'     => $kw,
                'clicks'      => (int) ($opp['clicks'] ?? 0),
                'impressions' => (int) ($opp['impressions'] ?? 0),
                'position'    => round($opp['position'] ?? 0, 1),
                'ctr'         => round(($opp['ctr'] ?? 0) * 100, 2),
                'status'      => self::article_status($opp['position'] ?? 99),
            ];

            if (count($results) >= $limit) break;
        }

        // If less than limit, fill with plugin posts by date
        if (count($results) < $limit) {
            foreach (self::top_articles_by_date($limit) as $a) {
                if (!isset($results[$a['id']])) {
                    $results[$a['id']] = $a;
                }
            }
        }

        usort($results, fn($a,$b) => $b['clicks'] - $a['clicks']);
        return array_values(array_slice($results, 0, $limit));
    }

    private static function top_articles_by_date(int $limit): array {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'meta_key'    => '_soser_focus_keyword',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
        return array_map(fn($p) => [
            'id'          => $p->ID,
            'title'       => $p->post_title,
            'url'         => get_permalink($p->ID),
            'keyword'     => get_post_meta($p->ID, '_soser_focus_keyword', true),
            'clicks'      => 0,
            'impressions' => 0,
            'position'    => 0,
            'ctr'         => 0,
            'status'      => 'new',
        ], $posts);
    }

    // ── Opportunities (keywords to target) ────────────────────────

    private static function opportunities(int $limit = 10): array {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) return [];

        $site = V4_GSC::get_site_url();
        if (!$site) return [];

        $opps = V4_GSC::get_keyword_opportunities($site, 90);
        if (empty($opps)) return [];

        $results = [];
        foreach ($opps as $opp) {
            $pos    = $opp['position'] ?? 99;
            $clicks = $opp['clicks'] ?? 0;
            $impr   = $opp['impressions'] ?? 0;

            // Quick win: position 4-15 with decent impressions
            if ($pos >= 4 && $pos <= 15 && $impr >= 50) {
                $results[] = [
                    'keyword'     => $opp['keyword'],
                    'position'    => round($pos, 1),
                    'clicks'      => (int) $clicks,
                    'impressions' => (int) $impr,
                    'opportunity' => 'quick_win',
                    'label'       => '⚡ Quick Win',
                    'action'      => "Ottimizza per passare da pos {$pos} → top 3",
                ];
            }
            // Sleeping giant: high impressions, low clicks
            elseif ($impr >= 200 && $clicks < $impr * 0.01) {
                $results[] = [
                    'keyword'     => $opp['keyword'],
                    'position'    => round($pos, 1),
                    'clicks'      => (int) $clicks,
                    'impressions' => (int) $impr,
                    'opportunity' => 'low_ctr',
                    'label'       => '😴 CTR Basso',
                    'action'      => 'Migliora il title e meta description',
                ];
            }
            // New article opportunity: ranking but no content yet
            elseif ($pos >= 16 && $impr >= 100) {
                $results[] = [
                    'keyword'     => $opp['keyword'],
                    'position'    => round($pos, 1),
                    'clicks'      => (int) $clicks,
                    'impressions' => (int) $impr,
                    'opportunity' => 'new_article',
                    'label'       => '✍️ Scrivi articolo',
                    'action'      => "Posizione {$pos} — scrivi un articolo dedicato",
                ];
            }
        }

        usort($results, fn($a,$b) => $b['impressions'] - $a['impressions']);
        return array_slice($results, 0, $limit);
    }

    // ── SERP summary ──────────────────────────────────────────────

    private static function serp_summary(): array {
        if (!class_exists('V57_SERPTracker')) return [];

        $positions = V57_SERPTracker::get_latest_positions();
        if (empty($positions)) return [];

        $top3  = array_filter($positions, fn($p) => ($p['position'] ?? 99) <= 3);
        $top10 = array_filter($positions, fn($p) => ($p['position'] ?? 99) <= 10);
        $drop  = array_filter($positions, fn($p) => ($p['change'] ?? 0) < -3);
        $rise  = array_filter($positions, fn($p) => ($p['change'] ?? 0) > 3);

        return [
            'total'  => count($positions),
            'top3'   => count($top3),
            'top10'  => count($top10),
            'drops'  => count($drop),
            'rises'  => count($rise),
            'detail' => array_slice(array_values($positions), 0, 20),
        ];
    }

    // ── Refresh queue summary ─────────────────────────────────────

    private static function refresh_queue_summary(): array {
        if (!class_exists('V53_BulkRefresh')) return [];
        $stats = V53_BulkRefresh::stats();
        return [
            'pending'   => $stats['pending']  ?? 0,
            'done'      => $stats['done']     ?? 0,
            'failed'    => $stats['failed']   ?? 0,
            'total'     => $stats['total']    ?? 0,
        ];
    }

    // ── Recent plugin posts ───────────────────────────────────────

    private static function recent_plugin_posts(int $limit = 5): array {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'meta_key'    => '_soser_focus_keyword',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
        return array_map(fn($p) => [
            'id'      => $p->ID,
            'title'   => $p->post_title,
            'url'     => get_permalink($p->ID),
            'keyword' => get_post_meta($p->ID, '_soser_focus_keyword', true),
            'date'    => get_the_date('d/m/Y', $p),
            'score'   => (int) get_post_meta($p->ID, '_soser_content_score', true),
        ], $posts);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private static function keyword_to_post_id(string $keyword): ?int {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_soser_focus_keyword'
             AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($keyword) . '%'
        ));
        return $id ? (int) $id : null;
    }

    private static function article_status(float $position): string {
        if ($position <= 3)  return 'top3';
        if ($position <= 10) return 'top10';
        if ($position <= 20) return 'growing';
        return 'needs_work';
    }
}
