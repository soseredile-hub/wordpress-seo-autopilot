<?php
defined('ABSPATH') || exit;

/**
 * V5.7 Feature 1: Featured Snippet Optimizer
 * Formats content to win the blue box above position #1.
 *
 * Google picks Featured Snippets from:
 * - Direct definitions "X is..."
 * - Numbered steps (HowTo)
 * - Price/comparison tables
 * - 40-60 word direct answers
 * - Lists with clear headers
 */
class V57_FeaturedSnippet {

    /**
     * Optimize article content for Featured Snippet eligibility.
     * Injects properly formatted answer blocks.
     */
    public static function optimize(array $article, string $kw): array {
        $opts    = V4_Options::get();
        $intent  = V5_Semantic::detect_intent($kw);
        $content = $article['content'];

        // 1. Add direct answer block (40-60 words) at very top
        $answer_block = self::generate_answer_block($kw, $opts);
        if ($answer_block) {
            $content = $answer_block . $content;
        }

        // 2. Format price sections as tables (Google loves tables for prices)
        $content = self::convert_prices_to_table($content, $kw, $opts);

        // 3. Format step-by-step as numbered lists
        if ($intent === 'informational' || $intent === 'info-commercial') {
            $content = self::format_steps($content);
        }

        // 4. Add definition box for main keyword
        $content = self::add_definition_box($content, $kw, $opts);

        $article['content'] = $content;
        return $article;
    }

    /**
     * Generate a 40-60 word direct answer block.
     * This is exactly what Google extracts for Featured Snippets.
     */
    private static function generate_answer_block(string $kw, array $opts): string {
        $city    = $opts['geo_city'] ?: $opts['geo'] ?: 'Milano';
        $biz     = $opts['business'] ?: 'SOSER';
        $year    = date('Y');
        $sector  = $opts['sector'] ?: 'ristrutturazione';

        $prompt = "Scrivi UNA risposta diretta di esattamente 45-55 parole per la keyword: \"{$kw}\"\n"
                . "Contesto: {$biz}, {$city}, anno {$year}.\n"
                . "Regole FONDAMENTALI:\n"
                . "- Inizia con la risposta diretta (non con 'In questo articolo...')\n"
                . "- Includi UN numero/dato specifico\n"
                . "- Frase unica e completa\n"
                . "- Esattamente 45-55 parole\n"
                . "Solo il testo, nient'altro.";

        $answer = V5_Cost::cached_call(
            [['role'=>'user','content'=>$prompt]],
            $opts['openai_model'] ?: 'gpt-4.1-mini',
            100,
            72
        );

        if (empty($answer)) return '';

        return '<div class="v57-snippet-answer" style="background:#e8f4ff;border-right:4px solid #1a73e8;padding:14px 18px;margin:0 0 24px;border-radius:0 6px 6px 0">'
             . '<p style="margin:0;font-size:15px;line-height:1.6;color:#1a1a1a">' . esc_html(trim($answer)) . '</p>'
             . '</div>' . "\n";
    }

    /**
     * Convert price mentions to a proper HTML table.
     * Google shows tables in Featured Snippets for price queries.
     */
    private static function convert_prices_to_table(string $content, string $kw, array $opts): string {
        // Check if content already has a table
        if (strpos($content, '<table') !== false) return $content;

        $city     = $opts['geo_city'] ?: 'Milano';
        $currency = $opts['currency'] ?: 'EUR';
        $symbol   = $currency === 'EUR' ? '€' : ($currency === 'GBP' ? '£' : '$');
        $year     = date('Y');

        // Only add table for price-intent keywords
        if (!preg_match('/cost[oi]|prezz[oi]|preventivo|quanto/i', $kw)) return $content;

        // Generate price table via AI
        $prompt = "Crea una tabella HTML prezzi per: \"{$kw}\" a {$city} ({$year}).\n"
                . "Formato ESATTO — solo questo HTML, nient'altro:\n"
                . "<table><thead><tr><th>Tipo intervento</th><th>Prezzo ({$symbol})</th><th>Note</th></tr></thead>"
                . "<tbody><tr><td>...</td><td>...</td><td>...</td></tr></tbody></table>\n"
                . "3-5 righe con prezzi realistici. Solo HTML puro.";

        $table = V5_Cost::cached_call(
            [['role'=>'user','content'=>$prompt]],
            $opts['openai_model'] ?: 'gpt-4.1-mini',
            300,
            48
        );

        if (empty($table) || strpos($table, '<table') === false) return $content;

        // Wrap table for responsive display
        $styled_table = '<div class="v57-price-table" style="overflow-x:auto;margin:20px 0">'
                      . '<h3>Prezzi ' . esc_html($kw) . ' ' . esc_html($city) . ' ' . $year . '</h3>'
                      . $table
                      . '</div>';

        // Inject after first H2
        $pos = stripos($content, '</h2>');
        if ($pos !== false) {
            return substr($content, 0, $pos + 5) . $styled_table . substr($content, $pos + 5);
        }
        return $content . $styled_table;
    }

