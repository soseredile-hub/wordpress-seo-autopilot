<?php
defined('ABSPATH') || exit;

/**
 * V4 Keyword Intelligence Layer
 *
 * Pipeline:
 *   Seeds → Expand (Autosuggest + Trends + PAA + Related) → Deduplicate
 *   → Scan existing content → Score (volume estimate + intent + local + difficulty)
 *   → Rank → Return best opportunity
 */
class V4_Keyword_Intel {

    private array $opts;
    private string $geo;
    private string $lang;

    public function __construct() {
        $this->opts = V4_Options::get();
        $this->geo  = $this->opts['geo'];
        $this->lang = $this->opts['language'] ?: 'it';
    }

    // ─────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────

    /**
     * Full intelligence run.
     * Returns ranked array of keyword opportunities.
     */
    public function run(): array {
        $seeds = $this->get_seeds();
        if (empty($seeds)) return [];

        // 1. Expand
        $candidates = $this->expand_all($seeds);

        // 2. Deduplicate & clean
        $candidates = $this->deduplicate($candidates);

        // 3. Filter too-short / junk
        $candidates = array_filter($candidates, fn($kw) => mb_strlen($kw) >= 8 && str_word_count($kw) >= 2);
        $candidates = array_values($candidates);

        // 4. Score each
        $covered_tokens = V4_Content_Scanner::get_covered_tokens();
        $results = [];
        foreach (array_slice($candidates, 0, 200) as $kw) {
            $covered   = V4_Content_Scanner::is_covered($kw);
            $score_data = $this->score($kw, $covered_tokens);
            $results[] = array_merge([
                'keyword' => $kw,
                'covered' => $covered,
            ], $score_data);
        }

        // 5. Sort by opportunity score desc
        usort($results, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);

        // 6. Cache
        set_transient('soser_v4_keyword_intel', $results, 2 * HOUR_IN_SECONDS);

        return $results;
    }

    /** Returns cached results or runs fresh */
    public function get_cached(): array {
        $c = get_transient('soser_v4_keyword_intel');
        return $c ?: $this->run();
    }

