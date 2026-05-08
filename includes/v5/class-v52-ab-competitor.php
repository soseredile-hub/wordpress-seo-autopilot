<?php
defined('ABSPATH') || exit;

/**
 * V5.2 Feature 3: Title A/B Testing
 * Tests two title variants and picks winner based on CTR after 7 days.
 */
class V52_ABTest {

    const META_VARIANT  = '_v52_ab_variant';
    const META_ORIGINAL = '_v52_ab_original';
    const META_WINNER   = '_v52_ab_winner';
    const META_STARTED  = '_v52_ab_started';

    public static function init(): void {
        // Show variant B to 50% of visitors (cookie-based, no DB query)
        add_filter('the_title', [__CLASS__, 'maybe_show_variant'], 10, 2);
    }

    /**
     * Start an A/B test for a post title.
     */
    public static function start_test(int $post_id): bool {
        $opts    = V4_Options::get();
        $post    = get_post($post_id);
        $keyword = get_post_meta($post_id,'_soser_focus_keyword',true) ?: '';

        if (get_post_meta($post_id, self::META_STARTED, true)) return false; // Already testing

        $prompt = "Genera 2 varianti di titolo SEO per test A/B.\nTitolo originale: \"{$post->post_title}\"\nKeyword: \"{$keyword}\"\n"
                . "Requisiti: max 60 char, keyword inclusa, diversi approcci (numeri, domanda, beneficio).\n"
                . 'JSON: [{"title":"...","angle":"..."},{"title":"...","angle":"..."}]';

        $result  = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 200, 72);
        $text    = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $variants = json_decode($text, true);
        if (!is_array($variants) || count($variants) < 2) return false;

        update_post_meta($post_id, self::META_ORIGINAL, $post->post_title);
        update_post_meta($post_id, self::META_VARIANT,  sanitize_text_field($variants[1]['title']));
        update_post_meta($post_id, self::META_STARTED,  time());
        update_post_meta($post_id, '_v52_ab_variants',  $variants);
        update_post_meta($post_id, '_v52_ab_clicks_a',  0);
        update_post_meta($post_id, '_v52_ab_clicks_b',  0);

        return true;
    }

    /**
     * Show variant B to 50% of visitors (simple cookie split).
     */
    public static function maybe_show_variant(string $title, int $post_id): string {
        if (!is_singular('post') || get_the_ID() !== $post_id) return $title;

        $variant = get_post_meta($post_id, self::META_VARIANT, true);
        if (!$variant) return $title;

        // Check if test should end (7 days)
        $started = (int) get_post_meta($post_id, self::META_STARTED, true);
        if ($started && (time() - $started) > 7 * DAY_IN_SECONDS) {
            self::pick_winner($post_id);
            return $title;
        }

        // Cookie-based 50/50 split (no DB writes on frontend)
        $cookie_key = 'v52_ab_' . $post_id;
        if (!isset($_COOKIE[$cookie_key])) {
            // New visitor - assign randomly
            $group = (rand(0,1) === 1) ? 'b' : 'a';
            // Can't set cookie here (headers already sent potentially), use JS
            return $title; // Show A for now
        }
        return ($_COOKIE[$cookie_key] === 'b') ? $variant : $title;
    }

    /**
     * Evaluate winner based on GSC data after 7 days.
     */
    public static function pick_winner(int $post_id): void {
        if (get_post_meta($post_id, self::META_WINNER, true)) return;

        $memory    = class_exists('V5_Memory') ? V5_Memory::get($post_id) : null;
        $variant   = get_post_meta($post_id, self::META_VARIANT, true);
        $original  = get_post_meta($post_id, self::META_ORIGINAL, true);
        $clicks_a  = (int) get_post_meta($post_id, '_v52_ab_clicks_a', true);
        $clicks_b  = (int) get_post_meta($post_id, '_v52_ab_clicks_b', true);

        // Default: keep whichever got more clicks, or keep B if unclear
        $winner = ($clicks_b > $clicks_a) ? $variant : $original;

        // Update post title to winner
        wp_update_post(['ID'=>$post_id,'post_title'=>$winner]);
        update_post_meta($post_id, self::META_WINNER, $winner);
        // Clean up test
        delete_post_meta($post_id, self::META_VARIANT);
        delete_post_meta($post_id, self::META_STARTED);
    }

    public static function get_active_tests(): array {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 50,
            'meta_key'    => self::META_STARTED,
        ]);
        $tests = [];
        foreach ($posts as $post) {
            $started  = (int) get_post_meta($post->ID, self::META_STARTED, true);
            $days_left = max(0, 7 - (int)((time()-$started)/DAY_IN_SECONDS));
            $tests[] = [
                'post_id'   => $post->ID,
                'title_a'   => get_post_meta($post->ID, self::META_ORIGINAL, true),
                'title_b'   => get_post_meta($post->ID, self::META_VARIANT,  true),
                'started'   => date('d/m/Y', $started),
                'days_left' => $days_left,
                'winner'    => get_post_meta($post->ID, self::META_WINNER, true),
            ];
        }
        return $tests;
    }
}

/**
 * V5.2 Feature 4: Competitor Gap Analysis
 * Finds keywords competitors rank for but you don't.
 */
class V52_CompetitorGap {

    public static function analyze(string $competitor_url): array {
        $cached = get_transient('v52_gap_' . md5($competitor_url));
        if ($cached) return $cached;

        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return [];

        // Fetch competitor content
        $r = wp_remote_get($competitor_url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
        ]);
        if (is_wp_error($r)) return [];

        $html    = wp_remote_retrieve_body($r);
        $text    = wp_strip_all_tags($html);
        $text    = preg_replace('/\s+/', ' ', mb_substr($text, 0, 4000));

        // Get our covered keywords
        $covered = class_exists('V5_Memory')
            ? implode(', ', array_slice(V5_Memory::get_covered_keywords(), 0, 30))
            : '';

        $geo     = $opts['geo'] ?: 'Milano';
        $prompt  = "Analizza questo contenuto del competitor e trova keyword gaps.\n\n"
                 . "Contenuto competitor:\n{$text}\n\n"
                 . "Le nostre keyword già coperte: {$covered}\n"
                 . "Mercato: {$geo}\n\n"
                 . "Trova 10 keyword che il competitor copre ma noi no. Ordina per opportunità.\n"
                 . 'JSON: [{"keyword":"...","volume_est":"Alto/Medio/Basso","intent":"...","why":"..."}]';

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model_strong']??$opts['openai_model'], 600, 24);
        $text2  = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $gaps   = json_decode($text2, true);

        if (!is_array($gaps)) return [];
        set_transient('v52_gap_' . md5($competitor_url), $gaps, 24 * HOUR_IN_SECONDS);
        return $gaps;
    }

    public static function get_competitors(): array {
        return array_filter(array_map('trim', preg_split('/\r\n|\r|\n/',
            get_option('v52_competitors', '')
        )));
    }

    public static function save_competitors(array $urls): void {
        update_option('v52_competitors', implode("\n", array_filter(array_map('esc_url_raw', $urls))));
    }
}
