<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>⭐ Featured Snippet Optimizer</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Il Featured Snippet appare <strong>sopra il #1</strong> — è la "posizione 0". Google lo estrae da contenuti strutturati con risposta diretta, tabelle e liste numerate.
</p>
<div class="soser-card">
  <h2>📊 Prontezza Snippet dei tuoi articoli</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>Articolo</th><th>Score Snippet</th><th>Pronto?</th><th>Cosa manca</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($scores as $pid => $data):
      $s = $data['snippet'];
      $score = $s['score'] ?? 0;
      $ready = $s['ready'] ?? false;
      $tips  = $s['tips']  ?? [];
    ?>
    <tr>
      <td><a href="<?= get_edit_post_link($pid) ?>"><?= esc_html(mb_substr($data['post']->post_title,0,40)) ?></a></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div style="width:60px;height:8px;background:#f0f0f1;border-radius:4px;overflow:hidden">
            <div style="width:<?= $score ?>%;height:100%;background:<?= $score>=60?'#00a32a':'#f0a500' ?>;border-radius:4px"></div>
          </div>
          <strong><?= $score ?>/100</strong>
        </div>
      </td>
      <td><?= $ready ? '✅ Sì' : '❌ No' ?></td>
      <td style="font-size:11px;color:#666"><?= !empty($tips) ? esc_html($tips[0]) : '—' ?></td>
      <td><a href="<?= get_edit_post_link($pid) ?>" class="button button-small">Ottimizza</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="soser-card" style="margin-top:16px;background:#f0f9ff">
  <h2>💡 Come ottenere il Featured Snippet</h2>
  <ol style="font-size:13px;line-height:2.2">
    <li>Aggiungi una risposta diretta di <strong>40-60 parole</strong> subito dopo il primo H2</li>
    <li>Crea una <strong>tabella prezzi</strong> per keyword "quanto costa..."</li>
    <li>Usa <strong>liste numerate</strong> per guide "come fare..."</li>
    <li>Inserisci la keyword nel <strong>primo H2</strong></li>
    <li>Attiva il modulo <strong>AI Overview</strong> dalla pagina Rich Schema</li>
  </ol>
</div>
</div>
