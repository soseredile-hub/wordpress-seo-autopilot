<?php defined('ABSPATH') || exit; ?>
<div class="wrap soser-wrap">
<h1>🤝 E-E-A-T Signals</h1>
<?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>✅ Salvato!</p></div><?php endif; ?>
<p style="font-size:13px;color:#555;margin-bottom:16px">Questi dati vengono iniettati automaticamente in ogni articolo per aumentare la fiducia di Google.</p>

<div class="soser-grid-2">
  <div class="soser-card">
    <h2>🏢 Informazioni Business</h2>
    <form method="post" action="<?= admin_url('admin-post.php') ?>">
      <?php wp_nonce_field('soser_v51_eeat') ?>
      <input type="hidden" name="action" value="soser_v51_eeat_save">
      <table class="form-table">
        <?php echo class_exists('V5_EEAT') ? V5_EEAT::settings_fields() : ''; ?>
      </table>
      <?php submit_button('💾 Salva', 'primary'); ?>
    </form>
  </div>
  <div class="soser-card">
    <h2>👁️ Anteprima Author Box</h2>
    <?php
    $opts = V4_Options::get();
    $business = $opts['business'] ?: get_bloginfo('name');
    $years = get_option('v5_years_experience','10');
    $cert  = get_option('v5_certifications','Impresa edile certificata');
    $rating = get_option('v5_rating_value','4.8');
    $reviews = get_option('v5_review_count','47');
    ?>
    <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:16px;display:flex;gap:12px;align-items:flex-start">
      <div style="width:50px;height:50px;background:#2271b1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">🏗️</div>
      <div>
        <strong><?= esc_html($business) ?></strong><br>
        <span style="font-size:12px;color:#555"><?= esc_html($cert) ?> · <?= esc_html($opts['geo']) ?> · <?= esc_html($years) ?>+ anni</span><br>
        <span style="font-size:12px;color:#777">⭐ <?= esc_html($rating) ?>/5 (<?= esc_html($reviews) ?> recensioni)</span>
      </div>
    </div>
    <p style="font-size:12px;color:#888;margin-top:10px">Appare automaticamente alla fine di ogni articolo.</p>
  </div>
</div>
</div>
