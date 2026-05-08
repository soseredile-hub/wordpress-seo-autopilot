<?php
defined('ABSPATH') || exit;
?>
<div class="wrap soser-wrap">
<h1>🧠 Keyword Intelligence</h1>
<?php if (isset($_GET['refreshed'])): ?><div class="notice notice-success is-dismissible"><p>✅ Analisi completata!</p></div><?php endif; ?>

<form method="post" action="<?= admin_url('admin-post.php') ?>" style="margin-bottom:16px">
  <?php wp_nonce_field('soser_v4_discover') ?><input type="hidden" name="action" value="soser_v4_discover">
  <button class="button button-primary">🔄 Riesegui analisi keyword</button>
  <span style="font-size:12px;color:#888;margin-right:8px">Ultima analisi: <?php
    $ts = get_option('_transient_timeout_soser_v4_keyword_intel');
    echo $ts ? wp_date('d/m H:i', $ts - 2*HOUR_IN_SECONDS) : 'Mai';
  ?></span>
</form>

<?php if (empty($cached)): ?>
<div class="soser-card"><p>⚠️ Nessuna analisi disponibile. Clicca "Riesegui analisi keyword" per iniziare.</p></div>
<?php else:
  $uncovered = array_filter($cached, fn($r) => !$r['covered']);
  $best = reset($uncovered);
?>

<?php if ($best): ?>
<div class="soser-info-bar" style="background:#e8f9e8;border-color:#00a32a">
  🏆 <strong>Migliore opportunità:</strong> <?= esc_html($best['keyword']) ?> —
  Score: <strong><?= $best['opportunity_score'] ?>/100</strong> |
  <?= esc_html($best['recommendation']) ?> |
  <?= esc_html($best['intent']) ?>
  <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline;margin-right:12px">
    <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
    <input type="hidden" name="keyword" value="<?= esc_attr($best['keyword']) ?>">
    <button class="button button-primary button-small">Scrivi ora →</button>
  </form>
</div>
<?php endif; ?>

<table class="soser-table widefat striped" style="margin-top:16px">
  <thead>
    <tr>
      <th>#</th><th>Keyword</th><th>Coperta</th><th>Opportunità</th>
      <th>Volume est.</th><th>Intent</th><th>CPC est.</th>
      <th>Locale</th><th>Difficoltà (inv.)</th><th>Raccomandazione</th><th>Azione</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach (array_slice($cached, 0, 60) as $i => $r): ?>
    <tr<?= $r['covered'] ? ' style="opacity:.5"' : '' ?>>
      <td><?= $i+1 ?></td>
      <td><strong><?= esc_html($r['keyword']) ?></strong></td>
      <td><?= $r['covered'] ? '✅ Sì' : '🆕 No' ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <div style="width:<?= $r['opportunity_score'] ?>px;height:8px;background:<?= $r['opportunity_score']>=65?'#00a32a':($r['opportunity_score']>=45?'#f0a500':'#d63638') ?>;border-radius:4px;max-width:100px"></div>
          <strong><?= $r['opportunity_score'] ?>/100</strong>
        </div>
      </td>
      <td><?= esc_html($r['volume_estimate']) ?></td>
      <td><?= esc_html($r['intent']) ?></td>
      <td><?= esc_html($r['cpc_estimate']) ?></td>
      <td><?= $r['local_score'] ?>/25</td>
      <td><?= $r['difficulty_score'] ?>/15</td>
      <td><?= esc_html($r['recommendation']) ?></td>
      <td>
        <?php if (!$r['covered']): ?>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($r['keyword']) ?>">
          <button class="button button-small">Scrivi</button>
        </form>
        <?php else: echo '<span style="color:#888">Già coperta</span>'; endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php

// ─────────────────────────────────────────────────────────
