<?php
defined('ABSPATH') || exit;

// Handle AJAX batch analyze
if (isset($_GET['action']) && $_GET['action'] === 'v10_analyze' && class_exists('V10_AIVision')) {
    $limit  = min(10, max(1, (int)($_GET['limit'] ?? 5)));
    $result = V10_AIVision::batch_analyze($limit);
    wp_redirect(admin_url('admin.php?page=soser-v10-vision&done=' . $result['done'] . '&failed=' . $result['failed']));
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'v10_reanalyze' && isset($_GET['id']) && class_exists('V10_AIVision')) {
    V10_AIVision::reanalyze((int)$_GET['id']);
    wp_redirect(admin_url('admin.php?page=soser-v10-vision&reanalyzed=1'));
    exit;
}

$stats   = class_exists('V10_AIVision') ? V10_AIVision::stats() : [];
$color   = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';

// Get analyzed images for display
global $wpdb;
$analyzed_ids = $wpdb->get_col(
    "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
     WHERE meta_key = '_v10_ai_analyzed'
     ORDER BY meta_id DESC
     LIMIT 30"
);
?>
<div class="wrap soser-wrap">
<h1>🔍 AI Vision — Analisi immagini</h1>
<p style="font-size:13px;color:#555;max-width:700px;margin-bottom:20px;line-height:1.7">
  L'AI analizza ogni foto che carichi e capisce automaticamente cosa mostra.
  Scrive l'<strong>alt text SEO</strong>, assegna una <strong>categoria</strong> e le <strong>keyword</strong>.
  Poi quando scrivi un articolo su "bagno", l'AI trova le tue foto del bagno e le usa automaticamente.
</p>

<?php if (isset($_GET['done'])): ?>
<div class="notice notice-success is-dismissible">
  <p>✅ Analizzate <?= (int)$_GET['done'] ?> immagini<?= (int)$_GET['failed'] > 0 ? ', ' . (int)$_GET['failed'] . ' fallite' : '' ?>!</p>
</div>
<?php endif; ?>
<?php if (isset($_GET['reanalyzed'])): ?>
<div class="notice notice-success is-dismissible"><p>✅ Immagine rianalizzata!</p></div>
<?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;max-width:700px;margin-bottom:24px">
  <?php $cats = $stats['categories'] ?? []; ?>
  <div class="soser-card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:<?=esc_attr($color)?>;font-family:Georgia,serif"><?=esc_html($stats['total']??0)?></div>
    <div style="font-size:11px;color:#888;margin-top:3px">Totale immagini</div>
  </div>
  <div class="soser-card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:#16a34a;font-family:Georgia,serif"><?=esc_html($stats['analyzed']??0)?></div>
    <div style="font-size:11px;color:#888;margin-top:3px">Analizzate ✅</div>
  </div>
  <div class="soser-card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:<?=esc_attr($color)?>;font-family:Georgia,serif"><?=esc_html($stats['pending']??0)?></div>
    <div style="font-size:11px;color:#888;margin-top:3px">Da analizzare</div>
  </div>
  <div class="soser-card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:#2271b1;font-family:Georgia,serif"><?=count($cats)?></div>
    <div style="font-size:11px;color:#888;margin-top:3px">Categorie trovate</div>
  </div>
</div>

<!-- Action buttons -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
  <?php if (($stats['pending'] ?? 0) > 0): ?>
  <a href="<?=admin_url('admin.php?page=soser-v10-vision&action=v10_analyze&limit=5')?>"
     class="button button-primary"
     style="background:<?=esc_attr($color)?>;border-color:<?=esc_attr($color)?>;font-size:14px;padding:6px 18px">
    🔍 Analizza 5 immagini con AI
  </a>
  <a href="<?=admin_url('admin.php?page=soser-v10-vision&action=v10_analyze&limit=10')?>"
     class="button"
     onclick="return confirm('Analizzare 10 immagini? Costo ~$0.10')">
    🔍🔍 Analizza 10 immagini
  </a>
  <?php else: ?>
  <div class="notice notice-success inline" style="margin:0;padding:8px 14px;font-size:13px">
    ✅ Tutte le immagini sono già analizzate!
  </div>
  <?php endif; ?>
  <a href="<?=admin_url('media-new.php')?>" class="button">
    ⬆️ Carica nuove foto
  </a>
