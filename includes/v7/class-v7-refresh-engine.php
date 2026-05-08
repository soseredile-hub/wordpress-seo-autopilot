<?php
defined('ABSPATH') || exit;

/**
 * V7_RefreshEngine — Intelligent AI Refresh Engine
 *
 * Decision logic:
 *   1. Pull GSC positions for all plugin articles
 *   2. Score each article: position trend + age + CTR
 *   3. Build priority queue
 *   4. For each article: diagnose WHY it dropped
 *   5. Ask AI to refresh SPECIFICALLY what's needed
 *   6. Update post + save revision
 */
class V7_RefreshEngine {

    const CRON     = 'soser_v7_refresh_cron';
    const OPT_LOG  = 'soser_v7_refresh_log';
    const OPT_QUEUE= 'soser_v7_refresh_queue';

    // ── Init ──────────────────────────────────────────────────────

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run_batch']);
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time(), 'daily', self::CRON);
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::CRON);
    }

    // ── Build priority queue ──────────────────────────────────────

    public static function build_queue(): int {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'numberposts'    => 500,
            'meta_key'       => '_soser_focus_keyword',
            'meta_query'     => [
                ['key' => '_elementor_edit_mode', 'compare' => 'NOT EXISTS'],
                ['key' => '_elementor_data',      'compare' => 'NOT EXISTS'],
            ],
        ]);

        $queue    = [];
        $gsc_map  = self::get_gsc_map();
        $today    = time();

        foreach ($posts as $post) {
            $kw       = get_post_meta($post->ID, '_soser_focus_keyword', true);
            $age_days = (int) round(($today - strtotime($post->post_date)) / 86400);
            $gsc      = $gsc_map[strtolower($kw)] ?? null;
            $score    = self::priority_score($post->ID, $age_days, $gsc);

            if ($score < 20) continue; // Not worth refreshing

            $last_refresh = get_post_meta($post->ID, '_v7_refreshed_at', true);
            $days_since   = $last_refresh
                ? (int) round(($today - strtotime($last_refresh)) / 86400)
                : 9999;

            if ($days_since < 30) continue; // Refreshed recently

            $queue[$post->ID] = [
                'post_id'   => $post->ID,
                'keyword'   => $kw,
                'title'     => $post->post_title,
                'age_days'  => $age_days,
                'score'     => $score,
                'position'  => $gsc['position'] ?? 0,
                'clicks'    => $gsc['clicks']   ?? 0,
                'impressions'=> $gsc['impressions'] ?? 0,
                'reason'    => self::diagnose($post->ID, $age_days, $gsc),
                'queued_at' => date('Y-m-d H:i:s'),
                'status'    => 'pending',
            ];
        }

        // Sort by priority score desc
        uasort($queue, fn($a,$b) => $b['score'] - $a['score']);
        update_option(self::OPT_QUEUE, $queue);
        return count($queue);
    }

    // ── Run batch ─────────────────────────────────────────────────

    public static function run_batch(int $limit = 3): array {
        $queue = get_option(self::OPT_QUEUE, []);
        $opts  = V4_Options::get();
        $done  = 0;
        $log   = [];

        foreach ($queue as $post_id => &$job) {
            if ($job['status'] !== 'pending') continue;
            if ($done >= $limit) break;

            $job['status'] = 'running';
            update_option(self::OPT_QUEUE, $queue);

            $result = self::refresh_post($post_id, $job, $opts);

            if (is_wp_error($result)) {
                $job['status']  = 'failed';
                $job['error']   = $result->get_error_message();
            } else {
                $job['status']      = 'done';
                $job['finished_at'] = date('Y-m-d H:i:s');
                update_post_meta($post_id, '_v7_refreshed_at', date('Y-m-d H:i:s'));
                $done++;
            }

            $log[] = [
                'post_id' => $post_id,
                'keyword' => $job['keyword'],
                'status'  => $job['status'],
                'date'    => date('Y-m-d H:i:s'),
                'reason'  => $job['reason'] ?? '',
            ];
        }

        update_option(self::OPT_QUEUE, $queue);
        self::append_log($log);
        V7_Analytics::invalidate();

        return ['refreshed' => $done, 'log' => $log];
    }

    // ── Refresh a single post ─────────────────────────────────────

    private static function refresh_post(int $post_id, array $job, array $opts): mixed {
        $post    = get_post($post_id);
        if (!$post) return new WP_Error('no_post', 'Post not found');

        $kw      = $job['keyword'];
        $reason  = $job['reason'];
        $lang    = class_exists('V6_Profile') ? V6_Profile::language() : 'it';
        $profile = class_exists('V6_Profile') ? V6_Profile::get() : [];

        // Build smart refresh prompt
        $current_html = wp_strip_all_tags($post->post_content);
        $current_text = mb_substr($current_html, 0, 2000);

        $system = class_exists('V6_Language')
            ? V6_Language::article_system($lang, $profile) . " Sei specializzato nel MIGLIORARE articoli esistenti. Rispondi SOLO con JSON."
            : "You are an SEO expert. Improve existing articles. Reply ONLY with JSON.";

        $improvements = self::improvements_for_reason($reason, $lang);
        $year         = date('Y');
        $currency_sym = $profile['currency_symbol'] ?? '€';

        $user = self::refresh_prompt($lang, $kw, $current_text, $improvements, $year, $currency_sym, $profile);

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'           => $opts['openai_model'] ?: 'gpt-4.1-mini',
                'max_tokens'      => 3000,
                'temperature'     => 0.6,
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);

        if (is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if ($code >= 300) return new WP_Error('openai', $body['error']['message'] ?? "HTTP {$code}");

        $text    = $body['choices'][0]['message']['content'] ?? '';
        $text    = preg_replace('/^```json\s*/i', '', trim($text));
        $text    = preg_replace('/```\s*$/i', '', $text);
        $updated = json_decode($text, true);

        if (empty($updated['content_html'])) {
            return new WP_Error('no_content', 'AI non ha restituito contenuto');
        }

        // Save revision BEFORE updating
        wp_save_post_revision($post_id);

        $new_content = wp_kses_post($updated['content_html']);
        $update      = ['ID' => $post_id, 'post_content' => $new_content];

        // Update SEO title / meta if improved
        if (!empty($updated['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title',        sanitize_text_field($updated['seo_title']));
            update_post_meta($post_id, '_rank_math_title',           sanitize_text_field($updated['seo_title']));
            update_post_meta($post_id, '_aioseo_title',              sanitize_text_field($updated['seo_title']));
        }
        if (!empty($updated['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc',     sanitize_text_field($updated['meta_description']));
            update_post_meta($post_id, '_rank_math_description',     sanitize_text_field($updated['meta_description']));
            update_post_meta($post_id, '_aioseo_description',        sanitize_text_field($updated['meta_description']));
        }

        // Re-apply design system
        if (class_exists('V59_ArticleDesign')) {
            $article = ['content' => $new_content, 'faq' => $updated['faq'] ?? []];
            $article = V59_ArticleDesign::render($article, $kw);
            $update['post_content'] = $article['content'];
        }

        wp_update_post($update);
        update_post_meta($post_id, '_v7_refresh_reason', $reason);
        update_post_meta($post_id, '_v7_refresh_count',
            (int) get_post_meta($post_id, '_v7_refresh_count', true) + 1
        );

        return true;
    }

    // ── Diagnosis ─────────────────────────────────────────────────

    public static function diagnose(int $post_id, int $age_days, ?array $gsc): string {
        if (!$gsc) {
            if ($age_days > 180) return 'outdated_content';
            return 'no_gsc_data';
        }

        $position    = $gsc['position'] ?? 99;
        $ctr         = $gsc['ctr'] ?? 0;
        $impressions = $gsc['impressions'] ?? 0;
        $clicks      = $gsc['clicks'] ?? 0;

        // Position dropped
        $history = class_exists('V7_SERPTracker')
            ? V7_SERPTracker::get_history($gsc['keyword'] ?? '', 14)
            : [];

        if (count($history) >= 2) {
            $prev = $history[0]['position'] ?? $position;
            if ($position > $prev + 3) return 'position_drop';
        }

        if ($position > 10 && $impressions > 100) return 'needs_optimization';
        if ($ctr < 0.01 && $impressions > 200)    return 'low_ctr';
        if ($age_days > 365)                        return 'outdated_content';
        if ($age_days > 180 && $position > 5)      return 'needs_update';

        return 'general_improvement';
    }

    private static function improvements_for_reason(string $reason, string $lang): string {
        $map = [
            'position_drop'      => ['it' => 'Posizione calata. Aggiungi più contenuto, aggiorna prezzi, migliora H2.',
                                     'en' => 'Position dropped. Add more content, update prices, improve H2.',
                                     'ar' => 'انخفض الترتيب. أضف محتوى أكثر، حدث الأسعار، حسّن H2.'],
            'low_ctr'            => ['it' => 'CTR basso. Riscrivi title e meta description per essere più convincente.',
                                     'en' => 'Low CTR. Rewrite title and meta description to be more compelling.',
                                     'ar' => 'نسبة النقر منخفضة. أعد كتابة العنوان والوصف.'],
            'outdated_content'   => ['it' => "Contenuto vecchio. Aggiorna anno, prezzi, normative, statistiche a " . date('Y') . ".",
                                     'en' => "Outdated. Update year, prices, regulations, statistics to " . date('Y') . ".",
                                     'ar' => "محتوى قديم. حدّث السنة والأسعار والإحصائيات إلى " . date('Y') . "."],
            'needs_optimization' => ['it' => 'Posizione 11-20. Aggiungi FAQ, migliora H2, aggiungi più parole chiave LSI.',
                                     'en' => 'Position 11-20. Add FAQ, improve H2, add more LSI keywords.',
                                     'ar' => 'الترتيب 11-20. أضف FAQ وحسّن H2.'],
            'needs_update'       => ['it' => "Aggiorna prezzi e date a " . date('Y') . ". Aggiungi nuove sezioni.",
                                     'en' => "Update prices and dates to " . date('Y') . ". Add new sections.",
                                     'ar' => "حدّث الأسعار والتواريخ إلى " . date('Y') . "."],
        ];

        $entry = $map[$reason] ?? ['it' => 'Migliora qualità generale.', 'en' => 'Improve overall quality.', 'ar' => 'حسّن الجودة العامة.'];
        return $entry[$lang] ?? $entry['en'];
    }

    // ── Refresh prompts per language ──────────────────────────────

    private static function refresh_prompt(string $lang, string $kw, string $current, string $improvements, string $year, string $currency, array $profile): string {
        $city = $profile['city'] ?? '';
        $name = $profile['name'] ?? '';

        $templates = [
            'it' => "Migliora questo articolo esistente per la keyword: \"{$kw}\"\n\n"
                  . "Problema da risolvere: {$improvements}\n\n"
                  . "Testo attuale (estratto):\n{$current}\n\n"
                  . "Istruzioni:\n"
                  . "- Mantieni la struttura di base ma MIGLIORA ogni sezione\n"
                  . "- Aggiorna tutti i prezzi con {$currency} e anno {$year}\n"
                  . "- Aggiungi almeno 2 nuovi H2 con contenuto fresco\n"
                  . "- Aggiungi/migliora le FAQ (minimo 5)\n"
                  . "- Menziona {$name} e {$city} naturalmente\n"
                  . "- Mantieni lunghezza simile o superiore all'originale\n\n"
                  . "Rispondi SOLO con JSON:\n"
                  . '{"seo_title":"...","meta_description":"...","content_html":"...HTML completo...","faq":[{"question":"...","answer":"..."}]}',

            'en' => "Improve this existing article for keyword: \"{$kw}\"\n\n"
                  . "Issue to fix: {$improvements}\n\n"
                  . "Current content (excerpt):\n{$current}\n\n"
                  . "Instructions:\n"
                  . "- Keep base structure but IMPROVE every section\n"
                  . "- Update all prices with {$currency} and year {$year}\n"
                  . "- Add at least 2 new H2 sections with fresh content\n"
                  . "- Add/improve FAQ (minimum 5)\n"
                  . "- Mention {$name} and {$city} naturally\n"
                  . "- Keep similar or greater length than original\n\n"
                  . "Reply ONLY with JSON:\n"
                  . '{"seo_title":"...","meta_description":"...","content_html":"...full HTML...","faq":[{"question":"...","answer":"..."}]}',

            'ar' => "حسّن هذا المقال الموجود للكلمة المفتاحية: \"{$kw}\"\n\n"
                  . "المشكلة المطلوب حلها: {$improvements}\n\n"
                  . "المحتوى الحالي (مقتطف):\n{$current}\n\n"
                  . "التعليمات:\n"
                  . "- احتفظ بالهيكل الأساسي لكن حسّن كل قسم\n"
                  . "- حدّث كل الأسعار بـ{$currency} وسنة {$year}\n"
                  . "- أضف عنوانين H2 جديدين على الأقل بمحتوى جديد\n"
                  . "- أضف/حسّن الأسئلة الشائعة (5 على الأقل)\n"
                  . "- اذكر {$name} و{$city} بشكل طبيعي\n\n"
                  . "أجب فقط بـ JSON:\n"
                  . '{"seo_title":"...","meta_description":"...","content_html":"...HTML كامل...","faq":[{"question":"...","answer":"..."}]}',
        ];

        return $templates[$lang] ?? $templates['en'];
    }

    // ── Priority scoring ──────────────────────────────────────────

    private static function priority_score(int $post_id, int $age_days, ?array $gsc): int {
        $score = 0;
        if (!$gsc) {
            if ($age_days > 365) $score += 40;
            elseif ($age_days > 180) $score += 20;
            return $score;
        }

        $pos   = $gsc['position'] ?? 99;
        $impr  = $gsc['impressions'] ?? 0;
        $ctr   = $gsc['ctr'] ?? 0;
        $clicks= $gsc['clicks'] ?? 0;

        // High impressions = high potential
        if ($impr > 1000) $score += 30;
        elseif ($impr > 200) $score += 20;
        elseif ($impr > 50)  $score += 10;

        // Position 4-15 = quick win potential
        if ($pos >= 4 && $pos <= 10)  $score += 35;
        elseif ($pos >= 11 && $pos <= 20) $score += 20;
        elseif ($pos > 20) $score += 5;

        // Low CTR despite impressions
        if ($impr > 100 && $ctr < 0.02) $score += 25;

        // Age bonus
        if ($age_days > 365) $score += 20;
        elseif ($age_days > 180) $score += 10;

        // Was getting clicks but dropped
        if ($clicks > 10 && $pos > 10) $score += 15;

        return $score;
    }

    // ── Queue management ──────────────────────────────────────────

    public static function get_queue(): array {
        return get_option(self::OPT_QUEUE, []);
    }

    public static function get_pending(): array {
        return array_filter(self::get_queue(), fn($j) => $j['status'] === 'pending');
    }

    public static function get_log(int $limit = 50): array {
        return array_slice(get_option(self::OPT_LOG, []), 0, $limit);
    }

    public static function clear_done(): void {
        $queue = array_filter(self::get_queue(), fn($j) => $j['status'] !== 'done');
        update_option(self::OPT_QUEUE, $queue);
    }

    private static function append_log(array $entries): void {
        $log = get_option(self::OPT_LOG, []);
        $log = array_merge($entries, $log);
        update_option(self::OPT_LOG, array_slice($log, 0, 100));
    }

    // ── Stats ─────────────────────────────────────────────────────

    public static function stats(): array {
        $queue = self::get_queue();
        return [
            'pending' => count(array_filter($queue, fn($j) => $j['status'] === 'pending')),
            'done'    => count(array_filter($queue, fn($j) => $j['status'] === 'done')),
            'failed'  => count(array_filter($queue, fn($j) => $j['status'] === 'failed')),
            'total'   => count($queue),
        ];
    }

    // ── GSC helper ────────────────────────────────────────────────

    private static function get_gsc_map(): array {
        if (!class_exists('V4_GSC') || !V4_GSC::is_connected()) return [];
        $site = V4_GSC::get_site_url();
        if (!$site) return [];
        $data = V4_GSC::get_keyword_opportunities($site, 90);
        $map  = [];
        foreach ($data as $row) {
            $map[strtolower($row['keyword'])] = $row;
        }
        return $map;
    }
}
