<?php
defined('ABSPATH') || exit;

/**
 * V9_SERPAnalyzer — Reads top 10 Google results before writing.
 *
 * Flow:
 *   1. Google Custom Search API → top 10 URLs
 *   2. wp_remote_get each URL → read HTML
 *   3. Extract: H2s, H3s, word count, questions, prices, LSI
 *   4. Build competitive brief for AI prompt
 *
 * Result: AI writes article that beats all competitors.
 */
class V9_SERPAnalyzer {

    const OPT         = 'soser_v9_cfg';
    const CACHE_PRE   = 'soser_v9_serp_';
    const CACHE_TIME  = 12 * HOUR_IN_SECONDS;
    const MAX_URLS    = 5;   // fetch max 5 competitors (balance speed vs quality)
    const FETCH_TIMEOUT = 15;

    // ── Config ────────────────────────────────────────────────────

    public static function get_config(): array {
        return array_merge([
            'google_api_key' => '',
            'search_cx'      => '',
            'enabled'        => '0',
            'max_urls'       => '5',
            'fetch_timeout'  => '15',
        ], get_option(self::OPT, []));
    }

    public static function save_config(array $data): void {
        update_option(self::OPT, [
            'google_api_key' => sanitize_text_field($data['google_api_key'] ?? ''),
            'search_cx'      => sanitize_text_field($data['search_cx'] ?? ''),
            'enabled'        => !empty($data['enabled']) ? '1' : '0',
            'max_urls'       => min(10, max(1, (int)($data['max_urls'] ?? 5))),
            'fetch_timeout'  => min(30, max(5,  (int)($data['fetch_timeout'] ?? 15))),
        ]);
    }

    public static function is_enabled(): bool {
        $cfg = self::get_config();
        return $cfg['enabled'] === '1'
            && !empty($cfg['google_api_key'])
            && !empty($cfg['search_cx']);
    }

    // ── Main entry: analyze keyword ───────────────────────────────

    /**
     * Full SERP analysis for a keyword.
     * Returns competitive brief array or empty array on failure.
     */
    public static function analyze(string $keyword, string $lang = 'it'): array {
        if (!self::is_enabled()) return [];

        $cache_key = self::CACHE_PRE . md5($keyword);
        $cached    = get_transient($cache_key);
        if ($cached) return $cached;

        // 1. Get top URLs from Google
        $urls = self::google_search($keyword, $lang);
        if (empty($urls)) return [];

        // 2. Fetch and parse each competitor
        $cfg       = self::get_config();
        $max       = min((int)$cfg['max_urls'], count($urls));
        $competitors = [];

        foreach (array_slice($urls, 0, $max) as $i => $url) {
            $parsed = self::fetch_and_parse($url);
            if ($parsed) {
                $competitors[] = $parsed;
            }
        }

        if (empty($competitors)) return [];

        // 3. Build competitive brief
        $brief = self::build_brief($keyword, $competitors);
        set_transient($cache_key, $brief, self::CACHE_TIME);

        return $brief;
    }

    // ── Google Custom Search ──────────────────────────────────────

    private static function google_search(string $keyword, string $lang): array {
        $cfg = self::get_config();
        $gl  = self::lang_to_country($lang);
        $hl  = $lang;

        $url = add_query_arg([
            'key'   => $cfg['google_api_key'],
            'cx'    => $cfg['search_cx'],
            'q'     => $keyword,
            'num'   => 10,
            'gl'    => $gl,
            'hl'    => $hl,
        ], 'https://www.googleapis.com/customsearch/v1');

        $r = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return [];

        $data  = json_decode(wp_remote_retrieve_body($r), true);
        $items = $data['items'] ?? [];

        $urls = [];
        foreach ($items as $item) {
            $link = $item['link'] ?? '';
            // Skip: YouTube, Wikipedia, PDF, social media
            if (self::should_skip_url($link)) continue;
            $urls[] = $link;
        }

        return $urls;
    }

