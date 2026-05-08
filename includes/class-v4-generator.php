<?php
defined('ABSPATH') || exit;

class V4_Generator {

    /**
     * FIX #1: Every step sets status='pending' for next step (not 'running').
     * FIX #2: When images=0, write_article sets status='done' immediately.
     * FIX #4: keyword passed explicitly to insert_post().
     */
    public static function run_step(object $job) {
        $payload = $job->payload ? (json_decode($job->payload, true) ?: []) : [];
        $step    = $job->step ?: 'init';
        $opts    = V4_Options::get();

        try {

            // STEP 1: init
            if ($step === 'init') {
                $kw = trim($job->keyword);
                if ($kw === '') {
                    $intel = new V4_Keyword_Intel();
                    $best  = $intel->best_keyword();
                    if (!$best) return new WP_Error('no_kw', 'Nessuna keyword trovata. Aggiungi seed keywords o abbassa il threshold.');
                    $kw = $best['keyword'];
                    $payload['score_data'] = $best;
                }
                if (V4_Content_Scanner::is_covered($kw)) {
                    return new WP_Error('cannibal', "Cannibalizzazione: \"{$kw}\" gia coperta.");
                }
                $payload['keyword'] = $kw;
                V4_Queue::update($job->id, [
                    'keyword' => $kw,
                    'status'  => 'pending',
                    'step'    => 'write_article',
                    'payload' => wp_json_encode($payload),
                    'message' => "Keyword: {$kw}",
                ]);
                return true;
            }

            // STEP 2: write_article
            if ($step === 'write_article') {
                if (empty($opts['openai_key'])) return new WP_Error('no_key', 'OpenAI API Key mancante.');
                $kw      = $payload['keyword'] ?? $job->keyword;
                $article = self::openai_write($opts, $kw, $payload['score_data'] ?? []);
                if (is_wp_error($article)) return $article;
                if ($opts['enable_seo_fixer'] === '1') $article = self::seo_fix($article, $kw);
                if ($opts['enable_humanizer'] === '1') $article['content'] = self::humanize($article['content']);
                // V5.1: AI Overview optimization
                if (class_exists('V5_AI_Overview')) $article = V5_AI_Overview::optimize($article, $kw);
                // V5.1: E-E-A-T signals
                if (class_exists('V5_EEAT')) $article = V5_EEAT::enrich_article($article, $kw);
                // V5.1: Local SEO
                if (class_exists('V5_LocalSEO')) $article = V5_LocalSEO::enhance($article, $kw);
                if ($opts['enable_int_links'] === '1') $article['content'] = self::inject_internal_links($article['content'], $kw);
                // V5.9: Apply full Article Design System (UX blocks included)
                if (class_exists('V59_ArticleDesign')) {
                    $article = V59_ArticleDesign::render($article, $kw);
                }
                // Schema saved to postmeta to avoid wp_kses_post stripping <script> tags
                if ($opts['enable_schema'] === '1') $article['faq_schema'] = self::faq_schema($article['faq'] ?? []);
                $article['content'] .= self::seo_score_comment($article, $kw);
                $pid = self::insert_post($article, $opts, $kw); // FIX #4
                if (is_wp_error($pid)) return $pid;
                $payload['article'] = $article;
                $payload['post_id'] = $pid;
                $payload['img_idx'] = 0;
                $use_images  = $opts['generate_images'] === '1';
                // FIX #2: status=done when no images, pending when images needed
                V4_Queue::update($job->id, [
                    'post_id' => $pid,
                    'status'  => $use_images ? 'pending' : 'done',
                    'step'    => $use_images ? 'generate_image' : 'done',
                    'payload' => wp_json_encode($payload),
                    'message' => "Articolo #" . $pid . ($use_images ? '' : ' - Completato'),
                ]);
                if (!$use_images) delete_transient('soser_v4_content_map');
                // V5.1: Remember in memory engine
                if (class_exists('V5_Memory')) V5_Memory::remember($pid, $kw, ['seo_score' => 70]);
                return true;
            }

            // STEP 3: generate_image
            if ($step === 'generate_image') {
                $kw    = $payload['keyword'] ?? $job->keyword;
                $pid   = (int)($payload['post_id'] ?? 0);
                $idx   = (int)($payload['img_idx'] ?? 0);
                $total = max(0, min(3, (int)$opts['inline_images'])) + ($opts['featured_image'] === '1' ? 1 : 0);
                if ($idx >= $total || $total === 0) {
                    V4_Queue::update($job->id, ['status' => 'done', 'step' => 'done', 'message' => 'Completato']);
                    delete_transient('soser_v4_content_map');
                    return true;
                }
                $prompts = is_array(($payload['article']['image_prompts'] ?? null)) ? $payload['article']['image_prompts'] : [];
                // V8: Smart prompt - no hardcoded business names
                $city    = class_exists('V6_Profile') ? V6_Profile::city() : '';
                $ind     = class_exists('V6_Profile') ? V6_Profile::industry() : '';
                $default = trim("{$kw} {$ind} {$city}");
                $raw_prompt = $prompts[$idx] ?? $default;
                $prompt  = $raw_prompt . ', ' . ($opts['image_style'] ?: 'professional photography');
                // V10: Try user's own photos first (AI Vision matched)
                $img_id = class_exists('V10_AIVision')
                    ? V10_AIVision::find_for_keyword($kw)
                    : null;

                // V8: Fallback to external image engines
                if (!$img_id) $img_id = class_exists('V8_ImageEngine')
                    ? V8_ImageEngine::get_image($prompt, $kw, $opts)
                    : V4_Image::generate($prompt, $kw, $opts);
                if ($img_id && $pid > 0) {
                    $has_featured = $opts['featured_image'] === '1';
                    if ($idx === 0 && $has_featured) {
                        set_post_thumbnail($pid, $img_id);
                    } else {
                        $n    = $has_featured ? $idx : $idx + 1;
                        $html = wp_get_attachment_image($img_id, 'large', false, ['loading'=>'lazy','alt'=>esc_attr($kw.' - '.$opts['business'])]);
                        if ($html && ($post = get_post($pid))) {
                            $block = '<figure class="wp-block-image size-large">'.$html.'<figcaption>'.esc_html($kw).'</figcaption></figure>';
                            wp_update_post(['ID'=>$pid,'post_content'=>self::insert_after_nth_h2($post->post_content,$block,max(1,$n))]);
                        }
                    }
                }
                $payload['img_idx'] = $idx + 1;
                $done = ($idx + 1) >= $total;
                // FIX #1: status=pending for next image, done when finished
                V4_Queue::update($job->id, [
                    'status'  => $done ? 'done' : 'pending',
                    'step'    => $done ? 'done' : 'generate_image',
                    'payload' => wp_json_encode($payload),
                    'message' => "Immagine ".($idx+1)."/{$total}".($done?' - Completato':''),
                ]);
                if ($done) delete_transient('soser_v4_content_map');
                return true;
            }

            // STEP 4: done
            if ($step === 'done') {
                V4_Queue::update($job->id, ['status' => 'done', 'message' => 'Completato']);
                delete_transient('soser_v4_content_map');
                return true;
            }

        } catch (Throwable $e) {
            return new WP_Error('exception', $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
        }
        return new WP_Error('bad_step', "Step sconosciuto: {$step}");
    }

    private static function openai_write(array $opts, string $kw, array $score_data = []) {
        // V6: Use Business Profile + Language Engine for universal prompts
        $profile  = class_exists('V6_Profile')   ? V6_Profile::get()   : [];
        $lang     = class_exists('V6_Profile')    ? V6_Profile::language() : 'it';
        $biz_context = V4_Business_Context::to_prompt_string();

        $system = class_exists('V6_Language')
            ? V6_Language::article_system($lang, $profile) . " Rispondi SOLO con JSON valido, nessun altro testo."
            : "Sei un esperto SEO content writer. Rispondi SOLO con JSON valido, nessun altro testo.";

        // V9: SERP Analysis — read competitors before writing
        $serp_brief  = class_exists('V9_SERPAnalyzer') ? V9_SERPAnalyzer::analyze($kw, $lang) : [];
        $serp_prompt = class_exists('V9_SERPAnalyzer') ? V9_SERPAnalyzer::to_prompt($serp_brief, $lang) : '';

        // Set target word count from SERP data if available
        $target_words = !empty($serp_brief['target_words'])
            ? $serp_brief['target_words']
            : "{$opts['min_words']}-{$opts['max_words']}";

        $user = class_exists('V6_Language')
            ? V6_Language::article_user($lang, $kw, $profile)
              . "\n\nContesto business:\n" . $biz_context
              . "\nLunghezza target: " . $target_words . " parole"
              . "\nCTA da usare: " . V6_Profile::cta_quote()
              . (!empty($serp_prompt) ? "\n\n" . $serp_prompt : '')
            : "Scrivi articolo SEO per {$opts['business']}\n"
              . "Keyword: \"{$kw}\"\nGeo: {$opts['geo']}\n"
              . "Lunghezza: {$opts['min_words']}-{$opts['max_words']} parole\n\n"
              . $biz_context . "\n\n"
              . "CTA finale: \"{$opts['cta']}\""
              . '\nJSON: {"title":"...","seo_title":"...","meta_description":"...","slug":"...","excerpt":"...","content_html":"...","tags":[],"faq":[],"image_prompts":[]}';
        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => ['Authorization'=>'Bearer '.$opts['openai_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['model'=>$opts['openai_model']?:'gpt-4.1-mini','max_tokens'=>4096,'temperature'=>0.7,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$user]],'response_format'=>['type'=>'json_object']]),
        ]);
        if (is_wp_error($r)) return $r;
        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if ($code >= 300) return new WP_Error('openai', $body['error']['message'] ?? "HTTP {$code}");
        $text    = $body['choices'][0]['message']['content'] ?? '';
        // Clean AI response artifacts
        $text = preg_replace('/^```json\s*/i', '', trim($text));
        $text = preg_replace('/```\s*$/i', '', $text);
        // Remove literal "← HTML" or "←'" text artifacts from AI
        $text = str_replace(['"← HTML"', '"←\'', '"← \'', '← HTML', "←'"], ['', '', '', '', ''], $text);
        $article = json_decode($text, true);
        if (!$article || empty($article['title'])) return new WP_Error('parse','Risposta AI non parsabile.');
        return ['title'=>sanitize_text_field($article['title']),'seo_title'=>sanitize_text_field($article['seo_title']??$article['title']),'meta_description'=>sanitize_text_field($article['meta_description']??''),'slug'=>sanitize_title($article['slug']??$article['title']),'excerpt'=>sanitize_text_field($article['excerpt']??''),'content'=>wp_kses_post(self::clean_content($article['content_html']??'')),'tags'=>is_array($article['tags']??null)?array_map('sanitize_text_field',$article['tags']):[],'faq'=>is_array($article['faq']??null)?$article['faq']:[],'image_prompts'=>is_array($article['image_prompts']??null)?$article['image_prompts']:[]];
    }

    private static function seo_fix(array $a, string $kw): array {
        $opts_gen = V4_Options::get();
        // V6: Use universal context
        $ctx   = class_exists('V6_Context') ? V6_Context::get() : [];
        $_biz  = $ctx['name']  ?? ($opts_gen['business'] ?: 'Company');
        $_city = $ctx['city']  ?? ($opts_gen['geo_city'] ?: '');
        $_lang = $ctx['language'] ?? 'it';
        $_sym  = $ctx['currency_sym'] ?? '€';
        $_sector = $ctx['sector_label'] ?? ($opts_gen['sector'] ?: '');
        $_phone  = $ctx['phone']  ?? '';
        $_wa     = $ctx['wa_url'] ?? '#';
        $_years  = $ctx['trust_years']   ?? '';
        $_clients = $ctx['trust_clients'] ?? '';
        $_rating  = $ctx['rating']        ?? '';
        if (mb_strlen($a['seo_title'])>58||stripos($a['seo_title'],$kw)!==0) {
            $a['seo_title'] = mb_substr(ucfirst($kw).' | '.$_biz.' '.$_city, 0, 58);
        }
        $d=wp_strip_all_tags($a['meta_description']);
        if(mb_strlen($d)>150)$d=mb_substr($d,0,147).'…'; // 150 max for Yoast compatibility
        if(mb_strlen($d)<120)$d.=' Richiedi un preventivo gratuito con SOSER.';
        $a['meta_description']=$d;
        if(stripos(mb_substr(wp_strip_all_tags($a['content']),0,200),$kw)===false)
            $a['content']='<p><strong>'.esc_html(ucfirst($kw)).'</strong> — '.esc_html($_sector).' '.esc_html($_city).': prezzi, tempi e come scegliere il professionista giusto.</p>'.$a['content'];
        return $a;
    }

    private static function clean_content(string $html): string {
        // Remove backtick/markdown artifacts from AI output
        $html = str_replace(['```html','```HTML','```','`html','`HTML'], '', $html);
        $html = preg_replace('/^`html\s*/im', '', $html);
        // Remove H1 — theme/template handles the title display
        $html = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $html);
        return trim($html);
    }

