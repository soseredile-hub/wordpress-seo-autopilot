<?php
/**
 * Plugin Name: SOSER SEO Autopilot V5.0 — AI Brain
 * Version:     5.10.0
 * Author:      SOSER
 */

defined('ABSPATH') || exit;

define('SOSER_V4_DIR', plugin_dir_path(__FILE__));
define('SOSER_V4_URL', plugin_dir_url(__FILE__));
define('SOSER_V4_VER', '5.10.0');
define('SOSER_V4_OPT', 'soser_v4_options');

// ════════════════════════════════════════════════════════════════
// FRONTEND (Visitatori) — caricare MINIMO assoluto
// Solo: Options + Queue init + Schema in <head>
// ZERO impatto sulle performance del sito
// ════════════════════════════════════════════════════════════════
require_once SOSER_V4_DIR . 'includes/class-v4-options.php';
require_once SOSER_V4_DIR . 'includes/class-v6-context.php';
require_once SOSER_V4_DIR . 'includes/class-v6-context.php';
require_once SOSER_V4_DIR . 'includes/class-v4-queue.php'; // Solo per Cron hook

// V5.1: Init lightweight frontend classes
add_action('after_setup_theme', function () {
    if (class_exists('V5_Rich_Schema')) V5_Rich_Schema::init();
    if (class_exists('V5_EEAT'))        V5_EEAT::init();
    if (class_exists('V5_Silo'))        V5_Silo::init();
    if (class_exists('V52_ABTest'))    V52_ABTest::init();
    if (class_exists('V52_ImageSEO'))  V52_ImageSEO::init();
    if (class_exists('V52_PerformanceMonitor')) V52_PerformanceMonitor::init();
    if (class_exists('V57_RedirectManager'))  V57_RedirectManager::init();
    if (class_exists('V57_KnowledgeGraph'))   V57_KnowledgeGraph::init();
    if (class_exists('V58_ImagePicker'))    V58_ImagePicker::init();
    if (class_exists('V57_SERPTracker'))      V57_SERPTracker::init();
});

// Schema FAQ in <head> (solo per post singoli — 1 query DB leggera)
// V6: Dynamic CSS brand variables (changes with Business Profile)
add_action('wp_head',    function() { if(class_exists('V6_Branding')) V6_Branding::inject_frontend_vars(); }, 4);
add_action('admin_head', function() { if(class_exists('V6_Branding')) V6_Branding::inject_admin_vars(); }, 4);

// V6: Save Business Profile
add_action('admin_post_soser_v6_profile_save', function() {
    check_admin_referer('soser_v6_profile_save');
    if (class_exists('V6_Profile')) V6_Profile::save($_POST['v6'] ?? []);
    wp_redirect(admin_url('admin.php?page=soser-v6-profile&saved=1'));
    exit;
});

add_action('wp_head', function () {
    if (!is_singular('post')) return;
    $schema = get_post_meta(get_the_ID(), '_soser_faq_schema', true);
    if ($schema) echo $schema . "\n";
}, 5);

// Behavioral tracker (JS leggero, invia dati solo alla chiusura pagina)
add_action('wp_footer', function () {
    if (!is_singular('post')) return;
    $nonce = wp_create_nonce('v5_track');
    $pid   = get_the_ID();
    echo "<script>
(function(){
    var s=0,t=Date.now(),e=0;
    document.addEventListener('scroll',function(){s=Math.round(window.scrollY/(document.body.scrollHeight-window.innerHeight||1)*100);},{passive:true});
    window.addEventListener('beforeunload',function(){
        var td=Math.round((Date.now()-t)/1000);
        if(td<3||!navigator.sendBeacon)return;
        navigator.sendBeacon('".esc_url(admin_url('admin-ajax.php'))."',new URLSearchParams({action:'v5_track',nonce:'{$nonce}',pid:'{$pid}',scroll:s,time:td}));
    });
})();
</script>\n";
});

// Save behavioral signal (lightweight AJAX, no DB reads)
add_action('wp_ajax_nopriv_v5_track', function () {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'v5_track')) return;
    $pid  = (int)($_POST['pid']   ?? 0);
    $s    = min(100, max(0, (int)($_POST['scroll'] ?? 0)));
    $t    = min(3600, max(0, (int)($_POST['time']  ?? 0)));
    if (!$pid || $t < 3) return;
    // Lightweight rolling average stored in postmeta
    $d = get_post_meta($pid, '_v5_beh', true) ?: ['v'=>0,'s'=>0,'t'=>0];
    $n = $d['v'] + 1;
    $d = ['v'=>$n, 's'=>round(($d['s']*($n-1)+$s)/$n,1), 't'=>round(($d['t']*($n-1)+$t)/$n,1)];
    update_post_meta($pid, '_v5_beh', $d);
    wp_send_json_success();
});