    // ── Fetch & Parse competitor page ─────────────────────────────

    private static function fetch_and_parse(string $url): ?array {
        $cfg = self::get_config();

        $r = wp_remote_get($url, [
            'timeout'    => (int)$cfg['fetch_timeout'],
            'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
            'headers'    => ['Accept-Language' => 'it-IT,it;q=0.9,en;q=0.5'],
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return null;

        $html = wp_remote_retrieve_body($r);
        if (empty($html)) return null;

        return self::parse_html($url, $html);
    }

    private static function parse_html(string $url, string $html): array {
        // Remove scripts, styles, nav, footer, header
        $html = preg_replace('/<(script|style|nav|footer|header|aside)[^>]*>.*?<\/\1>/si', '', $html);

        // Extract title
        preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m);
        $title = strip_tags($m[1] ?? '');

        // Extract H1
        preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m);
        $h1 = trim(strip_tags($m[1] ?? ''));

        // Extract all H2s
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/si', $html, $m);
        $h2s = array_values(array_filter(array_map(fn($h) => trim(strip_tags($h)), $m[1] ?? [])));

        // Extract all H3s
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>/si', $html, $m);
        $h3s = array_values(array_filter(array_map(fn($h) => trim(strip_tags($h)), $m[1] ?? [])));

        // Extract main content text
        // Try article/main first, fallback to body
        if (preg_match('/<(?:article|main)[^>]*>(.*?)<\/(?:article|main)>/si', $html, $m)) {
            $text_html = $m[1];
        } else {
            $text_html = $html;
        }
        $text = preg_replace('/\s+/', ' ', strip_tags($text_html));
        $text = trim($text);

        // Word count
        $word_count = str_word_count($text);

        // Extract questions (sentences with ?, or h2/h3 with ?)
        $questions = [];
        preg_match_all('/[^.!?]*\?/u', implode(' ', array_merge($h2s, $h3s)), $qm);
        foreach ($qm[0] as $q) {
            $q = trim($q);
            if (mb_strlen($q) > 10 && mb_strlen($q) < 150) {
                $questions[] = $q;
            }
        }
        $questions = array_values(array_unique(array_slice($questions, 0, 10)));

        // Extract prices (€NN or NN€ or $NN patterns)
        $prices = [];
        preg_match_all('/(?:€|\\$|£)\s*[\d.,]+(?:\s*-\s*(?:€|\\$|£)?\s*[\d.,]+)?|[\d.,]+\s*(?:€|\\$|£)/u', $text, $pm);
        $prices = array_values(array_unique(array_slice($pm[0], 0, 15)));

        // Extract LSI keywords (frequent meaningful phrases)
        $lsi = self::extract_lsi($text, 20);

        // Extract meta description
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/si', $html, $mm);
        $meta_desc = strip_tags($mm[1] ?? '');

        return [
            'url'        => $url,
            'domain'     => parse_url($url, PHP_URL_HOST),
            'title'      => mb_substr($title, 0, 120),
            'h1'         => mb_substr($h1, 0, 120),
            'h2s'        => array_slice($h2s, 0, 10),
            'h3s'        => array_slice($h3s, 0, 15),
            'word_count' => $word_count,
            'questions'  => $questions,
            'prices'     => $prices,
            'lsi'        => $lsi,
            'meta_desc'  => mb_substr($meta_desc, 0, 200),
        ];
    }

    // ── Build competitive brief ───────────────────────────────────

