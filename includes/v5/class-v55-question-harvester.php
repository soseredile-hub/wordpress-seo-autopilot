<?php
defined('ABSPATH') || exit;

/**
 * V5.5 Feature 2: Question Harvester
 *
 * Collects ALL questions people ask about your services from:
 * - Google Autocomplete (come, quanto, quale, quando, perché...)
 * - People Also Ask simulation via AI
 * - Answer The Public patterns
 * - Italian question templates
 *
 * Each question = potential Featured Snippet = position 0
 */
class V55_QuestionHarvester {

    const QUESTION_PREFIXES_IT = [
        'come'          => 'Procedura',
        'quanto costa'  => 'Prezzo',
        'quanto si'     => 'Prezzo',
        'quale'         => 'Confronto',
        'quando'        => 'Tempistica',
        'perché'        => 'Motivazione',
        'chi fa'        => 'Servizio',
        'dove'          => 'Luogo',
        'è obbligatorio'=> 'Normativa',
        'quali permessi'=> 'Normativa',
        'quanti giorni' => 'Tempistica',
        'si può fare'   => 'Fattibilità',
        'conviene'      => 'Consiglio',
        'differenza tra'=> 'Confronto',
        'meglio'        => 'Confronto',
        'quanto dura'   => 'Tempistica',
        'quanto tempo'  => 'Tempistica',
        'costo medio'   => 'Prezzo',
        'prezzi'        => 'Prezzo',
        'come scegliere'=> 'Guida',
    ];

    /**
     * Harvest ALL questions for active services.
     */
    public static function harvest(bool $force = false): array {
        $cached = get_transient('v55_questions');
        if ($cached !== false && !$force) return $cached;

        $opts     = V4_Options::get();
        $services = class_exists('V54_ServiceFocus')
            ? V54_ServiceFocus::get_active_services()
            : [];
        $geo      = $opts['geo'] ?: 'Milano';
        $all      = [];

        foreach ($services as $svc) {
            $base     = mb_strtolower($svc['name']);
            $svc_qs   = [];

            // 1. Template-based questions
            foreach (self::QUESTION_PREFIXES_IT as $prefix => $category) {
                $variants = [
                    "{$prefix} {$base}",
                    "{$prefix} {$base} {$geo}",
                    "{$prefix} fare {$base} {$geo}",
                ];
                foreach ($variants as $q) {
                    $svc_qs[] = [
                        'question'      => $q,
                        'category'      => $category,
                        'service'       => $svc['name'],
                        'service_id'    => $svc['id'],
                        'source'        => 'template',
                        'snippet_type'  => self::get_snippet_type($category),
                        'covered'       => false,
                    ];
                }
            }

            // 2. Google Autocomplete questions
            $auto_qs = self::from_autocomplete($base, $geo);
            foreach ($auto_qs as $q) {
                $svc_qs[] = [
                    'question'      => $q,
                    'category'      => self::classify_question($q),
                    'service'       => $svc['name'],
                    'service_id'    => $svc['id'],
                    'source'        => 'autocomplete',
                    'snippet_type'  => 'paragraph',
                    'covered'       => false,
                ];
            }

            // 3. AI People Also Ask
            if (!empty($opts['openai_key'])) {
                $ai_qs = self::from_ai_paa($svc['name'], $geo, $opts);
                foreach ($ai_qs as $q) {
                    $svc_qs[] = [
                        'question'      => $q,
                        'category'      => self::classify_question($q),
                        'service'       => $svc['name'],
                        'service_id'    => $svc['id'],
                        'source'        => 'paa',
                        'snippet_type'  => self::get_snippet_type(self::classify_question($q)),
                        'covered'       => false,
                    ];
                }
            }

            // Check which questions are already covered
            foreach ($svc_qs as &$q_item) {
                $q_item['covered'] = V4_Content_Scanner::is_covered($q_item['question']);
            }

            $all = array_merge($all, $svc_qs);
        }

        // Deduplicate
        $seen   = [];
        $unique = [];
        foreach ($all as $q) {
            $key = mb_strtolower(trim($q['question']));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $q;
            }
        }

