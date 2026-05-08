<?php
defined('ABSPATH') || exit;

/**
 * V5.2 Feature 2: Auto Internal Linking for ALL existing posts
 * Goes through every published post and adds relevant internal links.
 */
class V52_AutoLinker {

    /**
     * Process all published posts and add internal links.
     * Run in batches to avoid timeout.
     */
    public static function process_batch(int $batch = 5): array {
        $results = ['processed'=>0,'linked'=>0,'skipped'=>0];

        // Get posts that haven't been processed yet
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $batch,
            'meta_query'  => [
                ['key'=>'_v52_autolinked','compare'=>'NOT EXISTS'],
            ],
        ]);

        if (empty($posts)) {
            // Reset flags to re-process all (monthly refresh)
            $last_reset = get_option('v52_autolink_reset',0);
            if (time() - $last_reset > 30 * DAY_IN_SECONDS) {
                global $wpdb;
                $wpdb->delete($wpdb->postmeta,['meta_key'=>'_v52_autolinked']);
                update_option('v52_autolink_reset', time());
            }
            return $results;
        }

        // Build keyword → URL map from all published posts
        $link_map = self::build_link_map();

        foreach ($posts as $post) {
            $results['processed']++;
            $new_content = self::inject_links($post->post_content, $link_map, $post->ID);

            if ($new_content !== $post->post_content) {
                wp_update_post(['ID'=>$post->ID,'post_content'=>$new_content]);
                $results['linked']++;
            } else {
                $results['skipped']++;
            }
            update_post_meta($post->ID,'_v52_autolinked', current_time('mysql'));
        }

        return $results;
    }

    /**
     * Build a map of keyword → URL from all posts.
     */
    private static function build_link_map(): array {
        $cached = get_transient('v52_link_map');
        if ($cached) return $cached;

        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 200,
        ]);

        $map = [];
        foreach ($posts as $post) {
            $kw = get_post_meta($post->ID,'_soser_focus_keyword',true) ?: '';
            if (!$kw || mb_strlen($kw) < 5) continue;
            $map[mb_strtolower($kw)] = [
                'url'   => get_permalink($post->ID),
                'title' => $post->post_title,
                'id'    => $post->ID,
            ];
            // Also add shorter version (first 2-3 words)
            $words = explode(' ', mb_strtolower($kw));
            if (count($words) >= 3) {
                $short = implode(' ', array_slice($words, 0, 3));
                if (!isset($map[$short])) {
                    $map[$short] = $map[mb_strtolower($kw)];
                }
            }
        }

        // Sort by keyword length (longer first = more specific)
        uksort($map, fn($a,$b) => mb_strlen($b) <=> mb_strlen($a));
        set_transient('v52_link_map', $map, 4 * HOUR_IN_SECONDS);
        return $map;
    }

    /**
     * Inject internal links into content.
     * Max 3 links per post, no self-links, no duplicate anchors.
     */
    private static function inject_links(string $content, array $link_map, int $current_id): string {
        $added       = 0;
        $max_links   = 3;
        $used_urls   = [];
        $plain_text  = wp_strip_all_tags($content);

        // Extract already linked URLs to avoid duplicates
        preg_match_all('/href=["\']([^"\']+)["\']/', $content, $existing);
        $already_linked = $existing[1] ?? [];

        foreach ($link_map as $kw => $data) {
            if ($added >= $max_links) break;
            if ($data['id'] === $current_id) continue;
            if (in_array($data['url'], $already_linked, true)) continue;
            if (in_array($data['url'], $used_urls, true)) continue;

            // Check if keyword exists in content (case-insensitive)
            if (stripos($plain_text, $kw) === false) continue;

            // Don't link inside existing links or headings
            $pattern = '/(?<!<a[^>]*?>)(?<![">])(' . preg_quote($kw, '/') . ')(?![^<]*?<\/a>)(?![^<]*?>)/iu';

            $new_content = preg_replace(
                $pattern,
                '<a href="' . esc_url($data['url']) . '">' . '$1' . '</a>',
                $content,
                1 // Replace only first occurrence
            );

            if ($new_content && $new_content !== $content) {
                $content     = $new_content;
                $plain_text  = wp_strip_all_tags($content);
                $used_urls[] = $data['url'];
                $added++;
            }
        }

        return $content;
    }

    public static function invalidate_map(): void {
        delete_transient('v52_link_map');
    }

    public static function reset_all(): void {
        global $wpdb;
        $wpdb->delete($wpdb->postmeta,['meta_key'=>'_v52_autolinked']);
        self::invalidate_map();
    }
}
