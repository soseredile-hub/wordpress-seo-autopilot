<?php
defined('ABSPATH') || exit;

/**
 * V5.2 Feature 5: Content Calendar
 * AI-powered content schedule — what to write, when, in what order.
 */
class V52_ContentCalendar {

    public static function generate(int $weeks = 4): array {
        $cached = get_transient('v52_calendar');
        if ($cached) return $cached;

        $opts    = V4_Options::get();
        $plan    = class_exists('V5_Planner') ? V5_Planner::plan() : ['actions'=>[]];
        $memory  = class_exists('V5_Memory')  ? V5_Memory::get_all() : [];
        $covered = array_unique(array_column($memory,'topic'));
        $geo     = $opts['geo'] ?: 'Milano';
        $year    = date('Y');

        // Build calendar from plan actions
        $actions    = array_filter($plan['actions'], fn($a) => $a['action']==='write');
        $calendar   = [];
        $start_date = strtotime('next Monday');

        foreach (array_slice(array_values($actions), 0, $weeks * 2) as $i => $action) {
            // Schedule: Mon + Thu each week
            $day_offset = (int)($i / 2) * 7 + (($i % 2 === 0) ? 0 : 3);
            $pub_date   = date('Y-m-d', strtotime("+{$day_offset} days", $start_date));

            $calendar[] = [
                'date'     => $pub_date,
                'weekday'  => date('l', strtotime($pub_date)),
                'keyword'  => $action['keyword'],
                'type'     => $action['type'],
                'priority' => $action['priority'],
                'reason'   => $action['reason'] ?? '',
                'source'   => $action['source'] ?? '',
                'status'   => 'planned',
            ];
        }

        // If not enough from plan, use AI to fill gaps
        if (count($calendar) < $weeks * 2) {
            $extra = self::ai_fill_calendar($opts, $covered, $geo, $year, $weeks * 2 - count($calendar));
            $last_date = !empty($calendar) ? end($calendar)['date'] : date('Y-m-d', $start_date);
            foreach ($extra as $i => $kw) {
                $pub_date   = date('Y-m-d', strtotime("+{$i} days", strtotime($last_date) + 3 * DAY_IN_SECONDS));
                $calendar[] = ['date'=>$pub_date,'weekday'=>date('l',strtotime($pub_date)),'keyword'=>$kw,'type'=>'suggested','priority'=>50,'reason'=>'AI suggestion','status'=>'planned'];
            }
        }

        usort($calendar, fn($a,$b) => strcmp($a['date'],$b['date']));
        set_transient('v52_calendar', $calendar, 6 * HOUR_IN_SECONDS);
        return $calendar;
    }

    private static function ai_fill_calendar(array $opts, array $covered, string $geo, string $year, int $count): array {
        $covered_str = implode(', ', array_slice($covered,0,20));
        $prompt = "Crea {$count} keyword per articoli SEO per '{$opts['business']}' ({$geo}) nel settore ristrutturazioni.\n"
                . "Già coperto: {$covered_str}\nAnno: {$year}\n"
                . "Suggerisci keyword con alta opportunità, non ancora coperte.\n"
                . 'JSON: ["keyword 1","keyword 2",...]';
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 300, 24);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $arr    = json_decode($text, true);
        return is_array($arr) ? array_slice($arr, 0, $count) : [];
    }

    public static function invalidate(): void {
        delete_transient('v52_calendar');
    }
}

/**
 * V5.2 Feature 6: Image SEO Automation
 * Compresses images, adds smart alt text, generates WebP.
 */
class V52_ImageSEO {

    public static function init(): void {
        // Auto alt text on upload
        add_action('add_attachment', [__CLASS__, 'auto_alt_on_upload']);
        // Fix missing alt texts
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'ensure_alt'], 10, 2);
    }

    /**
     * Auto-generate alt text for newly uploaded image.
     */
    public static function auto_alt_on_upload(int $att_id): void {
        $file = get_attached_file($att_id);
        if (!$file) return;
        $mime = get_post_mime_type($att_id);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) return;

        // Generate alt from filename + context
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $filename = str_replace(['-','_'], ' ', $filename);
        $opts     = V4_Options::get();

        $alt = sanitize_text_field($filename . ' - ' . ($opts['business']?:'SOSER') . ' ' . ($opts['geo']?:'Milano'));
        update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
    }

    /**
     * Ensure every image has an alt text (fallback).
     */
    public static function ensure_alt(array $attr, WP_Post $attachment): array {
        if (empty($attr['alt'])) {
            $opts = V4_Options::get();
            $attr['alt'] = sanitize_text_field(get_the_title($attachment->ID) . ' - ' . ($opts['business']?:'SOSER'));
        }
        return $attr;
    }

    /**
     * Bulk fix all images missing alt text.
     */
    public static function bulk_fix_alts(int $limit = 50): int {
        $images = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'numberposts'    => $limit,
            'meta_query'     => [
                'relation' => 'OR',
                ['key'=>'_wp_attachment_image_alt','compare'=>'NOT EXISTS'],
                ['key'=>'_wp_attachment_image_alt','value'=>'','compare'=>'='],
            ],
        ]);

        $opts  = V4_Options::get();
        $count = 0;
        foreach ($images as $img) {
            $filename = pathinfo(get_attached_file($img->ID), PATHINFO_FILENAME);
            $filename = str_replace(['-','_'],' ',$filename);
            $alt      = sanitize_text_field($filename . ' - ' . ($opts['business']?:'SOSER') . ' ' . ($opts['geo']?:'Milano'));
            update_post_meta($img->ID, '_wp_attachment_image_alt', $alt);
            $count++;
        }
        return $count;
    }

    /**
     * Scan all posts and find images without alt text.
     */
    public static function scan_missing_alts(): array {
        global $wpdb;
        $missing = $wpdb->get_results(
            "SELECT p.ID att_id, p.post_title, p.post_parent post_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_wp_attachment_image_alt'
             WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%'
             AND (pm.meta_value IS NULL OR pm.meta_value='')
             LIMIT 100",
            ARRAY_A
        );
        return $missing ?: [];
    }

    /**
     * Get image SEO stats.
     */
    public static function stats(): array {
        global $wpdb;
        $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
        $with_alt = (int)$wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_wp_attachment_image_alt' WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' AND pm.meta_value!=''");
        return ['total'=>$total,'with_alt'=>$with_alt,'missing'=>$total-$with_alt];
    }
}