        // Sort: uncovered first, then by category priority
        usort($unique, function($a, $b) {
            if ($a['covered'] !== $b['covered']) return $a['covered'] <=> $b['covered'];
            $priority = ['Prezzo'=>1,'Confronto'=>2,'Guida'=>3,'Normativa'=>4,'Tempistica'=>5,'Servizio'=>6,'Fattibilità'=>7,'Motivazione'=>8,'Luogo'=>9,'Consiglio'=>10];
            return ($priority[$a['category']]??99) <=> ($priority[$b['category']]??99);
        });

        set_transient('v55_questions', $unique, 6 * HOUR_IN_SECONDS);
        return $unique;
    }

    private static function from_autocomplete(string $base, string $geo): array {
        $results = [];
        $question_words = ['come ', 'quanto ', 'quale ', 'quando ', 'perché ', 'chi '];

        foreach (array_slice($question_words, 0, 4) as $qw) {
            $url = add_query_arg([
                'client' => 'firefox',
                'hl'     => 'it',
                'gl'     => 'it',
                'q'      => $qw . $base . ' ' . $geo,
            ], 'https://suggestqueries.google.com/complete/search');

            $r = wp_remote_get($url, ['timeout'=>6,'user-agent'=>'Mozilla/5.0']);
            if (is_wp_error($r)) continue;

            $body = json_decode(wp_remote_retrieve_body($r), true);
            foreach ($body[1] ?? [] as $item) {
                if (is_string($item) && mb_strlen($item) > 10) {
                    $results[] = $item;
                }
            }
            usleep(100000);
        }
        return array_unique($results);
    }

    private static function from_ai_paa(string $service, string $geo, array $opts): array {
        $cache_key = 'v55_paa_' . md5($service . $geo);
        $cached    = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $prompt = "Genera 10 domande reali che gli italiani cercano su Google riguardo a \"{$service}\" a {$geo}.\n"
                . "Includi: prezzi, permessi, tempi, confronti, consigli.\n"
                . "Formato: solo array JSON di stringhe. Es: [\"Quanto costa...\",\"Come fare...\"]";

        $result = V5_Cost::cached_call(
            [['role'=>'user','content'=>$prompt]],
            $opts['openai_model'] ?: 'gpt-4.1-mini',
            300,
            72
        );

        $text = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $qs   = json_decode($text, true);

        if (!is_array($qs)) return [];
        $clean = array_filter(array_map(fn($q) => is_string($q) ? sanitize_text_field($q) : '', $qs));
        set_transient($cache_key, array_values($clean), 72 * HOUR_IN_SECONDS);
        return array_values($clean);
    }

    private static function classify_question(string $q): string {
        $l = mb_strtolower($q);
        if (preg_match('/quanto costa|prezzi?|costo|preventivo|spende/', $l)) return 'Prezzo';
        if (preg_match('/come fare|come scegliere|guida|passo/', $l))        return 'Guida';
        if (preg_match('/permess|normativa|obbligatori|legge|regolamento/', $l)) return 'Normativa';
        if (preg_match('/quanto dura|quanto tempo|giorn|settiman/', $l))     return 'Tempistica';
        if (preg_match('/differenza|meglio|vs|confronto|oppure/', $l))       return 'Confronto';
        if (preg_match('/chi fa|chi offre|dove trovare|migliore impresa/', $l)) return 'Servizio';
        if (preg_match('/conviene|vale la pena|consiglio/', $l))             return 'Consiglio';
        return 'Generale';
    }

    private static function get_snippet_type(string $category): string {
        $map = [
            'Prezzo'     => 'table',      // Google mostra tabella prezzi
            'Guida'      => 'numbered',   // Google mostra passi numerati
            'Normativa'  => 'paragraph',  // Google mostra paragrafo
            'Tempistica' => 'paragraph',
            'Confronto'  => 'table',
            'Servizio'   => 'paragraph',
        ];
        return $map[$category] ?? 'paragraph';
    }

    public static function get_stats(array $questions): array {
        $total    = count($questions);
        $covered  = count(array_filter($questions, fn($q) => $q['covered']));
        $by_cat   = [];
        foreach ($questions as $q) {
            $cat = $q['category'];
            if (!isset($by_cat[$cat])) $by_cat[$cat] = ['total'=>0,'covered'=>0];
            $by_cat[$cat]['total']++;
            if ($q['covered']) $by_cat[$cat]['covered']++;
        }
        return [
            'total'    => $total,
            'covered'  => $covered,
            'missing'  => $total - $covered,
            'by_cat'   => $by_cat,
        ];
    }
}
