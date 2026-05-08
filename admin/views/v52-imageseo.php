<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🖼️ Image SEO Automation</h1>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $stats['total']??0 ?></div><div class="stat-lbl">Immagini totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $stats['with_alt']??0 ?></div><div class="stat-lbl">Con alt text</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#d63638"><?= $stats['missing']??0 ?></div><div class="stat-lbl">Senza alt text</div></div>
</div>
<?php if (($stats['missing']??0) > 0): ?>
<div class="notice notice-warning"><p>⚠️ <strong><?= $stats['missing'] ?></strong> immagini senza alt text — Google non le capisce!</p></div>
<?php else: ?>
<div class="notice notice-success"><p>✅ Tutte le immagini hanno alt text!</p></div>
<?php endif; ?>
<div style="display:flex;gap:10px;margin-bottom:20px">
  <button class="button button-primary" id="v52-fix-alts">🔧 Correggi alt text (50 immagini)</button>
  <span id="v52-alts-result" style="font-size:12px;padding-top:6px"></span>
</div>
<?php if (!empty($missing)): ?>
<table class="soser-table widefat striped">
  <thead><tr><th>Immagine</th><th>Articolo collegato</th><th>Stato</th></tr></thead>
  <tbody>
  <?php foreach (array_slice($missing,0,20) as $img): ?>
  <tr>
    <td><?= wp_get_attachment_image($img['att_id'],'thumbnail') ?> <span style="font-size:11px"><?= esc_html($img['post_title']) ?></span></td>
    <td><?= $img['post_id'] ? '<a href="'.get_edit_post_link($img['post_id']).'">'.esc_html(get_the_title($img['post_id'])).'</a>' : '—' ?></td>
    <td><span class="soser-badge soser-badge-failed">❌ Alt mancante</span></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<script>
jQuery('#v52-fix-alts').on('click',function(){
  var $b=$(this),$r=jQuery('#v52-alts-result');
  $b.prop('disabled',true).text('...');
  jQuery.post(ajaxurl,{action:'soser_v52_fix_alts',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
    r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1500);
  }).always(()=>$b.prop('disabled',false).text('🔧 Correggi alt text'));
});
</script>
</div>