    /** Returns the single best keyword to write about — GSC data takes priority */
    public function best_keyword(): ?array {
        // First: try real GSC opportunities (highest quality)
        if (V4_Search_Console::is_connected()) {
            $gsc_kws = V4_Search_Console::get_intel_keywords();
            foreach ($gsc_kws as $kw) {
                if (!$kw['covered'] && $kw['opportunity_score'] >= 40) {
                    return $kw;
                }
            }
        }
        // Fallback: heuristic scoring
        $min_score = (int) $this->opts['min_opportunity'];
        $results   = $this->get_cached();
        foreach ($results as $r) {
            if (!$r['covered'] && $r['opportunity_score'] >= $min_score) return $r;
        }
        foreach ($results as $r) {
            if (!$r['covered']) return $r;
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // EXPANSION SOURCES
    // ─────────────────────────────────────────────────────────────

    private function expand_all(array $seeds): array {
        $all   = $seeds;
        $depth = max(5, min(30, (int) $this->opts['intel_depth']));

        foreach ($seeds as $seed) {
            // Google Autosuggest
            $all = array_merge($all, $this->google_autosuggest($seed));

            // Modifier expansion
            $all = array_merge($all, $this->modifier_expand($seed));

            // People Also Ask (via OpenAI — free tier friendly)
            $paa = $this->get_paa($seed);
            $all = array_merge($all, $paa);

            // Related searches simulation
            $all = array_merge($all, $this->related_searches($seed));

            // Google Trends related queries
            $all = array_merge($all, $this->google_trends_related($seed));
        }
        return $all;
    }

    /** Google Suggest API (free, no key needed) */
    private function google_autosuggest(string $q): array {
        $url = add_query_arg([
            'client' => 'firefox',
            'hl'     => $this->lang,
            'gl'     => 'it',
            'q'      => $q,
        ], 'https://suggestqueries.google.com/complete/search');

        $r = wp_remote_get($url, ['timeout' => 8, 'user-agent' => 'Mozilla/5.0']);
        if (is_wp_error($r)) return [];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        // Extract strings only — Chrome API may return [string, score, ...]
        $raw_sugs = isset($body[1]) && is_array($body[1]) ? $body[1] : [];
        $sugs = [];
        foreach ($raw_sugs as $item) {
            if (is_string($item)) $sugs[] = $item;
            elseif (is_array($item) && isset($item[0]) && is_string($item[0])) $sugs[] = $item[0];
        }

        // FIX #8: Reduced to 4 letters max to avoid 504 timeout
        $extra = [];
        foreach (['p','c','g','b'] as $letter) { // p=preventivo, c=costo, g=guida, b=bonus
            $url2 = add_query_arg([
                'client' => 'firefox',
                'hl'     => $this->lang,
                'gl'     => 'it',
                'q'      => $q . ' ' . $letter,
            ], 'https://suggestqueries.google.com/complete/search');
            $r2 = wp_remote_get($url2, ['timeout' => 5, 'user-agent' => 'Mozilla/5.0']);
            if (!is_wp_error($r2)) {
                $b2 = json_decode(wp_remote_retrieve_body($r2), true);
                if (isset($b2[1]) && is_array($b2[1])) {
                    foreach ($b2[1] as $item) {
                        if (is_string($item)) $extra[] = $item;
                        elseif (is_array($item) && isset($item[0]) && is_string($item[0])) $extra[] = $item[0];
                    }
                }
            }
            usleep(120000); // 120ms pause to avoid rate limiting
        }
        return array_slice(array_merge($sugs, $extra), 0, 40);
    }

    /** Expand with proven modifier patterns */
    private function modifier_expand(string $seed): array {
        $year     = date('Y');
        $next     = date('Y') + 1;
        $city = V4_Options::get()['geo_city'] ?: 'Milano'; // Dynamic city
        $mods = [
            // Commercial
            "prezzi {$seed}", "costo {$seed}", "preventivo {$seed}",
            "{$seed} {$city}", "{$seed} prezzi {$year}", "{$seed} quanto costa",
            "migliore {$seed} {$city}", "impresa {$seed} {$city}",
            "{$seed} economico {$city}", "ditta {$seed} {$city}",
            // Informational
            "come fare {$seed}", "guida {$seed}", "{$seed} tempi",
            "{$seed} permessi", "{$seed} normativa",
            // Bonus / fiscal
            "bonus {$seed} {$year}", "bonus {$seed} {$next}",
            "detrazione {$seed}", "detrazioni {$seed} {$year}",
            "superbonus {$seed}", "ecobonus {$seed}",
            // Long-tail
            "{$seed} chiavi in mano", "{$seed} Lombardia",
            "{$seed} senza permessi", "{$seed} fai da te",
            "quando fare {$seed}", "prima di fare {$seed}",
        ];
        $result = [];
        foreach ($mods as $m) {
            $result[] = $m;
            // Also swap if seed not at start
            if (strpos($m, $seed) !== 0) {
                $result[] = "{$seed} " . trim(str_replace($seed, '', $m));
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * People Also Ask — uses OpenAI to simulate PAA for the keyword.
     * This is a smart heuristic: costs ~0.001$ per call.
     */
    private function get_paa(string $seed): array {
        $key = $this->opts['openai_key'];
        if (empty($key)) return $this->paa_fallback($seed);

        $cache_key = 'soser_v4_paa_' . md5($seed);
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $prompt = "Generate 8 realistic Google 'People Also Ask' questions for the Italian search query: \"{$seed}\"\n"
                . "Context: Italian home renovation business in Milan.\n"
                . "Return ONLY a JSON array of question strings. Example: [\"Quanto costa...\",\"Come fare...\"]";

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-4.1-mini',
                'max_tokens'  => 200,
                'temperature' => 0.4,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) {
            return $this->paa_fallback($seed);
        }

        $body = json_decode(wp_remote_retrieve_body($r), true);
        $text = $body['choices'][0]['message']['content'] ?? '[]';
        // Try to parse as array
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            // Handle {"questions":[...]} or direct array
            $arr = is_array($decoded[0] ?? null) ? [] : array_values($decoded);
            if (empty($arr)) {
                foreach ($decoded as $v) {
                    if (is_string($v)) $arr[] = $v;
                    elseif (is_array($v)) $arr = array_merge($arr, array_values($v));
                }
            }
            set_transient($cache_key, $arr, 6 * HOUR_IN_SECONDS);
            return $arr;
        }
        return $this->paa_fallback($seed);
    }

    private function paa_fallback(string $seed): array {
        return [
            "Quanto costa {$seed}?",
            "Come scegliere {$seed}?",
            "Quali permessi servono per {$seed}?",
            "Quali bonus si applicano a {$seed}?",
            "Quanto tempo richiede {$seed}?",
        ];
    }

    /** Related searches — simulated from modifiers + location combos */
    private function related_searches(string $seed): array {
        // Build locations from settings
        $opts_loc  = V4_Options::get();
        $city      = $opts_loc['geo_city'] ?: 'Milano';
        $region    = $opts_loc['geo_region'] ?: 'Lombardia';
        $custom_zones = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $opts_loc['geo_zones'] ?? '')));
        $locations = array_merge([$city], $custom_zones ?: ['Zona Centro','Zona Nord','Zona Sud'], [$region]);
        $related   = [];
        foreach (array_slice($locations, 0, 4) as $loc) {
            $related[] = "{$seed} {$loc}";
            $related[] = "{$loc} {$seed}";
        }
        return $related;
    }

