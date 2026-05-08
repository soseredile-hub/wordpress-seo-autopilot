<?php defined('ABSPATH') || exit;
$pending  = (int)($stats['pending'] ?? 0);
$done     = (int)($stats['done']    ?? 0);
$total    = (int)($stats['total']   ?? 0);
$failed   = (int)($stats['failed']  ?? 0);
$progress = $total > 0 ? round($done / $total * 100) : 0;
$cost_est = round($total * 0.002, 2); // ~$0.002 per article
?>
<div class="wrap soser-wrap">
<h1>♻️ Bulk Refresh — Aggiorna Tutti gli Articoli Esistenti</h1>

<p style="font-size:14px;color:#444;max-width:700px;margin-bottom:20px;line-height:1.7">
  Il tuo dominio ha <strong>5+ anni di autorità</strong> — ogni articolo migliorato avrà effetto in
  <strong>settimane, non mesi</strong>. Questo tool aggiorna automaticamente ogni articolo vecchio
  con: AI rewrite, Schema, internal links, CTR optimization e AI Overview.
</p>

<?php if ($total === 0): ?>
<!-- STEP 1: Scan -->
<div class="soser-card" style="max-width:700px;text-align:center;padding:40px">
  <div style="font-size:48px;margin-bottom:16px">🔍</div>
  <h2 style="margin-bottom:8px">Inizia la scansione</h2>
  <p style="color:#666;font-size:14px;margin-bottom:24px">
    Prima di tutto, analizza tutti i tuoi articoli e scopri quali hanno bisogno di aggiornamento.
  </p>
  <button class="button button-primary button-large" id="v53-scan-btn">
    🔍 Scansiona tutti gli articoli
  </button>
  <span id="v53-scan-result" style="display:block;margin-top:12px;font-size:13px"></span>
</div>

<?php else: ?>
<!-- STATS -->
<div class="soser-stats" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-num" style="color:#2271b1"><?= $total ?></div>
    <div class="stat-lbl">Articoli totali</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#f0a500"><?= $pending ?></div>
    <div class="stat-lbl">Da aggiornare</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#00a32a"><?= $done ?></div>
    <div class="stat-lbl">Aggiornati ✅</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#7c3aed"><?= $progress ?>%</div>
    <div class="stat-lbl">Completato</div>
  </div>
</div>

<!-- PROGRESS BAR -->
<div class="soser-card" style="max-width:700px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;margin-bottom:8px">
    <strong style="font-size:13px">Progresso refresh</strong>
    <span style="font-size:12px;color:#888"><?= $done ?>/<?= $total ?></span>
  </div>
  <div style="background:#f0f0f1;border-radius:6px;height:12px;overflow:hidden">
    <div style="width:<?= $progress ?>%;height:100%;background:linear-gradient(90deg,#2271b1,#00a32a);border-radius:6px;transition:width .3s" id="v53-progress-bar"></div>
  </div>
  <p style="font-size:12px;color:#666;margin-top:8px">
    💰 Costo stimato: ~$<?= $cost_est ?> totale (già aggiornati: $<?= round($done * 0.002, 3) ?>)
  </p>
</div>

<!-- CONTROLS -->
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
  <?php if ($pending > 0): ?>
  <button class="button button-primary button-large" id="v53-process-btn">
    ⚡ Aggiorna i prossimi 2 articoli
  </button>
  <button class="button button-primary" id="v53-process-5-btn">
    ⚡⚡ Aggiorna i prossimi 5
  </button>
  <?php else: ?>
  <div class="soser-info-bar" style="background:#e8f9e8;border-color:#00a32a">
    ✅ Tutti gli articoli sono stati aggiornati!
    <button class="button button-small" id="v53-reset-btn" style="margin-right:8px">🔄 Riscansiona tutto</button>
  </div>
  <?php endif; ?>
  <button class="button" id="v53-scan-btn">🔍 Riscansiona articoli</button>
  <?php if ($done > 0): ?>
  <button class="button" id="v53-reset-btn">🔄 Reset (riprocessa tutti)</button>
  <?php endif; ?>
  <span id="v53-process-result" style="font-size:12px;font-weight:600"></span>
</div>

<!-- AUTO MODE -->
<div class="soser-card" style="max-width:700px;margin-bottom:20px;background:#fff9e6;border-color:#f0a500">
  <h3 style="margin:0 0 8px;font-size:14px">⚡ Modalità automatica</h3>
  <p style="font-size:13px;color:#555;margin-bottom:12px">
    Il cron aggiorna automaticamente <strong>2 articoli ogni 12 ore</strong>.
    Con <?= $pending ?> articoli rimanenti, finirà in ~<?= ceil($pending/4) ?> giorni.
  </p>
  <?php
  $next = wp_next_scheduled('soser_v53_refresh_cron');
  if ($next): ?>
  <p style="font-size:12px;color:#888">⏰ Prossimo run automatico: <strong><?= wp_date('d/m/Y H:i', $next) ?></strong></p>
  <?php endif; ?>
</div>

