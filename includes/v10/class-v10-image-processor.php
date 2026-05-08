<?php
defined('ABSPATH') || exit;

/**
 * V10_ImageProcessor — Auto-processes every uploaded image.
 *
 * On upload:
 *   1. Resize to max 1400px
 *   2. Enhance: brightness + contrast + saturation
 *   3. Compress (JPEG quality 82%)
 *   4. Convert to WebP
 *   5. AI Vision → Alt text + Title + Caption + Service tag
 */
class V10_ImageProcessor {

    const OPT      = 'soser_v10_img_cfg';
    const META_TAG = '_soser_img_service';
    const META_DONE= '_soser_img_processed';

    // ── Config ────────────────────────────────────────────────────

    public static function get_config(): array {
        return array_merge([
            'enabled'      => '1',
            'max_width'    => '1400',
            'quality'      => '82',
            'convert_webp' => '1',
            'enhance'      => '1',
            'brightness'   => '8',
            'contrast'     => '15',
            'saturation'   => '10',
            'ai_alt'       => '1',
        ], get_option(self::OPT, []));
    }

    public static function save_config(array $data): void {
        $clean = [];
        foreach (array_keys(self::get_config()) as $k) {
            $clean[$k] = sanitize_text_field($data[$k] ?? '');
        }
        update_option(self::OPT, $clean);
    }

    // ── Init ──────────────────────────────────────────────────────

    public static function init(): void {
        // Process on every new upload
        add_action('add_attachment', [__CLASS__, 'process_single'], 20);
    }

    // ── Process single image ──────────────────────────────────────

    public static function process_single(int $id): void {
        $cfg  = self::get_config();
        if ($cfg['enabled'] !== '1') return;

        $mime = get_post_mime_type($id);
        if (!str_starts_with($mime, 'image/')) return;

        $file = get_attached_file($id);
        if (!$file || !file_exists($file)) return;

        // Skip if already processed
        if (get_post_meta($id, self::META_DONE, true)) return;

        // Step 1: Enhance + Resize + Compress
        if ($cfg['enhance'] === '1') {
            self::enhance($file, $cfg);
        }

        // Step 2: Convert to WebP
        if ($cfg['convert_webp'] === '1') {
            $webp = self::to_webp($file, (int)$cfg['quality']);
            if ($webp && $webp !== $file) {
                @unlink($file); // remove original
                update_attached_file($id, $webp);
                wp_update_post(['ID' => $id, 'post_mime_type' => 'image/webp']);
                $file = $webp;
            }
        }

        // Step 3: AI Alt text
        if ($cfg['ai_alt'] === '1') {
            self::ai_describe($id, $file);
        }

        // Mark as processed
        update_post_meta($id, self::META_DONE, date('Y-m-d H:i:s'));

        // Regenerate thumbnails
        $metadata = wp_generate_attachment_metadata($id, $file);
        wp_update_attachment_metadata($id, $metadata);
    }

    // ── Bulk process existing images ──────────────────────────────