// ════════════════════════════════════════════════════════════════
// ADMIN + CRON — caricare tutto solo quando necessario
// I visitatori NON eseguono mai questo codice
// ════════════════════════════════════════════════════════════════
// V5.1 frontend-safe classes (lightweight hooks only)
require_once SOSER_V4_DIR . 'includes/v5/class-v5-rich-schema.php';
require_once SOSER_V4_DIR . 'includes/v5/class-v5-eeat.php';
require_once SOSER_V4_DIR . 'includes/v5/class-v5-silo-local.php';

if (is_admin() || wp_doing_cron()) {

    // Core
    require_once SOSER_V4_DIR . 'includes/class-v4-content-scanner.php';
    require_once SOSER_V4_DIR . 'includes/class-v4-business-context.php';
// V7: Analytics + SERP + Refresh
require_once SOSER_V4_DIR . 'includes/v7/class-v7-analytics.php';
require_once SOSER_V4_DIR . 'includes/v7/class-v7-ga4.php';
// V10: AI Vision
require_once SOSER_V4_DIR . 'includes/v10/class-v10-ai-vision.php';

// V10: Image Processor
require_once SOSER_V4_DIR . 'includes/v10/class-v10-image-processor.php';

// V9: SERP Analyzer
require_once SOSER_V4_DIR . 'includes/v9/class-v9-serp-analyzer.php';

// V8: Image Engine + SEO Autofill + Publish Controller
require_once SOSER_V4_DIR . 'includes/v8/class-v8-image-engine.php';
require_once SOSER_V4_DIR . 'includes/v8/class-v8-seo-autofill.php';
require_once SOSER_V4_DIR . 'includes/v8/class-v8-publish-controller.php';
require_once SOSER_V4_DIR . 'includes/v7/class-v7-serp-tracker.php';
require_once SOSER_V4_DIR . 'includes/v7/class-v7-refresh-engine.php';

// V6: Universal engine
require_once SOSER_V4_DIR . 'includes/v6/class-v6-profile.php';
require_once SOSER_V4_DIR . 'includes/v6/class-v6-language.php';
require_once SOSER_V4_DIR . 'includes/v6/class-v6-branding.php';
    require_once SOSER_V4_DIR . 'includes/class-v4-keyword-intel.php';
    require_once SOSER_V4_DIR . 'includes/class-v4-generator.php';
    require_once SOSER_V4_DIR . 'includes/class-v4-image.php';
    require_once SOSER_V4_DIR . 'includes/class-v4-gsc.php';

    // V5 Features
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-enterprise.php'; // Cost tracker first
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-memory.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-semantic.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-planner.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-refresh-ctr.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-growth.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-agents.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v5-ai-overview.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v52-cannibalization.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v52-autolinker.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v52-ab-competitor.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v52-calendar-imageseo.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v52-extra-features.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v53-bulk-refresh.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v54-service-focus.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v55-quick-win.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v55-question-harvester.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v55-seasonal.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v57-snippet-depth.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v57-entity-multiloc.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v57-advanced.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v58-image-picker.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v59-design.php';
    require_once SOSER_V4_DIR . 'includes/v5/class-v510-ux.php';

    // Admin panel
    require_once SOSER_V4_DIR . 'admin/class-v4-admin.php';

    add_action('plugins_loaded', function () {
        V4_Queue::init();
        V4_Admin::init();
        if (class_exists('V53_BulkRefresh')) V53_BulkRefresh::init();
        if (class_exists('V54_ServiceFocus')) V54_ServiceFocus::rebuild_seeds();
    if (class_exists('V57_RedirectManager')) {} // init via plugins_loaded
    if (class_exists('V57_KnowledgeGraph'))  V57_KnowledgeGraph::invalidate();
    });
}

// ════════════════════════════════════════════════════════════════
// ACTIVATION / DEACTIVATION
// ════════════════════════════════════════════════════════════════
register_activation_hook(__FILE__, function () {
    V4_Queue::create_tables();
    V4_Queue::schedule();
    if (class_exists('V5_Memory'))      { V5_Memory::create_table(); V5_Memory::sync(); }
    if (class_exists('V53_BulkRefresh')) V53_BulkRefresh::create_table();
});

register_deactivation_hook(__FILE__, function () {
    V4_Queue::unschedule();
});
