<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>📊 Google Search Console</h1>

<?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>✅ Credenziali salvate.</p></div><?php endif; ?>
<?php if (isset($_GET['connected'])): ?><div class="notice notice-success is-dismissible"><p>✅ Connesso a Google Search Console!</p></div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="notice notice-error is-dismissible"><p>❌ Errore autenticazione. Riprova.</p></div><?php endif; ?>

<div class="soser-grid-2">
  <!-- Step 1 -->
  <div class="soser-card">
    <h2>① Credenziali Google Cloud</h2>
    <p style="font-size:13px;color:#555;margin-bottom:10px">
      Vai su <a href="https://console.cloud.google.com/" target="_blank"><strong>Google Cloud Console</strong></a> →
      crea un progetto → abilita <strong>Search Console API</strong> → crea credenziali
      <strong>OAuth 2.0 Web Application</strong> → aggiungi questo URI:
    </p>
    <code style="background:#f6f7f7;padding:6px 10px;border-radius:4px;font-size:11px;display:block;margin-bottom:12px;word-break:break-all"><?= esc_html(V4_GSC::get_redirect_uri()) ?></code>
    <form method="post" action="<?= admin_url('admin-post.php') ?>">
      <?php wp_nonce_field('soser_v4_gsc_save') ?>
      <input type="hidden" name="action" value="soser_v4_gsc_save">
      <table class="form-table">
        <tr><th>Client ID</th><td><input type="text" name="gsc_client_id" value="<?= esc_attr($creds['client_id']) ?>" class="large-text" placeholder="xxxx.apps.googleusercontent.com"></td></tr>
        <tr><th>Client Secret</th><td><input type="password" name="gsc_client_secret" value="<?= $creds['client_secret'] ? '••••••••' : '' ?>" class="regular-text" autocomplete="off"></td></tr>
        <tr><th>URL Sito</th><td>
          <input type="url" name="gsc_site_url" value="<?= esc_attr($site_url) ?>" class="regular-text" placeholder="https://www.soser.it/">
          <p class="description">Esattamente come appare in Search Console</p>
        </td></tr>
      </table>
      <?php submit_button('💾 Salva credenziali', 'secondary'); ?>
    </form>
  </div>

  <!-- Step 2 -->
  <div class="soser-card">
    <h2>② Connetti a Google</h2>
    <?php if ($connected): ?>
      <div style="background:#edfaef;border:1px solid #00a32a;border-radius:6px;padding:12px;margin-bottom:14px">
        <strong style="color:#00a32a">✅ Connesso!</strong> Sito: <?= esc_html($site_url) ?>
      </div>
      <button class="button button-primary" id="soser-gsc-refresh">🔄 Aggiorna keyword da GSC</button>
      <span id="soser-gsc-refresh-result" style="font-size:12px;margin-right:8px"></span>
      <hr style="margin:14px 0">
      <button type="button" class="button" id="soser-gsc-disconnect">🔌 Disconnetti</button>
      <div id="soser-gsc-preview" style="margin-top:14px;display:none">
        <strong style="font-size:12px">Top opportunità:</strong>
        <div id="soser-gsc-top-list" style="margin-top:6px;font-size:12px"></div>
      </div>
    <?php elseif ($configured): ?>
      <p style="font-size:13px;color:#555;margin-bottom:14px">
        Clicca per autorizzare l'accesso in sola lettura alle keyword del tuo sito.
      </p>
      <a href="<?= esc_url($auth_url) ?>" class="button button-primary button-large">🔗 Connetti Google Search Console</a>
    <?php else: ?>
      <p style="color:#888;font-size:13px">Prima salva le credenziali Google Cloud (①).</p>
    <?php endif; ?>
  </div>
</div>

<?php if ($connected): ?>
<div class="soser-card">
  <h2>🎯 Opportunità keyword reali dal tuo sito</h2>
  <?php $opps = V4_GSC::get_keyword_opportunities($site_url); ?>
  <?php if (empty($opps)): ?>
    <div class="soser-info-bar">⏳ Clicca "Aggiorna keyword da GSC" per caricare i dati.</div>
  <?php else: ?>
  <p style="font-size:12px;color:#888;margin-bottom:12px">Trovate <strong><?= count($opps) ?></strong> opportunità — aggiornate ogni 6 ore.</p>
  <table class="soser-table widefat striped">
    <thead><tr><th>#</th><th>Keyword</th><th>Tipo</th><th>Impressioni</th><th>Click</th><th>CTR</th><th>Posizione</th><th>Coperta</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($opps, 0, 50) as $i => $opp): ?>
    <tr<?= $opp['covered'] ? ' style="opacity:.55"' : '' ?>>
      <td><?= $i+1 ?></td>
      <td><strong><?= esc_html($opp['keyword']) ?></strong></td>
      <td><?= esc_html($opp['type']) ?></td>
      <td><?= number_format($opp['impressions']) ?></td>
      <td><?= number_format($opp['clicks']) ?></td>
      <td><?= esc_html($opp['ctr']) ?></td>
      <td><?= esc_html($opp['position']) ?></td>
      <td><?= $opp['covered'] ? '✅' : '🆕' ?></td>
      <td><?php if (!$opp['covered']): ?>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($opp['keyword']) ?>">
          <button class="button button-small">✍️ Scrivi</button>
        </form>
      <?php else: ?><span style="color:#888;font-size:11px">Già coperta</span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>
