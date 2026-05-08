<?php
defined('ABSPATH') || exit;

/**
 * V11 Feature 10: Google Discover Engine
 * Optimizes content for Google Discover (trending + emotional + mobile).
 */
class V5_Discover {

    public static function optimize_for_discover(array $article): array {
        $opts   = V4_Options::get();

        $prompt = "Ottimizza questo articolo per Google Discover.\nTitolo: \"{$article['title']}\"\nKeyword: \"{$article['keyword']}\"\n"
                . "Requisiti Discover:\n- Titolo emotivo 50-70 caratteri\n- Niente clickbait\n- Mobile-first\n- Trending topic\n"
                . 'JSON: {"discover_title":"...","discover_meta":"...","emotional_hook":"...","trending_angle":"..."}';

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 200, 24);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $data   = json_decode($text, true);

        if ($data && !empty($data['discover_title'])) {
            $article['discover_title'] = sanitize_text_field($data['discover_title']);
            $article['discover_meta']  = sanitize_text_field($data['discover_meta'] ?? '');
        }
        return $article;
    }
}

/**
 * V11 Feature 11: Social AI Engine
 * Auto-publishes to Facebook and LinkedIn with AI captions.
 */
class V5_Social {

    public static function publish(int $post_id, string $keyword, string $title): bool {
        $opts = V4_Options::get();
        if ($opts['enable_social'] !== '1') return false;

        $caption   = self::generate_caption($keyword, $title, $opts);
        $hashtags  = self::hashtags($keyword);
        $url       = get_permalink($post_id);
        $text      = $caption . "\n\n" . $hashtags . "\n\n" . $url;
        $published = false;

        if (!empty($opts['facebook_token']) && !empty($opts['facebook_page_id'])) {
            $r = wp_remote_post("https://graph.facebook.com/v18.0/{$opts['facebook_page_id']}/feed", [
                'timeout' => 20,
                'body'    => ['message'=>$text,'link'=>$url,'access_token'=>$opts['facebook_token']],
            ]);
            $published = !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
        }

        if ($published) update_post_meta($post_id, '_v5_social_at', current_time('mysql'));
        return $published;
    }

    private static function generate_caption(string $kw, string $title, array $opts): string {
        $prompt = "2-3 frasi social in italiano per '{$opts['business']}' sull'articolo: \"{$title}\"\nKeyword: {$kw}\nTono: professionale ma umano. Solo il testo.";
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 120, 72);
        return trim($result) ?: $title;
    }

    private static function hashtags(string $kw): string {
        $tags = ['#ristrutturazione','#Milano','#casaitalia'];
        foreach (array_slice(explode(' ', mb_strtolower($kw)), 0, 3) as $w) {
            if (mb_strlen($w)>4) $tags[] = '#'.ucfirst($w);
        }
        return implode(' ', array_unique(array_slice($tags, 0, 5)));
    }
}

/**
 * V11 Feature 12: Video AI Pipeline
 * Converts articles to video scripts, Reels, Shorts scripts.
 */
class V5_Video {

    public static function generate_script(int $post_id): array {
        $opts    = V4_Options::get();
        $post    = get_post($post_id);
        $memory  = V5_Memory::get($post_id);
        $kw      = $memory['keyword'] ?? $post->post_title;
        $excerpt = mb_substr(wp_strip_all_tags($post->post_content), 0, 1500);

        $prompt = "Crea script video per questo articolo.\nTitolo: \"{$post->post_title}\"\nKeyword: \"{$kw}\"\n"
                . "Contenuto:\n{$excerpt}\n\n"
                . "Genera:\n1. Script Reels/TikTok (30s, hook + valore + CTA)\n2. Script YouTube Short (60s)\n3. Hook iniziale emotivo\n"
                . 'JSON: {"reels_script":"...","youtube_short":"...","hook":"...","hashtags":"..."}';

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 600, 72);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $data   = json_decode($text, true);

        if ($data) {
            update_post_meta($post_id, '_v5_video_script', $data);
            return $data;
        }
        return [];
    }
}
