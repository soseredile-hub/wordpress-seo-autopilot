<?php
defined('ABSPATH') || exit;

/**
 * V7_GA4 — Google Analytics 4 Data API Integration
 * Fetches: sessions, conversions, bounce rate, top pages.
 * Uses GA4 Data API v1 with service account or OAuth2.
 */
class V7_GA4 {

    const OPT_CREDS    = 'soser_v7_ga4_creds';
    const OPT_PROP     = 'soser_v7_ga4_property';
    const OPT_TOKENS   = 'soser_v7_ga4_tokens';
    const CACHE_PREFIX = 'soser_v7_ga4_';

    // ── Config ────────────────────────────────────────────────────

    public static function is_configured(): bool {
        $c = get_option(self::OPT_CREDS, []);
        return !empty($c['client_id']) && !empty($c['client_secret']) && !empty(get_option(self::OPT_PROP));
    }

    public static function is_connected(): bool {
        $t = get_option(self::OPT_TOKENS, []);
        return !empty($t['refresh_token']);
    }

    public static function get_property_id(): string {
        return get_option(self::OPT_PROP, '');
    }

    public static function save_config(string $client_id, string $client_secret, string $property_id): void {
        update_option(self::OPT_CREDS, [
            'client_id'     => sanitize_text_field($client_id),
            'client_secret' => sanitize_text_field($client_secret),
        ]);
        update_option(self::OPT_PROP, sanitize_text_field($property_id));
    }

    // ── OAuth2 (reuses Google OAuth) ──────────────────────────────

