<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🎯 Servizi Focus — Su cosa scrivere adesso</h1>

<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:24px;line-height:1.7">
  Seleziona i servizi su cui vuoi che la AI si concentri <strong>adesso</strong>.
  Solo le keyword di questi servizi verranno usate per scrivere nuovi articoli.
  Puoi cambiare il focus in qualsiasi momento.
</p>

<!-- COVERAGE OVERVIEW -->
<div class="soser-card" style="margin-bottom:24px">
  <h2>📊 Copertura attuale per servizio</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:4px">
    <?php foreach ($coverage as $c):
      $svc        = $c['service'];
      $is_active  = $c['is_active'];
      $posts      = $c['post_count'];
      $clicks     = $c['gsc_clicks'];
      $needs_work = $c['needs_work'];
      $bar_color  = $posts === 0 ? '#d63638' : ($posts < 3 ? '#f0a500' : '#00a32a');
      $bar_w      = min(100, $posts * 20); // 5 posts = 100%
    ?>
    <div class="soser-svc-card <?= $is_active ? 'soser-svc-active' : '' ?>"
         style="border:2px solid <?= $is_active ? '#2271b1' : '#e0e0e0' ?>;border-radius:8px;padding:14px;cursor:pointer;transition:all .2s;background:<?= $is_active ? '#f0f6fc' : '#fff' ?>"
         data-id="<?= esc_attr($svc['id']) ?>"
         onclick="toggleService('<?= esc_attr($svc['id']) ?>')">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <span style="font-size:20px"><?= esc_html($svc['icon']) ?></span>
        <span class="soser-svc-check" style="font-size:18px"><?= $is_active ? '✅' : '⬜' ?></span>
      </div>
      <strong style="font-size:14px;display:block;margin-bottom:6px"><?= esc_html($svc['name']) ?></strong>
      <div style="display:flex;gap:12px;font-size:12px;color:#666;margin-bottom:8px">
        <span>📄 <?= $posts ?> articoli</span>
        <span>👆 <?= number_format($clicks) ?> click</span>
      </div>
      <!-- Coverage bar -->
      <div style="background:#f0f0f1;border-radius:4px;height:6px;overflow:hidden">
        <div style="width:<?= $bar_w ?>%;height:100%;background:<?= $bar_color ?>;border-radius:4px"></div>
      </div>
      <div style="margin-top:6px;font-size:11px">
        <?php if ($posts === 0): ?>
          <span style="color:#d63638;font-weight:700">⚠️ Nessun articolo — priorità alta!</span>
        <?php elseif ($needs_work): ?>
          <span style="color:#f0a500;font-weight:700">📈 Pochi articoli — da sviluppare</span>
        <?php else: ?>
          <span style="color:#00a32a">✅ Buona copertura</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- FOCUS CONTROLS -->
<div class="soser-card" style="max-width:700px;margin-bottom:20px">
  <h2>🎯 Imposta focus</h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">
    Clicca sulle card sopra per selezionare/deselezionare servizi, poi salva.
  </p>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <button class="button button-secondary" onclick="selectAll()">Seleziona tutti</button>
    <button class="button button-secondary" onclick="selectNone()">Deseleziona tutti</button>
    <button class="button button-secondary" onclick="selectNeedy()">🔥 Solo quelli senza articoli</button>
  </div>

  <div id="v54-selected-preview" style="background:#f0f6fc;border-radius:6px;padding:10px 14px;font-size:13px;margin-bottom:14px;min-height:40px">
    <strong>Focus attuale:</strong>
    <?php
    $active_svcs = array_filter($coverage, fn($c) => $c['is_active']);
    $active_names = array_map(fn($c) => $c['service']['icon'].' '.$c['service']['name'], $active_svcs);
    echo esc_html(implode(' · ', $active_names));
    ?>
  </div>

  <button class="button button-primary button-large" id="v54-save-btn">
    💾 Salva focus e aggiorna keyword
  </button>
  <span id="v54-save-result" style="display:block;margin-top:10px;font-size:13px;font-weight:600"></span>
</div>

<!-- ADD CUSTOM SERVICE -->
<div class="soser-card" style="max-width:700px;margin-bottom:20px">
  <h2>➕ Aggiungi servizio personalizzato</h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">Hai un servizio non in lista? Aggiungilo qui.</p>

  <table class="form-table" style="max-width:600px">
    <tr>
      <th style="width:130px">Nome servizio</th>
      <td><input type="text" id="v54-svc-name" class="regular-text" placeholder="es. Ristrutturazione Tetto"></td>
    </tr>
    <tr>
      <th>Icona (emoji)</th>
      <td><input type="text" id="v54-svc-icon" class="small-text" value="🔧" style="width:60px"></td>
    </tr>
    <tr>
      <th>Keyword seeds</th>
      <td>
        <textarea id="v54-svc-seeds" rows="4" class="large-text" placeholder="ristrutturazione tetto Milano&#10;costo rifacimento tetto Milano&#10;impermeabilizzazione tetto Milano"></textarea>
        <p class="description">Una keyword per riga</p>
      </td>
    </tr>
  </table>
  <button class="button button-secondary" id="v54-add-svc-btn">➕ Aggiungi servizio</button>
  <span id="v54-svc-result" style="font-size:12px;margin-right:8px"></span>
</div>

