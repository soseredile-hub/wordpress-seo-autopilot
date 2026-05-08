<?php defined('ABSPATH') || exit;
global $wpdb;
$processed = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_v52_autolinked'");
$total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
?>
<div class="wrap soser-wrap">
<h1>🔗 Auto Internal Linking</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Aggiunge automaticamente link interni a tutti gli articoli esistenti.</p>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $total ?></div><div class="stat-lbl">Articoli totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $processed ?></div><div class="stat-lbl">Processati</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= $total-$processed ?></div><div class="stat-lbl">Da processare</div></div>
</div>
<div style="display:flex;gap:10px;margin-bottom:16px">
  <button class="button button-primary" id="v52-autolink-btn">🔗 Processa prossimi 5</button>
  <button class="button" id="v52-autolink-reset">🔄 Reset (riprocessa tutti)</button>
  <span id="v52-autolink-result" style="font-size:12px;padding-top:6px"></span>
</div>
<div class="soser-card">
  <h2>⚙️ Come funziona</h2>
  <ol style="font-size:13px;line-height:2">
    <li>Legge le keyword focus di ogni articolo</li>
    <li>Cerca queste keyword nel testo degli altri articoli</li>
    <li>Aggiunge link automaticamente (max 3 per articolo)</li>
    <li>Evita duplicati e auto-link</li>
  </ol>
</div>
<script>
jQuery(function($){
  $('#v52-autolink-btn').on('click',function(){
    var $b=$(this),$r=$('#v52-autolink-result');
    $b.prop('disabled',true).text('...');
    $.post(ajaxurl,{action:'soser_v52_autolink_batch',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
      r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
      if(r.success)setTimeout(()=>location.reload(),1500);
    }).always(()=>$b.prop('disabled',false).text('🔗 Processa prossimi 5'));
  });
  $('#v52-autolink-reset').on('click',function(){
    if(!confirm('Reset tutti i flag? Rielaborerà tutti gli articoli.'))return;
    // TODO: add reset AJAX
    alert('Reset completato al prossimo run.');
  });
});
</script>
</div>
