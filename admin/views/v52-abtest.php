<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🧪 A/B Title Testing</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Testa due varianti di titolo per 7 giorni, vince quello con CTR migliore.</p>
<?php if (!empty($tests)): ?>
<div class="soser-card" style="margin-bottom:16px">
  <h2>Test attivi</h2>
  <table class="soser-table widefat">
    <thead><tr><th>Articolo</th><th>Titolo A</th><th>Titolo B</th><th>Iniziato</th><th>Giorni rimasti</th><th>Vincitore</th></tr></thead>
    <tbody>
    <?php foreach ($tests as $t): ?>
    <tr>
      <td><a href="<?= get_edit_post_link($t['post_id']) ?>">#<?= $t['post_id'] ?></a></td>
      <td style="font-size:12px"><?= esc_html($t['title_a']) ?></td>
      <td style="font-size:12px"><?= esc_html($t['title_b']) ?></td>
      <td style="font-size:12px"><?= esc_html($t['started']) ?></td>
      <td><?= $t['days_left'] ?> giorni</td>
      <td><?= $t['winner'] ? '<span style="color:#00a32a;font-weight:600">'.esc_html($t['winner']).'</span>' : '⏳ In corso' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<div class="soser-card">
  <h2>Avvia nuovo test</h2>
  <select id="v52-ab-post" class="regular-text">
    <option value="">-- Seleziona articolo --</option>
    <?php foreach ($posts as $p): ?>
    <option value="<?= $p->ID ?>"><?= esc_html(mb_substr($p->post_title,0,50)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="button button-primary" id="v52-ab-start" style="margin-right:10px">🧪 Avvia A/B Test</button>
  <span id="v52-ab-result" style="font-size:12px"></span>
</div>
<script>
jQuery('#v52-ab-start').on('click',function(){
  var pid=jQuery('#v52-ab-post').val();
  if(!pid)return alert('Seleziona un articolo');
  jQuery(this).prop('disabled',true).text('...');
  jQuery.post(ajaxurl,{action:'soser_v52_start_ab',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>',post_id:pid},function(r){
    r.success?jQuery('#v52-ab-result').css('color','#00a32a').text('✅ '+r.data):jQuery('#v52-ab-result').css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1500);
  }).always(()=>jQuery('#v52-ab-start').prop('disabled',false).text('🧪 Avvia A/B Test'));
});
</script>
</div>