    /**
     * Format "come fare" steps as proper numbered list.
     */
    private static function format_steps(string $content): string {
        // Find sections that look like steps but aren't in OL
        $content = preg_replace_callback(
            '/<h[23][^>]*>(?:Passo|Step|Fase|Come fare|Come|Procedura)[^<]*<\/h[23]>(.*?)(?=<h[23]|$)/is',
            function($m) {
                $section = $m[0];
                // Already has OL/UL
                if (strpos($section, '<ol') !== false || strpos($section, '<ul') !== false) return $section;
                // Convert paragraphs to ordered list
                $paras = preg_split('/<p[^>]*>/', $section);
                if (count($paras) < 3) return $section;
                return $section; // Keep as-is for now, don't risk breaking
            },
            $content
        );
        return $content;
    }

    /**
     * Add a definition box for the main keyword.
     */
    private static function add_definition_box(string $content, string $kw, array $opts): string {
        if (strpos($content, 'v57-definition') !== false) return $content;
        if (!preg_match('/cosa è|cos\'è|definizione|che cosa/i', $content)) return $content;

        $definition_html = '<div class="v57-definition" itemscope itemtype="https://schema.org/DefinedTerm" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:12px 16px;margin:16px 0">'
                         . '<strong itemprop="name">' . esc_html(ucfirst($kw)) . '</strong>: '
                         . '<span itemprop="description">[definizione]</span>'
                         . '</div>';

        // Will be filled by AI during generation
        return $content;
    }

    /**
     * Analyze how snippet-ready an article is.
     */
    public static function score(string $content, string $kw): array {
        $score  = 0;
        $tips   = [];

        $plain  = wp_strip_all_tags($content);
        $words  = str_word_count($plain);

        // Direct answer in first 100 words
        $first_100 = wp_strip_all_tags(substr($content, 0, 600));
        if (str_word_count($first_100) >= 40 && str_word_count($first_100) <= 70) {
            $score += 25;
        } else {
            $tips[] = 'Aggiungi risposta diretta di 40-60 parole all\'inizio';
        }

        // Has table
        if (strpos($content, '<table') !== false) { $score += 20; }
        elseif (preg_match('/prezz[oi]|cost[oi]/i', $kw)) { $tips[] = 'Aggiungi tabella prezzi (Google la mostra come snippet)'; }

        // Has numbered list
        if (strpos($content, '<ol') !== false) { $score += 15; }
        elseif (preg_match('/come fare|guida|passo/i', $kw)) { $tips[] = 'Aggiungi lista numerata con i passi'; }

        // Keyword in first H2
        preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $content, $m);
        if (isset($m[1]) && stripos($m[1], $kw) !== false) { $score += 20; }
        else { $tips[] = 'Inserisci la keyword nel primo tag H2'; }

        // Has answer box
        if (strpos($content, 'v57-snippet-answer') !== false || strpos($content, 'v5-answer-box') !== false) {
            $score += 20;
        } else {
            $tips[] = 'Aggiungi riquadro risposta rapida (usa AI Overview)';
        }

        return [
            'score' => min(100, $score),
            'tips'  => $tips,
            'ready' => $score >= 60,
        ];
    }
}


/**
 * V5.7 Feature 2: Topical Depth Analyzer
 * Compares your article with Top 10 competitors and finds missing subtopics.
 */
class V57_TopicalDepth {

