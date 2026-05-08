<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">

<style>
.v10-topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px}
.v10-g4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.v10-g3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px}
.v10-g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
.v10-st{background:#fff;border:1px solid #e8e5e0;border-radius:10px;padding:14px 16px;text-align:center;transition:box-shadow .2s}
.v10-st:hover{box-shadow:0 2px 12px rgba(0,0,0,.06)}
.v10-st .n{font-size:26px;font-weight:900;display:block;line-height:1.1;font-family:Georgia,serif}
.v10-st .l{font-size:11px;color:#888;margin-top:3px;display:block;font-weight:600}
.v10-st .sub{font-family:monospace;font-size:10px;color:#bbb;margin-top:2px;display:block}
.v10-card{background:#fff;border:1px solid #e8e5e0;border-radius:10px;padding:18px;margin-bottom:14px}
.v10-card h3{font-size:13px;font-weight:700;color:#1a1a1a;margin:0 0 12px;display:flex;align-items:center;gap:6px}
.v10-tbl{width:100%;border-collapse:collapse;font-size:12px}
.v10-tbl th{text-align:right;padding:4px 8px;border-bottom:2px solid #f0f0f0;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px}
.v10-tbl td{padding:7px 8px;border-bottom:1px solid #f7f7f5;vertical-align:middle}
.v10-tbl tr:last-child td{border:none}
.v10-tbl tr:hover td{background:#fafaf8}
.pb{display:inline-block;padding:2px 7px;border-radius:7px;font-size:10px;font-weight:700}
.p1{background:#d1fae5;color:#065f46}.p2{background:#dbeafe;color:#1e40af}
.p3{background:#fef9c3;color:#854d0e}.px{background:#fee2e2;color:#991b1b}
.s-run{background:#fff3cd;color:#92400e;padding:2px 7px;border-radius:7px;font-size:10px;font-weight:700}
.s-ok{background:#d1fae5;color:#065f46;padding:2px 7px;border-radius:7px;font-size:10px;font-weight:700}
.s-fail{background:#fee2e2;color:#991b1b;padding:2px 7px;border-radius:7px;font-size:10px;font-weight:700}
.s-pend{background:#eff6ff;color:#1e40af;padding:2px 7px;border-radius:7px;font-size:10px;font-weight:700}
.conn-ok{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;color:#16a34a}
.conn-no{background:#fff8f0;border:1px solid #fed7aa;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;color:#92400e}
.inp{border:1.5px solid #e8e5e0;border-radius:6px;padding:8px 12px;font-size:13px;font-family:inherit;flex:1;outline:none}
.inp:focus{border-color:<?= esc_js($color) ?>}
.btn{padding:8px 15px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;border:none;font-family:inherit}
.btn-p{background:<?= esc_js($color) ?>;color:#fff}
.btn-s{background:#f0f0ee;color:#444;border:1.5px solid #e8e5e0}
.btn-sm{padding:5px 10px;font-size:11px}
.alert-d{color:#dc2626;font-size:12px;padding:5px 0;border-bottom:1px solid #fee2e2;display:flex;justify-content:space-between}
.alert-r{color:#16a34a;font-size:12px;padding:5px 0;border-bottom:1px solid #d1fae5;display:flex;justify-content:space-between}
.alert-t{color:#d97706;font-size:12px;padding:5px 0;border-bottom:1px solid #fef9c3;display:flex;justify-content:space-between}
.pulse{animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.ts{font-family:monospace;font-size:10px;color:#ccc}
@media(max-width:700px){.v10-g4{grid-template-columns:repeat(2,1fr)}.v10-g2,.v10-g3{grid-template-columns:1fr}}
</style>

<!-- Top bar -->
<div class="v10-topbar">
  <h1 style="margin:0;font-size:20px">🚀 SOSER SEO Autopilot</h1>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <span class="<?= $gsc_ok?'conn-ok':'conn-no' ?>"><?= $gsc_ok?'✅ GSC':'⚠️ GSC' ?></span>
    <span class="<?= $ga4_ok?'conn-ok':'conn-no' ?>"><?= $ga4_ok?'✅ GA4':'⚠️ GA4' ?></span>
    <span id="v10-ts" class="ts">—</span>
    <button class="btn btn-s btn-sm" onclick="loadDashboard()">🔄 Aggiorna</button>
  </div>
</div>

<!-- Quick add form -->
<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;flex-wrap:wrap">
  <input class="inp" id="v10-kw" placeholder="Keyword → scrivi articolo (es: rifacimento bagno Milano prezzi)" style="max-width:460px">
  <button class="btn btn-p" onclick="addKeyword()">➕ Aggiungi alla coda</button>
  <button class="btn btn-s btn-sm" onclick="processNext()">⚙️ Processa ora</button>
  <span id="v10-add-msg" style="font-size:12px;color:<?= esc_js($color) ?>;font-weight:700"></span>
</div>

<!-- ROW 1: Stats -->
<div class="v10-g4" id="v10-stats-row">
  <div class="v10-st"><span class="n" id="s-pending" style="color:#2271b1">—</span><span class="l">⏳ In coda</span><span class="sub" id="s-running-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-done" style="color:#00a32a">—</span><span class="l">✅ Pubblicati</span><span class="sub" id="s-done-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-clicks" style="color:#1a73e8">—</span><span class="l">👆 Click GSC</span><span class="sub" id="s-impr-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-top3" style="color:#16a34a">—</span><span class="l">🏆 Top 3</span><span class="sub" id="s-top10-sub">—</span></div>
</div>

<div class="v10-g4" id="v10-stats-row2">
  <div class="v10-st"><span class="n" id="s-pos" style="color:<?= esc_js($color) ?>">—</span><span class="l">🎯 Pos. media</span><span class="sub" id="s-ctr-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-sessions" style="color:#2271b1">—</span><span class="l">📲 Sessioni GA4</span><span class="sub" id="s-conv-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-refresh" style="color:#7c3aed">—</span><span class="l">🔄 Da refreshare</span><span class="sub" id="s-refresh-sub">—</span></div>
  <div class="v10-st"><span class="n" id="s-draft" style="color:#d97706">—</span><span class="l">📝 Bozze</span><span class="sub" id="s-sched-sub">—</span></div>
</div>

<!-- ROW 2: Queue + Alerts -->
<div class="v10-g2">

  <!-- Queue -->
  <div class="v10-card">
    <h3>📋 Coda attuale <span id="v10-queue-badge" style="background:<?= esc_js($color) ?>;color:#fff;border-radius:10px;padding:1px 8px;font-size:10px;font-weight:700;margin-right:4px">0</span></h3>
    <div id="v10-queue-body">
      <p style="color:#aaa;font-size:12px;text-align:center;padding:16px 0">Caricamento...</p>
    </div>
  </div>

  <!-- Alerts + Opportunities -->
  <div class="v10-card">
    <h3>🔔 Alert & Opportunità</h3>
    <div id="v10-alerts-body">
      <p style="color:#aaa;font-size:12px;text-align:center;padding:16px 0">Caricamento...</p>
    </div>
  </div>
</div>

<!-- ROW 3: Recent articles + Log -->
<div class="v10-g2">

  <!-- Recent articles -->
  <div class="v10-card">
    <h3>📝 Ultimi articoli pubblicati</h3>
    <div id="v10-recent-body">
      <p style="color:#aaa;font-size:12px;text-align:center;padding:16px 0">Caricamento...</p>
    </div>
  </div>

  <!-- Job log -->
  <div class="v10-card">
    <h3>📜 Log attività</h3>
    <div id="v10-log-body">
      <p style="color:#aaa;font-size:12px;text-align:center;padding:16px 0">Caricamento...</p>
    </div>

    <?php if ($next): ?>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0;font-size:12px;color:#888">
      ⏰ Prossimo automatico: <strong><?= wp_date('d/m H:i', $next) ?></strong>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const NONCE = '<?= esc_js($nonce) ?>';
const AJAX  = '<?= esc_js(admin_url('admin-ajax.php')) ?>';
const COLOR = '<?= esc_js($color) ?>';
let autoTimer = null;

function fmt(n){ return n >= 1000 ? (n/1000).toFixed(1)+'k' : n; }

function loadDashboard(){
  fetch(AJAX, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'soser_v10_dashboard_data', nonce:NONCE})
  })
  .then(r=>r.json())
  .then(res=>{
    if (!res.success) return;
    const d = res.data;

    // Timestamp
    document.getElementById('v10-ts').textContent = '⏱ '+d.timestamp;

    // Stats row 1
    const pend = (d.queue.pending||0) + (d.queue.running||0);
    document.getElementById('s-pending').textContent = pend;
    document.getElementById('s-running-sub').textContent = d.queue.running > 0 ? '🟡 '+d.queue.running+' in esecuzione' : '— in attesa';
    document.getElementById('s-done').textContent = d.pub_stats.published || d.queue.done || 0;
    document.getElementById('s-done-sub').textContent = (d.pub_stats.scheduled||0) + ' schedulati';
    document.getElementById('s-clicks').textContent = d.gsc_ok ? fmt(d.gsc.clicks||0) : '—';
    document.getElementById('s-impr-sub').textContent = d.gsc_ok ? fmt(d.gsc.impressions||0)+' impr.' : 'GSC non connessa';
    document.getElementById('s-top3').textContent = d.serp.top3 || 0;
    document.getElementById('s-top10-sub').textContent = (d.serp.top10||0)+' in top 10';

    // Stats row 2
    document.getElementById('s-pos').textContent = d.gsc_ok && d.gsc.avg_position > 0 ? d.gsc.avg_position : '—';
    document.getElementById('s-ctr-sub').textContent = d.gsc_ok ? 'CTR: '+(d.gsc.avg_ctr||0)+'%' : '—';
    document.getElementById('s-sessions').textContent = d.ga4_ok ? fmt(d.ga4.sessions||0) : '—';
    document.getElementById('s-conv-sub').textContent = d.ga4_ok ? (d.ga4.conversions||0)+' conv.' : 'GA4 non connessa';
    document.getElementById('s-refresh').textContent = d.refresh.pending || 0;
    document.getElementById('s-refresh-sub').textContent = (d.refresh.done||0)+' completati';
    document.getElementById('s-draft').textContent = d.pub_stats.draft || 0;
    document.getElementById('s-sched-sub').textContent = (d.pub_stats.pending||0)+' in revisione';

    // Queue
    const qb = document.getElementById('v10-queue-body');
    document.getElementById('v10-queue-badge').textContent = pend;
    if (!d.log || !d.log.length) {
      qb.innerHTML = '<p style="color:#aaa;font-size:12px;text-align:center;padding:14px 0">Coda vuota — aggiungi una keyword sopra</p>';
    } else {
      let h = '<table class="v10-tbl"><tr><th>Keyword</th><th>Step</th><th>Stato</th></tr>';
      d.log.forEach(j => {
        const sc = {done:'s-ok',running:'s-run',failed:'s-fail',pending:'s-pend'}[j.status]||'s-pend';
        const icon = {done:'✅',running:'🟡',failed:'❌',pending:'⏳'}[j.status]||'⏳';
        h += `<tr>
          <td style="font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${j.keyword}</td>
          <td style="font-family:monospace;font-size:10px;color:#aaa">${j.step||'—'}</td>
          <td><span class="${sc}">${icon} ${j.status}</span></td>
        </tr>`;
      });
      h += '</table>';
      qb.innerHTML = h;
    }

    // Alerts + Opportunities
    const ab = document.getElementById('v10-alerts-body');
    let ah = '';

    if (d.alerts && d.alerts.length) {
      d.alerts.slice(0,4).forEach(a => {
        const cls = {drop:'alert-d',rise:'alert-r',top3:'alert-t'}[a.type]||'alert-d';
        ah += `<div class="${cls}"><span>${a.message}</span><span style="color:#ccc;font-size:10px">${a.date}</span></div>`;
      });
    }

    if (d.opportunities && d.opportunities.length) {
      ah += '<div style="font-size:10px;font-weight:700;color:#aaa;margin:10px 0 5px;letter-spacing:.5px">⚡ QUICK WINS</div>';
      d.opportunities.forEach(o => {
        ah += `<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid #f7f7f5">
          <span style="font-size:12px;font-weight:600">${o.keyword}</span>
          <span style="display:flex;gap:6px;align-items:center">
            <span class="pb p3">pos ${o.position}</span>
            <span style="font-size:10px;color:#aaa">${o.impressions} impr.</span>
          </span>
        </div>`;
      });
    }

    if (!ah) ah = '<p style="color:#aaa;font-size:12px;text-align:center;padding:14px 0">Nessun alert — tutto ok ✅</p>';
    ab.innerHTML = ah;

    // Recent articles
    const rb = document.getElementById('v10-recent-body');
    if (!d.recent || !d.recent.length) {
      rb.innerHTML = '<p style="color:#aaa;font-size:12px;text-align:center;padding:14px 0">Nessun articolo ancora</p>';
    } else {
      let rh = '<table class="v10-tbl"><tr><th>Titolo</th><th>Score</th><th>Data</th><th></th></tr>';
      d.recent.forEach(a => {
        const sc = a.score >= 80 ? '#16a34a' : a.score >= 60 ? '#d97706' : '#dc2626';
        rh += `<tr>
          <td style="max-width:200px">
            <a href="${a.url}" target="_blank" style="color:#1a1a1a;font-weight:600;text-decoration:none;font-size:12px">${a.title.substring(0,45)}${a.title.length>45?'…':''}</a>
            <br><span style="color:#aaa;font-size:10px">${a.keyword}</span>
          </td>
          <td style="font-weight:700;color:${sc}">${a.score||'—'}</td>
          <td style="color:#aaa;font-size:10px">${a.date}</td>
          <td><a href="${a.edit}" style="font-size:10px;color:${COLOR}">Edit</a></td>
        </tr>`;
      });
      rh += '</table>';
      rb.innerHTML = rh;
    }

    // Log
    const lb = document.getElementById('v10-log-body');
    if (!d.log || !d.log.length) {
      lb.innerHTML = '<p style="color:#aaa;font-size:12px;text-align:center;padding:14px 0">Nessuna attività recente</p>';
    } else {
      let lh = '<table class="v10-tbl"><tr><th>Keyword</th><th>Stato</th><th>Ora</th></tr>';
      d.log.forEach(j => {
        const sc = {done:'s-ok',running:'s-run',failed:'s-fail',pending:'s-pend'}[j.status]||'s-pend';
        lh += `<tr>
          <td style="font-weight:600;font-size:11px">${j.keyword}</td>
          <td><span class="${sc}">${j.status}</span></td>
          <td style="color:#ccc;font-size:10px">${(j.updated||'').substring(11,16)}</td>
        </tr>`;
      });
      lh += '</table>';
      lb.innerHTML = lh;
    }
  })
  .catch(err=>{
    console.error('Dashboard load error:', err);
  });
}

function addKeyword(){
  const kw = document.getElementById('v10-kw').value.trim();
  if (!kw) return;
  const msg = document.getElementById('v10-add-msg');
  msg.textContent = '⏳ Aggiungendo...';
  fetch(AJAX, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'soser_v10_add_keyword', nonce:NONCE, keyword:kw})
  })
  .then(r=>r.json())
  .then(res=>{
    if (res.success) {
      msg.textContent = '✅ "'+kw+'" aggiunto!';
      document.getElementById('v10-kw').value = '';
      setTimeout(()=>{ msg.textContent=''; }, 3000);
      loadDashboard();
    } else {
      msg.textContent = '❌ '+(res.data||'Errore sconosciuto');
    }
  })
  .catch(err=>{
    msg.textContent = '❌ Errore: '+err.message;
    console.error('addKeyword error:', err);
  });
}

function processNext(){
  fetch(AJAX, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'soser_v10_process_next', nonce:NONCE})
  })
  .then(r=>r.json())
  .then(()=>{ loadDashboard(); });
}

// Enter key on input
document.getElementById('v10-kw').addEventListener('keydown', e=>{
  if (e.key==='Enter') addKeyword();
});

// Load on startup
loadDashboard();

// Auto-refresh every 30 seconds
autoTimer = setInterval(loadDashboard, 30000);
</script>

</div>
