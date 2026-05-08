<?php defined('ABSPATH') || exit;
$p = class_exists('V6_Profile') ? V6_Profile::get() : [];
$langs = class_exists('V6_Language') ? V6_Language::supported() : ['en'=>'English'];
?>
<div class="wrap soser-wrap">
<h1>🌍 Business Profile</h1>
<p style="color:#555;font-size:14px;max-width:700px;margin-bottom:24px;line-height:1.7">
  Configura qui la tua azienda. Tutti gli articoli generati useranno automaticamente questi dati — 
  nome, città, colori, lingua, valuta. <strong>Funziona per qualsiasi paese e qualsiasi settore.</strong>
</p>

<?php if (isset($_GET['saved'])): ?>
<div class="notice notice-success"><p>✅ Profilo salvato con successo!</p></div>
<?php endif; ?>

<form method="post" action="<?= admin_url('admin-post.php') ?>">
<?php wp_nonce_field('soser_v6_profile_save'); ?>
<input type="hidden" name="action" value="soser_v6_profile_save">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">

  <!-- Col 1 -->
  <div>
    <div class="soser-card">
      <h2>🏢 Identità Aziendale</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Nome Azienda *</th>
          <td><input type="text" name="v6[name]" value="<?= esc_attr($p['name']??'') ?>" class="regular-text" placeholder="es. SOSER, Ahmed Contracting, BuildPro..."></td>
        </tr>
        <tr>
          <th>Tagline</th>
          <td><input type="text" name="v6[tagline]" value="<?= esc_attr($p['tagline']??'') ?>" class="regular-text" placeholder="es. Ristrutturazioni chiavi in mano"></td>
        </tr>
        <tr>
          <th>Settore / Industry *</th>
          <td><input type="text" name="v6[industry]" value="<?= esc_attr($p['industry']??'') ?>" class="regular-text" placeholder="es. ristrutturazioni, plumbing, real estate..."></td>
        </tr>
        <tr>
          <th>Descrizione breve</th>
          <td><textarea name="v6[description]" rows="2" class="regular-text" placeholder="Chi siete in 1-2 righe"><?= esc_textarea($p['description']??'') ?></textarea></td>
        </tr>
        <tr>
          <th>Schema Type</th>
          <td>
            <select name="v6[schema_type]">
              <?php foreach (['LocalBusiness','Contractor','HomeAndConstructionBusiness','Plumber','ElectricianBusiness','RoofingContractor','ProfessionalService','MedicalBusiness','LegalService','Restaurant','Store','GeneralContractor'] as $t): ?>
              <option value="<?= $t ?>" <?= selected($p['schema_type']??'LocalBusiness',$t,false) ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      </table>
    </div>

    <div class="soser-card" style="margin-top:16px">
      <h2>📍 Posizione</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Città / City *</th>
          <td><input type="text" name="v6[city]" value="<?= esc_attr($p['city']??'') ?>" class="regular-text" placeholder="es. Milano, Dubai, London, Cairo..."></td>
        </tr>
        <tr>
          <th>Paese / Country *</th>
          <td><input type="text" name="v6[country]" value="<?= esc_attr($p['country']??'') ?>" class="regular-text" placeholder="es. Italia, UAE, UK, Egypt..."></td>
        </tr>
        <tr>
          <th>Indirizzo</th>
          <td><input type="text" name="v6[address]" value="<?= esc_attr($p['address']??'') ?>" class="regular-text" placeholder="Via Roma 1, Milano"></td>
        </tr>
      </table>
    </div>

    <div class="soser-card" style="margin-top:16px">
      <h2>📞 Contatti</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Telefono *</th>
          <td><input type="text" name="v6[phone]" value="<?= esc_attr($p['phone']??'') ?>" class="regular-text" placeholder="+39 02 1234567"></td>
        </tr>
        <tr>
          <th>WhatsApp</th>
          <td><input type="text" name="v6[whatsapp]" value="<?= esc_attr($p['whatsapp']??'') ?>" class="regular-text" placeholder="+393901234567 (con prefisso paese)"><p class="description">Lascia vuoto per usare il telefono</p></td>
        </tr>
        <tr>
          <th>Email</th>
          <td><input type="email" name="v6[email]" value="<?= esc_attr($p['email']??'') ?>" class="regular-text"></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Col 2 -->
  <div>
    <div class="soser-card">
      <h2>🌐 Lingua e Valuta</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Lingua articoli *</th>
          <td>
            <select name="v6[language]" style="font-size:15px">
              <?php foreach ($langs as $code => $label): ?>
              <option value="<?= $code ?>" <?= selected($p['language']??'it',$code,false) ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <p class="description">Tutti gli articoli verranno scritti in questa lingua</p>
          </td>
        </tr>
        <tr>
          <th>Valuta</th>
          <td>
            <select name="v6[currency]">
              <?php foreach (['EUR'=>'EUR — Euro','USD'=>'USD — Dollar','GBP'=>'GBP — Pound','AED'=>'AED — Dirham','SAR'=>'SAR — Riyal','EGP'=>'EGP — Pound','MAD'=>'MAD — Dirham','TRY'=>'TRY — Lira','INR'=>'INR — Rupee','BRL'=>'BRL — Real','MXN'=>'MXN — Peso'] as $code=>$label): ?>
              <option value="<?= $code ?>" <?= selected($p['currency']??'EUR',$code,false) ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th>Simbolo valuta</th>
          <td><input type="text" name="v6[currency_symbol]" value="<?= esc_attr($p['currency_symbol']??'€') ?>" style="width:60px" placeholder="€"></td>
        </tr>
      </table>
    </div>

    <div class="soser-card" style="margin-top:16px">
      <h2>🎨 Branding</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Colore principale</th>
          <td>
            <div style="display:flex;gap:10px;align-items:center">
              <input type="color" name="v6[color]" value="<?= esc_attr($p['color']??'#e87c2a') ?>" style="width:50px;height:36px;cursor:pointer;border:none">
              <input type="text" name="v6[color_hex]" value="<?= esc_attr($p['color']??'#e87c2a') ?>" style="width:90px" placeholder="#e87c2a" id="color-hex-input">
            </div>
            <p class="description">Colore per CTA, bordi, highlights — cambierà tutto automaticamente</p>
          </td>
        </tr>
        <tr>
          <th>Colore scuro</th>
          <td>
            <input type="color" name="v6[color_dark]" value="<?= esc_attr($p['color_dark']??'#1a1a1a') ?>" style="width:50px;height:36px;cursor:pointer;border:none">
            <p class="description">Per header, garanzie box, CTA scuri</p>
          </td>
        </tr>
      </table>

      <!-- Live preview -->
      <div style="margin-top:16px;padding:16px;border-radius:8px;background:#f8f7f4;border:1px solid #e8e5e0">
        <p style="font-size:12px;color:#888;margin-bottom:8px">Anteprima CTA:</p>
        <div id="cta-preview" style="background:<?= esc_attr($p['color_dark']??'#1a1a1a') ?>;padding:14px 18px;border-radius:8px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <span style="color:#fff;font-size:14px;font-weight:600">Preventivo gratuito entro 24h</span>
          <a href="#" id="btn-preview" style="background:<?= esc_attr($p['color']??'#e87c2a') ?>;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:700">📞 Chiama ora</a>
          <a href="#" style="background:#25d366;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:700">💬 WhatsApp</a>
        </div>
      </div>
    </div>

    <div class="soser-card" style="margin-top:16px">
      <h2>📊 Statistiche & Trust</h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Anni esperienza</th>
          <td><input type="text" name="v6[years_exp]" value="<?= esc_attr($p['years_exp']??'') ?>" style="width:80px" placeholder="10+"></td>
        </tr>
        <tr>
          <th>Clienti soddisfatti</th>
          <td><input type="text" name="v6[clients_count]" value="<?= esc_attr($p['clients_count']??'') ?>" style="width:80px" placeholder="50+"></td>
        </tr>
        <tr>
          <th>Valutazione media</th>
          <td><input type="text" name="v6[rating]" value="<?= esc_attr($p['rating']??'') ?>" style="width:80px" placeholder="4.8"></td>
        </tr>
        <tr>
          <th>Risposta entro (ore)</th>
          <td><input type="text" name="v6[response_hours]" value="<?= esc_attr($p['response_hours']??'24') ?>" style="width:80px" placeholder="24"></td>
        </tr>
      </table>
    </div>

    <div class="soser-card" style="margin-top:16px">
      <h2>✏️ CTA personalizzate <small style="font-weight:400;color:#888">(opzionale)</small></h2>
      <table class="form-table" style="margin:0">
        <tr>
          <th>Testo preventivo</th>
          <td><input type="text" name="v6[cta_quote]" value="<?= esc_attr($p['cta_quote']??'') ?>" class="regular-text" placeholder="Auto (basato su lingua)"></td>
        </tr>
        <tr>
          <th>Testo Chiama</th>
          <td><input type="text" name="v6[cta_call]" value="<?= esc_attr($p['cta_call']??'') ?>" class="regular-text" placeholder="Auto (basato su lingua)"></td>
        </tr>
        <tr>
          <th>Trust line</th>
          <td><input type="text" name="v6[trust_line]" value="<?= esc_attr($p['trust_line']??'') ?>" class="regular-text" placeholder="Auto (basato su lingua e città)"></td>
        </tr>
      </table>
    </div>
  </div>
