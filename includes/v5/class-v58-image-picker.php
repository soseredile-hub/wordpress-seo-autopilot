<?php
defined('ABSPATH') || exit;

/**
 * V5.8: Smart Image Picker
 * Uses real photos from Media Library instead of AI-generated images.
 */
class V58_ImagePicker {

    const META_TAGS     = '_v58_img_tags';
    const META_SERVICE  = '_v58_img_service';
    const META_APPROVED = '_v58_img_approved';
    const OPT_FALLBACK  = 'v58_fallback_images';

    public static function init(): void {
        add_filter('attachment_fields_to_edit', [__CLASS__, 'add_media_fields'], 10, 2);
        add_filter('attachment_fields_to_save', [__CLASS__, 'save_media_fields'], 10, 2);
    }

    public static function find_best(string $keyword, string $service_id = ''): ?int {
        $kw_lower = mb_strtolower($keyword);
        $images   = self::get_approved_images();
        if (empty($images)) $images = self::get_all_tagged_images();
        if (empty($images)) return null;

        $scores = [];
        foreach ($images as $att_id) {
            $score = 0;
            foreach (self::get_image_tags($att_id) as $tag) {
                similar_text($kw_lower, mb_strtolower($tag), $pct);
                if ($pct >= 80) $score += 50;
                elseif ($pct >= 60) $score += 30;
                elseif ($pct >= 40) $score += 10;
            }
            if ($service_id && get_post_meta($att_id, self::META_SERVICE, true) === $service_id) $score += 40;
            $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true) ?: '';
            similar_text($kw_lower, mb_strtolower($alt), $p2); $score += (int)($p2/4);
            $fn = str_replace(['-','_'],' ', pathinfo(get_attached_file($att_id), PATHINFO_FILENAME));
            similar_text($kw_lower, mb_strtolower($fn), $p3); if($p3>=60) $score+=20;
            if ($score > 0) $scores[$att_id] = $score;
        }
        if (empty($scores)) return self::get_fallback_image();
        arsort($scores);
        return (int) array_key_first($scores);
    }

    public static function assign_to_post(int $post_id, string $keyword, string $service_id = ''): int {
        $count = 0;
        $id    = self::find_best($keyword, $service_id);
        if ($id && !has_post_thumbnail($post_id)) {
            set_post_thumbnail($post_id, $id);
            $opts = V4_Options::get();
            update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($keyword . ' - ' . ($opts['business']?:'SOSER') . ' ' . ($opts['geo_city']?:'Milano')));
            $count++;
        }
        return $count;
    }

    public static function add_media_fields(array $ff, WP_Post $post): array {
        if (!wp_attachment_is_image($post->ID)) return $ff;
        $services = class_exists('V54_ServiceFocus') ? V54_ServiceFocus::get_services() : [];
        $tags     = implode(', ', self::get_image_tags($post->ID));
        $svc      = get_post_meta($post->ID, self::META_SERVICE, true);
        $approved = get_post_meta($post->ID, self::META_APPROVED, true);
        $ff['v58_tags'] = ['label'=>'🏷️ SEO Tags','input'=>'html',
            'html'=>'<input type="text" name="attachments['.$post->ID.'][v58_tags]" value="'.esc_attr($tags).'" class="widefat" placeholder="ristrutturazione bagno, Milano">',
            'helps'=>'Keyword separate da virgola'];
        $sel='<select name="attachments['.$post->ID.'][v58_service]" class="widefat"><option value="">-- Generico --</option>';
        foreach($services as $s){$sel.='<option value="'.esc_attr($s['id']).'"'.selected($svc,$s['id'],false).'>'.esc_html($s['icon'].' '.$s['name']).'</option>';}
        $sel.='</select>';
        $ff['v58_service']  = ['label'=>'🔧 Servizio','input'=>'html','html'=>$sel];
        $ff['v58_approved'] = ['label'=>'✅ Usa negli articoli','input'=>'html',
            'html'=>'<label><input type="checkbox" name="attachments['.$post->ID.'][v58_approved]" '.($approved?'checked':'').' value="1"> Approva per uso automatico</label>'];
        return $ff;
    }

    public static function save_media_fields(array $post, array $att): array {
        $id = $post['ID'];
        if (isset($att['v58_tags'])) { $tags=array_filter(array_map('trim',explode(',',sanitize_text_field($att['v58_tags'])))); update_post_meta($id,self::META_TAGS,$tags); }
        if (isset($att['v58_service'])) update_post_meta($id,self::META_SERVICE,sanitize_key($att['v58_service']));
        update_post_meta($id,self::META_APPROVED,!empty($att['v58_approved'])?'1':'0');
        delete_transient('v58_approved'); delete_transient('v58_tagged');
        return $post;
    }

    public static function get_image_tags(int $id): array { $t=get_post_meta($id,self::META_TAGS,true); return is_array($t)?$t:[]; }

    public static function get_approved_images(): array {
        $c=get_transient('v58_approved'); if($c!==false) return $c;
        global $wpdb;
        $ids=array_map('intval',$wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_v58_img_approved' AND pm.meta_value='1' WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' LIMIT 200")??[]);
        set_transient('v58_approved',$ids,HOUR_IN_SECONDS); return $ids;
    }

    public static function get_all_tagged_images(): array {
        $c=get_transient('v58_tagged'); if($c!==false) return $c;
        global $wpdb;
        $ids=array_map('intval',$wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_v58_img_tags' WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%' LIMIT 200")??[]);
        set_transient('v58_tagged',$ids,HOUR_IN_SECONDS); return $ids;
    }

    public static function get_fallback_image(): ?int { $ids=get_option(self::OPT_FALLBACK,[]); return !empty($ids)?(int)$ids[array_rand($ids)]:null; }
    public static function set_fallback_images(array $ids): void { update_option(self::OPT_FALLBACK,array_map('intval',$ids)); }

    public static function stats(): array {
        $a=self::get_approved_images(); $t=self::get_all_tagged_images();
        $total=(int)array_sum((array)wp_count_attachments('image'));
        return ['total'=>$total,'approved'=>count($a),'tagged'=>count($t),'ready'=>count($a)+count($t)];
    }

    public static function bulk_auto_tag(int $limit=20): int {
        $opts=V4_Options::get(); if(empty($opts['openai_key'])) return 0;
        $images=get_posts(['post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','numberposts'=>$limit,'meta_query'=>[['key'=>self::META_TAGS,'compare'=>'NOT EXISTS']]]);
        $count=0;
        foreach($images as $img){
            $fn=str_replace(['-','_'],' ',pathinfo(get_attached_file($img->ID),PATHINFO_FILENAME));
            $alt=get_post_meta($img->ID,'_wp_attachment_image_alt',true)?:'';
            $prompt="Analizza filename immagine: \"{$fn}\". Alt: \"{$alt}\". Settore: ".($opts['sector']?:'ristrutturazione')."\nJSON: {\"tags\":[\"...\"],\"service\":\"id_or_empty\",\"approved\":true}";
            $result=V5_Cost::cached_call([['role'=>'user','content'=>$prompt]],$opts['openai_model'],100,72);
            $text=preg_replace('/```json\s*|\s*```/','',trim($result));
            $data=json_decode($text,true);
            if(is_array($data)&&!empty($data['tags'])){
                update_post_meta($img->ID,self::META_TAGS,array_map('sanitize_text_field',$data['tags']));
                if(!empty($data['service'])) update_post_meta($img->ID,self::META_SERVICE,sanitize_key($data['service']));
                if(!empty($data['approved'])) update_post_meta($img->ID,self::META_APPROVED,'1');
                $count++;
            }
        }
        delete_transient('v58_approved'); delete_transient('v58_tagged');
        return $count;
    }
}
