<?php
defined('ABSPATH') || exit;

/**
 * V7_SERPTracker — Real SERP position tracking
 * Uses GSC as data source. Stores history per keyword.
 * Detects: drops, rises, opportunities, cannibalization.
 */
class V7_SERPTracker {

    const OPT_KEYWORDS = 'soser_v7_serp_keywords';
    const OPT_EMAIL    = 'soser_v7_alert_email';
    const OPT_EMAIL_ON = 'soser_v7_email_alerts';
    const OPT_HISTORY  = 'soser_v7_serp_history';
    const OPT_ALERTS   = 'soser_v7_serp_alerts';
    const CRON         = 'soser_v7_serp_cron';

    // ── Init & Cron ───────────────────────────────────────────────

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run_check']);
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time(), 'daily', self::CRON);
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::CRON);
    }

    // ── Keyword management ────────────────────────────────────────

    public static function get_keywords(): array {
        return get_option(self::OPT_KEYWORDS, []);
    }

    public static function add_keyword(string $kw, int $post_id = 0): void {
        $kws = self::get_keywords();
        $key = sanitize_text_field($kw);
        if (!isset($kws[$key])) {
            $kws[$key] = [
                'keyword'  => $key,
                'post_id'  => $post_id,
                'added'    => date('Y-m-d'),
                'target'   => 1,
            ];
            update_option(self::OPT_KEYWORDS, $kws);
        }
    }

    public static function remove_keyword(string $kw): void {
        $kws = self::get_keywords();
        unset($kws[$kw]);
        update_option(self::OPT_KEYWORDS, $kws);
        // Also clean history
        $history = get_option(self::OPT_HISTORY, []);
        unset($history[$kw]);
        update_option(self::OPT_HISTORY, $history);
    }

    /** Auto-import all plugin keywords */
    public static function auto_import(): int {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 200,
            'meta_key'    => '_soser_focus_keyword',
        ]);
        $count = 0;
        foreach ($posts as $p) {
            $kw = get_post_meta($p->ID, '_soser_focus_keyword', true);
            if ($kw) {
                self::add_keyword($kw, $p->ID);
                $count++;
            }
        }
        return $count;
    }

    // ── Run check ─────────────────────────────────────────────────

    public static function run_check(): array {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) {
            return ['error' => 'GSC non connesso'];
        }

        $site    = V4_GSC::get_site_url();
        if (!$site) return ['error' => 'Sito non configurato'];

        $kws     = self::get_keywords();
        $history = get_option(self::OPT_HISTORY, []);
        $today   = date('Y-m-d');
        $alerts  = [];
        $updated = 0;

        // Fetch fresh GSC data
        $gsc_data = V4_GSC::get_keyword_opportunities($site, 7);
        $gsc_map  = [];
        foreach ($gsc_data as $row) {
            $gsc_map[strtolower($row['keyword'])] = $row;
        }

        foreach ($kws as $kw => $meta) {
            $lower = strtolower($kw);
            $row   = $gsc_map[$lower] ?? null;

            if (!$row) continue;

            $position    = round($row['position'], 1);
            $clicks      = (int) $row['clicks'];
            $impressions = (int) $row['impressions'];
            $ctr         = round($row['ctr'] * 100, 2);

            // Get previous position
            $prev_entry  = end($history[$kw] ?? []);
            $prev_pos    = $prev_entry ? $prev_entry['position'] : null;
            $change      = $prev_pos !== null ? round($prev_pos - $position, 1) : 0;

            // Store snapshot
            $history[$kw][] = [
                'date'        => $today,
                'position'    => $position,
                'clicks'      => $clicks,
                'impressions' => $impressions,
                'ctr'         => $ctr,
                'change'      => $change,
            ];

            // Keep last 90 days only
            if (count($history[$kw]) > 90) {
                $history[$kw] = array_slice($history[$kw], -90);
            }

            // Update keyword meta
            $kws[$kw]['last_position'] = $position;
            $kws[$kw]['last_checked']  = $today;
            $kws[$kw]['last_change']   = $change;
            $updated++;

            // Generate alerts
            if ($prev_pos !== null) {
                if ($change <= -5) {
                    $alerts[] = [
                        'type'    => 'drop',
                        'keyword' => $kw,
                        'message' => "⬇️ DROP: \"{$kw}\" sceso da {$prev_pos} a {$position} (−" . abs($change) . " pos)",
                        'date'    => $today,
                    ];
                } elseif ($change >= 5) {
                    $alerts[] = [
                        'type'    => 'rise',
                        'keyword' => $kw,
                        'message' => "⬆️ RISE: \"{$kw}\" salito da {$prev_pos} a {$position} (+{$change} pos)",
                        'date'    => $today,
                    ];
                }
                if ($position <= 3 && $prev_pos > 3) {
                    $alerts[] = [
                        'type'    => 'top3',
                        'keyword' => $kw,
                        'message' => "🏆 TOP 3: \"{$kw}\" è entrato in posizione {$position}!",
                        'date'    => $today,
                    ];
                }
            }
        }

        update_option(self::OPT_KEYWORDS, $kws);
        update_option(self::OPT_HISTORY,  $history);

        // Store last 30 alerts
        $existing = get_option(self::OPT_ALERTS, []);
        $all_alerts = array_merge($alerts, $existing);
        update_option(self::OPT_ALERTS, array_slice($all_alerts, 0, 30));

        // Send email notifications for drops
        if (!empty($alerts) && get_option(self::OPT_EMAIL_ON, '0') === '1') {
            self::send_alert_email($alerts);
        }

        return ['updated' => $updated, 'alerts' => count($alerts)];
    }

    // ── History & reports ─────────────────────────────────────────

    public static function get_history(string $kw, int $days = 30): array {
        $history = get_option(self::OPT_HISTORY, []);
        $entries = $history[$kw] ?? [];
        return array_slice($entries, -$days);
    }

    public static function get_all_with_positions(): array {
        $kws  = self::get_keywords();
        usort($kws, function($a, $b) {
            $pa = $a['last_position'] ?? 999;
            $pb = $b['last_position'] ?? 999;
            return $pa - $pb;
        });
        return $kws;
    }

    public static function get_alerts(int $limit = 20): array {
        return array_slice(get_option(self::OPT_ALERTS, []), 0, $limit);
    }

    public static function get_drops(): array {
        return array_filter(self::get_keywords(), fn($k) => ($k['last_change'] ?? 0) < -3);
    }

    public static function get_rises(): array {
        return array_filter(self::get_keywords(), fn($k) => ($k['last_change'] ?? 0) > 3);
    }

    public static function get_top3(): array {
        return array_filter(self::get_keywords(), fn($k) => ($k['last_position'] ?? 99) <= 3);
    }

    public static function get_quick_wins(): array {
        return array_filter(self::get_keywords(), function($k) {
            $p = $k['last_position'] ?? 99;
            return $p >= 4 && $p <= 15;
        });
    }
    // ── Email Alerts ──────────────────────────────────────────────

    public static function send_alert_email(array $alerts): void {
        $email = get_option(self::OPT_EMAIL, get_option('admin_email'));
        if (!$email || !is_email($email)) return;

        $site  = get_bloginfo('name');
        $drops = array_filter($alerts, fn($a) => $a['type'] === 'drop');
        $rises = array_filter($alerts, fn($a) => $a['type'] === 'rise');
        $top3  = array_filter($alerts, fn($a) => $a['type'] === 'top3');

        $subject = "[{$site}] SERP Alert: " . count($drops) . " drop, " . count($top3) . " top3";

        $body  = "<h2 style='color:#1a1a1a'>📊 SERP Report — {$site}</h2>";
        $body .= "<p style='color:#666'>Data: " . date('d/m/Y H:i') . "</p>";

        if (!empty($top3)) {
            $body .= "<h3 style='color:#16a34a'>🏆 Entrati in Top 3</h3><ul>";
            foreach ($top3 as $a) $body .= "<li style='color:#16a34a'>" . esc_html($a['message']) . "</li>";
            $body .= "</ul>";
        }
        if (!empty($rises)) {
            $body .= "<h3 style='color:#2563eb'>⬆️ Salite di posizione</h3><ul>";
            foreach ($rises as $a) $body .= "<li style='color:#2563eb'>" . esc_html($a['message']) . "</li>";
            $body .= "</ul>";
        }
        if (!empty($drops)) {
            $body .= "<h3 style='color:#dc2626'>⬇️ Cali di posizione</h3><ul>";
            foreach ($drops as $a) $body .= "<li style='color:#dc2626'>" . esc_html($a['message']) . "</li>";
            $body .= "</ul>";
            $body .= "<p><a href='" . admin_url('admin.php?page=soser-v7-analytics') . "' style='background:#e87c2a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;margin-top:10px'>Analizza e Risolvi →</a></p>";
        }

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public static function save_email_settings(string $email, bool $enabled): void {
        update_option(self::OPT_EMAIL,    sanitize_email($email));
        update_option(self::OPT_EMAIL_ON, $enabled ? '1' : '0');
    }

    public static function get_email_settings(): array {
        return [
            'email'   => get_option(self::OPT_EMAIL, get_option('admin_email')),
            'enabled' => get_option(self::OPT_EMAIL_ON, '0') === '1',
        ];
    }


}
