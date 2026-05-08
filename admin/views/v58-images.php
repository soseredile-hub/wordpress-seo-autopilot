<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🖼️ Libreria Foto — Foto reali per i tuoi articoli</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Carica le tue <strong>foto reali</strong> dei lavori svolti e il sistema sceglierà automaticamente la foto più adatta per ogni nuovo articolo. <strong>Niente più immagini AI.</strong>
</p>
<div class="soser-stats" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $stats['total']??0 ?></div><div class="stat-lbl">Foto totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $stats['approved']??0 ?></div><div class="stat-lbl">✅ Approvate</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= $stats['tagged']??0 ?></div><div class="stat-lbl">🏷️ Con tag</div></div>
</div>
<?php if (($stats['approved']??0) === 0): ?>
<div class="notice notice-warning"><p>
  ⚠️ <strong>Nessuna foto approvata.</strong> Vai alla <a href="<?= admin_url('upload.php') ?>"><strong>Libreria Media</strong></a>, apri ogni foto e:
  <ol style="font-size:13px;line-height:2;margin-top:8px">
    <li>Aggiungi <strong>SEO Tags</strong> — es: "ristrutturazione bagno, ceramiche, Milano"</li>
    <li>Seleziona il <strong>Servizio</strong></li>
    <li>Spunta <strong>"Usa negli articoli"</strong> ✅</li>
  </ol>
</p></div>
<?php endif; ?>
<div class="soser-grid-2">
  <div class="soser-card">
    <h2>⚙️ Come funziona</h2>
    <ol style="font-size:13px;line-height:2.4">
      <li>📤 <strong>Carica foto reali</strong> nella Libreria Media WordPress</li>
      <li>🏷️ <strong>Aggiungi tag SEO</strong> a ogni foto</li>
      <li>✅ <strong>Approva</strong> le foto da usare negli articoli</li>
      <li>🤖 Il sistema <strong>sceglie automaticamente</strong> la foto adatta</li>
    </ol>
    <a href="<?= admin_url('upload.php') ?>" class="button button-primary" style="margin-top:10px">📤 Vai alla Libreria Media →</a>
  </div>
  <div class="soser-card">
    <h2>🤖 Auto-Tag con AI</h2>
    <p style="font-size:13px;color:#555;margin-bottom:14px">L'AI analizza il nome del file e assegna automaticamente tag SEO e servizio.</p>
    <button class="button button-primary" id="v58-auto-tag-btn">🤖 Auto-tag 20 foto con AI</button>
    <span id="v58-auto-tag-result" style="display:block;margin-top:8px;font-size:12px"></span>
    <hr style="margin:14px 0">
    <p style="font-size:12px;color:#888">💡 Rinomina le foto prima di caricarle:<br>
    <code>ristrutturazione-bagno-milano.jpg</code></p>
  </div>
  <div class="soser-card">
    <h2>🔍 Test abbinamento</h2>
    <input type="text" id="v58-test-kw" class="regular-text" placeholder="ristrutturazione bagno Milano" style="margin-bottom:8px"><br>
    <select id="v58-test-svc" class="regular-text" style="margin-bottom:10px">
      <option value="">-- Qualsiasi servizio --</option>
      <?php foreach ($services as $svc): ?>
      <option value="<?= esc_attr($svc['id']) ?>"><?= esc_html($svc['icon'].' '.$svc['name']) ?></option>
      <?php endforeach; ?>
    </select><br>
    <button class="button" id="v58-test-btn">🔍 Trova foto migliore</button>
    <div id="v58-test-result" style="margin-top:12px"></div>
  </div>
  <div class="soser-card">
    <h2>✅ Foto approvate (<?= count($approved) ?>)</h2>
    <?php if (empty($approved)): ?>
    <p style="color:#888;font-size:13px">Nessuna foto approvata ancora.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
      <?php foreach (array_slice($approved,0,8) as $id): ?>
      <img src="<?= wp_get_attachment_image_url($id,'thumbnail') ?>" style="width:100%;height:70px;object-fit:cover;border-radius:4px;border:2px solid #00a32a">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
jQuery(function($){
  var nonce='<?= wp_create_nonce("soser_v4_nonce") ?>';
  $('#v58-auto-tag-btn').on('click',function(){
    var $b=$(this),$r=$('#v58-auto-tag-result');
    $b.prop('disabled',true).text('...');
    $.post(ajaxurl,{action:'soser_v58_auto_tag',nonce:nonce},function(r){
      r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
      if(r.success)setTimeout(()=>location.reload(),2000);
    }).always(()=>$b.prop('disabled',false).text('🤖 Auto-tag 20 foto con AI'));
  });
  $('#v58-test-btn').on('click',function(){
    var kw=$('#v58-test-kw').val(),svc=$('#v58-test-svc').val();
    if(!kw){alert('Inserisci keyword');return;}
    $(this).prop('disabled',true).text('...');
    $.post(ajaxurl,{action:'soser_v58_test_match',nonce:nonce,kw:kw,svc:svc},function(r){
      if(r.success){var d=r.data;$('#v58-test-result').html('<div style="display:flex;gap:10px;align-items:center;background:#e8f9e8;padding:10px;border-radius:6px"><img src="'+d.url+'" style="width:70px;height:70px;object-fit:cover;border-radius:4px"><div><strong>'+d.title+'</strong><br><small>Tags: '+d.tags.join(', ')+'</small></div></div>');}
      else $('#v58-test-result').html('<p style="color:#d63638">❌ '+r.data+'</p>');
    }).always(()=>$('#v58-test-btn').prop('disabled',false).text('🔍 Trova foto migliore'));
  });
});
</script>
</div>
