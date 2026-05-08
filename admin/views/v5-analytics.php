<?php defined('ABSPATH') || exit; $r=$report; ?>
<div class="wrap soser-wrap">
<h1>📊 Analytics V5 — AI Dashboard</h1>
<?php foreach ($r['alerts']??[] as $a): ?>
<div class="notice notice-<?= $a['type']==='warning'?'warning':'info' ?> is-dismissible"><p><?= esc_html($a['msg']) ?></p></div>
<?php endforeach; ?>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $r['content']['articles'] ?></div><div class="stat-lbl">Articoli</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= number_format($r['performance']['total_clicks']) ?></div><div class="stat-lbl">Click totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#7c3aed"><?= $r['performance']['avg_ctr'] ?></div><div class="stat-lbl">CTR medio</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= $r['performance']['avg_position'] ?></div><div class="stat-lbl">Posizione media</div></div>
</div>
<div class="soser-grid-2">
  <div class="soser-card">
    <h2>💰 Costi API (ultimi 7 giorni)</h2>
    <p style="font-size:22px;font-weight:900;color:#2271b1">$<?= $r['cost']['today_usd'] ?> <span style="font-size:13px;font-weight:400;color:#888">oggi</span></p>
    <p style="font-size:13px;color:#555">Ultimi 30 giorni: <strong>$<?= $r['cost']['last_30d_usd'] ?></strong> | Budget: $<?= $r['cost']['budget'] ?>/giorno</p>
    <table class="soser-table" style="margin-top:10px">
      <thead><tr><th>Data</th><th>USD</th><th>Chiamate</th><th>Token</th></tr></thead>
      <tbody>
      <?php foreach (array_slice(array_reverse($r['cost']['daily_chart']),0,7) as $d): ?>
      <tr><td><?= esc_html($d['date']) ?></td><td>$<?= $d['usd'] ?></td><td><?= $d['calls'] ?></td><td><?= number_format($d['tokens']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="soser-card">
    <h2>🏆 Top articoli</h2>
    <?php if (empty($r['performance']['top_articles'])): ?>
    <p style="color:#888;font-size:13px">Collega Search Console per vedere i dati.</p>
    <?php else: ?>
    <?php foreach ($r['performance']['top_articles'] as $top): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:12px">
      <a href="<?= get_edit_post_link($top['post_id']) ?>"><?= esc_html(mb_substr($top['keyword'],0,30)) ?></a>
      <span><strong><?= $top['gsc_clicks'] ?></strong> click</span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</div>
