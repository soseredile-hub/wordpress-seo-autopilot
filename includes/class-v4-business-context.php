<?php
defined('ABSPATH') || exit;

/**
 * Scans the site to extract business services, USPs, and context.
 * Used to enrich article writing prompts.
 */
class V4_Business_Context {

    /** Build full business context from site pages + options */
    public static function get(): array {
        $cached = get_transient('soser_v4_business_context');
        if ($cached) return $cached;
        return self::build();
    }

    public static function build(): array {
        $opts     = V4_Options::get();
        $services = self::extract_services();
        $pages    = self::scan_key_pages();

        $context = [
            'business_name' => $opts['business'],
            'geo'           => $opts['geo'],
            'cta'           => $opts['cta'],
            'services'      => $services,
            'pages'         => $pages,
            'summary'       => self::build_summary($opts['business'], $services),
        ];

        set_transient('soser_v4_business_context', $context, 6 * HOUR_IN_SECONDS);
        return $context;
    }

    /** Extract services from categories, pages, and menu */
    private static function extract_services(): array {
        $services = [];

        // 1. From WordPress categories
        $cats = get_categories(['hide_empty' => false, 'number' => 20]);
        foreach ($cats as $cat) {
            if (strtolower($cat->name) !== 'uncategorized' && strtolower($cat->name) !== 'senza categoria') {
                $services[] = [
                    'name'        => $cat->name,
                    'description' => $cat->description ?: '',
                    'source'      => 'category',
                    'url'         => get_category_link($cat->term_id),
                ];
            }
        }

        // 2. From pages (service/chi-siamo/servizi pages)
        $service_keywords = ['servizi', 'service', 'ristrutturazione', 'bagno', 'cucina',
                             'cartongesso', 'imbiancatura', 'pavimenti', 'impianti', 'lavori'];
        $pages = get_posts([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 20,
        ]);
        foreach ($pages as $page) {
            $title_lower = mb_strtolower($page->post_title);
            foreach ($service_keywords as $kw) {
                if (strpos($title_lower, $kw) !== false) {
                    $excerpt = wp_trim_words(wp_strip_all_tags($page->post_content), 20);
                    $services[] = [
                        'name'        => $page->post_title,
                        'description' => $excerpt,
                        'source'      => 'page',
                        'url'         => get_permalink($page->ID),
                    ];
                    break;
                }
            }
        }

        // 3. Deduplicate by name
        $seen = [];
        $unique = [];
        foreach ($services as $s) {
            $key = mb_strtolower($s['name']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $s;
            }
        }

        return $unique;
    }

    /** Scan homepage and about page for business description */
    private static function scan_key_pages(): array {
        $pages = [];

        // Homepage
        $home_id = (int) get_option('page_on_front');
        if ($home_id > 0) {
            $home = get_post($home_id);
            if ($home) {
                $pages['homepage'] = wp_trim_words(wp_strip_all_tags($home->post_content), 50);
            }
        }

        // Chi siamo / About
        $about = get_posts([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            's'           => 'chi siamo',
        ]);
        if (!empty($about)) {
            $pages['about'] = wp_trim_words(wp_strip_all_tags($about[0]->post_content), 50);
        }

        return $pages;
    }

    /** Build a short text summary for the AI prompt */
    private static function build_summary(string $business_name, array $services): string {
        if (empty($services)) {
            return "{$business_name} è un'impresa di ristrutturazioni a Milano.";
        }
        $service_names = array_column($services, 'name');
        $list = implode(', ', array_slice($service_names, 0, 6));
        return "{$business_name} offre i seguenti servizi a Milano: {$list}. "
             . "Si specializza in ristrutturazioni complete chiavi in mano con alta qualità e trasparenza nei costi.";
    }

    /** Format context as a compact string for AI prompt injection */
    public static function to_prompt_string(): string {
        $ctx = self::get();
        $lines = ["Contesto aziendale:"];
        $lines[] = "- Business: " . $ctx['business_name'] . " (" . $ctx['geo'] . ")";
        $lines[] = "- Descrizione: " . $ctx['summary'];
        if (!empty($ctx['services'])) {
            $lines[] = "- Servizi offerti:";
            foreach (array_slice($ctx['services'], 0, 8) as $s) {
                $desc = $s['description'] ? " — " . mb_substr($s['description'], 0, 60) : '';
                $lines[] = "  * " . $s['name'] . $desc;
            }
        }
        $lines[] = "- CTA da usare: " . $ctx['cta'];
        return implode("\n", $lines);
    }

    public static function clear_cache(): void {
        delete_transient('soser_v4_business_context');
    }
}
