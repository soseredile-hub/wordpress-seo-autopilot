<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>📍 Local SEO — Milano & Provincia</h1>
<p style="font-size:13px;color:#555;margin-bottom:20px">Struttura silo e ottimizzazione locale per dominare le ricerche a Milano.</p>

<div class="soser-grid-2">
  <div class="soser-card">
    <h2>🗂️ Struttura Silo</h2>
    <?php if (empty($silos)): ?>
    <p style="color:#888">Nessun silo rilevato. Scrivi almeno 3 articoli per costruire un silo.</p>
    <?php else: ?>
    <?php foreach ($silos as $topic => $silo): ?>
    <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0">
      <strong style="font-size:13px">🏛️ <?= esc_html(ucfirst($topic)) ?></strong>
      <span style="font-size:11px;color:#888;margin-right:8px">(<?= count($silo['posts']) ?> articoli)</span>
      <?php if ($silo['pillar_id']): ?>
      <span style="font-size:11px;background:#e8f4ff;color:#1a5c9a;padding:1px 6px;border-radius:8px">Pillar: <?= esc_html(mb_substr(get_the_title($silo['pillar_id']),0,30)) ?></span>
      <?php endif; ?>
      <div style="padding-right:12px;margin-top:6px">
        <?php foreach (array_slice($silo['posts'],0,4) as $p): ?>
        <div style="font-size:11px;color:#555;padding:2px 0">↳ <a href="<?= get_edit_post_link($p['post_id']) ?>"><?= esc_html(mb_substr(get_the_title($p['post_id']),0,45)) ?></a></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="soser-card">
    <h2>📍 Zone Servite</h2>
    <p style="font-size:13px;color:#555;margin-bottom:12px">Aggiunte automaticamente in ogni articolo per il Local SEO.</p>
    <?php $zones = ['Milano Centro','Navigli','Porta Romana','Isola','Città Studi','Sesto San Giovanni','Monza','Cinisello Balsamo','Rho','Legnano','Corsico','Assago']; ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($zones as $z): ?>
      <span style="background:#e8f9e8;border:1px solid #7abca8;border-radius:10px;padding:3px 10px;font-size:11px;color:#1a5c3a">📍 <?= esc_html($z) ?></span>
      <?php endforeach; ?>
    </div>
    <hr style="margin:16px 0">
    <h3 style="font-size:13px">📊 Local Score articoli</h3>
    <?php foreach (array_slice($memory,0,8) as $m): ?>
    <?php $ls = class_exists('V5_LocalSEO') ? V5_LocalSEO::local_score($m['keyword']) : 0; ?>
    <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0">
      <span><?= esc_html(mb_substr($m['keyword'],0,30)) ?></span>
      <span style="color:<?= $ls>=50?'#00a32a':($ls>=25?'#f0a500':'#d63638') ?>;font-weight:600"><?= $ls ?>/100</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>
