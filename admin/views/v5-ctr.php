<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>⚡ CTR Optimizer</h1>
<p style="font-size:13px;color:#555;margin-bottom:20px">Ottimizza meta description e titoli degli articoli con basso CTR usando l'AI.</p>
<div style="margin-bottom:16px">
  <button class="button button-primary" id="v5-bulk-ctr">⚡ Ottimizza i 5 peggiori</button>
  <span id="v5-ctr-result" style="font-size:12px;margin-right:8px"></span>
</div>
<table class="soser-table widefat striped">
  <thead><tr><th>Articolo</th><th>Keyword</th><th>Impressioni</th><th>CTR attuale</th><th>Posizione</th><th>Azione</th></tr></thead>
  <tbody>
  <?php
  $low_ctr = array_filter($memory, fn($m) => $m['gsc_impr']>=30 && $m['gsc_ctr']<0.05);
  usort($low_ctr, fn($a,$b) => $b['gsc_impr']<=>$a['gsc_impr']);
  foreach (array_slice($low_ctr,0,20) as $m):
  ?>
  <tr>
    <td><a href="<?= get_edit_post_link($m['post_id']) ?>" target="_blank"><?= esc_html(mb_substr(get_the_title($m['post_id']),0,40)) ?></a></td>
    <td style="font-size:11px"><?= esc_html(mb_substr($m['keyword'],0,30)) ?></td>
    <td><?= number_format($m['gsc_impr']) ?></td>
    <td style="color:<?= $m['gsc_ctr']<0.02?'#d63638':'#f0a500' ?>"><?= round($m['gsc_ctr']*100,1) ?>%</td>
    <td><?= round($m['gsc_position'],1) ?></td>
    <td>
      <button class="button button-small v5-ctr-btn" data-pid="<?= (int)$m['post_id'] ?>">⚡ Ottimizza</button>
      <div id="ctr-result-<?= (int)$m['post_id'] ?>" style="font-size:11px;color:#555;margin-top:4px"></div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
