<?php
defined('ABSPATH') || exit;

/**
 * V5.9: AI Article Design System
 *
 * Transforms plain AI content into a high-converting,
 * beautiful article layout with:
 * - Cards, boxes, highlights
 * - CTA blocks (WhatsApp, phone, quote)
 * - FAQ accordions
 * - Trust sections
 * - TOC
 * - Progress bar
 * - Reading time
 * - Mobile-optimized spacing
 * - Brand colors
 * - Local service blocks
 */
class V59_ArticleDesign {

    /**
     * Main entry point — wraps raw AI content in beautiful layout.
     */
    public static function render(array $article, string $kw): array {
        $opts    = V4_Options::get();
        $content = $article['content'] ?? '';

        // Clean AI artifacts FIRST
        $content = self::clean_artifacts($content);

        // Remove H1 from content (template/theme handles the title)
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content);

        // 1. Parse content into sections
        $sections = self::parse_sections($content);

        // 2. Build beautiful HTML
        $html = '';
        // Article structure (no duplicate sections)
        $html .= self::render_reading_bar();
        $html .= self::render_expert_badge($opts, $kw);
        $html .= class_exists('V510_UX') ? V510_UX::render_smart_toc($sections) : self::render_toc($sections);
        $html .= self::render_answer_box($kw, $opts);
        // Social proof near top
        if (class_exists('V510_UX')) $html .= V510_UX::render_social_proof($opts);
        $html .= self::render_sections($sections, $kw, $opts);
        // Bottom blocks
        if (class_exists('V510_UX')) {
            $html .= V510_UX::render_timeline($kw, $opts);
            $html .= V510_UX::render_price_table($kw, $opts);
            $faqs = V510_UX::generate_faq($kw, $opts);
            if (!empty($faqs)) $html .= V510_UX::render_faq($faqs, $kw);
            $html .= V510_UX::render_garanzie($opts); // dark box with guarantees
        }
        $html .= self::render_price_cta($kw, $opts);
        // Author/E-E-A-T box
        $html .= self::render_author_box($opts);
        $html .= self::render_whatsapp_cta($opts);