    public static function bulk_process(int $limit = 20): array {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => $limit,
            'meta_query'     => [
                ['key' => self::META_DONE, 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $done   = 0;
        $errors = [];

        foreach ($attachments as $att) {
            try {
                self::process_single($att->ID);
                $done++;
            } catch (Throwable $e) {
                $errors[] = "ID {$att->ID}: " . $e->getMessage();
            }
        }

        return ['done' => $done, 'errors' => $errors, 'remaining' => self::count_unprocessed()];
    }

    public static function count_unprocessed(): int {
        global $wpdb;
        return (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '" . self::META_DONE . "'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND m.meta_value IS NULL
        ");
    }

    // ── Image Enhancement ─────────────────────────────────────────

    private static function enhance(string $file, array $cfg): void {
        if (!extension_loaded('gd')) return;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $img = match($ext) {
            'jpg','jpeg' => @imagecreatefromjpeg($file),
            'png'        => @imagecreatefrompng($file),
            'webp'       => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : null,
            default      => null,
        };
        if (!$img) return;

        $ow = imagesx($img);
        $oh = imagesy($img);
        $mw = (int)$cfg['max_width'];

        // Resize if needed
        if ($ow > $mw) {
            $nh      = (int)round($oh * $mw / $ow);
            $resized = imagecreatetruecolor($mw, $nh);
            if ($ext === 'png') { imagealphablending($resized, false); imagesavealpha($resized, true); }
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $mw, $nh, $ow, $oh);
            imagedestroy($img);
            $img = $resized;
        }

        // Brightness
        $b = (int)$cfg['brightness'];
        if ($b) imagefilter($img, IMG_FILTER_BRIGHTNESS, $b);

        // Contrast (GD: negative = more contrast)
        $c = (int)$cfg['contrast'];
        if ($c) imagefilter($img, IMG_FILTER_CONTRAST, -$c);

        // Sharpen slightly
        $sharpen = [
            [0, -1, 0],
            [-1, 9, -1],
            [0, -1, 0],
        ];
        imageconvolution($img, $sharpen, 5, 0);

        // Save
        $q = (int)$cfg['quality'];
        match($ext) {
            'jpg','jpeg' => imagejpeg($img, $file, $q),
            'png'        => imagepng($img, $file, (int)round((100-$q)/11)),
            'webp'       => function_exists('imagewebp') ? imagewebp($img, $file, $q) : null,
            default      => null,
        };

        imagedestroy($img);
    }

    // ── WebP Conversion ───────────────────────────────────────────

    private static function to_webp(string $file, int $quality): ?string {
        if (!function_exists('imagewebp')) return null;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'webp') return $file; // already webp

        $img = match($ext) {
            'jpg','jpeg' => @imagecreatefromjpeg($file),
            'png'        => @imagecreatefrompng($file),
            default      => null,
        };
        if (!$img) return null;

        // PNG transparency
        if ($ext === 'png') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        $webp_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
        $ok        = imagewebp($img, $webp_file, $quality);
        imagedestroy($img);

        return ($ok && file_exists($webp_file)) ? $webp_file : null;
    }

    // ── AI Vision: describe image ─────────────────────────────────

    private static function ai_describe(int $id, string $file): void {
        $opts = V4_Options::get();
        if (empty($opts['openai_key'])) return;

        // Read image as base64
        $data   = @file_get_contents($file);
        if (!$data) return;
        $b64    = base64_encode($data);
        $mime   = get_post_mime_type($id) ?: 'image/jpeg';

        $profile  = class_exists('V6_Profile') ? V6_Profile::get() : [];
        $biz      = $profile['name']     ?? 'SOSER';
        $city     = $profile['city']     ?? 'Milano';
        $industry = $profile['industry'] ?? 'ristrutturazione';
        $lang     = class_exists('V6_Profile') ? V6_Profile::language() : 'it';

        $prompts = [
            'it' => "Sei un esperto SEO per {$biz}, azienda di {$industry} a {$city}.\n\n"
                  . "Analizza questa immagine e rispondi SOLO con JSON:\n"
                  . "{\n"
                  . "  \"alt\": \"descrizione SEO max 125 caratteri con keyword principale\",\n"
                  . "  \"title\": \"titolo immagine max 60 caratteri\",\n"
                  . "  \"caption\": \"didascalia breve max 80 caratteri\",\n"
                  . "  \"service\": \"una parola chiave es: bagno, cucina, pavimenti, tinteggiatura, impianti\",\n"
                  . "  \"quality\": \"prima_lavori|durante_lavori|dopo_lavori|prodotto|ambiente\"\n"
                  . "}",
            'en' => "You are an SEO expert for {$biz}, {$industry} company in {$city}.\n\n"
                  . "Analyze this image and reply ONLY with JSON:\n"
                  . "{\n"
                  . "  \"alt\": \"SEO description max 125 chars with main keyword\",\n"
                  . "  \"title\": \"image title max 60 chars\",\n"
                  . "  \"caption\": \"short caption max 80 chars\",\n"
                  . "  \"service\": \"one keyword eg: bathroom, kitchen, flooring, painting\",\n"
                  . "  \"quality\": \"before_work|during_work|after_work|product|environment\"\n"
                  . "}",
            'ar' => "أنت خبير SEO لـ {$biz}، شركة {$industry} في {$city}.\n\n"
                  . "حلّل هذه الصورة وأجب فقط بـ JSON:\n"
                  . "{\n"
                  . "  \"alt\": \"وصف SEO أقصاه 125 حرفاً مع الكلمة المفتاحية\",\n"
                  . "  \"title\": \"عنوان الصورة أقصاه 60 حرفاً\",\n"
                  . "  \"caption\": \"تعليق قصير أقصاه 80 حرفاً\",\n"
                  . "  \"service\": \"كلمة مفتاحية واحدة مثل: حمام، مطبخ، أرضيات\",\n"
                  . "  \"quality\": \"before_work|during_work|after_work|product|environment\"\n"
                  . "}",
        ];

        $prompt = $prompts[$lang] ?? $prompts['it'];

        $r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'gpt-4o-mini',
                'max_tokens' => 300,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text',       'text'      => $prompt],
                        ['type' => 'image_url',  'image_url' => [
                            'url'    => "data:{$mime};base64,{$b64}",
                            'detail' => 'low',
                        ]],
                    ],
                ]],
            ]),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return;

        $body = json_decode(wp_remote_retrieve_body($r), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        $text = preg_replace('/```json|```/i', '', trim($text));
        $data = json_decode($text, true);

        if (empty($data)) return;

        // Save alt text
        if (!empty($data['alt'])) {
            update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($data['alt']));
        }

        // Save title + caption to post
        $update = ['ID' => $id];
        if (!empty($data['title']))   $update['post_title']   = sanitize_text_field($data['title']);
        if (!empty($data['caption'])) $update['post_excerpt'] = sanitize_text_field($data['caption']);
        if (count($update) > 1) wp_update_post($update);

        // Save service tag for auto-matching with articles
        if (!empty($data['service'])) {
            update_post_meta($id, self::META_TAG, sanitize_text_field($data['service']));
        }
        if (!empty($data['quality'])) {
            update_post_meta($id, '_soser_img_quality', sanitize_text_field($data['quality']));
        }
    }

    // ── Find best image for a keyword ─────────────────────────────

    public static function find_for_keyword(string $keyword, int $limit = 3): array {
        global $wpdb;

        // Extract service from keyword
        $services = ['bagno','cucina','pavimenti','tinteggiatura','impianti','cartongesso',
                     'ristrutturazione','bathroom','kitchen','flooring','painting'];
        $service  = '';
        foreach ($services as $s) {
            if (stripos($keyword, $s) !== false) { $service = $s; break; }
        }

        if (!$service) {
            // Fallback: return latest processed images
            return get_posts([
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => $limit,
                'meta_key'       => self::META_DONE,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
        }

        // Find images tagged with this service
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value LIKE %s
             LIMIT %d",
            self::META_TAG,
            '%' . $wpdb->esc_like($service) . '%',
            $limit
        ));

        if (empty($ids)) return [];

        return array_map('get_post', $ids);
    }

    // ── Stats ─────────────────────────────────────────────────────

    public static function stats(): array {
        global $wpdb;
        $total     = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'");
        $processed = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='" . self::META_DONE . "'");
        return [
            'total'     => $total,
            'processed' => $processed,
            'remaining' => max(0, $total - $processed),
        ];
    }
}
