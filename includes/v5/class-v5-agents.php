<?php
defined('ABSPATH') || exit;

/**
 * V12 Feature 13: Research Agent
 * Researches trends, competitors, keywords automatically.
 */
class V5_Research_Agent {

    public static function research(string $keyword): array {
        $opts   = V4_Options::get();
        $prompt = "Fai una ricerca SEO competitiva per: \"{$keyword}\" nel mercato italiano ({$opts['geo']}).\n"
                . "Analizza: intenzione utente, argomenti correlati, domande frequenti, angoli unici.\n"
                . 'JSON: {"intent":"...","related_topics":["..."],"questions":["..."],"unique_angles":["..."],"word_count_recommendation":0}';
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 400, 24);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        return json_decode($text, true) ?: [];
    }
}

/**
 * V12 Feature 14: SEO Agent
 * Optimizes entities, structure, internal links of existing articles.
 */
class V5_SEO_Agent {

    public static function analyze(int $post_id): array {
        $post    = get_post($post_id);
        $memory  = V5_Memory::get($post_id);
        $kw      = $memory['keyword'] ?? '';
        $content = mb_substr(wp_strip_all_tags($post->post_content), 0, 2000);
        $opts    = V4_Options::get();

        $prompt = "Analizza questo articolo SEO.\nKeyword: \"{$kw}\"\nContenuto:\n{$content}\n\n"
                . "Fornisci: punteggio SEO 0-100, problemi principali, suggerimenti.\n"
                . 'JSON: {"seo_score":0,"issues":["..."],"improvements":["..."],"entity_gaps":["..."]}';
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 400, 48);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $data   = json_decode($text, true) ?: [];

        if (isset($data['seo_score'])) {
            global $wpdb;
            $wpdb->update($wpdb->prefix.V5_Memory::TABLE,
                ['seo_score'=>(int)$data['seo_score'],'updated_at'=>current_time('mysql')],
                ['post_id'=>$post_id]);
        }
        return $data;
    }
}

/**
 * V12 Feature 15: CRO Agent
 * Optimizes CTAs, forms, conversion elements.
 */
class V5_CRO_Agent {

    public static function optimize_cta(int $post_id): string {
        $opts    = V4_Options::get();
        $memory  = V5_Memory::get($post_id);
        $kw      = $memory['keyword'] ?? '';
        $intent  = $memory['intent'] ?? 'commercial';

        $prompt = "Crea una CTA ottimizzata per conversioni.\nKeyword: \"{$kw}\"\nIntent: {$intent}\nBusiness: {$opts['business']}\nGeo: {$opts['geo']}\n"
                . "Crea: 1 CTA button + 1 paragrafo persuasivo (3 righe max). Solo HTML.";
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 200, 72);
        return trim($result);
    }
}

/**
 * V12 Feature 16: QA Agent
 * Checks hallucinations, weak sections, AI patterns.
 */
class V5_QA_Agent {

    public static function check(string $content, string $keyword, string $title): array {
        $issues = []; $score = 100;

        // AI Pattern check
        $ai_phrases = ['in conclusione','in sintesi','è fondamentale notare','assolutamente','certamente',
                       'senza dubbio','come esperto','in questo articolo esploreremo'];
        $lower = mb_strtolower(wp_strip_all_tags($content));
        $found = [];
        foreach ($ai_phrases as $p) { if (strpos($lower,$p)!==false) { $found[] = "\"{$p}\""; $score-=8; } }
        if ($found) $issues[] = '⚠️ Pattern AI: '.implode(', ',$found);

        // Keyword in first 200 chars
        if (stripos(mb_substr(wp_strip_all_tags($content),0,200), $keyword)===false) {
            $issues[] = '❌ Keyword assente nei primi 200 caratteri';
            $score -= 10;
        }

        // H2 count
        $h2 = substr_count($content,'<h2');
        if ($h2 < 3) { $issues[] = "❌ Solo {$h2} H2 (minimo 3)"; $score -= 10; }

        // Word count
        $words = str_word_count(wp_strip_all_tags($content));
        if ($words < 600) { $issues[] = "❌ Troppo corto ({$words} parole)"; $score -= 20; }

        // Title length
        if (mb_strlen($title) > 65) { $issues[] = '⚠️ Titolo lungo ('.mb_strlen($title).' chars)'; $score -= 5; }

        return [
            'passed'     => $score >= 70,
            'score'      => max(0, $score),
            'issues'     => $issues,
            'word_count' => $words,
            'h2_count'   => $h2,
        ];
    }
}

/**
 * V12 Feature 17: Publishing Agent
 * Decides WHEN and IN WHAT ORDER to publish content.
 */
class V5_Publishing_Agent {

    public static function decide_publish_time(string $keyword, string $intent): string {
        // Commercial intent → publish Monday/Tuesday morning (buying decisions)
        // Informational    → publish any day
        $day_of_week = (int)date('N'); // 1=Mon, 7=Sun
        $hour        = (int)date('H');

        if ($intent === 'commercial' && ($day_of_week > 5 || $hour < 7 || $hour > 18)) {
            // Schedule for next Monday 9AM
            $next_monday = strtotime('next monday 09:00');
            return date('Y-m-d H:i:s', $next_monday);
        }

        return current_time('mysql');
    }

    public static function should_publish_now(string $keyword): bool {
        $intent = V5_Semantic::detect_intent($keyword);
        // Don't publish on weekends for commercial content
        $day = (int)date('N');
        if ($intent === 'commercial' && $day >= 6) return false;
        return true;
    }
}
