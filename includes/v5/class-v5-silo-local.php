<?php
defined('ABSPATH') || exit;

/**
 * V5.1 Feature 4: Internal Silo Structure
 *
 * Builds a proper SEO silo architecture:
 * - Groups posts into topic silos
 * - Adds "Related articles" sections
 * - Injects silo navigation (breadcrumbs-style)
 * - Prevents cross-silo leaking
 * - Adds "Pillar page" link at bottom of every cluster article
 */
class V5_Silo {

    const OPT = 'v5_silo_map';

    public static function init(): void {
        add_filter('the_content', [__CLASS__, 'inject_silo_nav'], 25);
    }

    /**
     * Build silo map from existing content memory.
     * Groups posts by topic cluster.
     */
    public static function build_silo_map(): array {
        $cached = get_transient('v5_silo_map');
        if ($cached) return $cached;

        $memory  = class_exists('V5_Memory') ? V5_Memory::get_all() : [];
        $silos   = [];

        foreach ($memory as $item) {
            $topic = $item['topic'] ?: 'generale';
            if (!isset($silos[$topic])) {
                $silos[$topic] = ['topic'=>$topic,'posts'=>[],'pillar_id'=>null];
            }
            $silos[$topic]['posts'][] = [
                'post_id' => (int)$item['post_id'],
                'keyword' => $item['keyword'],
                'words'   => (int)$item['word_count'],
                'clicks'  => (int)$item['gsc_clicks'],
            ];
            // Longest article = pillar
            if (!$silos[$topic]['pillar_id'] || $item['word_count'] > ($silos[$topic]['pillar_words'] ?? 0)) {
                $silos[$topic]['pillar_id']    = (int)$item['post_id'];
                $silos[$topic]['pillar_words'] = (int)$item['word_count'];
            }
        }

        update_option(self::OPT, $silos);
        set_transient('v5_silo_map', $silos, 6 * HOUR_IN_SECONDS);
        return $silos;
    }

    /**
     * Inject silo navigation into post content.
     * Adds: related articles from same silo + pillar link.
     */
    public static function inject_silo_nav(string $content): string {
        if (!is_singular('post') || !in_the_loop()) return $content;

        $post_id = get_the_ID();
        $memory  = class_exists('V5_Memory') ? V5_Memory::get($post_id) : null;
        if (!$memory) return $content;

        $topic   = $memory['topic'];
        $silos   = self::build_silo_map();
        $silo    = $silos[$topic] ?? null;

        if (!$silo || count($silo['posts']) < 2) return $content;

        // Build related articles box
        $related_items = '';
        $count = 0;
        foreach ($silo['posts'] as $p) {
            if ($p['post_id'] === $post_id) continue;
            if ($count >= 3) break;
            $url   = get_permalink($p['post_id']);
            $title = get_the_title($p['post_id']);
            if (!$url || !$title) continue;
            $related_items .= '<li style="margin-bottom:6px"><a href="' . esc_url($url) . '" style="color:#2271b1">'
                            . esc_html($title) . '</a></li>';
            $count++;
        }
        if (!$related_items) return $content;

        $related_box = '<div class="v5-silo-related" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:16px 20px;margin:24px 0">'
                     . '<strong style="font-size:13px;display:block;margin-bottom:10px">📚 Articoli correlati su ' . esc_html(ucfirst($topic)) . '</strong>'
                     . '<ul style="margin:0;padding-right:20px;list-style:disc">' . $related_items . '</ul>'
                     . '</div>';

        // Add pillar link if this is a cluster article
        $pillar_link = '';
        if ($silo['pillar_id'] && $silo['pillar_id'] !== $post_id) {
            $p_url   = get_permalink($silo['pillar_id']);
            $p_title = get_the_title($silo['pillar_id']);
            if ($p_url && $p_title) {
                $pillar_link = '<p style="font-size:13px;margin-top:16px">🏛️ <strong>Guida completa:</strong> <a href="' . esc_url($p_url) . '">' . esc_html($p_title) . '</a></p>';
            }
        }

        return $content . $related_box . $pillar_link;
    }

    public static function invalidate(): void {
        delete_transient('v5_silo_map');
    }
}

/**
 * V5.1 Feature 5: Local SEO Engine
 *
 * Optimizes every article for local search in Milano and surrounding areas.
 * - Injects local signals into content
 * - Adds neighborhood-specific variations
 * - Optimizes for "near me" searches
 * - Adds LocalBusiness references
 */
class V5_LocalSEO {

    // Zones loaded dynamically from settings (can be customized per city)
    const MILAN_ZONES = []; // Legacy - use get_zones() instead