    /**
     * Google Trends "related queries" via unofficial RSS endpoint.
     * Returns related rising/top queries.
     */
        /**
     * FIX #6: Replaced fake Trends RSS (was returning unrelated trending topics)
     * with targeted modifier-based expansion that is more relevant and reliable.
     * Real Google Trends Related Queries API requires OAuth — not feasible here.
     */
    private function google_trends_related(string $seed): array {
        $cache_key = 'soser_v4_trends_' . md5($seed);
        $cached    = get_transient($cache_key);
        if ($cached !== false) return $cached;

        // Use a second Autosuggest pass with question prefixes
        // These mimic what appears in Trends "related queries" for Italian home searches
        $question_prefixes = ['come ', 'quanto costa ', 'migliore ', 'prezzi ', 'costo '];
        $results = [];
        foreach (array_slice($question_prefixes, 0, 3) as $prefix) {
            $url = add_query_arg([
                'client' => 'firefox',
                'hl'     => $this->lang,
                'gl'     => 'it',
                'q'      => $prefix . $seed,
            ], 'https://suggestqueries.google.com/complete/search');
            $r = wp_remote_get($url, ['timeout' => 6, 'user-agent' => 'Mozilla/5.0']);
            if (!is_wp_error($r)) {
                $body = json_decode(wp_remote_retrieve_body($r), true);
                if (isset($body[1])) $results = array_merge($results, array_slice($body[1], 0, 4));
            }
            usleep(100000);
        }
        set_transient($cache_key, $results, 4 * HOUR_IN_SECONDS);
        return $results;
    }

    // ─────────────────────────────────────────────────────────────
    // SCORING ENGINE
    // ─────────────────────────────────────────────────────────────

    /**
     * Score a keyword across multiple dimensions.
     * Returns array with scores + final opportunity_score.
     */
    public function score(string $kw, array $covered_tokens = []): array {
        $l      = mb_strtolower($kw);
        $words  = str_word_count($kw, 0, 'àèìòùáéíóú');

        // 1. Volume Estimate (0-40)
        $volume_score = $this->estimate_volume($l, $words);

        // 2. Local Intent (0-25)
        $local_score = $this->score_local_intent($l);

        // 3. Commercial Intent (0-20)
        $commercial_score = $this->score_commercial_intent($l);

        // 4. SEO Difficulty — inverse (lower difficulty = higher score) (0-15)
        $difficulty_score = $this->estimate_difficulty($l, $words);

        // 5. Similarity penalty (subtract if too similar to covered content)
        $similarity_penalty = $this->similarity_penalty($l, $covered_tokens);

        // Raw opportunity
        $raw = $volume_score + $local_score + $commercial_score + $difficulty_score - $similarity_penalty;
        $opportunity = max(0, min(100, $raw));

        return [
            'volume_estimate'    => $this->volume_label($volume_score),
            'volume_score'       => $volume_score,
            'local_score'        => $local_score,
            'commercial_score'   => $commercial_score,
            'difficulty_score'   => $difficulty_score,
            'similarity_penalty' => $similarity_penalty,
            'opportunity_score'  => $opportunity,
            'intent'             => $this->classify_intent($l),
            'cpc_estimate'       => $this->estimate_cpc($l),
            'recommendation'     => $this->recommendation($opportunity),
        ];
    }

    private function estimate_volume(string $l, int $words): int {
        $score = 10;
        // High-volume signals
        if (preg_match('/ristrutturazione|bonus|costo|prezzi|preventivo/', $l)) $score += 15;
        if ((strpos($l, 'milano') !== false)) $score += 8;
        if (preg_match('/bagno|cucina|appartamento|casa/', $l))               $score += 6;
        if (preg_match('/2025|2026/', $l))                                    $score += 4;
        if (preg_match('/superbonus|ecobonus|110/', $l))                      $score += 5;
        // Long-tail sweet spot (3-5 words = less volume but more intent)
        if ($words >= 3 && $words <= 5) $score += 3;
        if ($words > 6) $score -= 5; // too long = very low volume
        return min(40, $score);
    }