<!-- WHAT HAPPENS NEXT -->
<div class="soser-card" style="max-width:700px;background:#f9f9f9">
  <h2>💡 Cosa succede dopo il salvataggio</h2>
  <ol style="font-size:13px;line-height:2.2;color:#444">
    <li>Le keyword dei servizi selezionati diventano i <strong>Seed Keywords</strong></li>
    <li>Keyword Intel trova nuove keyword per questi servizi</li>
    <li>AI Planner pianifica articoli <strong>solo</strong> per questi servizi</li>
    <li>Il Cron giornaliero scrive un articolo per uno di questi servizi</li>
    <li>Puoi tornare qui e cambiare focus quando vuoi</li>
  </ol>
</div>

</div>

<script>
var activeIds = <?= json_encode(array_column(array_filter($coverage, fn($c)=>$c['is_active']), 'service') ? array_keys(array_column(array_column(array_filter($coverage, fn($c)=>$c['is_active']),'service'),'id','id')) : []) ?>;
// Fix: build from PHP
var activeIds = <?= json_encode(array_values(array_map(fn($c)=>$c['service']['id'], array_filter($coverage, fn($c)=>$c['is_active'])))) ?>;
var allServices = <?= json_encode(array_map(fn($c)=>['id'=>$c['service']['id'],'name'=>$c['service']['icon'].' '.$c['service']['name'],'has_posts'=>$c['post_count']>0], $coverage)) ?>;
var nonce = '<?= wp_create_nonce("soser_v4_nonce") ?>';

function toggleService(id) {
    var card = document.querySelector('[data-id="'+id+'"]');
    var check = card.querySelector('.soser-svc-check');
    var idx   = activeIds.indexOf(id);

    if (idx >= 0) {
        activeIds.splice(idx, 1);
        card.style.borderColor  = '#e0e0e0';
        card.style.background   = '#fff';
        check.textContent       = '⬜';
    } else {
        activeIds.push(id);
        card.style.borderColor  = '#2271b1';
        card.style.background   = '#f0f6fc';
        check.textContent       = '✅';
    }
    updatePreview();
}

function selectAll() {
    activeIds = allServices.map(s=>s.id);
    allServices.forEach(s=>{
        var card = document.querySelector('[data-id="'+s.id+'"]');
        if(card){ card.style.borderColor='#2271b1';card.style.background='#f0f6fc';card.querySelector('.soser-svc-check').textContent='✅'; }
    });
    updatePreview();
}

function selectNone() {
    activeIds = [];
    allServices.forEach(s=>{
        var card = document.querySelector('[data-id="'+s.id+'"]');
        if(card){ card.style.borderColor='#e0e0e0';card.style.background='#fff';card.querySelector('.soser-svc-check').textContent='⬜'; }
    });
    updatePreview();
}

function selectNeedy() {
    activeIds = allServices.filter(s=>!s.has_posts).map(s=>s.id);
    if(activeIds.length===0) activeIds = allServices.filter(s=>s.has_posts<3).map(s=>s.id);
    allServices.forEach(s=>{
        var active = activeIds.indexOf(s.id)>=0;
        var card = document.querySelector('[data-id="'+s.id+'"]');
        if(card){
            card.style.borderColor = active?'#2271b1':'#e0e0e0';
            card.style.background  = active?'#f0f6fc':'#fff';
            card.querySelector('.soser-svc-check').textContent = active?'✅':'⬜';
        }
    });
    updatePreview();
}

function updatePreview() {
    var names = allServices.filter(s=>activeIds.indexOf(s.id)>=0).map(s=>s.name);
    document.getElementById('v54-selected-preview').innerHTML =
        '<strong>Focus selezionato:</strong> ' + (names.length ? names.join(' · ') : '<span style="color:#d63638">Nessun servizio selezionato!</span>');
}

jQuery(function($){
    // Save active services
    $('#v54-save-btn').on('click', function(){
        if(activeIds.length===0){ alert('Seleziona almeno un servizio!'); return; }
        var $b=$(this),$r=$('#v54-save-result');
        $b.prop('disabled',true).text('Salvataggio...');
        $.post(ajaxurl,{action:'soser_v54_save_active',nonce:nonce,active:activeIds},function(r){
            if(r.success){
                $r.css('color','#00a32a').text('✅ '+r.data.message);
                setTimeout(()=>location.reload(),2000);
            } else {
                $r.css('color','#d63638').text('❌ '+r.data);
            }
        }).always(()=>$b.prop('disabled',false).text('💾 Salva focus e aggiorna keyword'));
    });

    // Add custom service
    $('#v54-add-svc-btn').on('click', function(){
        var name  = $('#v54-svc-name').val();
        var icon  = $('#v54-svc-icon').val();
        var seeds = $('#v54-svc-seeds').val();
        var $r    = $('#v54-svc-result');
        if(!name){ alert('Inserisci il nome del servizio'); return; }
        $(this).prop('disabled',true).text('...');
        $.post(ajaxurl,{
            action:'soser_v54_save_service',nonce:nonce,
            svc_name:name,svc_icon:icon,svc_seeds:seeds
        },function(r){
            r.success ? $r.css('color','#00a32a').text('✅ '+r.data) : $r.css('color','#d63638').text('❌ '+r.data);
            if(r.success) setTimeout(()=>location.reload(),1200);
        }).always(()=>$('#v54-add-svc-btn').prop('disabled',false).text('➕ Aggiungi servizio'));
    });
});
</script>

<style>
.soser-svc-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-1px); }
</style>
