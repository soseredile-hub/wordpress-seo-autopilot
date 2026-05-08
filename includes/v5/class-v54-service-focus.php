<?php
defined('ABSPATH') || exit;

/**
 * V5.4: Service Focus Manager
 *
 * Lets the user define all their services and mark which ones
 * to focus on RIGHT NOW. The keyword intel and planner will
 * prioritize only the active services.
 */
class V54_ServiceFocus {

    const OPT_SERVICES = 'soser_v54_services';
    const OPT_ACTIVE   = 'soser_v54_active_services';

    // ── Default services (pre-filled from site scan) ──────────────

    public static function get_default_services(): array {
        $opts  = V4_Options::get();
        $city  = $opts['geo_city']  ?: 'Milano';
        $geo   = $opts['geo']       ?: 'Milano, Italia';
        $sector = $opts['sector']   ?: 'ristrutturazione';

        // Check if we have a non-renovation sector → return empty (user defines own services)
        $renovation_sectors = ['ristrutturazione','edilizia','costruzione','renovation','building'];
        $is_renovation = false;
        foreach ($renovation_sectors as $rs) {
            if (stripos($sector, $rs) !== false) { $is_renovation = true; break; }
        }

        if (!$is_renovation) {
            // Generic services for other niches
            return [
                [
                    'id'    => 'main',
                    'name'  => ucfirst($sector),
                    'icon'  => '🔧',
                    'seeds' => [
                        "{$sector} {$city}",
                        "prezzi {$sector} {$city}",
                        "costo {$sector} {$city}",
                        "migliore {$sector} {$city}",
                        "{$sector} professionale {$city}",
                    ],
                ],
            ];
        }

        // Renovation-specific services (with dynamic city)
        return [
            [
                'id'    => 'ristrutturazione',
                'name'  => 'Ristrutturazione Completa',
                'icon'  => '🏗️',
                'seeds' => [
                    "ristrutturazione completa appartamento {$city}",
                    "costo ristrutturazione appartamento {$city}",
                    "impresa ristrutturazione {$city}",
                    "ristrutturazione chiavi in mano {$city}",
                    "preventivo ristrutturazione {$city}",
                ],
            ],
            [
                'id'    => 'bagno',
                'name'  => 'Bagno E Cucina',
                'icon'  => '🚿',
                'seeds' => [
                    "ristrutturazione bagno {$city}",
                    "rifacimento bagno {$city} prezzi",
                    "costo rifacimento bagno {$city}",
                    "ristrutturazione cucina {$city}",
                    "rifacimento cucina {$city} prezzi",
                ],
            ],
            [
                'id'    => 'cartongesso',
                'name'  => 'Cartongesso',
                'icon'  => '🪟',
                'seeds' => [
                    "cartongesso {$city} prezzi",
                    "pareti cartongesso {$city}",
                    "controsoffitto cartongesso {$city}",
                    "libreria cartongesso {$city}",
                    "nicchie cartongesso {$city}",
                ],
            ],
            [
                'id'    => 'imbiancatura',
                'name'  => 'Imbiancatura E Tinteggiatura',
                'icon'  => '🎨',
                'seeds' => [
                    "imbiancatura appartamento {$city}",
                    "tinteggiatura pareti {$city} prezzi",
                    "imbianchino {$city} prezzi",
                    "pittura casa {$city}",
                    "costo imbiancatura {$city}",
                ],
            ],
            [
                'id'    => 'pavimenti',
                'name'  => 'Pavimenti E Rivestimenti',
                'icon'  => '🪵',
                'seeds' => [
                    "pavimenti casa {$city} prezzi",
                    "posa pavimenti {$city}",
                    "parquet {$city} prezzi",
                    "rivestimenti bagno {$city}",
                    "gres porcellanato {$city} prezzi",
                ],
            ],
            [
                'id'    => 'impianti',
                'name'  => 'Impianti Elettrici',
                'icon'  => '⚡',
                'seeds' => [
                    "impianti elettrici civili {$city}",
                    "elettricista {$city} prezzi",
                    "rifacimento impianto elettrico {$city}",
                    "impianto elettrico appartamento {$city}",
                    "costo impianto elettrico {$city}",
                ],
            ],
        ];
    }

