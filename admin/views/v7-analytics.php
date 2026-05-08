<?php
defined('ABSPATH') || exit;

// Handle GA4 OAuth callback
if (isset($_GET['ga4_callback'], $_GET['code']) && class_exists('V7_GA4')) {
    if (V7_GA4::exchange_code(sanitize_text_field($_GET['code']))) {
        wp_redirect(admin_url('admin.php?page=soser-v7-analytics&done=ga4_connected')); exit;
    }
}

// Handle actions (nonce protected)
if (isset($_GET['action'], $_GET['_n']) && wp_verify_nonce($_GET['_n'], 'soser_v7_action')) {
    $act = $_GET['action'];
    if ($act === 'refresh_data'  && class_exists('V7_Analytics'))    { V7_Analytics::invalidate(); }
    if ($act === 'import_serp'   && class_exists('V7_SERPTracker'))  { $_SESSION['v7_count'] = V7_SERPTracker::auto_import(); }
    if ($act === 'build_refresh' && class_exists('V7_RefreshEngine')){ $_SESSION['v7_count'] = V7_RefreshEngine::build_queue(); }
    if ($act === 'run_refresh'   && class_exists('V7_RefreshEngine')){ $r = V7_RefreshEngine::run_batch(3); $_SESSION['v7_count'] = $r['refreshed']; }
    if ($act === 'serp_check'    && class_exists('V7_SERPTracker'))  { V7_SERPTracker::run_check(); }
    if ($act === 'ga4_disconnect'&& class_exists('V7_GA4'))          { V7_GA4::disconnect(); }
    wp_redirect(admin_url('admin.php?page=soser-v7-analytics&done=' . $act . '&count=' . ($_SESSION['v7_count'] ?? 0))); exit;
}

// Handle GA4 config save
if (isset($_POST['soser_v7_ga4_save']) && check_admin_referer('soser_v7_ga4')) {
    if (class_exists('V7_GA4')) {
        V7_GA4::save_config(
            sanitize_text_field($_POST['ga4_client_id'] ?? ''),
            sanitize_text_field($_POST['ga4_client_secret'] ?? ''),
            sanitize_text_field($_POST['ga4_property'] ?? '')
        );
    }
    if (class_exists('V7_GA4') && V7_GA4::is_configured()) {
        wp_redirect(V7_GA4::get_auth_url()); exit;
    }
    wp_redirect(admin_url('admin.php?page=soser-v7-analytics&done=ga4_saved')); exit;
}

// Handle email settings
if (isset($_POST['soser_v7_email_save']) && check_admin_referer('soser_v7_email')) {
    if (class_exists('V7_SERPTracker')) {
        V7_SERPTracker::save_email_settings(
            sanitize_email($_POST['alert_email'] ?? ''),
            !empty($_POST['email_enabled'])
        );
    }
    wp_redirect(admin_url('admin.php?page=soser-v7-analytics&done=email_saved')); exit;
}

// Load all data
$data    = class_exists('V7_Analytics') ? V7_Analytics::get() : [];
$ov      = $data['overview']      ?? [];
$top     = $data['top_articles']  ?? [];
$opps    = $data['opportunities'] ?? [];
$serp    = $data['serp_summary']  ?? [];
$rq      = $data['refresh_queue'] ?? [];
$recent  = $data['recent_posts']  ?? [];
$ga4_ov  = $data['ga4_overview']  ?? [];
$ga4_t   = $data['ga4_trend']     ?? [];
$ga4_src = $data['ga4_sources']   ?? [];
$gsc_ok  = $data['gsc_connected'] ?? false;
$ga4_ok  = $data['ga4_connected'] ?? false;
$color   = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
$dark    = class_exists('V6_Profile') ? (V6_Profile::get()['color_dark'] ?? '#1a1a1a') : '#1a1a1a';
$n       = wp_create_nonce('soser_v7_action');
$serp_kw = class_exists('V7_SERPTracker') ? V7_SERPTracker::get_all_with_positions() : [];
$alerts  = class_exists('V7_SERPTracker') ? V7_SERPTracker::get_alerts(10) : [];
$ecfg    = class_exists('V7_SERPTracker') ? V7_SERPTracker::get_email_settings() : ['email'=>'','enabled'=>false];
$queue   = class_exists('V7_RefreshEngine') ? V7_RefreshEngine::get_pending() : [];
$rlog    = class_exists('V7_RefreshEngine') ? V7_RefreshEngine::get_log(20) : [];

