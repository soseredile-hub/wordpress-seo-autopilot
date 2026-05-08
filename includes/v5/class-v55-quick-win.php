<?php
defined('ABSPATH') || exit;

/**
 * V5.5 Feature 1: Quick Win Finder
 *
 * Finds keywords where you can reach #1 FAST by combining:
 * - Position 11-30 in GSC (almost there!)
 * - High impressions (real search volume)
 * - Low/no existing content
 * - Low competition signals
 * - Long-tail local variants not yet covered
 */
class V55_QuickWin {

    /**
     * Find all quick win opportunities.
     * Returns ranked list of easiest keywords to rank #1 for.
     */
    public static function find(): array {
        $cached = get_transient('v55_quick_wins');
        if ($cached !== false) return $cached;

        $wins = [];

        // ── Source 1: GSC Position 11-30 (biggest quick wins) ────
        if (class_exists('V4_GSC') && V4_GSC::is_connected()) {
            $gsc_wins = self::from_gsc();
            $wins = array_merge($wins, $gsc_wins);
        }

        // ── Source 2: Long-tail local expansion ──────────────────
        $local_wins = self::from_local_longtail();
        $wins = array_merge($wins, $local_wins);

        // ── Source 3: Quanto costa questions ──────────────────────
        $question_wins = self::from_questions();
        $wins = array_merge($wins, $question_wins);

        // ── Deduplicate & score ───────────────────────────────────
        $wins = self::deduplicate($wins);

        // ── Filter already covered ────────────────────────────────
        $wins = array_filter($wins, function($w) {
            if (class_exists('V5_Semantic')) {
                return !V5_Semantic::is_semantic_duplicate($w['keyword'], 0.85);
            }
            return !V4_Content_Scanner::is_covered($w['keyword']);
        });

        // Sort by win_score desc
        usort($wins, fn($a,$b) => $b['win_score'] <=> $a['win_score']);
        $wins = array_values(array_slice($wins, 0, 50));

        set_transient('v55_quick_wins', $wins, 4 * HOUR_IN_SECONDS);
        return $wins;
    }

    // ── Source 1: GSC near-page-1 keywords ───────────────────────

    private static function from_gsc(): array {
        $site = V4_GSC::get_site_url();
        $opps = V4_GSC::get_keyword_opportunities($site, 90);
        $wins = [];

        foreach ($opps as $opp) {
            $pos   = (float)($opp['position'] ?? 99);
            $impr  = (int)($opp['impressions'] ?? 0);
            $ctr   = (float)str_replace('%','',$opp['ctr'] ?? '0');
            $kw    = $opp['keyword'] ?? '';

            if ($impr < 20 || $pos < 4 || $pos > 35) continue;

            // Win score: closer to page 1 + more impressions = higher score
            $score = 0;
            if ($pos >= 11 && $pos <= 15)      $score += 50; // Just off page 1
            elseif ($pos >= 16 && $pos <= 20)  $score += 40;
            elseif ($pos >= 21 && $pos <= 30)  $score += 25;
            elseif ($pos >= 4  && $pos <= 10)  $score += 35; // Already page 1, push to #1

            $score += min(30, (int)($impr / 20)); // Volume bonus
            if ($ctr < 2) $score += 15; // Low CTR = room to improve

            $wins[] = [
                'keyword'     => $kw,
                'source'      => 'gsc',
                'position'    => round($pos, 1),
                'impressions' => $impr,
                'ctr'         => $ctr . '%',
                'win_score'   => $score,
                'why'         => self::why_label($pos, $impr, $ctr),
                'time_to_top' => self::estimate_time($pos, $score),
                'action'      => 'refresh', // Existing content needs improvement
            ];
        }
        return $wins;
    }

    // ── Source 2: Local long-tail not yet covered ─────────────────

