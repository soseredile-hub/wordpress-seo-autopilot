<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🎨 Article Design System — V5.9</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Trasforma tutti i tuoi articoli in <strong>landing page ad alta conversione</strong> con design moderno, CTA, trust sections, WhatsApp button e progress bar. Applicabile su tutti gli articoli vecchi e nuovi.
</p>

<div class="soser-stats" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $stats['redesigned'] ?></div><div class="stat-lbl">✅ Già rinnovati</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= $stats['pending'] ?></div><div class="stat-lbl">⏳ Da rinnovare</div></div>
</div>

<!-- PHONE SETTING -->
<div class="soser-card" style="max-width:500px;margin-bottom:20px">
  <h2>📞 Numero di telefono (per CTA)</h2>
  <form method="post">
    <?php wp_nonce_field('v59_phone') ?>
    <input type="text" name="v59_phone" value="<?= esc_attr($phone) ?>" class="regular-text" placeholder="392 882 4381">
    <?php if(isset($_POST['v59_phone'])&&wp_verify_nonce($_POST['_wpnonce']??'','v59_phone')){update_option('v59_phone',sanitize_text_field($_POST['v59_phone']));echo '<p style="color:#00a32a">✅ Salvato!</p>';} ?>
    <?php submit_button('💾 Salva','secondary','',false); ?>
  </form>
</div>

<!-- WHAT'S INCLUDED -->
<div class="soser-card" style="margin-bottom:20px">
  <h2>✨ Cosa include il nuovo design</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px">
    <?php $features = [
      ['📊','Progress Bar','Barra di lettura in alto'],
      ['✅','Expert Badge','Verificato + data aggiornamento'],
      ['📋','TOC Automatico','Indice con link ancorati'],
      ['⚡','Risposta Rapida','Answer box per AI Overview'],
      ['💰','Price Box','Prezzi con contesto locale'],
      ['🏆','Trust Section','Anni, recensioni, certificazioni'],
      ['📞','CTA Inline','Ogni 3 sezioni — telefono + WA'],
      ['💬','WhatsApp Sticky','Bottone fisso in basso a destra'],
    ];
    foreach($features as $f): ?>
    <div style="background:#f8f7f4;border-radius:8px;padding:12px;border:1px solid #e8e5e0">
      <div style="font-size:20px;margin-bottom:4px"><?= $f[0] ?></div>
      <strong style="font-size:13px;display:block"><?= esc_html($f[1]) ?></strong>
      <span style="font-size:11px;color:#666"><?= esc_html($f[2]) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- APPLY BUTTON -->
<!-- ⚠️ Warning FIRST, buttons AFTER -->
<div class="soser-card" style="max-width:600px;background:#fdecea;border-color:#d63638">
  <h2 style="color:#d63638">⚠️ Backup obbligatorio prima di procedere</h2>
  <p style="font-size:13px;color:#5a1a1a;margin-bottom:10px;line-height:1.7">
    <strong>Bulk Apply modifica il contenuto reale (post_content) degli articoli.</strong><br>
    Una volta applicato, il contenuto originale viene sostituito — anche se viene salvata una revisione WP.
  </p>
  <p style="font-size:13px;color:#5a1a1a;margin-bottom:14px">
    📌 <strong>Hostinger:</strong> hPanel → Database → phpMyAdmin → Esporta<br>
    📌 <strong>Plugin:</strong> UpdraftPlus → Backup Now<br>
    📌 <strong>Solo articoli SOSER SEO</strong> — salta automaticamente i post Elementor ✅
  </p>
  <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#5a1a1a;cursor:pointer">
    <input type="checkbox" id="v59-backup-confirm" style="width:18px;height:18px">
    Ho fatto il Backup del database — procedi
  </label>
</div>

<div class="soser-card" style="background:#f0f9f0;border-color:#00a32a;max-width:600px;margin-top:12px">
  <h2>🚀 Applica il nuovo design</h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">
    Ogni run processa <strong>5 o 10 articoli</strong>. I nuovi articoli usano già il design automaticamente.
  </p>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button class="button button-primary button-large" id="v59-apply-btn" disabled>🎨 Applica a 5 articoli</button>
    <button class="button" id="v59-apply-10-btn" disabled>🎨🎨 Applica a 10 articoli</button>
    <span id="v59-apply-result" style="font-size:13px;font-weight:600"></span>
  </div>
  <p style="font-size:12px;color:#888;margin-top:10px">
    ✅ I bottoni si attivano solo dopo aver confermato il Backup.
  </p>
</div>

</div>
<script>
jQuery(function($){
  var nonce='<?= wp_create_nonce("soser_v4_nonce") ?>';

  function applyDesign(limit){
    var $r=$('#v59-apply-result');
    $('#v59-apply-btn,#v59-apply-10-btn').prop('disabled',true);
    $r.css('color','#888').text('In corso...');
    $.post(ajaxurl,{action:'soser_v59_apply_design',nonce:nonce,limit:limit},function(r){
      if(r.success){
        $r.css('color','#00a32a').text('✅ '+r.data.message);
        setTimeout(()=>location.reload(),1500);
      } else {
        $r.css('color','#d63638').text('❌ '+r.data);
      }
    }).always(()=>$('#v59-apply-btn,#v59-apply-10-btn').prop('disabled',false));
  }

  // Checkbox enables/disables buttons
  $('#v59-backup-confirm').on('change', function() {
    $('#v59-apply-btn, #v59-apply-10-btn').prop('disabled', !this.checked);
  });
  $('#v59-apply-btn').on('click', function() {
    if (!$('#v59-backup-confirm').is(':checked')) return;
    applyDesign(5);
  });
  $('#v59-apply-10-btn').on('click', function() {
    if (!$('#v59-backup-confirm').is(':checked')) return;
    applyDesign(10);
  });
});
</script>
