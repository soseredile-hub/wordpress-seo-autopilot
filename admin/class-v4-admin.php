<?php
defined('ABSPATH') || exit;

class V4_Admin {

    public static function init() {
        add_action('admin_menu',            [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_post_soser_v4_save',     [__CLASS__, 'save']);
        add_action('admin_post_soser_v4_generate', [__CLASS__, 'manual_generate']);
        add_action('admin_post_soser_v4_discover', [__CLASS__, 'discover']);
        add_action('admin_post_soser_v4_process',  [__CLASS__, 'process']);
        add_action('admin_post_soser_v4_clear',    [__CLASS__, 'clear_done']);
        add_action('admin_post_soser_v4_gsc_save', [__CLASS__, 'gsc_save']);
        add_action('admin_init',                   [__CLASS__, 'gsc_handle_callback']);
        // V10: Unified Dashboard AJAX
        add_action('wp_ajax_soser_v10_dashboard_data', [__CLASS__, 'ajax_v10_dashboard']);
        add_action('wp_ajax_soser_v10_add_keyword',    [__CLASS__, 'ajax_v10_add_keyword']);
        add_action('wp_ajax_soser_v10_process_next',   [__CLASS__, 'ajax_v10_process_next']);

        add_action('wp_ajax_soser_v4_scan_site',     [__CLASS__, 'ajax_scan']);
        add_action('wp_ajax_soser_v4_test_api',      [__CLASS__, 'ajax_test']);
        add_action('wp_ajax_soser_v4_scan_business', [__CLASS__, 'ajax_scan_business']);
        add_action('wp_ajax_soser_v4_gsc_sites',     [__CLASS__, 'ajax_gsc_sites']);
        add_action('wp_ajax_soser_v4_gsc_refresh',   [__CLASS__, 'ajax_gsc_refresh']);
        add_action('wp_ajax_soser_v4_gsc_disconnect',[__CLASS__, 'ajax_gsc_disconnect']);
        // V5 AJAX
        add_action('wp_ajax_soser_v5_run_plan',     [__CLASS__, 'ajax_v5_run_plan']);
        add_action('wp_ajax_soser_v5_refresh_post', [__CLASS__, 'ajax_v5_refresh_post']);
        add_action('wp_ajax_soser_v5_optimize_ctr', [__CLASS__, 'ajax_v5_optimize_ctr']);
        add_action('wp_ajax_soser_v5_sync_memory',  [__CLASS__, 'ajax_v5_sync_memory']);
        add_action('wp_ajax_soser_v5_gen_video',    [__CLASS__, 'ajax_v5_gen_video']);
        add_action('wp_ajax_soser_v5_ai_overview',   [__CLASS__, 'ajax_v5_ai_overview']);
        add_action('admin_post_soser_v51_eeat_save', [__CLASS__, 'save_eeat']);
        add_action('wp_ajax_soser_v52_autolink_batch',  [__CLASS__, 'ajax_v52_autolink']);
        add_action('wp_ajax_soser_v52_fix_alts',        [__CLASS__, 'ajax_v52_fix_alts']);
        add_action('wp_ajax_soser_v52_start_ab',        [__CLASS__, 'ajax_v52_start_ab']);
        add_action('wp_ajax_soser_v52_scan_cannibal',   [__CLASS__, 'ajax_v52_scan_cannibal']);
        add_action('wp_ajax_soser_v52_analyze_comp',    [__CLASS__, 'ajax_v52_analyze_comp']);
        add_action('wp_ajax_soser_v52_translate',       [__CLASS__, 'ajax_v52_translate']);
        add_action('wp_ajax_soser_v53_scan',         [__CLASS__, 'ajax_v53_scan']);
        add_action('wp_ajax_soser_v53_process',      [__CLASS__, 'ajax_v53_process']);
        add_action('wp_ajax_soser_v53_reset',        [__CLASS__, 'ajax_v53_reset']);
        add_action('wp_ajax_soser_v54_save_active',   [__CLASS__, 'ajax_v54_save_active']);
        add_action('wp_ajax_soser_v54_save_service',  [__CLASS__, 'ajax_v54_save_service']);
        add_action('wp_ajax_soser_v55_refresh_wins',    [__CLASS__, 'ajax_v55_refresh_wins']);
        add_action('wp_ajax_soser_v55_harvest_qs',      [__CLASS__, 'ajax_v55_harvest_qs']);
        add_action('wp_ajax_soser_v57_depth',          [__CLASS__, 'ajax_v57_depth']);
        add_action('wp_ajax_soser_v57_multiloc_queue', [__CLASS__, 'ajax_v57_multiloc_queue']);
        add_action('wp_ajax_soser_v57_save_cities',    [__CLASS__, 'ajax_v57_save_cities']);
        add_action('wp_ajax_soser_v57_add_redirect',   [__CLASS__, 'ajax_v57_add_redirect']);
        add_action('wp_ajax_soser_v57_del_redirect',   [__CLASS__, 'ajax_v57_del_redirect']);
        add_action('wp_ajax_soser_v58_auto_tag',       [__CLASS__, 'ajax_v58_auto_tag']);
        add_action('wp_ajax_soser_v59_apply_design',  [__CLASS__, 'ajax_v59_apply_design']);
        add_action('wp_ajax_soser_v58_test_match',     [__CLASS__, 'ajax_v58_test_match']);
        add_action('wp_ajax_soser_v57_serp_run',       [__CLASS__, 'ajax_v57_serp_run']);
    }

    public static function menu() {
        add_menu_page('SOSER SEO V4', 'SOSER SEO V4', 'manage_options',
            'soser-v4', [__CLASS__, 'page_dashboard'], 'dashicons-chart-line', 55);
        // ── CORE (always visible) ──────────────────────────────
        add_submenu_page('soser-v4', 'Dashboard',           'Dashboard',           'manage_options', 'soser-v4',            [__CLASS__, 'page_dashboard']);
        add_submenu_page('soser-v4', '📝 Coda & Log',       '📝 Coda & Log',       'manage_options', 'soser-v4-queue',      [__CLASS__, 'page_queue']);
        add_submenu_page('soser-v4', '📊 Analytics',        '📊 Analytics',        'manage_options', 'soser-v7-analytics',  [__CLASS__, 'page_v7_analytics']);
        add_submenu_page('soser-v4', '🌍 Business Profile', '🌍 Business Profile', 'manage_options', 'soser-v6-profile',    [__CLASS__, 'page_v6_profile']);

        // ── CONTENT ────────────────────────────────────────────
        add_submenu_page('soser-v4', '— Contenuto —',      '— Contenuto —',       'manage_options', 'soser-v4-intel',      [__CLASS__, 'page_intel']);
        add_submenu_page('soser-v4', '🎯 Servizi Focus',    '🎯 Servizi Focus',    'manage_options', 'soser-v54-services',  [__CLASS__, 'page_v54_services']);
        add_submenu_page('soser-v4', '⚡ Quick Wins',        '⚡ Quick Wins',        'manage_options', 'soser-v55-quickwin',  [__CLASS__, 'page_v55_quickwin']);
        add_submenu_page('soser-v4', '❓ Domande',           '❓ Domande',           'manage_options', 'soser-v55-questions', [__CLASS__, 'page_v55_questions']);
        add_submenu_page('soser-v4', '📅 Stagionalità',      '📅 Stagionalità',     'manage_options', 'soser-v55-seasonal',  [__CLASS__, 'page_v55_seasonal']);
        add_submenu_page('soser-v4', '🧠 AI Planner',        '🧠 AI Planner',       'manage_options', 'soser-v5-planner',    [__CLASS__, 'page_v5_planner']);
        add_submenu_page('soser-v4', '🗺️ Topic Map',         '🗺️ Topic Map',        'manage_options', 'soser-v5-topicmap',   [__CLASS__, 'page_v5_topicmap']);

        // ── SEO & TRACKING ─────────────────────────────────────
        add_submenu_page('soser-v4', '— SEO & Tracking —',  '— SEO & Tracking —',  'manage_options', 'soser-v57-serp',      [__CLASS__, 'page_v57_serp']);
        add_submenu_page('soser-v4', '📊 Search Console',   '📊 Search Console',   'manage_options', 'soser-v4-gsc',        [__CLASS__, 'page_gsc']);
        add_submenu_page('soser-v4', '⭐ Snippet Win',       '⭐ Snippet Win',       'manage_options', 'soser-v57-snippet',   [__CLASS__, 'page_v57_snippet']);
        add_submenu_page('soser-v4', '🧠 Content Score',     '🧠 Content Score',    'manage_options', 'soser-v57-score',     [__CLASS__, 'page_v57_score']);
        add_submenu_page('soser-v4', '🌍 Multi-Città',       '🌍 Multi-Città',      'manage_options', 'soser-v57-multiloc',  [__CLASS__, 'page_v57_multiloc']);
        add_submenu_page('soser-v4', '⚔️ Cannibalization',   '⚔️ Cannibalization',  'manage_options', 'soser-v52-cannibal',  [__CLASS__, 'page_v52_cannibal']);
        add_submenu_page('soser-v4', '🔗 Auto Links',        '🔗 Auto Links',       'manage_options', 'soser-v52-autolink',  [__CLASS__, 'page_v52_autolink']);
        add_submenu_page('soser-v4', '🏆 Rich Schema',       '🏆 Rich Schema',      'manage_options', 'soser-v5-schema',     [__CLASS__, 'page_v5_schema']);
        add_submenu_page('soser-v4', '📍 Local SEO',         '📍 Local SEO',        'manage_options', 'soser-v5-local',      [__CLASS__, 'page_v5_local']);
        add_submenu_page('soser-v4', '🤝 E-E-A-T',           '🤝 E-E-A-T',          'manage_options', 'soser-v5-eeat',       [__CLASS__, 'page_v5_eeat']);

        // ── DESIGN & MEDIA ─────────────────────────────────────
        add_submenu_page('soser-v4', '— Design & Media —',  '— Design & Media —',  'manage_options', 'soser-v59-design',    [__CLASS__, 'page_v59_design']);
        add_submenu_page('soser-v4', '🎯 UX Engine',         '🎯 UX Engine',        'manage_options', 'soser-v510-ux',       [__CLASS__, 'page_v510_ux']);
        add_submenu_page('soser-v4', '🔍 AI Vision',       '🔍 AI Vision',       'manage_options', 'soser-v10-vision',    [__CLASS__, 'page_v10_vision']);
        add_submenu_page('soser-v4', '🖼️ Image Processor', '🖼️ Image Processor', 'manage_options', 'soser-v10-images', [__CLASS__, 'page_v10_images']);
        add_submenu_page('soser-v4', '🖼️ Libreria Foto',     '🖼️ Libreria Foto',    'manage_options', 'soser-v58-images',    [__CLASS__, 'page_v58_images']);
        add_submenu_page('soser-v4', '🖼️ Image SEO',         '🖼️ Image SEO',        'manage_options', 'soser-v52-imageseo',  [__CLASS__, 'page_v52_imageseo']);

        // ── ADVANCED ───────────────────────────────────────────
        add_submenu_page('soser-v4', '— Avanzato —',        '— Avanzato —',        'manage_options', 'soser-v53-refresh',   [__CLASS__, 'page_v53_refresh']);
        add_submenu_page('soser-v4', '⚡ CTR Optimizer',    '⚡ CTR Optimizer',    'manage_options', 'soser-v5-ctr',        [__CLASS__, 'page_v5_ctr']);
        add_submenu_page('soser-v4', '🧪 A/B Titles',        '🧪 A/B Titles',       'manage_options', 'soser-v52-abtest',    [__CLASS__, 'page_v52_abtest']);
        add_submenu_page('soser-v4', '🕵️ Competitor Gap',    '🕵️ Competitor Gap',   'manage_options', 'soser-v52-competitor',[__CLASS__, 'page_v52_competitor']);
        add_submenu_page('soser-v4', '↩️ Redirect',          '↩️ Redirect',         'manage_options', 'soser-v57-redirect',  [__CLASS__, 'page_v57_redirect']);
        add_submenu_page('soser-v4', '🗓️ Calendario',        '🗓️ Calendario',       'manage_options', 'soser-v52-calendar',  [__CLASS__, 'page_v52_calendar']);
        add_submenu_page('soser-v4', '📡 Monitor',           '📡 Monitor',          'manage_options', 'soser-v52-monitor',   [__CLASS__, 'page_v52_monitor']);
        add_submenu_page('soser-v4', '🧬 Memory',            '🧬 Memory',           'manage_options', 'soser-v5-memory',     [__CLASS__, 'page_v5_memory']);
        add_submenu_page('soser-v4', 'Content Scanner',     'Content Scanner',     'manage_options', 'soser-v4-scanner',    [__CLASS__, 'page_scanner']);

        // ── SETTINGS ───────────────────────────────────────────
        add_submenu_page('soser-v4', '⚙️ V8 Settings',      '⚙️ V8 Settings',      'manage_options', 'soser-v8-settings',   [__CLASS__, 'page_v8_settings']);
        add_submenu_page('soser-v4', 'Impostazioni',        'Impostazioni',        'manage_options', 'soser-v4-settings',   [__CLASS__, 'page_settings']);
    }

    public static function enqueue($hook) {
        if (strpos($hook, 'soser-v4') === false) return;
        wp_enqueue_style('soser-v4',  SOSER_V4_URL . 'assets/css/admin.css', [], SOSER_V4_VER);
        wp_enqueue_script('soser-v4', SOSER_V4_URL . 'assets/js/admin.js', ['jquery'], SOSER_V4_VER, true);
        wp_localize_script('soser-v4', 'soserV4', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('soser_v4_nonce'),
        ]);
    }

    // ── PAGES ──────────────────────────────────────────────────────

    public static function page_dashboard() {
        $stats     = V4_Queue::stats();
        $log       = V4_Queue::get_log(5);
        $o         = V4_Options::get();
        $next      = wp_next_scheduled(V4_Queue::CRON_DAILY);
        $gsc_ok    = class_exists('V4_GSC') && V4_GSC::is_connected();
        $ga4_ok    = class_exists('V7_GA4') && V7_GA4::is_connected();
        $serp_ok   = class_exists('V7_SERPTracker');
        $pub_stats = class_exists('V8_PublishController') ? V8_PublishController::stats() : [];
        $nonce     = wp_create_nonce('soser_v10_dashboard');
        $color     = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
        include SOSER_V4_DIR . 'admin/views/dashboard.php';
    }

    public static function page_intel() {
        $cached = get_transient('soser_v4_keyword_intel') ?: [];
        // Merge GSC opportunities if connected
        $gsc_opps = [];
        if (V4_GSC::is_connected()) {
            $gsc_opps = V4_GSC::get_keyword_opportunities(V4_GSC::get_site_url());
        }
        include SOSER_V4_DIR . 'admin/views/intel.php';
    }

    public static function page_gsc() {
        $creds      = V4_GSC::get_credentials();
        $connected  = V4_GSC::is_connected();
        $configured = V4_GSC::is_configured();
        $site_url   = V4_GSC::get_site_url();
        $auth_url   = $configured ? V4_GSC::get_auth_url() : '';
        include SOSER_V4_DIR . 'admin/views/gsc.php';
    }

    public static function page_scanner() {
        $map = get_transient('soser_v4_content_map') ?: [];
        include SOSER_V4_DIR . 'admin/views/scanner.php';
    }

    public static function page_queue() {
        $log   = V4_Queue::get_log(100);
        $stats = V4_Queue::stats();
        include SOSER_V4_DIR . 'admin/views/queue.php';
    }

    public static function page_settings() {
        $o = V4_Options::get();
        include SOSER_V4_DIR . 'admin/views/settings.php';
    }

    // ── ACTIONS ────────────────────────────────────────────────────

    public static function save() {
        check_admin_referer('soser_v4_settings');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        // Save universal settings
        $universal = ['geo_city','geo_region','geo_country','geo_lat','geo_lon','geo_zones','sector','sector_label','business_type','currency','price_range'];
        $opts = V4_Options::get();
        foreach ($universal as $k) {
            $post_key = 'aaw_' . $k;
            if (isset($_POST[$post_key])) {
                $val = sanitize_text_field(wp_unslash($_POST[$post_key]));
                if ($k === 'geo_zones') $val = sanitize_textarea_field(wp_unslash($_POST[$post_key]));
                $opts[$k] = $val;
            }
        }
        update_option(SOSER_V4_OPT, $opts);
        // Update geo in main settings too
        if (isset($_POST['aaw_geo_city'], $_POST['aaw_geo_region'])) {
            $_POST['aaw_geo'] = sanitize_text_field($_POST['aaw_geo_city']) . ', ' . sanitize_text_field($_POST['aaw_geo_region']);
        }
        V4_Options::save($_POST);
        // Rebuild service seeds with new city
        if (class_exists('V54_ServiceFocus')) V54_ServiceFocus::rebuild_seeds();
        V4_Queue::schedule();
        wp_redirect(admin_url('admin.php?page=soser-v4-settings&saved=1'));
        exit;
    }

    public static function manual_generate() {
        check_admin_referer('soser_v4_generate');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $kw = sanitize_text_field($_POST['keyword'] ?? '');
        $id = V4_Queue::enqueue($kw, 'manual');
        V4_Queue::kick();
        wp_redirect(admin_url('admin.php?page=soser-v4&queued=' . $id));
        exit;
    }

    public static function discover() {
        check_admin_referer('soser_v4_discover');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        delete_transient('soser_v4_keyword_intel');
        $intel = new V4_Keyword_Intel();
        $intel->run();
        wp_redirect(admin_url('admin.php?page=soser-v4-intel&refreshed=1'));
        exit;
    }

    public static function process() {
        check_admin_referer('soser_v4_process');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        V4_Queue::process_next(1);
        wp_redirect(admin_url('admin.php?page=soser-v4-queue&processed=1'));
        exit;
    }

    public static function clear_done() {
        check_admin_referer('soser_v4_clear');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        V4_Queue::clear_done();
        wp_redirect(admin_url('admin.php?page=soser-v4-queue&cleared=1'));
        exit;
    }

    public static function gsc_save() {
        check_admin_referer('soser_v4_gsc_save');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        V4_GSC::save_credentials(
            sanitize_text_field($_POST['gsc_client_id'] ?? ''),
            sanitize_text_field($_POST['gsc_client_secret'] ?? '')
        );
        V4_GSC::save_site_url(esc_url_raw($_POST['gsc_site_url'] ?? home_url('/')));
        wp_redirect(admin_url('admin.php?page=soser-v4-gsc&saved=1'));
        exit;
    }

    public static function gsc_handle_callback() {
        if (empty($_GET['soser_gsc_callback']) || empty($_GET['code'])) return;
        if (!current_user_can('manage_options')) return;
        $ok  = V4_GSC::exchange_code(sanitize_text_field($_GET['code']));
        $msg = $ok ? 'connected=1' : 'error=auth_failed';
        wp_redirect(admin_url('admin.php?page=soser-v4-gsc&' . $msg));
        exit;
    }

    // ── AJAX ───────────────────────────────────────────────────────

    public static function ajax_scan() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        delete_transient('soser_v4_content_map');
        $map = V4_Content_Scanner::scan();
        wp_send_json_success(['count' => count($map), 'message' => 'Scansionati ' . count($map) . ' contenuti.']);
    }

