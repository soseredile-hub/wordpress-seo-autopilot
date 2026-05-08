<?php
defined('ABSPATH') || exit;

/**
 * V5.5 Feature 3: Seasonal Content Calendar
 *
 * Knows WHEN search volume peaks for each renovation service.
 * Schedules content publication 3-4 weeks BEFORE the peak
 * so articles rank just in time for the high-traffic season.
 */
class V55_SeasonalCalendar {

    /**
     * Seasonal peaks for Italian renovation market.
     * month => [service_id => search_multiplier]
     * 1.0 = normal, 2.0 = double, 3.0 = triple
     */
    const SEASONALITY = [
        1  => ['ristrutturazione'=>1.8,'bagno'=>1.5,'imbiancatura'=>1.2,'bonus'=>2.5,'cartongesso'=>1.2,'pavimenti'=>1.0,'impianti'=>1.2], // Gennaio - nuovi progetti
        2  => ['ristrutturazione'=>2.0,'bagno'=>1.8,'imbiancatura'=>1.3,'bonus'=>2.8,'cartongesso'=>1.3,'pavimenti'=>1.1,'impianti'=>1.3], // Febbraio - pianificazione primavera
        3  => ['ristrutturazione'=>2.5,'bagno'=>2.2,'imbiancatura'=>2.0,'bonus'=>3.0,'cartongesso'=>1.8,'pavimenti'=>1.5,'impianti'=>1.5], // Marzo - PICCO primavera
        4  => ['ristrutturazione'=>2.8,'bagno'=>2.5,'imbiancatura'=>2.5,'bonus'=>3.2,'cartongesso'=>2.0,'pavimenti'=>2.0,'impianti'=>1.8], // Aprile - PICCO massimo
        5  => ['ristrutturazione'=>2.5,'bagno'=>2.0,'imbiancatura'=>2.2,'bonus'=>2.5,'cartongesso'=>1.8,'pavimenti'=>2.5,'impianti'=>1.5], // Maggio - alta stagione
        6  => ['ristrutturazione'=>1.8,'bagno'=>1.5,'imbiancatura'=>1.5,'bonus'=>1.8,'cartongesso'=>1.5,'pavimenti'=>2.8,'impianti'=>1.3], // Giugno - pavimenti
        7  => ['ristrutturazione'=>1.2,'bagno'=>1.0,'imbiancatura'=>1.0,'bonus'=>1.2,'cartongesso'=>1.0,'pavimenti'=>1.5,'impianti'=>1.0], // Luglio - ferie
        8  => ['ristrutturazione'=>1.0,'bagno'=>0.8,'imbiancatura'=>0.8,'bonus'=>1.0,'cartongesso'=>0.8,'pavimenti'=>1.0,'impianti'=>0.8], // Agosto - bassa stagione
        9  => ['ristrutturazione'=>2.2,'bagno'=>2.0,'imbiancatura'=>2.0,'bonus'=>2.0,'cartongesso'=>1.8,'pavimenti'=>1.8,'impianti'=>1.8], // Settembre - ripresa autunnale
        10 => ['ristrutturazione'=>2.0,'bagno'=>1.8,'imbiancatura'=>1.8,'bonus'=>2.2,'cartongesso'=>1.8,'pavimenti'=>1.5,'impianti'=>2.0], // Ottobre - autunno
        11 => ['ristrutturazione'=>1.5,'bagno'=>1.3,'imbiancatura'=>1.3,'bonus'=>1.8,'cartongesso'=>1.5,'pavimenti'=>1.2,'impianti'=>1.8], // Novembre - bonus fine anno
        12 => ['ristrutturazione'=>1.2,'bagno'=>1.0,'imbiancatura'=>1.0,'bonus'=>2.5,'cartongesso'=>1.2,'pavimenti'=>1.0,'impianti'=>1.5], // Dicembre - bonus scadenze
    ];

    /**
     * Get current month's hot services.
     */
    public static function get_hot_now(): array {
        $month = (int) date('n');
        $data  = self::SEASONALITY[$month] ?? [];
        arsort($data);
        return $data;
    }

    /**
     * Get what to write NOW to be ready for the next peak.
     * Looks 3-4 weeks ahead (time for Google to index + rank).
     */
    public static function get_write_now(): array {
        $lead_weeks  = 4; // Weeks before peak to publish
        $target_month = (int) date('n', strtotime("+{$lead_weeks} weeks"));
        $data         = self::SEASONALITY[$target_month] ?? [];

        arsort($data);

        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_services()
            : [];
        $svc_map = array_column($services, null, 'id');

        $result = [];
        foreach ($data as $svc_id => $multiplier) {
            if ($multiplier < 1.5) continue; // Only high-peak services
            $svc = $svc_map[$svc_id] ?? null;
            if (!$svc) continue;

            $result[] = [
                'service_id'   => $svc_id,
                'service_name' => $svc['name'],
                'service_icon' => $svc['icon'],
                'multiplier'   => $multiplier,
                'peak_month'   => self::month_name($target_month),
                'urgency'      => $multiplier >= 2.5 ? 'alta' : ($multiplier >= 2.0 ? 'media' : 'normale'),
                'suggested_kws'=> array_slice($svc['seeds'] ?? [], 0, 3),
            ];
        }

        return $result;
    }

