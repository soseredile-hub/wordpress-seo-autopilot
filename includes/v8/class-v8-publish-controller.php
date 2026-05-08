<?php
defined('ABSPATH') || exit;

/**
 * V8_PublishController — Full publish workflow.
 *
 * Modes:
 *   publish   → live immediately
 *   draft     → save as draft for manual review
 *   review    → pending review (editors can approve)
 *   schedule  → publish at specific date/time
 *   smart     → AI decides based on score + time of day
 */
class V8_PublishController {

    const OPT = 'soser_v8_publish_cfg';

    // ── Config ────────────────────────────────────────────────────

    public static function get_config(): array {
        return array_merge([
            'mode'            => 'publish',   // publish|draft|review|schedule|smart
            'schedule_time'   => '09:00',     // HH:MM for scheduled posts
            'schedule_days'   => ['mon','wed','fri'], // days to publish
            'min_score'       => '60',        // minimum content score to auto-publish
            'notify_email'    => '',          // email on draft/review ready
            'notify_enabled'  => '0',
            'author_id'       => '1',
        ], get_option(self::OPT, []));
    }

    public static function save_config(array $data): void {
        $clean = [];
        $clean['mode']           = sanitize_text_field($data['mode'] ?? 'publish');
        $clean['schedule_time']  = sanitize_text_field($data['schedule_time'] ?? '09:00');
        $clean['schedule_days']  = is_array($data['schedule_days'] ?? null) ? array_map('sanitize_text_field', $data['schedule_days']) : ['mon','wed','fri'];
        $clean['min_score']      = (int) ($data['min_score'] ?? 60);
        $clean['notify_email']   = sanitize_email($data['notify_email'] ?? '');
        $clean['notify_enabled'] = !empty($data['notify_enabled']) ? '1' : '0';
        $clean['author_id']      = (int) ($data['author_id'] ?? 1);
        update_option(self::OPT, $clean);
    }

    // ── Determine post_status for a new article ───────────────────

    /**
     * Returns ['status' => string, 'date' => string|null]
     * date is only set for scheduled posts (MySQL datetime format)
     */
    public static function resolve(int $content_score = 0): array {
        $cfg  = self::get_config();
        $mode = $cfg['mode'];

        switch ($mode) {
            case 'draft':
                return ['status' => 'draft', 'date' => null];

            case 'review':
                return ['status' => 'pending', 'date' => null];

            case 'schedule':
                $date = self::next_schedule_slot($cfg);
                return ['status' => 'future', 'date' => $date];

            case 'smart':
                return self::smart_resolve($content_score, $cfg);

            case 'publish':
            default:
                return ['status' => 'publish', 'date' => null];
        }
    }

    // ── Smart mode: AI decides ────────────────────────────────────

    private static function smart_resolve(int $score, array $cfg): array {
        $min = (int) $cfg['min_score'];

        // Below minimum score → draft for review
        if ($score > 0 && $score < $min) {
            return ['status' => 'draft', 'date' => null];
        }

        // Good score → schedule for next optimal slot
        $date = self::next_schedule_slot($cfg);
        return ['status' => 'future', 'date' => $date];
    }

    // ── Calculate next schedule slot ─────────────────────────────

