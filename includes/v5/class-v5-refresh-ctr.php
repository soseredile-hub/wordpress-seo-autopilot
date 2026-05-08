<?php
defined('ABSPATH') || exit;

/**
 * V10 Feature 8: Content Refresh Engine
 * Detects stale/declining articles and updates them automatically.
 */
class V5_Refresh {

    public static function refresh(int $post_id): bool {
        $opts   = V4_Options::get();
        $post   = get_post($post_id);
        $memory = V5_Memory::get($post_id);
        if (!$post || empty($opts['openai_key'])) return false;

        $kw      = $memory['keyword'] ?? get_post_meta($post_id,'_soser_focus_keyword',true) ?: $post->post_title;
        $excerpt = mb_substr(wp_strip_all_tags($post->post_content), 0, 2000);
        $year    = date('Y');

        $prompt = "Aggiorna questo articolo SEO per {$year}. Keyword: \"{$kw}\"\n"
                . "Problemi: " . ($memory && $memory['gsc_position']>15 ? "posizione bassa ({$memory['gsc_position']}), " : '')
                . ($memory && $memory['gsc_ctr']<0.02 ? "CTR basso " : '') . "\n"
                . "Contenuto attuale:\n{$excerpt}\n\n"
                . "Migliora: aggiorna prezzi/{$year}, ottimizza H1, aggiungi FAQ, AI Overview.\n"
                . 'JSON: {"new_title":"...","new_content_html":"...(HTML)","new_meta":"...(130-150 chars)","changes":"..."}';

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => ['Authorization'=>'Bearer '.$opts['openai_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['model'=>$opts['openai_model'],'max_tokens'=>3000,'temperature'=>0.6,
                'messages'=>[['role'=>'user','content'=>$prompt]],
                'response_format'=>['type'=>'json_object']]),
        ]);

        if (is_wp_error($r)) return false;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        V5_Cost::track($opts['openai_model'], $body['usage']['prompt_tokens']??0, $body['usage']['completion_tokens']??0);

        $text = $body['choices'][0]['message']['content'] ?? '';
        $data = json_decode($text, true);
        if (!$data || empty($data['new_content_html'])) return false;

        wp_update_post(['ID'=>$post_id,'post_title'=>sanitize_text_field($data['new_title']??$post->post_title),'post_content'=>wp_kses_post($data['new_content_html'])]);

        if (!empty($data['new_meta'])) {
            update_post_meta($post_id,'_yoast_wpseo_metadesc',$data['new_meta']);
            update_post_meta($post_id,'rank_math_description',$data['new_meta']);
        }
        global $wpdb;
        $wpdb->update($wpdb->prefix.V5_Memory::TABLE,['needs_refresh'=>0,'updated_at'=>current_time('mysql')],['post_id'=>$post_id]);
        V5_Memory::sync();
        return true;
    }

    public static function scan_stale(): int {
        $memory  = V5_Memory::get_all();
        $flagged = 0;
        foreach ($memory as $item) {
            $post = get_post($item['post_id']);
            if (!$post) continue;
            $age  = (time() - strtotime($post->post_modified)) / DAY_IN_SECONDS;
            $flag = $age > 180
                || ($item['gsc_impr'] > 200 && $item['gsc_ctr'] < 0.02)
                || ($item['gsc_position'] > 15 && $item['gsc_impr'] > 50)
                || (preg_match('/202[0-3]/', $post->post_title) && !preg_match('/' . date('Y') . '/', $post->post_title));
            if ($flag) {
                V5_Memory::update_gsc($item['post_id'], [
                    'clicks'=>$item['gsc_clicks'],'impressions'=>$item['gsc_impr'],
                    'ctr'=>$item['gsc_ctr'],'position'=>$item['gsc_position'],
                ]);
                $flagged++;
            }
        }
        return $flagged;
    }
}

/**
 * V10 Feature 9: AI CTR Optimizer
 * Generates better titles/meta to increase click-through rate.
 */
class V5_CTR {

    public static function optimize_meta(int $post_id): string {
        $opts   = V4_Options::get();
        $memory = V5_Memory::get($post_id);
        $kw     = $memory['keyword'] ?? get_post_meta($post_id,'_soser_focus_keyword',true) ?? '';
        $cur    = get_post_meta($post_id,'_yoast_wpseo_metadesc',true) ?: '';
        $ctr    = $memory ? round($memory['gsc_ctr']*100,1).'%' : 'sconosciuto';

        $prompt = "Ottimizza questa meta description per CTR.\nKeyword: \"{$kw}\"\nCTR attuale: {$ctr}\nMeta attuale: \"{$cur}\"\n"
                . "Requisiti: 130-150 caratteri, include keyword, beneficio, CTA.\nRispondi SOLO con il testo meta.";

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 100);
        $meta   = trim(strip_tags($result));
        if (mb_strlen($meta)>150) $meta = mb_substr($meta,0,147).'…';
        return $meta;
    }

    public static function generate_title_variants(int $post_id): array {
        $opts   = V4_Options::get();
        $post   = get_post($post_id);
        $memory = V5_Memory::get($post_id);
        $kw     = $memory['keyword'] ?? '';

        $prompt = "Genera 3 varianti di titolo SEO per CTR alto.\nKeyword: \"{$kw}\"\nTitolo attuale: \"{$post->post_title}\"\n"
                . "Usa: numeri, anni, domande, urgenza. Max 60 caratteri.\n"
                . 'JSON: [{"title":"...","hook":"...","boost":"+X%"}]';

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 300, 48);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        return json_decode($text, true) ?: [];
    }

    public static function bulk_optimize(int $limit = 5): int {
        $memory = V5_Memory::get_all();
        $count  = 0;
        foreach ($memory as $item) {
            if ($count >= $limit) break;
            if ($item['gsc_impr'] < 50 || $item['gsc_ctr'] >= 0.05) continue;
            $meta = self::optimize_meta((int)$item['post_id']);
            if ($meta) {
                update_post_meta($item['post_id'],'_yoast_wpseo_metadesc',$meta);
                update_post_meta($item['post_id'],'rank_math_description',$meta);
                $count++;
            }
        }
        return $count;
    }
}