    public static function get_auth_url(): string {
        $creds = get_option(self::OPT_CREDS, []);
        $params = [
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => self::get_redirect_uri(),
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/analytics.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('soser_ga4_oauth'),
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public static function get_redirect_uri(): string {
        return admin_url('admin.php?page=soser-v7-analytics&ga4_callback=1');
    }

    public static function exchange_code(string $code): bool {
        $creds = get_option(self::OPT_CREDS, []);
        $r = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'redirect_uri'  => self::get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);
        if (is_wp_error($r)) return false;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['access_token'])) return false;
        update_option(self::OPT_TOKENS, [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + ($body['expires_in'] ?? 3600),
        ]);
        return true;
    }

    private static function get_access_token(): string {
        $tokens = get_option(self::OPT_TOKENS, []);
        if (empty($tokens['refresh_token'])) return '';

        if (!empty($tokens['access_token']) && $tokens['expires_at'] > time() + 60) {
            return $tokens['access_token'];
        }
        // Refresh
        $creds = get_option(self::OPT_CREDS, []);
        $r = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'refresh_token' => $tokens['refresh_token'],
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'grant_type'    => 'refresh_token',
            ],
        ]);
        if (is_wp_error($r)) return '';
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['access_token'])) return '';
        $tokens['access_token'] = $body['access_token'];
        $tokens['expires_at']   = time() + ($body['expires_in'] ?? 3600);
        update_option(self::OPT_TOKENS, $tokens);
        return $body['access_token'];
    }

    public static function disconnect(): void {
        delete_option(self::OPT_TOKENS);
    }

    // ── GA4 Data API calls ────────────────────────────────────────

    /** Run any GA4 report */
    private static function run_report(array $body): array {
        $token    = self::get_access_token();
        $property = self::get_property_id();
        if (!$token || !$property) return [];

        $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";

        $r = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return [];
        return json_decode(wp_remote_retrieve_body($r), true) ?? [];
    }

    // ── Overview (sessions, users, conversions, bounce) ───────────

    public static function get_overview(int $days = 28): array {
        $cache_key = self::CACHE_PREFIX . 'overview_' . $days;
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $data = self::run_report([
            'dateRanges'  => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'metrics'     => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'conversions'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'screenPageViews'],
            ],
        ]);

        if (empty($data['rows'][0]['metricValues'])) return [];

        $vals = array_column($data['rows'][0]['metricValues'], 'value');
        $result = [
            'sessions'         => (int)   $vals[0],
            'users'            => (int)   $vals[1],
            'conversions'      => (int)   $vals[2],
            'bounce_rate'      => round((float) $vals[3] * 100, 1),
            'avg_session'      => self::format_duration((int) $vals[4]),
            'pageviews'        => (int)   $vals[5],
        ];

        set_transient($cache_key, $result, 3 * HOUR_IN_SECONDS);
        return $result;
    }

    // ── Top pages ─────────────────────────────────────────────────

    public static function get_top_pages(int $days = 28, int $limit = 10): array {
        $cache_key = self::CACHE_PREFIX . 'pages_' . $days;
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $data = self::run_report([
            'dateRanges'  => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'dimensions'  => [['name' => 'pagePath'], ['name' => 'pageTitle']],
            'metrics'     => [
                ['name' => 'screenPageViews'],
                ['name' => 'sessions'],
                ['name' => 'conversions'],
                ['name' => 'bounceRate'],
            ],
            'orderBys'    => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'       => $limit,
        ]);

        $rows = $data['rows'] ?? [];
        $result = [];
        foreach ($rows as $row) {
            $dims = array_column($row['dimensionValues'], 'value');
            $vals = array_column($row['metricValues'],    'value');
            $result[] = [
                'path'        => $dims[0],
                'title'       => $dims[1] ?? '',
                'pageviews'   => (int)   $vals[0],
                'sessions'    => (int)   $vals[1],
                'conversions' => (int)   $vals[2],
                'bounce_rate' => round((float) ($vals[3] ?? 0) * 100, 1),
            ];
        }

        set_transient($cache_key, $result, 3 * HOUR_IN_SECONDS);
        return $result;
    }

    // ── Daily sessions trend (for chart) ─────────────────────────

    public static function get_daily_trend(int $days = 28): array {
        $cache_key = self::CACHE_PREFIX . 'trend_' . $days;
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $data = self::run_report([
            'dateRanges'  => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'dimensions'  => [['name' => 'date']],
            'metrics'     => [
                ['name' => 'sessions'],
                ['name' => 'conversions'],
            ],
            'orderBys'    => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
        ]);

        $rows   = $data['rows'] ?? [];
        $result = ['labels' => [], 'sessions' => [], 'conversions' => []];
        foreach ($rows as $row) {
            $date_raw = $row['dimensionValues'][0]['value'] ?? '';
            $label    = strlen($date_raw) === 8
                ? substr($date_raw, 6, 2) . '/' . substr($date_raw, 4, 2)
                : $date_raw;
            $result['labels'][]      = $label;
            $result['sessions'][]    = (int) ($row['metricValues'][0]['value'] ?? 0);
            $result['conversions'][] = (int) ($row['metricValues'][1]['value'] ?? 0);
        }

        set_transient($cache_key, $result, 3 * HOUR_IN_SECONDS);
        return $result;
    }

    // ── Conversions by source ─────────────────────────────────────

    public static function get_conversions_by_source(int $days = 28): array {
        $cache_key = self::CACHE_PREFIX . 'sources_' . $days;
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $data = self::run_report([
            'dateRanges'  => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
            'dimensions'  => [['name' => 'sessionDefaultChannelGroup']],
            'metrics'     => [
                ['name' => 'sessions'],
                ['name' => 'conversions'],
            ],
            'orderBys'    => [['metric' => ['metricName' => 'conversions'], 'desc' => true]],
            'limit'       => 8,
        ]);

        $rows   = $data['rows'] ?? [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'source'      => $row['dimensionValues'][0]['value'] ?? '',
                'sessions'    => (int) ($row['metricValues'][0]['value'] ?? 0),
                'conversions' => (int) ($row['metricValues'][1]['value'] ?? 0),
            ];
        }

        set_transient($cache_key, $result, 3 * HOUR_IN_SECONDS);
        return $result;
    }

    /** Clear all GA4 cache */
    public static function clear_cache(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_soser_v7_ga4_%'");
    }

    private static function format_duration(int $seconds): string {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return "{$m}m {$s}s";
    }
}