    /**
     * Get full 12-month seasonal plan.
     */
    public static function get_yearly_plan(): array {
        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_services()
            : [];
        $svc_map = array_column($services, null, 'id');
        $plan    = [];
        $current = (int) date('n');

        for ($m = 1; $m <= 12; $m++) {
            $month_data = self::SEASONALITY[$m] ?? [];
            arsort($month_data);
            $top = array_slice($month_data, 0, 3, true);

            $entries = [];
            foreach ($top as $svc_id => $mult) {
                $svc = $svc_map[$svc_id] ?? null;
                $entries[] = [
                    'service'    => $svc['name'] ?? $svc_id,
                    'icon'       => $svc['icon'] ?? '🔧',
                    'multiplier' => $mult,
                    'bar_width'  => min(100, (int)(($mult - 0.8) / 2.4 * 100)),
                ];
            }

            $plan[] = [
                'month'      => $m,
                'month_name' => self::month_name($m),
                'is_current' => $m === $current,
                'is_past'    => $m < $current,
                'publish_by' => self::month_name(max(1, $m - 1)), // Publish 1 month before
                'services'   => $entries,
            ];
        }

        return $plan;
    }

    /**
     * Get content ideas for a specific service in the next 3 months.
     */
    public static function get_service_schedule(string $service_id): array {
        $current = (int) date('n');
        $ideas   = [];

        for ($i = 0; $i < 3; $i++) {
            $month = (($current + $i - 1) % 12) + 1;
            $mult  = self::SEASONALITY[$month][$service_id] ?? 1.0;

            $ideas[] = [
                'month'      => self::month_name($month),
                'multiplier' => $mult,
                'priority'   => $mult >= 2.0 ? '🔥 Alta' : ($mult >= 1.5 ? '📈 Media' : '📊 Normale'),
            ];
        }
        return $ideas;
    }

    // ── Trending keywords this season ────────────────────────────

    /**
     * Get keyword suggestions enhanced with seasonal context.
     */
    public static function get_seasonal_keywords(): array {
        $opts    = V4_Options::get();
        $geo     = $opts['geo'] ?: 'Milano';
        $year    = date('Y');
        $month   = (int) date('n');
        $hot     = self::get_hot_now();
        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_active_services()
            : [];
        $svc_map = array_column($services, null, 'id');

        $keywords = [];
        foreach (array_slice($hot, 0, 4, true) as $svc_id => $mult) {
            if ($mult < 1.4) continue;
            $svc = $svc_map[$svc_id] ?? null;
            if (!$svc) continue;

            $base = mb_strtolower($svc['name']);
            $season_mod = self::get_season_modifier($month);

            $keywords[] = [
                'keyword'    => "{$base} {$geo} {$year}",
                'service'    => $svc['name'],
                'multiplier' => $mult,
                'seasonal'   => $season_mod,
                'priority'   => round($mult * 50),
                'why'        => "🔥 Alta stagione per " . $svc['name'] . " — " . self::month_name($month),
            ];
            $keywords[] = [
                'keyword'    => "bonus {$base} {$year} {$geo}",
                'service'    => $svc['name'],
                'multiplier' => $mult * 0.9,
                'seasonal'   => $season_mod,
                'priority'   => round($mult * 45),
                'why'        => "💰 Bonus + stagione = massima domanda",
            ];
        }

        usort($keywords, fn($a,$b) => $b['priority'] <=> $a['priority']);
        return $keywords;
    }

    // ── Helpers ───────────────────────────────────────────────────

    public static function month_name(int $m): string {
        $names = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                  'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
        return $names[$m] ?? '';
    }

    private static function get_season_modifier(int $month): string {
        if (in_array($month, [3,4,5]))  return '🌸 Primavera — picco massimo';
        if (in_array($month, [9,10]))   return '🍂 Autunno — seconda stagione';
        if (in_array($month, [1,2]))    return '❄️ Inizio anno — pianificazione';
        if (in_array($month, [11,12]))  return '🎄 Fine anno — bonus scadenze';
        if (in_array($month, [6,7,8]))  return '☀️ Estate — bassa stagione';
        return '';
    }
}
