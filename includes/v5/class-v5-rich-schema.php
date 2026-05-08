<?php
defined('ABSPATH') || exit;

/**
 * V5.1 Feature 1: Rich Schema Markup
 *
 * Generates and injects advanced Schema.org markup:
 * - LocalBusiness (sitewide)
 * - Article + Author (every post)
 * - FAQPage (posts with FAQ)
 * - HowTo (guide posts)
 * - AggregateRating (with review count)
 * - PriceRange (service pages)
 *
 * Why: Rich snippets increase CTR by 20-30% even from position 3-5.
 */
class V5_Rich_Schema {

    public static function init(): void {
        // Inject all schemas in <head> — frontend safe (no heavy processing)
        add_action('wp_head', [__CLASS__, 'output_schemas'], 3);
    }

    public static function output_schemas(): void {
        // LocalBusiness — output on every page (cached)
        $lb = self::get_local_business_schema();
        if ($lb) echo '<script type="application/ld+json">' . $lb . '</script>' . "\n";

        // Post-specific schemas
        if (!is_singular('post')) return;

        $post_id = get_the_ID();

        // Article + Author
        $article = self::get_article_schema($post_id);
        if ($article) echo '<script type="application/ld+json">' . $article . '</script>' . "\n";

        // FAQ (stored during generation)
        $faq = get_post_meta($post_id, '_soser_faq_schema', true);
        if ($faq) echo $faq . "\n";

        // HowTo (if post contains step-by-step)
        $howto = self::get_howto_schema($post_id);
        if ($howto) echo '<script type="application/ld+json">' . $howto . '</script>' . "\n";
    }

    // ── LocalBusiness Schema ──────────────────────────────────────

