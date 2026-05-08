<?php
defined('ABSPATH') || exit;

/**
 * Scans existing site content to:
 * - Extract covered keywords & topics
 * - Detect post intent
 * - Build a "covered map" for cannibalisation checks
 */
class V4_Content_Scanner {

    /** Returns array of all published post data with extracted keywords */
    public static function scan(): array {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft', 'future'],
            'numberposts'    => 500,
            'fields'         => 'all',
        ]);

        $map = [];
        foreach ($posts as $p) {
            $text   = wp_strip_all_tags($p->post_title . ' ' . $p->post_excerpt . ' ' . $p->post_content);
            $words  = self::extract_key_phrases($text);
            $intent = self::detect_intent($text);
            $map[]  = [
                'id'       => $p->ID,
                'title'    => $p->post_title,
                'slug'     => $p->post_name,
                'status'   => $p->post_status,
                'intent'   => $intent,
                'phrases'  => $words,
                'url'      => get_permalink($p->ID),
            ];
        }
        // Cache for 1 hour
        set_transient('soser_v4_content_map', $map, HOUR_IN_SECONDS);
        return $map;
    }

    public static function get_cached(): array {
        $c = get_transient('soser_v4_content_map');
        return $c ?: self::scan();
    }

    /**
     * Check if a keyword is already covered (cannibalisation check).
     * Uses fuzzy matching — not just exact title match.
     */
    public static function is_covered(string $keyword): bool {
        global $wpdb;
        $terms = self::tokenize($keyword);
        if (count($terms) < 2) return false;

        // Direct DB search for efficiency
        $conditions = [];
        foreach (array_slice($terms, 0, 4) as $t) {
            $conditions[] = $wpdb->prepare(
                "(post_title LIKE %s OR post_content LIKE %s)",
                '%' . $wpdb->esc_like($t) . '%',
                '%' . $wpdb->esc_like($t) . '%'
            );
        }
        $sql = "SELECT ID FROM {$wpdb->posts}
                WHERE post_type='post'
                AND post_status IN ('publish','draft','future')
                AND " . implode(' AND ', $conditions) . "
                LIMIT 1";
        return (bool) $wpdb->get_var($sql);
    }

    /** Get all covered topic tokens for deduplication */
    public static function get_covered_tokens(): array {
        $map    = self::get_cached();
        $tokens = [];
        foreach ($map as $post) {
            foreach ($post['phrases'] as $p) {
                $tokens[] = mb_strtolower($p);
            }
        }
        return array_unique($tokens);
    }

    // ── Private helpers ──────────────────────────────────────────

    private static function extract_key_phrases(string $text): array {
        $text   = mb_strtolower(wp_strip_all_tags($text));
        $words  = preg_split('/[\s,.\-\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop   = self::stop_words();
        $words  = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $stop, true));

        // Build bigrams + trigrams
        $words  = array_values($words);
        $phrases = $words;
        for ($i = 0; $i < count($words) - 1; $i++) {
            $phrases[] = $words[$i] . ' ' . $words[$i + 1];
            if (isset($words[$i + 2])) {
                $phrases[] = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
            }
        }
        return array_unique(array_slice($phrases, 0, 80));
    }

    private static function detect_intent(string $text): string {
        $l = mb_strtolower($text);
        if (preg_match('/prezz[oi]|cost[oi]|preventivo|impresa|azienda|migliore|vicin[oi]/', $l)) {
            return 'commercial';
        }
        if (preg_match('/bonus|detrazione|permess[oi]|normativa|come fare|guida|quanto/', $l)) {
            return 'informational-commercial';
        }
        return 'informational';
    }

    private static function tokenize(string $kw): array {
        return array_filter(
            preg_split('/\s+/', mb_strtolower($kw)),
            fn($w) => mb_strlen($w) > 3
        );
    }

    private static function stop_words(): array {
        return [
            'che','per','con','una','uno','sono','della','delle','degli','dei',
            'nella','nelle','negli','nei','alla','alle','agli','dai','dal',
            'come','quando','dove','questo','questa','questi','queste',
            'anche','molto','dopo','prima','ogni','solo','senza','fino',
            'tutti','tutto','tutta','tutte','essere','avere','fare',
            'the','and','for','with','that','this','are','from',
        ];
    }
}
