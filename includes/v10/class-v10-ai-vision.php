<?php
defined('ABSPATH') || exit;

/**
 * V10_AIVision — AI-powered image analyzer
 *
 * Uses GPT-4 Vision to:
 * 1. Understand what's in each photo
 * 2. Write SEO alt text in correct language
 * 3. Assign categories (bagno, cucina, pavimenti, etc.)
 * 4. Match images to articles automatically
 */
class V10_AIVision {

    const META_ANALYZED  = '_v10_ai_analyzed';
    const META_CATEGORY  = '_v10_ai_category';
    const META_KEYWORDS  = '_v10_ai_keywords';
    const META_ALT       = '_v10_ai_alt';
    const META_DESC      = '_v10_ai_desc';
    const BATCH_SIZE     = 5;

    // ── Analyze single image ──────────────────────────────────────

    public static function analyze(int $attachment_id, array $opts = []): bool {
        if (empty($opts)) $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return false;

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return false;

        $mime = mime_content_type($file);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) return false;

        // Read image as base64
        $b64  = base64_encode(file_get_contents($file));
        $lang = class_exists('V6_Profile') ? V6_Profile::language() : 'it';
        $biz  = class_exists('V6_Profile') ? V6_Profile::name() : 'SOSER';
        $ind  = class_exists('V6_Profile') ? V6_Profile::industry() : 'ristrutturazioni';
        $city = class_exists('V6_Profile') ? V6_Profile::city() : 'Milano';

