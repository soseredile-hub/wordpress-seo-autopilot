<?php defined('ABSPATH') || exit;
$no_gsc = !class_exists('V4_GSC') || !V4_GSC::is_connected();
?>
<div class="wrap soser-wrap">
<h1>⚡ Quick Win Finder — Parole che ti portano al #1 in settimane</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Queste keyword sono le <strong>più facili e veloci</strong> per arrivare primi su Google.
  Combina: posizione vicina alla pagina 1 + alto volume + bassa competizione + intento commerciale.
</p>

<?php if ($no_gsc): ?>
<div class="notice notice-warning"><p>
  💡 <strong>Consiglio:</strong> Collega <a href="<?= admin_url('admin.php?page=soser-v4-gsc') ?>">Google Search Console</a>
  per trovare le keyword dove sei già in posizione 11-30 — queste sono le più facili da portare al #1!
</p></div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <button class="button button-primary" id="v55-refresh-wins">🔄 Aggiorna opportunità</button>
  <span id="v55-wins-result" style="font-size:12px;padding-top:6px"></span>
</div>

<!-- STATS ROW -->
<?php
$by_source = array_count_values(array_column($wins,'source'));
$urgent    = array_filter($wins, fn($w) => $w['time_to_top'] === '1-2 settimane');
?>
<div class="soser-stats" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= count($wins) ?></div><div class="stat-lbl">Opportunità totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#d63638"><?= count($urgent) ?></div><div class="stat-lbl">🔥 Urgenti (1-2 sett.)</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $by_source['gsc'] ?? 0 ?></div><div class="stat-lbl">Da GSC (posiz. reale)</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= ($by_source['price_intent']??0)+($by_source['question']??0) ?></div><div class="stat-lbl">Domande + Prezzi</div></div>
</div>

<?php if (empty($wins)): ?>
<div class="soser-info-bar">⏳ Nessuna opportunità trovata. Clicca "Aggiorna" o configura i Servizi Focus.</div>
<?php else: ?>

<!-- TOP 3 HIGHLIGHT -->
<div class="soser-card" style="margin-bottom:20px;background:linear-gradient(135deg,#f0f6fc,#e8f9e8)">
  <h2>🏆 Top 3 Quick Wins — Inizia da qui</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:8px">
    <?php foreach (array_slice($wins,0,3) as $i => $w): ?>
    <div style="background:#fff;border-radius:8px;padding:14px;border:2px solid <?= $i===0?'#d63638':($i===1?'#f0a500':'#2271b1') ?>">
      <div style="font-size:11px;font-weight:700;color:<?= $i===0?'#d63638':($i===1?'#f0a500':'#2271b1') ?>;margin-bottom:6px">
        <?= $i===0?'🥇 MIGLIORE':($i===1?'🥈 SECONDO':'🥉 TERZO') ?>
      </div>
      <strong style="font-size:13px;display:block;margin-bottom:6px"><?= esc_html($w['keyword']) ?></strong>
      <span style="font-size:11px;color:#666;display:block;margin-bottom:8px"><?= esc_html($w['why']) ?></span>
      <span style="font-size:11px;background:#f0f0f1;padding:2px 8px;border-radius:10px">⏱️ <?= esc_html($w['time_to_top']) ?></span>
      <form method="post" action="<?= admin_url('admin-post.php') ?>" style="margin-top:10px">
        <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
        <input type="hidden" name="keyword" value="<?= esc_attr($w['keyword']) ?>">
        <button class="button button-primary button-small">✍️ Scrivi ora</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- FULL TABLE -->
<div class="soser-card">
  <h2>📋 Tutte le opportunità</h2>
  <table class="soser-table widefat striped">
    <thead>
      <tr><th>#</th><th>Keyword</th><th>Perché è una quick win</th><th>Fonte</th><th>Pos. GSC</th><th>Impressioni</th><th>Tempo stimato</th><th>Score</th><th>Azione</th></tr>
    </thead>
    <tbody>
    <?php foreach ($wins as $i => $w): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><strong><?= esc_html($w['keyword']) ?></strong></td>
      <td style="font-size:11px;color:#444;max-width:220px"><?= esc_html($w['why']) ?></td>
      <td>
        <?php $source_labels=['gsc'=>'📊 GSC','local_longtail'=>'📍 Locale','price_intent'=>'💰 Prezzo','question'=>'❓ Domanda'];
        echo esc_html($source_labels[$w['source']] ?? $w['source']); ?>
      </td>
      <td><?= $w['position'] ?? '—' ?></td>
      <td><?= $w['impressions'] ? number_format((int)$w['impressions']) : '—' ?></td>
      <td><span style="background:#e8f9e8;color:#1a5c2a;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">⏱️ <?= esc_html($w['time_to_top']) ?></span></td>
      <td><strong style="color:<?= (int)$w['win_score']>=60?'#d63638':((int)$w['win_score']>=40?'#f0a500':'#2271b1') ?>"><?= (int)$w['win_score'] ?></strong></td>
      <td>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($w['keyword']) ?>">
          <button class="button button-small">✍️ Scrivi</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
jQuery('#v55-refresh-wins').on('click',function(){
  var $b=$(this),$r=jQuery('#v55-wins-result');
  $b.prop('disabled',true).text('...');
  jQuery.post(ajaxurl,{action:'soser_v55_refresh_wins',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
    r.success?$r.css('color','#00a32a').text('✅ '+r.data.message):$r.css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1200);
  }).always(()=>$b.prop('disabled',false).text('🔄 Aggiorna opportunità'));
});
</script>
</div>
