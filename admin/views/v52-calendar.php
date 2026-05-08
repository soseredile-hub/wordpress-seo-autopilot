<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🗓️ Calendario Contenuti</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Piano AI dei prossimi 4 settimane — cosa scrivere e quando per massimizzare l'autorità tematica.</p>
<div style="display:flex;gap:10px;margin-bottom:20px">
  <button class="button button-primary" onclick="if(confirm('Rigenera il calendario?')){window.location.href='<?= admin_url('admin.php?page=soser-v52-calendar&regen=1') ?>'}">🔄 Rigenera</button>
</div>
<?php if(isset($_GET['regen'])){delete_transient('v52_calendar');wp_redirect(admin_url('admin.php?page=soser-v52-calendar'));exit;} ?>
<table class="soser-table widefat striped">
  <thead><tr><th>Data</th><th>Giorno</th><th>Keyword</th><th>Tipo</th><th>Priorità</th><th>Fonte</th><th>Azione</th></tr></thead>
  <tbody>
  <?php
  $today = date('Y-m-d');
  foreach ($calendar as $item):
    $past = $item['date'] < $today;
    $style = $past ? 'opacity:.5' : '';
  ?>
  <tr style="<?= $style ?>">
    <td><strong><?= esc_html($item['date']) ?></strong></td>
    <td><?= esc_html($item['weekday']) ?></td>
    <td><?= esc_html($item['keyword']) ?></td>
    <td><span class="soser-badge soser-badge-<?= $item['type']==='pillar'?'done':'pending' ?>"><?= esc_html($item['type']) ?></span></td>
    <td><?= (int)$item['priority'] ?>/100</td>
    <td style="font-size:11px;color:#888"><?= esc_html($item['source']??'') ?></td>
    <td>
      <?php if (!$past): ?>
      <form method="post" action="<?= admin_url('admin-post.php') ?>">
        <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
        <input type="hidden" name="keyword" value="<?= esc_attr($item['keyword']) ?>">
        <button class="button button-small">✍️ Scrivi ora</button>
      </form>
      <?php else: echo '<span style="color:#888;font-size:11px">Passato</span>'; endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
