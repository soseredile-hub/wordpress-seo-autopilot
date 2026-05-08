<?php
defined('ABSPATH') || exit;

// Save handlers
if (isset($_POST['v8_image_save']) && check_admin_referer('v8_image')) {
    if (class_exists('V8_ImageEngine')) V8_ImageEngine::save_config($_POST['img'] ?? []);
    wp_redirect(admin_url('admin.php?page=soser-v8-settings&saved=img')); exit;
}
if (isset($_POST['v8_publish_save']) && check_admin_referer('v8_publish')) {
    if (class_exists('V8_PublishController')) V8_PublishController::save_config($_POST['pub'] ?? []);
    wp_redirect(admin_url('admin.php?page=soser-v8-settings&saved=pub')); exit;
}
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'bulk_seo' && class_exists('V8_SEOAutofill')) {
        $c = V8_SEOAutofill::bulk_fill(50);
        wp_redirect(admin_url("admin.php?page=soser-v8-settings&saved=seo&count={$c}")); exit;
    }
    if ($_GET['action'] === 'publish_drafts' && class_exists('V8_PublishController')) {
        $c = V8_PublishController::publish_drafts(10);
        wp_redirect(admin_url("admin.php?page=soser-v8-settings&saved=published&count={$c}")); exit;
    }
}

$img_cfg = class_exists('V8_ImageEngine')      ? V8_ImageEngine::get_config()      : [];
$pub_cfg = class_exists('V8_PublishController') ? V8_PublishController::get_config(): [];
$pub_st  = class_exists('V8_PublishController') ? V8_PublishController::stats()     : [];
$seo_act = class_exists('V8_SEOAutofill')       ? V8_SEOAutofill::detect_active()   : [];
$color   = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
$authors = get_users(['role__in' => ['administrator','editor','author']]);
$saved   = $_GET['saved'] ?? '';
$cnt     = (int)($_GET['count'] ?? 0);
?>
<div class="wrap soser-wrap">
<h1>⚙️ V8 Settings</h1>

