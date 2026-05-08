<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>⚔️ Keyword Cannibalization</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Articoli che si fanno concorrenza sulla stessa keyword — riducono entrambi il ranking.</p>
<div style="margin-bottom:16px">
  <button class="button button-primary" id="v52-scan-cannibal">🔍 Riscansiona</button>
  <span id="v52-cannibal-result" style="font-size:12px;margin-right:8px"></span>
</div>
<?php if (empty($conflicts)): ?>
<div class="soser-info-bar" style="background:#e8f9e8;border-color:#00a32a">✅ Nessuna cannibalizzazione rilevata! Ottimo lavoro.</div>
<?php else: ?>
<div class="notice notice-warning"><p>⚠️ Trovati <strong><?= count($conflicts) ?></strong> conflitti da risolvere.</p></div>
<?php foreach ($conflicts as $c): ?>
<div class="soser-card" style="margin-bottom:14px;border-right:4px solid <?= $c['severity']==='high'?'#d63638':'#f0a500' ?>">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <span class="soser-badge soser-badge-<?= $c['severity']==='high'?'failed':'pending' ?>"><?= $c['severity']==='high'?'🔴 Alto':'🟠 Medio' ?></span>
      <strong style="margin-right:10px;font-size:13px"><?= esc_html($c['keyword']) ?></strong>
    </div>
  </div>
  <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach ($c['posts'] as $p): ?>
    <div style="background:#f6f7f7;border:1px solid #e0e0e0;border-radius:4px;padding:6px 10px;font-size:12px">
      <a href="<?= get_edit_post_link($p['post_id']) ?>"><?= esc_html(mb_substr($p['title'],0,40)) ?></a>
      <span style="color:#888;margin-right:6px">(<?= esc_html($p['status']) ?>)</span>
    </div>
    <?php endforeach; ?>
  </div>
  <p style="font-size:12px;color:#555;margin-top:8px">💡 <?= esc_html($c['suggestion']) ?></p>
</div>
<?php endforeach; ?>
<?php endif; ?>
<script>
jQuery('#v52-scan-cannibal').on('click',function(){
  var $b=$(this),$r=jQuery('#v52-cannibal-result');
  $b.prop('disabled',true).text('...');
  jQuery.post(ajaxurl,{action:'soser_v52_scan_cannibal',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
    r.success?$r.css('color','#00a32a').text('✅ '+r.data.message):$r.css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1000);
  }).always(()=>$b.prop('disabled',false).text('🔍 Riscansiona'));
});
</script>
</div>
