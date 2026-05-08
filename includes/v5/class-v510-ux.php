<?php
defined('ABSPATH') || exit;

/**
 * V5.10: Advanced UX Features
 */
class V510_UX {

    // ── FAQ Accordion + FAQPage Schema ────────────────────────────

    public static function render_faq(array $faqs, string $kw): string {
        if (empty($faqs)) return '';
        $uid = 'v510faq' . substr(md5($kw), 0, 5);
        $html = $schema = '';
        $schema_items = [];

        $html .= '<div class="v510-faq" style="margin:32px 0;font-family:system-ui,sans-serif">';
        $html .= '<h2 style="font-size:20px;font-weight:700;color:#1a1a1a;margin-bottom:16px;padding-bottom:10px;border-bottom:3px solid #e87c2a">❓ Domande Frequenti</h2>';

        foreach ($faqs as $i => $faq) {
            $q = esc_html($faq['q'] ?? '');
            $a = wp_kses_post($faq['a'] ?? '');
            $fid = $uid . $i;
            $schema_items[] = ['@type'=>'Question','name'=>strip_tags($q),'acceptedAnswer'=>['@type'=>'Answer','text'=>strip_tags($a)]];

            $html .= '<div style="border:1px solid #e8e5e0;border-radius:8px;margin-bottom:8px;overflow:hidden">';
            $html .= '<button onclick="var d=document.getElementById(\'' . $fid . '\'),a=this.querySelector(\'span.arr\');d.style.display=d.style.display===\'none\'?\'block\':\'none\';a.style.transform=d.style.display===\'none\'?\'rotate(0)\':\'rotate(180deg)\'" style="width:100%;background:#f8f7f4;border:none;padding:14px 18px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-size:15px;font-weight:600;color:#1a1a1a;text-align:right"><span>' . $q . '</span><span class="arr" style="color:#e87c2a;transition:transform .25s;font-size:18px">▼</span></button>';
            $html .= '<div id="' . $fid . '" style="display:none;padding:14px 18px;background:#fff;font-size:15px;line-height:1.7;color:#2a2a2a">' . $a . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if (!empty($schema_items)) {
            $html .= '<script type="application/ld+json">' . wp_json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$schema_items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
        }
        return $html;
    }

    public static function generate_faq(string $kw, array $opts): array {
        $prompt = "Genera 5 FAQ reali per: \"" . $kw . "\" a " . ($opts['geo_city']?:'Milano') . " (" . date('Y') . ").\n"
                . "Includi prezzi, tempistiche, permessi, consigli.\n"
                . "JSON: [{\"q\":\"...\",\"a\":\"...(2-3 frasi)\"}]";
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model']?:'gpt-4.1-mini', 400, 72);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $faqs   = json_decode($text, true);
        return is_array($faqs) ? $faqs : [];
    }

    // ── Price Table ───────────────────────────────────────────────

    public static function render_price_table(string $kw, array $opts): string {
        $geo  = esc_html($opts['geo_city'] ?: 'Milano');
        $year = date('Y');

        $prompt = "Prezzi " . $kw . " a " . ($opts['geo_city']?:'Milano') . " " . $year . ". 3 fasce.\n"
                . "JSON: [{\"tier\":\"Base\",\"price\":\"X-Y euro\",\"includes\":[\"...\",\"...\"],\"ideal\":\"...\"},{\"tier\":\"Standard\",\"price\":\"X-Y euro\",\"includes\":[\"...\"],\"ideal\":\"...\"},{\"tier\":\"Premium\",\"price\":\"X-Y euro\",\"includes\":[\"...\"],\"ideal\":\"...\"}]";
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model']?:'gpt-4.1-mini', 400, 72);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $tiers  = json_decode($text, true);
        if (!is_array($tiers) || count($tiers) < 3) {
            return '<div style="background:#fff9f0;border:1px solid #fde8c8;border-radius:8px;padding:16px;margin:28px 0;font-family:system-ui,sans-serif"><p style="margin:0;font-size:14px">Per un preventivo preciso su <strong>' . esc_html($kw) . '</strong> a ' . $geo . ', contattaci gratuitamente.</p></div>';
        }

        $bg = ['#f8f7f4','#f0f6fc','#1a1a1a'];
        $tc = ['#1a1a1a','#1a1a1a','#fff'];
        $badge = ['','','⭐ Più richiesto'];

        $html  = '<div style="margin:32px 0;font-family:system-ui,sans-serif">';
        $html .= '<h3 style="font-size:18px;font-weight:700;color:#1a1a1a;margin-bottom:14px">💰 Prezzi ' . esc_html($kw) . ' ' . $geo . ' ' . $year . '</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">';

        foreach ($tiers as $i => $tier) {
            $b = isset($bg[$i]) ? $bg[$i] : '#f8f7f4';
            $t = isset($tc[$i]) ? $tc[$i] : '#1a1a1a';
            $html .= '<div style="background:' . $b . ';border-radius:10px;padding:20px;border:' . ($i===2?'2px solid #e87c2a':'1px solid #e8e5e0') . '">';
            if (!empty($badge[$i])) $html .= '<div style="background:#e87c2a;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:10px;display:inline-block;margin-bottom:8px">' . esc_html($badge[$i]) . '</div>';
            $html .= '<div style="font-size:15px;font-weight:700;color:' . $t . ';margin-bottom:4px">' . esc_html($tier['tier'] ?? '') . '</div>';
            $html .= '<div style="font-size:22px;font-weight:900;color:#e87c2a;margin-bottom:12px">' . esc_html($tier['price'] ?? '') . '</div>';
            if (!empty($tier['includes'])) {
                $html .= '<ul style="margin:0;padding:0;list-style:none">';
                foreach ((array)$tier['includes'] as $item) $html .= '<li style="font-size:12px;color:' . ($i===2?'#ccc':'#555') . ';margin-bottom:5px">✓ ' . esc_html($item) . '</li>';
                $html .= '</ul>';
            }
            if (!empty($tier['ideal'])) $html .= '<div style="font-size:12px;color:' . ($i===2?'#aaa':'#888') . ';margin-top:8px;font-style:italic">' . esc_html($tier['ideal']) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div><p style="font-size:11px;color:#aaa;margin-top:8px">* Prezzi indicativi per ' . $geo . '. Preventivo gratuito su richiesta.</p></div>';
        return $html;
    }

    // ── Garanzie Box ─────────────────────────────────────────────

    public static function render_garanzie(array $opts): string {
        $biz   = esc_html($opts['business'] ?: 'SOSER');
        $years = esc_html(get_option('v5_years_experience','10'));
        $phone = esc_html(get_option('v59_phone','392 882 4381'));
        $items = [
            ['🏆', $years . '+ anni di esperienza'],
            ['📋', 'Preventivo scritto gratuito'],
            ['✅', 'Materiali certificati'],
            ['🛡️', '2 anni di garanzia'],
            ['⚡', 'Risposta entro 24 ore'],
            ['📍', 'Milano e provincia'],
        ];
        $html  = '<div style="background:linear-gradient(135deg,#1a1a1a,#2a2a2a);border-radius:12px;padding:24px;margin:32px 0;font-family:system-ui,sans-serif">';
        $html .= '<h3 style="font-size:16px;font-weight:700;color:#fff;margin:0 0 16px">🏅 Perché scegliere ' . $biz . '</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px">';
        foreach ($items as $item) $html .= '<div style="display:flex;align-items:center;gap:8px"><span style="font-size:18px">' . $item[0] . '</span><span style="font-size:13px;color:#ddd">' . esc_html($item[1]) . '</span></div>';
        $html .= '</div><a href="tel:' . preg_replace('/\s/','',$phone) . '" style="display:inline-block;background:#e87c2a;color:#fff;padding:11px 24px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:700">📞 Preventivo gratuito</a></div>';
        return $html;
    }

    // ── Social Proof ─────────────────────────────────────────────

    public static function render_social_proof(array $opts): string {
        $geo     = esc_html($opts['geo_city'] ?: 'Milano');
        $reviews = esc_html(get_option('v5_review_count','47'));
        $rating  = esc_html(get_option('v5_rating_value','4.8'));
        $years   = esc_html(get_option('v5_years_experience','10'));
        $stats   = [[$years.'+','anni a '.$geo],[$reviews,'clienti soddisfatti'],['⭐ '.$rating,'valutazione'],['24h','risposta garantita']];
        $html    = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:28px 0;font-family:system-ui,sans-serif">';
        foreach ($stats as $s) $html .= '<div style="text-align:center;background:#fff9f0;border:1px solid #fde8c8;border-radius:8px;padding:14px"><div style="font-size:24px;font-weight:900;color:#e87c2a">' . esc_html($s[0]) . '</div><div style="font-size:11px;color:#666;margin-top:2px">' . esc_html($s[1]) . '</div></div>';
        $html .= '</div>';
        return $html;
    }

    // ── Smart TOC (collapsed on mobile) ──────────────────────────

    public static function render_smart_toc(array $sections): string {
        if (count($sections) < 2) return '';
        $icons = ['📍','💰','🔧','📋','❓','✅','📊','🏗️','🎯','📌'];
        $items = ''; $i = 1;
        foreach ($sections as $section) {
            if (empty($section['title'])) continue;
            $icon   = $icons[($i-1) % count($icons)];
            $items .= '<li><a href="#v59-sec-' . $i . '" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:6px;text-decoration:none;color:#2a2a2a;font-size:14px">'
                    . '<span>' . $icon . '</span><span>' . $i . '. ' . esc_html($section['title']) . '</span></a></li>';
            $i++;
        }
        if (!$items) return '';
        return '<div style="margin:0 0 28px;font-family:system-ui,sans-serif">'
             . '<details style="border:1.5px solid #e87c2a;border-radius:8px;overflow:hidden">'
             . '<summary style="padding:13px 18px;background:#fff9f0;cursor:pointer;font-size:13px;font-weight:700;color:#e87c2a;display:flex;justify-content:space-between;list-style:none"><span>📋 Indice dell\'articolo</span><span style="font-size:11px;color:#aaa;margin-top:1px">▼ apri</span></summary>'
             . '<div style="background:#fff;padding:8px"><ol style="margin:0;padding:0;list-style:none">' . $items . '</ol></div>'
             . '</details></div>';
    }

    // ── Timeline ─────────────────────────────────────────────────

    public static function render_timeline(string $kw, array $opts): string {
        $prompt = "Timeline esecuzione lavori per \"" . $kw . "\". 4 fasi. JSON: [{\"fase\":\"...\",\"giorni\":\"...\",\"desc\":\"...\"}]";
        $result = V5_Cost::cached_call([['role'=>'user','content'=>$prompt]], $opts['openai_model']?:'gpt-4.1-mini', 200, 72);
        $text   = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $phases = json_decode($text, true);
        if (!is_array($phases) || empty($phases)) return '';

        $html = '<div style="margin:28px 0;font-family:system-ui,sans-serif"><h3 style="font-size:16px;font-weight:700;color:#1a1a1a;margin-bottom:14px">📅 Tempi di esecuzione</h3>';
        foreach ($phases as $i => $p) {
            $html .= '<div style="display:flex;gap:14px;margin-bottom:10px;align-items:flex-start">'
                   . '<div style="flex-shrink:0;width:34px;height:34px;background:#e87c2a;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px">' . ($i+1) . '</div>'
                   . '<div style="flex:1;background:#f8f7f4;border-radius:8px;padding:10px 14px"><div style="display:flex;justify-content:space-between;margin-bottom:3px"><strong style="font-size:13px">' . esc_html($p['fase']??'') . '</strong><span style="font-size:11px;background:#e87c2a;color:#fff;padding:2px 7px;border-radius:8px">' . esc_html($p['giorni']??'') . '</span></div><p style="font-size:12px;color:#666;margin:0">' . esc_html($p['desc']??'') . '</p></div></div>';
        }
        $html .= '</div>';
        return $html;
    }
}
