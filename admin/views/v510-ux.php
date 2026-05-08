<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🎯 UX Engine V5.10</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Tutti i blocchi UX vengono aggiunti <strong>automaticamente</strong> nei nuovi articoli scritti dalla AI.
  Nessuna azione richiesta per i nuovi contenuti.
</p>
<div class="soser-stats" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">FAQ Accordion + Schema</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">Tabella Prezzi AI</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">Timeline Esecuzione</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">Garanzie Box</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">Social Proof Counter</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#00a32a">✅</div><div class="stat-lbl">Smart TOC Mobile</div></div>
</div>
<div class="soser-card" style="max-width:700px">
  <h2>📋 Struttura articolo generato</h2>
  <ol style="font-size:13px;line-height:2.6;counter-reset:none">
    <?php $blocks = [
      ['📊','Progress Bar','Barra di lettura in cima alla pagina'],
      ['✅','Expert Badge','Verificato da SOSER · Milano · Anno · Minuti lettura'],
      ['📋','Smart TOC','Indice collassato con icone — apre al tap'],
      ['⚡','Answer Box','Risposta rapida per Google AI Overview'],
      ['📝','Contenuto','Sezioni H2 con spacing ottimale 17px'],
      ['📊','Social Proof','Anni esperienza · Clienti · Rating · 24h'],
      ['📅','Timeline','Fasi di esecuzione con durata in giorni'],
      ['💰','Tabella Prezzi','3 fasce Base/Standard/Premium generate da AI'],
      ['❓','FAQ Accordion','5 domande con FAQPage Schema per Google'],
      ['🏅','Perché SOSER','Box scuro con garanzie e CTA telefono'],
      ['💰','Price CTA','Box prezzi locali con contesto città'],
      ['🏆','Trust Section','4 statistiche su sfondo chiaro'],
      ['📚','Fonti Ufficiali','Accordion con link esterni — visibile a Google'],
      ['💬','WhatsApp Sticky','Bottone verde fisso in basso a destra'],
    ];
    foreach($blocks as $i=>$b): ?>
    <li style="display:flex;gap:12px;align-items:center;padding:4px 0;border-bottom:1px solid #f5f5f5">
      <span style="font-size:18px;flex-shrink:0"><?= $b[0] ?></span>
      <strong style="min-width:140px"><?= esc_html($b[1]) ?></strong>
      <span style="font-size:12px;color:#888"><?= esc_html($b[2]) ?></span>
    </li>
    <?php endforeach; ?>
  </ol>
</div>
</div>
