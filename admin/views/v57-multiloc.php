<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🌍 Multi-Città — Scrivi per 10+ città in un click</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Inserisci le città target e il sistema scrive automaticamente lo stesso servizio per ogni città — ognuna con contenuto unico e ottimizzato localmente.
</p>
<div class="soser-grid-2">
  <div class="soser-card">
    <h2>📍 Città target</h2>
    <div id="cities-list">
      <?php foreach ($cities as $i=>$city): ?>
      <div style="display:flex;gap:8px;margin-bottom:6px">
        <input type="text" name="cities[]" value="<?= esc_attr($city) ?>" class="regular-text city-input">
        <button type="button" class="button" onclick="this.parentElement.remove()">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="add-city-btn" style="margin-top:8px">+ Aggiungi città</button>
    <hr style="margin:14px 0">
    <button class="button button-primary" id="save-cities-btn">💾 Salva città</button>
    <span id="save-cities-result" style="font-size:12px;margin-right:8px"></span>
  </div>
  <div class="soser-card">
    <h2>🚀 Genera articoli per tutte le città</h2>
    <p style="font-size:13px;color:#555;margin-bottom:14px">Scegli un servizio e il sistema crea automaticamente un articolo per ogni città.</p>
    <select id="multiloc-service" class="regular-text" style="margin-bottom:8px">
      <option value="">-- Seleziona servizio --</option>
      <?php foreach ($services as $svc): ?>
      <option value="<?= esc_attr(mb_strtolower($svc['name'])) ?>"><?= esc_html($svc['icon'].' '.$svc['name']) ?></option>
      <?php endforeach; ?>
    </select><br>
    <input type="text" id="multiloc-custom-kw" class="regular-text" placeholder="O inserisci keyword personalizzata" style="margin-bottom:10px">
    <button class="button button-primary" id="multiloc-queue-btn">🚀 Genera per tutte le <?= count($cities) ?> città</button>
    <span id="multiloc-result" style="font-size:12px;display:block;margin-top:8px"></span>
  </div>
</div>
<?php if (!empty($coverage)): ?>
<div class="soser-card" style="margin-top:16px">
  <h2>📊 Copertura per città</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>Città</th><th>Articoli</th><th>Keyword coperte</th></tr></thead>
    <tbody>
    <?php foreach ($coverage as $city=>$data): ?>
    <tr>
      <td><strong><?= esc_html($city) ?></strong></td>
      <td><?= $data['post_count'] ?> <?= $data['post_count']===0?'<span style="color:#d63638">⚠️ Nessuno</span>':'' ?></td>
      <td style="font-size:11px;color:#666"><?= esc_html(implode(', ',array_slice($data['keywords'],0,3))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<script>
jQuery(function($){
  $('#add-city-btn').on('click',function(){
    $('#cities-list').append('<div style="display:flex;gap:8px;margin-bottom:6px"><input type="text" name="cities[]" class="regular-text city-input" placeholder="es. Roma, Napoli..."><button type="button" class="button" onclick="this.parentElement.remove()">✕</button></div>');
  });
  $('#save-cities-btn').on('click',function(){
    var cities=$('.city-input').map(function(){return $(this).val();}).get().filter(Boolean);
    var $r=$('#save-cities-result'),$b=$(this);
    $b.prop('disabled',true);
    $.post(ajaxurl,{action:'soser_v57_save_cities',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>',cities:cities},function(r){
      r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
      if(r.success)setTimeout(()=>location.reload(),1000);
    }).always(()=>$b.prop('disabled',false));
  });
  $('#multiloc-queue-btn').on('click',function(){
    var svc=$('#multiloc-service').val();
    var custom=$('#multiloc-custom-kw').val();
    var kw=custom||svc;
    if(!kw){alert('Seleziona un servizio o inserisci una keyword');return;}
    var cities=$('.city-input').map(function(){return $(this).val();}).get().filter(Boolean);
    if(!cities.length){alert('Aggiungi almeno una città');return;}
    var $b=$(this),$r=$('#multiloc-result');
    $b.prop('disabled',true).text('...');
    $.post(ajaxurl,{action:'soser_v57_multiloc_queue',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>',keyword:kw,cities:cities},function(r){
      r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
    }).always(()=>$b.prop('disabled',false).text('🚀 Genera per tutte le città'));
  });
});
</script>
</div>
