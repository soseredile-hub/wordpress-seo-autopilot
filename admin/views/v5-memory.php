<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🧬 AI Memory Engine</h1>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $stats['total']??0 ?></div><div class="stat-lbl">Articoli</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= number_format($stats['words']??0) ?></div><div class="stat-lbl">Parole totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= round((float)($stats['avg_score']??0),1) ?></div><div class="stat-lbl">Score medio</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#d63638"><?= $stats['to_refresh']??0 ?></div><div class="stat-lbl">Da aggiornare</div></div>
</div>
<div style="margin-bottom:16px">
  <button class="button button-primary" id="v5-sync-memory">🔄 Sincronizza memoria</button>
  <span id="v5-sync-result" style="font-size:12px;margin-right:8px"></span>
</div>
<table class="soser-table widefat striped">
  <thead><tr><th>Articolo</th><th>Keyword</th><th>Topic</th><th>Intent</th><th>Parole</th><th>Score</th><th>Impressioni</th><th>CTR</th><th>Pos.</th><th>Refresh</th></tr></thead>
  <tbody>
  <?php foreach ($memory as $m): ?>
  <tr<?= $m['needs_refresh']?' style="background:#fff8e1"':'' ?>>
    <td><a href="<?= get_edit_post_link($m['post_id']) ?>" target="_blank"><?= esc_html(mb_substr(get_the_title($m['post_id']),0,35)) ?></a></td>
    <td style="font-size:11px"><?= esc_html(mb_substr($m['keyword'],0,30)) ?></td>
    <td><span class="chip" style="background:#f0f6fc;border:1px solid #b8d4f0;border-radius:10px;padding:2px 8px;font-size:10px"><?= esc_html($m['topic']) ?></span></td>
    <td style="font-size:11px"><?= esc_html($m['intent']) ?></td>
    <td><?= number_format($m['word_count']) ?></td>
    <td><?= $m['seo_score']>0 ? $m['seo_score'].'/100' : '—' ?></td>
    <td><?= number_format($m['gsc_impr']) ?></td>
    <td><?= $m['gsc_ctr']>0 ? round($m['gsc_ctr']*100,1).'%' : '—' ?></td>
    <td><?= $m['gsc_position']>0 ? round($m['gsc_position'],1) : '—' ?></td>
    <td><?= $m['needs_refresh']?'<button class="button button-small v5-refresh-btn" data-pid="'.(int)$m['post_id'].'">🔄</button>':'—' ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
