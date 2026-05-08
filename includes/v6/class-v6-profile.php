<?php
defined('ABSPATH') || exit;

/**
 * V6_Profile — Central Business Profile
 * Single source of truth for ALL business data.
 * Replaces every hardcoded "SOSER", "Milano", "#e87c2a".
 *
 * Usage anywhere in plugin:
 *   $p = V6_Profile::get();
 *   echo $p['name'];        // "My Company"
 *   echo $p['city'];        // "Dubai"
 *   echo $p['color'];       // "#e87c2a"
 *   echo $p['currency'];    // "AED"
 */
class V6_Profile {

    const OPT = 'soser_v6_profile';

    /** Full profile with smart defaults */
    public static function get(): array {
        $saved = get_option(self::OPT, []);
        return array_merge(self::defaults(), $saved);
    }

    public static function save(array $data): void {
        $clean = [];
        $fields = array_keys(self::defaults());
        foreach ($fields as $f) {
            $clean[$f] = sanitize_text_field($data[$f] ?? '');
        }
        // Color: validate hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $clean['color'])) {
            $clean['color'] = '#e87c2a';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $clean['color_dark'])) {
            $clean['color_dark'] = '#1a1a1a';
        }
        update_option(self::OPT, $clean);
        delete_transient('soser_v6_profile_cache');
    }

    /** Shortcut helpers */
    public static function name():     string { return self::get()['name']     ?: get_bloginfo('name'); }
    public static function city():     string { return self::get()['city']     ?: ''; }
    public static function country():  string { return self::get()['country']  ?: ''; }
    public static function language(): string { return self::get()['language'] ?: 'it'; }
    public static function color():    string { return self::get()['color']    ?: '#e87c2a'; }
    public static function currency(): string { return self::get()['currency_symbol'] ?: '€'; }
    public static function phone():    string { return self::get()['phone']    ?: ''; }
    public static function whatsapp(): string { return self::get()['whatsapp'] ?: self::get()['phone'] ?: ''; }
    public static function industry(): string { return self::get()['industry'] ?: ''; }

    /** Build badge text: "Verificato da NAME · CITY · YEAR" */
    public static function badge(): string {
        $p    = self::get();
        $year = date('Y');
        $lang = self::language();

        $verified = [
            'it' => 'Verificato da',
            'en' => 'Verified by',
            'ar' => 'معتمد من',
            'es' => 'Verificado por',
            'fr' => 'Vérifié par',
            'de' => 'Geprüft von',
        ][$lang] ?? 'Verified by';

        return "{$verified} {$p['name']} · {$p['city']} · {$year}";
    }

    /** CTA buttons text */
    public static function cta_call(): string {
        $p    = self::get();
        $lang = self::language();
        if (!empty($p['cta_call'])) return $p['cta_call'];
        return [
            'it' => '📞 Chiama ora',
            'en' => '📞 Call now',
            'ar' => '📞 اتصل الآن',
            'es' => '📞 Llama ahora',
            'fr' => '📞 Appelez maintenant',
            'de' => '📞 Jetzt anrufen',
        ][$lang] ?? '📞 Call now';
    }

    public static function cta_whatsapp(): string {
        $p    = self::get();
        $lang = self::language();
        if (!empty($p['cta_whatsapp'])) return $p['cta_whatsapp'];
        return [
            'it' => '💬 WhatsApp',
            'en' => '💬 WhatsApp',
            'ar' => '💬 واتساب',
            'es' => '💬 WhatsApp',
            'fr' => '💬 WhatsApp',
            'de' => '💬 WhatsApp',
        ][$lang] ?? '💬 WhatsApp';
    }

    public static function cta_quote(): string {
        $p    = self::get();
        $lang = self::language();
        if (!empty($p['cta_quote'])) return $p['cta_quote'];
        return [
            'it' => 'Preventivo gratuito entro 24h',
            'en' => 'Free quote within 24h',
            'ar' => 'عرض سعر مجاني خلال 24 ساعة',
            'es' => 'Presupuesto gratis en 24h',
            'fr' => 'Devis gratuit en 24h',
            'de' => 'Kostenloses Angebot in 24h',
        ][$lang] ?? 'Free quote within 24h';
    }

    /** Trust line under CTA */
    public static function trust_line(): string {
        $p    = self::get();
        $lang = self::language();
        if (!empty($p['trust_line'])) return $p['trust_line'];
        $city = $p['city'] ?: ($lang === 'ar' ? 'المدينة' : 'City');
        return [
            'it' => "✔ Risposta veloce · ✔ Sopralluogo gratuito · ✔ {$city} e provincia",
            'en' => "✔ Fast response · ✔ Free consultation · ✔ {$city} & surroundings",
            'ar' => "✔ رد سريع · ✔ استشارة مجانية · ✔ {$city} والمنطقة",
            'es' => "✔ Respuesta rápida · ✔ Consulta gratis · ✔ {$city} y alrededores",
            'fr' => "✔ Réponse rapide · ✔ Consultation gratuite · ✔ {$city} et environs",
            'de' => "✔ Schnelle Antwort · ✔ Kostenlose Beratung · ✔ {$city} und Umgebung",
        ][$lang] ?? "✔ Fast response · ✔ Free consultation · ✔ {$city}";
    }

    /** Stats for social proof */
    public static function stats(): array {
        $p = self::get();
        return [
            'years'   => $p['years_exp']      ?: '10+',
            'clients' => $p['clients_count']  ?: '50+',
            'rating'  => $p['rating']         ?: '4.8',
            'hours'   => $p['response_hours'] ?: '24',
        ];
    }

    /** WhatsApp URL */
    public static function whatsapp_url(): string {
        $p    = self::get();
        $num  = preg_replace('/[^0-9]/', '', $p['whatsapp'] ?: $p['phone'] ?: '');
        $msg  = urlencode(self::cta_quote());
        return $num ? "https://wa.me/{$num}?text={$msg}" : '#';
    }

    /** Phone URL */
    public static function phone_url(): string {
        $p   = self::get();
        $num = preg_replace('/[^0-9+]/', '', $p['phone'] ?: '');
        return $num ? "tel:{$num}" : '#';
    }

    /** Location string for prompts: "Milano, Italia" */
    public static function location(): string {
        $p = self::get();
        $parts = array_filter([$p['city'], $p['country']]);
        return implode(', ', $parts);
    }

    /** All defaults */
    private static function defaults(): array {
        return [
            // Identity
            'name'            => '',
            'tagline'         => '',
            'industry'        => '',
            'description'     => '',

            // Location
            'city'            => '',
            'country'         => '',
            'address'         => '',

            // Language & Currency
            'language'        => 'it',
            'currency'        => 'EUR',
            'currency_symbol' => '€',

            // Contact
            'phone'           => '',
            'whatsapp'        => '',
            'email'           => '',
            'website'         => '',

            // Branding
            'color'           => '#e87c2a',
            'color_dark'      => '#1a1a1a',

            // CTAs (optional override)
            'cta_call'        => '',
            'cta_whatsapp'    => '',
            'cta_quote'       => '',
            'trust_line'      => '',

            // Stats
            'years_exp'       => '',
            'clients_count'   => '',
            'rating'          => '',
            'response_hours'  => '24',

            // SEO
            'schema_type'     => 'LocalBusiness',
        ];
    }
}