    // ── Save / Load ───────────────────────────────────────────────

    public static function get_services(): array {
        $saved = get_option(self::OPT_SERVICES, []);
        return !empty($saved) ? $saved : self::get_default_services();
    }

    public static function get_active_ids(): array {
        $active = get_option(self::OPT_ACTIVE, []);
        // Default: all active
        if (empty($active)) {
            return array_column(self::get_services(), 'id');
        }
        return $active;
    }

    public static function save_active(array $active_ids): void {
        $valid = array_column(self::get_services(), 'id');
        $clean = array_filter($active_ids, fn($id) => in_array($id, $valid, true));
        update_option(self::OPT_ACTIVE, array_values($clean));
        // Rebuild seed keywords from active services
        self::rebuild_seeds();
        // Invalidate all caches
        delete_transient('soser_v4_keyword_intel');
        delete_transient('v5_plan');
        delete_transient('v5_clusters');
        delete_transient('v52_calendar');
    }

    public static function save_services(array $services): void {
        // Sanitize
        $clean = [];
        foreach ($services as $s) {
            if (empty($s['id']) || empty($s['name'])) continue;
            $clean[] = [
                'id'    => sanitize_key($s['id']),
                'name'  => sanitize_text_field($s['name']),
                'icon'  => sanitize_text_field($s['icon'] ?? '🔧'),
                'seeds' => array_filter(array_map('sanitize_text_field', $s['seeds'] ?? [])),
            ];
        }
        update_option(self::OPT_SERVICES, $clean);
    }

    /**
     * Rebuild V4 seed keywords from ONLY the active services.
     * This is what makes the entire system focus on selected services.
     */
    public static function rebuild_seeds(): void {
        $services   = self::get_services();
        $active_ids = self::get_active_ids();
        $seeds      = [];

        foreach ($services as $service) {
            if (!in_array($service['id'], $active_ids, true)) continue;
            foreach ($service['seeds'] as $seed) {
                $seeds[] = $seed;
            }
        }

        // Update V4 options
        $opts = V4_Options::get();
        $opts['seed_keywords'] = implode("\n", $seeds);
        update_option(SOSER_V4_OPT, $opts);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public static function get_active_services(): array {
        $all    = self::get_services();
        $active = self::get_active_ids();
        return array_filter($all, fn($s) => in_array($s['id'], $active, true));
    }

    public static function get_coverage(): array {
        $services = self::get_services();
        $coverage = [];

        foreach ($services as $service) {
            // Count published posts for this service
            $post_count = 0;
            $gsc_clicks = 0;

            if (class_exists('V5_Memory')) {
                $memory = V5_Memory::get_all();
                foreach ($memory as $m) {
                    if (stripos($m['topic'] ?? '', $service['id']) !== false ||
                        stripos($m['keyword'] ?? '', $service['name']) !== false ||
                        self::keyword_matches_service($m['keyword'] ?? '', $service)) {
                        $post_count++;
                        $gsc_clicks += (int)($m['gsc_clicks'] ?? 0);
                    }
                }
            }

            // Fallback: count by category/keyword
            if ($post_count === 0) {
                global $wpdb;
                $like = '%' . $wpdb->esc_like(strtolower($service['id'])) . '%';
                $post_count = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type='post' AND post_status='publish'
                     AND (post_title LIKE %s OR post_content LIKE %s)",
                    $like, $like
                ));
            }

            $coverage[] = [
                'service'    => $service,
                'post_count' => $post_count,
                'gsc_clicks' => $gsc_clicks,
                'is_active'  => in_array($service['id'], self::get_active_ids(), true),
                'needs_work' => $post_count < 3,
            ];
        }

        return $coverage;
    }

    private static function keyword_matches_service(string $kw, array $service): bool {
        $kw_lower = mb_strtolower($kw);
        // Check against service name words
        $words = explode(' ', mb_strtolower($service['name']));
        foreach ($words as $w) {
            if (mb_strlen($w) > 4 && strpos($kw_lower, $w) !== false) return true;
        }
        // Check against seeds
        foreach ($service['seeds'] as $seed) {
            similar_text($kw_lower, mb_strtolower($seed), $pct);
            if ($pct > 60) return true;
        }
        return false;
    }
}
