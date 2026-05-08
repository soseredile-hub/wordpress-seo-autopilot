<?php
defined('ABSPATH') || exit;
?>
<div class="wrap soser-wrap">
<h1>⚙️ Coda & Log</h1>
<?php if (isset($_GET['processed'])): ?><div class="notice notice-success is-dismissible"><p>✅ Job processato.</p></div><?php endif; ?>
<?php if (isset($_GET['cleared'])): ?><div class="notice notice-success is-dismissible"><p>✅ Log pulito.</p></div><?php endif; ?>

<div class="soser-stats">
  <?php foreach (['pending'=>'In coda','running'=>'Running','done'=>'Done','failed'=>'Falliti'] as $k=>$l): ?>
  <div class="stat-card"><div class="stat-num" style="color:<?= $k==='done'?'#00a32a':($k==='failed'?'#d63638':($k==='running'?'#f0a500':'#2271b1')) ?>"><?= $stats[$k] ?></div><div class="stat-lbl"><?= $l ?></div></div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:10px;margin-bottom:16px">
  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <?php wp_nonce_field('soser_v4_process') ?><input type="hidden" name="action" value="soser_v4_process">
    <button class="button button-primary">⚙️ Processa prossimo</button>
  </form>
  <form method="post" action="<?= admin_url('admin-post.php') ?>">
    <?php wp_nonce_field('soser_v4_clear') ?><input type="hidden" name="action" value="soser_v4_clear">
    <button class="button">🗑️ Pulisci done/failed</button>
  </form>
</div>

<table class="soser-table widefat striped">
  <thead><tr><th>ID</th><th>Keyword</th><th>Sorgente</th><th>Status</th><th>Step</th><th>Articolo</th><th>Messaggio</th><th>Data</th></tr></thead>
  <tbody>
    <?php foreach ($log as $r): ?>
    <tr>
      <td><?= $r->id ?></td>
      <td><strong><?= esc_html($r->keyword ?: '(auto)') ?></strong></td>
      <td><?= esc_html($r->source) ?></td>
      <td><span class="soser-badge soser-badge-<?= esc_attr($r->status) ?>"><?= esc_html($r->status) ?></span></td>
      <td><code><?= esc_html($r->step) ?></code></td>
      <td><?php $pid = $r->post_id ?? 0; echo $pid ? '<a href="'.get_edit_post_link($pid).'" target="_blank">#'.$pid.'</a>' : '—'; ?></td>
      <td style="font-size:11px;color:#666;max-width:250px"><?= esc_html(mb_substr($r->message,0,80)) ?></td>
      <td style="font-size:11px"><?= esc_html(substr($r->created_at,0,16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php

// ─────────────────────────────────────────────────────────