        $prompt = self::build_prompt($lang, $biz, $ind, $city);

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'gpt-4o-mini',
                'max_tokens' => 500,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => 'data:' . $mime . ';base64,' . $b64,
                                'detail' => 'low',
                            ],
                        ],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return false;

        $body = json_decode(wp_remote_retrieve_body($r), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        $text = preg_replace('/^```json\s*|\s*```$/i', '', trim($text));
        $data = json_decode($text, true);

        if (empty($data)) return false;

        // Save results
        $alt      = sanitize_text_field($data['alt_text']    ?? '');
        $category = sanitize_text_field($data['category']    ?? '');
        $keywords = array_map('sanitize_text_field', $data['keywords'] ?? []);
        $desc     = sanitize_text_field($data['description'] ?? '');

        update_post_meta($attachment_id, self::META_ALT,       $alt);
        update_post_meta($attachment_id, self::META_CATEGORY,  $category);
        update_post_meta($attachment_id, self::META_KEYWORDS,  $keywords);
        update_post_meta($attachment_id, self::META_DESC,      $desc);
        update_post_meta($attachment_id, self::META_ANALYZED,  date('Y-m-d H:i:s'));

        // Also update WP alt text
        if ($alt) update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);

        // Update V58 tags for matching
        if (!empty($keywords) && class_exists('V58_ImagePicker')) {
            update_post_meta($attachment_id, V58_ImagePicker::META_TAGS, $keywords);
            if ($category) {
                update_post_meta($attachment_id, V58_ImagePicker::META_SERVICE, $category);
                update_post_meta($attachment_id, V58_ImagePicker::META_APPROVED, '1');
            }
        }

        return true;
    }

    // ── Batch analyze unanalyzed images ──────────────────────────

    public static function batch_analyze(int $limit = 5): array {
        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return ['error' => 'OpenAI key mancante'];

        // Get unanalyzed images
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg','image/png','image/webp'],
            'post_status'    => 'inherit',
            'posts_per_page' => $limit,
            'meta_query'     => [[
                'key'     => self::META_ANALYZED,
                'compare' => 'NOT EXISTS',
            ]],
        ]);

        $done   = 0;
        $failed = 0;
        $results = [];

        foreach ($attachments as $att) {
            $ok = self::analyze($att->ID, $opts);
            if ($ok) {
                $done++;
                $results[] = [
                    'id'       => $att->ID,
                    'file'     => basename(get_attached_file($att->ID)),
                    'alt'      => get_post_meta($att->ID, self::META_ALT, true),
                    'category' => get_post_meta($att->ID, self::META_CATEGORY, true),
                    'status'   => 'ok',
                ];
            } else {
                $failed++;
                $results[] = [
                    'id'     => $att->ID,
                    'file'   => basename(get_attached_file($att->ID)),
                    'status' => 'failed',
                ];
            }
            // Small delay to avoid rate limits
            usleep(300000); // 0.3s
        }

        return [
            'done'    => $done,
            'failed'  => $failed,
            'total'   => count($attachments),
            'results' => $results,
        ];
    }

    // ── Re-analyze single image ───────────────────────────────────

    public static function reanalyze(int $attachment_id): bool {
        delete_post_meta($attachment_id, self::META_ANALYZED);
        return self::analyze($attachment_id);
    }

    // ── Find best image for keyword ───────────────────────────────

    public static function find_for_keyword(string $keyword): ?int {
        $kw_lower = strtolower($keyword);

        // Get all analyzed images
        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '" . self::META_ANALYZED . "'
             ORDER BY meta_id DESC"
        );

        if (empty($ids)) return null;

        $scores = [];
        foreach ($ids as $id) {
            $score    = 0;
            $category = strtolower(get_post_meta($id, self::META_CATEGORY, true) ?? '');
            $keywords = (array) (get_post_meta($id, self::META_KEYWORDS, true) ?? []);
            $alt      = strtolower(get_post_meta($id, self::META_ALT, true) ?? '');

            // Score by category match
            similar_text($kw_lower, $category, $pct);
            if ($pct >= 60) $score += 50;

            // Score by keyword match
            foreach ($keywords as $kw) {
                similar_text($kw_lower, strtolower($kw), $p);
                if ($p >= 70) $score += 40;
                elseif ($p >= 50) $score += 20;
            }

            // Score by alt text
            similar_text($kw_lower, $alt, $pa);
            $score += (int)($pa / 3);

            if ($score > 0) $scores[$id] = $score;
        }

        if (empty($scores)) return null;
        arsort($scores);
        return (int) array_key_first($scores);
    }

    // ── Stats ─────────────────────────────────────────────────────

    public static function stats(): array {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'"
        );

        $analyzed = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key = '" . self::META_ANALYZED . "'"
        );

        // Categories breakdown
        $cats = $wpdb->get_results(
            "SELECT meta_value as cat, COUNT(*) as n
             FROM {$wpdb->postmeta}
             WHERE meta_key = '" . self::META_CATEGORY . "'
             AND meta_value != ''
             GROUP BY meta_value
             ORDER BY n DESC
             LIMIT 10",
            ARRAY_A
        );

        return [
            'total'      => $total,
            'analyzed'   => $analyzed,
            'pending'    => $total - $analyzed,
            'categories' => $cats ?: [],
        ];
    }

    // ── Build prompt ──────────────────────────────────────────────

    private static function build_prompt(string $lang, string $biz, string $industry, string $city): string {
        $prompts = [
            'it' => "Analizza questa foto per il sito web di {$biz}, impresa di {$industry} a {$city}.

Rispondi SOLO con JSON:
{
  \"alt_text\": \"testo alt SEO descrittivo in italiano (max 120 caratteri)\",
  \"category\": \"categoria principale (es: bagno, cucina, pavimenti, tinteggiatura, impianti-elettrici, cartongesso, ristrutturazione-completa, esterno, cantiere, prima-dopo)\",
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"],
  \"description\": \"descrizione breve di cosa mostra la foto (1 frase)\"
}

Sii preciso: se vedi un bagno rinnovato scrivi 'bagno', se vedi operai al lavoro scrivi 'cantiere', ecc.",

            'en' => "Analyze this photo for {$biz}, a {$industry} company in {$city}.

Reply ONLY with JSON:
{
  \"alt_text\": \"SEO alt text in English (max 120 chars)\",
  \"category\": \"main category (e.g.: bathroom, kitchen, flooring, painting, electrical, drywall, full-renovation, exterior, construction, before-after)\",
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"],
  \"description\": \"brief description of what the photo shows (1 sentence)\"
}",

            'ar' => "حلّل هذه الصورة لموقع {$biz}، شركة {$industry} في {$city}.

أجب فقط بـ JSON:
{
  \"alt_text\": \"نص alt لـ SEO بالعربية (أقصى 120 حرف)\",
  \"category\": \"الفئة الرئيسية (مثال: حمام، مطبخ، أرضيات، دهانات، كهرباء، تشطيب-كامل، خارجي)\",
  \"keywords\": [\"كلمة1\", \"كلمة2\", \"كلمة3\", \"كلمة4\", \"كلمة5\"],
  \"description\": \"وصف مختصر لما تظهره الصورة (جملة واحدة)\"
}",
        ];

        return $prompts[$lang] ?? $prompts['en'];
    }
}
