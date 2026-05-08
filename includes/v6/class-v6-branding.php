<?php
defined('ABSPATH') || exit;

/**
 * V6_Branding — Dynamic CSS variables from Business Profile.
 * Replaces all hardcoded #e87c2a and #1a1a1a with CSS vars.
 */
class V6_Branding {

    /** Inject CSS variables in <head> of frontend */
    public static function inject_frontend_vars(): void {
        $p = V6_Profile::get();
        $color      = esc_attr($p['color']      ?: '#e87c2a');
        $color_dark = esc_attr($p['color_dark'] ?: '#1a1a1a');

        // Derive lighter shade automatically
        $color_light = self::lighten_hex($color, 0.92);

        echo "<style id='soser-brand-vars'>
:root {
  --soser-color:       {$color};
  --soser-dark:        {$color_dark};
  --soser-light:       {$color_light};
  --soser-text:        #2a2a2a;
  --soser-muted:       #666666;
  --soser-border:      #e8e5e0;
  --soser-bg:          #fafaf8;
}
/* Override article.css orange references */
.v59-badge-bar { border-color: var(--soser-color) !important; }
.v59-answer, .v5-answer-box { border-color: var(--soser-color) !important; }
.v510-toc details { border-color: var(--soser-color) !important; }
.v510-toc summary { color: var(--soser-color) !important; }
.v59-inline-cta { background: var(--soser-dark) !important; }
.v510-garanzie   { background: var(--soser-dark) !important; }
.v59-price-cta   { border-color: var(--soser-color) !important; background: var(--soser-light) !important; }
.v59-wa-sticky a { background: #25d366 !important; }
#v59-reading-bar { background: var(--soser-color) !important; }
</style>\n";
    }

    /** Inject CSS vars in wp-admin */
    public static function inject_admin_vars(): void {
        $p     = V6_Profile::get();
        $color = esc_attr($p['color'] ?: '#e87c2a');
        echo "<style id='soser-admin-brand'>:root{--soser-color:{$color};--soser-dark:" . esc_attr($p['color_dark'] ?: '#1a1a1a') . ";}</style>\n";
    }

    /** Generate a lighter background version of a hex color */
    public static function lighten_hex(string $hex, float $factor = 0.9): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#fff9f0';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = (int) round($r + (255 - $r) * $factor);
        $g = (int) round($g + (255 - $g) * $factor);
        $b = (int) round($b + (255 - $b) * $factor);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Get inline style string for a colored button */
    public static function btn_style(string $type = 'primary'): string {
        $p     = V6_Profile::get();
        $color = $p['color'] ?: '#e87c2a';
        $dark  = $p['color_dark'] ?: '#1a1a1a';
        if ($type === 'dark') {
            return "background:{$dark};color:#fff;padding:10px 20px;border-radius:7px;text-decoration:none;font-family:system-ui,sans-serif;font-size:14px;font-weight:700;display:inline-block";
        }
        return "background:{$color};color:#fff;padding:10px 20px;border-radius:7px;text-decoration:none;font-family:system-ui,sans-serif;font-size:14px;font-weight:700;display:inline-block";
    }
}