    private static function humanize(string $html): string {
        return str_ireplace(['In conclusione,','In sintesi,','È importante notare che','È fondamentale','Assolutamente,','Certamente,'],['Prima di decidere,','In pratica,','Spesso si dimentica che','Vale la pena ricordare','',''],$html);
    }

    private static function inject_internal_links(string $html, string $kw): string {
        // Search with cleaned keyword
        $clean = preg_replace('/Milano|2026|2025|bonus/i', '', $kw);
        $posts = get_posts([
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => 10,
            's'           => trim($clean),
        ]);

        // Also get homepage and service pages as fallback
        if (empty($posts)) {
            $posts = get_posts([
                'post_type'   => 'page',
                'post_status' => 'publish',
                'numberposts' => 3,
            ]);
        }

        $added = 0;
        foreach ($posts as $p) {
            if ($added >= 2) break;
            $url = get_permalink($p);
            if (strpos($html, $url) !== false) continue;
            // Inject inside content after first H2 (not just append at end)
            $link = '<p>Leggi anche: <a href="' . esc_url($url) . '">' . esc_html(get_the_title($p)) . '</a>.</p>';
            $html .= $link;
            $added++;
        }

        // If still no links found, add link to homepage
        if ($added === 0) {
            $home = home_url('/');
            // Only add service link if business name is set
            $_biz_name = class_exists('V6_Profile') ? V6_Profile::name() : esc_html($_biz);
            $_city_name = class_exists('V6_Profile') ? V6_Profile::city() : esc_html($_city);
            if (!empty($_biz_name) && !empty($_city_name)) {
                $html .= '<p>Scopri tutti i nostri servizi: <a href="' . esc_url($home) . '">' . esc_html($_biz_name . ' ' . $_city_name) . '</a>.</p>';
            }
        }

        return $html;
    }

