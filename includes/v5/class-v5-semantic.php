<?php
defined('ABSPATH') || exit;

/**
 * V9 Feature 1+2+5: Semantic Clustering, Topic Maps, Semantic Dedup
 * Uses OpenAI Embeddings to understand keyword MEANING not just text.
 * Lazy-loaded: only runs during admin/cron, ZERO impact on frontend.
 */
class V5_Semantic {

    const EMBED_TABLE = 'soser_v5_embeddings';
    const CLUSTER_TTL = 12 * HOUR_IN_SECONDS;

    // ── Embeddings ────────────────────────────────────────────────

    public static function embed(string $text): array {
        if (empty($text)) return [];
        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return [];

        $hash   = 'v5emb_' . md5($text);
        $cached = get_transient($hash);
        if ($cached !== false) return $cached;

        $r = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $opts['openai_key'], 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['model' => 'text-embedding-3-small', 'input' => mb_substr($text, 0, 8000)]),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return [];
        $body   = json_decode(wp_remote_retrieve_body($r), true);
        $vector = $body['data'][0]['embedding'] ?? [];

        if ($vector) {
            set_transient($hash, $vector, 7 * DAY_IN_SECONDS);
            // Track cost: ~$0.00002 per 1K tokens
            V5_Cost::track('text-embedding-3-small', $body['usage']['total_tokens'] ?? 0, 0);
        }
        return $vector;
    }

    public static function batch_embed(array $texts): array {
        $result = []; $to_fetch = [];
        foreach ($texts as $t) {
            $c = get_transient('v5emb_' . md5($t));
            if ($c !== false) $result[$t] = $c;
            else $to_fetch[] = $t;
        }
        if (empty($to_fetch)) return $result;

        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return $result;

        foreach (array_chunk($to_fetch, 50) as $chunk) {
            $r = wp_remote_post('https://api.openai.com/v1/embeddings', [
                'timeout' => 30,
                'headers' => ['Authorization' => 'Bearer ' . $opts['openai_key'], 'Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['model' => 'text-embedding-3-small', 'input' => array_map(fn($t) => mb_substr($t,0,8000), $chunk)]),
            ]);
            if (is_wp_error($r)) continue;
            $body = json_decode(wp_remote_retrieve_body($r), true);
            foreach ($body['data'] ?? [] as $item) {
                $t = $chunk[$item['index']];
                $result[$t] = $item['embedding'];
                set_transient('v5emb_' . md5($t), $item['embedding'], 7 * DAY_IN_SECONDS);
            }
            V5_Cost::track('text-embedding-3-small', $body['usage']['total_tokens'] ?? 0, 0);
        }
        return $result;
    }

    public static function cosine(array $a, array $b): float {
        if (empty($a) || empty($b) || count($a) !== count($b)) return 0.0;
        $dot = $na = $nb = 0.0;
        for ($i = 0, $l = count($a); $i < $l; $i++) {
            $dot += $a[$i] * $b[$i]; $na += $a[$i]**2; $nb += $b[$i]**2;
        }
        return ($na && $nb) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }

    // ── Feature 1: Semantic Clustering ───────────────────────────

    /**
     * Group keywords by MEANING.
     * "bagno moderno" + "rifacimento bagno" + "costo bagno" = same cluster.
     */
    public static function cluster(array $keywords, float $threshold = 0.78): array {
        $cached = get_transient('v5_clusters_' . md5(implode(',', $keywords)));
        if ($cached) return $cached;

        $vectors  = self::batch_embed($keywords);
        $clusters = [];
        $assigned = [];

        foreach ($keywords as $kw) {
            if (isset($assigned[$kw])) continue;
            $vec = $vectors[$kw] ?? [];
            $best_ci = -1; $best_sim = 0.0;

            foreach ($clusters as $ci => $cl) {
                $sim = self::cosine($vec, $vectors[$cl['centroid']] ?? []);
                if ($sim > $best_sim && $sim >= $threshold) { $best_sim = $sim; $best_ci = $ci; }
            }

            if ($best_ci >= 0) {
                $clusters[$best_ci]['members'][] = $kw;
            } else {
                $clusters[] = [
                    'centroid' => $kw,
                    'members'  => [$kw],
                    'topic'    => self::extract_topic($kw),
                    'intent'   => self::detect_intent($kw),
                ];
            }
            $assigned[$kw] = true;
        }

        usort($clusters, fn($a,$b) => count($b['members']) <=> count($a['members']));
        set_transient('v5_clusters_' . md5(implode(',', $keywords)), $clusters, self::CLUSTER_TTL);
        return $clusters;
    }

    // ── Feature 2: Topic Authority Map ───────────────────────────

    /**
     * Build Pillar → Cluster → FAQ hierarchy.
     */
    public static function build_topic_map(array $seeds, array $covered = []): array {
        $clusters = self::cluster($seeds);
        $map      = [];

        foreach ($clusters as $cl) {
            if (in_array($cl['topic'], $covered, true)) continue;
            $map[] = [
                'pillar'   => $cl['centroid'],
                'topic'    => $cl['topic'],
                'clusters' => array_slice($cl['members'], 1, 5),
                'intent'   => $cl['intent'],
                'covered'  => false,
            ];
        }
        return $map;
    }

    // ── Feature 5: Semantic Duplicate Detection ───────────────────

    /**
     * Check if keyword is semantically duplicate of existing posts.
     * MUCH more powerful than string matching.
     */
    public static function is_semantic_duplicate(string $keyword, float $threshold = 0.85): bool {
        global $wpdb;
        $titles = $wpdb->get_col(
            "SELECT post_title FROM {$wpdb->posts}
             WHERE post_type='post' AND post_status IN ('publish','draft') LIMIT 100"
        );
        if (empty($titles)) return false;

        $kw_vec     = self::embed($keyword);
        if (empty($kw_vec)) {
            // Fallback string match
            foreach ($titles as $t) {
                similar_text(mb_strtolower($keyword), mb_strtolower($t), $pct);
                if ($pct/100 > 0.75) return true;
            }
            return false;
        }

        $title_vecs = self::batch_embed(array_slice($titles, 0, 30));
        foreach ($title_vecs as $vec) {
            if (self::cosine($kw_vec, $vec) >= $threshold) return true;
        }
        return false;
    }

    // ── Feature 4: Smart Internal Links ──────────────────────────

    /**
     * Find best semantic internal links for a keyword.
     */
    public static function find_internal_links(string $keyword, int $limit = 3): array {
        $memory  = V5_Memory::get_all();
        if (empty($memory)) return [];

        $kw_vec  = self::embed($keyword);
        $scored  = [];

        foreach ($memory as $item) {
            if (empty($item['embedding'])) continue;
            $sim = self::cosine($kw_vec, $item['embedding']);
            if ($sim > 0.35) {
                $scored[] = [
                    'post_id'    => $item['post_id'],
                    'title'      => get_the_title($item['post_id']),
                    'keyword'    => $item['keyword'],
                    'url'        => get_permalink($item['post_id']),
                    'similarity' => $sim,
                ];
            }
        }

        usort($scored, fn($a,$b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($scored, 0, $limit);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public static function extract_topic(string $kw): string {
        $map = ['bagno'=>'bagno','cucina'=>'cucina','paviment'=>'pavimenti','imbiancatura'=>'imbiancatura',
                'cartongesso'=>'cartongesso','impianti'=>'impianti','ristrutturazione'=>'ristrutturazione',
                'bonus'=>'bonus','detrazione'=>'bonus','facciata'=>'facciata'];
        $l = mb_strtolower($kw);
        foreach ($map as $key => $topic) { if (strpos($l, $key) !== false) return $topic; }
        $words = array_filter(explode(' ', $l), fn($w) => mb_strlen($w) > 4);
        return reset($words) ?: $kw;
    }

    public static function detect_intent(string $kw): string {
        $l = mb_strtolower($kw);
        if (preg_match('/preventivo|prezzi?|costo?|impresa|ditta|migliore/', $l)) return 'commercial';
        if (preg_match('/bonus|detrazione|permess|guida|come/', $l)) return 'info-commercial';
        return 'informational';
    }
}
