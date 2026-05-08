<?php
defined('ABSPATH') || exit;

// Save
if (isset($_POST['v9_save']) && check_admin_referer('v9_serp')) {
    if (class_exists('V9_SERPAnalyzer')) V9_SERPAnalyzer::save_config($_POST['v9'] ?? []);
    wp_redirect(admin_url('admin.php?page=soser-v9-serp&saved=1')); exit;
}

// Test
if (isset($_GET['action']) && $_GET['action'] === 'test' && class_exists('V9_SERPAnalyzer')) {
    $kw     = sanitize_text_field($_GET['kw'] ?? 'ristrutturazione bagno Milano');
    $result = V9_SERPAnalyzer::test($kw);
    wp_redirect(admin_url('admin.php?page=soser-v9-serp&test_ok=' . ($result['ok']?1:0) . '&test_msg=' . urlencode($result['error'] ?? "Trovati {$result['results']} risultati. Primo: {$result['first_url']}")));
    exit;
}

$cfg   = class_exists('V9_SERPAnalyzer') ? V9_SERPAnalyzer::get_config() : [];
$color = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
?>
<div class="wrap soser-wrap">
<h1>🔍 V9 SERP Analyzer</h1>
<p style="font-size:14px;color:#555;max-width:680px;line-height:1.8;margin-bottom:20px">
  Prima di scrivere ogni articolo, l'AI legge i <strong>top 10 risultati Google</strong> per la tua keyword —
  analizza H2, domande, prezzi e lunghezza dei competitor —
  poi scrive un articolo <strong>più lungo e più completo di tutti</strong>.
</p>

