<?php defined('ABSPATH') || exit;
$categories_order = ['Prezzo','Guida','Normativa','Confronto','Tempistica','Servizio','Consiglio','Fattibilità','Generale'];
$by_category = [];
foreach ($questions as $q) { $by_category[$q['category']][] = $q; }
?>
<div class="wrap soser-wrap">
<h1>❓ Question Harvester — Ogni domanda = Featured Snippet possibile</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Queste sono tutte le domande che le persone cercano sui tuoi servizi.
  Ogni domanda senza risposta nel tuo sito = opportunità persa.
  <strong>Le domande "Prezzo" hanno il CTR più alto.</strong>
</p>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <button class="button button-primary" id="v55-harvest-btn">🔄 Aggiorna domande</button>
  <span id="v55-harvest-result" style="font-size:12px;padding-top:6px"></span>
</div>

<!-- STATS -->
<div class="soser-stats" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-num" style="color:#2271b1"><?= $stats['total']??0 ?></div><div class="stat-lbl">Domande totali</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#d63638"><?= $stats['missing']??0 ?></div><div class="stat-lbl">Non ancora coperte</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a"><?= $stats['covered']??0 ?></div><div class="stat-lbl">Già coperte ✅</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f0a500"><?= count($stats['by_cat']??[]) ?></div><div class="stat-lbl">Categorie</div></div>
</div>

<?php foreach ($categories_order as $cat):
  $cat_qs = $by_category[$cat] ?? [];
  if (empty($cat_qs)) continue;
  $missing = count(array_filter($cat_qs, fn($q)=>!$q['covered']));
  $cat_icons = ['Prezzo'=>'💰','Guida'=>'📋','Normativa'=>'⚖️','Confronto'=>'⚖️','Tempistica'=>'⏱️','Servizio'=>'🔧','Consiglio'=>'💡','Fattibilità'=>'✅','Generale'=>'❓'];
  $icon = $cat_icons[$cat] ?? '❓';
?>
<div class="soser-card" style="margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2><?= $icon ?> <?= esc_html($cat) ?> <span style="font-size:13px;color:#888;font-weight:400">(<?= $missing ?> non coperte)</span></h2>
    <?php if ($cat==='Prezzo'): ?>
    <span style="background:#fff3d6;color:#8a6200;padding:4px 10px;border-radius:10px;font-size:11px;font-weight:700">⭐ Più ricercate</span>
    <?php endif; ?>
  </div>
  <table class="soser-table widefat" style="margin-top:8px">
    <thead><tr><th>Domanda</th><th>Servizio</th><th>Fonte</th><th>Tipo Snippet</th><th>Coperta</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($cat_qs,0,15) as $q): ?>
    <tr style="<?= $q['covered']?'opacity:.55':'' ?>">
      <td><strong style="font-size:12px"><?= esc_html($q['question']) ?></strong></td>
      <td style="font-size:11px;color:#666"><?= esc_html($q['service']) ?></td>
      <td style="font-size:11px"><?php $src_labels=['template'=>'📝 Template','autocomplete'=>'🔍 Google','paa'=>'🤖 AI PAA']; echo esc_html($src_labels[$q['source']]??$q['source']); ?></td>
      <td style="font-size:11px">
        <?php $snip=['table'=>'📊 Tabella','numbered'=>'🔢 Lista','paragraph'=>'📄 Paragrafo'];
        echo esc_html($snip[$q['snippet_type']]??''); ?>
      </td>
      <td><?= $q['covered']?'✅ Sì':'<span style="color:#d63638;font-weight:700">❌ No</span>' ?></td>
      <td>
        <?php if (!$q['covered']): ?>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($q['question']) ?>">
          <button class="button button-small">✍️ Scrivi</button>
        </form>
        <?php else: echo '<span style="color:#888;font-size:11px">Coperta</span>'; endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<script>
jQuery('#v55-harvest-btn').on('click',function(){
  var $b=$(this),$r=jQuery('#v55-harvest-result');
  $b.prop('disabled',true).text('Raccolta domande...');
  jQuery.post(ajaxurl,{action:'soser_v55_harvest_qs',nonce:'<?= wp_create_nonce("soser_v4_nonce") ?>'},function(r){
    r.success?$r.css('color','#00a32a').text('✅ '+r.data.message):$r.css('color','#d63638').text('❌ '+r.data);
    if(r.success)setTimeout(()=>location.reload(),1500);
  }).always(()=>$b.prop('disabled',false).text('🔄 Aggiorna domande'));
});
</script>
</div>
