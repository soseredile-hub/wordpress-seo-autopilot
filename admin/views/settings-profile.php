<?php defined('ABSPATH') || exit;
$o = V4_Options::get();

// Language options
$languages = [
    'it' => '🇮🇹 Italiano',
    'en' => '🇬🇧 English',
    'ar' => '🇸🇦 العربية',
    'fr' => '🇫🇷 Français',
    'es' => '🇪🇸 Español',
    'de' => '🇩🇪 Deutsch',
    'pt' => '🇧🇷 Português',
    'nl' => '🇳🇱 Nederlands',
    'ru' => '🇷🇺 Русский',
    'zh' => '🇨🇳 中文',
];

// Currency options
$currencies = [
    'EUR' => '€ Euro',
    'USD' => '$ US Dollar',
    'GBP' => '£ British Pound',
    'AED' => 'د.إ UAE Dirham',
    'SAR' => 'ر.س Saudi Riyal',
    'QAR' => 'ر.ق Qatari Riyal',
    'KWD' => 'د.ك Kuwaiti Dinar',
    'EGP' => 'ج.م Egyptian Pound',
    'MAD' => 'د.م Moroccan Dirham',
    'TRY' => '₺ Turkish Lira',
    'INR' => '₹ Indian Rupee',
    'AUD' => 'A$ Australian Dollar',
    'CAD' => 'C$ Canadian Dollar',
    'CHF' => 'CHF Swiss Franc',
    'JPY' => '¥ Japanese Yen',
];

// Country codes
$countries = [
    'IT' => '🇮🇹 Italia',
    'US' => '🇺🇸 United States',
    'GB' => '🇬🇧 United Kingdom',
    'AE' => '🇦🇪 UAE',
    'SA' => '🇸🇦 Saudi Arabia',
    'QA' => '🇶🇦 Qatar',
    'KW' => '🇰🇼 Kuwait',
    'EG' => '🇪🇬 Egypt',
    'MA' => '🇲🇦 Morocco',
    'FR' => '🇫🇷 France',
    'DE' => '🇩🇪 Germany',
    'ES' => '🇪🇸 Spain',
    'PT' => '🇵🇹 Portugal',
    'NL' => '🇳🇱 Netherlands',
    'AU' => '🇦🇺 Australia',
    'CA' => '🇨🇦 Canada',
    'TR' => '🇹🇷 Turkey',
    'IN' => '🇮🇳 India',
    'BR' => '🇧🇷 Brazil',
    'MX' => '🇲🇽 Mexico',
    'JP' => '🇯🇵 Japan',
    'ZA' => '🇿🇦 South Africa',
    'NG' => '🇳🇬 Nigeria',
    'Other' => '🌍 Other',
];

// Completeness check
$required_fields = ['business','geo_city','geo_country','language','currency','sector','business_phone'];
$filled = array_filter($required_fields, fn($f) => !empty($o[$f]));
$pct = round(count($filled)/count($required_fields)*100);
?>
<div class="wrap soser-wrap">
<h1>🌍 Business Profile — Universal Setup</h1>
<p style="font-size:14px;color:#555;max-width:780px;margin-bottom:8px;line-height:1.7">
  Configura qui i dati della tua azienda. Questi valori vengono usati da tutta la AI per generare articoli,
  titoli, CTA, e design — in qualsiasi lingua, paese o settore.
</p>

<!-- Completeness bar -->
<div style="max-width:780px;margin-bottom:24px;background:#fff;border:1px solid #e8e5e0;border-radius:8px;padding:14px 18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <span style="font-family:system-ui,sans-serif;font-size:13px;font-weight:700;color:#333">
      📊 Profilo completato: <span style="color:<?= $pct>=80?'#00a32a':($pct>=50?'#dba617':'#d63638') ?>"><?= $pct ?>%</span>
    </span>
    <?php if($pct < 100): ?>
    <span style="font-family:system-ui,sans-serif;font-size:12px;color:#888">
      Mancano: <?= implode(', ', array_diff($required_fields, $filled)) ?>
    </span>
    <?php endif; ?>
  </div>
  <div style="background:#e8e5e0;border-radius:4px;height:6px">
    <div style="background:<?= $pct>=80?'#00a32a':($pct>=50?'#dba617':'#d63638') ?>;width:<?= $pct ?>%;height:6px;border-radius:4px;transition:width .3s"></div>
  </div>
