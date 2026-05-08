<?php
defined('ABSPATH') || exit;

/**
 * V8_ImageEngine — Smart image generation & sourcing.
 *
 * Priority order:
 *   1. DALL-E 3 / gpt-image-1  (if OpenAI key + image enabled)
 *   2. Unsplash API             (if Unsplash key configured)
 *   3. Pexels API               (if Pexels key configured)
 *   4. Skip silently            (no image = no crash)
 */
class V8_ImageEngine {

    const OPT = 'soser_v8_image_cfg';

    // ── Config ────────────────────────────────────────────────────

    public static function get_config(): array {
        return array_merge([
            'source'         => 'dalle',   // dalle | unsplash | pexels | auto
            'unsplash_key'   => '',
            'pexels_key'     => '',
            'dalle_model'    => 'gpt-image-1',
            'dalle_size'     => '1024x1024',
            'dalle_quality'  => 'medium',
            'style_suffix'   => 'professional photography, high quality, realistic',
            'featured'       => '1',
            'inline_count'   => '2',
        ], get_option(self::OPT, []));
    }

    public static function save_config(array $data): void {
        $clean = [];
        foreach (array_keys(self::get_config()) as $k) {
            $clean[$k] = sanitize_text_field($data[$k] ?? '');
        }
        update_option(self::OPT, $clean);
    }

    // ── Main entry point ──────────────────────────────────────────

    /**
     * Generate or fetch one image.
     * Returns WP attachment ID or 0 on failure.
     */
    public static function get_image(string $prompt, string $keyword, array $opts): int {
        $cfg    = self::get_config();
        $source = $cfg['source'];

        // Build smart prompt using business profile
        $smart_prompt = self::build_prompt($prompt, $keyword, $cfg);

        // Auto = try DALL-E first, fallback to Unsplash
        if ($source === 'auto' || $source === 'dalle') {
            $id = self::dalle($smart_prompt, $keyword, $opts, $cfg);
            if ($id > 0) return $id;
            // Fallback
            if ($source === 'auto') {
                $id = self::unsplash($keyword, $cfg);
                if ($id > 0) return $id;
                return self::pexels($keyword, $cfg);
            }
        }

        if ($source === 'unsplash') return self::unsplash($keyword, $cfg);
        if ($source === 'pexels')   return self::pexels($keyword, $cfg);

        return 0;
    }

    // ── Smart prompt builder ──────────────────────────────────────

    private static function build_prompt(string $ai_prompt, string $keyword, array $cfg): string {
        $profile  = class_exists('V6_Profile') ? V6_Profile::get() : [];
        $city     = $profile['city']     ?? '';
        $industry = $profile['industry'] ?? '';
        $suffix   = $cfg['style_suffix'] ?: 'professional photography, high quality, realistic';

        // Remove placeholder language from AI prompt
        $prompt = $ai_prompt;
        $prompt = preg_replace('/ristrutturazione|Milano|soser\.it/i', '', $prompt);
        $prompt = trim(preg_replace('/\s+/', ' ', $prompt));

        // If AI prompt is empty or too generic, build from keyword
        if (mb_strlen($prompt) < 15) {
            $prompt = $keyword;
            if ($industry) $prompt .= ", {$industry}";
            if ($city)     $prompt .= ", {$city}";
        }

        return "{$prompt}, {$suffix}";
    }

    // ── DALL-E / gpt-image-1 ──────────────────────────────────────

    private static function dalle(string $prompt, string $alt, array $opts, array $cfg): int {
        if (empty($opts['openai_key'])) return 0;

        $model = $cfg['dalle_model'] ?: 'gpt-image-1';
        $body  = [
            'model'   => $model,
            'prompt'  => mb_substr($prompt, 0, 4000),
            'size'    => $cfg['dalle_size'] ?: '1024x1024',
            'n'       => 1,
        ];

        // gpt-image-1 uses quality: low/medium/high
        // dall-e-3 uses quality: standard/hd
        if (str_contains($model, 'dall-e-3')) {
            $body['quality'] = 'standard';
        } else {
            $body['quality'] = $cfg['dalle_quality'] ?: 'medium';
        }

        $r = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return 0;

        $data = json_decode(wp_remote_retrieve_body($r), true);
        $b64  = $data['data'][0]['b64_json'] ?? '';
        $url  = $data['data'][0]['url']      ?? '';

        if ($b64) return self::save_b64($b64, $alt, 'png');
        if ($url)  return self::save_url($url, $alt, 'png');
        return 0;
    }

