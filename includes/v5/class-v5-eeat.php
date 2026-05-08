<?php
defined('ABSPATH') || exit;

/**
 * V5.1 Feature 3: E-E-A-T Signals
 *
 * Google's E-E-A-T = Experience, Expertise, Authoritativeness, Trustworthiness
 * This class automatically injects trust signals into every article:
 * - Author bio with expertise
 * - Publication + update dates
 * - Reviewer/expert attribution
 * - Trust badges (years of experience, certifications)
 * - Breadcrumbs schema
 */
class V5_EEAT {

    public static function init(): void {
        // Inject author bio after content
        add_filter('the_content', [__CLASS__, 'inject_author_bio'], 20);
        // Breadcrumbs schema
        add_action('wp_head', [__CLASS__, 'breadcrumb_schema'], 4);
    }

    /**
     * Inject expert author bio box after article content.
     */
    public static function inject_author_bio(string $content): string {
        if (!is_singular('post') || !in_the_loop()) return $content;

        $opts     = V4_Options::get();
        $business = $opts['business'] ?: get_bloginfo('name');
        $geo      = $opts['geo'] ?: 'Milano';
        $years    = get_option('v5_years_experience', '10');
        $cert     = get_option('v5_certifications', 'Impresa edile certificata');
        $reviews  = get_option('v5_review_count', '47');
        $rating   = get_option('v5_rating_value', '4.8');

        // Only add if not already present
        if (strpos($content, 'v5-author-box') !== false) return $content;

        $last_updated = get_the_modified_date('d/m/Y');

        $bio = '<div class="v5-author-box" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin:30px 0;display:flex;gap:16px;align-items:flex-start">'
             . '<div style="flex-shrink:0;width:56px;height:56px;background:#2271b1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px">🏗️</div>'
             . '<div>'
             . '<strong style="font-size:15px;display:block;margin-bottom:4px">' . esc_html($business) . '</strong>'
             . '<span style="font-size:12px;color:#555;display:block;margin-bottom:8px">'
             . esc_html($cert) . ' · ' . esc_html($geo) . ' · ' . esc_html($years) . '+ anni di esperienza'
             . '</span>'
             . '<div style="font-size:12px;color:#777;display:flex;gap:16px;flex-wrap:wrap">'
             . '<span>⭐ ' . esc_html($rating) . '/5 (' . esc_html($reviews) . ' recensioni)</span>'
             . '<span>📅 Aggiornato: ' . esc_html($last_updated) . '</span>'
             . '</div>'
             . '</div>'
             . '</div>';

        return $content . $bio;
    }

    /**
     * Breadcrumb schema for all pages.
     */
    public static function breadcrumb_schema(): void {
        if (!is_singular()) return;

        $items = [
            ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>home_url('/')],
        ];

        if (is_singular('post')) {
            $cats = get_the_category();
            if ($cats) {
                $items[] = ['@type'=>'ListItem','position'=>2,'name'=>$cats[0]->name,'item'=>get_category_link($cats[0]->term_id)];
            }
            $items[] = ['@type'=>'ListItem','position'=>count($items)+1,'name'=>get_the_title(),'item'=>get_permalink()];
        }

        $schema = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$items];
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * Add EEAT signals to a new article during generation.
     */
    public static function enrich_article(array $article, string $kw): array {
        $opts    = V4_Options::get();
        $year    = date('Y');
        $geo     = $opts['geo'] ?: 'Milano';
        $business = $opts['business'] ?: 'SOSER';

        // Add expert review note at top
        $expertise_note = '<p style="font-size:13px;color:#555;background:#fff9e6;padding:10px 14px;border-radius:4px;margin-bottom:20px">'
                        . '📋 <strong>Verificato da ' . esc_html($business) . '</strong> — '
                        . esc_html($geo) . ' · Aggiornato ' . esc_html($year) . ' · '
                        . esc_html(get_option('v5_years_experience','10')) . '+ anni di esperienza nel settore'
                        . '</p>';

        $article['content'] = $expertise_note . $article['content'];

        return $article;
    }

    /**
     * Add trust signals settings to options page.
     * Returns HTML fields for settings page.
     */
    public static function settings_fields(): string {
        $fields = [
            'v5_years_experience' => ['label'=>'Anni di esperienza', 'default'=>'10'],
            'v5_certifications'   => ['label'=>'Certificazione', 'default'=>'Impresa edile certificata'],
            'v5_rating_value'     => ['label'=>'Valutazione (es. 4.8)', 'default'=>'4.8'],
            'v5_review_count'     => ['label'=>'N° recensioni', 'default'=>'47'],
            'v5_facebook_url'     => ['label'=>'URL Facebook', 'default'=>''],
            'v5_linkedin_url'     => ['label'=>'URL LinkedIn', 'default'=>''],
        ];
        $html = '';
        foreach ($fields as $key => $f) {
            $val   = esc_attr(get_option($key, $f['default']));
            $label = esc_html($f['label']);
            $html .= "<tr><th>{$label}</th><td><input type='text' name='{$key}' value='{$val}' class='regular-text'></td></tr>";
        }
        return $html;
    }

    public static function save_settings(array $post): void {
        $keys = ['v5_years_experience','v5_certifications','v5_rating_value','v5_review_count','v5_facebook_url','v5_linkedin_url'];
        foreach ($keys as $k) {
            if (isset($post[$k])) update_option($k, sanitize_text_field($post[$k]));
        }
    }
}
