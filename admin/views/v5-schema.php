<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🏆 Rich Schema Markup</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Schema automatici attivi su tutto il sito. Aumentano il CTR anche dal 3° posto.</p>

<div class="soser-grid-2">
  <div class="soser-card">
    <h2>✅ Schema attivi</h2>
    <ul style="font-size:13px;line-height:2.2">
      <li>🏢 <strong>LocalBusiness</strong> — su tutte le pagine</li>
      <li>📰 <strong>Article + Author</strong> — su ogni post</li>
      <li>❓ <strong>FAQPage</strong> — post con domande</li>
      <li>📋 <strong>HowTo</strong> — guide passo-passo</li>
      <li>💰 <strong>Service + Price</strong> — articoli con prezzi</li>
      <li>🍞 <strong>BreadcrumbList</strong> — navigazione</li>
    </ul>
    <button class="button button-primary" id="v5-refresh-schema" style="margin-top:10px">🔄 Rigenera LocalBusiness Schema</button>
    <span id="v5-schema-result" style="font-size:12px;margin-right:8px"></span>
  </div>
  <div class="soser-card">
    <h2>📊 Copertura</h2>
    <p style="font-size:13px"><strong><?= count($posts_with_schema) ?></strong> articoli con schema FAQ/Service</p>
    <p style="font-size:12px;color:#888;margin-top:8px">Schema LocalBusiness: <?= $lb_cached ? '✅ Cached' : '⏳ Verrà generato al prossimo caricamento' ?></p>
    <hr style="margin:14px 0">
    <h3 style="font-size:13px">🔍 Test Schema Google</h3>
    <p style="font-size:12px;color:#555">Verifica i tuoi schema su:</p>
    <a href="https://search.google.com/test/rich-results" target="_blank" class="button button-secondary">Apri Rich Results Test →</a>
  </div>
</div>

<div class="soser-card">
  <h2>💡 AI Overview — Risposta Rapida</h2>
  <p style="font-size:13px;color:#555;margin-bottom:12px">Aggiunge un riquadro "risposta rapida" all'inizio di ogni articolo per aumentare le possibilità di apparire nel Google AI Overview.</p>
  <button class="button button-primary" id="v5-ai-overview-btn">⚡ Aggiungi risposta rapida ai post</button>
  <span id="v5-overview-result" style="font-size:12px;margin-right:8px"></span>
</div>

<script>
jQuery(function($){
  $('#v5-refresh-schema').on('click',function(){
    jQuery.post(ajaxurl,{action:'soser_v5_run_plan',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(){
      $('#v5-schema-result').css('color','#00a32a').text('✅ Schema rigenerato');
    });
  });
  $('#v5-ai-overview-btn').on('click',function(){
    var $b=$(this);$b.prop('disabled',true).text('...');
    jQuery.post(ajaxurl,{action:'soser_v5_ai_overview',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>',limit:5},function(r){
      r.success?$('#v5-overview-result').css('color','#00a32a').text('✅ '+r.data):$('#v5-overview-result').css('color','#d63638').text('❌ '+r.data);
    }).always(()=>$b.prop('disabled',false).text('⚡ Aggiungi risposta rapida ai post'));
  });
});
</script>
</div>