    public static function get_zones(): array {
        $opts  = V4_Options::get();
        $city  = $opts['geo_city'] ?: 'Milano';
        // Custom zones from settings
        $custom = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $opts['geo_zones'] ?? '')));
        if (!empty($custom)) return $custom;
        // Default Italian city zones (generic)
        return [
            $city . ' Centro', $city . ' Nord', $city . ' Sud', $city . ' Est', $city . ' Ovest',
            $city . ' Periferia', 'Provincia di ' . $city,
        ];
    }

    /**
     * Enhance article with local SEO signals.
     */
    public static function enhance(array $article, string $kw): array {
        $year = date('Y');
        $geo  = V4_Options::get()['geo'] ?: 'Milano';

        // 1. Add local price context if not present
        $city_name = V4_Options::get()['geo_city'] ?: 'Milano';
        if (stripos($article['content'], $city_name) === false || stripos($article['content'], 'euro') === false) {
            $local_box = '<div class="v5-local-box" style="background:#e8f4ff;border:1px solid #b8d4f0;border-radius:6px;padding:14px 18px;margin:20px 0">'
                       . '<strong>📍 Prezzi a ' . esc_html($geo) . ' (' . $year . ')</strong><br>'
                       . '<span style="font-size:13px;color:#444">I costi indicati si riferiscono al mercato di Milano e provincia. '
                       . 'Per un preventivo gratuito personalizzato nella tua zona, contattaci.</span>'
                       . '</div>';
            $article['content'] .= $local_box;
        }

        // 2. Add "zone served" section
        $city      = class_exists('V6_Profile') ? V6_Profile::city() : 'Milano';
        $country   = class_exists('V6_Profile') ? V6_Profile::get()['country'] ?? 'Italia' : 'Italia';
        $zones_str = implode(', ', array_slice(self::MILAN_ZONES, 0, 8));
        $lang      = class_exists('V6_Profile') ? V6_Profile::language() : 'it';
        $area_title = [
            'it' => "Dove Operiamo a {$city} e Provincia",
            'en' => "We operate in {$city} and surroundings",
            'ar' => "نعمل في {$city} والمنطقة المحيطة",
        ][$lang] ?? "Dove Operiamo a {$city}";
        $area_section = '<h2>' . esc_html($area_title) . '</h2>'
                      . '<p>Offriamo i nostri servizi di ' . esc_html(mb_substr($kw, 0, 40))
                      . ' nelle seguenti zone: <strong>' . esc_html($zones_str) . '</strong> e in tutta la provincia di ' . esc_html($city) . '.</p>';
        $article['content'] .= $area_section;

        // 3. Enhance slug with local signal
        if (stripos($article['slug'], 'milano') === false) {
            $article['slug'] = $article['slug'] . '-milano';
        }

        return $article;
    }

    /**
     * Generate local keyword variations for a base keyword.
     * Used by Keyword Intel to expand local opportunities.
     */
    public static function expand_local(string $base_kw): array {
        $variations = [];
        $top_zones  = array_slice(self::MILAN_ZONES, 0, 6);

        foreach ($top_zones as $zone) {
            $variations[] = $base_kw . ' ' . $zone;
            $variations[] = $zone . ' ' . $base_kw;
        }

        // Near me variants
        $variations[] = $base_kw . ' vicino a me';
        $opts_z = V4_Options::get();
        $city_z = $opts_z['geo_city'] ?: 'Milano';
        $reg_z  = $opts_z['geo_region'] ?: 'Lombardia';
        $variations[] = $base_kw . ' zona ' . $city_z;
        $variations[] = $base_kw . ' provincia ' . $city_z;
        $variations[] = $base_kw . ' ' . $reg_z;

        return $variations;
    }

    /**
     * Check if a keyword has local intent.
     */
    public static function has_local_intent(string $kw): bool {
        $l = mb_strtolower($kw);
        foreach (array_map('mb_strtolower', self::MILAN_ZONES) as $zone) {
            if (strpos($l, $zone) !== false) return true;
        }
        return (bool) preg_match('/vicino|zona|provincia|quartiere|near me/', $l);
    }

    /**
     * Get local SEO score for a keyword (0-100).
     */
    public static function local_score(string $kw): int {
        $score = 0;
        $l     = mb_strtolower($kw);
        if (strpos($l, 'milano') !== false)      $score += 30;
        if (strpos($l, 'lombard') !== false)     $score += 15;
        foreach (array_slice(self::MILAN_ZONES, 2) as $z) {
            if (strpos($l, mb_strtolower($z)) !== false) { $score += 20; break; }
        }
        if (preg_match('/prezzi?|costo?|preventivo/', $l)) $score += 25;
        if (preg_match('/vicino|near|zona/', $l))          $score += 10;
        return min(100, $score);
    }
}
