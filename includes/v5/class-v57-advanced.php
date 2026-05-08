<?php
defined('ABSPATH') || exit;

/**
 * V5.7 Feature 5: Redirect Manager
 * Manages 301 redirects for merged/deleted articles.
 * Preserves SEO authority when consolidating content.
 */
class V57_RedirectManager {

    const OPT = 'soser_v57_redirects';

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'handle_redirect'], 1);
    }

    public static function add(string $from_slug, int $to_post_id): void {
        $redirects = get_option(self::OPT, []);
        $redirects[sanitize_title($from_slug)] = [
            'to_id'    => $to_post_id,
            'to_url'   => get_permalink($to_post_id),
            'added_at' => current_time('mysql'),
        ];
        update_option(self::OPT, $redirects);
    }

    public static function remove(string $from_slug): void {
        $redirects = get_option(self::OPT, []);
        unset($redirects[sanitize_title($from_slug)]);
        update_option(self::OPT, $redirects);
    }

    public static function get_all(): array {
        return get_option(self::OPT, []);
    }

    public static function handle_redirect(): void {
        $slug      = get_query_var('name') ?: trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $slug      = sanitize_title($slug);
        $redirects = get_option(self::OPT, []);

        if (isset($redirects[$slug])) {
            $url = get_permalink($redirects[$slug]['to_id']) ?: $redirects[$slug]['to_url'];
            if ($url) {
                wp_redirect($url, 301);
                exit;
            }
        }
    }

    /** Auto-add redirect when a post is merged (called by Cannibalization fixer) */
    public static function auto_redirect_merged(int $from_id, int $to_id): void {
        $post = get_post($from_id);
        if ($post) self::add($post->post_name, $to_id);
    }
}


/**
 * V5.7 Feature 6: Content Score Live
 * Real-time SEO scoring with actionable tips.
 */
class V57_ContentScore {

    /**
     * Full content analysis — returns score 0-100 with detailed breakdown.
     */
    public static function analyze(int $post_id): array {
        $post    = get_post($post_id);
        $kw      = get_post_meta($post_id,'_soser_focus_keyword',true)
                ?: get_post_meta($post_id,'_yoast_wpseo_focuskw',true)
                ?: '';
        $content = $post->post_content;
        $title   = $post->post_title;
        $meta    = get_post_meta($post_id,'_yoast_wpseo_metadesc',true) ?: '';
        $plain   = wp_strip_all_tags($content);
        $words   = str_word_count($plain);

        $scores  = [];
        $tips    = [];

        // ① Keyword in title (15 pts)
        $s1 = ($kw && stripos($title, $kw) !== false) ? 15 : 0;
        $scores['title'] = $s1;
        if (!$s1 && $kw) $tips[] = "❌ Keyword mancante nel titolo";

        // ② Keyword in first 100 words (15 pts)
        $first_100 = mb_substr($plain, 0, 500);
        $s2 = ($kw && stripos($first_100, $kw) !== false) ? 15 : 0;
        $scores['intro'] = $s2;
        if (!$s2 && $kw) $tips[] = "❌ Keyword assente nei primi 100 caratteri";

        // ③ Meta description (10 pts)
        $meta_len = mb_strlen($meta);
        $s3 = ($meta_len >= 120 && $meta_len <= 155) ? 10 : ($meta_len > 0 ? 5 : 0);
        $scores['meta'] = $s3;
        if ($meta_len < 120) $tips[] = "⚠️ Meta description troppo corta ({$meta_len} chars, minimo 120)";
        if ($meta_len > 155) $tips[] = "⚠️ Meta description troppo lunga ({$meta_len} chars, massimo 155)";

        // ④ Word count (15 pts)
        $s4 = $words >= 1200 ? 15 : ($words >= 800 ? 10 : ($words >= 500 ? 5 : 0));
        $scores['length'] = $s4;
        if ($words < 800) $tips[] = "❌ Articolo troppo corto ({$words} parole) — punta a 1200+";

        // ⑤ H2 structure (10 pts)
        $h2_count = substr_count($content, '<h2');
        $s5 = $h2_count >= 4 ? 10 : ($h2_count >= 2 ? 6 : ($h2_count >= 1 ? 3 : 0));
        $scores['headings'] = $s5;
        if ($h2_count < 3) $tips[] = "⚠️ Aggiungi più H2 (hai {$h2_count}, consigliati 4+)";

        // ⑥ Internal links (10 pts)
        preg_match_all('/href=["\']' . preg_quote(home_url(''), '/') . '/i', $content, $int_m);
        $int_links = count($int_m[0]);
        $s6 = $int_links >= 3 ? 10 : ($int_links >= 1 ? 5 : 0);
        $scores['internal_links'] = $s6;
        if ($int_links < 2) $tips[] = "❌ Aggiungi link interni ({$int_links} trovati, minimo 2)";

        // ⑦ External links (5 pts)
        $ext_links = substr_count($content, 'rel="nofollow') + substr_count($content, 'rel="noopener');
        $s7 = $ext_links >= 1 ? 5 : 0;
        $scores['external_links'] = $s7;
        if (!$s7) $tips[] = "⚠️ Aggiungi almeno 1 link esterno a fonte autorevole";

        // ⑧ Images with alt (5 pts)
        preg_match_all('/<img[^>]+>/i', $content, $img_m);
        $imgs       = count($img_m[0]);
        $imgs_w_alt = count(array_filter($img_m[0], fn($i) => strpos($i,'alt=') !== false));
        $s8 = ($imgs > 0 && $imgs_w_alt === $imgs) ? 5 : ($imgs_w_alt > 0 ? 3 : ($imgs > 0 ? 1 : 0));
        $scores['images'] = $s8;
        if ($imgs > 0 && $imgs_w_alt < $imgs) $tips[] = "⚠️ " . ($imgs-$imgs_w_alt) . " immagini senza alt text";

        // ⑨ Schema (10 pts)
        $s9 = (strpos($content,'schema.org') !== false || get_post_meta($post_id,'_soser_faq_schema',true)) ? 10 : 0;
        $scores['schema'] = $s9;
        if (!$s9) $tips[] = "❌ Schema markup mancante";

        // ⑩ Featured snippet readiness (5 pts)
        $snippet = V57_FeaturedSnippet::score($content, $kw);
        $s10     = (int)($snippet['score'] / 20); // 0-5
        $scores['snippet'] = $s10;
        if ($snippet['score'] < 60) $tips[] = "💡 Ottimizza per Featured Snippet: " . ($snippet['tips'][0] ?? '');

        $total = array_sum($scores);
        $grade = $total >= 85 ? 'A' : ($total >= 70 ? 'B' : ($total >= 55 ? 'C' : ($total >= 40 ? 'D' : 'F')));

        return [
            'total'       => min(100, $total),
            'grade'       => $grade,
            'breakdown'   => $scores,
            'tips'        => $tips,
            'word_count'  => $words,
            'h2_count'    => $h2_count,
            'int_links'   => $int_links,
            'images'      => $imgs,
        ];
    }

