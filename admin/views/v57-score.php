<?php defined('ABSPATH') || exit;
$avg = count($scores) ? round(array_sum(array_column($scores,'score'))/count($scores)) : 0;
$excellent = count(array_filter($scores,fn($s)=>$s['score']>=85));
$poor      = count(array_filter($scores,fn($s)=>$s['score']<55));
?>
<div class="wrap soser-wrap">
<h1>🧠 Content Score — Punteggio SEO di ogni articolo</h1>
<div class="soser-stats" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $avg ?>/100</div><div class="stat-lbl">Score medio</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $excellent ?></div><div class="stat-lbl">Eccellenti (A)</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#d63638"><?= $poor ?></div><div class="stat-lbl">Da migliorare</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= count($scores) ?></div><div class="stat-lbl">Articoli analizzati</div></div>
</div>
<div class="soser-card">
  <h2>📋 Articoli ordinati per score (peggiori prima)</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>Articolo</th><th>Score</th><th>Grade</th><th>Problemi</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($scores as $s):
      $grade_colors = ['A'=>'#00a32a','B'=>'#2271b1','C'=>'#f0a500','D'=>'#d63638','F'=>'#8c1719'];
      $gc = $grade_colors[$s['grade']] ?? '#888';
    ?>
    <tr>
      <td><a href="<?= get_edit_post_link($s['post_id']) ?>"><?= esc_html(mb_substr($s['title'],0,45)) ?></a></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div style="width:80px;height:10px;background:#f0f0f1;border-radius:5px;overflow:hidden">
            <div style="width:<?= $s['score'] ?>%;height:100%;background:<?= $gc ?>;border-radius:5px"></div>
          </div>
          <strong><?= $s['score'] ?></strong>
        </div>
      </td>
      <td><span style="background:<?= $gc ?>;color:#fff;padding:2px 8px;border-radius:4px;font-weight:700;font-size:13px"><?= $s['grade'] ?></span></td>
      <td><span style="background:#fdecea;color:#d63638;padding:2px 8px;border-radius:10px;font-size:11px"><?= $s['tips'] ?> problemi</span></td>
      <td><a href="<?= get_edit_post_link($s['post_id']) ?>" class="button button-small">Migliora</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
