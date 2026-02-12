<?php
/**
 * カラーユーティリティ
 * サイトのテーマカラーを自動取得するヘルパー関数
 *
 * @package Hotel_Review_System
 * @since 8.1.0
 * 
 * 配置先: includes/utils/color-utils.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// 二重読み込み防止
if (function_exists('hrs_get_site_colors')) {
    return;
}

/**
 * サイトのプライマリ/セカンダリカラーを取得
 *
 * Cocoonテーマのカスタマイザー設定 → WordPress標準 → デフォルト値の順で取得
 *
 * @return array {
 *     @type string $primary   プライマリカラー（HEX）
 *     @type string $secondary セカンダリカラー（HEX）
 *     @type string $accent    アクセントカラー（HEX）
 *     @type string $text      テキストカラー（HEX）
 *     @type string $bg        背景カラー（HEX）
 * }
 */
function hrs_get_site_colors() {
    // デフォルト値
    $defaults = array(
        'primary'   => '#2563eb',
        'secondary' => '#7c3aed',
        'accent'    => '#f59e0b',
        'text'      => '#1f2937',
        'bg'        => '#ffffff',
    );

    $colors = $defaults;

    // Cocoonテーマの場合
    if (function_exists('get_theme_mod')) {
        // Cocoon独自のキーカラー
        $cocoon_key_color = get_theme_mod('site_key_color', '');
        if (!empty($cocoon_key_color)) {
            $colors['primary'] = sanitize_hex_color($cocoon_key_color) ?: $defaults['primary'];
        }

        // Cocoon独自のキーカラー（テキスト）
        $cocoon_key_text_color = get_theme_mod('site_key_text_color', '');
        if (!empty($cocoon_key_text_color)) {
            $colors['text'] = sanitize_hex_color($cocoon_key_text_color) ?: $defaults['text'];
        }

        // WordPress標準カスタマイザー
        $custom_primary = get_theme_mod('hrs_primary_color', '');
        if (!empty($custom_primary)) {
            $colors['primary'] = sanitize_hex_color($custom_primary) ?: $defaults['primary'];
        }

        $custom_secondary = get_theme_mod('hrs_secondary_color', '');
        if (!empty($custom_secondary)) {
            $colors['secondary'] = sanitize_hex_color($custom_secondary) ?: $defaults['secondary'];
        }
    }

    // プラグイン設定からの上書き
    $plugin_primary = get_option('hrs_color_primary', '');
    if (!empty($plugin_primary)) {
        $colors['primary'] = sanitize_hex_color($plugin_primary) ?: $defaults['primary'];
    }

    $plugin_secondary = get_option('hrs_color_secondary', '');
    if (!empty($plugin_secondary)) {
        $colors['secondary'] = sanitize_hex_color($plugin_secondary) ?: $defaults['secondary'];
    }

    return $colors;
}

/**
 * HEXカラーを明るくする
 *
 * @param string $hex HEXカラー
 * @param int    $percent 明るさ（0-100）
 * @return string
 */
function hrs_lighten_color($hex, $percent = 20) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = min(255, $r + (255 - $r) * ($percent / 100));
    $g = min(255, $g + (255 - $g) * ($percent / 100));
    $b = min(255, $b + (255 - $b) * ($percent / 100));

    return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
}

/**
 * HEXカラーを暗くする
 *
 * @param string $hex HEXカラー
 * @param int    $percent 暗さ（0-100）
 * @return string
 */
function hrs_darken_color($hex, $percent = 20) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, $r - $r * ($percent / 100));
    $g = max(0, $g - $g * ($percent / 100));
    $b = max(0, $b - $b * ($percent / 100));

    return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
}

/**
 * 背景色に対する適切なテキストカラーを返す
 *
 * @param string $bg_hex 背景HEXカラー
 * @return string '#ffffff' または '#1f2937'
 */
function hrs_contrast_text_color($bg_hex) {
    $hex = ltrim($bg_hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // 相対輝度計算（WCAG 2.0）
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    return $luminance > 0.5 ? '#1f2937' : '#ffffff';
}