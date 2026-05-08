<?php
defined('ABSPATH') || exit;

/**
 * V5.7 Feature 3: Entity SEO
 * Google understands ENTITIES not just keywords.
 * Adds structured entity references to build topical authority.
 */
class V57_EntitySEO {

    /**
     * Enrich article with relevant entities.
     * Entities = real-world objects Google recognizes (places, laws, orgs...)
     */
    public static function enrich(array $article, string $kw): array {
        $opts     = V4_Options::get();
        $entities = self::get_entities_for_sector($opts);

        if (empty($entities)) return $article;

        // Add entity references naturally in content
        $entity_section = self::build_entity_section($entities, $kw, $opts);
        if ($entity_section) {
            $article['content'] .= $entity_section;
        }

        // Add entity Schema markup
        $entity_schema = self::build_entity_schema($entities, $opts);
        if ($entity_schema) {
            $existing = $article['content'];
            $article['content'] = $existing . "\n<!-- Entity Schema -->\n"
                                 . '<script type="application/ld+json">' . $entity_schema . '</script>';
        }

        return $article;
    }

    /**
     * Get relevant entities for the business sector.
     */
    private static function get_entities_for_sector(array $opts): array {
        $sector  = $opts['sector'] ?: 'ristrutturazione';
        $city    = $opts['geo_city'] ?: 'Milano';
        $region  = $opts['geo_region'] ?: 'Lombardia';
        $country = $opts['geo_country'] ?: 'IT';
        $year    = date('Y');

        // Renovation-specific entities
        $renovation_entities = [
            ['name'=>'Agenzia delle Entrate','type'=>'GovernmentOrganization','url'=>'https://www.agenziaentrate.gov.it/','relevance'=>'Detrazioni fiscali'],
            ['name'=>'Comune di '.$city,'type'=>'GovernmentOrganization','url'=>'https://www.comune.'.strtolower($city).'.it/','relevance'=>'Permessi edilizi'],
            ['name'=>'CILA','type'=>'Thing','relevance'=>'Comunicazione Inizio Lavori Asseverata'],
            ['name'=>'SCIA','type'=>'Thing','relevance'=>'Segnalazione Certificata Inizio Attività'],
            ['name'=>'Bonus Ristrutturazioni','type'=>'Thing','relevance'=>'Detrazione 50% spese ristrutturazione '.$year],
            ['name'=>'Ecobonus','type'=>'Thing','relevance'=>'Incentivo per efficienza energetica'],
            ['name'=>'Sismabonus','type'=>'Thing','relevance'=>'Detrazione per lavori antisismici'],
        ];

        // Generic entities for any sector
        $generic_entities = [
            ['name'=>$city,'type'=>'City','relevance'=>'Città target'],
            ['name'=>$region,'type'=>'State','relevance'=>'Regione'],
        ];

        $renovation_sectors = ['ristrutturazione','edilizia','costruzione','renovation'];
        foreach ($renovation_sectors as $rs) {
            if (stripos($sector, $rs) !== false) {
                return array_merge($renovation_entities, $generic_entities);
            }
        }

        return $generic_entities;
    }

    /**
     * Build a natural-language section mentioning entities.
     */
    private static function build_entity_section(array $entities, string $kw, array $opts): string {
        $geo    = $opts['geo_city'] ?: 'Milano';
        $year   = date('Y');

        // Only add normative/legal entities
        $legal = array_filter($entities, fn($e) => in_array($e['type'], ['GovernmentOrganization','Thing'], true));
        if (empty($legal)) return '';

        $html = "<h2>Normative e Agevolazioni Fiscali per " . esc_html($kw) . " a " . esc_html($geo) . "</h2>\n<ul>\n";
        foreach (array_slice($legal, 0, 4) as $e) {
            $link = !empty($e['url'])
                ? '<a href="' . esc_url($e['url']) . '" target="_blank" rel="noopener">' . esc_html($e['name']) . '</a>'
                : '<strong>' . esc_html($e['name']) . '</strong>';
            $html .= '<li>' . $link . ': ' . esc_html($e['relevance']) . "</li>\n";
        }
        $html .= "</ul>\n";
        return $html;
    }

    /**
     * Build JSON-LD entity schema.
     */
    private static function build_entity_schema(array $entities, array $opts): string {
        $mentions = [];
        foreach (array_slice($entities, 0, 5) as $e) {
            $mention = ['@type'=>$e['type'],'name'=>$e['name']];
            if (!empty($e['url'])) $mention['url'] = $e['url'];
            $mentions[] = $mention;
        }
        if (empty($mentions)) return '';

        return wp_json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            'about'    => $mentions,
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /**
     * Analyze entity coverage of an existing post.
     */
    public static function analyze_post(int $post_id): array {
        $post    = get_post($post_id);
        $opts    = V4_Options::get();
        $content = wp_strip_all_tags($post->post_content);
        $entities = self::get_entities_for_sector($opts);

        $found   = [];
        $missing = [];
        foreach ($entities as $e) {
            if (stripos($content, $e['name']) !== false) {
                $found[] = $e['name'];
            } else {
                $missing[] = $e;
            }
        }

        return [
            'found'       => $found,
            'missing'     => $missing,
            'score'       => count($entities) > 0 ? round(count($found)/count($entities)*100) : 0,
            'total'       => count($entities),
        ];
    }
}


/**
 * V5.7 Feature 4: Multi-Location Manager
 * Write the same service for 10+ cities in one click.
 */
class V57_MultiLocation {

    const OPT_CITIES = 'soser_v57_cities';

    public static function get_cities(): array {
        $saved = get_option(self::OPT_CITIES, []);
        return !empty($saved) ? $saved : [];
    }

    public static function save_cities(array $cities): void {
        update_option(self::OPT_CITIES, array_map('sanitize_text_field', $cities));
    }

    /**
     * Queue articles for a keyword across multiple cities.
     * Returns number of jobs queued.
     */
    public static function queue_all(string $base_keyword, array $cities = []): int {
        if (empty($cities)) $cities = self::get_cities();
        if (empty($cities)) return 0;

        $base_keyword = sanitize_text_field($base_keyword);
        $count = 0;

        foreach ($cities as $city) {
            $city = trim(sanitize_text_field($city));
            if (!$city) continue;

            // Replace original city with target city in keyword
            $opts         = V4_Options::get();
            $original_city = $opts['geo_city'] ?: 'Milano';
            $kw = str_ireplace($original_city, $city, $base_keyword);
            if ($kw === $base_keyword) $kw = $base_keyword . ' ' . $city;

            // Check not already covered
            if (class_exists('V5_Semantic') && V5_Semantic::is_semantic_duplicate($kw, 0.90)) continue;

            V4_Queue::enqueue($kw, 'multilocation');
            V4_Queue::kick();
            $count++;
        }

        return $count;
    }

    /**
     * Get coverage stats per city.
     */
    public static function get_coverage(): array {
        $cities   = self::get_cities();
        $memory   = class_exists('V5_Memory') ? V5_Memory::get_all() : [];
        $coverage = [];

        foreach ($cities as $city) {
            $city_posts = array_filter($memory, fn($m) =>
                stripos($m['keyword'] ?? '', $city) !== false
            );
            $coverage[$city] = [
                'city'       => $city,
                'post_count' => count($city_posts),
                'keywords'   => array_column($city_posts, 'keyword'),
            ];
        }

        return $coverage;
    }
}
