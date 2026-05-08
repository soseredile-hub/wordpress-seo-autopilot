<?php
defined('ABSPATH') || exit;

/**
 * V6_Context — Universal Business Context
 * Single source of truth for all AI modules.
 * Replaces all hardcoded SOSER/Milano/ristrutturazione references.
 */
class V6_Context {

    private static ?array $cache = null;

    /** Get full context array */
    public static function get(): array {
        if (self::$cache !== null) return self::$cache;

        $o = V4_Options::get();

        self::$cache = [
            // Identity
            'name'          => $o['business']           ?: get_bloginfo('name'),
            'tagline'       => $o['business_tagline']   ?: '',
            'type'          => $o['business_type']      ?: 'LocalBusiness',
            'founded'       => $o['business_founded']   ?: '',
            'email'         => $o['business_email']     ?: '',
            'phone'         => $o['business_phone']     ?: '',
            'whatsapp'      => $o['business_whatsapp']  ?: $o['business_phone'] ?: '',
            'address'       => $o['business_address']   ?: '',
            'website'       => $o['business_website']   ?: home_url(),
            // Location
            'city'          => $o['geo_city']           ?: '',
            'region'        => $o['geo_region']         ?: '',
            'country'       => $o['geo_country']        ?: '',
            'country_name'  => $o['geo_country_name']   ?: '',
            'geo_full'      => self::geo_full($o),
            'lat'           => $o['geo_lat']            ?: '',
            'lon'           => $o['geo_lon']            ?: '',
            'zones'         => array_filter(array_map('trim', explode("\n", $o['geo_zones'] ?? ''))),
            // Language & Currency
            'language'      => $o['language']           ?: 'it',
            'language_name' => self::language_name($o['language'] ?? 'it'),
            'currency'      => $o['currency']           ?: 'EUR',
            'currency_sym'  => $o['currency_symbol']    ?: self::currency_symbol($o['currency'] ?? 'EUR'),
            // Sector
            'sector'        => $o['sector']             ?: '',
            'sector_label'  => $o['sector_label']       ?: $o['sector'] ?: '',
            'services'      => array_filter(array_map('trim', explode("\n", $o['sector_services'] ?? ''))),
            // Branding
            'color'         => $o['brand_color']        ?: '#E87C2A',
            'color_dark'    => $o['brand_color_dark']   ?: '#1A1A1A',
            'logo'          => $o['brand_logo_url']     ?: '',
            // CTA
            'cta'           => $o['cta']                ?: '',
            'cta_urgency'   => $o['cta_urgency']        ?: '',
            // Trust
            'trust_years'   => $o['trust_years']        ?: '',
            'trust_clients' => $o['trust_clients']      ?: '',
            'rating'        => $o['trust_rating']       ?: '',
            'rating_count'  => $o['trust_rating_count'] ?: '',
            // Guarantees
            'guarantees'    => self::guarantees($o),
            // WhatsApp URL
            'wa_url'        => self::wa_url($o),
            // Phone URL
            'phone_url'     => 'tel:' . preg_replace('/[^+\d]/', '', $o['business_phone'] ?? ''),
        ];

        return self::$cache;
    }

    /** Quick getter: V6_Context::val('city') */
    public static function val(string $key, string $fallback = ''): string {
        $ctx = self::get();
        return (string)($ctx[$key] ?? $fallback);
    }

    /** Build system prompt context block for AI */
    public static function ai_context(): string {
        $c = self::get();
        $year = date('Y');

        $services_str = !empty($c['services'])
            ? implode(', ', array_slice($c['services'], 0, 8))
            : $c['sector_label'];

        $guarantees_str = !empty($c['guarantees'])
            ? implode(' | ', $c['guarantees'])
            : '';

        $zones_str = !empty($c['zones'])
            ? implode(', ', array_slice($c['zones'], 0, 5))
            : '';

        return "BUSINESS PROFILE:
Company: {$c['name']}
Sector: {$c['sector_label']}
Services: {$services_str}
Location: {$c['city']}, {$c['country_name']}
Language: {$c['language_name']}
Currency: {$c['currency']} ({$c['currency_sym']})
Year: {$year}
Phone: {$c['phone']}
Trust: {$c['trust_years']} years | {$c['trust_clients']} clients | ⭐{$c['rating']}
Guarantees: {$guarantees_str}
" . ($zones_str ? "Zones served: {$zones_str}" : '');
    }

    /** Build CSS variables for dynamic branding */
    public static function css_vars(): string {
        $c = self::get();
        return ":root {
  --soser-primary:   {$c['color']};
  --soser-dark:      {$c['color_dark']};
  --soser-light:     " . self::lighten($c['color']) . ";
}";
    }

    // ── Private helpers ──────────────────────────────────────

    private static function geo_full(array $o): string {
        $parts = array_filter([$o['geo_city'] ?? '', $o['geo_country_name'] ?? '']);
        return implode(', ', $parts);
    }

    private static function guarantees(array $o): array {
        $out = [];
        for ($i = 1; $i <= 6; $i++) {
            $v = trim($o["guarantee_{$i}"] ?? '');
            if ($v) $out[] = $v;
        }
        return $out;
    }

    private static function wa_url(array $o): string {
        $num = preg_replace('/[^+\d]/', '', $o['business_whatsapp'] ?? $o['business_phone'] ?? '');
        if (!$num) return '#';
        $msg = urlencode(($o['cta'] ?: 'Ciao, vorrei un preventivo gratuito'));
        return "https://wa.me/{$num}?text={$msg}";
    }

    private static function language_name(string $code): string {
        return [
            'it'=>'Italian','en'=>'English','ar'=>'Arabic','fr'=>'French',
            'es'=>'Spanish','de'=>'German','pt'=>'Portuguese','nl'=>'Dutch',
            'ru'=>'Russian','zh'=>'Chinese',
        ][$code] ?? 'English';
    }

    private static function currency_symbol(string $code): string {
        return [
            'EUR'=>'€','USD'=>'$','GBP'=>'£','AED'=>'د.إ','SAR'=>'ر.س',
            'QAR'=>'ر.ق','KWD'=>'د.ك','EGP'=>'ج.م','MAD'=>'د.م',
            'TRY'=>'₺','INR'=>'₹','AUD'=>'A$','CAD'=>'C$','CHF'=>'CHF','JPY'=>'¥',
        ][$code] ?? $code;
    }

    /** Generate a light tint of a hex color for backgrounds */
    private static function lighten(string $hex): string {
        $hex  = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#FFF9F0';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = min(255, (int)($r + (255 - $r) * 0.88));
        $g = min(255, (int)($g + (255 - $g) * 0.88));
        $b = min(255, (int)($b + (255 - $b) * 0.88));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Flush cache (call after saving options) */
    public static function flush(): void {
        self::$cache = null;
    }
}
