<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>📡 SERP Tracker — Posizioni keyword nel tempo</h1>
<div style="display:flex;gap:10px;margin-bottom:20px">
  <button class="button button-primary" id="v57-serp-run">🔄 Aggiorna posizioni ora</button>
  <span id="v57-serp-result" style="font-size:12px;padding-top:6px"></span>
</div>
<?php if (empty($positions)): ?>
<div class="soser-info-bar">⏳ Collega <a href="<?= admin_url('admin.php?page=soser-v4-gsc') ?>">Google Search Console</a> per tracciare le posizioni.</div>
<?php else: ?>
<div class="soser-stats" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= count($positions) ?></div><div class="stat-lbl">Keyword tracciate</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= count(array_filter($positions,fn($p)=>$p['position']<=3)) ?></div><div class="stat-lbl">Top 3 🥇</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= count(array_filter($positions,fn($p)=>$p['position']<=10)) ?></div><div class="stat-lbl">Top 10</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= count(array_filter($positions,fn($p)=>$p['change']>0)) ?></div><div class="stat-lbl">Migliorate 📈</div></div>
</div>
<table class="soser-table widefat striped">
  <thead><tr><th>Keyword</th><th>Posizione</th><th>Variazione</th><th>Impressioni</th><th>Click</th><th>CTR</th></tr></thead>
  <tbody>
  <?php foreach (array_slice($positions,0,50) as $p): ?>
  <tr>
    <td><strong><?= esc_html($p['keyword']) ?></strong></td>
    <td>
      <span style="background:<?= $p['position']<=3?'#00a32a':($p['position']<=10?'#2271b1':($p['position']<=20?'#f0a500':'#d63638')) ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700">
        #<?= $p['position'] ?>
      </span>
    </td>
    <td>
      <?php if ($p['change'] != 0): ?>
      <span style="color:<?= $p['change']>0?'#00a32a':'#d63638' ?>;font-weight:700">
        <?= $p['trend'] ?> <?= $p['change']>0?'+':'' ?><?= $p['change'] ?>
      </span>
      <?php else: echo '➡️ Stabile'; endif; ?>
    </td>
    <td><?= number_format($p['impressions']) ?></td>
    <td><?= number_format($p['clicks']) ?></td>
    <td><?= $p['ctr'] ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<script>
jQuery('#v57-serp-run').on('click',function(){
  var $b=$(this),$r=jQuery('#v57-serp-result');
  $b.prop('disabled',true).text('...');
  jQuery.post(ajaxurl,{action:'soser_v57_serp_run',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
    r.success?$r.css('color','#00a32a').text('✅ '+r.data.message):$r.css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1500);
  }).always(()=>$b.prop('disabled',false).text('🔄 Aggiorna posizioni ora'));
});
</script>
</div>