</div>

<form method="post" action="<?= admin_url('admin-post.php') ?>">
<?php wp_nonce_field('soser_save_settings','soser_nonce'); ?>
<input type="hidden" name="action" value="soser_save_settings">

<!-- ── SECTION 1: Identity ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🏢 Identità Aziendale</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Nome Azienda *</label>
      <input type="text" name="soser[business]" value="<?= esc_attr($o['business']) ?>"
        placeholder="es. SOSER, Al Baraka Contracting, Smith & Sons..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Tagline</label>
      <input type="text" name="soser[business_tagline]" value="<?= esc_attr($o['business_tagline']) ?>"
        placeholder="es. Esperti in ristrutturazioni dal 2010"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Anno Fondazione</label>
      <input type="text" name="soser[business_founded]" value="<?= esc_attr($o['business_founded']) ?>"
        placeholder="es. 2010"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Schema.org Type</label>
      <select name="soser[business_type]" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
        <?php foreach(['LocalBusiness','Contractor','HomeAndConstructionBusiness','ProfessionalService',
          'MedicalBusiness','LegalService','FinancialService','FoodEstablishment','Store',
          'AutoRepair','BeautySalon','HealthAndBeautyBusiness','TravelAgency','RealEstateAgent'] as $t): ?>
        <option value="<?= $t ?>" <?= selected($o['business_type'],$t,false) ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- ── SECTION 2: Location ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>📍 Posizione</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Paese *</label>
      <select name="soser[geo_country]" id="geo_country" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
        <option value="">— Seleziona paese —</option>
        <?php foreach($countries as $code => $name): ?>
        <option value="<?= $code ?>" <?= selected($o['geo_country'],$code,false) ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Nome Paese (per articoli)</label>
      <input type="text" name="soser[geo_country_name]" value="<?= esc_attr($o['geo_country_name']) ?>"
        placeholder="es. Italia, UAE, Saudi Arabia, France..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Città *</label>
      <input type="text" name="soser[geo_city]" value="<?= esc_attr($o['geo_city']) ?>"
        placeholder="es. Milano, Dubai, London, Cairo..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Regione / Stato</label>
      <input type="text" name="soser[geo_region]" value="<?= esc_attr($o['geo_region']) ?>"
        placeholder="es. Lombardia, Dubai Emirate, California..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Coordinate (lat)</label>
      <input type="text" name="soser[geo_lat]" value="<?= esc_attr($o['geo_lat']) ?>"
        placeholder="es. 45.4654"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Coordinate (lon)</label>
      <input type="text" name="soser[geo_lon]" value="<?= esc_attr($o['geo_lon']) ?>"
        placeholder="es. 9.1866"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
  <div style="margin-top:14px">
    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Zone servite (una per riga)</label>
    <textarea name="soser[geo_zones]" rows="3"
      placeholder="es.&#10;Monza&#10;Sesto San Giovanni&#10;Cinisello Balsamo"
      style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px"><?= esc_textarea($o['geo_zones']) ?></textarea>
  </div>
</div>

