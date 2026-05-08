<?php
defined('ABSPATH') || exit;

/**
 * V9 Feature 3: AI Memory Engine
 * Remembers every article: keyword, topic, intent, performance, embedding.
 * All agents use this to make smart decisions.
 * ZERO frontend impact — only reads/writes in admin/cron.
 */
class V5_Memory {

    const TABLE = 'soser_v5_memory';

    public static function create_table(): void {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE IF NOT EXISTS {$t} (
            id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            post_id       BIGINT UNSIGNED  NOT NULL,
            keyword       VARCHAR(255)     NOT NULL DEFAULT '',
            topic         VARCHAR(100)     NOT NULL DEFAULT '',
            intent        VARCHAR(50)      NOT NULL DEFAULT '',
            cluster       VARCHAR(100)     NOT NULL DEFAULT '',
            word_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            seo_score     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            embedding     LONGTEXT         NULL,
            gsc_clicks    INT UNSIGNED     NOT NULL DEFAULT 0,
            gsc_impr      INT UNSIGNED     NOT NULL DEFAULT 0,
            gsc_ctr       FLOAT            NOT NULL DEFAULT 0,
            gsc_position  FLOAT            NOT NULL DEFAULT 0,
            needs_refresh TINYINT(1)       NOT NULL DEFAULT 0,
            created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY topic (topic),
            KEY needs_refresh (needs_refresh)
        ) {$wpdb->get_charset_collate()};");
    }

    // ── Write ─────────────────────────────────────────────────────

    public static function remember(int $post_id, string $keyword, array $meta = []): void {
        global $wpdb;
        $topic     = $meta['topic'] ?? V5_Semantic::extract_topic($keyword);
        $intent    = $meta['intent'] ?? V5_Semantic::detect_intent($keyword);
        $embedding = V5_Semantic::embed($keyword . ' ' . get_the_title($post_id));
        $now       = current_time('mysql');

        $wpdb->replace($wpdb->prefix . self::TABLE, [
            'post_id'    => $post_id,
            'keyword'    => sanitize_text_field($keyword),
            'topic'      => sanitize_text_field($topic),
            'intent'     => sanitize_text_field($intent),
            'word_count' => (int)($meta['word_count'] ?? str_word_count(wp_strip_all_tags(get_post_field('post_content', $post_id)))),
            'seo_score'  => (int)($meta['seo_score'] ?? 0),
            'embedding'  => $embedding ? wp_json_encode($embedding) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        delete_transient('v5_memory_all');
    }

    public static function update_gsc(int $post_id, array $d): void {
        global $wpdb;
        $needs = 0;
        if (($d['position'] ?? 0) > 15 || (($d['impressions'] ?? 0) > 100 && ($d['ctr'] ?? 0) < 0.02)) {
            $needs = 1;
        }
        $wpdb->update($wpdb->prefix . self::TABLE, [
            'gsc_clicks'   => (int)($d['clicks'] ?? 0),
            'gsc_impr'     => (int)($d['impressions'] ?? 0),
            'gsc_ctr'      => (float)($d['ctr'] ?? 0),
            'gsc_position' => (float)($d['position'] ?? 0),
            'needs_refresh'=> $needs,
            'updated_at'   => current_time('mysql'),
        ], ['post_id' => $post_id]);
        delete_transient('v5_memory_all');
    }

    // ── Read ──────────────────────────────────────────────────────

    public static function get(int $post_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . self::TABLE . " WHERE post_id=%d", $post_id
        ), ARRAY_A);
        if (!$row) return null;
        if ($row['embedding']) $row['embedding'] = json_decode($row['embedding'], true);
        return $row;
    }

    public static function get_all(): array {
        $cached = get_transient('v5_memory_all');
        if ($cached !== false) return $cached;

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT post_id,keyword,topic,intent,word_count,seo_score,
                    gsc_clicks,gsc_impr,gsc_ctr,gsc_position,needs_refresh,embedding
             FROM " . $wpdb->prefix . self::TABLE . " ORDER BY id DESC LIMIT 500",
            ARRAY_A
        ) ?: [];

        foreach ($rows as &$row) {
            if ($row['embedding']) $row['embedding'] = json_decode($row['embedding'], true);
        }

        set_transient('v5_memory_all', $rows, HOUR_IN_SECONDS);
        return $rows;
    }

    public static function get_topics_covered(): array {
        return array_unique(array_filter(array_column(self::get_all(), 'topic')));
    }

    public static function get_needs_refresh(int $limit = 10): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . self::TABLE . " WHERE needs_refresh=1 ORDER BY gsc_impr DESC LIMIT %d", $limit
        ), ARRAY_A) ?: [];
    }

    public static function stats(): array {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT COUNT(*) total, SUM(word_count) words, AVG(seo_score) avg_score,
                    SUM(gsc_clicks) clicks, SUM(gsc_impr) impressions, SUM(needs_refresh) to_refresh
             FROM " . $wpdb->prefix . self::TABLE,
            ARRAY_A
        );
        return $row ?? [];
    }

    /** Sync memory from existing posts (run on activation) */
    public static function sync(): int {
        $posts = get_posts(['post_type'=>'post','post_status'=>['publish','draft'],'numberposts'=>500]);
        $count = 0;
        foreach ($posts as $post) {
            if (self::get($post->ID)) continue;
            $kw = get_post_meta($post->ID,'_soser_focus_keyword',true)
               ?: get_post_meta($post->ID,'_yoast_wpseo_focuskw',true)
               ?: $post->post_title;
            self::remember($post->ID, $kw, [
                'word_count' => str_word_count(wp_strip_all_tags($post->post_content))
            ]);
            $count++;
        }
        return $count;
    }
}
