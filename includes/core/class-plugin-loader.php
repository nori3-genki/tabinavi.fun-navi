<?php
/**
 * HRS Plugin Loader
 * クラス初期化・plugins_loaded処理（確定版）
 *
 * - Ajax Registry 設計に完全準拠
 * - plugins_loaded 起点
 * - Bootstrap 不要
 *
 * @package Hotel_Review_System
 * @version 7.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Plugin_Loader {

    /**
     * 初期化済みフラグ（多重 init 防止）
     */
    private static $initialized = false;

    /**
     * 初期化
     */
    public static function init() {

        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // メインクラス
        self::init_main_class();

        // 設定ページ
        self::init_settings_page();

        // APIテスター
        self::init_api_tester();

        // 管理メニュー
        self::init_admin_menu();

        // AJAX（Registry 一元管理）
        self::init_ajax_system();

        // HQCジェネレーター
        self::init_hqc_generator();

        // OTAマッピング
        self::init_mapping_system();

        // LinkSwitch
        self::init_linkswitch();

        // パフォーマンスダッシュボード
        self::init_performance_dashboard();

        // APIスケジューラー
        self::init_api_scheduler();

        hrs_log('Plugin Loader completed', 'info');
    }

    /**
     * メインクラス起動
     */
    private static function init_main_class() {
        if (class_exists('HRS_Hotel_Review_System')) {
            try {
                HRS_Hotel_Review_System::get_instance();
            } catch (Throwable $t) {
                hrs_log('Main class init error: ' . $t->getMessage(), 'error');
            }
        } else {
            hrs_log('HRS_Hotel_Review_System not found', 'warning');
        }
    }

    /**
     * 設定ページ
     */
    private static function init_settings_page() {
        if (class_exists('HRS_Settings_Page')) {
            try {
                new HRS_Settings_Page();
            } catch (Throwable $t) {
                hrs_log('Settings page init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * APIテスター
     */
    private static function init_api_tester() {
        if (class_exists('HRS_API_Tester')) {
            try {
                HRS_API_Tester::init();
            } catch (Throwable $t) {
                hrs_log('API Tester init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * 管理メニュー
     */
    private static function init_admin_menu() {
        if (class_exists('HRS_Admin_Menu')) {
            try {
                new HRS_Admin_Menu();
            } catch (Throwable $t) {
                hrs_log('Admin menu init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * AJAX システム初期化（★最重要）
     *
     * - Registry に一本化
     * - 個別 Ajax クラスの init / new は行わない
     */
    private static function init_ajax_system() {

        // Ajax Registry
        if (class_exists('HRS_Ajax_Registry')) {
            try {
                HRS_Ajax_Registry::init();
                hrs_log('Ajax Registry initialized', 'info');
            } catch (Throwable $t) {
                hrs_log('Ajax Registry init error: ' . $t->getMessage(), 'error');
            }
        } else {
            hrs_log('HRS_Ajax_Registry not found', 'warning');
        }

        // Generator Scripts（JS + nonce）
        if (class_exists('HRS_Generator_Scripts')) {
            try {
                HRS_Generator_Scripts::init();
                hrs_log('Generator Scripts initialized', 'info');
            } catch (Throwable $t) {
                hrs_log('Generator Scripts init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * HQCジェネレーター
     */
    private static function init_hqc_generator() {
        if (class_exists('HRS_HQC_Generator')) {
            try {
                HRS_HQC_Generator::init();
            } catch (Throwable $t) {
                hrs_log('HQC Generator init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * OTAマッピング
     */
    private static function init_mapping_system() {
        if (class_exists('HRS_Mapping_Manager')) {

            global $hrs_mapping_manager;
            $hrs_mapping_manager = new HRS_Mapping_Manager();

            if (is_admin() && class_exists('HRS_Mapping_UI')) {
                new HRS_Mapping_UI($hrs_mapping_manager);
            }

            hrs_log('OTA Mapping System initialized', 'info');
        }
    }

    /**
     * LinkSwitch
     */
    private static function init_linkswitch() {
        if (class_exists('HRS_LinkSwitch_Integration')) {
            try {
                new HRS_LinkSwitch_Integration();
            } catch (Throwable $t) {
                hrs_log('LinkSwitch init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * パフォーマンスダッシュボード
     */
    private static function init_performance_dashboard() {

        if (!is_admin()) {
            return;
        }

        if (class_exists('HRS_Performance_Admin_Page')) {
            try {
                new HRS_Performance_Admin_Page();
                hrs_log('Performance Dashboard initialized', 'info');
            } catch (Throwable $t) {
                hrs_log('Performance Dashboard init error: ' . $t->getMessage(), 'error');
            }
        }

        if (class_exists('HRS_API_Settings_Extension')) {
            try {
                new HRS_API_Settings_Extension();
            } catch (Throwable $t) {
                hrs_log('API Settings Extension init error: ' . $t->getMessage(), 'error');
            }
        }
    }

    /**
     * APIスケジューラー
     */
    private static function init_api_scheduler() {
        if (class_exists('HRS_API_Scheduler')) {
            try {
                new HRS_API_Scheduler();
            } catch (Throwable $t) {
                hrs_log('API Scheduler init error: ' . $t->getMessage(), 'error');
            }
        }
    }
}