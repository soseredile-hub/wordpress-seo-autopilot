<?php defined('ABSPATH') || exit;
$posts = get_posts(['post_type'=>'post','post_status'=>'publish','numberposts'=>100]);
?>
<div class="wrap soser-wrap">
<h1>↩️ Redirect Manager — Non perdere l'Authority dei vecchi articoli</h1>
<p style="font-size:14px;color:#444;max-width:750px;margin-bottom:20px;line-height:1.7">
  Quando unisci o elimini un articolo, aggiungi un <strong>redirect 301</strong> per preservare tutta l'autorità SEO accumulata.
</p>
<div class="soser-card" style="max-width:600px;margin-bottom:20px">
  <h2>➕ Aggiungi nuovo redirect</h2>
  <table class="form-table">
    <tr><th>Da (slug vecchio)</th><td><input type="text" id="redir-from" class="regular-text" placeholder="es. vecchio-articolo-bagno-2022"></td></tr>
    <tr><th>A (articolo attuale)</th><td>
      <select id="redir-to" class="regular-text">
        <option value="">-- Seleziona articolo destinazione --</option>
        <?php foreach ($posts as $p): ?>
        <option value="<?= $p->ID ?>"><?= esc_html(mb_substr($p->post_title,0,55)) ?></option>
        <?php endforeach; ?>
      </select>
    </td></tr>
  </table>
  <button class="button button-primary" id="add-redirect-btn">➕ Aggiungi redirect 301</button>
  <span id="redir-result" style="font-size:12px;margin-right:8px"></span>
</div>
<?php if (!empty($redirects)): ?>
<div class="soser-card">
  <h2>📋 Redirect attivi (<?= count($redirects) ?>)</h2>
  <table class="soser-table widefat striped">
    <thead><tr><th>Da (vecchio URL)</th><th>A (nuovo articolo)</th><th>Aggiunto</th><th>Azione</th></tr></thead>
    <tbody>
    <?php foreach ($redirects as $slug=>$data): ?>
    <tr>
      <td><code><?= esc_html(home_url('/'.$slug.'/')) ?></code></td>
      <td><a href="<?= esc_url($data['to_url']??get_permalink($data['to_id'])) ?>" target="_blank"><?= esc_html(get_the_title($data['to_id'])) ?></a></td>
      <td style="font-size:11px;color:#888"><?= esc_html(substr($data['added_at']??'',0,10)) ?></td>
      <td><button class="button button-small del-redir-btn" data-slug="<?= esc_attr($slug) ?>" style="color:#d63638">✕ Rimuovi</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="soser-info-bar">Nessun redirect attivo. Aggiungine uno quando elimini o unisci articoli.</div>
<?php endif; ?>
<script>
jQuery(function($){
  var nonce='<?= wp_create_nonce("soser_v4_nonce") ?>';
  $('#add-redirect-btn').on('click',function(){
    var from=$('#redir-from').val(),to=$('#redir-to').val(),$r=$('#redir-result');
    if(!from||!to){alert('Inserisci slug e articolo destinazione');return;}
    $(this).prop('disabled',true);
    $.post(ajaxurl,{action:'soser_v57_add_redirect',nonce:nonce,from:from,to:to},function(r){
      r.success?$r.css('color','#00a32a').text('✅ '+r.data):$r.css('color','#d63638').text('❌ '+r.data);
      if(r.success)setTimeout(()=>location.reload(),800);
    }).always(()=>$('#add-redirect-btn').prop('disabled',false));
  });
  $(document).on('click','.del-redir-btn',function(){
    if(!confirm('Rimuovere questo redirect?'))return;
    var slug=$(this).data('slug');
    $.post(ajaxurl,{action:'soser_v57_del_redirect',nonce:nonce,slug:slug},function(r){
      if(r.success)location.reload();
    });
  });
});
</script>
</div>