<!-- QUEUE TABLE -->
<div class="soser-card">
  <h2>📋 Coda articoli (ordinati per priorità)</h2>
  <table class="soser-table widefat striped" style="font-size:12px">
    <thead>
      <tr>
        <th>Priorità</th>
        <th>Articolo</th>
        <th>Stato</th>
        <th>Problemi rilevati</th>
        <th>Età</th>
        <th>Parole</th>
        <th>Pos. GSC</th>
        <th>CTR</th>
        <th>Impressioni</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($queue as $row):
      $colors = ['done'=>'#00a32a','pending'=>'#f0a500','failed'=>'#d63638','running'=>'#2271b1'];
      $color  = $colors[$row['status']] ?? '#888';
    ?>
    <tr style="<?= $row['status']==='done'?'opacity:.6':'' ?>">
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <div style="width:<?= min(60,(int)$row['priority_score']) ?>px;height:8px;background:<?= (int)$row['priority_score']>=80?'#d63638':((int)$row['priority_score']>=50?'#f0a500':'#2271b1') ?>;border-radius:4px"></div>
          <strong><?= (int)$row['priority_score'] ?></strong>
        </div>
      </td>
      <td>
        <a href="<?= get_edit_post_link($row['post_id']) ?>" target="_blank">
          <?= esc_html(mb_substr($row['post_title'] ?? 'N/A', 0, 45)) ?>
        </a>
      </td>
      <td>
        <span style="color:<?= $color ?>;font-weight:700">
          <?php
          $labels = ['done'=>'✅ Fatto','pending'=>'⏳ In attesa','failed'=>'❌ Fallito','running'=>'⚙️ In corso'];
          echo $labels[$row['status']] ?? $row['status'];
          ?>
        </span>
      </td>
      <td style="color:#555;max-width:250px;font-size:11px"><?= esc_html(mb_substr($row['reasons'] ?? '', 0, 80)) ?></td>
      <td><?= (int)$row['age_days'] ?> gg</td>
      <td><?= number_format((int)$row['word_count']) ?></td>
      <td><?= $row['gsc_position'] > 0 ? round((float)$row['gsc_position'],1) : '—' ?></td>
      <td><?= $row['gsc_ctr'] > 0 ? round((float)$row['gsc_ctr']*100,1).'%' : '—' ?></td>
      <td><?= number_format((int)$row['gsc_impressions']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

</div>

<script>
jQuery(function($){
    var nonce = '<?= wp_create_nonce("soser_v4_nonce") ?>';

    // Scan
    $('#v53-scan-btn').on('click', function(){
        var $b=$(this),$r=$('#v53-scan-result');
        $b.prop('disabled',true).text('Scansione...');
        if($r.length===0) $r=$('<span id="v53-process-result" style="font-size:12px;font-weight:600"></span>').insertAfter($b);
        $.post(ajaxurl,{action:'soser_v53_scan',nonce:nonce},function(r){
            if(r.success){
                var msg='✅ '+r.data.message;
                $r.css('color','#00a32a').text(msg);
                $('#v53-process-result').css('color','#00a32a').text(msg);
                setTimeout(()=>location.reload(),1500);
            } else {
                $r.css('color','#d63638').text('❌ '+r.data);
            }
        }).always(()=>$b.prop('disabled',false).text('🔍 Scansiona tutti gli articoli'));
    });

    // Process 2
    $('#v53-process-btn').on('click', function(){ processArticles(2, $(this)); });
    // Process 5
    $('#v53-process-5-btn').on('click', function(){ processArticles(5, $(this)); });

    function processArticles(limit, $btn){
        var $r=$('#v53-process-result');
        $btn.prop('disabled',true).text('Aggiornamento in corso...');
        $('#v53-process-btn,#v53-process-5-btn').prop('disabled',true);
        $.post(ajaxurl,{action:'soser_v53_process',nonce:nonce,limit:limit},function(r){
            if(r.success){
                var remaining=r.data.remaining||0;
                var done=r.data.done||0;
                $r.css('color','#00a32a').text('✅ '+r.data.message+' | Rimanenti: '+remaining);
                // Update progress bar
                var total = <?= $total ?>;
                var pct = total>0 ? Math.round(done/total*100) : 0;
                $('#v53-progress-bar').css('width',pct+'%');
                if(remaining===0){
                    setTimeout(()=>location.reload(),1000);
                } else {
                    setTimeout(()=>location.reload(),1500);
                }
            } else {
                $r.css('color','#d63638').text('❌ '+r.data);
            }
        }).always(function(){
            $btn.prop('disabled',false);
            $('#v53-process-btn').prop('disabled',false).text('⚡ Aggiorna i prossimi 2 articoli');
            $('#v53-process-5-btn').prop('disabled',false).text('⚡⚡ Aggiorna i prossimi 5');
        });
    }

    // Reset
    $('#v53-reset-btn').on('click', function(){
        if(!confirm('Resettare tutti i flag? Gli articoli già aggiornati verranno rielaborati.')) return;
        var $b=$(this);
        $b.prop('disabled',true);
        $.post(ajaxurl,{action:'soser_v53_reset',nonce:nonce},function(r){
            r.success?location.reload():alert('❌ '+r.data);
        }).always(()=>$b.prop('disabled',false));
    });
});
</script>
