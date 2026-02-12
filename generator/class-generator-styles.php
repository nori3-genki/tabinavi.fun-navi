<?php
/**
 * Generator Styles - CSSスタイル管理クラス
 *
 * 管理画面用CSS enqueue専用
 *
 * @package Hotel_Review_System
 * @subpackage Generator
 * @version 6.7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Generator_Styles {
    /**
     * 初期化
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    /**
     * CSS読み込み（管理画面）
     */
    public static function enqueue_styles($hook) {
        // デバッグ：hook名を確認
        error_log('HRS Generator Hook: ' . $hook);
        
        // Generator 画面以外では読み込まない
       if (strpos($hook, '5d-review-builder') === false) {
            return;
        }
        
        wp_enqueue_style(
            'hrs-generator-manual',
            plugins_url('assets/css/hrs-generator-manual.css', dirname(__FILE__, 3)),
            [],
            '6.7.2'
        );
    }
}

HRS_Generator_Styles::init();