    public static function next_schedule_slot(array $cfg = []): string {
        if (empty($cfg)) $cfg = self::get_config();

        $time_parts = explode(':', $cfg['schedule_time'] ?: '09:00');
        $hour       = (int) ($time_parts[0] ?? 9);
        $minute     = (int) ($time_parts[1] ?? 0);

        $days_map   = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
        $allowed    = array_map(fn($d) => $days_map[$d] ?? -1, $cfg['schedule_days'] ?: ['mon','wed','fri']);
        $allowed    = array_filter($allowed, fn($d) => $d >= 0);

        if (empty($allowed)) {
            // Fallback: tomorrow at schedule time
            $ts = strtotime('tomorrow') + $hour * 3600 + $minute * 60;
            return get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts));
        }

        // Find next allowed day
        $now     = current_time('timestamp');
        $today   = (int) date('w', $now);
        $now_hm  = date('Hi', $now);
        $sched_hm= str_pad($hour, 2, '0', STR_PAD_LEFT) . str_pad($minute, 2, '0', STR_PAD_LEFT);

        // Try today if we're before schedule time and today is allowed
        if (in_array($today, $allowed) && $now_hm < $sched_hm) {
            $ts = mktime($hour, $minute, 0, date('n', $now), date('j', $now), date('Y', $now));
            return get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts));
        }

        // Find next allowed weekday
        for ($i = 1; $i <= 7; $i++) {
            $next_day = ($today + $i) % 7;
            if (in_array($next_day, $allowed)) {
                $ts = strtotime("+{$i} days", $now);
                $ts = mktime($hour, $minute, 0, date('n', $ts), date('j', $ts), date('Y', $ts));
                return get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts));
            }
        }

        // Fallback
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $now + 86400));
    }

    // ── Apply to wp_insert_post args ─────────────────────────────

    /**
     * Returns array to merge into wp_insert_post() call
     */
    public static function get_post_args(int $content_score = 0): array {
        $resolved = self::resolve($content_score);
        $args     = ['post_status' => $resolved['status']];

        if ($resolved['status'] === 'future' && $resolved['date']) {
            $args['post_date']     = $resolved['date'];
            $args['post_date_gmt'] = get_gmt_from_date($resolved['date']);
        }

        $cfg = self::get_config();
        if (!empty($cfg['author_id'])) {
            $args['post_author'] = (int) $cfg['author_id'];
        }

        return $args;
    }

    // ── Notify on draft/review ready ─────────────────────────────

    public static function notify_ready(int $post_id, string $status): void {
        $cfg = self::get_config();
        if ($cfg['notify_enabled'] !== '1' || empty($cfg['notify_email'])) return;
        if (!in_array($status, ['draft', 'pending'])) return;

        $post  = get_post($post_id);
        if (!$post) return;

        $kw    = get_post_meta($post_id, '_soser_focus_keyword', true);
        $site  = get_bloginfo('name');
        $edit  = get_edit_post_link($post_id, 'raw');
        $label = $status === 'pending' ? 'In revisione' : 'Bozza';

        $subject = "[{$site}] Nuovo articolo {$label}: {$post->post_title}";
        $body    = "<h2>Nuovo articolo generato — {$label}</h2>"
                 . "<p><strong>Titolo:</strong> {$post->post_title}</p>"
                 . "<p><strong>Keyword:</strong> {$kw}</p>"
                 . "<p><strong>Stato:</strong> {$label}</p>"
                 . "<p><a href='{$edit}' style='background:#e87c2a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold'>Rivedi e Pubblica →</a></p>";

        wp_mail($cfg['notify_email'], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    // ── Queue all drafts for publishing ──────────────────────────

    public static function publish_drafts(int $limit = 5): int {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => ['draft', 'pending'],
            'numberposts' => $limit,
            'meta_key'    => '_soser_focus_keyword',
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]);
        $count = 0;
        foreach ($posts as $post) {
            $date = self::next_schedule_slot();
            wp_update_post(['ID' => $post->ID, 'post_status' => 'future', 'post_date' => $date]);
            $count++;
        }
        return $count;
    }

    // ── Stats ─────────────────────────────────────────────────────

    public static function stats(): array {
        global $wpdb;
        return [
            'published' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_soser_focus_keyword' AND p.post_status='publish'"),
            'draft'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_soser_focus_keyword' AND p.post_status='draft'"),
            'pending'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_soser_focus_keyword' AND p.post_status='pending'"),
            'scheduled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_soser_focus_keyword' AND p.post_status='future'"),
        ];
    }
}