    private function score_local_intent(string $l): int {
        $score = 0;
        $localities = [
            'milano' => 15, 'lombardia' => 8, 'sesto san giovanni' => 5,
            'monza' => 5, 'cinisello' => 4, 'rho ' => 4, 'legnano' => 4,
        ];
        foreach ($localities as $loc => $pts) {
            if ((strpos($l, $loc) !== false)) { $score += $pts; break; }
        }
        return min(25, $score);
    }

    private function score_commercial_intent(string $l): int {
        $score = 0;
        $signals = [
            'preventivo' => 20, 'prezzi' => 18, 'costo' => 18,
            'impresa'    => 16, 'ditta'  => 14, 'migliore' => 14,
            'economico'  => 12, 'affidabile' => 10,
            'bonus'      => 10, 'detrazione' => 8,
        ];
        foreach ($signals as $signal => $pts) {
            if ((strpos($l, $signal) !== false)) { $score += $pts; break; }
        }
        return min(20, $score);
    }

    private function estimate_difficulty(string $l, int $words): int {
        // Higher score = LOWER difficulty (easier to rank)
        $score = 8;
        if ($words >= 4)             $score += 4; // long-tail = easier
        if ($words >= 6)             $score += 2;
        if (preg_match('/\d{4}/', $l)) $score += 2; // year = fresher, less competition
        if ((strpos($l, 'quanto costa') !== false) || (strpos($l, 'come fare') !== false)) $score += 2;
        return min(15, $score);
    }

    private function similarity_penalty(string $l, array $covered_tokens): int {
        $penalty = 0;
        $kw_tokens = preg_split('/\s+/', $l, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($kw_tokens as $t) {
            if (mb_strlen($t) > 4 && in_array($t, $covered_tokens, true)) {
                $penalty += 5;
            }
        }
        return min(30, $penalty);
    }

    private function classify_intent(string $l): string {
        if (preg_match('/preventivo|prezzi|costo|impresa|ditta|migliore/', $l)) return '💰 Commerciale';
        if (preg_match('/bonus|detrazione|permess|normativa|guida|come/', $l))  return '📚 Info-Commerciale';
        return '📖 Informazionale';
    }

    private function estimate_cpc(string $l): string {
        if (preg_match('/preventivo|impresa|ditta|migliore/', $l)) return '€€€ Alto';
        if (preg_match('/prezzi|costo|bonus|detrazione/', $l))     return '€€ Medio';
        return '€ Basso';
    }

    private function volume_label(int $score): string {
        if ($score >= 30) return '🔥 Alto';
        if ($score >= 20) return '📈 Medio';
        if ($score >= 12) return '📊 Basso';
        return '🔍 Molto basso';
    }

    private function recommendation(int $score): string {
        if ($score >= 65) return '✅ Scrivi subito';
        if ($score >= 45) return '👍 Buona opportunità';
        if ($score >= 30) return '🤔 Considera';
        return '⛔ Salta';
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function get_seeds(): array {
        $raw = $this->opts['seed_keywords'] ?? '';
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $raw))
        ));
    }

    private function deduplicate(array $keywords): array {
        $clean = [];
        foreach ($keywords as $kw) {
            // Skip non-string values (arrays from nested API responses)
            if (is_array($kw)) {
                // Flatten one level — some APIs return ['keyword', score, ...]
                foreach ($kw as $item) {
                    if (is_string($item) && mb_strlen(trim($item)) > 2) {
                        $cleaned = $this->clean($item);
                        if ($cleaned) $clean[] = $cleaned;
                    }
                }
                continue;
            }
            if (!is_string($kw)) continue;
            $cleaned = $this->clean($kw);
            if ($cleaned) $clean[] = $cleaned;
        }
        $clean = array_unique($clean);

        // Remove near-duplicates (fuzzy similarity)
        $result = [];
        foreach ($clean as $kw) {
            $is_dup = false;
            foreach ($result as $existing) {
                if ($this->similarity($kw, $existing) > 0.82) {
                    $is_dup = true;
                    break;
                }
            }
            if (!$is_dup) $result[] = $kw;
        }
        return array_values($result);
    }

    private function clean($kw): string {
        // Accept any type safely
        if (!is_string($kw)) return '';
        return preg_replace('/\s+/', ' ', mb_strtolower(trim(wp_strip_all_tags($kw))));
    }

    private function similarity(string $a, string $b): float {
        if ($a === $b) return 1.0;
        $la = mb_strlen($a); $lb = mb_strlen($b);
        if ($la === 0 || $lb === 0) return 0.0;
        similar_text($a, $b, $pct);
        return $pct / 100;
    }
}
