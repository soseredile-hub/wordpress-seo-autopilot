<?php defined('ABSPATH') || exit;
$current_month = (int)date('n');
?>
<div class="wrap soser-wrap">
<h1>📅 Stagionalità — Scrivi adesso per il picco di tra 4 settimane</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Google impiega 2-4 settimane per indicizzare e posizionare nuovi articoli.
  Pubblica <strong>prima del picco stagionale</strong> per essere pronto quando la gente cerca.
</p>

<!-- WRITE NOW BOX -->
<?php if (!empty($write_now)): ?>
<div class="soser-card" style="background:linear-gradient(135deg,#fff9e6,#fff3d6);border-color:#f0a500;margin-bottom:24px">
  <h2>🔥 Scrivi ADESSO per essere pronto al prossimo picco</h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">Il picco arriva tra ~4 settimane — pubblica questi articoli oggi:</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px">
    <?php foreach ($write_now as $item): ?>
    <div style="background:#fff;border-radius:8px;padding:14px;border:1px solid #e0e0e0">
      <div style="font-size:24px;margin-bottom:6px"><?= esc_html($item['service_icon']) ?></div>
      <strong style="display:block;margin-bottom:4px"><?= esc_html($item['service_name']) ?></strong>
      <div style="font-size:12px;color:#666;margin-bottom:8px">
        Picco: <strong><?= esc_html($item['peak_month']) ?></strong> ×<?= $item['multiplier'] ?> ricerche
      </div>
      <span style="font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700;
        background:<?= $item['urgency']==='alta'?'#fdecea':'#fff9e6' ?>;
        color:<?= $item['urgency']==='alta'?'#d63638':'#8a6200' ?>">
        <?= $item['urgency']==='alta'?'🔥 Urgente':'📈 Importante' ?>
      </span>
      <?php if (!empty($item['suggested_kws'])): ?>
      <div style="margin-top:10px">
        <?php foreach (array_slice($item['suggested_kws'],0,2) as $kw): ?>
        <form method="post" action="<?= admin_url('admin-post.php') ?>" style="margin-bottom:4px">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($kw) ?>">
          <button class="button button-small" style="width:100%;text-align:right">✍️ <?= esc_html(mb_substr($kw,0,35)) ?></button>
        </form>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- SEASONAL KEYWORDS -->
<?php if (!empty($seasonal_kws)): ?>
<div class="soser-card" style="margin-bottom:24px">
  <h2>🔥 Keyword stagionali HOT adesso — <?= V55_SeasonalCalendar::month_name($current_month) ?></h2>
  <table class="soser-table widefat">
    <thead><tr><th>Keyword</th><th>Servizio</th><th>Motivo</th><th>Priorità</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($seasonal_kws as $kw): ?>
    <tr>
      <td><strong><?= esc_html($kw['keyword']) ?></strong></td>
      <td><?= esc_html($kw['service']) ?></td>
      <td style="font-size:11px;color:#666"><?= esc_html($kw['why']) ?></td>
      <td><strong style="color:#d63638"><?= $kw['priority'] ?></strong></td>
      <td>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
          <?php wp_nonce_field('soser_v4_generate') ?><input type="hidden" name="action" value="soser_v4_generate">
          <input type="hidden" name="keyword" value="<?= esc_attr($kw['keyword']) ?>">
          <button class="button button-small">✍️ Scrivi</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- 12 MONTH CALENDAR -->
<div class="soser-card">
  <h2>📅 Calendario stagionale annuale</h2>
  <p style="font-size:12px;color:#888;margin-bottom:16px">Pubblica 1 mese prima del picco per essere pronto.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
    <?php foreach ($yearly_plan as $month): ?>
    <div style="border:2px solid <?= $month['is_current']?'#2271b1':'#e0e0e0' ?>;border-radius:8px;padding:12px;background:<?= $month['is_current']?'#f0f6fc':($month['is_past']?'#f9f9f9':'#fff') ?>">
      <strong style="display:block;margin-bottom:6px;font-size:13px">
        <?= $month['is_current']?'📍 ':'' ?><?= esc_html($month['month_name']) ?>
        <?= $month['is_current']?'(ora)':'' ?>
      </strong>
      <div style="font-size:11px;color:#888;margin-bottom:8px">Pubblica entro: <?= esc_html($month['publish_by']) ?></div>
      <?php foreach ($month['services'] as $svc): ?>
      <div style="margin-bottom:4px">
        <span style="font-size:12px"><?= esc_html($svc['icon'].' '.$svc['service']) ?></span>
        <div style="height:5px;background:#f0f0f1;border-radius:3px;margin-top:2px;overflow:hidden">
          <div style="width:<?= $svc['bar_width'] ?>%;height:100%;background:<?= $svc['bar_width']>=75?'#d63638':($svc['bar_width']>=50?'#f0a500':'#2271b1') ?>;border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>