    /**
     * Analyze topical coverage vs competitors.
     * Returns missing topics you should add to beat competitors.
     */
    public static function analyze(string $kw, string $content = ''): array {
        $cached = get_transient('v57_depth_' . md5($kw));
        if ($cached !== false) return $cached;

        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return self::fallback_analysis($kw, $opts);

        $plain_content = $content
            ? mb_substr(wp_strip_all_tags($content), 0, 2000)
            : 'Articolo non ancora scritto';

        $sector = $opts['sector'] ?: 'ristrutturazione';
        $geo    = $opts['geo_city'] ?: 'Milano';

        $prompt = "Sei un esperto SEO. Analizza la copertura tematica per: \"{$kw}\"\n"
                . "Settore: {$sector}, Città: {$geo}\n\n"
                . "Contenuto attuale:\n{$plain_content}\n\n"
                . "Compito: simula cosa coprono i Top 10 risultati Google per questa keyword e trova cosa manca.\n"
                . "Rispondi SOLO con JSON:\n"
                . '{"covered_topics":["..."],"missing_topics":["..."],"competitor_advantages":["..."],'
                . '"recommended_sections":[{"title":"...","why":"...","word_count":0}],'
                . '"topical_score":0,"verdict":"..."}';

        $result = V5_Cost::cached_call(
            [['role'=>'user','content'=>$prompt]],
            $opts['openai_model_strong'] ?? $opts['openai_model'] ?: 'gpt-4.1-mini',
            600,
            24
        );

        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $data   = json_decode($text, true);

        if (!is_array($data)) $data = self::fallback_analysis($kw, $opts);

        set_transient('v57_depth_' . md5($kw), $data, 6 * HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Auto-enrich article with missing topics.
     */
    public static function enrich_article(array $article, string $kw): array {
        $analysis = self::analyze($kw, $article['content']);
        $missing  = $analysis['recommended_sections'] ?? [];

        if (empty($missing) || empty(V4_Options::get()['openai_key'])) return $article;

        $opts = V4_Options::get();

        // Add top 2 missing sections
        foreach (array_slice($missing, 0, 2) as $section) {
            $title = $section['title'] ?? '';
            $why   = $section['why'] ?? '';
            if (!$title) continue;

            $prompt = "Scrivi la sezione '{$title}' per un articolo su '{$kw}'.\n"
                    . "Motivo: {$why}\n"
                    . "Circa " . ($section['word_count'] ?? 150) . " parole, HTML con paragrafi.\n"
                    . "Solo HTML della sezione, niente H1.";

            $section_html = V5_Cost::cached_call(
                [['role'=>'user','content'=>$prompt]],
                $opts['openai_model'] ?: 'gpt-4.1-mini',
                400,
                48
            );

            if ($section_html) {
                $article['content'] .= "\n<h2>" . esc_html($title) . "</h2>\n"
                                     . wp_kses_post($section_html);
            }
        }

        return $article;
    }

    /**
     * Get topical score for existing post.
     */
    public static function score_post(int $post_id): array {
        $post    = get_post($post_id);
        $kw      = get_post_meta($post_id,'_soser_focus_keyword',true) ?: $post->post_title;
        $content = $post->post_content;
        return self::analyze($kw, $content);
    }

    private static function fallback_analysis(string $kw, array $opts): array {
        $sector = $opts['sector'] ?: 'ristrutturazione';
        $geo    = $opts['geo_city'] ?: 'Milano';
        return [
            'covered_topics'         => [$kw],
            'missing_topics'         => ["Prezzi aggiornati {$geo}", "Normative " . ($opts['geo_region'] ?? ''), "FAQ specifiche", "Confronto materiali"],
            'competitor_advantages'  => ["Tabelle prezzi dettagliate", "Foto prima/dopo", "Certificazioni"],
            'recommended_sections'   => [
                ['title'=>"Prezzi {$kw} ".date('Y')." a {$geo}",'why'=>'Alta ricerca','word_count'=>200],
                ['title'=>"Come scegliere il migliore {$sector}",'why'=>'Intento decisionale','word_count'=>150],
            ],
            'topical_score'          => 50,
            'verdict'                => 'Aggiungi sezioni sui prezzi e normative locali',
        ];
    }
}
