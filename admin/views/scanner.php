<?php
defined('ABSPATH') || exit;
?>
<div class="wrap soser-wrap">
<h1>🗂️ Content Scanner</h1>
<p>Analisi del contenuto esistente per rilevare keyword usate e intent.</p>
<button class="button button-primary" id="soser-scan-site">🔄 Riscansiona ora</button>
<span id="soser-scan-result" style="margin-right:8px;font-size:13px;font-weight:600"></span>
<?php if (empty($map)): ?>
<div class="soser-card" style="margin-top:16px"><p>Clicca "Riscansiona ora" per analizzare i contenuti del sito.</p></div>
<?php else: ?>
<p style="color:#888;font-size:12px;margin-top:8px">Trovati <strong><?= count($map) ?></strong> contenuti. Aggiornato ogni ora.</p>
<table class="soser-table widefat striped" style="margin-top:16px">
  <thead><tr><th>Titolo</th><th>Stato</th><th>Intent</th><th>Frasi chiave rilevate</th><th>Azione</th></tr></thead>
  <tbody>
    <?php foreach ($map as $post): ?>
    <tr>
      <td><a href="<?= esc_url($post['url']) ?>" target="_blank"><?= esc_html(mb_substr($post['title'],0,55)) ?></a></td>
      <td><?= esc_html($post['status']) ?></td>
      <td><?= esc_html($post['intent']) ?></td>
      <td style="font-size:11px;color:#666"><?= esc_html(implode(', ', array_slice($post['phrases'],0,6))) ?>…</td>
      <td><a href="<?= get_edit_post_link($post['id']) ?>" class="button button-small">Modifica</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php

// ─────────────────────────────────────────────────────────
