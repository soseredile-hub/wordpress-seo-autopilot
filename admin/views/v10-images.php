<?php
defined('ABSPATH') || exit;

// Handle actions
if (isset($_POST['v10_img_save']) && check_admin_referer('v10_img')) {
    if (class_exists('V10_ImageProcessor')) V10_ImageProcessor::save_config($_POST['img'] ?? []);
    wp_redirect(admin_url('admin.php?page=soser-v10-images&saved=1')); exit;
}

// AJAX bulk process handled separately
$cfg   = class_exists('V10_ImageProcessor') ? V10_ImageProcessor::get_config() : [];
$stats = class_exists('V10_ImageProcessor') ? V10_ImageProcessor::stats() : [];
$color = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
$nonce = wp_create_nonce('soser_v10_bulk');
?>
<div class="wrap soser-wrap">
<h1>🖼️ Image Processor V10</h1>
<p style="font-size:13px;color:#555;max-width:700px;margin-bottom:20px;line-height:1.8">
  Ogni immagine caricata viene <strong>automaticamente</strong>: ridimensionata, compressa,
  migliorata (luminosità/contrasto) e descritta dall'AI (alt text + tag servizio).
</p>

<?php if (isset($_GET['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>✅ Salvato!</p></div>
<?php endif; ?>

<!-- Stats -->
<div class="soser-stats" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-num" style="color:#2271b1"><?= esc_html($stats['total'] ?? 0) ?></div>
    <div class="stat-lbl">📷 Totale immagini</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#00a32a"><?= esc_html($stats['processed'] ?? 0) ?></div>
    <div class="stat-lbl">✅ Elaborate</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:<?= esc_attr($color) ?>"><?= esc_html($stats['remaining'] ?? 0) ?></div>
    <div class="stat-lbl">⏳ Da elaborare</div>
  </div>
</div>

<!-- Bulk process -->
<?php if (($stats['remaining'] ?? 0) > 0): ?>
<div class="soser-card" style="max-width:650px;background:#fff9f0;border-color:#fde8c8;margin-bottom:20px">
  <h2>🚀 Elabora immagini esistenti</h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">
    Hai <strong><?= esc_html($stats['remaining']) ?></strong> immagini non ancora elaborate.
    Premi il pulsante per avviare — processa 20 immagini per volta.
  </p>
  <button class="button button-primary"
    style="background:<?= esc_attr($color) ?>;border-color:<?= esc_attr($color) ?>"
    id="v10-bulk-btn"
    onclick="startBulk()">
    🖼️ Avvia elaborazione (20 alla volta)
  </button>
  <div id="v10-bulk-progress" style="margin-top:12px;font-size:13px;color:#555"></div>
</div>
<?php else: ?>
<div class="soser-card" style="max-width:650px;background:#f0fdf4;border-color:#bbf7d0;margin-bottom:20px">
  <p style="color:#16a34a;font-weight:700">✅ Tutte le immagini sono state elaborate!</p>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">

  <!-- Settings -->
  <div class="soser-card">
    <h2>⚙️ Impostazioni</h2>
    <form method="post">
      <?php wp_nonce_field('v10_img'); ?>
      <input type="hidden" name="v10_img_save" value="1">
      <table class="form-table" style="margin:0">
        <tr>
          <th>Abilita elaborazione</th>
          <td><label><input type="checkbox" name="img[enabled]" value="1"
            <?= checked($cfg['enabled']??'1','1',false) ?>> Elabora automaticamente ogni upload</label></td>
        </tr>
        <tr>
          <th>Larghezza massima</th>
          <td>
            <select name="img[max_width]">
              <?php foreach(['1200'=>'1200px','1400'=>'1400px (consigliato)','1600'=>'1600px','1920'=>'1920px'] as $v=>$l): ?>
              <option value="<?=$v?>" <?=selected($cfg['max_width']??'1400',$v,false)?>><?=$l?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th>Qualità compressione</th>
          <td>
            <select name="img[quality]">
              <?php foreach(['72'=>'72% (piccolo)','80'=>'80%','82'=>'82% (bilanciato)','88'=>'88%','92'=>'92% (alta)'] as $v=>$l): ?>
              <option value="<?=$v?>" <?=selected($cfg['quality']??'82',$v,false)?>><?=$l?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th>Converti in WebP</th>
          <td><label><input type="checkbox" name="img[convert_webp]" value="1"
            <?= checked($cfg['convert_webp']??'1','1',false) ?>>
            Risparmia 60% di spazio</label></td>
        </tr>
        <tr>
          <th>Miglioramento automatico</th>
          <td><label><input type="checkbox" name="img[enhance]" value="1"
            <?= checked($cfg['enhance']??'1','1',false) ?>>
            Luminosità +<?=$cfg['brightness']??8?> · Contrasto +<?=$cfg['contrast']??15?></label></td>
        </tr>
        <tr>
          <th>AI Alt text</th>
          <td><label><input type="checkbox" name="img[ai_alt]" value="1"
            <?= checked($cfg['ai_alt']??'1','1',false) ?>>
            GPT-4o Vision (~$0.002 per immagine)</label></td>
        </tr>
      </table>
      <button type="submit" class="button button-primary"
        style="margin-top:12px;background:<?=esc_attr($color)?>;border-color:<?=esc_attr($color)?>">
        💾 Salva impostazioni
      </button>
    </form>
  </div>

  <!-- How it works -->
  <div class="soser-card">
    <h2>🔄 Come funziona</h2>
    <ol style="font-size:13px;line-height:2.4;color:#444;padding-right:16px">
      <li>Carichi una foto dal tuo cantiere</li>
      <li>La foto viene <strong>ridimensionata</strong> a <?=$cfg['max_width']??1400?>px</li>
      <li>Luminosità e contrasto migliorati automaticamente</li>
      <li>Compressa e convertita in <strong>WebP</strong> (più leggera)</li>
      <li>L'AI guarda la foto e scrive:
        <ul style="margin-right:16px;margin-top:4px">
          <li>✅ Alt text SEO</li>
          <li>✅ Titolo immagine</li>
          <li>✅ Didascalia</li>
          <li>✅ Tag servizio (bagno / cucina / ecc.)</li>
        </ul>
      </li>
      <li>La foto viene usata nei <strong>nuovi articoli</strong> del servizio corretto</li>
    </ol>
    <div style="background:#f8f7f4;border-radius:6px;padding:10px 12px;font-size:12px;color:#888;margin-top:8px">
      💰 Costo totale per 100 foto: ~$0.20
    </div>
  </div>
</div>

<script>
const AJAX  = '<?= esc_js(admin_url('admin-ajax.php')) ?>';
const NONCE = '<?= esc_js($nonce) ?>';

function startBulk() {
  const btn  = document.getElementById('v10-bulk-btn');
  const prog = document.getElementById('v10-bulk-progress');
  btn.disabled = true;
  btn.textContent = '⏳ Elaborando...';

  function runBatch() {
    prog.innerHTML = '⏳ Elaborazione in corso...';
    fetch(AJAX, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action: 'soser_v10_bulk_images', nonce: NONCE})
    })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        prog.innerHTML = '❌ ' + (res.data || 'Errore');
        btn.disabled = false;
        btn.textContent = '🖼️ Riprova';
        return;
      }
      const d = res.data;
      prog.innerHTML = `✅ Elaborate: <strong>${d.done}</strong> · Rimanenti: <strong>${d.remaining}</strong>`;

      if (d.remaining > 0) {
        setTimeout(runBatch, 1500);
      } else {
        btn.textContent = '✅ Completato!';
        prog.innerHTML  = '🎉 Tutte le immagini elaborate!';
      }
    })
    .catch(err => {
      prog.innerHTML = '❌ ' + err.message;
      btn.disabled = false;
    });
  }

  runBatch();
}
</script>
</div>