// Chart data
$csl = []; $csp = [];
foreach (array_slice($serp_kw, 0, 10) as $k) { $csl[] = mb_substr($k['keyword'] ?? '', 0, 25); $csp[] = $k['last_position'] ?? 0; }
$ctl = $ga4_t['labels'] ?? []; $css = $ga4_t['sessions'] ?? []; $csc = $ga4_t['conversions'] ?? [];
?>
<div class="wrap soser-wrap">
<style>
.v7-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px}
.v7-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px}
.v7-tab{padding:8px 15px;border:1.5px solid #e8e5e0;background:#f8f7f4;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;color:#555;text-decoration:none;transition:all .15s}
.v7-tab:hover{background:#fff}
.v7-tab.active{background:<?= esc_js($color) ?>;color:#fff;border-color:<?= esc_js($color) ?>}
.v7-sec{display:none}.v7-sec.active{display:block}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.st{background:#fff;border:1px solid #e8e5e0;border-radius:10px;padding:15px 14px;text-align:center}
.st .n{font-size:24px;font-weight:900;color:<?= esc_js($color) ?>;display:block;line-height:1.1;font-family:Georgia,serif}
.st .l{font-size:11px;color:#999;margin-top:3px;display:block}
.st.bl .n{color:#1a73e8}.st.gr .n{color:#16a34a}.st.rd .n{color:#dc2626}
.cd{background:#fff;border:1px solid #e8e5e0;border-radius:10px;padding:18px;margin-bottom:16px}
.cd h2{font-size:14px;font-weight:700;margin:0 0 12px}
.tb{width:100%;border-collapse:collapse;font-size:12px}
.tb th{text-align:right;padding:5px 9px;border-bottom:2px solid #f0f0f0;font-size:11px;font-weight:600;color:#aaa}
.tb td{padding:7px 9px;border-bottom:1px solid #f7f7f7;vertical-align:middle}
.tb tr:last-child td{border:none}.tb tr:hover td{background:#fafaf8}
.pb{display:inline-block;padding:2px 7px;border-radius:8px;font-size:11px;font-weight:700}
.p1{background:#d1fae5;color:#065f46}.p2{background:#dbeafe;color:#1e40af}
.p3{background:#fef9c3;color:#854d0e}.px{background:#fee2e2;color:#991b1b}
.ob{display:inline-block;padding:2px 7px;border-radius:8px;font-size:11px;font-weight:700}
.o1{background:#fef3c7;color:#92400e}.o2{background:#ede9fe;color:#5b21b6}.o3{background:#d1fae5;color:#065f46}
.bw{background:#fff8f0;border:1px solid #fed7aa;border-radius:7px;padding:10px 14px;margin-bottom:12px;font-size:13px}
.bg{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:10px 14px;margin-bottom:12px;font-size:13px}
.cw{position:relative;height:200px;margin-top:8px}
.ar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.btn{padding:8px 15px;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;display:inline-block;cursor:pointer}
.btn-p{background:<?= esc_js($color) ?>;color:#fff;border:none}
.btn-s{background:#fff;color:#555;border:1.5px solid #ddd}
.da{font-size:11px;color:#ccc}
@media(max-width:700px){.g4{grid-template-columns:repeat(2,1fr)}.g2,.g3{grid-template-columns:1fr}}
</style>

<?php
// Notice messages
$done = $_GET['done'] ?? '';
$cnt  = (int)($_GET['count'] ?? 0);
$nm   = ['refresh_data'=>'✅ Dati aggiornati!','import_serp'=>"✅ Importate {$cnt} keyword!",'build_refresh'=>"✅ {$cnt} articoli in coda!",'run_refresh'=>"✅ {$cnt} articoli refreshati!",'serp_check'=>'✅ SERP check completato!','email_saved'=>'✅ Email salvata!','ga4_connected'=>'✅ GA4 connesso!','ga4_saved'=>'ℹ️ Credenziali salvate. Completa il login Google.','ga4_disconnect'=>'ℹ️ GA4 disconnesso.'];
if (isset($nm[$done])) echo "<div class='notice notice-success is-dismissible'><p>" . esc_html($nm[$done]) . "</p></div>";
?>

<div class="v7-hdr">
  <h1 style="margin:0">📊 Analytics Dashboard</h1>
  <span style="font-size:11px;color:#aaa">⏰ <?= esc_html($data['generated_at'] ?? '—') ?> &nbsp;·&nbsp; <a href="<?= admin_url("admin.php?page=soser-v7-analytics&action=refresh_data&_n={$n}") ?>">🔄 Aggiorna</a></span>
</div>

<div class="v7-tabs">
  <a href="#" class="v7-tab active" data-tab="overview">📊 Overview</a>
  <a href="#" class="v7-tab" data-tab="serp">📡 SERP Tracker</a>
  <a href="#" class="v7-tab" data-tab="refresh">🤖 AI Refresh</a>
  <a href="#" class="v7-tab" data-tab="ga4">📈 GA4</a>
  <a href="#" class="v7-tab" data-tab="settings">⚙️ Impostazioni</a>
</div>

<!-- ══ OVERVIEW ══ -->
<div class="v7-sec active" id="tab-overview">
<?php if(!$gsc_ok):?><div class="bw">⚠️ GSC non connessa — <a href="<?=admin_url('admin.php?page=soser-v4-settings')?>" style="color:<?=esc_attr($color)?>;font-weight:700">Connetti →</a></div><?php endif;?>
<div class="g4">
  <div class="st"><span class="n"><?=esc_html($ov['published']??0)?></span><span class="l">📝 Articoli</span></div>
  <div class="st gr"><span class="n"><?=esc_html($serp['top3']??0)?></span><span class="l">🏆 Top 3</span></div>
  <div class="st bl"><span class="n"><?=esc_html($serp['top10']??0)?></span><span class="l">✅ Top 10</span></div>
  <div class="st"><span class="n"><?=esc_html($ov['tracked_kws']??0)?></span><span class="l">📡 Keyword</span></div>
</div>
<div class="g4">
  <div class="st bl"><span class="n"><?=number_format($ov['total_clicks']??0)?></span><span class="l">👆 Click GSC</span></div>
  <div class="st bl"><span class="n"><?=number_format($ov['total_impressions']??0)?></span><span class="l">👁️ Impressioni</span></div>
  <div class="st bl"><span class="n"><?=esc_html($ov['avg_ctr']??'0')?>%</span><span class="l">📊 CTR medio</span></div>
  <div class="st bl"><span class="n"><?=$ov['avg_position']>0?esc_html($ov['avg_position']):'—'?></span><span class="l">🎯 Pos. media</span></div>
</div>
<?php if(!empty($ga4_ov)):?>
<div class="g4">
  <div class="st"><span class="n"><?=number_format($ga4_ov['sessions']??0)?></span><span class="l">📲 Sessioni GA4</span></div>
  <div class="st gr"><span class="n"><?=number_format($ga4_ov['conversions']??0)?></span><span class="l">💰 Conversioni</span></div>
  <div class="st"><span class="n"><?=esc_html($ga4_ov['bounce_rate']??'—')?>%</span><span class="l">↩️ Bounce</span></div>
  <div class="st"><span class="n"><?=esc_html($ga4_ov['avg_session']??'—')?></span><span class="l">⏱️ Durata</span></div>
</div>
<?php endif;?>
<div class="g2">
  <div class="cd">
    <h2>📡 Posizioni keyword</h2>
    <?php if(empty($csl)):?><p style="color:#aaa;font-size:12px">Nessun dato — <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=import_serp&_n={$n}")?>">Importa →</a></p>
    <?php else:?><div class="cw"><canvas id="cSerp"></canvas></div><?php endif;?>
  </div>
  <div class="cd">
    <h2>📈 Trend Sessioni/Conversioni</h2>
    <?php if(!$ga4_ok):?><div class="bw" style="margin:0">Connetti <a href="#" onclick="st('ga4')">GA4</a> per vedere il trend.</div>
    <?php elseif(empty($ctl)):?><p style="color:#aaa;font-size:12px">Nessun dato.</p>
    <?php else:?><div class="cw"><canvas id="cTrend"></canvas></div><?php endif;?>
  </div>
</div>
<div class="g2">
  <div class="cd"><h2>🏆 Top Articoli</h2>
    <?php if(empty($top)):?><p style="color:#aaa;font-size:12px">Connetti GSC.</p>
    <?php else:?>
    <table class="tb"><tr><th>Articolo</th><th>Pos</th><th>Click</th><th>CTR</th></tr>
    <?php foreach(array_slice($top,0,8) as $a): $pos=$a['position']; $pc=$pos<=3?'p1':($pos<=10?'p2':($pos<=20?'p3':'px'));?>
    <tr><td><a href="<?=esc_url($a['url'])?>" target="_blank" style="color:#1a1a1a;font-weight:600;text-decoration:none"><?=esc_html(mb_substr($a['title'],0,38))?></a><?php if($a['keyword']):?><br><span class="da"><?=esc_html($a['keyword'])?></span><?php endif;?></td>
    <td><?=$pos>0?"<span class='pb {$pc}'>{$pos}</span>":'<span class="da">—</span>'?></td>
    <td style="font-weight:700;color:<?=esc_attr($color)?>"><?=$a['clicks']?:'—'?></td>
    <td><?=$a['ctr']?$a['ctr'].'%':'—'?></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
  <div class="cd"><h2>🎯 Opportunità</h2>
    <?php if(empty($opps)):?><p style="color:#aaa;font-size:12px">Connetti GSC.</p>
    <?php else:?>
    <table class="tb"><tr><th>Keyword</th><th>Pos</th><th>Tipo</th></tr>
    <?php foreach(array_slice($opps,0,8) as $o): $oc=['quick_win'=>'o1','low_ctr'=>'o2','new_article'=>'o3'][$o['opportunity']]??'o1';?>
    <tr><td><strong><?=esc_html($o['keyword'])?></strong><br><span class="da"><?=esc_html($o['action'])?></span></td>
    <td><span class="pb p3"><?=round($o['position'],1)?></span></td>
    <td><span class="ob <?=$oc?>"><?=esc_html($o['label'])?></span></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
</div>
</div>

<!-- ══ SERP ══ -->
<div class="v7-sec" id="tab-serp">
<div class="ar">
  <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=import_serp&_n={$n}")?>" class="btn btn-s">📥 Importa keyword</a>
  <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=serp_check&_n={$n}")?>" class="btn btn-s">🔄 Aggiorna ora</a>
</div>
<div class="g3">
  <div class="st gr"><span class="n"><?=esc_html($serp['top3']??0)?></span><span class="l">🏆 Top 3</span></div>
  <div class="st bl"><span class="n"><?=esc_html($serp['top10']??0)?></span><span class="l">✅ Top 10</span></div>
  <div class="st rd"><span class="n"><?=esc_html($serp['drops']??0)?></span><span class="l">⬇️ Drop</span></div>
</div>
<div class="g2">
  <div class="cd"><h2>📋 Keyword tracciate</h2>
    <?php if(empty($serp_kw)):?><p style="color:#aaa;font-size:12px">Premi "Importa keyword" per iniziare.</p>
    <?php else:?>
    <table class="tb"><tr><th>Keyword</th><th>Posizione</th><th>Trend</th><th>Aggiornato</th></tr>
    <?php foreach(array_slice($serp_kw,0,25) as $k): $pos=$k['last_position']??0; $ch=$k['last_change']??0; $pc=$pos<=3?'p1':($pos<=10?'p2':($pos<=20?'p3':'px'));?>
    <tr><td style="font-weight:600"><?=esc_html($k['keyword']??'')?></td>
    <td><?=$pos?"<span class='pb {$pc}'>{$pos}</span>":'<span class="da">—</span>'?></td>
    <td><?=$ch>0?"<span style='color:#16a34a;font-weight:700'>⬆ +{$ch}</span>":($ch<0?"<span style='color:#dc2626;font-weight:700'>⬇ {$ch}</span>":'<span class="da">—</span>')?></td>
    <td class="da"><?=esc_html($k['last_checked']??'—')?></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
  <div class="cd"><h2>🔔 Alert</h2>
    <?php if(empty($alerts)):?><p style="color:#aaa;font-size:12px">Nessun alert. Check automatico ogni 24h.</p>
    <?php else: foreach($alerts as $a): $cls=['drop'=>'#dc2626','rise'=>'#16a34a','top3'=>'#d97706'][$a['type']]??'#666';?>
    <div style="font-size:12px;color:<?=esc_attr($cls)?>;margin:4px 0"><?=esc_html($a['message'])?> <span class="da"><?=esc_html($a['date'])?></span></div>
    <?php endforeach; endif;?>
    <?php if(!empty($csl)):?><div class="cw" style="height:160px;margin-top:14px"><canvas id="cSerpD"></canvas></div><?php endif;?>
  </div>
</div>
</div>

<!-- ══ REFRESH ══ -->
<div class="v7-sec" id="tab-refresh">
<div class="ar">
  <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=build_refresh&_n={$n}")?>" class="btn btn-s">🔍 Analizza da refreshare</a>
  <?php if(!empty($rq['pending'])):?>
  <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=run_refresh&_n={$n}")?>" class="btn btn-p"
     onclick="return confirm('⚠️ AI aggiornerà 3 articoli.\nHai fatto un Backup?\n\nHostinger → hPanel → Database → Backup\n\nOK = confermo backup fatto')">
    🤖 Avvia AI Refresh (3 articoli)
  </a>
  <?php endif;?>
</div>
<div class="g4">
  <div class="st"><span class="n"><?=esc_html($rq['pending']??0)?></span><span class="l">⏳ In attesa</span></div>
  <div class="st gr"><span class="n"><?=esc_html($rq['done']??0)?></span><span class="l">✅ Completati</span></div>
  <div class="st rd"><span class="n"><?=esc_html($rq['failed']??0)?></span><span class="l">❌ Falliti</span></div>
  <div class="st bl"><span class="n"><?=esc_html($rq['total']??0)?></span><span class="l">📋 Totale</span></div>
</div>
<div class="g2">
  <div class="cd"><h2>📋 Coda di refresh</h2>
    <?php if(empty($queue)):?><p style="color:#aaa;font-size:12px">Coda vuota. Premi "Analizza" per costruire la coda.</p>
    <?php else:?>
    <table class="tb"><tr><th>Articolo</th><th>Score</th><th>Pos</th><th>Motivo</th></tr>
    <?php foreach(array_slice($queue,0,15) as $j):?>
    <tr><td style="font-weight:600"><?=esc_html(mb_substr($j['title'],0,35))?><br><span class="da"><?=esc_html($j['keyword'])?></span></td>
    <td style="font-weight:700;color:<?=esc_attr($color)?>"><?=esc_html($j['score'])?></td>
    <td><?=$j['position']>0?"<span class='pb p3'>".round($j['position'],1)."</span>":'—'?></td>
    <td class="da"><?=esc_html($j['reason'])?></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
  <div class="cd"><h2>📜 Log refresh</h2>
    <?php if(empty($rlog)):?><p style="color:#aaa;font-size:12px">Nessun refresh eseguito.</p>
    <?php else:?>
    <table class="tb"><tr><th>Keyword</th><th>Stato</th><th>Data</th></tr>
    <?php foreach($rlog as $l):?>
    <tr><td style="font-weight:600"><?=esc_html($l['keyword'])?></td>
    <td><?=$l['status']==='done'?"<span style='color:#16a34a;font-weight:700'>✅</span>":"<span style='color:#dc2626;font-weight:700'>❌</span>"?></td>
    <td class="da"><?=esc_html($l['date'])?></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
</div>
</div>

<!-- ══ GA4 ══ -->
<div class="v7-sec" id="tab-ga4">
<?php if(!$ga4_ok):?>
<div class="cd" style="max-width:580px">
  <h2>📈 Connetti Google Analytics 4</h2>
  <p style="font-size:13px;color:#555;line-height:1.8;margin-bottom:14px">Visualizza sessioni, conversioni, bounce rate e canali di acquisizione.</p>
  <ol style="font-size:13px;line-height:2.2;color:#444;margin-bottom:16px">
    <li>Vai su <a href="https://console.cloud.google.com" target="_blank" style="color:<?=esc_attr($color)?>">Google Cloud Console</a></li>
    <li>Crea progetto → abilita <strong>Google Analytics Data API v1</strong></li>
    <li>Credenziali → OAuth2 → aggiungi Authorized redirect URI:<br>
      <code style="background:#f5f5f5;padding:2px 8px;border-radius:4px;font-size:11px"><?=esc_html(class_exists('V7_GA4')?V7_GA4::get_redirect_uri():'')?></code></li>
    <li>Copia Client ID, Client Secret e inserisci qui sotto</li>
    <li>Trova Property ID: <strong>Admin GA4 → Impostazioni proprietà → ID</strong></li>
  </ol>
  <form method="post">
    <?php wp_nonce_field('soser_v7_ga4');?>
    <input type="hidden" name="soser_v7_ga4_save" value="1">
    <table class="form-table" style="margin:0">
      <tr><th>Client ID</th><td><input type="text" name="ga4_client_id" class="regular-text" placeholder="xxxxx.apps.googleusercontent.com"></td></tr>
      <tr><th>Client Secret</th><td><input type="text" name="ga4_client_secret" class="regular-text"></td></tr>
      <tr><th>Property ID</th><td><input type="text" name="ga4_property" class="regular-text" placeholder="123456789"><p class="description">Solo numeri, senza "properties/"</p></td></tr>
    </table>
    <button type="submit" class="btn btn-p" style="margin-top:14px">Salva e connetti con Google →</button>
  </form>
</div>
<?php else:?>
<div class="bg">✅ Google Analytics 4 connesso.
  <a href="<?=admin_url("admin.php?page=soser-v7-analytics&action=ga4_disconnect&_n={$n}")?>" style="color:#666;font-size:12px;margin-right:8px" onclick="return confirm('Disconnettere GA4?')">Disconnetti</a>
</div>
<div class="g4">
  <div class="st"><span class="n"><?=number_format($ga4_ov['sessions']??0)?></span><span class="l">📲 Sessioni</span></div>
  <div class="st"><span class="n"><?=number_format($ga4_ov['users']??0)?></span><span class="l">👤 Utenti</span></div>
  <div class="st gr"><span class="n"><?=number_format($ga4_ov['conversions']??0)?></span><span class="l">💰 Conversioni</span></div>
  <div class="st"><span class="n"><?=esc_html($ga4_ov['bounce_rate']??'—')?>%</span><span class="l">↩️ Bounce</span></div>
</div>
<div class="g2">
  <div class="cd"><h2>📈 Sessioni & Conversioni — 28 giorni</h2>
    <?php if(empty($ctl)):?><p style="color:#aaa;font-size:12px">Nessun dato.</p>
    <?php else:?><div class="cw"><canvas id="cTrendFull"></canvas></div><?php endif;?>
  </div>
  <div class="cd"><h2>🎯 Conversioni per canale</h2>
    <?php if(empty($ga4_src)):?><p style="color:#aaa;font-size:12px">Nessun dato.</p>
    <?php else:?>
    <table class="tb"><tr><th>Canale</th><th>Sessioni</th><th>Conversioni</th></tr>
    <?php foreach($ga4_src as $s):?>
    <tr><td style="font-weight:600"><?=esc_html($s['source'])?></td>
    <td><?=number_format($s['sessions'])?></td>
    <td style="font-weight:700;color:<?=esc_attr($color)?>"><?=number_format($s['conversions'])?></td></tr>
    <?php endforeach;?></table><?php endif;?>
  </div>
</div>
<?php endif;?>
</div>

<!-- ══ SETTINGS ══ -->
<div class="v7-sec" id="tab-settings">
<div class="g2">
  <div class="cd"><h2>📧 Email Alert (drop posizione)</h2>
    <form method="post">
      <?php wp_nonce_field('soser_v7_email');?>
      <input type="hidden" name="soser_v7_email_save" value="1">
      <table class="form-table" style="margin:0">
        <tr><th>Email</th><td><input type="email" name="alert_email" value="<?=esc_attr($ecfg['email'])?>" class="regular-text"></td></tr>
        <tr><th>Abilita</th><td><label><input type="checkbox" name="email_enabled" value="1" <?=checked($ecfg['enabled'],true,false)?>>&nbsp;Invia email quando keyword cala 5+ posizioni</label></td></tr>
      </table>
      <button type="submit" class="button button-primary" style="margin-top:12px">💾 Salva</button>
    </form>
  </div>
  <div class="cd"><h2>⚙️ Stato automazioni</h2>
    <table class="tb">
      <tr><td>📡 SERP check giornaliero</td><td><?=wp_next_scheduled('soser_v7_serp_cron')?"<span style='color:#16a34a;font-weight:700'>✅ Attivo</span>":"<span style='color:#dc2626'>❌ Off</span>"?></td></tr>
      <tr><td>🤖 AI Refresh automatico</td><td><?=wp_next_scheduled('soser_v7_refresh_cron')?"<span style='color:#16a34a;font-weight:700'>✅ Attivo</span>":"<span style='color:#dc2626'>❌ Off</span>"?></td></tr>
      <tr><td>📊 GSC connessa</td><td><?=$gsc_ok?"<span style='color:#16a34a;font-weight:700'>✅ Sì</span>":"<span style='color:#dc2626'>❌ No</span>"?></td></tr>
      <tr><td>📈 GA4 connessa</td><td><?=$ga4_ok?"<span style='color:#16a34a;font-weight:700'>✅ Sì</span>":"<span style='color:#888'>— No</span>"?></td></tr>
    </table>
    <p style="font-size:11px;color:#aaa;margin-top:10px">Cron Hostinger:<br><code>*/5 * * * * curl -s "<?=esc_html(site_url('/wp-cron.php?doing_wp_cron'))?>"</code></p>
  </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const C = '<?=esc_js($color)?>';
let ci = false;
function st(id){ document.querySelector('[data-tab="'+id+'"]')?.click(); }
document.querySelectorAll('.v7-tab').forEach(t => t.addEventListener('click', e => {
  e.preventDefault();
  document.querySelectorAll('.v7-tab').forEach(x=>x.classList.remove('active'));
  document.querySelectorAll('.v7-sec').forEach(x=>x.classList.remove('active'));
  t.classList.add('active');
  document.getElementById('tab-'+t.dataset.tab)?.classList.add('active');
  if(!ci){ ci=true; initC(); }
}));
function mkBar(id,labels,data){
  const el=document.getElementById(id); if(!el)return;
  const colors=data.map(v=>v<=3?'#16a34a':v<=10?'#2563eb':v<=20?'#d97706':'#dc2626');
  new Chart(el,{type:'bar',data:{labels,datasets:[{label:'Posizione',data,backgroundColor:colors,borderRadius:4}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
      scales:{x:{reverse:true,min:0,max:30,ticks:{font:{size:10}}},y:{ticks:{font:{size:10}}}}}});
}
function mkLine(id,labels,d1,d2){
  const el=document.getElementById(id); if(!el)return;
  new Chart(el,{type:'line',data:{labels,datasets:[
    {label:'Sessioni',data:d1,borderColor:'#2563eb',backgroundColor:'#2563eb15',fill:true,tension:0.4,pointRadius:2},
    {label:'Conversioni',data:d2,borderColor:C,backgroundColor:C+'15',fill:true,tension:0.4,pointRadius:2}
  ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{ticks:{font:{size:10}}}}}});
}
function initC(){
  const sl=<?=wp_json_encode($csl)?>, sp=<?=wp_json_encode($csp)?>;
  const tl=<?=wp_json_encode($ctl)?>, ts=<?=wp_json_encode($css)?>, tc=<?=wp_json_encode($csc)?>;
  mkBar('cSerp',sl,sp); mkBar('cSerpD',sl,sp);
  mkLine('cTrend',tl,ts,tc); mkLine('cTrendFull',tl,ts,tc);
}
window.addEventListener('load',()=>{ if(!ci){ ci=true; initC(); } });
</script>
</div>
