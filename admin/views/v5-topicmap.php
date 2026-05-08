<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🗺️ Topic Authority Map</h1>
<p style="font-size:13px;color:#555;margin-bottom:20px">Mappa semantica dei tuoi contenuti. Pillar pages + Cluster articles basati sul significato reale.</p>
<?php if (empty($map)): ?>
<div class="soser-info-bar">⚠️ Aggiungi seed keywords nelle Impostazioni per generare la mappa.</div>
<?php else: ?>
<div class="soser-grid-2">
<?php foreach ($map as $item): ?>
<div class="soser-card">
  <div class="card-head" style="background:#f0f6fc">🏛️ PILLAR: <strong><?= esc_html($item['topic']) ?></strong></div>
  <div style="padding:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:13px"><?= esc_html($item['pillar']) ?></strong>
      <form method="post" action="<?= admin_url('admin-post.php') ?>">
        <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
        <input type="hidden" name="keyword" value="<?= esc_attr($item['pillar']) ?>">
        <button class="button button-primary button-small">✍️ Scrivi Pillar</button>
      </form>
    </div>
    <?php if (!empty($item['clusters'])): ?>
    <div style="padding-right:16px;border-right:3px solid #e0e0e0">
      <strong style="font-size:11px;color:#888">CLUSTER ARTICLES:</strong>
      <?php foreach ($item['clusters'] as $ck): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f5f5f5">
        <span style="font-size:12px">↳ <?= esc_html($ck) ?></span>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($ck) ?>">
          <button class="button button-small">Scrivi</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