    private static function build_brief(string $keyword, array $competitors): array {
        if (empty($competitors)) return [];

        // Aggregate data
        $all_h2s       = [];
        $all_h3s       = [];
        $all_questions = [];
        $all_prices    = [];
        $all_lsi       = [];
        $word_counts   = [];

        foreach ($competitors as $c) {
            $all_h2s       = array_merge($all_h2s,       $c['h2s']);
            $all_h3s       = array_merge($all_h3s,       $c['h3s']);
            $all_questions = array_merge($all_questions, $c['questions']);
            $all_prices    = array_merge($all_prices,    $c['prices']);
            $all_lsi       = array_merge($all_lsi,       $c['lsi']);
            if ($c['word_count'] > 100) $word_counts[] = $c['word_count'];
        }

        // Stats
        $avg_words = $word_counts ? (int)(array_sum($word_counts) / count($word_counts)) : 800;
        $max_words = $word_counts ? max($word_counts) : 1000;
        $target_words = (int)($max_words * 1.2); // beat the longest by 20%

        // Deduplicate
        $top_h2s    = array_values(array_unique(array_slice($all_h2s, 0, 12)));
        $top_h3s    = array_values(array_unique(array_slice($all_h3s, 0, 15)));
        $top_q      = array_values(array_unique(array_slice($all_questions, 0, 10)));
        $top_prices = array_values(array_unique(array_slice($all_prices, 0, 10)));
        $top_lsi    = self::top_frequent($all_lsi, 20);

        return [
            'keyword'      => $keyword,
            'competitors'  => count($competitors),
            'avg_words'    => $avg_words,
            'max_words'    => $max_words,
            'target_words' => $target_words,
            'h2s'          => $top_h2s,
            'h3s'          => $top_h3s,
            'questions'    => $top_q,
            'prices'       => $top_prices,
            'lsi'          => $top_lsi,
            'sources'      => array_column($competitors, 'domain'),
        ];
    }

    // ── Convert brief to AI prompt addition ──────────────────────

    public static function to_prompt(array $brief, string $lang = 'it'): string {
        if (empty($brief)) return '';

        $kw      = $brief['keyword'] ?? '';
        $n       = $brief['competitors'] ?? 0;
        $target  = $brief['target_words'] ?? 1200;
        $h2s     = implode("\n  - ", $brief['h2s'] ?? []);
        $qs      = implode("\n  - ", $brief['questions'] ?? []);
        $prices  = implode(', ', $brief['prices'] ?? []);
        $lsi     = implode(', ', $brief['lsi'] ?? []);

        $templates = [
            'it' => "## Analisi SERP per \"{$kw}\" (top {$n} risultati Google)\n\n"
                  . "📊 **Lunghezza target:** scrivi ALMENO {$target} parole (i competitor hanno in media {$brief['avg_words']})\n\n"
                  . ($h2s ? "📋 **Sezioni usate dai competitor (coprile tutte + aggiungine di nuove):**\n  - {$h2s}\n\n" : '')
                  . ($qs  ? "❓ **Domande che i competitor rispondono (includile nelle FAQ):**\n  - {$qs}\n\n" : '')
                  . ($prices ? "💰 **Prezzi menzionati dai competitor:** {$prices}\n\n" : '')
                  . ($lsi ? "🔑 **Keyword LSI da includere nel testo:** {$lsi}\n\n" : '')
                  . "⚠️ **Obiettivo:** scrivi un articolo PIÙ COMPLETO e PIÙ LUNGO di tutti i competitor analizzati.\n",

            'en' => "## SERP Analysis for \"{$kw}\" (top {$n} Google results)\n\n"
                  . "📊 **Target length:** write AT LEAST {$target} words (competitors average {$brief['avg_words']})\n\n"
                  . ($h2s ? "📋 **Sections used by competitors (cover all + add new ones):**\n  - {$h2s}\n\n" : '')
                  . ($qs  ? "❓ **Questions competitors answer (include in FAQ):**\n  - {$qs}\n\n" : '')
                  . ($prices ? "💰 **Prices mentioned by competitors:** {$prices}\n\n" : '')
                  . ($lsi ? "🔑 **LSI keywords to include:** {$lsi}\n\n" : '')
                  . "⚠️ **Goal:** write an article MORE COMPLETE and LONGER than all analyzed competitors.\n",

            'ar' => "## تحليل SERP لـ \"{$kw}\" (أول {$n} نتائج Google)\n\n"
                  . "📊 **الطول المستهدف:** اكتب على الأقل {$target} كلمة (المنافسون في المتوسط {$brief['avg_words']})\n\n"
                  . ($h2s ? "📋 **الأقسام التي يستخدمها المنافسون (غطّها كلها + أضف جديدة):**\n  - {$h2s}\n\n" : '')
                  . ($qs  ? "❓ **أسئلة يجيب عليها المنافسون (أضفها في FAQ):**\n  - {$qs}\n\n" : '')
                  . ($prices ? "💰 **الأسعار المذكورة:** {$prices}\n\n" : '')
                  . ($lsi ? "🔑 **كلمات LSI للتضمين:** {$lsi}\n\n" : '')
                  . "⚠️ **الهدف:** اكتب مقالاً أشمل وأطول من جميع المنافسين.\n",
        ];

        return $templates[$lang] ?? $templates['en'];
    }

