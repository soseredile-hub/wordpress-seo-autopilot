<?php
defined('ABSPATH') || exit;

/**
 * V8_SEOAutofill — Auto-fills ALL major SEO plugins.
 *
 * Supported:
 *   - Yoast SEO (free + premium)
 *   - RankMath
 *   - AIOSEO (All in One SEO)
 *   - The SEO Framework
 *   - SEOPress
 *   - Squirrly SEO
 */
class V8_SEOAutofill {

    /**
     * Fill all detected SEO plugins for a post.
     * Called after wp_insert_post and after AI Refresh.
     */
    public static function fill(int $post_id, array $article, string $keyword): void {
        if ($post_id <= 0) return;

        $title       = $article['seo_title']        ?? $article['title']           ?? '';
        $desc        = $article['meta_description']  ?? $article['excerpt']         ?? '';
        $focus_kw    = $keyword;
        $og_title    = $title;
        $og_desc     = $desc;

        // Detect and fill each plugin
        self::fill_yoast($post_id, $title, $desc, $focus_kw);
        self::fill_rankmath($post_id, $title, $desc, $focus_kw);
        self::fill_aioseo($post_id, $title, $desc, $focus_kw);
        self::fill_seo_framework($post_id, $title, $desc, $focus_kw);
        self::fill_seopress($post_id, $title, $desc, $focus_kw);

        // Open Graph (works regardless of SEO plugin)
        self::fill_og($post_id, $og_title, $og_desc, $article);

        // Mark as filled
        update_post_meta($post_id, '_v8_seo_filled', date('Y-m-d H:i:s'));
    }

    // ── Yoast SEO ─────────────────────────────────────────────────

    private static function fill_yoast(int $pid, string $title, string $desc, string $kw): void {
        // Focus keyword
        update_post_meta($pid, '_yoast_wpseo_focuskw',      $kw);
        // SEO title (Yoast format: use %%title%% or raw)
        update_post_meta($pid, '_yoast_wpseo_title',        $title);
        // Meta description
        update_post_meta($pid, '_yoast_wpseo_metadesc',     $desc);
        // Mark as not analysed (forces Yoast to re-evaluate)
        update_post_meta($pid, '_yoast_wpseo_linkdex',      '0');
        update_post_meta($pid, '_yoast_wpseo_content_score','0');
        // Canonical (use default)
        delete_post_meta($pid, '_yoast_wpseo_canonical');
        // Mark as cornerstone if score >= 80
        $score = (int) get_post_meta($pid, '_soser_content_score', true);
        if ($score >= 80) {
            update_post_meta($pid, '_yoast_wpseo_is_cornerstone', '1');
        }
        // Twitter/OG via Yoast
        update_post_meta($pid, '_yoast_wpseo_twitter-title',       $title);
        update_post_meta($pid, '_yoast_wpseo_twitter-description', $desc);
        update_post_meta($pid, '_yoast_wpseo_opengraph-title',     $title);
        update_post_meta($pid, '_yoast_wpseo_opengraph-description', $desc);
    }

    // ── RankMath ──────────────────────────────────────────────────

    private static function fill_rankmath(int $pid, string $title, string $desc, string $kw): void {
        update_post_meta($pid, 'rank_math_focus_keyword',   $kw);
        update_post_meta($pid, 'rank_math_title',           $title);
        update_post_meta($pid, 'rank_math_description',     $desc);
        // Advanced settings
        update_post_meta($pid, 'rank_math_robots',          []);        // use defaults
        update_post_meta($pid, 'rank_math_canonical_url',   '');        // auto
        // OG
        update_post_meta($pid, 'rank_math_facebook_title',       $title);
        update_post_meta($pid, 'rank_math_facebook_description', $desc);
        update_post_meta($pid, 'rank_math_twitter_use_facebook', 'on');
        // Schema type
        update_post_meta($pid, 'rank_math_rich_snippet',    'article');
    }

    // ── All in One SEO (AIOSEO) ───────────────────────────────────