    /**
     * Bulk score all published posts.
     */
    public static function bulk_score(int $limit = 50): array {
        $posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>$limit]);
        $results = [];
        foreach ($posts as $post) {
            $score = self::analyze($post->ID);
            $results[] = [
                'post_id' => $post->ID,
                'title'   => $post->post_title,
                'score'   => $score['total'],
                'grade'   => $score['grade'],
                'tips'    => count($score['tips']),
            ];
        }
        usort($results, fn($a,$b) => $a['score'] <=> $b['score']); // Worst first
        return $results;
    }
}


/**
 * V5.7 Feature 7: SERP Tracker
 * Weekly keyword position tracking with history.
 */
class V57_SERPTracker {

    const OPT_HISTORY  = 'soser_v57_serp_history';
    const OPT_KEYWORDS = 'soser_v57_track_keywords';
    const CRON         = 'soser_v57_serp_check';

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run_check']);
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(strtotime('next Monday 06:00'), 'weekly', self::CRON);
        }
    }

    public static function get_tracked_keywords(): array {
        return get_option(self::OPT_KEYWORDS, []);
    }

    public static function save_keywords(array $kws): void {
        update_option(self::OPT_KEYWORDS, array_map('sanitize_text_field', array_filter($kws)));
    }

    /**
     * Pull position data from GSC for tracked keywords.
     */
    public static function run_check(): void {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) return;

        $site = V4_GSC::get_site_url();
        $opps = V4_GSC::get_keyword_opportunities($site, 30);

        if (empty($opps)) return;

        $history   = get_option(self::OPT_HISTORY, []);
        $today     = date('Y-m-d');
        $snapshot  = ['date'=>$today,'keywords'=>[]];

        foreach ($opps as $opp) {
            $snapshot['keywords'][$opp['keyword']] = [
                'position'    => round($opp['position'], 1),
                'impressions' => $opp['impressions'],
                'clicks'      => $opp['clicks'],
                'ctr'         => $opp['ctr'],
            ];
        }

        $history[] = $snapshot;
        $history   = array_slice($history, -52); // 1 year
        update_option(self::OPT_HISTORY, $history);
    }

    /**
     * Get position history for a specific keyword.
     */
    public static function get_keyword_trend(string $kw): array {
        $history = get_option(self::OPT_HISTORY, []);
        $trend   = [];

        foreach ($history as $snap) {
            $kw_lower = mb_strtolower($kw);
            foreach ($snap['keywords'] as $tracked_kw => $data) {
                if (mb_strtolower($tracked_kw) === $kw_lower) {
                    $trend[] = ['date'=>$snap['date'],'position'=>$data['position'],'clicks'=>$data['clicks']];
                    break;
                }
            }
        }
        return $trend;
    }

    /**
     * Get all keywords with their latest positions.
     */
    public static function get_latest_positions(): array {
        $history = get_option(self::OPT_HISTORY, []);
        if (empty($history)) return [];

        $latest    = end($history);
        $previous  = count($history) >= 2 ? $history[count($history)-2] : null;
        $positions = [];

        foreach ($latest['keywords'] as $kw => $data) {
            $prev_pos  = $previous['keywords'][$kw]['position'] ?? null;
            $change    = $prev_pos ? round($prev_pos - $data['position'], 1) : 0; // Positive = improved

            $positions[] = [
                'keyword'    => $kw,
                'position'   => $data['position'],
                'change'     => $change,
                'trend'      => $change > 0 ? '📈' : ($change < 0 ? '📉' : '➡️'),
                'impressions'=> $data['impressions'],
                'clicks'     => $data['clicks'],
                'ctr'        => $data['ctr'],
            ];
        }

        usort($positions, fn($a,$b) => $a['position'] <=> $b['position']);
        return $positions;
    }
}