    // ── Unsplash ──────────────────────────────────────────────────

    private static function unsplash(string $keyword, array $cfg): int {
        if (empty($cfg['unsplash_key'])) return 0;

        $query = urlencode(mb_substr($keyword, 0, 80));
        $r     = wp_remote_get(
            "https://api.unsplash.com/photos/random?query={$query}&orientation=landscape",
            [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Client-ID ' . $cfg['unsplash_key']],
            ]
        );

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return 0;

        $data = json_decode(wp_remote_retrieve_body($r), true);
        $url  = $data['urls']['regular'] ?? '';
        $alt  = $data['alt_description'] ?? $keyword;

        if (!$url) return 0;

        // Unsplash requires attribution
        $credit = ($data['user']['name'] ?? '') . ' — Unsplash';
        update_option('soser_last_unsplash_credit', sanitize_text_field($credit));

        return self::save_url($url, $alt . ' (Photo: ' . $credit . ')', 'jpg');
    }

    // ── Pexels ────────────────────────────────────────────────────

    private static function pexels(string $keyword, array $cfg): int {
        if (empty($cfg['pexels_key'])) return 0;

        $query = urlencode(mb_substr($keyword, 0, 80));
        $r     = wp_remote_get(
            "https://api.pexels.com/v1/search?query={$query}&per_page=1&orientation=landscape",
            [
                'timeout' => 20,
                'headers' => ['Authorization' => $cfg['pexels_key']],
            ]
        );

        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 300) return 0;

        $data   = json_decode(wp_remote_retrieve_body($r), true);
        $photo  = $data['photos'][0] ?? null;
        if (!$photo) return 0;

        $url    = $photo['src']['large2x'] ?? $photo['src']['large'] ?? '';
        $alt    = $photo['alt'] ?? $keyword;
        $credit = ($photo['photographer'] ?? '') . ' — Pexels';

        return self::save_url($url, $alt . ' (Photo: ' . $credit . ')', 'jpg');
    }

    // ── Save helpers ──────────────────────────────────────────────

    private static function save_b64(string $b64, string $alt, string $ext): int {
        $filename = sanitize_title(mb_substr($alt, 0, 40)) . '-' . time() . '.' . $ext;
        $upload   = wp_upload_bits($filename, null, base64_decode($b64));
        if (!empty($upload['error'])) return 0;
        return self::create_attachment($upload['file'], $alt);
    }

    private static function save_url(string $url, string $alt, string $ext): int {
        $response = wp_remote_get($url, ['timeout' => 30]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) return 0;

        $body     = wp_remote_retrieve_body($response);
        $filename = sanitize_title(mb_substr($alt, 0, 40)) . '-' . time() . '.' . $ext;
        $upload   = wp_upload_bits($filename, null, $body);
        if (!empty($upload['error'])) return 0;
        return self::create_attachment($upload['file'], $alt);
    }

    private static function create_attachment(string $file, string $alt): int {
        $type   = wp_check_filetype($file);
        $att_id = wp_insert_attachment([
            'post_mime_type' => $type['type'],
            'post_title'     => sanitize_text_field($alt),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $file);

        if (is_wp_error($att_id)) return 0;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($att_id, $file);
        wp_update_attachment_metadata($att_id, $metadata);
        update_post_meta($att_id, '_wp_attachment_image_alt', sanitize_text_field($alt));

        return (int) $att_id;
    }

    // ── Test connection ───────────────────────────────────────────

    public static function test_unsplash(string $key): bool {
        $r = wp_remote_get('https://api.unsplash.com/photos/random?query=test', [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Client-ID ' . $key],
        ]);
        return !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
    }

    public static function test_pexels(string $key): bool {
        $r = wp_remote_get('https://api.pexels.com/v1/search?query=test&per_page=1', [
            'timeout' => 10,
            'headers' => ['Authorization' => $key],
        ]);
        return !is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200;
    }
}
