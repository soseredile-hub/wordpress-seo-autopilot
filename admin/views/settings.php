<?php
defined('ABSPATH') || exit;
?>
<div class="wrap soser-wrap">
<h1>⚙️ Impostazioni</h1>
<?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>✅ Impostazioni salvate.</p></div><?php endif; ?>
<form method="post" action="<?= admin_url('admin-post.php') ?>">
  <?php wp_nonce_field('soser_v4_settings') ?><input type="hidden" name="action" value="soser_v4_save">

  <div class="soser-grid-2">
    <!-- API -->
    <div class="soser-card">
      <h2>🔑 API</h2>
      <table class="form-table">
        <tr><th>OpenAI API Key</th><td>
          <input type="password" name="openai_key" value="<?= $o['openai_key'] ? '••••••••' : '' ?>" class="regular-text" id="soser-api-key" autocomplete="off">
          <button type="button" class="button" id="soser-test-api">Testa</button>
          <span id="soser-api-result"></span>
        </td></tr>
        <tr><th>Modello testo</th><td><input name="openai_model" value="<?= esc_attr($o['openai_model']) ?>" class="regular-text"></td></tr>
        <tr><th>Modello immagini</th><td><input name="image_model" value="<?= esc_attr($o['image_model']) ?>" class="regular-text"></td></tr>
      </table>
    </div>
    <!-- Business -->
    <div class="soser-card">
      <h2>🏢 Business</h2>
      <table class="form-table">
        <tr><th>Geo target</th><td><input name="geo" value="<?= esc_attr($o['geo']) ?>" class="regular-text"></td></tr>
        <tr><th>Nome business</th><td><input name="business" value="<?= esc_attr($o['business']) ?>" class="regular-text"></td></tr>
        <tr><th>Lingua</th><td><select name="language"><option value="it" <?= selected($o['language'],'it',false) ?>>Italiano</option><option value="en" <?= selected($o['language'],'en',false) ?>>English</option></select></td></tr>
        <tr><th>CTA</th><td><textarea name="cta" rows="2" class="large-text"><?= esc_textarea($o['cta']) ?></textarea></td></tr>
      </table>
    </div>
    <!-- Keywords -->
    <div class="soser-card">
      <h2>🔍 Keyword Seeds</h2>
      <table class="form-table">
        <tr><th>Seed keywords</th><td>
          <textarea name="seed_keywords" rows="8" class="large-text code"><?= esc_textarea($o['seed_keywords']) ?></textarea>
          <p class="description">Una keyword per riga. Il sistema espande automaticamente.</p>
        </td></tr>
        <tr><th>Score minimo</th><td><input type="number" name="min_opportunity" value="<?= esc_attr($o['min_opportunity']) ?>" min="0" max="100" class="small-text"> /100</td></tr>
        <tr><th>Profondità ricerca</th><td><input type="number" name="intel_depth" value="<?= esc_attr($o['intel_depth']) ?>" min="5" max="50" class="small-text"> keyword per seed</td></tr>
      </table>
    </div>
    <!-- Publishing -->
    <div class="soser-card">
      <h2>📝 Pubblicazione</h2>
      <table class="form-table">
        <tr><th>Stato post</th><td>
          <select name="post_status">
            <option value="draft" <?= selected($o['post_status'],'draft',false) ?>>Bozza</option>
            <option value="publish" <?= selected($o['post_status'],'publish',false) ?>>Pubblica</option>
            <option value="pending" <?= selected($o['post_status'],'pending',false) ?>>Revisione</option>
          </select>
        </td></tr>
        <tr><th>Categoria</th><td><?php wp_dropdown_categories(['name'=>'category_id','selected'=>$o['category_id'],'show_option_none'=>'Nessuna','hide_empty'=>0]) ?></td></tr>
        <tr><th>Parole</th><td><input name="min_words" value="<?= esc_attr($o['min_words']) ?>" size="5"> – <input name="max_words" value="<?= esc_attr($o['max_words']) ?>" size="5"></td></tr>
      </table>
    </div>
    <!-- Images -->
    <div class="soser-card">
      <h2>🖼️ Immagini AI</h2>
      <table class="form-table">
        <tr><th></th><td>
          <label><input type="checkbox" name="generate_images" <?= checked($o['generate_images'],'1',false) ?>> Genera immagini AI</label><br>
          <label><input type="checkbox" name="featured_image" <?= checked($o['featured_image'],'1',false) ?>> Featured Image automatica</label>
        </td></tr>
        <tr><th>Immagini inline</th><td><input type="number" name="inline_images" value="<?= esc_attr($o['inline_images']) ?>" min="0" max="4" class="small-text"></td></tr>
        <tr><th>Stile prompt</th><td><textarea name="image_style" rows="2" class="large-text"><?= esc_textarea($o['image_style']) ?></textarea></td></tr>
      </table>
    </div>
    <!-- Modules -->
    <div class="soser-card">
      <h2>🔧 Moduli</h2>
      <table class="form-table">
        <?php
        $modules = [
          'enable_seo_fixer'  => '🔍 SEO Fixer (Title + Meta + Keyword in apertura)',
          'enable_int_links'  => '🔗 Link interni automatici',
          'enable_ext_links'  => '🌐 Link esterni autorevoli',
          'enable_schema'     => '📊 FAQ Schema (JSON-LD)',
          'enable_humanizer'  => '🧑 Humanizer (rimuove frasi AI)',
          'cron_enabled'      => '⏰ Cron giornaliero automatico',
          'debug'             => '🐛 Debug log',
        ];
        foreach ($modules as $k => $lbl): ?>
        <tr><th></th><td><label><input type="checkbox" name="<?= esc_attr($k) ?>" <?= checked($o[$k],'1',false) ?>> <?= esc_html($lbl) ?></label></td></tr>
        <?php endforeach; ?>
        <tr><th>Ora cron</th><td><input name="cron_hour" value="<?= esc_attr($o['cron_hour']) ?>" size="3">:00</td></tr>
      </table>
    </div>
    <!-- External links -->
    <div class="soser-card" style="grid-column:1/-1">
      <h2>🌐 Link esterni</h2>
      <textarea name="external_links" rows="4" class="large-text code"><?= esc_textarea($o['external_links']) ?></textarea>
      <p class="description">Un URL per riga. Verranno inseriti automaticamente negli articoli.</p>
    </div>
  </div>

  <!-- Business Context Scanner -->
  <div class="soser-card" style="grid-column:1/-1;background:#f0f6fc;border-color:#b8d4f0">
    <h2>🏢 Business Context — Servizi rilevati dal sito</h2>
    <p style="font-size:13px;color:#444;margin-bottom:12px">
      Il plugin scansiona le pagine del tuo sito per capire i servizi offerti e usarli nei prompt AI.
      Clicca per aggiornare dopo aver modificato le pagine del sito.
    </p>
    <button type="button" class="button button-primary" id="soser-scan-business">🔍 Scansiona servizi dal sito</button>
    <span id="soser-biz-result" style="margin-right:10px;font-size:12px"></span>
    <div id="soser-biz-services" style="margin-top:12px;display:none">
      <strong style="font-size:12px">Servizi trovati:</strong>
      <div id="soser-biz-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px"></div>
      <p id="soser-biz-summary" style="font-size:12px;color:#555;margin-top:8px;font-style:italic"></p>
    </div>
  </div>

  <!-- Universal Settings Card -->
  <div class="soser-card" style="grid-column:1/-1;border:2px solid #2271b1">
    <h2>🌍 Impostazioni Universali — Qualsiasi Città, Qualsiasi Settore</h2>
    <p style="font-size:13px;color:#555;margin-bottom:16px">
      Cambia questi valori per adattare il plugin a <strong>qualsiasi città</strong> e <strong>qualsiasi settore</strong> al mondo.
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px">
      <!-- Location -->
      <div>
        <h3 style="font-size:14px;margin-bottom:12px">📍 Posizione</h3>
        <table class="form-table">
          <tr><th>Città target</th><td><input name="aaw_geo_city" value="<?= esc_attr(get_option('soser_v4_options')['geo_city']??'Milano') ?>" class="regular-text" placeholder="es. Roma, Napoli, Paris, London"></td></tr>
          <tr><th>Regione/Stato</th><td><input name="aaw_geo_region" value="<?= esc_attr(get_option('soser_v4_options')['geo_region']??'Lombardia') ?>" class="regular-text" placeholder="es. Lazio, Campania, Île-de-France"></td></tr>
          <tr><th>Paese (codice)</th><td><input name="aaw_geo_country" value="<?= esc_attr(get_option('soser_v4_options')['geo_country']??'IT') ?>" class="small-text" placeholder="IT, FR, DE, ES..."></td></tr>
          <tr><th>Coordinate GPS</th><td>
            <input name="aaw_geo_lat" value="<?= esc_attr(get_option('soser_v4_options')['geo_lat']??'45.4654') ?>" class="small-text" placeholder="Lat"> ,
            <input name="aaw_geo_lon" value="<?= esc_attr(get_option('soser_v4_options')['geo_lon']??'9.1866') ?>" class="small-text" placeholder="Lon">
            <p class="description">Trova su <a href="https://maps.google.com" target="_blank">Google Maps</a> → click destro → "Cosa c'è qui"</p>
          </td></tr>
          <tr><th>Zone vicine</th><td>
            <textarea name="aaw_geo_zones" rows="4" class="large-text" placeholder="Zona Centro&#10;Zona Nord&#10;Quartiere 1&#10;Città vicina"><?= esc_textarea(get_option('soser_v4_options')['geo_zones']??'') ?></textarea>
            <p class="description">Una zona per riga — usate per varianti keyword locali</p>
          </td></tr>
        </table>
      </div>
      <!-- Sector/Niche -->
      <div>
        <h3 style="font-size:14px;margin-bottom:12px">🏢 Settore / Nicchia</h3>
        <table class="form-table">
          <tr><th>Settore principale</th><td>
            <input name="aaw_sector" value="<?= esc_attr(get_option('soser_v4_options')['sector']??'ristrutturazione') ?>" class="regular-text" placeholder="es. ristrutturazione, dentista, avvocato, ecommerce">
            <p class="description">La parola chiave principale del tuo business</p>
          </td></tr>
          <tr><th>Etichetta settore</th><td><input name="aaw_sector_label" value="<?= esc_attr(get_option('soser_v4_options')['sector_label']??'Ristrutturazione e Edilizia') ?>" class="regular-text" placeholder="es. Studio Dentistico, Consulenza Legale"></td></tr>
          <tr><th>Tipo business</th><td>
            <select name="aaw_business_type">
              <?php $bt = get_option('soser_v4_options')['business_type']??'LocalBusiness';
              $types = ['LocalBusiness'=>'Local Business (generico)','HomeAndConstructionBusiness'=>'Edilizia/Ristrutturazione','MedicalBusiness'=>'Medico/Dentista','LegalService'=>'Avvocato/Consulente','AutomotiveBusiness'=>'Auto/Meccanico','FoodEstablishment'=>'Ristorante/Food','Store'=>'Negozio/Retail','ProfessionalService'=>'Servizio Professionale'];
              foreach ($types as $val=>$lbl): ?>
              <option value="<?= esc_attr($val) ?>" <?= selected($bt,$val,false) ?>><?= esc_html($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
          <tr><th>Valuta</th><td>
            <select name="aaw_currency">
              <?php $cur = get_option('soser_v4_options')['currency']??'EUR';
              foreach(['EUR'=>'€ Euro','GBP'=>'£ Sterlina','USD'=>'$ Dollaro','CHF'=>'CHF Franco Svizzero','AED'=>'AED Dirham'] as $c=>$l): ?>
              <option value="<?= $c ?>" <?= selected($cur,$c,false) ?>><?= esc_html($l) ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
          <tr><th>Fascia prezzi</th><td>
            <select name="aaw_price_range">
              <?php $pr = get_option('soser_v4_options')['price_range']??'€€';
              foreach(['€'=>'€ Economico','€€'=>'€€ Medio','€€€'=>'€€€ Premium','€€€€'=>'€€€€ Lusso'] as $p=>$l): ?>
              <option value="<?= $p ?>" <?= selected($pr,$p,false) ?>><?= esc_html($l) ?></option>
              <?php endforeach; ?>
            </select>
          </td></tr>
        </table>
      </div>
    </div>
    <!-- Preview -->
    <div style="background:#f0f6fc;border-radius:6px;padding:12px 16px;margin-top:16px;font-size:13px">
      <strong>👁️ Anteprima keyword generate:</strong>
      <span style="color:#2271b1" id="v56-preview-kw">
        [settore] [città] · prezzi [settore] [città] · costo [settore] [città]
      </span>
    </div>
  </div>

  <?php submit_button('💾 Salva impostazioni', 'primary large'); ?>
</form>
</div>
