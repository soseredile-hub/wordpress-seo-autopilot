<?php
defined('ABSPATH') || exit;

/**
 * Google Search Console Integration
 *
 * Flow:
 *   1. Admin enters Client ID + Secret from Google Cloud Console
 *   2. Plugin generates OAuth2 authorization URL
 *   3. Admin clicks → authorizes → Google redirects back with code
 *   4. Plugin exchanges code for access_token + refresh_token
 *   5. Refresh token stored securely in DB
 *   6. Plugin fetches real keyword data from GSC API
 */
class V4_GSC {

    const OPT_TOKENS   = 'soser_v4_gsc_tokens';
    const OPT_CREDS    = 'soser_v4_gsc_credentials';
    const REDIRECT_URI_SLUG = 'soser-v4-gsc-callback';

    // ── Credentials ───────────────────────────────────────────────

    public static function get_credentials(): array {
        return get_option(self::OPT_CREDS, ['client_id' => '', 'client_secret' => '']);
    }

    public static function save_credentials(string $client_id, string $client_secret): void {
        update_option(self::OPT_CREDS, [
            'client_id'     => sanitize_text_field($client_id),
            'client_secret' => sanitize_text_field($client_secret),
        ]);
    }

    public static function is_configured(): bool {
        $creds = self::get_credentials();
        return !empty($creds['client_id']) && !empty($creds['client_secret']);
    }

    public static function is_connected(): bool {
        $tokens = get_option(self::OPT_TOKENS, []);
        return !empty($tokens['refresh_token']);
    }

    // ── OAuth2 Flow ───────────────────────────────────────────────

    public static function get_redirect_uri(): string {
        return admin_url('admin.php?page=soser-v4-settings&soser_gsc_callback=1');
    }

    public static function get_auth_url(): string {
        $creds = self::get_credentials();
        $params = [
            'client_id'             => $creds['client_id'],
            'redirect_uri'          => self::get_redirect_uri(),
            'response_type'         => 'code',
            'scope'                 => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => wp_create_nonce('soser_gsc_oauth'),
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /** Exchange authorization code for tokens */
    public static function exchange_code(string $code): bool {
        $creds = self::get_credentials();
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
            'expires_at'    => time() + (int)($body['expires_in'] ?? 3600),
        ]);
        return true;
    }

    /** Get fresh access token, refreshing if expired */
    private static function get_access_token(): string {
        $tokens = get_option(self::OPT_TOKENS, []);
        if (empty($tokens['refresh_token'])) return '';

        // Still valid?
        if (!empty($tokens['access_token']) && time() < ($tokens['expires_at'] - 60)) {
            return $tokens['access_token'];
        }

        // Refresh
        $creds = self::get_credentials();
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
        $tokens['expires_at']   = time() + (int)($body['expires_in'] ?? 3600);
        update_option(self::OPT_TOKENS, $tokens);
        return $tokens['access_token'];
    }

    public static function disconnect(): void {
        delete_option(self::OPT_TOKENS);
    }

    // ── GSC API Calls ─────────────────────────────────────────────

    /** Get list of verified sites in GSC */
    public static function get_sites(): array {
        $token = self::get_access_token();
        if (!$token) return [];

        $r = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($r)) return [];
        $body = json_decode(wp_remote_retrieve_body($r), true);
        return $body['siteEntry'] ?? [];
    }

    /**
     * Fetch keyword opportunities from GSC.
     *
     * Strategy: find queries with HIGH impressions but LOW CTR
     * → These rank on page 1-2 but people don't click → write focused article
     *
     * Also find queries NOT yet covered by existing posts.
     */
    public static function get_keyword_opportunities(string $site_url, int $days = 90): array {
        $cache_key = 'soser_v4_gsc_kw_' . md5($site_url . $days);
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        $token = self::get_access_token();
        if (!$token) return [];

        $end_date   = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $body = wp_json_encode([
            'startDate'  => $start_date,
            'endDate'    => $end_date,
            'dimensions' => ['query'],
            'rowLimit'   => 500,
            'startRow'   => 0,
        ]);

        $r = wp_remote_post(
            "https://www.googleapis.com/webmasters/v3/sites/" . urlencode($site_url) . "/searchAnalytics/query",
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => $body,
            ]
        );

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return [];

        $data = json_decode(wp_remote_retrieve_body($r), true);
        $rows = $data['rows'] ?? [];
        if (empty($rows)) return [];

        // Score and categorize keywords
        $opportunities = [];
        $covered_tokens = V4_Content_Scanner::get_covered_tokens();

        foreach ($rows as $row) {
            $query       = $row['keys'][0] ?? '';
            $clicks      = (int)   $row['clicks'];
            $impressions = (int)   $row['impressions'];
            $ctr         = (float) $row['ctr'];
            $position    = (float) $row['position'];

            if (mb_strlen($query) < 5) continue;

            $is_covered = V4_Content_Scanner::is_covered($query);

            // Opportunity types:
            $type = '';
            $score = 0;

            // Type 1: High impressions, low CTR (page 1-2 but not clicking) → write better article
            if ($impressions >= 100 && $ctr < 0.03 && $position <= 20) {
                $type  = '🎯 Alta opportunità';
                $score = 80 + (int)($impressions / 100);
            }
            // Type 2: Good position (1-10) but low clicks → title/meta needs improvement
            elseif ($position <= 10 && $clicks < 10 && $impressions >= 50) {
                $type  = '📈 Migliora titolo';
                $score = 60 + (int)($impressions / 50);
            }
            // Type 3: Ranking 11-20 → push to page 1 with better content
            elseif ($position > 10 && $position <= 20 && $impressions >= 30) {
                $type  = '⬆️ Sposta a pagina 1';
                $score = 50 + (int)($impressions / 30);
            }
            // Type 4: New keyword not yet covered
            elseif (!$is_covered && $impressions >= 10) {
                $type  = '🆕 Non coperta';
                $score = 40 + (int)($impressions / 10);
            }

            if (!$type) continue;

            $opportunities[] = [
                'keyword'     => $query,
                'type'        => $type,
                'score'       => $score,
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'ctr'         => round($ctr * 100, 1) . '%',
                'position'    => round($position, 1),
                'covered'     => $is_covered,
                'source'      => 'gsc',
            ];
        }

        // Sort by score
        usort($opportunities, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($opportunities, 0, 100);

        set_transient($cache_key, $top, 6 * HOUR_IN_SECONDS);
        return $top;
    }

    /** Best uncovered keyword from GSC for auto-generation */
    public static function best_gsc_keyword(string $site_url): ?array {
        $opps = self::get_keyword_opportunities($site_url);
        foreach ($opps as $opp) {
            if (!$opp['covered']) return $opp;
        }
        return null;
    }

    /** Get saved site URL from options */
    public static function get_site_url(): string {
        return get_option('soser_v4_gsc_site_url', home_url('/'));
    }

    public static function save_site_url(string $url): void {
        update_option('soser_v4_gsc_site_url', esc_url_raw($url));
    }
}
