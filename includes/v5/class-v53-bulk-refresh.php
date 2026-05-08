<?php
defined('ABSPATH') || exit;

/**
 * V5.3: Bulk Refresh Engine
 *
 * Scans ALL existing articles and prioritizes them for refresh based on:
 * 1. Age (older = higher priority)
 * 2. GSC Impressions (more impressions = more potential)
 * 3. Low CTR (low CTR = title/meta needs improvement)
 * 4. Position 11-20 (almost page 1 = easy win)
 * 5. Outdated year references (2022, 2023 in content)
 * 6. Missing Schema
 * 7. Missing internal links
 * 8. Short content (< 800 words)
 *
 * Then refreshes them one by one via async queue.
 */
class V53_BulkRefresh {

    const TABLE    = 'soser_v53_refresh';
    const CRON     = 'soser_v53_refresh_cron';
    const BATCH    = 2; // articles per cron run

    // ── Setup ─────────────────────────────────────────────────────

    public static function create_table(): void {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE IF NOT EXISTS {$t} (
            id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            post_id         BIGINT UNSIGNED  NOT NULL,
            priority_score  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status          VARCHAR(20)      NOT NULL DEFAULT 'pending',
            reasons         TEXT             NULL,
            age_days        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            word_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            gsc_impressions INT UNSIGNED     NOT NULL DEFAULT 0,
            gsc_ctr         FLOAT            NOT NULL DEFAULT 0,
            gsc_position    FLOAT            NOT NULL DEFAULT 0,
            has_schema      TINYINT(1)       NOT NULL DEFAULT 0,
            has_int_links   TINYINT(1)       NOT NULL DEFAULT 0,
            year_outdated   TINYINT(1)       NOT NULL DEFAULT 0,
            refreshed_at    DATETIME         NULL,
            created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY status_priority (status, priority_score),
            KEY priority_score (priority_score)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'process_batch']);
    }

    public static function schedule_cron(): void {
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 60, 'twicedaily', self::CRON);
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::CRON);
    }

    // ── Scan & Score ─────────────────────────────────────────────

    /**
     * Scan ALL published posts and score them for refresh priority.
     * Returns total articles scanned.
     */
    public static function scan_all(): int {
        global $wpdb;

        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => ['publish'],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]);

        $current_year = (int) date('Y');
        $t            = $wpdb->prefix . self::TABLE;
        $count        = 0;

        foreach ($posts as $post) {
            $score   = 0;
            $reasons = [];

            // ── Score 1: Age ──────────────────────────────────────
            $age_days = (int)((time() - strtotime($post->post_modified)) / DAY_IN_SECONDS);
            if ($age_days > 365)      { $score += 30; $reasons[] = "Vecchio di {$age_days} giorni"; }
            elseif ($age_days > 180)  { $score += 20; $reasons[] = "Vecchio di {$age_days} giorni"; }
            elseif ($age_days > 90)   { $score += 10; $reasons[] = "Vecchio di {$age_days} giorni"; }

            // ── Score 2: Word count ───────────────────────────────
            $words = str_word_count(wp_strip_all_tags($post->post_content));
            if ($words < 500)       { $score += 25; $reasons[] = "Contenuto corto ({$words} parole)"; }
            elseif ($words < 800)   { $score += 15; $reasons[] = "Contenuto corto ({$words} parole)"; }

            // ── Score 3: Outdated year references ─────────────────
            $old_years   = range(2019, $current_year - 1);
            $year_found  = false;
            foreach ($old_years as $y) {
                if (strpos($post->post_title . $post->post_content, (string)$y) !== false) {
                    $score += 20;
                    $reasons[] = "Riferimento anno vecchio ({$y})";
                    $year_found = true;
                    break;
                }
            }

            // ── Score 4: Missing Schema ───────────────────────────
            $has_schema = (bool) get_post_meta($post->ID, '_soser_faq_schema', true);
            if (!$has_schema) {
                $score += 15;
                $reasons[] = 'Schema mancante';
            }

            // ── Score 5: Missing internal links ───────────────────
            $link_count    = substr_count($post->post_content, '<a href=');
            $int_links     = 0;
            $home          = home_url();
            preg_match_all('/href=["\'](' . preg_quote($home, '/') . '[^"\']*)["\']/', $post->post_content, $m);
            $int_links     = count($m[1]);
            $has_int_links = $int_links >= 2;
            if (!$has_int_links) {
                $score += 10;
                $reasons[] = "Pochi link interni ({$int_links})";
            }

            // ── Score 6: GSC Data ─────────────────────────────────
            $gsc_impr = 0; $gsc_ctr = 0; $gsc_pos = 0;
            if (class_exists('V5_Memory')) {
                $mem = V5_Memory::get($post->ID);
                if ($mem) {
                    $gsc_impr = (int)   $mem['gsc_impr'];
                    $gsc_ctr  = (float) $mem['gsc_ctr'];
                    $gsc_pos  = (float) $mem['gsc_position'];

                    // High impressions, low CTR = easy win
                    if ($gsc_impr > 200 && $gsc_ctr < 0.02) {
                        $score += 25;
                        $reasons[] = "CTR basso con " . number_format($gsc_impr) . " impressioni";
                    }
                    // Position 11-20 = almost page 1
                    if ($gsc_pos > 10 && $gsc_pos <= 20 && $gsc_impr > 50) {
                        $score += 20;
                        $reasons[] = "Posizione {$gsc_pos} — vicino a pagina 1";
                    }
                    // Position 4-10 = optimize for #1
                    if ($gsc_pos >= 4 && $gsc_pos <= 10 && $gsc_impr > 100) {
                        $score += 15;
                        $reasons[] = "Posizione {$gsc_pos} — ottimizzabile per top 3";
                    }
                }
            }

            // ── Score 7: Missing focus keyword ────────────────────
            $kw = get_post_meta($post->ID,'_soser_focus_keyword',true)
               ?: get_post_meta($post->ID,'_yoast_wpseo_focuskw',true);
            if (!$kw) {
                $score += 10;
                $reasons[] = 'Focus keyword mancante';
            }

            // Upsert into table
            $wpdb->replace($t, [
                'post_id'         => $post->ID,
                'priority_score'  => min(150, $score),
                'status'          => 'pending',
                'reasons'         => implode(' · ', $reasons),
                'age_days'        => $age_days,
                'word_count'      => $words,
                'gsc_impressions' => $gsc_impr,
                'gsc_ctr'         => $gsc_ctr,
                'gsc_position'    => $gsc_pos,
                'has_schema'      => $has_schema ? 1 : 0,
                'has_int_links'   => $has_int_links ? 1 : 0,
                'year_outdated'   => $year_found ? 1 : 0,
            ]);
            $count++;
        }

        delete_transient('v53_refresh_stats');
        return $count;
    }

    // ── Process ───────────────────────────────────────────────────

    /**
     * Process next batch of articles.
     * Called by cron or manually.
     */
    public static function process_batch(int $limit = 0): array {
        global $wpdb;

        if (!V5_Cost::can_spend()) {
            return ['status'=>'budget_exceeded','processed'=>0,'message'=>'Budget giornaliero raggiunto. Riprova domani.'];
        }

        $limit = $limit ?: self::BATCH;
        $t     = $wpdb->prefix . self::TABLE;

        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE status='pending' ORDER BY priority_score DESC LIMIT %d",
            $limit
        ), ARRAY_A);

        if (empty($jobs)) {
            return ['status'=>'complete','processed'=>0,'message'=>'Tutti gli articoli sono aggiornati! ✅'];
        }

        $processed = 0;
        foreach ($jobs as $job) {
            $pid = (int)$job['post_id'];
            $post = get_post($pid);
            if (!$post) {
                $wpdb->update($t,['status'=>'skipped'],['post_id'=>$pid]);
                continue;
            }

            // Mark as running
            $wpdb->update($t,['status'=>'running'],['post_id'=>$pid]);

            $ok = self::refresh_single($pid, $job);

            $wpdb->update($t, [
                'status'       => $ok ? 'done' : 'failed',
                'refreshed_at' => current_time('mysql'),
            ], ['post_id' => $pid]);

            $processed++;
        }

        delete_transient('v53_refresh_stats');
        return ['status'=>'ok','processed'=>$processed,'message'=>"Aggiornati {$processed} articoli."];
    }

    /**
     * Refresh a single article intelligently.
     * Applies only what's needed based on reasons.
     */
    private static function refresh_single(int $post_id, array $job): bool {
        $opts    = V4_Options::get();
        $post    = get_post($post_id);
        $kw      = get_post_meta($post_id,'_soser_focus_keyword',true)
                ?: get_post_meta($post_id,'_yoast_wpseo_focuskw',true)
                ?: $post->post_title;
        $reasons = $job['reasons'] ?? '';
        $updated = false;
        $year    = date('Y');

        // ── Fix 1: Add Schema if missing ─────────────────────────
        if (!$job['has_schema'] && class_exists('V5_Rich_Schema')) {
            V5_Rich_Schema::generate_for_post($post_id, ['title'=>$post->post_title,'content'=>$post->post_content,'meta_description'=>get_post_meta($post_id,'_yoast_wpseo_metadesc',true)], $kw);
            $updated = true;
        }

        // ── Fix 2: Add internal links if missing ──────────────────
        if (!$job['has_int_links'] && class_exists('V52_AutoLinker')) {
            V52_AutoLinker::process_batch(1);
            $updated = true;
        }

        // ── Fix 3: Add Answer Box for AI Overview ─────────────────
        if (class_exists('V5_AI_Overview')) {
            V5_AI_Overview::add_answer_box($post_id);
            $updated = true;
        }

        // ── Fix 4: Rewrite with AI if needed ──────────────────────
        $needs_ai_rewrite = $job['year_outdated']
            || (int)$job['word_count'] < 500
            || (float)$job['gsc_ctr'] < 0.02 && (int)$job['gsc_impressions'] > 100
            || (float)$job['gsc_position'] > 10;

        if ($needs_ai_rewrite && !empty($opts['openai_key']) && V5_Cost::can_spend()) {
            $ok = self::ai_rewrite($post_id, $kw, $job, $opts);
            if ($ok) $updated = true;
        } elseif ($updated) {
            // At minimum update modified date
            wp_update_post(['ID'=>$post_id,'post_modified'=>current_time('mysql')]);
        }

        // ── Fix 5: Optimize CTR (title + meta) ────────────────────
        if ((float)$job['gsc_ctr'] < 0.03 && (int)$job['gsc_impressions'] > 50 && class_exists('V5_CTR')) {
            $new_meta = V5_CTR::optimize_meta($post_id);
            if ($new_meta) {
                update_post_meta($post_id,'_yoast_wpseo_metadesc',$new_meta);
                update_post_meta($post_id,'rank_math_description',$new_meta);
                $updated = true;
            }
        }

        // Update memory
        if ($updated && class_exists('V5_Memory')) {
            V5_Memory::remember($post_id, $kw, [
                'word_count' => str_word_count(wp_strip_all_tags(get_post_field('post_content',$post_id))),
            ]);
        }

        return $updated;
    }

    /**
     * Full AI rewrite of an old article.
     */
    private static function ai_rewrite(int $post_id, string $kw, array $job, array $opts): bool {
        $post    = get_post($post_id);
        $year    = date('Y');
        $geo     = $opts['geo'] ?: 'Milano';
        $excerpt = mb_substr(wp_strip_all_tags($post->post_content), 0, 2000);

        $problems = [];
        if ($job['year_outdated'])                               $problems[] = "contiene anni vecchi — aggiorna al {$year}";
        if ((int)$job['word_count'] < 500)                       $problems[] = "troppo corto — espandi a 1000+ parole";
        if ((float)$job['gsc_ctr'] < 0.02)                      $problems[] = "CTR basso — migliora hook e struttura";
        if ((float)$job['gsc_position'] > 10)                   $problems[] = "posizione " . round($job['gsc_position'],1) . " — ottimizza per top 5";

        if (empty($problems)) return false;

        $prompt = "Riscrivi e migliora questo articolo SEO. Anno: {$year}. Geo: {$geo}.\n"
                . "Focus keyword: \"{$kw}\"\n"
                . "Problemi da correggere: " . implode(', ', $problems) . "\n\n"
                . "Articolo attuale:\n{$excerpt}\n\n"
                . "Requisiti:\n"
                . "- Riscrivi completamente con HTML semantico\n"
                . "- Includi prezzi aggiornati al {$year} per {$geo}\n"
                . "- Almeno 3 H2, paragrafi brevi\n"
                . "- Focus keyword nel primo paragrafo\n"
                . "- Tono professionale italiano\n\n"
                . 'JSON: {"new_title":"...","new_content_html":"...(HTML completo)","new_meta":"...(130-150 chars)","changes":"..."}';

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'           => $opts['openai_model'] ?: 'gpt-4.1-mini',
                'max_tokens'      => 3500,
                'temperature'     => 0.6,
                'messages'        => [['role'=>'user','content'=>$prompt]],
                'response_format' => ['type'=>'json_object'],
            ]),
        ]);

        if (is_wp_error($r)) return false;

        $body = json_decode(wp_remote_retrieve_body($r), true);
        V5_Cost::track(
            $opts['openai_model'] ?: 'gpt-4.1-mini',
            $body['usage']['prompt_tokens']    ?? 0,
            $body['usage']['completion_tokens'] ?? 0
        );

        $text = $body['choices'][0]['message']['content'] ?? '';
        $data = json_decode($text, true);
        if (!$data || empty($data['new_content_html'])) return false;

        // Update post
        wp_update_post([
            'ID'           => $post_id,
            'post_title'   => sanitize_text_field($data['new_title']    ?? $post->post_title),
            'post_content' => wp_kses_post($data['new_content_html']),
            'post_modified' => current_time('mysql'),
        ]);

        // Update SEO meta
        if (!empty($data['new_meta'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc',   $data['new_meta']);
            update_post_meta($post_id, 'rank_math_description',    $data['new_meta']);
        }

        // Log change
        update_post_meta($post_id, '_v53_last_refresh', [
            'date'    => current_time('mysql'),
            'changes' => $data['changes'] ?? '',
            'version' => 'V5.3',
        ]);

        return true;
    }

    // ── Stats & Queue ─────────────────────────────────────────────

    public static function get_queue(int $limit = 100): array {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, p.post_title FROM {$t} r
                 LEFT JOIN {$wpdb->posts} p ON p.ID = r.post_id
                 ORDER BY r.priority_score DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function stats(): array {
        $cached = get_transient('v53_refresh_stats');
        if ($cached) return $cached;

        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) total,
                SUM(status='pending') pending,
                SUM(status='done')    done,
                SUM(status='running') running,
                SUM(status='failed')  failed,
                AVG(priority_score)   avg_score,
                MAX(priority_score)   max_score
             FROM {$t}",
            ARRAY_A
        ) ?: ['total'=>0,'pending'=>0,'done'=>0,'running'=>0,'failed'=>0,'avg_score'=>0,'max_score'=>0];

        set_transient('v53_refresh_stats', $row, 10 * MINUTE_IN_SECONDS);
        return $row;
    }

    public static function reset_done(): void {
        global $wpdb;
        $wpdb->query(
            "UPDATE " . $wpdb->prefix . self::TABLE . " SET status='pending' WHERE status='done'"
        );
        delete_transient('v53_refresh_stats');
    }

    public static function clear_all(): void {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . self::TABLE);
        delete_transient('v53_refresh_stats');
    }
}