    public static function ajax_test() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $key = sanitize_text_field($_POST['api_key'] ?? V4_Options::get()['openai_key']);
        $r   = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 12,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['model' => 'gpt-4.1-mini', 'max_tokens' => 5, 'messages' => [['role' => 'user', 'content' => 'ok']]]),
        ]);
        if (is_wp_error($r)) { wp_send_json_error($r->get_error_message()); }
        $code = wp_remote_retrieve_response_code($r);
        if ($code === 200) wp_send_json_success('✅ API Key valida!');
        $b = json_decode(wp_remote_retrieve_body($r), true);
        wp_send_json_error($b['error']['message'] ?? "HTTP {$code}");
    }

    public static function ajax_scan_business() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V4_Business_Context::clear_cache();
        $ctx   = V4_Business_Context::build();
        $count = count($ctx['services']);
        wp_send_json_success([
            'count'    => $count,
            'services' => array_column($ctx['services'], 'name'),
            'message'  => "Trovati {$count} servizi dal sito.",
            'summary'  => $ctx['summary'],
        ]);
    }

    public static function ajax_gsc_sites() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        wp_send_json_success(V4_GSC::get_sites());
    }

    public static function ajax_gsc_refresh() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $site = sanitize_text_field($_POST['site_url'] ?? V4_GSC::get_site_url());
        delete_transient('soser_v4_gsc_kw_' . md5($site . '90'));
        $opps = V4_GSC::get_keyword_opportunities($site, 90);
        wp_send_json_success([
            'count'   => count($opps),
            'message' => 'Trovate ' . count($opps) . ' opportunità da GSC.',
            'top'     => array_slice($opps, 0, 5),
        ]);
    }

    // ── V5 PAGES ──────────────────────────────────────────────────

    public static function page_v5_planner() {
        if (!class_exists('V5_Planner')) { echo '<div class="wrap"><p>V5 non disponibile.</p></div>'; return; }
        $plan = V5_Planner::plan();
        include SOSER_V4_DIR . 'admin/views/v5-planner.php';
    }

    public static function page_v5_topicmap() {
        if (!class_exists('V5_Semantic')) { echo '<div class="wrap"><p>V5 non disponibile.</p></div>'; return; }
        $opts  = V4_Options::get();
        $seeds = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $opts['seed_keywords']??'')));
        $map   = $seeds ? V5_Semantic::build_topic_map($seeds, V5_Memory::get_topics_covered()) : [];
        include SOSER_V4_DIR . 'admin/views/v5-topicmap.php';
    }

    public static function page_v5_memory() {
        if (!class_exists('V5_Memory')) { echo '<div class="wrap"><p>V5 non disponibile.</p></div>'; return; }
        $memory = V5_Memory::get_all();
        $stats  = V5_Memory::stats();
        include SOSER_V4_DIR . 'admin/views/v5-memory.php';
    }

    public static function page_v5_analytics() {
        if (!class_exists('V5_Analytics')) { echo '<div class="wrap"><p>V5 non disponibile.</p></div>'; return; }
        $report = V5_Analytics::report();
        include SOSER_V4_DIR . 'admin/views/v5-analytics.php';
    }

    public static function page_v5_ctr() {
        if (!class_exists('V5_CTR')) { echo '<div class="wrap"><p>V5 non disponibile.</p></div>'; return; }
        $memory = V5_Memory::get_all();
        include SOSER_V4_DIR . 'admin/views/v5-ctr.php';
    }

    // ── V5.7 PAGES ────────────────────────────────────────────────

    public static function page_v57_snippet() {
        $posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>30,'meta_key'=>'_soser_focus_keyword']);
        $scores = [];
        foreach (array_slice($posts,0,20) as $p) {
            $s = class_exists('V57_FeaturedSnippet') ? V57_FeaturedSnippet::score($p->post_content, get_post_meta($p->ID,'_soser_focus_keyword',true)) : [];
            $scores[$p->ID] = ['post'=>$p,'snippet'=>$s];
        }
        include SOSER_V4_DIR . 'admin/views/v57-snippet.php';
    }

    public static function page_v57_score() {
        $scores = class_exists('V57_ContentScore') ? V57_ContentScore::bulk_score(50) : [];
        include SOSER_V4_DIR . 'admin/views/v57-score.php';
    }

    public static function page_v57_serp() {
        $positions = class_exists('V57_SERPTracker') ? V57_SERPTracker::get_latest_positions() : [];
        $history   = get_option('soser_v57_serp_history',[]);
        include SOSER_V4_DIR . 'admin/views/v57-serp.php';
    }

    public static function page_v57_multiloc() {
        $cities   = class_exists('V57_MultiLocation') ? V57_MultiLocation::get_cities() : [];
        $coverage = class_exists('V57_MultiLocation') ? V57_MultiLocation::get_coverage() : [];
        $services = class_exists('V54_ServiceFocus')  ? V54_ServiceFocus::get_active_services() : [];
        include SOSER_V4_DIR . 'admin/views/v57-multiloc.php';
    }

    public static function page_v57_redirect() {
        $redirects = class_exists('V57_RedirectManager') ? V57_RedirectManager::get_all() : [];
        include SOSER_V4_DIR . 'admin/views/v57-redirect.php';
    }

    public static function ajax_v57_depth() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $kw     = sanitize_text_field($_POST['kw']??'');
        $pid    = (int)($_POST['post_id']??0);
        $content = $pid ? get_post_field('post_content',$pid) : '';
        $result = class_exists('V57_TopicalDepth') ? V57_TopicalDepth::analyze($kw,$content) : [];
        wp_send_json_success($result);
    }

    public static function ajax_v57_multiloc_queue() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $kw = sanitize_text_field($_POST['keyword']??'');
        if (!$kw) wp_send_json_error('Keyword mancante');
        $cities = array_map('sanitize_text_field', $_POST['cities']??[]);
        $count  = class_exists('V57_MultiLocation') ? V57_MultiLocation::queue_all($kw,$cities) : 0;
        wp_send_json_success("Aggiunti {$count} articoli alla coda per le città selezionate.");
    }

    public static function ajax_v57_save_cities() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $cities = array_map('sanitize_text_field', array_filter($_POST['cities']??[]));
        if (class_exists('V57_MultiLocation')) V57_MultiLocation::save_cities($cities);
        wp_send_json_success('Città salvate: '.count($cities));
    }

    public static function ajax_v57_add_redirect() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $from = sanitize_title($_POST['from']??'');
        $to   = (int)($_POST['to']??0);
        if (!$from||!$to) wp_send_json_error('Dati mancanti');
        V57_RedirectManager::add($from,$to);
        wp_send_json_success('Redirect aggiunto.');
    }

    public static function ajax_v57_del_redirect() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V57_RedirectManager::remove(sanitize_title($_POST['slug']??''));
        wp_send_json_success('Redirect rimosso.');
    }

    public static function ajax_v57_serp_run() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V57_SERPTracker::run_check();
        $positions = V57_SERPTracker::get_latest_positions();
        wp_send_json_success(['count'=>count($positions),'message'=>'Aggiornate '.count($positions).' keyword.']);
    }

    // ── V5.5 PAGES ────────────────────────────────────────────────

    public static function page_v55_quickwin() {
        $wins    = class_exists('V55_QuickWin') ? V55_QuickWin::find() : [];
        $hot_now = class_exists('V55_SeasonalCalendar') ? V55_SeasonalCalendar::get_hot_now() : [];
        include SOSER_V4_DIR . 'admin/views/v55-quickwin.php';
    }

    public static function page_v55_questions() {
        $questions = class_exists('V55_QuestionHarvester') ? V55_QuestionHarvester::harvest() : [];
        $stats     = class_exists('V55_QuestionHarvester') ? V55_QuestionHarvester::get_stats($questions) : [];
        include SOSER_V4_DIR . 'admin/views/v55-questions.php';
    }

    public static function page_v55_seasonal() {
        $write_now   = class_exists('V55_SeasonalCalendar') ? V55_SeasonalCalendar::get_write_now() : [];
        $yearly_plan = class_exists('V55_SeasonalCalendar') ? V55_SeasonalCalendar::get_yearly_plan() : [];
        $seasonal_kws= class_exists('V55_SeasonalCalendar') ? V55_SeasonalCalendar::get_seasonal_keywords() : [];
        include SOSER_V4_DIR . 'admin/views/v55-seasonal.php';
    }

    public static function ajax_v55_refresh_wins() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V55_QuickWin::invalidate();
        $wins = V55_QuickWin::find();
        wp_send_json_success(['count'=>count($wins),'message'=>'Trovate '.count($wins).' opportunità quick win.']);
    }

    public static function ajax_v55_harvest_qs() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        delete_transient('v55_questions');
        $qs    = V55_QuestionHarvester::harvest(true);
        $stats = V55_QuestionHarvester::get_stats($qs);
        wp_send_json_success(['total'=>$stats['total'],'missing'=>$stats['missing'],'message'=>"Trovate {$stats['total']} domande, {$stats['missing']} non ancora coperte."]);
    }

    // ── V5.4 PAGES ────────────────────────────────────────────────

    public static function page_v54_services() {
        if (!class_exists('V54_ServiceFocus')) { echo '<div class="wrap"><p>V5.4 non disponibile.</p></div>'; return; }
        $services  = V54_ServiceFocus::get_services();
        $active    = V54_ServiceFocus::get_active_ids();
        $coverage  = V54_ServiceFocus::get_coverage();
        include SOSER_V4_DIR . 'admin/views/v54-services.php';
    }

    public static function ajax_v54_save_active() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $ids = array_map('sanitize_key', $_POST['active'] ?? []);
        if (empty($ids)) wp_send_json_error('Seleziona almeno un servizio.');
        V54_ServiceFocus::save_active($ids);
        $active  = V54_ServiceFocus::get_active_services();
        $names   = array_column($active, 'name');
        wp_send_json_success([
            'count'   => count($names),
            'names'   => $names,
            'message' => 'Focus aggiornato! Ora la AI scriverà su: ' . implode(', ', $names),
        ]);
    }

    public static function ajax_v54_save_service() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        // Add or update a custom service
        $services = V54_ServiceFocus::get_services();
        $new_svc  = [
            'id'    => sanitize_key($_POST['svc_id']   ?? uniqid('svc_')),
            'name'  => sanitize_text_field($_POST['svc_name'] ?? ''),
            'icon'  => sanitize_text_field($_POST['svc_icon'] ?? '🔧'),
            'seeds' => array_filter(array_map('sanitize_text_field',
                preg_split('/
|
|
/', $_POST['svc_seeds'] ?? '')
            )),
        ];
        if (empty($new_svc['name'])) wp_send_json_error('Nome servizio mancante.');
        // Update or add
        $found = false;
        foreach ($services as &$s) {
            if ($s['id'] === $new_svc['id']) { $s = $new_svc; $found = true; break; }
        }
        if (!$found) $services[] = $new_svc;
        V54_ServiceFocus::save_services($services);
        wp_send_json_success('Servizio salvato!');
    }

    // ── V5.3 PAGES ────────────────────────────────────────────────

    public static function page_v53_refresh() {
        if (!class_exists('V53_BulkRefresh')) { echo '<div class="wrap"><p>V5.3 non disponibile.</p></div>'; return; }
        $stats = V53_BulkRefresh::stats();
        $queue = V53_BulkRefresh::get_queue(200);
        include SOSER_V4_DIR . 'admin/views/v53-refresh.php';
    }

    public static function ajax_v53_scan() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V53_BulkRefresh::clear_all();
        $count = V53_BulkRefresh::scan_all();
        $stats = V53_BulkRefresh::stats();
        wp_send_json_success([
            'count'   => $count,
            'pending' => (int)$stats['pending'],
            'message' => "Scansionati {$count} articoli. Pronti: {$stats['pending']}",
        ]);
    }

    public static function ajax_v53_process() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $limit  = min(5, max(1, (int)($_POST['limit'] ?? 2)));
        $result = V53_BulkRefresh::process_batch($limit);
        $stats  = V53_BulkRefresh::stats();
        wp_send_json_success(array_merge($result, [
            'remaining' => (int)$stats['pending'],
            'done'      => (int)$stats['done'],
        ]));
    }

    public static function ajax_v53_reset() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V53_BulkRefresh::reset_done();
        wp_send_json_success('Reset completato — tutti gli articoli sono pronti per un nuovo refresh.');
    }

    // ── V5.2 PAGES ────────────────────────────────────────────────

    public static function page_v52_calendar() {
        $calendar = class_exists('V52_ContentCalendar') ? V52_ContentCalendar::generate(4) : [];
        include SOSER_V4_DIR . 'admin/views/v52-calendar.php';
    }
    public static function page_v52_cannibal() {
        $conflicts = class_exists('V52_Cannibalization') ? V52_Cannibalization::get_cached() : [];
        include SOSER_V4_DIR . 'admin/views/v52-cannibal.php';
    }
    public static function page_v52_autolink() {
        include SOSER_V4_DIR . 'admin/views/v52-autolink.php';
    }
    public static function page_v52_abtest() {
        $tests = class_exists('V52_ABTest') ? V52_ABTest::get_active_tests() : [];
        $posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>20]);
        include SOSER_V4_DIR . 'admin/views/v52-abtest.php';
    }
    public static function page_v52_competitor() {
        $competitors = class_exists('V52_CompetitorGap') ? V52_CompetitorGap::get_competitors() : [];
        include SOSER_V4_DIR . 'admin/views/v52-competitor.php';
    }
    public static function page_v52_imageseo() {
        $stats   = class_exists('V52_ImageSEO') ? V52_ImageSEO::stats() : [];
        $missing = class_exists('V52_ImageSEO') ? V52_ImageSEO::scan_missing_alts() : [];
        include SOSER_V4_DIR . 'admin/views/v52-imageseo.php';
    }
    public static function page_v52_monitor() {
        $history = class_exists('V52_PerformanceMonitor') ? V52_PerformanceMonitor::get_history() : [];
        $trend   = class_exists('V52_PerformanceMonitor') ? V52_PerformanceMonitor::get_trend() : [];
        include SOSER_V4_DIR . 'admin/views/v52-monitor.php';
    }

    // ── V5.2 AJAX ─────────────────────────────────────────────────

    public static function ajax_v52_autolink() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $r = class_exists('V52_AutoLinker') ? V52_AutoLinker::process_batch(5) : [];
        wp_send_json_success("Processati: {$r['processed']}, Linkati: {$r['linked']}, Saltati: {$r['skipped']}");
    }
    public static function ajax_v52_fix_alts() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $n = class_exists('V52_ImageSEO') ? V52_ImageSEO::bulk_fix_alts(50) : 0;
        wp_send_json_success("Corrette {$n} immagini.");
    }
    public static function ajax_v52_start_ab() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $pid = (int)($_POST['post_id']??0);
        $ok  = class_exists('V52_ABTest') ? V52_ABTest::start_test($pid) : false;
        $ok ? wp_send_json_success('Test A/B avviato!') : wp_send_json_error('Impossibile avviare il test.');
    }
    public static function ajax_v52_scan_cannibal() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        delete_transient('v52_cannibalization');
        $r = class_exists('V52_Cannibalization') ? V52_Cannibalization::scan() : [];
        wp_send_json_success(['count'=>count($r),'message'=>'Trovati '.count($r).' conflitti.']);
    }
    public static function ajax_v52_analyze_comp() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $url  = esc_url_raw($_POST['url']??'');
        $gaps = class_exists('V52_CompetitorGap') ? V52_CompetitorGap::analyze($url) : [];
        wp_send_json_success(['count'=>count($gaps),'gaps'=>array_slice($gaps,0,10)]);
    }
    public static function ajax_v52_translate() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $pid = (int)($_POST['post_id']??0);
        $new_id = class_exists('V52_Multilingual') ? V52_Multilingual::translate_article($pid) : 0;
        $new_id ? wp_send_json_success("Bozza inglese creata: #{$new_id}") : wp_send_json_error('Traduzione fallita.');
    }

    public static function page_v5_schema() {
        $lb_cached = get_transient('v5_schema_lb');
        $posts_with_schema = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>10,'meta_key'=>'_soser_faq_schema']);
        include SOSER_V4_DIR . 'admin/views/v5-schema.php';
    }

    public static function page_v5_local() {
        $memory = class_exists('V5_Memory') ? V5_Memory::get_all() : [];
        $silos  = class_exists('V5_Silo') ? V5_Silo::build_silo_map() : [];
        include SOSER_V4_DIR . 'admin/views/v5-local.php';
    }

    public static function page_v5_eeat() {
        include SOSER_V4_DIR . 'admin/views/v5-eeat.php';
    }

    public static function save_eeat() {
        check_admin_referer('soser_v51_eeat');
        if (!current_user_can('manage_options')) wp_die();
        if (class_exists('V5_EEAT')) V5_EEAT::save_settings($_POST);
        delete_transient('v5_schema_lb');
        wp_redirect(admin_url('admin.php?page=soser-v5-eeat&saved=1'));
        exit;
    }

    public static function ajax_v5_ai_overview() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $count = class_exists('V5_AI_Overview') ? V5_AI_Overview::bulk_add_answer_boxes((int)($_POST['limit']??5)) : 0;
        wp_send_json_success("Aggiunti {$count} riquadri risposta rapida.");
    }

    // ── V5 AJAX ────────────────────────────────────────────────────

    public static function ajax_v5_run_plan() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V5_Planner::invalidate();
        $plan = V5_Planner::plan();
        wp_send_json_success(['count'=>$plan['total'],'next'=>$plan['next_write']['keyword']??'Nessuna','plan'=>array_slice($plan['actions'],0,10)]);
    }

    public static function ajax_v5_refresh_post() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $pid = (int)($_POST['post_id']??0);
        $ok  = V5_Refresh::refresh($pid);
        $ok ? wp_send_json_success("Post #{$pid} aggiornato!") : wp_send_json_error('Aggiornamento fallito.');
    }

    public static function ajax_v5_optimize_ctr() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $pid  = (int)($_POST['post_id']??0);
        $meta = V5_CTR::optimize_meta($pid);
        $vars = V5_CTR::generate_title_variants($pid);
        wp_send_json_success(['meta'=>$meta,'title_variants'=>$vars]);
    }

    public static function ajax_v5_sync_memory() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $count = V5_Memory::sync();
        wp_send_json_success("Sincronizzati {$count} articoli in memoria.");
    }

    public static function ajax_v5_gen_video() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $pid  = (int)($_POST['post_id']??0);
        $data = V5_Video::generate_script($pid);
        $data ? wp_send_json_success($data) : wp_send_json_error('Generazione fallita.');
    }

    public static function ajax_gsc_disconnect() {
        check_ajax_referer('soser_v4_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        V4_GSC::disconnect();
        wp_send_json_success('Disconnesso da Google Search Console.');
    }
    // ── V5.8 + V5.9 Missing Methods ──────────────────────────────

    public static function page_v58_images() {
        $stats    = class_exists('V58_ImagePicker') ? V58_ImagePicker::stats() : [];
        $approved = class_exists('V58_ImagePicker') ? V58_ImagePicker::get_approved_images() : [];
        $services = class_exists('V54_ServiceFocus') ? V54_ServiceFocus::get_services() : [];
        include SOSER_V4_DIR . 'admin/views/v58-images.php';
    }

    public static function ajax_v58_auto_tag() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $count = class_exists('V58_ImagePicker') ? V58_ImagePicker::bulk_auto_tag(20) : 0;
        wp_send_json_success("Auto-taggiate {$count} immagini.");
    }

    public static function ajax_v58_test_match() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $kw  = sanitize_text_field($_POST['kw'] ?? '');
        $svc = sanitize_key($_POST['svc'] ?? '');
        $id  = class_exists('V58_ImagePicker') ? V58_ImagePicker::find_best($kw, $svc) : null;
        if (!$id) wp_send_json_error('Nessuna immagine trovata.');
        $url  = wp_get_attachment_image_url($id,'medium');
        $tags = V58_ImagePicker::get_image_tags($id);
        wp_send_json_success(['id'=>$id,'url'=>$url,'title'=>get_the_title($id),'tags'=>$tags]);
    }

    public static function page_v59_design() {
        $stats = ['redesigned'=>0,'pending'=>0];
        global $wpdb;
        $stats['redesigned'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_v59_redesigned'");
        $stats['pending']    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'") - $stats['redesigned'];
        $phone = get_option('v59_phone','392 882 4381');
        include SOSER_V4_DIR . 'admin/views/v59-design.php';
    }

    public static function ajax_v59_apply_design() {
        check_ajax_referer('soser_v4_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
        $limit  = min(10, max(1,(int)($_POST['limit']??5)));
        $result = class_exists('V59_ArticleDesign') ? V59_ArticleDesign::bulk_apply($limit) : 0;
        $redesigned = (int)get_option('v59_redesigned_count', 0) + (int)$result;
        update_option('v59_redesigned_count', $redesigned);
        global $wpdb;
        $total   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $pending = $total - (int)$wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_v59_redesigned'");
        wp_send_json_success(['count'=>$result,'remaining'=>max(0,$pending),'message'=>"Rinnovati {$result} articoli con il nuovo design."]);
    }


    public static function page_business_profile() {
        include SOSER_V4_DIR . 'admin/views/settings-profile.php';
    }

    public static function page_v510_ux() {
        include SOSER_V4_DIR . 'admin/views/v510-ux.php';
    }

    public static function page_v6_profile() {
        include SOSER_V4_DIR . 'admin/views/v6-profile.php';
    }

    public static function page_v7_analytics() {
        include SOSER_V4_DIR . 'admin/views/v7-analytics.php';
    }

    public static function page_v8_settings() {
        include SOSER_V4_DIR . 'admin/views/v8-settings.php';
    }

    public static function page_v9_serp() {
        include SOSER_V4_DIR . 'admin/views/v9-serp.php';
    }

    // ── V10 Unified Dashboard AJAX ────────────────────────────────

    public static function ajax_v10_dashboard(): void {
        check_ajax_referer('soser_v10_dashboard', 'nonce');

        $queue_stats = V4_Queue::stats();
        $log         = V4_Queue::get_log(5);

        // GSC data
        $gsc_ok  = class_exists('V4_GSC') && V4_GSC::is_connected();
        $gsc_ov  = [];
        $opps    = [];
        if ($gsc_ok) {
            $site   = V4_GSC::get_site_url();
            $raw    = $site ? V4_GSC::get_keyword_opportunities($site, 28) : [];
            $clicks = array_sum(array_column($raw, 'clicks'));
            $impr   = array_sum(array_column($raw, 'impressions'));
            $pos    = array_column($raw, 'position');
            $gsc_ov = [
                'clicks'      => $clicks,
                'impressions' => $impr,
                'avg_position'=> $pos ? round(array_sum($pos)/count($pos),1) : 0,
                'avg_ctr'     => $impr > 0 ? round($clicks/$impr*100,2) : 0,
            ];
            // Quick win opportunities
            foreach (array_slice($raw, 0, 50) as $r) {
                $p = $r['position'] ?? 99;
                if ($p >= 4 && $p <= 15 && ($r['impressions']??0) >= 30) {
                    $opps[] = [
                        'keyword'  => $r['keyword'],
                        'position' => round($p,1),
                        'impressions'=> (int)$r['impressions'],
                        'type'     => 'quick_win',
                    ];
                }
            }
            usort($opps, fn($a,$b) => $b['impressions'] - $a['impressions']);
            $opps = array_slice($opps, 0, 5);
        }

        // SERP alerts
        $alerts = class_exists('V7_SERPTracker') ? V7_SERPTracker::get_alerts(5) : [];

        // SERP summary
        $serp_kw = class_exists('V7_SERPTracker') ? V7_SERPTracker::get_all_with_positions() : [];
        $top3    = count(array_filter($serp_kw, fn($k) => ($k['last_position']??99) <= 3));
        $top10   = count(array_filter($serp_kw, fn($k) => ($k['last_position']??99) <= 10));

        // GA4
        $ga4_ov = class_exists('V7_GA4') && V7_GA4::is_connected()
            ? V7_GA4::get_overview(28) : [];

        // Refresh stats
        $refresh = class_exists('V7_RefreshEngine') ? V7_RefreshEngine::stats() : [];

        // Publish stats
        $pub_stats = class_exists('V8_PublishController') ? V8_PublishController::stats() : [];

        // Recent articles
        $recent = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 5,
            'meta_key'    => '_soser_focus_keyword',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
        $recent_data = array_map(fn($p) => [
            'id'      => $p->ID,
            'title'   => $p->post_title,
            'url'     => get_permalink($p->ID),
            'keyword' => get_post_meta($p->ID, '_soser_focus_keyword', true),
            'date'    => get_the_date('d/m/Y', $p),
            'score'   => (int) get_post_meta($p->ID, '_soser_content_score', true),
            'edit'    => get_edit_post_link($p->ID, 'raw'),
        ], $recent);

        // Next cron
        $next_cron = wp_next_scheduled(V4_Queue::CRON_DAILY);

        wp_send_json_success([
            'queue'       => $queue_stats,
            'log'         => array_map(function($j) {
                $kw  = is_array($j) ? ($j['keyword']??'')    : ($j->keyword??'');
                $st  = is_array($j) ? ($j['status']??'')     : ($j->status??'');
                $sp  = is_array($j) ? ($j['step']??'')       : ($j->step??'');
                $upd = is_array($j) ? ($j['updated_at']??'') : ($j->updated_at??'');
                return ['keyword'=>$kw?:'—','status'=>$st?:'pending','step'=>$sp?:'init','updated'=>$upd];
            }, $log),
            'gsc_ok'      => $gsc_ok,
            'gsc'         => $gsc_ov,
            'ga4_ok'      => !empty($ga4_ov),
            'ga4'         => $ga4_ov,
            'alerts'      => $alerts,
            'opportunities'=> $opps,
            'serp'        => ['top3'=>$top3,'top10'=>$top10,'total'=>count($serp_kw)],
            'refresh'     => $refresh,
            'pub_stats'   => $pub_stats,
            'recent'      => $recent_data,
            'next_cron'   => $next_cron ? date('d/m/Y H:i', $next_cron) : null,
            'timestamp'   => current_time('H:i:s'),
        ]);
    }

    public static function ajax_v10_add_keyword(): void {
        check_ajax_referer('soser_v10_dashboard', 'nonce');
        $kw = sanitize_text_field($_POST['keyword'] ?? '');
        if (!$kw) { wp_send_json_error('Keyword vuota'); return; }

        // Ensure table exists
        V4_Queue::create_tables();

        $job_id = V4_Queue::enqueue($kw, 'manual');
        V4_Queue::kick();

        if (!$job_id) {
            wp_send_json_error('Errore DB: impossibile aggiungere alla coda');
            return;
        }
        wp_send_json_success(['job_id' => $job_id, 'keyword' => $kw]);
    }

    public static function ajax_v10_process_next(): void {
        check_ajax_referer('soser_v10_dashboard', 'nonce');
        $result = V4_Queue::process_next();
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(['result' => $result]);
        }
    }


    public static function page_v10_vision() {
        include SOSER_V4_DIR . 'admin/views/v10-vision.php';
    }

    public static function page_v10_images() {
        include SOSER_V4_DIR . 'admin/views/v10-images.php';
    }

}