<?php if (isset($_GET['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>✅ Impostazioni salvate!</p></div>
<?php endif; ?>
<?php if (isset($_GET['test_ok'])): ?>
<div class="notice notice-<?= $_GET['test_ok']?'success':'error' ?> is-dismissible">
  <p><?= $_GET['test_ok']?'✅':'❌' ?> <?= esc_html(urldecode($_GET['test_msg']??'')) ?></p>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">

  <div>
    <div class="soser-card">
      <h2>⚙️ Configurazione</h2>

      <div style="background:#f0f9f0;border:1px solid #bbf7d0;border-radius:7px;padding:12px 16px;margin-bottom:16px;font-size:13px">
        <strong>Gratis: 100 analisi/giorno</strong><br>
        <span style="color:#666">Ogni articolo usa 1 analisi. Per $5 puoi avere 1000/giorno.</span>
      </div>

      <form method="post">
        <?php wp_nonce_field('v9_serp'); ?>
        <input type="hidden" name="v9_save" value="1">
        <table class="form-table" style="margin:0">
          <tr>
            <th>Abilita SERP Analyzer</th>
            <td>
              <label>
                <input type="checkbox" name="v9[enabled]" value="1" <?= checked($cfg['enabled']??'0','1',false) ?>>
                Analizza i competitor prima di scrivere
              </label>
              <p class="description">Se disabilitato, l'AI scrive senza analisi competitor</p>
            </td>
          </tr>
          <tr>
            <th>Google API Key</th>
            <td>
              <input type="text" name="v9[google_api_key]"
                value="<?= esc_attr($cfg['google_api_key']??'') ?>"
                class="regular-text" placeholder="AIza...">
              <p class="description">
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank">
                  Ottieni da Google Cloud Console →
                </a>
              </p>
            </td>
          </tr>
          <tr>
            <th>Search Engine ID (cx)</th>
            <td>
              <input type="text" name="v9[search_cx]"
                value="<?= esc_attr($cfg['search_cx']??'') ?>"
                class="regular-text" placeholder="a1b2c3d4e5f6g:xxx">
              <p class="description">
                <a href="https://programmablesearchengine.google.com" target="_blank">
                  Crea su programmablesearchengine.google.com →
                </a>
              </p>
            </td>
          </tr>
          <tr>
            <th>Competitor da leggere</th>
            <td>
              <select name="v9[max_urls]">
                <?php foreach(['3'=>'3 (veloce ~15 sec)','5'=>'5 (bilanciato ~25 sec)','7'=>'7 (completo ~40 sec)','10'=>'10 (massimo ~60 sec)'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= selected($cfg['max_urls']??'5',$v,false) ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
              <p class="description">Più competitor = articolo più completo ma più lento</p>
            </td>
          </tr>
          <tr>
            <th>Timeout per sito (sec)</th>
            <td>
              <input type="number" name="v9[fetch_timeout]"
                value="<?= esc_attr($cfg['fetch_timeout']??'15') ?>"
                min="5" max="30" style="width:70px">
            </td>
          </tr>
        </table>
        <button type="submit" class="button button-primary"
          style="margin-top:14px;background:<?= esc_attr($color) ?>;border-color:<?= esc_attr($color) ?>">
          💾 Salva impostazioni
        </button>
      </form>
    </div>

    <!-- Test -->
    <div class="soser-card" style="margin-top:16px">
      <h2>🧪 Testa la connessione</h2>
      <form method="get" action="<?= admin_url('admin.php') ?>">
        <input type="hidden" name="page" value="soser-v9-serp">
        <input type="hidden" name="action" value="test">
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" name="kw" value="ristrutturazione bagno Milano"
            class="regular-text" placeholder="Keyword di test">
          <button type="submit" class="button">🔍 Testa</button>
        </div>
      </form>
    </div>
  </div>

  <div>
    <!-- Come funziona -->
    <div class="soser-card">
      <h2>🔄 Come funziona</h2>
      <div style="font-size:13px;line-height:2.2;color:#444">
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px">
          <span style="background:<?= esc_attr($color) ?>;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">1</span>
          <span>Premi "Aggiungi alla coda" sul Dashboard</span>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px">
          <span style="background:<?= esc_attr($color) ?>;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">2</span>
          <span>L'AI cerca la keyword su Google → ottiene top 10 URL</span>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px">
          <span style="background:<?= esc_attr($color) ?>;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">3</span>
          <span>Legge ogni sito competitor e estrae:</span>
        </div>
        <div style="margin-right:32px;background:#f8f7f4;border-radius:7px;padding:10px 14px;font-size:12px;margin-bottom:8px">
          ✅ Tutti i titoli H2 e H3<br>
          ✅ Quante parole ha scritto<br>
          ✅ Quali domande risponde<br>
          ✅ Quali prezzi menziona<br>
          ✅ Keywords LSI usate
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px">
          <span style="background:<?= esc_attr($color) ?>;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">4</span>
          <span>Dà tutto questo all'AI insieme al prompt</span>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start">
          <span style="background:#16a34a;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">✓</span>
          <span><strong>L'AI scrive un articolo più lungo e completo di tutti i competitor</strong></span>
        </div>
      </div>
    </div>

    <!-- Setup guide -->
    <div class="soser-card" style="margin-top:16px">
      <h2>📋 Setup in 5 minuti</h2>
      <ol style="font-size:13px;line-height:2.4;color:#444;padding-right:16px">
        <li>Vai su <a href="https://console.cloud.google.com" target="_blank" style="color:<?= esc_attr($color) ?>">console.cloud.google.com</a></li>
        <li>New Project → Nome: <strong>soser-seo</strong></li>
        <li>APIs & Services → Enable → cerca <strong>"Custom Search API"</strong></li>
        <li>Credentials → Create → <strong>API Key</strong> → copia</li>
        <li>Vai su <a href="https://programmablesearchengine.google.com" target="_blank" style="color:<?= esc_attr($color) ?>">programmablesearchengine.google.com</a></li>
        <li>New Search Engine → Sites: <strong>*.com</strong> → Create</li>
        <li>Copia <strong>Search Engine ID</strong></li>
        <li>Incolla entrambi qui sopra → Salva ✅</li>
      </ol>
    </div>
  </div>
</div>
</div>