<!-- ── SECTION 3: Language & Currency ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🌐 Lingua & Valuta</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Lingua AI *</label>
      <select name="soser[language]" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
        <?php foreach($languages as $code => $name): ?>
        <option value="<?= $code ?>" <?= selected($o['language'],$code,false) ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
      <p style="font-size:11px;color:#888;margin-top:4px">La AI scriverà tutti gli articoli in questa lingua</p>
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Valuta *</label>
      <select name="soser[currency]" id="currency_select" onchange="updateSymbol(this.value)"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
        <?php foreach($currencies as $code => $name): ?>
        <option value="<?= $code ?>" <?= selected($o['currency'],$code,false) ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Simbolo Valuta</label>
      <input type="text" name="soser[currency_symbol]" id="currency_symbol"
        value="<?= esc_attr($o['currency_symbol'] ?: '€') ?>"
        placeholder="€, $, ر.س, د.إ"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
</div>

<!-- ── SECTION 4: Contacts ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>📞 Contatti</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Telefono *</label>
      <input type="text" name="soser[business_phone]" value="<?= esc_attr($o['business_phone']) ?>"
        placeholder="+39 02 1234567"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">WhatsApp</label>
      <input type="text" name="soser[business_whatsapp]" value="<?= esc_attr($o['business_whatsapp']) ?>"
        placeholder="+393331234567 (solo numeri con +)"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Email</label>
      <input type="email" name="soser[business_email]" value="<?= esc_attr($o['business_email']) ?>"
        placeholder="info@tuaazienda.com"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Indirizzo</label>
      <input type="text" name="soser[business_address]" value="<?= esc_attr($o['business_address']) ?>"
        placeholder="Via Roma 1, Milano"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
</div>

<!-- ── SECTION 5: Sector / Niche ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🏗️ Settore / Niche</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Settore (slug) *</label>
      <input type="text" name="soser[sector]" value="<?= esc_attr($o['sector']) ?>"
        placeholder="es. ristrutturazione, plumbing, dental, real-estate, catering..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Settore (etichetta)</label>
      <input type="text" name="soser[sector_label]" value="<?= esc_attr($o['sector_label']) ?>"
        placeholder="es. Ristrutturazione, Plumbing Services, Dental Clinic..."
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
  <div style="margin-top:14px">
    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Servizi offerti (uno per riga)</label>
    <textarea name="soser[sector_services]" rows="4"
      placeholder="es.&#10;Ristrutturazione bagno&#10;Impianti elettrici&#10;Tinteggiatura&#10;Parquet"
      style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px"><?= esc_textarea($o['sector_services']) ?></textarea>
    <p style="font-size:11px;color:#888;margin-top:4px">La AI userà questi servizi nelle keyword e nei CTA</p>
  </div>
</div>

<!-- ── SECTION 6: Branding ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🎨 Branding & Colori</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:end">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Colore principale</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="color" name="soser[brand_color]" value="<?= esc_attr($o['brand_color'] ?: '#E87C2A') ?>"
          id="brand_color" onchange="document.getElementById('brand_hex').value=this.value"
          style="width:44px;height:38px;border:1px solid #ddd;border-radius:5px;cursor:pointer;padding:2px">
        <input type="text" id="brand_hex" value="<?= esc_attr($o['brand_color'] ?: '#E87C2A') ?>"
          onchange="document.getElementById('brand_color').value=this.value"
          style="flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
      </div>
      <p style="font-size:11px;color:#888;margin-top:4px">CTA buttons, progress bar, accenti</p>
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Colore scuro</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="color" name="soser[brand_color_dark]" value="<?= esc_attr($o['brand_color_dark'] ?: '#1A1A1A') ?>"
          id="brand_dark" onchange="document.getElementById('brand_dark_hex').value=this.value"
          style="width:44px;height:38px;border:1px solid #ddd;border-radius:5px;cursor:pointer;padding:2px">
        <input type="text" id="brand_dark_hex" value="<?= esc_attr($o['brand_color_dark'] ?: '#1A1A1A') ?>"
          onchange="document.getElementById('brand_dark').value=this.value"
          style="flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
      </div>
      <p style="font-size:11px;color:#888;margin-top:4px">Header, CTA box, Garanzie</p>
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Anteprima</label>
      <div id="brand_preview" style="height:38px;border-radius:5px;background:<?= esc_attr($o['brand_color'] ?: '#E87C2A') ?>;display:flex;align-items:center;justify-content:center">
        <span style="color:#fff;font-size:13px;font-weight:700"><?= esc_html($o['business'] ?: 'Brand') ?></span>
      </div>
    </div>
  </div>
  <div style="margin-top:14px">
    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">URL Logo</label>
    <input type="url" name="soser[brand_logo_url]" value="<?= esc_attr($o['brand_logo_url']) ?>"
      placeholder="https://tuosito.com/wp-content/uploads/logo.png"
      style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
  </div>