        $article['content'] = $html;
        return $article;
    }

    /**
     * Remove common AI artifacts from content
     */
    private static function clean_artifacts(string $html): string {
        $html = str_replace(['`html', '`HTML', '"html', '"HTML'], '', $html);
        $html = preg_replace('/^`html\s*/im', '', $html);
        return trim($html);
    }

    // ── Fonti Accordion ──────────────────────────────────────────

    private static function render_fonti_accordion(array $fonti): string {
        if (empty($fonti)) return '';

        $uid   = 'v59f' . substr(md5(serialize($fonti)), 0, 6);
        $items = '';
        foreach ($fonti as $f) {
            $items .= '<li style="margin-bottom:6px">'
                    . '<a href="' . esc_url($f['url']) . '" target="_blank" rel="noopener" '
                    . 'style="color:#2271b1;font-size:13px;text-decoration:none">'
                    . '🔗 ' . esc_html($f['label'])
                    . '</a></li>';
        }

        $js = "var d=document.getElementById('" . $uid . "'),a=this.querySelector('.v59arr');"
            . "d.open=!d.open;a.style.transform=d.open?'rotate(180deg)':'';";

        return '<div class="v59-fonti" style="margin:28px 0;border:1px solid #e8e5e0;border-radius:8px;overflow:hidden;font-family:system-ui,sans-serif">'
             . '<button onclick="' . htmlspecialchars($js) . '" '
             . 'style="width:100%;background:#f8f7f4;border:none;padding:13px 18px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:600;color:#444">'
             . '<span>📚 Fonti ufficiali</span>'
             . '<span class="v59arr" style="transition:transform .25s;display:inline-block">▼</span>'
             . '</button>'
             . '<details open id="' . $uid . '" style="padding:0">'
             . '<summary style="list-style:none;height:0;overflow:hidden;padding:0;margin:0"></summary>'
             . '<ul style="margin:0;padding:14px 18px;list-style:none">' . $items . '</ul>'
             . '</details>'
             . '</div>' . "\n";
    }

    // ── Reading Progress Bar ──────────────────────────────────────

    private static function render_reading_bar(): string {
        // Reading bar div only — JS is injected via wp_footer hook (not inline in post_content)
        return '<div id="v59-reading-bar" style="position:fixed;top:0;left:0;width:0%;height:3px;background:var(--soser-color, #e87c2a);z-index:9999;transition:width .1s"></div>' . "\n";
    }

    // ── Expert Badge ──────────────────────────────────────────────

    private static function render_expert_badge(array $opts, string $kw): string {
        $biz    = esc_html(class_exists('V6_Context') ? V6_Context::val('name') : ($opts['business'] ?: ''));
        $geo    = esc_html(class_exists('V6_Context') ? V6_Context::val('city') : ($opts['geo_city'] ?: ''));
        $year   = date('Y');
        $time   = ceil(str_word_count(strip_tags($kw)) * 0.5 + 4);

        $badge_items = [
            '✅ <strong style="color:#1a1a1a">Verificato da ' . $biz . '</strong>',
            '📍 ' . $geo,
            '📅 Aggiornato ' . $year,
            '⏱️ ~' . $time . ' min',
        ];
        $items_html = implode(
            '<span style="color:#ddd;margin:0 2px">|</span>',
            $badge_items
        );
        return '<div style="background:#fff9f0;border:1px solid #fde8c8;border-radius:6px;padding:9px 14px;margin-bottom:20px;display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;gap:6px 10px;font-family:system-ui,sans-serif;font-size:12px;color:#666;line-height:1.4">'
            . $items_html
            . '</div>' . "\n";
    }

    // ── Table of Contents ─────────────────────────────────────────

    private static function render_toc(array $sections): string {
        $items = '';
        $i     = 1;
        foreach ($sections as $section) {
            if (empty($section['title'])) continue;
            $slug   = 'v59-sec-' . $i;
            $items .= '<li style="margin-bottom:6px"><a href="#' . esc_attr($slug) . '" style="color:var(--soser-color, #e87c2a);text-decoration:none;font-size:14px">'
                    . $i . '. ' . esc_html($section['title']) . '</a></li>';
            $i++;
        }
        if (!$items) return '';

        return '<div class="v59-toc" style="background:#f8f7f4;border:1.5px solid var(--soser-color, #e87c2a);border-radius:8px;padding:18px 22px;margin-bottom:28px;font-family:system-ui,sans-serif">
  <strong style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:var(--soser-color, #e87c2a);display:block;margin-bottom:12px">📋 Indice dell\'articolo</strong>
  <ol style="margin:0;padding-right:20px;list-style:decimal">' . $items . '</ol>
</div>' . "\n";
    }

    // ── Answer Box ────────────────────────────────────────────────

    private static function render_answer_box(string $kw, array $opts): string {
        $city  = esc_html($ctx['city'] ?? ($opts['geo_city'] ?: ''));
        $year  = date('Y');

        // Generate a short direct answer based on keyword type
        if (preg_match('/costo|prezzi?|quanto/i', $kw)) {
            $answer = "Il costo di <strong>" . esc_html($kw) . "</strong> a {$city} nel {$year} varia in base alle dimensioni e ai materiali. Contatta " . esc_html($opts['business'] ?: 'SOSER') . " per un preventivo gratuito entro 24 ore.";
        } elseif (preg_match('/come|guida|passo/i', $kw)) {
            $answer = "Scopri come affrontare <strong>" . esc_html($kw) . "</strong> con " . esc_html($opts['business'] ?: 'SOSER') . " a {$city}: professionisti esperti con oltre 10 anni di esperienza nel settore.";
        } else {
            $answer = "<strong>" . esc_html(ucfirst($kw)) . "</strong> a {$city}: " . esc_html($opts['business'] ?: 'SOSER') . " offre soluzioni professionali chiavi in mano con preventivo gratuito e garanzia sui lavori.";
        }

        return '<div class="v59-answer" style="background:#fff8f2;border-right:4px solid var(--soser-color, #e87c2a);padding:16px 20px;margin-bottom:28px;border-radius:0 8px 8px 0;font-family:system-ui,sans-serif">
  <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--soser-color, #e87c2a);margin-bottom:8px">⚡ Risposta rapida</div>
  <p style="margin:0;font-size:16px;line-height:1.7;color:#2a2a2a">' . $answer . '</p>
</div>' . "\n";
    }

    // ── Content Sections ──────────────────────────────────────────

    private static function render_sections(array $sections, string $kw, array $opts): string {
        $html = '';
        $i    = 1;

        foreach ($sections as $section) {
            $slug    = 'v59-sec-' . $i;
            $title   = $section['title'] ?? '';
            $content = $section['content'] ?? '';

            if ($title) {
                $html .= '<h2 id="' . esc_attr($slug) . '" class="v59-h2" style="font-size:22px;font-weight:700;color:var(--soser-dark, #1a1a1a);margin:40px 0 14px;padding-bottom:10px;border-bottom:3px solid var(--soser-color, #e87c2a);font-family:system-ui,sans-serif;line-height:1.3">'
                       . esc_html($title) . '</h2>' . "\n";
            }

            // Detect and style special content
            $content = self::style_lists($content);
            $content = self::style_prices($content);
            $content = self::style_paragraphs($content);

            $html .= $content . "\n";

            // Insert inline CTA every 3 sections
            if ($i % 3 === 0) {
                $html .= self::render_inline_cta($opts);
            }

            $i++;
        }

        return $html;
    }

    // ── Style Paragraphs ──────────────────────────────────────────

    private static function style_paragraphs(string $content): string {
        // Wrap bare paragraphs with proper styling
        $content = preg_replace(
            '/<p>(.+?)<\/p>/s',
            '<p style="font-size:17px;line-height:1.85;color:#2a2a2a;margin-bottom:20px;font-family:Georgia,serif">$1</p>',
            $content
        );
        return $content;
    }

    // ── Style Lists ───────────────────────────────────────────────

    private static function style_lists(string $content): string {
        // Style UL lists
        $content = preg_replace(
            '/<ul>(.*?)<\/ul>/s',
            '<ul style="margin:16px 0;padding-right:24px;font-family:system-ui,sans-serif">$1</ul>',
            $content
        );
        // Style LI
        $content = preg_replace(
            '/<li>(.*?)<\/li>/s',
            '<li style="font-size:16px;line-height:1.8;margin-bottom:8px;color:#2a2a2a;padding-right:4px">$1</li>',
            $content
        );
        return $content;
    }

    // ── Style Prices (detect price tables) ───────────────────────

    private static function style_prices(string $content): string {
        if (strpos($content, '<table') === false) return $content;

        $content = str_replace(
            '<table>',
            '<div style="overflow-x:auto;margin:20px 0"><table style="width:100%;border-collapse:collapse;font-family:system-ui,sans-serif;font-size:14px">',
            $content
        );
        $content = str_replace('</table>', '</table></div>', $content);
        $content = str_replace('<th>', '<th style="background:var(--soser-dark, #1a1a1a);color:#fff;padding:11px 14px;text-align:right;font-weight:600">', $content);
        $content = str_replace('<td>', '<td style="padding:10px 14px;border-bottom:1px solid #f0ede8;color:#2a2a2a">', $content);
        return $content;
    }

    // ── Inline CTA ────────────────────────────────────────────────

    private static function render_inline_cta(array $opts): string {
        $phone = esc_html($ctx['phone']  ?? ($opts['business_phone'] ?? ''));
        $wa    = $ctx['wa_url'] ?? '#';
        $biz   = esc_html(class_exists('V6_Context') ? V6_Context::val('name') : ($opts['business'] ?: ''));
        $geo   = esc_html(class_exists('V6_Context') ? V6_Context::val('city') : ($opts['geo_city'] ?: ''));
        $urgency = esc_html($ctx['cta_urgency'] ?? '');

                $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        $cta_quote   = class_exists('V6_Profile') ? V6_Profile::cta_quote() : 'Preventivo gratuito entro 24h';
        $trust_line  = class_exists('V6_Profile') ? V6_Profile::trust_line() : '';
        $wa_url      = class_exists('V6_Profile') ? V6_Profile::whatsapp_url() : ('https://wa.me/' . $phone_clean);
        $phone_url   = class_exists('V6_Profile') ? V6_Profile::phone_url() : ('tel:' . $phone_clean);

        return '<div style="background:linear-gradient(135deg,#1a1a1a,#2d2d2d);border-radius:10px;padding:22px;margin:32px 0;font-family:system-ui,sans-serif;text-align:center">'
            . '<div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:14px">' . esc_html($cta_quote) . '</div>'
            . '<div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">'
            . '<a href="' . esc_url($phone_url) . '" style="display:inline-flex;align-items:center;gap:6px;background:var(--soser-color,#e87c2a);color:#fff;padding:11px 22px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:700">📞 Chiama ora</a>'
            . '<a href="' . esc_url($wa_url) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;background:#25d366;color:#fff;padding:11px 22px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:700">💬 WhatsApp</a>'
            . '</div>'
            . '<div style="font-size:12px;color:#aaa">' . esc_html($trust_line) . '</div>'
            . '</div>' . "\n";
    }

    // ── Price CTA Section ─────────────────────────────────────────

    private static function render_price_cta(string $kw, array $opts): string {
        $geo   = esc_html($opts['geo_city'] ?: 'Milano');
        $biz   = esc_html($opts['business'] ?: 'SOSER');
        $year  = date('Y');
        $phone = esc_html(get_option('v59_phone', '392 882 4381'));

        return '<div class="v59-price-cta" style="background:#fff9f0;border:2px solid var(--soser-color, #e87c2a);border-radius:10px;padding:24px;margin:32px 0;font-family:system-ui,sans-serif">
  <h3 style="font-size:18px;font-weight:700;color:var(--soser-dark, #1a1a1a);margin:0 0 8px">💰 Prezzi a ' . $geo . ' (' . $year . ')</h3>
  <p style="font-size:14px;color:#555;margin:0 0 16px">I costi indicati si riferiscono al mercato di ' . $geo . ' e provincia. Per un preventivo gratuito personalizzato, contattaci:</p>
  <div style="display:flex;flex-wrap:wrap;gap:10px">
    <a href="tel:' . preg_replace('/\s/', '', $phone) . '" style="background:var(--soser-color, #e87c2a);color:#fff;padding:11px 22px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:700;display:inline-block">📞 Preventivo gratuito — Chiama ' . $phone . '</a>
  </div>
</div>' . "\n";
    }

    // ── Trust Section ─────────────────────────────────────────────

    private static function render_trust_section(array $opts): string {
        $biz     = esc_html(class_exists('V6_Profile') ? V6_Profile::name() : ($opts['business'] ?: 'SOSER'));
        $geo     = esc_html(class_exists('V6_Profile') ? V6_Profile::city() : ($opts['geo_city'] ?: 'Milano'));
        $stats   = class_exists('V6_Profile') ? V6_Profile::stats() : [];
        $years   = esc_html($stats['years']   ?? get_option('v5_years_experience', '10'));
        $rating  = esc_html($stats['rating']  ?? get_option('v5_rating_value', '4.8'));
        $reviews = esc_html($stats['clients'] ?? get_option('v5_review_count', '47'));
        $cert    = 'Impresa edile certificata';

        $cell = 'background:#fff;border-radius:8px;padding:14px 10px;border:1px solid #e8e5e0;text-align:center;flex:1 1 calc(50% - 6px);min-width:120px;box-sizing:border-box';
        $num  = 'font-size:22px;font-weight:900;color:var(--soser-color,#e87c2a);display:block;margin-bottom:4px';
        $lbl  = 'font-size:11px;color:#888;display:block';

        return '<div style="background:#f8f7f4;border-radius:10px;padding:16px;margin:24px 0;font-family:system-ui,sans-serif">'
             . '<div style="display:flex;flex-wrap:wrap;gap:10px">'
             . '<div style="' . $cell . '"><span style="' . $num . '">' . $years . '+</span><span style="' . $lbl . '">Anni di esperienza</span></div>'
             . '<div style="' . $cell . '"><span style="' . $num . '">⭐ ' . $rating . '</span><span style="' . $lbl . '">' . $reviews . ' recensioni</span></div>'
             . '<div style="' . $cell . '"><span style="' . $num . '">✅</span><span style="' . $lbl . '">' . $cert . '</span></div>'
             . '<div style="' . $cell . '"><span style="' . $num . '">24h</span><span style="' . $lbl . '">Preventivo gratuito</span></div>'
             . '</div>'
             . '</div>' . "\n";
    }

    // ── WhatsApp Sticky CTA ───────────────────────────────────────

    private static function render_author_box(array $opts): string {
        $biz     = class_exists('V6_Profile') ? V6_Profile::name() : ($opts['business'] ?: get_bloginfo('name'));
        $geo     = class_exists('V6_Profile') ? V6_Profile::city() : ($opts['geo_city'] ?: '');
        $stats   = class_exists('V6_Profile') ? V6_Profile::stats() : [];
        $years   = $stats['years']  ?? get_option('v5_years_experience', '10');
        $rating  = $stats['rating'] ?? get_option('v5_rating_value', '4.8');
        $clients = $stats['clients']?? get_option('v5_review_count', '47');
        $cert    = get_option('v5_certifications', 'Impresa edile certificata');
        $color   = class_exists('V6_Profile') ? V6_Profile::color() : '#e87c2a';
        $updated = date('d/m/Y');

        return '<div class="v5-author-box" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;padding:18px 20px;margin:28px 0;display:flex;gap:14px;align-items:center;font-family:system-ui,sans-serif">'
             . '<div style="flex-shrink:0;width:52px;height:52px;background:' . esc_attr($color) . ';border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px">🏗️</div>'
             . '<div>'
             . '<strong style="font-size:14px;display:block;color:#1a1a1a;margin-bottom:3px">' . esc_html($biz) . '</strong>'
             . '<span style="font-size:12px;color:#666;display:block;margin-bottom:5px">' . esc_html($cert) . ' · ' . esc_html($geo) . ' · ' . esc_html($years) . '+ anni di esperienza</span>'
             . '<div style="font-size:12px;color:#888;display:flex;gap:14px;flex-wrap:wrap">'
             . '<span>⭐ ' . esc_html($rating) . '/5 (' . esc_html($clients) . ' recensioni)</span>'
             . '<span>📅 Aggiornato: ' . esc_html($updated) . '</span>'
             . '</div>'
             . '</div>'
             . '</div>' . "\n";
    }

    private static function render_whatsapp_cta(array $opts): string {
        $phone = get_option('v59_phone', '392 882 4381');
        $phone_clean = preg_replace('/\s/', '', $phone);
        $geo   = esc_html($opts['geo_city'] ?: 'Milano');
        $biz   = esc_html($opts['business'] ?: 'SOSER');

        $wa_url_sticky = class_exists('V6_Profile')
            ? V6_Profile::whatsapp_url()
            : 'https://wa.me/39' . $phone_clean . '?text=' . urlencode('Preventivo gratuito');

        return '<div style="position:fixed;bottom:20px;right:16px;z-index:9998">'
            . '<a href="' . esc_url($wa_url_sticky) . '" target="_blank" rel="noopener" '
            . 'style="display:flex;align-items:center;gap:8px;background:#25d366;color:#fff;'
            . 'padding:12px 16px 12px 14px;border-radius:30px;text-decoration:none;'
            . 'font-family:system-ui,sans-serif;font-size:14px;font-weight:700;'
            . 'box-shadow:0 4px 16px rgba(0,0,0,.2);white-space:nowrap">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white" style="flex-shrink:0"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>'
            . '<span>Preventivo gratis</span>'
            . '</a>'
            . '</div>' . "\n";
    }

    // ── Parse sections from HTML ──────────────────────────────────

    private static function parse_sections(string $html): array {
        $sections  = [];
        $current   = ['title' => '', 'content' => ''];
        $parts     = preg_split('/(<h2[^>]*>.*?<\/h2>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if (preg_match('/<h2[^>]*>(.*?)<\/h2>/is', $part, $m)) {
                if (!empty($current['content']) || !empty($current['title'])) {
                    $sections[] = $current;
                }
                $current = ['title' => strip_tags($m[1]), 'content' => ''];
            } else {
                $current['content'] .= $part;
            }
        }
        if (!empty($current['content']) || !empty($current['title'])) {
            $sections[] = $current;
        }
        return array_filter($sections, fn($s) => trim(strip_tags($s['content'] ?? '')) !== '' || !empty($s['title']));
    }

    /**
     * Bulk re-render existing posts with new design.
     */
    /**
     * SAFE bulk apply - skips Elementor posts + creates revision backup.
     * Only applies to posts written by SOSER SEO plugin (has _soser_focus_keyword).
     */
    /**
     * SAFE bulk_apply:
     * - Skips ALL Elementor posts
     * - Skips posts without _soser_focus_keyword (not written by plugin)
     * - Creates WP revision before any change
     * - Only processes plugin-generated articles
     */
    public static function bulk_apply(int $limit = 10): int {
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'meta_key'    => '_soser_focus_keyword',
            'meta_query'  => [
                'relation' => 'AND',
                ['key'=>'_v59_redesigned',     'compare'=>'NOT EXISTS'],
                ['key'=>'_soser_focus_keyword', 'compare'=>'EXISTS'],
                ['key'=>'_elementor_edit_mode', 'compare'=>'NOT EXISTS'],
                ['key'=>'_elementor_data',      'compare'=>'NOT EXISTS'],
                ['key'=>'_elementor_template',  'compare'=>'NOT EXISTS'],
            ],
        ]);

        $count = 0;
        foreach ($posts as $post) {
            $kw   = get_post_meta($post->ID,'_soser_focus_keyword',true) ?: $post->post_title;
            $opts = V4_Options::get();

            // Only apply design wrapper — don't regenerate content
            $clean_content = self::clean_artifacts($post->post_content);
            $clean_content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $clean_content);
            $sections = self::parse_sections($clean_content);
            $new_html = self::render_reading_bar()
                      . self::render_expert_badge($opts, $kw)
                      . self::render_toc($sections)
                      . self::render_answer_box($kw, $opts)
                      . self::render_sections($sections, $kw, $opts)
                      . self::render_price_cta($kw, $opts)
                      . self::render_trust_section($opts)
                      . self::render_whatsapp_cta($opts);

            // Create revision backup before overwriting
            wp_save_post_revision($post->ID);
            wp_update_post(['ID'=>$post->ID,'post_content'=>$new_html]);
            update_post_meta($post->ID,'_v59_redesigned',current_time('mysql'));
            $count++;
        }
        return $count;
    }
}
