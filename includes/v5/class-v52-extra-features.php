<?php
defined('ABSPATH') || exit;

/**
 * V5.2 Feature 7: Google Business Profile Sync
 * Publishes article excerpts as GBP posts automatically.
 */
class V52_GBP {

    public static function publish_post(int $post_id, string $keyword): bool {
        $token    = get_option('v52_gbp_token','');
        $location = get_option('v52_gbp_location_id','');
        if (!$token || !$location) return false;

        $post    = get_post($post_id);
        $excerpt = get_the_excerpt($post_id) ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30);
        $url     = get_permalink($post_id);

        $body = [
            'languageCode' => 'it',
            'summary'      => mb_substr($excerpt, 0, 1500),
            'callToAction' => ['actionType'=>'LEARN_MORE','url'=>$url],
            'media'        => [],
        ];

        // Add featured image if exists
        $img_id = get_post_thumbnail_id($post_id);
        if ($img_id) {
            $img_url = wp_get_attachment_image_url($img_id, 'large');
            if ($img_url) $body['media'][] = ['mediaFormat'=>'PHOTO','sourceUrl'=>$img_url];
        }

        $r = wp_remote_post(
            "https://mybusiness.googleapis.com/v4/accounts/-/locations/{$location}/localPosts",
            [
                'timeout' => 20,
                'headers' => ['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],
                'body'    => wp_json_encode($body),
            ]
        );

        $ok = !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
        if ($ok) update_post_meta($post_id,'_v52_gbp_published',current_time('mysql'));
        return $ok;
    }
}

/**
 * V5.2 Feature 8: Multilingual SEO
 * Creates Italian + English versions of articles for foreign visitors in Milan.
 */
class V52_Multilingual {

    public static function translate_article(int $post_id): int {
        $opts = V4_Options::get();
        $post = get_post($post_id);
        $kw   = get_post_meta($post_id,'_soser_focus_keyword',true) ?: $post->post_title;

        // Check if English version already exists
        $existing = get_post_meta($post_id,'_v52_en_post_id',true);
        if ($existing && get_post($existing)) return (int)$existing;

        $prompt = "Translate this Italian article to English, keeping SEO optimization for Milan/Italy market.\n"
                . "Original keyword: \"{$kw}\" → English keyword target: \"" . str_ireplace('milano','Milan',$kw) . "\"\n\n"
                . "Article: " . mb_substr(wp_strip_all_tags($post->post_content),0,3000) . "\n\n"
                . 'JSON: {"title":"...","meta_description":"...","content_html":"...(full HTML)","en_keyword":"..."}';

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 3000, 72);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $data   = json_decode($text, true);
        if (!$data || empty($data['content_html'])) return 0;

        $en_post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content_html']),
            'post_excerpt' => sanitize_text_field($data['meta_description']??''),
            'post_status'  => 'draft', // Review before publishing
            'post_name'    => sanitize_title($data['title']) . '-milan-italy',
        ]);

        if (!is_wp_error($en_post_id)) {
            update_post_meta($en_post_id,'_soser_focus_keyword',$data['en_keyword']??$kw);
            update_post_meta($en_post_id,'_yoast_wpseo_metadesc',$data['meta_description']??'');
            update_post_meta($en_post_id,'_v52_translated_from',$post_id);
            update_post_meta($post_id,'_v52_en_post_id',$en_post_id);
            return (int)$en_post_id;
        }
        return 0;
    }
}

/**
 * V5.2 Feature 9: Voice Search Optimization
 * Optimizes content for "Ok Google, trovami..." queries.
 */
class V52_VoiceSearch {

    public static function optimize(array $article, string $kw): array {
        // Voice searches are conversational — add natural Q&A format
        $year = date('Y');
        $geo  = V4_Options::get()['geo'] ?: 'Milano';

        // Add voice-optimized FAQ if not present
        $voice_faq = '<div class="v52-voice-faq">';
        $voice_faq .= '<h2>Domande Frequenti su ' . esc_html(ucfirst($kw)) . '</h2>';

        $questions = self::get_voice_questions($kw, $geo);
        foreach ($questions as $q) {
            $voice_faq .= '<details style="margin-bottom:12px;border:1px solid #e0e0e0;border-radius:4px;padding:10px 14px">';
            $voice_faq .= '<summary style="font-weight:600;cursor:pointer">' . esc_html($q['q']) . '</summary>';
            $voice_faq .= '<p style="margin-top:8px;font-size:14px">' . esc_html($q['a']) . '</p>';
            $voice_faq .= '</details>';
        }
        $voice_faq .= '</div>';

        $article['content'] .= $voice_faq;
        return $article;
    }