</div>

<!-- Categories breakdown -->
<?php if (!empty($cats)): ?>
<div class="soser-card" style="max-width:700px;margin-bottom:24px">
  <h2>📂 Categorie trovate dall'AI</h2>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach ($cats as $c): ?>
    <span style="background:#f0f0ee;border-radius:20px;padding:5px 14px;font-size:13px;font-weight:600">
      <?=esc_html($c['cat'])?> <span style="color:<?=esc_attr($color)?>;font-weight:700">(<?=esc_html($c['n'])?>)</span>
    </span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Analyzed images grid -->
<?php if (!empty($analyzed_ids)): ?>
<div class="soser-card">
  <h2>🖼️ Immagini analizzate</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:14px">
    <?php foreach ($analyzed_ids as $id):
      $img_url  = wp_get_attachment_image_url($id, 'medium');
      $alt      = get_post_meta($id, '_v10_ai_alt', true);
      $category = get_post_meta($id, '_v10_ai_category', true);
      $keywords = (array)(get_post_meta($id, '_v10_ai_keywords', true) ?: []);
      $analyzed = get_post_meta($id, '_v10_ai_analyzed', true);
      if (!$img_url) continue;
    ?>
    <div style="border:1px solid #e8e5e0;border-radius:8px;overflow:hidden;background:#fff">
      <div style="position:relative;height:150px;overflow:hidden;background:#f0f0f0">
        <img src="<?=esc_url($img_url)?>" alt="<?=esc_attr($alt)?>"
             style="width:100%;height:100%;object-fit:cover">
        <?php if ($category): ?>
        <span style="position:absolute;top:8px;right:8px;background:<?=esc_attr($color)?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700">
          <?=esc_html($category)?>
        </span>
        <?php endif; ?>
      </div>
      <div style="padding:10px 12px">
        <p style="font-size:11px;color:#555;margin:0 0 6px;line-height:1.4">
          <strong>Alt:</strong> <?=esc_html(mb_substr($alt,0,80))?>
        </p>
        <?php if (!empty($keywords)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px">
          <?php foreach (array_slice($keywords,0,4) as $kw): ?>
          <span style="background:#f8f7f4;padding:1px 7px;border-radius:8px;font-size:10px;color:#666">
            <?=esc_html($kw)?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:10px;color:#ccc"><?=esc_html(substr($analyzed,0,10))?></span>
          <a href="<?=admin_url('admin.php?page=soser-v10-vision&action=v10_reanalyze&id='.$id)?>"
             style="font-size:11px;color:<?=esc_attr($color)?>">🔄 Rianalizza</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Unanalyzed images -->
<?php
$unanalyzed = get_posts([
    'post_type'      => 'attachment',
    'post_mime_type' => ['image/jpeg','image/png','image/webp'],
    'post_status'    => 'inherit',
    'posts_per_page' => 12,
    'meta_query'     => [['key'=>'_v10_ai_analyzed','compare'=>'NOT EXISTS']],
]);
if (!empty($unanalyzed)):
?>
<div class="soser-card" style="margin-top:20px">
  <h2>⏳ Immagini non ancora analizzate (<?=count($unanalyzed)?>+)</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:12px">
    <?php foreach ($unanalyzed as $att):
      $img = wp_get_attachment_image_url($att->ID, 'thumbnail');
      if (!$img) continue;
    ?>
    <div style="position:relative;border-radius:6px;overflow:hidden;background:#f0f0f0;height:100px">
      <img src="<?=esc_url($img)?>" alt="" style="width:100%;height:100%;object-fit:cover;opacity:.6">
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
        <span style="background:rgba(0,0,0,.5);color:#fff;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:700">⏳ Da analizzare</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p style="font-size:12px;color:#888;margin-top:12px">
    Premi "Analizza 5 immagini con AI" per iniziare — costo ~$0.02 per immagine.
  </p>
</div>
<?php endif; ?>
</div>