/**
 * V5.7 Feature 8: Knowledge Graph Builder
 * Builds an entity network that helps Google understand your site as an authority.
 */
class V57_KnowledgeGraph {

    /**
     * Generate site-wide Knowledge Graph Schema.
     * Output in <head> of homepage.
     */
    public static function init(): void {
        add_action('wp_head', [__CLASS__, 'output_organization_schema'], 2);
    }

    public static function output_organization_schema(): void {
        if (!is_front_page() && !is_home()) return;
        $schema = self::build();
        if ($schema) echo '<script type="application/ld+json">' . $schema . '</script>' . "\n";
    }

    public static function build(): string {
        $cached = get_transient('v57_kg_schema');
        if ($cached !== false) return $cached;

        $opts     = V4_Options::get();
        $city     = $opts['geo_city']    ?: 'Milano';
        $region   = $opts['geo_region']  ?: 'Lombardia';
        $country  = $opts['geo_country'] ?: 'IT';
        $business = $opts['business']    ?: get_bloginfo('name');
        $sector   = $opts['sector']      ?: 'ristrutturazione';
        $type     = $opts['business_type'] ?: 'LocalBusiness';
        $year     = date('Y');

        // Build service list from active services
        $services_schema = [];
        if (class_exists('V54_ServiceFocus')) {
            foreach (V54_ServiceFocus::get_active_services() as $svc) {
                $services_schema[] = [
                    '@type'       => 'Service',
                    'name'        => $svc['name'],
                    'description' => "Servizio di " . mb_strtolower($svc['name']) . " a {$city}",
                    'areaServed'  => ['@type'=>'City','name'=>$city],
                    'provider'    => ['@id'=>home_url('/#organization')],
                ];
            }
        }

        // Build knowledge graph
        $schema = [
            '@context'  => 'https://schema.org',
            '@graph'    => [
                // Organization node
                [
                    '@type'           => [$type, 'Organization'],
                    '@id'             => home_url('/#organization'),
                    'name'            => $business,
                    'url'             => home_url('/'),
                    'logo'            => ['@type'=>'ImageObject','url'=>get_site_icon_url(512)],
                    'foundingDate'    => get_option('v57_founding_year', '2015'),
                    'numberOfEmployees' => get_option('v57_employees', '10'),
                    'address'         => [
                        '@type'           => 'PostalAddress',
                        'addressLocality' => $city,
                        'addressRegion'   => $region,
                        'addressCountry'  => $country,
                    ],
                    'areaServed'      => ['@type'=>'City','name'=>$city],
                    'knowsAbout'      => array_column(
                        class_exists('V54_ServiceFocus') ? V54_ServiceFocus::get_active_services() : [],
                        'name'
                    ),
                    'hasOfferCatalog' => [
                        '@type'           => 'OfferCatalog',
                        'name'            => "Servizi di " . $sector . " a " . $city,
                        'itemListElement' => $services_schema,
                    ],
                    'aggregateRating' => [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => get_option('v5_rating_value','4.8'),
                        'reviewCount' => get_option('v5_review_count','47'),
                    ],
                ],
                // WebSite node
                [
                    '@type'           => 'WebSite',
                    '@id'             => home_url('/#website'),
                    'url'             => home_url('/'),
                    'name'            => $business,
                    'publisher'       => ['@id'=>home_url('/#organization')],
                    'inLanguage'      => $opts['language'] ?: 'it',
                    'potentialAction' => [
                        '@type'       => 'SearchAction',
                        'target'      => ['@type'=>'EntryPoint','urlTemplate'=>home_url('/?s={search_term_string}')],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];

        $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        set_transient('v57_kg_schema', $json, 24 * HOUR_IN_SECONDS);
        return $json;
    }

    public static function invalidate(): void {
        delete_transient('v57_kg_schema');
    }
}