    private static function get_voice_questions(string $kw, string $geo): array {
        return [
            ['q'=>"Quanto costa {$kw} a {$geo}?", 'a'=>"Il costo di {$kw} a {$geo} varia in base alle dimensioni e ai materiali. Contatta SOSER per un preventivo gratuito personalizzato."],
            ['q'=>"Chi fa {$kw} vicino a me a {$geo}?", 'a'=>"SOSER è un'impresa specializzata in {$kw} a {$geo} e provincia con oltre 10 anni di esperienza."],
            ['q'=>"Quanto tempo ci vuole per {$kw}?", 'a'=>"La durata dipende dalla complessità del progetto. In media, richiede dai 3 ai 15 giorni lavorativi."],
        ];
    }
}

/**
 * V5.2 Feature 10: Performance Monitor
 * Weekly ranking tracker and automatic report.
 */
class V52_PerformanceMonitor {

    const OPT_RANKINGS = 'v52_rankings_history';

    public static function init(): void {
        add_action('v52_weekly_report', [__CLASS__, 'run']);
        if (!wp_next_scheduled('v52_weekly_report')) {
            wp_schedule_event(strtotime('next Monday 07:00'), 'weekly', 'v52_weekly_report');
        }
    }

    public static function run(): void {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) return;

        $site_url = V4_GSC::get_site_url();
        $opps     = V4_GSC::get_keyword_opportunities($site_url, 30);

        $snapshot = [
            'date'        => date('Y-m-d'),
            'keywords'    => count($opps),
            'avg_position'=> count($opps) ? round(array_sum(array_column($opps,'position')) / count($opps), 1) : 0,
            'total_clicks'=> array_sum(array_column($opps,'clicks')),
            'top_10'      => count(array_filter($opps, fn($o) => $o['position'] <= 10)),
        ];

        $history   = get_option(self::OPT_RANKINGS, []);
        $history[] = $snapshot;
        $history   = array_slice($history, -52); // Keep 1 year
        update_option(self::OPT_RANKINGS, $history);

        // Email report if configured
        $email = get_option('v52_report_email','');
        if ($email && is_email($email)) {
            self::send_report($email, $snapshot, $history);
        }
    }

    public static function get_history(): array {
        return get_option(self::OPT_RANKINGS, []);
    }

    public static function get_trend(): array {
        $history = self::get_history();
        if (count($history) < 2) return ['direction'=>'neutral','change'=>0];
        $last  = end($history);
        $prev  = $history[count($history)-2];
        $change = round($last['avg_position'] - $prev['avg_position'], 1);
        return [
            'direction' => $change < 0 ? 'up' : ($change > 0 ? 'down' : 'neutral'),
            'change'    => abs($change),
            'clicks_change' => $last['total_clicks'] - $prev['total_clicks'],
        ];
    }

    private static function send_report(string $to, array $snapshot, array $history): void {
        $trend = self::get_trend();
        $arrow = $trend['direction'] === 'up' ? '📈' : ($trend['direction'] === 'down' ? '📉' : '➡️');

        $subject = "[SOSER SEO] Report settimanale — {$snapshot['date']}";
        $message = "Ciao!\n\nEcco il report SEO della settimana:\n\n"
                 . "• Posizione media: {$snapshot['avg_position']} {$arrow}\n"
                 . "• Keyword in top 10: {$snapshot['top_10']}\n"
                 . "• Click totali: {$snapshot['total_clicks']}\n\n"
                 . "Accedi al pannello: " . admin_url('admin.php?page=soser-v52-monitor') . "\n\n"
                 . "SOSER SEO Autopilot V5.2";

        wp_mail($to, $subject, $message);
    }
}
