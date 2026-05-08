<?php
defined('ABSPATH') || exit;

class V4_Options {
    public static function defaults(): array {
        return [
            // API
            'openai_key'         => '',
            'openai_model'       => 'gpt-4.1-mini',
            'image_model'        => 'gpt-image-1',
            // ── UNIVERSAL BUSINESS PROFILE ──
            // Identity
            'business'           => '',                 // Company name
            'business_tagline'   => '',                 // Short tagline
            'business_type'      => 'LocalBusiness',    // Schema.org type
            'business_founded'   => '',                 // Year founded
            'business_email'     => '',
            'business_phone'     => '',                 // e.g. +39 02 1234567
            'business_whatsapp'  => '',                 // e.g. +393331234567
            'business_address'   => '',
            'business_website'   => '',
            // Branding
            'brand_color'        => '#E87C2A',          // Primary color (hex)
            'brand_color_dark'   => '#1A1A1A',          // Dark background color
            'brand_logo_url'     => '',                 // Logo URL
            // Location
            'geo'                => '',                 // Full: "Milano, Italia"
            'geo_city'           => '',                 // City only
            'geo_region'         => '',                 // Region/State
            'geo_country'        => '',                 // Country code: IT, AE, US, SA...
            'geo_country_name'   => '',                 // Country full name
            'geo_lat'            => '',
            'geo_lon'            => '',
            'geo_zones'          => '',                 // Nearby zones (one per line)
            // Language & Currency
            'language'           => 'it',               // it, en, ar, fr, es, de...
            'currency'           => 'EUR',              // EUR, USD, AED, SAR, GBP...
            'currency_symbol'    => '€',               // €, $, د.إ, ر.س, £
            'price_range'        => '€€',
            // Sector / Niche
            'sector'             => '',                 // Main sector slug
            'sector_label'       => '',                 // Human label
            'sector_services'    => '',                 // Services (one per line)
            // CTA
            'cta'                => '',                 // Main CTA text
            'cta_urgency'        => '',                 // Urgency text (24h response, etc)
            // Trust / Social Proof
            'trust_years'        => '',                 // Years in business
            'trust_clients'      => '',                 // Number of clients
            'trust_rating'       => '',                 // e.g. 4.8
            'trust_rating_count' => '',                 // e.g. 47
            'trust_certifications' => '',               // Certifications (one per line)
            // Guarantees
            'guarantee_1'        => '',
            'guarantee_2'        => '',
            'guarantee_3'        => '',
            'guarantee_4'        => '',
            'guarantee_5'        => '',
            'guarantee_6'        => '',
            // Seeds
            'seed_keywords'      => "ristrutturazione bagno Milano\ncosto ristrutturazione appartamento Milano\nimpresa edile Milano\nristrutturazione cucina Milano\nbonus ristrutturazione 2026",
            // Publishing
            'post_status'        => 'draft',
            'post_author'        => '1',
            'category_id'        => '0',
            'min_words'          => '1200',
            'max_words'          => '2000',
            // Images
            'generate_images'    => '1',
            'featured_image'     => '1',
            'inline_images'      => '2',
            'image_style'        => 'realistic Italian home renovation photo, bright, professional, no text, no logos',
            // Links
            'external_links'     => "https://www.agenziaentrate.gov.it/\nhttps://www.comune.milano.it/\nhttps://www.governo.it/",
            // Modules
            'enable_seo_fixer'   => '1',
            'enable_int_links'   => '1',
            'enable_ext_links'   => '1',
            'enable_schema'      => '1',
            'enable_humanizer'   => '1',
            // Keyword Intel
            'min_opportunity'    => '30',   // minimum opportunity score to accept a keyword
            'intel_depth'        => '15',   // max keyword candidates per seed
            // Cron
            'cron_enabled'       => '0',
            'cron_hour'          => '9',
            // Debug
            'debug'              => '0',
            // V5
            'cost_budget_daily'  => '5.00',
            'enable_social'      => '0',
            'facebook_token'     => '',
            'facebook_page_id'   => '',
            'linkedin_token'     => '',
        ];
    }

    public static function get(): array {
        return wp_parse_args(get_option(SOSER_V4_OPT, []), self::defaults());
    }

    public static function save(array $raw): void {
        $old      = self::get();
        $defaults = self::defaults();
        $new      = [];
        $checkboxes = [
            'generate_images','featured_image','enable_seo_fixer',
            'enable_int_links','enable_ext_links','enable_schema',
            'enable_humanizer','cron_enabled','debug',
        ];
        $textareas = ['seed_keywords','cta','external_links','image_style','geo_zones'];

        foreach ($defaults as $k => $v) {
            if (in_array($k, $checkboxes, true)) {
                $new[$k] = isset($raw[$k]) ? '1' : '0';
            } elseif (in_array($k, $textareas, true)) {
                $new[$k] = isset($raw[$k]) ? sanitize_textarea_field(wp_unslash($raw[$k])) : '';
            } else {
                $new[$k] = isset($raw[$k]) ? sanitize_text_field(wp_unslash($raw[$k])) : $v;
            }
        }
        // FIX #3: Preserve ALL sensitive API keys if masked or empty
        $sensitive_keys = ['openai_key', 'gsc_client_secret', 'facebook_token', 'linkedin_token'];
        foreach ($sensitive_keys as $key) {
            $val   = $new[$key] ?? '';
            // Remove all possible masking characters: bullets •, asterisks *, dots ., spaces
            $clean = str_replace(
                ["\xE2\x80\xA2", "\xC2\xB7", '*', '.', ' ', chr(226).chr(128).chr(162)],
                '',
                $val
            );
            // Keep old key if cleaned value is suspiciously short (< 8 real chars)
            if (strlen($clean) < 8 && !empty($old[$key])) {
                $new[$key] = $old[$key];
            }
        }
        update_option(SOSER_V4_OPT, $new);
        if (class_exists('V6_Context')) V6_Context::flush();
    }
}
