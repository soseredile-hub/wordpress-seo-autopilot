<?php
defined('ABSPATH') || exit;

/**
 * V5.1 Feature 2: AI Overview Optimization
 *
 * Optimizes content to appear in Google's AI Overviews (SGE).
 * Google AI Overview prefers:
 * - Direct answers in first paragraph
 * - Structured data with clear definitions
 * - Authoritative, concise sentences
 * - Proper use of headers
 * - Specific numbers and dates
 */
class V5_AI_Overview {

    /**
     * Enhance article content for AI Overview eligibility.
     * Called during article generation.
     */
    public static function optimize(array $article, string $kw): array {
        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return $article;

        $prompt = "Ottimizza questo articolo per apparire nel Google AI Overview (SGE).\n"
                . "Keyword: \"{$kw}\"\n"
                . "Titolo: \"{$article['title']}\"\n\n"
                . "Regole Google AI Overview:\n"
                . "1. Aggiungi una risposta diretta e concisa (2-3 frasi) SUBITO dopo il primo H2\n"
                . "2. Usa frasi brevi (max 20 parole) nelle sezioni chiave\n"
                . "3. Aggiungi definizioni chiare: 'X è...' 'Y si intende...'\n"
                . "4. Includi numeri specifici e aggiornati al " . date('Y') . "\n"
                . "5. Struttura: domanda → risposta diretta → approfondimento\n\n"
                . "Restituisci SOLO il content_html migliorato, nient'altro.";

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => ['Authorization'=>'Bearer '.$opts['openai_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode([
                'model'       => $opts['openai_model'] ?: 'gpt-4.1-mini',
                'max_tokens'  => 3000,
                'temperature' => 0.5,
                'messages'    => [['role'=>'user','content'=>$prompt]],
            ]),
        ]);

        if (is_wp_error($r)) return $article;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        if ($body['usage'] ?? false) {
            V5_Cost::track($opts['openai_model'], $body['usage']['prompt_tokens']??0, $body['usage']['completion_tokens']??0);
        }

        // Only update if response is substantial HTML
        if (mb_strlen($text) > 500 && (strpos($text,'<') !== false)) {
            $article['content'] = wp_kses_post($text);
        }

        return $article;
    }

    /**
     * Add "direct answer box" to existing posts.
     * Injects a concise answer right after the first paragraph.
     */
    public static function add_answer_box(int $post_id): bool {
        $opts    = V4_Options::get();
        $post    = get_post($post_id);
        $keyword = get_post_meta($post_id, '_soser_focus_keyword', true) ?: $post->post_title;

        if (strpos($post->post_content, 'v5-answer-box') !== false) return false; // Already added

        $prompt = "Per la keyword \"{$keyword}\", scrivi una risposta diretta di 2-3 frasi.\n"
                . "Deve iniziare direttamente con la risposta (no 'in questo articolo...').\n"
                . "Includi un numero/dato specifico se possibile.\n"
                . "Solo il testo della risposta, nient'altro.";

        $answer = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 100, 72);
        if (empty($answer)) return false;

        $box = '<div class="v5-answer-box" style="background:#f0f6fc;border-right:4px solid #2271b1;padding:14px 18px;margin:20px 0;border-radius:0 6px 6px 0">'
             . '<strong style="font-size:12px;color:#2271b1;text-transform:uppercase;letter-spacing:1px">Risposta rapida</strong>'
             . '<p style="margin:6px 0 0;font-size:15px;line-height:1.6">' . esc_html($answer) . '</p>'
             . '</div>';

        // Insert after first paragraph
        $new_content = preg_replace('/(<\/p>)/', '$1' . $box, $post->post_content, 1);
        if ($new_content && $new_content !== $post->post_content) {
            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
            return true;
        }
        return false;
    }

    /**
     * Bulk add answer boxes to posts without them.
     */
    public static function bulk_add_answer_boxes(int $limit = 5): int {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit * 3,
            'meta_query'  => [['key'=>'_soser_focus_keyword','compare'=>'EXISTS']],
        ]);

        $count = 0;
        foreach ($posts as $post) {
            if ($count >= $limit) break;
            if (strpos($post->post_content, 'v5-answer-box') !== false) continue;
            if (self::add_answer_box($post->ID)) $count++;
        }
        return $count;
    }
}
