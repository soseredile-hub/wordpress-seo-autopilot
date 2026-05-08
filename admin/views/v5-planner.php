<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🧠 AI SEO Planner — Piano Autonomo</h1>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $plan['total'] ?></div><div class="stat-lbl">Azioni pianificate</div></div>
  <?php $ms=$plan['stats']??[]; ?>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $ms['total']??0 ?></div><div class="stat-lbl">Articoli in memoria</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= $ms['to_refresh']??0 ?></div><div class="stat-lbl">Da aggiornare</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= number_format($ms['words']??0) ?></div><div class="stat-lbl">Parole totali</div></div>
</div>
<div style="display:flex;gap:10px;margin-bottom:20px">
  <button class="button button-primary" id="v5-run-plan">🔄 Ricalcola piano</button>
  <span id="v5-plan-result" style="font-size:12px;padding-top:6px"></span>
</div>
<?php if ($plan['next_write']): ?>
<div class="soser-info-bar" style="background:#e8f9e8;border-color:#00a32a">
  🏆 <strong>Prossimo da scrivere:</strong> <?= esc_html($plan['next_write']['keyword']??'') ?>
  — <?= esc_html($plan['next_write']['reason']??'') ?>
  <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display:inline;margin-right:10px">
    <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
    <input type="hidden" name="keyword" value="<?= esc_attr($plan['next_write']['keyword']??'') ?>">
    <button class="button button-primary button-small">✍️ Scrivi ora</button>
  </form>
</div>
<?php endif; ?>
<div class="soser-card">
  <h2>📋 Piano completo</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>#</th><th>Azione</th><th>Keyword</th><th>Tipo</th><th>Priorità</th><th>Motivo</th><th>Fonte</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($plan['actions'] as $i=>$a): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= $a['action']==='write'?'✍️ Scrivi':'🔄 Aggiorna' ?></td>
      <td><strong><?= esc_html($a['keyword']??'—') ?></strong></td>
      <td><span class="soser-badge soser-badge-<?= $a['action']==='write'?'done':'pending' ?>"><?= esc_html($a['type']??'') ?></span></td>
      <td><?= (int)($a['priority']??0) ?>/100</td>
      <td style="font-size:11px;color:#666"><?= esc_html(mb_substr($a['reason']??'',0,60)) ?></td>
      <td style="font-size:11px"><?= esc_html($a['source']??'') ?></td>
      <td>
        <?php if ($a['action']==='write'&&!empty($a['keyword'])): ?>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($a['keyword']) ?>">
          <button class="button button-small">Scrivi</button>
        </form>
        <?php elseif($a['action']==='refresh'&&!empty($a['post_id'])): ?>
          <button class="button button-small v5-refresh-btn" data-pid="<?= (int)$a['post_id'] ?>">Aggiorna</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