</div>

<!-- ── SECTION 7: CTA ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>📣 CTA & Messaggi</h2>
  <div style="display:grid;gap:14px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">CTA principale</label>
      <input type="text" name="soser[cta]" value="<?= esc_attr($o['cta']) ?>"
        placeholder="es. Richiedi un preventivo gratuito entro 24 ore"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Messaggio urgenza</label>
      <input type="text" name="soser[cta_urgency]" value="<?= esc_attr($o['cta_urgency']) ?>"
        placeholder="es. Risposta entro 24h · Sopralluogo gratuito · Milano e provincia"
        style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
</div>

<!-- ── SECTION 8: Trust ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🏆 Trust & Social Proof</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Anni di attività</label>
      <input type="text" name="soser[trust_years]" value="<?= esc_attr($o['trust_years']) ?>"
        placeholder="es. 10+" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Clienti soddisfatti</label>
      <input type="text" name="soser[trust_clients]" value="<?= esc_attr($o['trust_clients']) ?>"
        placeholder="es. 500+" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">Valutazione (es. 4.8)</label>
      <input type="text" name="soser[trust_rating]" value="<?= esc_attr($o['trust_rating']) ?>"
        placeholder="4.8" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
    <div>
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px">N° recensioni</label>
      <input type="text" name="soser[trust_rating_count]" value="<?= esc_attr($o['trust_rating_count']) ?>"
        placeholder="47" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:14px">
    </div>
  </div>
</div>

<!-- ── SECTION 9: Garanzie ── -->
<div class="soser-card" style="max-width:780px;margin-bottom:20px">
  <h2>🛡️ Garanzie (Guarantee Box)</h2>
  <p style="font-size:12px;color:#888;margin-bottom:14px">Appaiono nel box scuro "Perché scegliere [nome azienda]?"</p>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <?php for($i=1;$i<=6;$i++): ?>
    <input type="text" name="soser[guarantee_<?= $i ?>]"
      value="<?= esc_attr($o['guarantee_'.$i] ?? '') ?>"
      placeholder="<?= ['🏆 10+ anni di esperienza','📋 Preventivo gratuito','✅ Materiali certificati','🛡️ 2 anni di garanzia','⚡ Risposta entro 24h','📍 Zona servita'][$i-1] ?>"
      style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px">
    <?php endfor; ?>
  </div>
</div>

<div style="max-width:780px">
  <?php submit_button('💾 Salva Business Profile', 'primary large', 'submit', true); ?>
</div>

</form>
</div>

<script>
const symbolMap = {EUR:'€',USD:'$',GBP:'£',AED:'د.إ',SAR:'ر.س',QAR:'ر.ق',KWD:'د.ك',
  EGP:'ج.م',MAD:'د.م',TRY:'₺',INR:'₹',AUD:'A$',CAD:'C$',CHF:'CHF',JPY:'¥'};
function updateSymbol(val) {
  if(symbolMap[val]) document.getElementById('currency_symbol').value = symbolMap[val];
}
// Live brand preview
['brand_color','brand_dark'].forEach(function(id) {
  var el = document.getElementById(id);
  if(el) el.addEventListener('input', function() {
    document.getElementById('brand_preview').style.background = document.getElementById('brand_color').value;
  });
});
</script>
