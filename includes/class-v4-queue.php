<?php
defined('ABSPATH') || exit;

class V4_Queue {

    const CRON_DAILY = 'soser_v4_daily';
    const CRON_QUEUE = 'soser_v4_process_queue';

    public static function init() {
        add_action(self::CRON_DAILY, [__CLASS__, 'cron_daily']);
        add_action(self::CRON_QUEUE, [__CLASS__, 'process_next']);
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = self::table();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword      VARCHAR(255)    NOT NULL DEFAULT '',
            source       VARCHAR(50)     NOT NULL DEFAULT 'manual',
            status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
            step         VARCHAR(40)     NOT NULL DEFAULT 'init',
            post_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
            attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            message      TEXT            NULL,
            payload      LONGTEXT        NULL,
            score_data   TEXT            NULL,
            created_at   DATETIME        NOT NULL,
            updated_at   DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY status_step (status, step)
        ) {$charset};");
    }

    public static function schedule() {
        $o    = V4_Options::get();
        wp_clear_scheduled_hook(self::CRON_DAILY);
        if ($o['cron_enabled'] === '1') {
            $hour  = max(0, min(23, (int) $o['cron_hour']));
            $offset = (int) get_option('gmt_offset', 0) * HOUR_IN_SECONDS;
            $ts    = strtotime('today') - $offset + $hour * HOUR_IN_SECONDS;
            if ($ts <= time()) $ts += DAY_IN_SECONDS;
            wp_schedule_event($ts, 'daily', self::CRON_DAILY);
        }
    }

    public static function unschedule() {
        foreach ([self::CRON_DAILY, self::CRON_QUEUE] as $h) {
            if ($ts = wp_next_scheduled($h)) wp_unschedule_event($ts, $h);
        }
    }

    public static function cron_daily() {
        self::enqueue('', 'cron');
        self::kick();
    }

    public static function enqueue(string $keyword = '', string $source = 'manual', array $score_data = []): int {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(self::table(), [
            'keyword'    => sanitize_text_field($keyword),
            'source'     => sanitize_text_field($source),
            'status'     => 'pending',
            'step'       => 'init',
            'attempts'   => 0,
            'message'    => '',
            'payload'    => '',
            'score_data' => $score_data ? wp_json_encode($score_data) : '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function kick() {
        if (!wp_next_scheduled(self::CRON_QUEUE)) {
            wp_schedule_single_event(time() + 5, self::CRON_QUEUE);
        }
    }

    public static function process_next(int $limit = 1) {
        global $wpdb;
        // Reset stale running jobs > 20 min
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status='pending' WHERE status='running' AND updated_at < %s",
            gmdate('Y-m-d H:i:s', time() - 1200)
        ));

        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE status='pending' ORDER BY id ASC LIMIT %d",
            $limit
        ));

        foreach ($jobs as $job) {
            self::update($job->id, ['status' => 'running', 'attempts' => (int)$job->attempts + 1]);
            $result = V4_Generator::run_step($job);
            if (is_wp_error($result)) {
                self::update($job->id, ['status' => 'failed', 'message' => $result->get_error_message()]);
            }
        }

        // Reschedule if more jobs pending
        $pending = self::stats()['pending'];
        if ($pending > 0) self::kick();
    }

    public static function update(int $id, array $data) {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update(self::table(), $data, ['id' => $id]);
    }

    public static function stats(): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) c FROM " . self::table() . " GROUP BY status", ARRAY_A
        );
        $out = ['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0];
        foreach ((array)$rows as $r) $out[$r['status']] = (int)$r['c'];
        return $out;
    }

    public static function get_log(int $limit = 50): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
        // Normalize: always return array of objects with consistent keys
        return array_map(function($row) {
            $obj = new stdClass();
            $obj->id         = $row['id']         ?? 0;
            $obj->keyword    = $row['keyword']     ?? '';
            $obj->status     = $row['status']      ?? 'pending';
            $obj->step       = $row['step']        ?? 'init';
            $obj->source     = $row['source']      ?? 'manual';
            $obj->payload    = $row['payload']     ?? '';
            $obj->updated_at = $row['updated_at']  ?? '';
            $obj->created_at = $row['created_at']  ?? '';
            $obj->message    = $row['message']     ?? '';
            $obj->post_id    = $row['post_id']     ?? 0;
            $obj->attempts   = $row['attempts']    ?? 0;
            return $obj;
        }, $rows);
    }

    public static function clear_done() {
        global $wpdb;
        $wpdb->query("DELETE FROM " . self::table() . " WHERE status IN ('done','failed')");
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'soser_v4_queue';
    }
}