    private static function from_local_longtail(): array {
        $opts     = V4_Options::get();
        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_active_services()
            : [];
        $geo      = $opts['geo'] ?: 'Milano';
        $wins     = [];

        // Milan neighborhoods — hyper-local = very low competition
        $zones = [
            'Porta Romana', 'Navigli', 'Isola', 'Città Studi',
            'Bicocca', 'Loreto', 'Bovisa', 'Pignone',
            'Sesto San Giovanni', 'Cinisello Balsamo', 'Monza',
        ];

        // Price modifiers — high commercial intent
        $price_mods = [
            'prezzi', 'costo', 'preventivo', 'quanto costa',
            'prezzi al mq', 'costo al metro quadro',
        ];

        foreach ($services as $svc) {
            $base_name = mb_strtolower($svc['name']);

            // Zone-specific variants
            foreach (array_slice($zones, 0, 5) as $zone) {
                $kw    = trim("{$base_name} {$zone}");
                $wins[] = [
                    'keyword'     => $kw,
                    'source'      => 'local_longtail',
                    'position'    => null,
                    'impressions' => null,
                    'ctr'         => null,
                    'win_score'   => 65,
                    'why'         => "Long-tail locale: bassa competizione, alta conversione",
                    'time_to_top' => '2-4 settimane',
                    'action'      => 'write',
                ];
            }

            // Price variants (highest commercial intent)
            foreach (array_slice($price_mods, 0, 3) as $mod) {
                $kw = trim("{$mod} {$base_name} {$geo}");
                $wins[] = [
                    'keyword'     => $kw,
                    'source'      => 'price_intent',
                    'position'    => null,
                    'impressions' => null,
                    'ctr'         => null,
                    'win_score'   => 70,
                    'why'         => "Intento commerciale alto + long-tail = facilissimo",
                    'time_to_top' => '1-3 settimane',
                    'action'      => 'write',
                ];
            }
        }
        return $wins;
    }

    // ── Source 3: Question keywords ───────────────────────────────

    private static function from_questions(): array {
        $opts     = V4_Options::get();
        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_active_services()
            : [];
        $geo  = $opts['geo'] ?: 'Milano';
        $wins = [];

        $question_prefixes = [
            'quanto costa'       => 80,
            'quanto si spende'   => 75,
            'come fare'          => 60,
            'come scegliere'     => 58,
            'quanto dura'        => 55,
            'quali permessi'     => 52,
            'quando fare'        => 50,
            'differenza tra'     => 48,
            'conviene fare'      => 65,
            'è obbligatorio'     => 50,
        ];

        foreach ($services as $svc) {
            $base = mb_strtolower($svc['name']);
            foreach ($question_prefixes as $prefix => $base_score) {
                $kw = "{$prefix} {$base} {$geo}";
                $wins[] = [
                    'keyword'     => $kw,
                    'source'      => 'question',
                    'position'    => null,
                    'impressions' => null,
                    'ctr'         => null,
                    'win_score'   => $base_score,
                    'why'         => "Domanda diretta → Featured Snippet possibile",
                    'time_to_top' => '2-5 settimane',
                    'action'      => 'write',
                ];
            }
        }
        return $wins;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private static function why_label(float $pos, int $impr, float $ctr): string {
        if ($pos >= 11 && $pos <= 15 && $impr > 100)
            return "🥇 A un passo dalla pagina 1! ({$pos}° posto, {$impr} impressioni)";
        if ($pos >= 11 && $pos <= 20)
            return "📈 Posizione {$pos} — con un articolo migliore vai in pagina 1";
        if ($pos >= 4 && $pos <= 10 && $ctr < 3)
            return "⚡ Già in pagina 1 ma CTR basso — ottimizza titolo e meta";
        if ($pos >= 21 && $pos <= 30)
            return "🎯 Posizione {$pos} — buon potenziale con contenuto più completo";
        return "Opportunità rilevata";
    }

    private static function estimate_time(float $pos, int $score): string {
        if ($pos <= 15 && $score >= 60) return '1-2 settimane';
        if ($pos <= 20 && $score >= 40) return '2-4 settimane';
        if ($pos <= 30)                 return '3-6 settimane';
        return '4-8 settimane';
    }

    private static function deduplicate(array $wins): array {
        $seen   = [];
        $result = [];
        foreach ($wins as $w) {
            $key = mb_strtolower(preg_replace('/\s+/', ' ', trim($w['keyword'])));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $w;
            }
        }
        return $result;
    }

    public static function invalidate(): void {
        delete_transient('v55_quick_wins');
    }
}
