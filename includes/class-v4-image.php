<?php
defined('ABSPATH') || exit;

class V4_Image {

    /**
     * Generate an image via OpenAI Images API and attach to WordPress Media Library.
     * Returns attachment ID or 0 on failure.
     */
    public static function generate(string $prompt, string $alt_text, array $opts): int {
        if (empty($opts['openai_key'])) return 0;

        // Sanitize prompt — max 4000 chars
        $prompt = mb_substr(sanitize_text_field($prompt), 0, 4000);

        $r = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $opts['openai_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'           => $opts['image_model'] ?: 'gpt-image-1',
                'prompt'          => $prompt,
                'size'            => '1024x1024',
                'quality'         => 'medium', // FIX #5: gpt-image-1 accepts low/medium/high not 'standard'
                'n'               => 1,
                // response_format omitted - b64_json is default for gpt-image-1
            ]),
        ]);

        if (is_wp_error($r)) return 0;
        $code = wp_remote_retrieve_response_code($r);
        if ($code >= 300) return 0;

        $body = json_decode(wp_remote_retrieve_body($r), true);
        $b64  = $body['data'][0]['b64_json'] ?? '';
        if (!$b64) return 0;

        return self::save_to_library($b64, $alt_text);
    }

    private static function save_to_library(string $b64, string $alt_text): int {
        $filename = sanitize_title(mb_substr($alt_text, 0, 40)) . '-' . time() . '.png';
        $upload   = wp_upload_bits($filename, null, base64_decode($b64));
        if (!empty($upload['error'])) return 0;

        $filetype = wp_check_filetype($upload['file']);
        $att_id   = wp_insert_attachment([
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field($alt_text),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file']);

        if (is_wp_error($att_id)) return 0;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata(
            $att_id,
            wp_generate_attachment_metadata($att_id, $upload['file'])
        );

        // Set alt text
        update_post_meta($att_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));

        return (int) $att_id;
    }
}
