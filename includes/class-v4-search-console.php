<?php
defined('ABSPATH') || exit;

/**
 * Google Search Console Integration
 * 
 * Reads real keyword data (clicks, impressions, position, CTR)
 * from your own site via Google Search Console API.
 * 
 * OAuth 2.0 flow:
 *   1. Admin enters Client ID + Secret from Google Cloud Console
 *   2. Clicks "Connect" → redirected to Google for permission
 *   3. Google returns code → plugin exchanges for access token
 *   4. Plugin stores token and uses it for API calls
 */
class V4_Search_Console {

    const OPT_TOKENS  = 'soser_v4_gsc_tokens';
    const OPT_CREDS   = 'soser_v4_gsc_credentials';
    const SCOPE       = 'https://www.googleapis.com/auth/webmasters.readonly';
    const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    const AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    const API_BASE    = 'https://searchconsole.googleapis.com/webmasters/v3';

    // ── Credentials ──────────────────────────────────────────────

    public static function get_credentials(): array {
        return get_option(self::OPT_CREDS, ['client_id' => '', 'client_secret' => '', 'site_url' => '']);
    }

    public static function save_credentials(array $creds): void {
        update_option(self::OPT_CREDS, [
            'client_id'     => sanitize_text_field($creds['client_id'] ?? ''),
            'client_secret' => sanitize_text_field($creds['client_secret'] ?? ''),
            'site_url'      => esc_url_raw($creds['site_url'] ?? get_home_url()),
        ]);
    }

    public static function is_configured(): bool {
        $c = self::get_credentials();
        return !empty($c['client_id']) && !empty($c['client_secret']);
    }

    public static function is_connected(): bool {
        $tokens = get_option(self::OPT_TOKENS, []);
        return !empty($tokens['access_token']) || !empty($tokens['refresh_token']);
    }

    // ── OAuth Flow ───────────────────────────────────────────────

    public static function get_auth_url(): string {
        $creds = self::get_credentials();
        return add_query_arg([
            'client_id'     => $creds['client_id'],
            'redirect_uri'  => self::redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('soser_gsc_oauth'),
        ], self::AUTH_URL);
    }

    public static function redirect_uri(): string {
        return admin_url('admin.php?page=soser-v4-gsc&oauth=callback');
    }