    private static function fill_aioseo(int $pid, string $title, string $desc, string $kw): void {
        // AIOSEO stores data in wp_aioseo_posts table AND postmeta
        update_post_meta($pid, '_aioseo_title',             $title);
        update_post_meta($pid, '_aioseo_description',       $desc);
        update_post_meta($pid, '_aioseo_keywords',          $kw);
        update_post_meta($pid, '_aioseo_og_title',          $title);
        update_post_meta($pid, '_aioseo_og_description',    $desc);
        update_post_meta($pid, '_aioseo_twitter_title',     $title);
        update_post_meta($pid, '_aioseo_twitter_description',$desc);

        // Write to AIOSEO custom table if plugin is active
        if (function_exists('aioseo') || defined('AIOSEO_VERSION')) {
            global $wpdb;
            $table = $wpdb->prefix . 'aioseo_posts';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $wpdb->replace($table, [
                    'post_id'           => $pid,
                    'title'             => $title,
                    'description'       => $desc,
                    'keywords'          => wp_json_encode([$kw]),
                    'og_title'          => $title,
                    'og_description'    => $desc,
                    'twitter_title'     => $title,
                    'twitter_description'=> $desc,
                    'created'           => current_time('mysql'),
                    'updated'           => current_time('mysql'),
                ]);
            }
        }
    }

    // ── The SEO Framework ─────────────────────────────────────────

    private static function fill_seo_framework(int $pid, string $title, string $desc, string $kw): void {
        // TSF stores all data in a single serialized postmeta key
        $current = get_post_meta($pid, '_genesis_title', true);
        if (!$current) {
            // TSF v4+ uses individual keys
            update_post_meta($pid, '_genesis_title',       $title);
            update_post_meta($pid, '_genesis_description', $desc);
            update_post_meta($pid, '_genesis_keywords',    $kw);
            update_post_meta($pid, '_open_graph_title',    $title);
            update_post_meta($pid, '_open_graph_description', $desc);
            update_post_meta($pid, '_twitter_title',       $title);
            update_post_meta($pid, '_twitter_description', $desc);
        }
        // TSF v5+ uses tsf_meta
        $tsf_data = get_post_meta($pid, 'tsf_meta', true);
        if (is_array($tsf_data) || !$tsf_data) {
            update_post_meta($pid, 'tsf_meta', array_merge(
                is_array($tsf_data) ? $tsf_data : [],
                [
                    'doctitle'          => $title,
                    'description'       => $desc,
                    'og_title'          => $title,
                    'og_description'    => $desc,
                    'tw_title'          => $title,
                    'tw_description'    => $desc,
                ]
            ));
        }
    }

    // ── SEOPress ──────────────────────────────────────────────────

    private static function fill_seopress(int $pid, string $title, string $desc, string $kw): void {
        update_post_meta($pid, '_seopress_titles_title',        $title);
        update_post_meta($pid, '_seopress_titles_desc',         $desc);
        update_post_meta($pid, '_seopress_analysis_target_kw',  $kw);
        update_post_meta($pid, '_seopress_social_fb_title',     $title);
        update_post_meta($pid, '_seopress_social_fb_desc',      $desc);
        update_post_meta($pid, '_seopress_social_twitter_title',$title);
        update_post_meta($pid, '_seopress_social_twitter_desc', $desc);
    }

    // ── Open Graph (generic) ──────────────────────────────────────

    private static function fill_og(int $pid, string $title, string $desc, array $article): void {
        update_post_meta($pid, '_og_title',       $title);
        update_post_meta($pid, '_og_description', $desc);
        // If featured image is set, use for OG image
        $thumb_id = get_post_thumbnail_id($pid);
        if ($thumb_id) {
            $img_url = wp_get_attachment_image_url($thumb_id, 'large');
            if ($img_url) update_post_meta($pid, '_og_image', $img_url);
        }
    }

    // ── Detect which plugins are active ──────────────────────────

    public static function detect_active(): array {
        $active = [];
        if (defined('WPSEO_VERSION') || function_exists('yoast_get_value'))        $active[] = 'Yoast SEO';
        if (defined('RANK_MATH_VERSION') || function_exists('rank_math'))          $active[] = 'RankMath';
        if (defined('AIOSEO_VERSION') || function_exists('aioseo'))                $active[] = 'AIOSEO';
        if (defined('THE_SEO_FRAMEWORK_VERSION') || function_exists('the_seo_framework')) $active[] = 'The SEO Framework';
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_get_service')) $active[] = 'SEOPress';
        return $active;
    }

    // ── Bulk fill existing posts ──────────────────────────────────

    public static function bulk_fill(int $limit = 20): int {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'numberposts'    => $limit,
            'meta_query'     => [
                ['key' => '_soser_focus_keyword', 'compare' => 'EXISTS'],
                ['key' => '_v8_seo_filled',       'compare' => 'NOT EXISTS'],
            ],
        ]);

        $count = 0;
        foreach ($posts as $post) {
            $kw   = get_post_meta($post->ID, '_soser_focus_keyword', true);
            $meta = [
                'seo_title'        => get_post_meta($post->ID, '_yoast_wpseo_title', true) ?: $post->post_title,
                'meta_description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: $post->post_excerpt,
                'title'            => $post->post_title,
                'excerpt'          => $post->post_excerpt,
            ];
            self::fill($post->ID, $meta, $kw);
            $count++;
        }
        return $count;
    }
}