    // ── Test connection ───────────────────────────────────────────

    public static function test(string $keyword = 'test'): array {
        $cfg = self::get_config();
        if (empty($cfg['google_api_key']) || empty($cfg['search_cx'])) {
            return ['ok' => false, 'error' => 'API Key o Search Engine ID mancante'];
        }

        $urls = self::google_search($keyword, 'it');
        if (empty($urls)) {
            return ['ok' => false, 'error' => 'Nessun risultato. Controlla API Key e CX.'];
        }

        return ['ok' => true, 'results' => count($urls), 'first_url' => $urls[0]];
    }

    // ── Helpers ───────────────────────────────────────────────────

    private static function should_skip_url(string $url): bool {
        $skip_domains = ['youtube.com','youtu.be','facebook.com','instagram.com',
                         'twitter.com','tiktok.com','linkedin.com','pinterest.com',
                         'amazon.','ebay.','wikipedia.org','maps.google'];
        foreach ($skip_domains as $d) {
            if (str_contains($url, $d)) return true;
        }
        // Skip PDFs
        if (str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.pdf')) return true;
        return false;
    }

    private static function extract_lsi(string $text, int $limit): array {
        // Extract 2-3 word phrases that appear frequently
        $text  = mb_strtolower($text);
        $words = preg_split('/\s+/', $text);
        $phrases = [];

        // Build 2-word phrases
        for ($i = 0; $i < count($words) - 1; $i++) {
            $w1 = preg_replace('/[^a-zà-ÿ]/u', '', $words[$i]);
            $w2 = preg_replace('/[^a-zà-ÿ]/u', '', $words[$i+1] ?? '');
            if (mb_strlen($w1) > 3 && mb_strlen($w2) > 3) {
                $phrase = "$w1 $w2";
                $phrases[$phrase] = ($phrases[$phrase] ?? 0) + 1;
            }
        }

        // Filter: appear 2+ times, remove stop words
        $stop = ['di','la','le','il','lo','un','una','che','per','con','nel','del','dei','degli','delle','sulla','sulle','degli'];
        $filtered = [];
        foreach ($phrases as $phrase => $count) {
            if ($count < 2) continue;
            $parts = explode(' ', $phrase);
            if (in_array($parts[0], $stop) || in_array($parts[1], $stop)) continue;
            $filtered[$phrase] = $count;
        }

        arsort($filtered);
        return array_keys(array_slice($filtered, 0, $limit, true));
    }

    private static function top_frequent(array $items, int $limit): array {
        $counts = array_count_values($items);
        arsort($counts);
        return array_keys(array_slice($counts, 0, $limit, true));
    }

    private static function lang_to_country(string $lang): string {
        return ['it'=>'it','en'=>'us','ar'=>'sa','es'=>'es','fr'=>'fr','de'=>'de'][$lang] ?? 'us';
    }
}