    private static function inject_external_links(string $html, array $opts): string {
        $links = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $opts['external_links'] ?? '')));
        if (empty($links)) return $html;

        $uid   = 'fonti' . substr(md5(implode(',', $links)), 0, 6);
        $items = '';
        foreach (array_slice(array_values($links), 0, 5) as $u) {
            $host = parse_url($u, PHP_URL_HOST);
            if (!$host) continue;
            $rel   = preg_match('/\.gov\.it|\.comune\.|wikipedia\.org|\.europa\.eu/', $u) ? 'noopener noreferrer' : 'nofollow noopener noreferrer';
            $items .= '<li style="margin-bottom:5px"><a href="' . esc_url($u) . '" target="_blank" rel="' . $rel . '" style="color:#2271b1;font-size:13px;font-family:system-ui,sans-serif;text-decoration:none">🔗 ' . esc_html($host) . '</a></li>';
        }
        if (!$items) return $html;

                $toggle_js = "var b=document.getElementById('{$uid}-body'),a=this.querySelector('.fonti-arr');"
                   . "var open=b.style.display==='block';b.style.display=open?'none':'block';"
                   . "a.style.transform=open?'':'rotate(180deg)';";

        // SEO-safe accordion: use <details open> so Google crawls links,
        // user can collapse by clicking summary.
        return $html
            . '<details open style="border:1px solid #e8e5e0;border-radius:8px;overflow:hidden;margin:24px 0;font-family:system-ui,sans-serif">'
            . '<summary style="padding:12px 18px;background:#f8f7f4;cursor:pointer;'
            . 'display:flex;justify-content:space-between;align-items:center;'
            . 'font-size:13px;font-weight:600;color:#555;font-family:system-ui,sans-serif;list-style:none">'
            . '<span>📚 Fonti ufficiali</span>'
            . '<span style="font-size:11px;color:#aaa">▲ chiudi</span>'
            . '</summary>'
            . '<ul style="margin:0;padding:12px 18px;list-style:none;background:#fff">' . $items . '</ul>'
            . '</details>';
    }

    private static function faq_schema(array $faq): string {
        if(empty($faq))return '';
        $e=[];
        foreach(array_slice($faq,0,6) as $f){
            $q=$f['question']??'';$a=$f['answer']??'';
            if($q&&$a)$e[]=['@type'=>'Question','name'=>wp_strip_all_tags($q),'acceptedAnswer'=>['@type'=>'Answer','text'=>wp_strip_all_tags($a)]];
        }
        if(!$e)return '';
        return '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$e],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    }

    private static function seo_score_comment(array $a, string $kw): string {
        $s=0;
        if(stripos($a['seo_title'],$kw)===0)$s+=20;
        if(mb_strlen($a['meta_description'])>=120)$s+=20;
        if(substr_count($a['content'],'<h2')>=3)$s+=15;
        if(substr_count($a['content'],'<a ')>=2)$s+=15;
        if(substr_count($a['content'],'<img')>=1)$s+=15;
        if(!empty($a['faq']))$s+=15;
        return "\n<!-- SOSER V4 SEO: {$s}/100 | KW: ".esc_html($kw)." -->";
    }

    private static function insert_post(array $article, array $opts, string $kw): int {

        // Category: use configured ID, else find best match by name
        $cat_id = (int) $opts['category_id'];
        if ($cat_id <= 0) {
            $all_cats = get_categories(['hide_empty' => false]);
            foreach ($all_cats as $cat) {
                if (stripos($cat->name, 'ristrutturazione') !== false || stripos($kw, $cat->name) !== false) {
                    $cat_id = $cat->term_id;
                    break;
                }
            }
            // Fallback: first non-cartongesso category
            if ($cat_id <= 0 && !empty($all_cats)) {
                foreach ($all_cats as $cat) {
                    if (stripos($cat->name, 'cartongesso') === false) {
                        $cat_id = $cat->term_id;
                        break;
                    }
                }
            }
        }

        // Tags: from AI response or auto-generated from keyword
        $tags = [];
        if (!empty($article['tags']) && is_array($article['tags'])) {
            $tags = array_map('sanitize_text_field', $article['tags']);
        } else {
            $words = array_filter(explode(' ', $kw), fn($w) => mb_strlen($w) > 3);
            $tags  = array_values($words);
            $opts_t = V4_Options::get();
            $ctx_t = class_exists('V6_Context') ? V6_Context::get() : [];
            $tags[] = ($ctx_t['sector'] ?: $opts_t['sector'] ?: '') . ' ' . ($ctx_t['city'] ?: $opts_t['geo_city'] ?: '');
            $tags[] = $ctx_t['name'] ?: $opts_t['business'] ?: '';
        }

        $pid = wp_insert_post([
            'post_title'    => $article['title'],
            'post_name'     => $article['slug'],
            'post_excerpt'  => $article['excerpt'],
            'post_content'  => $article['content'],
            // V8: Publish Controller (publish/draft/schedule/smart)
            'post_status'   => (class_exists('V8_PublishController')
                ? V8_PublishController::get_post_args($article['score'] ?? 0)['post_status']
                : ($opts['post_status'] ?: 'publish')),
            'post_author'   => (int) ($opts['post_author'] ?: 1),
            'post_category' => $cat_id > 0 ? [$cat_id] : [],
            'tags_input'    => $tags,
        ], true);

        if (is_wp_error($pid)) return $pid;
        // V8: Fill ALL SEO plugins in one call
        if (class_exists('V8_SEOAutofill')) {
            V8_SEOAutofill::fill($pid, $article, $kw);
        } else {
            update_post_meta($pid,'_yoast_wpseo_focuskw',$kw);
            update_post_meta($pid,'_yoast_wpseo_title',$article['seo_title']);
            update_post_meta($pid,'_yoast_wpseo_metadesc',$article['meta_description']);
            update_post_meta($pid,'rank_math_focus_keyword',$kw);
            update_post_meta($pid,'rank_math_title',$article['seo_title']);
            update_post_meta($pid,'rank_math_description',$article['meta_description']);
        }

        // V8: Set scheduled date + notify if draft/review
        if (class_exists('V8_PublishController')) {
            $pub_args = V8_PublishController::get_post_args($article['score'] ?? 0);
            if ($pub_args['post_status'] === 'future' && !empty($pub_args['post_date'])) {
                wp_update_post(['ID' => $pid, 'post_date' => $pub_args['post_date'], 'post_date_gmt' => get_gmt_from_date($pub_args['post_date']), 'post_status' => 'future']);
            }
            V8_PublishController::notify_ready($pid, $pub_args['post_status']);
        }
        update_post_meta($pid,'_soser_focus_keyword',$kw);
        // V5.1: Rich Schema (Service + Price)
        if (class_exists('V5_Rich_Schema')) V5_Rich_Schema::generate_for_post($pid, $article, $kw);
        // V5.1: Invalidate silo map (new post added)
        if (class_exists('V5_Silo')) V5_Silo::invalidate();
        // Save schema to postmeta - output via wp_head hook
        if (!empty($article['faq_schema'])) {
            update_post_meta($pid, '_soser_faq_schema', $article['faq_schema']);
        }
        return (int)$pid;
    }

    private static function insert_after_nth_h2(string $html, string $block, int $n): string {
        $pos=0;$count=0;
        while(($pos=stripos($html,'</h2>',$pos))!==false){$count++;$pos+=5;if($count===$n)return substr($html,0,$pos).$block.substr($html,$pos);}
        return $html.$block;
    }
}