<?php if ($saved === 'img'):?>
<div class="notice notice-success is-dismissible"><p>✅ Impostazioni immagini salvate!</p></div>
<?php elseif ($saved === 'pub'):?>
<div class="notice notice-success is-dismissible"><p>✅ Impostazioni pubblicazione salvate!</p></div>
<?php elseif ($saved === 'seo'):?>
<div class="notice notice-success is-dismissible"><p>✅ SEO compilato su <?= $cnt ?> articoli!</p></div>
<?php elseif ($saved === 'published'):?>
<div class="notice notice-success is-dismissible"><p>✅ <?= $cnt ?> bozze schedulate per la pubblicazione!</p></div>
<?php endif;?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:960px">

  <!-- ① IMAGE GENERATION -->
  <div>
  <div class="soser-card">
    <h2>🖼️ Image Generation</h2>
    <p style="font-size:13px;color:#666;margin-bottom:14px;line-height:1.6">
      Genera immagini con DALL-E oppure scarica gratis da Unsplash/Pexels come fallback.
    </p>
    <form method="post">
      <?php wp_nonce_field('v8_image');?>
      <input type="hidden" name="v8_image_save" value="1">
      <table class="form-table" style="margin:0">
        <tr>
          <th>Sorgente immagini</th>
          <td>
            <select name="img[source]" style="font-size:14px">
              <?php foreach(['auto'=>'🤖 Auto (DALL-E → Unsplash → Pexels)','dalle'=>'🎨 Solo DALL-E / gpt-image-1','unsplash'=>'📷 Solo Unsplash (gratis)','pexels'=>'📸 Solo Pexels (gratis)'] as $v=>$l):?>
              <option value="<?=$v?>" <?=selected($img_cfg['source']??'auto',$v,false)?>><?=$l?></option>
              <?php endforeach;?>
            </select>
          </td>
        </tr>
        <tr>
          <th>Modello DALL-E</th>
          <td>
            <select name="img[dalle_model]">
              <option value="gpt-image-1" <?=selected($img_cfg['dalle_model']??'gpt-image-1','gpt-image-1',false)?>>gpt-image-1 (consigliato)</option>
              <option value="dall-e-3"    <?=selected($img_cfg['dalle_model']??'','dall-e-3',false)?>>DALL-E 3</option>
            </select>
          </td>
        </tr>
        <tr>
          <th>Qualità DALL-E</th>
          <td>
            <select name="img[dalle_quality]">
              <option value="low"    <?=selected($img_cfg['dalle_quality']??'medium','low',false)   ?>>Low (veloce)</option>
              <option value="medium" <?=selected($img_cfg['dalle_quality']??'medium','medium',false) ?>>Medium (bilanciato)</option>
              <option value="high"   <?=selected($img_cfg['dalle_quality']??'medium','high',false)  ?>>High (migliore)</option>
            </select>
          </td>
        </tr>
        <tr>
          <th>Unsplash Access Key</th>
          <td>
            <input type="text" name="img[unsplash_key]" value="<?=esc_attr($img_cfg['unsplash_key']??'')?>" class="regular-text" placeholder="Leave empty to skip">
            <p class="description">Gratis su <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a></p>
          </td>
        </tr>
        <tr>
          <th>Pexels API Key</th>
          <td>
            <input type="text" name="img[pexels_key]" value="<?=esc_attr($img_cfg['pexels_key']??'')?>" class="regular-text" placeholder="Leave empty to skip">
            <p class="description">Gratis su <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a></p>
          </td>
        </tr>
        <tr>
          <th>Stile immagini</th>
          <td><input type="text" name="img[style_suffix]" value="<?=esc_attr($img_cfg['style_suffix']??'professional photography, high quality, realistic')?>" class="large-text"></td>
        </tr>
        <tr>
          <th>Immagine in evidenza</th>
          <td><label><input type="checkbox" name="img[featured]" value="1" <?=checked($img_cfg['featured']??'1','1',false)?>> Imposta come featured image</label></td>
        </tr>
        <tr>
          <th>Immagini inline</th>
          <td>
            <select name="img[inline_count]">
              <?php foreach(['0'=>'Nessuna','1'=>'1 immagine','2'=>'2 immagini','3'=>'3 immagini'] as $v=>$l):?>
              <option value="<?=$v?>" <?=selected($img_cfg['inline_count']??'2',$v,false)?>><?=$l?></option>
              <?php endforeach;?>
            </select>
          </td>
        </tr>
      </table>
      <button type="submit" class="button button-primary" style="margin-top:14px;background:<?=esc_attr($color)?>;border-color:<?=esc_attr($color)?>">💾 Salva impostazioni immagini</button>
    </form>
  </div>

  <!-- ② SEO AUTOFILL -->
  <div class="soser-card" style="margin-top:16px">
    <h2>🔍 SEO Autofill</h2>
    <?php if (!empty($seo_act)):?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
      ✅ Plugin rilevati: <strong><?=implode(', ',$seo_act)?></strong>
    </div>
    <?php else:?>
    <div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
      ⚠️ Nessun plugin SEO rilevato. Il sistema usa comunque postmeta standard.
    </div>
    <?php endif;?>
    <p style="font-size:13px;color:#555;margin-bottom:14px;line-height:1.6">
      Tutti i nuovi articoli vengono compilati automaticamente.<br>
      Per i vecchi articoli usa il pulsante qui sotto.
    </p>
    <a href="<?=admin_url('admin.php?page=soser-v8-settings&action=bulk_seo')?>"
       class="button button-primary"
       style="background:<?=esc_attr($color)?>;border-color:<?=esc_attr($color)?>"
       onclick="return confirm('Compila SEO su tutti gli articoli esistenti?\n\nQuesta operazione è sicura e reversibile.')">
      🔍 Compila SEO su articoli esistenti
    </a>
    <p class="description" style="margin-top:8px">Processa fino a 50 articoli per volta</p>

    <h3 style="margin-top:18px;font-size:13px">Plugin supportati:</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px">
      <?php foreach(['Yoast SEO','RankMath','AIOSEO','The SEO Framework','SEOPress','Open Graph'] as $p):
        $is_active = in_array($p,$seo_act);?>
      <div style="font-size:12px;padding:5px 10px;background:#f8f7f4;border-radius:5px">
        <?=$is_active?'✅':'⚪'?> <?=esc_html($p)?>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  </div>

  <!-- ③ PUBLISH CONTROL -->
  <div>
  <div class="soser-card">
    <h2>📅 Publish Control</h2>
    <p style="font-size:13px;color:#666;margin-bottom:14px;line-height:1.6">
      Controlla come e quando gli articoli vengono pubblicati.
    </p>

    <!-- Current stats -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;text-align:center">
      <?php foreach([['published','✅','Pubblicati','#16a34a'],['scheduled','📅','Schedulati','#2563eb'],['draft','📝','Bozze','#d97706'],['pending','👀','In revisione','#7c3aed']] as [$k,$ic,$lb,$cl]):?>
      <div style="background:#f8f7f4;border-radius:7px;padding:10px">
        <strong style="font-size:20px;color:<?=$cl?>"><?=$ic?> <?=esc_html($pub_st[$k]??0)?></strong>
        <div style="font-size:11px;color:#888"><?=$lb?></div>
      </div>
      <?php endforeach;?>
    </div>

    <form method="post">
      <?php wp_nonce_field('v8_publish');?>
      <input type="hidden" name="v8_publish_save" value="1">
      <table class="form-table" style="margin:0">
        <tr>
          <th>Modalità pubblicazione</th>
          <td>
            <select name="pub[mode]" id="pub-mode" onchange="showPubOptions(this.value)" style="font-size:14px">
              <?php foreach(['publish'=>'✅ Pubblica subito','draft'=>'📝 Salva come bozza','review'=>'👀 Invia in revisione','schedule'=>'📅 Schedula automaticamente','smart'=>'🤖 Smart (AI decide)'] as $v=>$l):?>
              <option value="<?=$v?>" <?=selected($pub_cfg['mode']??'publish',$v,false)?>><?=$l?></option>
              <?php endforeach;?>
            </select>
          </td>
        </tr>
        <tr id="row-schedule" style="display:<?=in_array($pub_cfg['mode']??'publish',['schedule','smart'])?'':'none'?>">
          <th>Orario pubblicazione</th>
          <td><input type="time" name="pub[schedule_time]" value="<?=esc_attr($pub_cfg['schedule_time']??'09:00')?>" style="font-size:14px"></td>
        </tr>
        <tr id="row-days" style="display:<?=in_array($pub_cfg['mode']??'publish',['schedule','smart'])?'':'none'?>">
          <th>Giorni di pubblicazione</th>
          <td>
            <?php foreach(['mon'=>'Lun','tue'=>'Mar','wed'=>'Mer','thu'=>'Gio','fri'=>'Ven','sat'=>'Sab','sun'=>'Dom'] as $v=>$l):
              $checked = in_array($v, $pub_cfg['schedule_days'] ?? ['mon','wed','fri']) ? 'checked' : '';?>
            <label style="margin-left:10px"><input type="checkbox" name="pub[schedule_days][]" value="<?=$v?>" <?=$checked?>> <?=$l?></label>
            <?php endforeach;?>
          </td>
        </tr>
        <tr id="row-score" style="display:<?=$pub_cfg['mode']??'publish'==='smart'?'':'none'?>">
          <th>Score minimo (Smart)</th>
          <td>
            <input type="number" name="pub[min_score]" value="<?=esc_attr($pub_cfg['min_score']??60)?>" min="0" max="100" style="width:70px"> / 100
            <p class="description">Articoli sotto questo score vanno in bozza</p>
          </td>
        </tr>
        <tr>
          <th>Autore articoli</th>
          <td>
            <select name="pub[author_id]">
              <?php foreach($authors as $u):?>
              <option value="<?=$u->ID?>" <?=selected($pub_cfg['author_id']??1,$u->ID,false)?>><?=esc_html($u->display_name)?> (<?=esc_html($u->user_login)?>)</option>
              <?php endforeach;?>
            </select>
          </td>
        </tr>
        <tr>
          <th>Notifica email</th>
          <td>
            <input type="email" name="pub[notify_email]" value="<?=esc_attr($pub_cfg['notify_email']??'')?>" class="regular-text" placeholder="email@sito.it">
            <label style="margin-right:8px">
              <input type="checkbox" name="pub[notify_enabled]" value="1" <?=checked($pub_cfg['notify_enabled']??'0','1',false)?>>
              Invia email quando un articolo è pronto per revisione
            </label>
          </td>
        </tr>
      </table>
      <button type="submit" class="button button-primary" style="margin-top:14px;background:<?=esc_attr($color)?>;border-color:<?=esc_attr($color)?>">💾 Salva impostazioni pubblicazione</button>
    </form>

    <?php if (($pub_st['draft']??0) > 0 || ($pub_st['pending']??0) > 0):?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
      <p style="font-size:13px;color:#555;margin-bottom:8px">
        Hai <strong><?=($pub_st['draft']??0)+($pub_st['pending']??0)?></strong> articoli non pubblicati.
      </p>
      <a href="<?=admin_url('admin.php?page=soser-v8-settings&action=publish_drafts')?>"
         class="button"
         onclick="return confirm('Schedulare le bozze per la pubblicazione automatica?')">
        📅 Schedula tutte le bozze
      </a>
    </div>
    <?php endif;?>
  </div>

  <!-- Next schedule slot preview -->
  <?php if (class_exists('V8_PublishController') && in_array($pub_cfg['mode']??'', ['schedule','smart'])):
    $next = V8_PublishController::next_schedule_slot($pub_cfg);?>
  <div class="soser-card" style="margin-top:16px;background:#f0fdf4;border-color:#bbf7d0">
    <h2>📅 Prossima pubblicazione</h2>
    <p style="font-size:15px;font-weight:700;color:#16a34a"><?=esc_html(date('l d/m/Y \a\l\l\e H:i', strtotime($next)))?></p>
    <p style="font-size:12px;color:#888">Il prossimo articolo verrà pubblicato in questo orario</p>
  </div>
  <?php endif;?>

  </div><!-- col 2 -->
</div><!-- grid -->
</div>

<script>
function showPubOptions(mode) {
  ['row-schedule','row-days'].forEach(id => {
    document.getElementById(id).style.display = ['schedule','smart'].includes(mode) ? '' : 'none';
  });
  document.getElementById('row-score').style.display = mode === 'smart' ? '' : 'none';
}
</script>
