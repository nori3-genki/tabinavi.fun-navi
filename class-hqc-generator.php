<?php

/**
 * HQC Generator - メインクラス
 *
 * Human/Quality/Content Framework 統合
 * 分割ファイルを読み込み、統合管理
 *
 * @package Hotel_Review_System
 * @version 7.0.5
 *
 * 変更履歴:
 * - 7.0.5: 自己読み込みループ削除（Phase 9 一元管理に統一、循環依存解消）
 * - 7.0.4: HRS_HQC出力をrender_page()（class-hqc-ui.php）に統一、output_inline_script()から削除
 * - 7.0.3: HRS_HQCグローバル変数の出力を追加（undefinedエラー修正）
 * - 7.0.2: HRS_Hqc_Ajax_Admin → HRS_Hqc_Ajax に修正（AJAX Registry対応）
 * - 7.0.2-hybrid: require_once を削除（Phase 9 で一元管理）
 */
if (!defined('ABSPATH')) {
    exit;
}

// ★ v7.0.5: 自己読み込みループを完全削除
// 全HQCファイルは 5d-review-builder.php Phase 9 で正しい順序で読み込まれる。
// ここで require_once すると、HRS_HQC_Generator 定義前に class-hqc-ui.php が
// 読み込まれ、循環依存エラーが発生する。

if (!class_exists('HRS_HQC_Generator', false)) :

    class HRS_HQC_Generator
    {

        /** Option name */
        private static $option_name = 'hrs_hqc_settings';

        /** Version */
        const VERSION = '7.0.5';

        /** 現在のページがHQCページかどうか */
        private static $is_hqc_page = false;

        /**
         * 初期化
         */
        public static function init()
        {
            if (!is_admin()) {
                return;
            }

            // ページ判定（admin_init で早期に判定）
            add_action('admin_init', [__CLASS__, 'check_page']);

            // CSSをheadで出力
            add_action('admin_head', [__CLASS__, 'output_inline_styles']);

            // JSをfooterで出力
            add_action('admin_footer', [__CLASS__, 'output_inline_script']);

            // AJAX登録はAJAX Registryが行うため、ここでは呼ばない
        }

        /**
         * ページ判定（$_GET['page'] ベース）
         */
        public static function check_page()
        {
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            
            // HQC Generator ページの判定
            if ($page === '5d-review-builder-generator') {
                self::$is_hqc_page = true;
            }
        }

        /**
         * headでインラインCSS出力
         */
        public static function output_inline_styles()
        {
            if (!self::$is_hqc_page) {
                return;
            }

            if (class_exists('HRS_Hqc_Styles')) {
                echo '<style type="text/css" id="hrs-hqc-styles">' . "\n";
                echo HRS_Hqc_Styles::get_inline_styles();
                echo "\n" . '</style>' . "\n";
            }
        }

        /**
         * フッターでインラインJS出力
         * 
         * ※ HRS_HQCグローバル変数は HRS_Hqc_UI::render_page() で出力済み。
         *   ここではスクリプト本体のみ出力する。
         */
        public static function output_inline_script()
        {
            if (!self::$is_hqc_page) {
                return;
            }

            if (class_exists('HRS_Hqc_Scripts')) {
                $current = get_option(self::$option_name, HRS_Hqc_Data::get_default_settings());

                if (class_exists('HRS_Hqc_Presets')) {
                    $current = HRS_Hqc_Presets::sanitize_settings_for_output($current);
                }

                echo '<script type="text/javascript" id="hrs-hqc-scripts">' . "\n";
                echo HRS_Hqc_Scripts::get_inline_script($current);
                echo "\n" . '</script>' . "\n";
            }
        }

        /**
         * ページをレンダリング
         */
        public static function render_page()
        {
            if (class_exists('HRS_Hqc_UI')) {
                HRS_Hqc_UI::render_page();
            }
        }

        /**
         * 設定を取得
         */
        public static function get_settings()
        {
            return get_option(self::$option_name, HRS_Hqc_Data::get_default_settings());
        }

        /**
         * 設定を保存
         */
        public static function save_settings($settings)
        {
            if (class_exists('HRS_Hqc_Presets')) {
                $sanitized = HRS_Hqc_Presets::sanitize_and_validate_settings($settings);
                return update_option(self::$option_name, $sanitized);
            }
            return false;
        }
    }

endif;

// 初期化
if (class_exists('HRS_HQC_Generator')) {
    add_action('init', ['HRS_HQC_Generator', 'init'], 20);
}