    private static function get_local_business_schema(): string {
        $cached = get_transient('v5_schema_lb');
        if ($cached !== false) return $cached;

        $opts = V4_Options::get();
        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => ['LocalBusiness', 'HomeAndConstructionBusiness'],
            'name'            => $opts['business'] ?: get_bloginfo('name'),
            'url'             => home_url('/'),
            'description'     => get_bloginfo('description'),
            'address'         => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $opts['geo_city']   ?: 'Milano',
                'addressRegion'   => $opts['geo_region'] ?: 'Lombardia',
                'addressCountry'  => $opts['geo_country']?: 'IT',
            ],
            'geo'             => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $opts['geo_lat'] ?: '45.4654',
                'longitude' => $opts['geo_lon'] ?: '9.1866',
            ],
            'areaServed'      => array_filter([
                ['@type'=>'City','name'  => $opts['geo_city']   ?: 'Milano'],
                ['@type'=>'State','name' => $opts['geo_region'] ?: 'Lombardia'],
            ]),
            'priceRange'      => $opts['price_range'] ?: '€€',
            'currenciesAccepted' => 'EUR',
            'paymentAccepted' => 'Bonifico bancario, Carta di credito',
            'openingHoursSpecification' => [
                ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>['Monday','Tuesday','Wednesday','Thursday','Friday'],'opens'=>'08:00','closes'=>'18:00'],
                ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>['Saturday'],'opens'=>'08:00','closes'=>'12:00'],
            ],
            'sameAs'          => array_filter([
                get_option('v5_facebook_url',''),
                get_option('v5_linkedin_url',''),
                get_option('v5_instagram_url',''),
            ]),
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => get_option('v5_rating_value','4.8'),
                'reviewCount' => get_option('v5_review_count','47'),
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
        ];

        // Add logo if exists
        $logo_id = get_option('site_logo') ?: attachment_url_to_postid(get_site_icon_url());
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                $schema['logo'] = ['@type'=>'ImageObject','url'=>$logo_url];
                $schema['image'] = $schema['logo'];
            }
        }

        $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        set_transient('v5_schema_lb', $json, 24 * HOUR_IN_SECONDS);
        return $json;
    }

    // ── Article Schema ────────────────────────────────────────────

    private static function get_article_schema(int $post_id): string {
        $post    = get_post($post_id);
        $opts    = V4_Options::get();
        $keyword = get_post_meta($post_id, '_soser_focus_keyword', true) ?: '';
        $img_id  = get_post_thumbnail_id($post_id);
        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'large') : '';

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => wp_strip_all_tags(get_the_title($post_id)),
            'description'      => get_post_meta($post_id,'_yoast_wpseo_metadesc',true) ?: wp_trim_words(wp_strip_all_tags($post->post_content), 25),
            'datePublished'    => get_the_date('c', $post_id),
            'dateModified'     => get_the_modified_date('c', $post_id),
            'url'              => get_permalink($post_id),
            'inLanguage'       => 'it-IT',
            'author'           => [
                '@type' => 'Organization',
                'name'  => $opts['business'] ?: get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => $opts['business'] ?: get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
            'mainEntityOfPage' => ['@type'=>'WebPage','@id'=>get_permalink($post_id)],
            'keywords'         => $keyword,
            'articleSection'   => get_the_category($post_id)[0]->name ?? 'Ristrutturazione',
            'wordCount'        => str_word_count(wp_strip_all_tags($post->post_content)),
        ];

        if ($img_url) {
            $schema['image'] = ['@type'=>'ImageObject','url'=>$img_url,'width'=>1024,'height'=>1024];
        }

        return wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ── HowTo Schema ─────────────────────────────────────────────

    private static function get_howto_schema(int $post_id): string {
        $post = get_post($post_id);
        // Only generate for "how to" / "guida" posts
        $title = mb_strtolower(get_the_title($post_id));
        if (!preg_match('/come fare|guida|passo|step|procedura|istruzioni/', $title)) return '';

        // Extract ordered list items as steps
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $post->post_content, $matches);
        if (count($matches[1]) < 3) return '';

        $steps = [];
        foreach (array_slice($matches[1], 0, 8) as $i => $step) {
            $text = wp_strip_all_tags($step);
            if (mb_strlen($text) < 10) continue;
            $steps[] = [
                '@type' => 'HowToStep',
                'name'  => mb_substr($text, 0, 50),
                'text'  => mb_substr($text, 0, 200),
                'position' => $i + 1,
            ];
        }
        if (count($steps) < 3) return '';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => get_the_title($post_id),
            'description' => wp_trim_words(wp_strip_all_tags($post->post_content), 20),
            'totalTime'   => 'PT2H',
            'step'        => $steps,
        ];
        return wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ── Generate Rich Schema for new articles (called from generator) ──

    public static function generate_for_post(int $post_id, array $article, string $kw): void {
        // Clear cached schemas
        delete_transient('v5_schema_lb');

        // Service schema with price range
        $price_match = [];
        preg_match('/(\d[\d.,]+)\s*(?:euro|€)/i', $article['content'] ?? '', $price_match);

        if (!empty($price_match[1])) {
            $price = str_replace(['.', ','], ['', '.'], $price_match[1]);
            $service_schema = wp_json_encode([
                '@context'    => 'https://schema.org',
                '@type'       => 'Service',
                'name'        => $article['title'],
                'description' => $article['meta_description'] ?? '',
                'provider'    => ['@type'=>'LocalBusiness','name'=>V4_Options::get()['business'],'url'=>home_url('/')],
                'areaServed'  => ['@type'=>'City','name'=> V4_Options::get()['geo_city'] ?: 'Milano'],
                'offers'      => ['@type'=>'Offer','priceCurrency'=>'EUR','price'=>$price,'availability'=>'https://schema.org/InStock'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existing = get_post_meta($post_id, '_soser_faq_schema', true) ?: '';
            $existing .= "\n<script type=\"application/ld+json\">{$service_schema}</script>";
            update_post_meta($post_id, '_soser_faq_schema', $existing);
        }
    }
}