    /** Handle OAuth callback from Google */
    public static function handle_callback(string $code, string $state): bool|WP_Error {
        if (!wp_verify_nonce($state, 'soser_gsc_oauth')) {
            return new WP_Error('nonce', 'Invalid state parameter.');
        }
        $creds = self::get_credentials();
        $r = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 20,
            'body'    => [
                'code'          => $code,
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'redirect_uri'  => self::redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);
        if (is_wp_error($r)) return $r;
        $data = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($data['access_token'])) {
            return new WP_Error('token', $data['error_description'] ?? 'Token exchange failed.');
        }
        update_option(self::OPT_TOKENS, [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_at'    => time() + (int)($data['expires_in'] ?? 3600),
        ]);
        return true;
    }

    /** Refresh access token if expired */
    private static function get_access_token(): string|WP_Error {
        $tokens = get_option(self::OPT_TOKENS, []);
        if (empty($tokens['access_token'])) {
            return new WP_Error('no_token', 'Not connected to Google Search Console.');
        }
        // Refresh if expired (with 5 min buffer)
        if (!empty($tokens['refresh_token']) && time() > ($tokens['expires_at'] - 300)) {
            $creds = self::get_credentials();
            $r = wp_remote_post(self::TOKEN_URL, [
                'timeout' => 15,
                'body'    => [
                    'refresh_token' => $tokens['refresh_token'],
                    'client_id'     => $creds['client_id'],
                    'client_secret' => $creds['client_secret'],
                    'grant_type'    => 'refresh_token',
                ],
            ]);
            if (!is_wp_error($r)) {
                $data = json_decode(wp_remote_retrieve_body($r), true);
                if (!empty($data['access_token'])) {
                    $tokens['access_token'] = $data['access_token'];
                    $tokens['expires_at']   = time() + (int)($data['expires_in'] ?? 3600);
                    update_option(self::OPT_TOKENS, $tokens);
                }
            }
        }
        return $tokens['access_token'];
    }

    public static function disconnect(): void {
        delete_option(self::OPT_TOKENS);
        delete_transient('soser_v4_gsc_queries');
        delete_transient('soser_v4_gsc_performance');
    }

    // ── API Calls ────────────────────────────────────────────────

    /**
     * Get top queries with clicks, impressions, CTR, position.
     * @param int $days  Number of days back (max 16 months)
     * @param int $limit Max rows to return
     */
    public static function get_top_queries(int $days = 90, int $limit = 100): array|WP_Error {
        $cache_key = "soser_v4_gsc_queries_{$days}_{$limit}";
        $cached    = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $token = self::get_access_token();
        if (is_wp_error($token)) return $token;

        $creds    = self::get_credentials();
        $site_url = $creds['site_url'] ?: get_home_url();
        $end      = date('Y-m-d');
        $start    = date('Y-m-d', strtotime("-{$days} days"));

        $r = wp_remote_post(
            self::API_BASE . '/sites/' . rawurlencode($site_url) . '/searchAnalytics/query',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'startDate'  => $start,
                    'endDate'    => $end,
                    'dimensions' => ['query'],
                    'rowLimit'   => $limit,
                    'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
                ]),
            ]
        );

        if (is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);

        if ($code >= 300) {
            return new WP_Error('gsc_api', $body['error']['message'] ?? "HTTP {$code}");
        }

        $rows = $body['rows'] ?? [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'keyword'     => $row['keys'][0] ?? '',
                'clicks'      => (int)   ($row['clicks']      ?? 0),
                'impressions' => (int)   ($row['impressions'] ?? 0),
                'ctr'         => round(  ($row['ctr']         ?? 0) * 100, 1),
                'position'    => round(  ($row['position']    ?? 0), 1),
            ];
        }

        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Get low-hanging fruit: keywords with impressions but low position (11-30).
     * These are on page 2-3 and can be boosted with a new article.
     */
    public static function get_opportunities(int $days = 90): array|WP_Error {
        $queries = self::get_top_queries($days, 500);
        if (is_wp_error($queries)) return $queries;

        $opportunities = [];
        foreach ($queries as $q) {
            // Position 8-30 = not ranking high enough, worth targeting
            if ($q['position'] >= 8 && $q['position'] <= 30 && $q['impressions'] >= 10) {
                $score = self::opportunity_score($q);
                $opportunities[] = array_merge($q, ['opportunity_score' => $score]);
            }
        }

        // Sort by opportunity score
        usort($opportunities, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);
        return array_slice($opportunities, 0, 50);
    }

    /**
     * Get keywords with zero/low clicks but high impressions.
     * Great for new articles.
     */
    public static function get_missing_content(int $days = 90): array|WP_Error {
        $queries = self::get_top_queries($days, 500);
        if (is_wp_error($queries)) return $queries;

        $missing = [];
        foreach ($queries as $q) {
            // High impressions but low CTR = missing or weak content
            if ($q['impressions'] >= 50 && $q['ctr'] < 3.0 && !V4_Content_Scanner::is_covered($q['keyword'])) {
                $missing[] = $q;
            }
        }
        usort($missing, fn($a, $b) => $b['impressions'] <=> $a['impressions']);
        return array_slice($missing, 0, 30);
    }

    /**
     * Get site performance summary (last 28 days vs previous 28 days).
     */
    public static function get_performance_summary(): array|WP_Error {
        $cached = get_transient('soser_v4_gsc_performance');
        if ($cached) return $cached;

        $token = self::get_access_token();
        if (is_wp_error($token)) return $token;

        $creds    = self::get_credentials();
        $site_url = $creds['site_url'] ?: get_home_url();

        // Last 28 days
        $end      = date('Y-m-d', strtotime('-3 days')); // GSC has ~3 day delay
        $start    = date('Y-m-d', strtotime('-31 days'));
        $prev_end = date('Y-m-d', strtotime('-32 days'));
        $prev_start = date('Y-m-d', strtotime('-59 days'));

        $current  = self::fetch_totals($token, $site_url, $start, $end);
        $previous = self::fetch_totals($token, $site_url, $prev_start, $prev_end);

        if (is_wp_error($current)) return $current;

        $result = [
            'clicks'      => $current['clicks'],
            'impressions' => $current['impressions'],
            'ctr'         => $current['ctr'],
            'position'    => $current['position'],
            'clicks_delta'      => $current['clicks']      - ($previous['clicks']      ?? 0),
            'impressions_delta' => $current['impressions'] - ($previous['impressions'] ?? 0),
        ];

        set_transient('soser_v4_gsc_performance', $result, HOUR_IN_SECONDS);
        return $result;
    }

    private static function fetch_totals(string $token, string $site_url, string $start, string $end): array|WP_Error {
        $r = wp_remote_post(
            self::API_BASE . '/sites/' . rawurlencode($site_url) . '/searchAnalytics/query',
            [
                'timeout' => 15,
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['startDate' => $start, 'endDate' => $end, 'rowLimit' => 1]),
            ]
        );
        if (is_wp_error($r)) return $r;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (wp_remote_retrieve_response_code($r) >= 300) {
            return new WP_Error('gsc', $body['error']['message'] ?? 'API error');
        }
        $row = $body['rows'][0] ?? [];
        return [
            'clicks'      => (int)   ($row['clicks']      ?? 0),
            'impressions' => (int)   ($row['impressions'] ?? 0),
            'ctr'         => round(  ($row['ctr']         ?? 0) * 100, 1),
            'position'    => round(  ($row['position']    ?? 0), 1),
        ];
    }

    // ── Scoring ──────────────────────────────────────────────────

    private static function opportunity_score(array $q): int {
        $score = 0;
        // High impressions = real search demand
        if ($q['impressions'] >= 1000) $score += 30;
        elseif ($q['impressions'] >= 100) $score += 20;
        elseif ($q['impressions'] >= 10) $score += 10;
        // Position 11-20 = page 2, easiest to move to page 1
        if ($q['position'] <= 20) $score += 25;
        elseif ($q['position'] <= 30) $score += 15;
        // Low CTR at good position = meta needs improvement
        if ($q['ctr'] < 2.0 && $q['position'] <= 15) $score += 20;
        // Clicks already = proven keyword
        if ($q['clicks'] >= 10) $score += 15;
        return min(100, $score);
    }

    // ── Integration with Keyword Intel ───────────────────────────

    /**
     * Returns GSC keywords formatted for V4_Keyword_Intel scoring.
     * These have real volume data — prioritized over heuristic estimates.
     */
    public static function get_intel_keywords(): array {
        if (!self::is_connected()) return [];
        $opps = self::get_opportunities();
        if (is_wp_error($opps)) return [];
        $result = [];
        foreach ($opps as $q) {
            $result[] = [
                'keyword'          => $q['keyword'],
                'covered'          => V4_Content_Scanner::is_covered($q['keyword']),
                'volume_estimate'  => $q['impressions'] >= 1000 ? '🔥 Alto (GSC)' : ($q['impressions'] >= 100 ? '📈 Medio (GSC)' : '📊 Basso (GSC)'),
                'volume_score'     => min(40, (int)($q['impressions'] / 50)),
                'local_score'      => 0,
                'commercial_score' => 0,
                'difficulty_score' => max(0, 15 - (int)(($q['position'] - 8) / 2)),
                'similarity_penalty' => 0,
                'opportunity_score'  => $q['opportunity_score'],
                'intent'           => '📊 Da GSC (reale)',
                'cpc_estimate'     => '— (GSC)',
                'recommendation'   => $q['opportunity_score'] >= 60 ? '✅ Scrivi subito' : '👍 Considera',
                'source'           => 'gsc',
                'clicks'           => $q['clicks'],
                'impressions'      => $q['impressions'],
                'ctr'              => $q['ctr'],
                'position'         => $q['position'],
            ];
        }
        return $result;
    }

    public static function clear_cache(): void {
        delete_transient('soser_v4_gsc_queries_90_100');
        delete_transient('soser_v4_gsc_queries_90_500');
        delete_transient('soser_v4_gsc_performance');
    }
}
