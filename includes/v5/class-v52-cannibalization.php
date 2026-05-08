<?php
defined('ABSPATH') || exit;

/**
 * V5.2 Feature 1: Keyword Cannibalization Fixer
 * Detects posts competing for same keyword and suggests merge/differentiation.
 */
class V52_Cannibalization {

    public static function scan(): array {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => ['publish','draft'],
            'numberposts' => 200,
        ]);

        $keyword_map = [];
        foreach ($posts as $post) {
            $kw = get_post_meta($post->ID,'_soser_focus_keyword',true)
               ?: get_post_meta($post->ID,'_yoast_wpseo_focuskw',true)
               ?: '';
            if (!$kw) continue;
            $key = mb_strtolower(preg_replace('/\s+/',' ',trim($kw)));
            $keyword_map[$key][] = [
                'post_id'  => $post->ID,
                'title'    => $post->post_title,
                'url'      => get_permalink($post->ID),
                'status'   => $post->post_status,
                'date'     => $post->post_date,
                'keyword'  => $kw,
            ];
        }

        // Also check semantic duplicates using embeddings
        $conflicts = [];
        $checked   = [];

        foreach ($keyword_map as $kw => $posts_group) {
            // Exact/near-exact conflicts
            if (count($posts_group) > 1) {
                $conflicts[] = [
                    'type'       => 'exact',
                    'severity'   => 'high',
                    'keyword'    => $kw,
                    'posts'      => $posts_group,
                    'suggestion' => self::suggest_fix($posts_group),
                ];
                continue;
            }
            // Semantic conflicts (if embeddings available)
            if (!class_exists('V5_Semantic')) continue;
            foreach ($checked as $other_kw => $other_posts) {
                $sim = 0.0;
                similar_text($kw, $other_kw, $pct);
                $sim = $pct / 100;
                if ($sim > 0.7 && $sim < 1.0) {
                    $all = array_merge($posts_group, $other_posts);
                    $conflicts[] = [
                        'type'       => 'semantic',
                        'severity'   => 'medium',
                        'keyword'    => "$kw vs $other_kw",
                        'posts'      => $all,
                        'similarity' => round($sim * 100) . '%',
                        'suggestion' => 'Differenzia i contenuti o consolida in un articolo più completo',
                    ];
                }
            }
            $checked[$kw] = $posts_group;
        }

        set_transient('v52_cannibalization', $conflicts, 6 * HOUR_IN_SECONDS);
        return $conflicts;
    }

    public static function get_cached(): array {
        $c = get_transient('v52_cannibalization');
        return $c !== false ? $c : self::scan();
    }

    public static function fix_with_ai(int $post_id_keep, int $post_id_merge): bool {
        $opts  = V4_Options::get();
        $keep  = get_post($post_id_keep);
        $merge = get_post($post_id_merge);
        if (!$keep || !$merge || empty($opts['openai_key'])) return false;

        $prompt = "Unisci questi due articoli in uno solo più completo.\n"
                . "Articolo 1 (mantieni): {$keep->post_title}\n"
                . mb_substr(wp_strip_all_tags($keep->post_content),0,1000) . "\n\n"
                . "Articolo 2 (fondi): {$merge->post_title}\n"
                . mb_substr(wp_strip_all_tags($merge->post_content),0,1000) . "\n\n"
                . "Crea un articolo unificato migliore. Rispondi SOLO con HTML.";

        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model'], 3000, 24);
        if (empty($result)) return false;

        wp_update_post(['ID'=>$post_id_keep,'post_content'=>wp_kses_post($result)]);
        // Redirect merged post to keeper
        update_post_meta($post_id_merge,'_v52_merged_to',$post_id_keep);
        wp_update_post(['ID'=>$post_id_merge,'post_status'=>'draft']);

        delete_transient('v52_cannibalization');
        return true;
    }

    private static function suggest_fix(array $posts): string {
        if (count($posts) === 2) return 'Fondi i due articoli in uno usando il pulsante "Unisci"';
        return 'Mantieni l\'articolo più lungo, converti gli altri in redirect 301';
    }
}
