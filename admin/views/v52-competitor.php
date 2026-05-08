<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🕵️ Competitor Gap Analysis</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Trova keyword che i competitor coprono e tu no.</p>
<div class="soser-card" style="max-width:600px;margin-bottom:20px">
  <h2>Aggiungi competitor</h2>
  <form id="v52-comp-form">
    <input type="url" id="v52-comp-url" class="large-text" placeholder="https://www.competitor.it">
    <button type="button" class="button button-primary" id="v52-analyze-btn" style="margin-top:8px">🔍 Analizza</button>
    <span id="v52-comp-result" style="font-size:12px;margin-top:8px;display:block"></span>
  </form>
</div>
<div id="v52-gap-results" style="display:none">
  <div class="soser-card">
    <h2>Gap trovati</h2>
    <table class="soser-table widefat striped" id="v52-gap-table">
      <thead><tr><th>#</th><th>Keyword</th><th>Volume</th><th>Intent</th><th>Perché</th><th>Azione</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>
<script>
jQuery('#v52-analyze-btn').on('click',function(){
  var url=jQuery('#v52-comp-url').val(),$r=jQuery('#v52-comp-result');
  if(!url)return;
  jQuery(this).prop('disabled',true).text('Analisi...');
  $r.css('color','#888').text('Analisi in corso...');
  jQuery.post(ajaxurl,{action:'soser_v52_analyze_comp',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>',url:url},function(r){
    if(r.success){
      $r.css('color','#00a32a').text('✅ Trovati '+r.data.count+' gap');
      var rows='';
      r.data.gaps.forEach(function(g,i){
        rows+='<tr><td>'+(i+1)+'</td><td><strong>'+g.keyword+'</strong></td><td>'+g.volume_est+'</td><td>'+g.intent+'</td><td style="font-size:11px;color:#666">'+g.why+'</td><td><form method="post" action="<?= admin_url("admin-post.php") ?>"><input type="hidden" name="action" value="soser_v4_generate"><input type="hidden" name="_wpnonce" value="<?= wp_create_nonce("soser_v4_generate") ?>"><input type="hidden" name="keyword" value="'+g.keyword+'"><button class="button button-small">Scrivi</button></form></td></tr>';
      });
      jQuery('#v52-gap-table tbody').html(rows);
      jQuery('#v52-gap-results').show();
    } else $r.css('color','#d63638').text('❌ '+r.data);
  }).always(()=>jQuery('#v52-analyze-btn').prop('disabled',false).text('🔍 Analizza'));
});
</script>
</div>
