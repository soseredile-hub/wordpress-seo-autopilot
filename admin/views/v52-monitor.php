<?php defined('ABSPATH') || exit;
$arrow = ($trend['direction']??'neutral')==='up' ? '📈' : (($trend['direction']??'neutral')==='down' ? '📉' : '➡️');
?>
<div class="wrap soser-wrap">
<h1>📡 Performance Monitor</h1>
<p style="font-size:13px;color:#555;margin-bottom:16px">Traccia il tuo ranking ogni settimana e ricevi report automatici.</p>
<?php if (empty($history)): ?>
<div class="soser-info-bar">⏳ Nessun dato ancora. Collega Search Console per iniziare il tracciamento.</div>
<?php else:
  $latest = end($history);
  $email  = get_option('v52_report_email','');
?>
<div class="soser-stats">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $latest['avg_position']??'—' ?> <?= $arrow ?></div><div class="stat-lbl">Posizione media</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $latest['top_10']??0 ?></div><div class="stat-lbl">Keyword top 10</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#7c3aed"><?= number_format($latest['total_clicks']??0) ?></div><div class="stat-lbl">Click settimana</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= count($history) ?></div><div class="stat-lbl">Settimane tracciate</div></div>
</div>
<div class="soser-card" style="max-width:500px">
  <h2>📧 Report Email settimanale</h2>
  <p style="font-size:13px;color:#555;margin-bottom:10px">Ricevi un report ogni lunedì mattina.</p>
  <form method="post">
    <?php wp_nonce_field('v52_report_email') ?>
    <input type="email" name="v52_report_email" value="<?= esc_attr($email) ?>" class="regular-text" placeholder="tua@email.com">
    <?php if(isset($_POST['v52_report_email'])&&wp_verify_nonce($_POST['_wpnonce']??'','v52_report_email')){update_option('v52_report_email',sanitize_email($_POST['v52_report_email']));echo '<p style="color:#00a32a">✅ Salvato!</p>';} ?>
    <?php submit_button('💾 Salva email','secondary','',false); ?>
  </form>
</div>
<div class="soser-card">
  <h2>📊 Storico settimanale</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>Data</th><th>Pos. media</th><th>Top 10</th><th>Click</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($history) as $snap): ?>
    <tr><td><?= esc_html($snap['date']) ?></td><td><?= $snap['avg_position'] ?></td><td><?= $snap['top_10'] ?></td><td><?= number_format($snap['total_clicks']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
</div>