</div><!-- grid -->

<p style="margin-top:24px">
  <button type="submit" class="button button-primary button-large" style="background:var(--soser-color,#e87c2a);border-color:var(--soser-color,#e87c2a);font-size:15px;padding:8px 28px">
    💾 Salva Business Profile
  </button>
</p>
</form>

<!-- Profile summary preview -->
<?php if (!empty($p['name'])): ?>
<div class="soser-card" style="max-width:700px;margin-top:24px;background:#f0f9f0;border-color:#00a32a">
  <h2>✅ Profilo attivo</h2>
  <div style="display:flex;flex-wrap:wrap;gap:20px;font-size:13px">
    <div><strong>Azienda:</strong> <?= esc_html($p['name']) ?></div>
    <div><strong>Settore:</strong> <?= esc_html($p['industry']) ?></div>
    <div><strong>Città:</strong> <?= esc_html($p['city']) ?>, <?= esc_html($p['country']) ?></div>
    <div><strong>Lingua:</strong> <?= esc_html($p['language']) ?></div>
    <div><strong>Valuta:</strong> <?= esc_html($p['currency_symbol']) ?> <?= esc_html($p['currency']) ?></div>
    <div><strong>Telefono:</strong> <?= esc_html($p['phone']) ?></div>
  </div>
  <p style="margin-top:12px;font-size:13px;color:#1a7a1a">
    🌍 I nuovi articoli useranno automaticamente: <strong><?= esc_html($p['name']) ?></strong> · <strong><?= esc_html($p['city']) ?></strong> · <strong><?= esc_html($p['language']) ?></strong> · <strong><?= esc_html($p['currency_symbol']) ?></strong>
  </p>
</div>
<?php endif; ?>

<script>
// Sync color picker with text field
document.querySelector('[name="v6[color]"]')?.addEventListener('input', function() {
  document.getElementById('color-hex-input').value = this.value;
  document.getElementById('btn-preview').style.background = this.value;
});
document.getElementById('color-hex-input')?.addEventListener('input', function() {
  if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
    document.querySelector('[name="v6[color]"]').value = this.value;
    document.getElementById('btn-preview').style.background = this.value;
  }
});
</script>
</div>
