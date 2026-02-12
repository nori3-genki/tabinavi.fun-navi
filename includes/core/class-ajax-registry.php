<?php
/**
 * AJAX Registry - 全AJAXハンドラー統合管理
 *
 * @package Hotel_Review_System
 * @subpackage Core
 * @version 2.4.0
 * 
 * 変更履歴:
 * - 2.2.0: HRS_Hqc_Ajax_Admin → HRS_Hqc_Ajax に修正
 * - 2.3.0: hrs_trash_article 追加、nonce名統一
 * - 2.4.0: hrs_hqc_auto_optimize 追加、class_file_map に HRS_HQC_Auto_Optimizer 追加
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Ajax_Registry {

    private static $handlers = [];

    public static function init() {

        if (!empty($GLOBALS['hrs_ajax_registry_done'])) {
            return;
        }
        $GLOBALS['hrs_ajax_registry_done'] = true;

        self::$handlers = [

            // ----------------------------------------
            // 【Article Generator】記事生成
            // ----------------------------------------
            'hrs_generate_article' => [
                'class'  => 'HRS_Article_Generator',
                'method' => 'ajax_generate_article',
                'nonce'  => 'hrs_generator_nonce',
                'cap'    => 'edit_posts',
                'static' => false,
            ],
            'hrs_save_as_post' => [
                'class'  => 'HRS_Article_Generator',
                'method' => 'ajax_save_as_post',
                'nonce'  => 'hrs_generator_nonce',
                'cap'    => 'publish_posts',
                'static' => false,
            ],

            // ----------------------------------------
            // 【Nurture Page】記事育成
            // ★ hrs_trash_article 追加（JSが使用）
            // ----------------------------------------
            'hrs_analyze_article' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_analyze_article',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'edit_posts',
            ],
            'hrs_delete_article' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_delete_article',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'delete_posts',
            ],
            'hrs_trash_article' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_delete_article',
                'nonce'  => 'hrs_trash_nonce',
                'cap'    => 'delete_posts',
            ],
            'hrs_bulk_analyze' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_bulk_analyze',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'edit_posts',
            ],
            'hrs_bulk_delete' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_bulk_delete',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'delete_posts',
            ],
            'hrs_bulk_trash' => [
                'class'  => 'HRS_Nurture_Ajax_Handlers',
                'method' => 'ajax_bulk_delete',
                'nonce'  => 'hrs_trash_nonce',
                'cap'    => 'delete_posts',
            ],

            // ----------------------------------------
            // 【Article Nurturing Page】詳細分析
            // ----------------------------------------
            'hrs_nurture_analyze_article' => [
                'class'  => 'HRS_Article_Nurturing_Page',
                'method' => 'ajax_analyze_article',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'edit_posts',
            ],
            'hrs_nurture_apply_suggestion' => [
                'class'  => 'HRS_Article_Nurturing_Page',
                'method' => 'ajax_optimize_article',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'edit_posts',
            ],
            'hrs_nurture_bulk_analyze' => [
                'class'  => 'HRS_Article_Nurturing_Page',
                'method' => 'ajax_bulk_analyze',
                'nonce'  => 'hrs_analyze_nonce',
                'cap'    => 'edit_posts',
            ],

            // ----------------------------------------
            // 【HQC Generator】設定・プリセット
            // ★ HRS_Hqc_Ajax（メソッド名は ajax_ なし）
            // ----------------------------------------
            'hrs_hqc_save_settings' => [
                'class'  => 'HRS_Hqc_Ajax',
                'method' => 'save_settings',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'manage_options',
            ],
            'hrs_hqc_save_persona' => [
                'class'  => 'HRS_Hqc_Ajax',
                'method' => 'save_persona',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'edit_posts',
            ],
            'hrs_hqc_apply_preset' => [
                'class'  => 'HRS_Hqc_Ajax',
                'method' => 'apply_preset',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'manage_options',
            ],
            'hrs_hqc_preview' => [
                'class'  => 'HRS_Hqc_Ajax',
                'method' => 'preview',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'manage_options',
            ],
            // ★ v2.4.0 追加: 自動最適化
            'hrs_hqc_auto_optimize' => [
                'class'  => 'HRS_Hqc_Ajax',
                'method' => 'ajax_auto_optimize',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'edit_posts',
            ],

            // ----------------------------------------
            // 【HQC Analyzer】分析
            // ----------------------------------------
            'hrs_hqc_analyze' => [
                'class'  => 'HRS_HQC_Analyzer',
                'method' => 'ajax_analyze',
                'nonce'  => 'hrs_hqc_nonce',
                'cap'    => 'edit_posts',
                'static' => false,
            ],

            // ----------------------------------------
            // 【Manual Generator】手動生成
            // ----------------------------------------
            'hrs_generate_manual_prompt' => [
                'class'  => 'HRS_Generator_Scripts',
                'method' => 'ajax_generate_prompt',
                'nonce'  => 'hrs_manual_generator_nonce',
                'cap'    => 'edit_posts',
            ],

            // ----------------------------------------
            // 【OTA / API】
            // ----------------------------------------
            'hrs_ota_get_ranking' => [
                'class'  => 'HRS_Rakuten_API_Test',
                'method' => 'get_ranking',
                'nonce'  => 'hrs_api_nonce',
                'cap'    => 'manage_options',
            ],
            'hrs_ota_search_hotel' => [
                'class'  => 'HRS_OTA_Selector',
                'method' => 'ajax_search_hotel',
                'nonce'  => 'hrs_api_nonce',
                'cap'    => 'manage_options',
            ],
            'hrs_test_rakuten_api' => [
                'class'  => 'HRS_Rakuten_API_Test',
                'method' => 'ajax_test',
                'nonce'  => 'hrs_api_test_nonce',
                'cap'    => 'manage_options',
            ],

            // ----------------------------------------
            // 【Performance】
            // ----------------------------------------
            'hrs_performance_fetch_ga4' => [
                'class'  => 'HRS_GA4_API_Client',
                'method' => 'ajax_fetch_ga4_data',
                'nonce'  => 'hrs_performance_nonce',
                'cap'    => 'manage_options',
            ],
            'hrs_performance_fetch_gsc' => [
                'class'  => 'HRS_GSC_API_Client',
                'method' => 'ajax_fetch_gsc_data',
                'nonce'  => 'hrs_performance_nonce',
                'cap'    => 'manage_options',
            ],
        ];

        foreach (self::$handlers as $action => $config) {
            $callback = self::build_callback($config);
            add_action('wp_ajax_' . $action, $callback);
        }

        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('hrs_log')) {
            hrs_log('[HRS] AJAX Registry initialized: ' . count(self::$handlers) . ' handlers', 'info');
        }
    }

    private static function build_callback(array $config) {

        return function () use ($config) {

            if (!defined('DOING_AJAX') || !DOING_AJAX) {
                wp_send_json_error(['message' => 'Invalid AJAX context'], 400);
            }

            $class  = $config['class']  ?? '';
            $method = $config['method'] ?? '';
            $cap    = $config['cap']    ?? '';
            $is_static = $config['static'] ?? true;

            if (!$class || !$method) {
                wp_send_json_error(['message' => 'Invalid handler configuration'], 500);
            }

            if ($cap && !current_user_can($cap)) {
                wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            }

            // クラス自動読み込み
            if (!class_exists($class)) {
                $class_file_map = [
                    'HRS_Article_Generator' => 'includes/admin/generator/class-article-generator.php',
                    'HRS_Nurture_Ajax_Handlers' => 'includes/admin/nurture/class-nurture-ajax-handlers.php',
                    'HRS_Article_Nurturing_Page' => 'includes/admin/class-article-nurturing-page.php',
                    'HRS_Hqc_Ajax' => 'includes/admin/hqc/class-hqc-ajax.php',
                    'HRS_HQC_Analyzer' => 'includes/learning/class-hqc-analyzer.php',
                    'HRS_HQC_Auto_Optimizer' => 'includes/learning/class-hqc-auto-optimizer.php',  // ★ v2.4.0 追加
                    'HRS_Generator_Scripts' => 'includes/admin/generator/class-generator-scripts.php',
                    'HRS_Rakuten_API_Test' => 'includes/ota/class-rakuten-api-test-endpoint.php',
                    'HRS_OTA_Selector' => 'includes/ota/class-ota-selector.php',
                    'HRS_GA4_API_Client' => 'includes/performance/class-ga4-api-client.php',
                    'HRS_GSC_API_Client' => 'includes/performance/class-gsc-api-client.php',
                ];

                if (isset($class_file_map[$class])) {
                    $file_path = HRS_PLUGIN_DIR . $class_file_map[$class];
                    if (file_exists($file_path)) {
                        require_once $file_path;
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[HRS_Ajax_Registry] Loaded: ' . $class_file_map[$class]);
                        }
                    }
                }
            }

            if (!class_exists($class)) {
                wp_send_json_error(['message' => 'Handler class not found: ' . $class], 500);
            }

            if (!method_exists($class, $method)) {
                wp_send_json_error(['message' => 'Handler method not found: ' . $class . '::' . $method], 500);
            }

            if ($is_static) {
                call_user_func([$class, $method]);
            } else {
                $instance = new $class();
                call_user_func([$instance, $method]);
            }
        };
    }

    public static function get_handlers() {
        return self::$handlers;
    }

    public static function get_handler($action) {
        return self::$handlers[$action] ?? null;
    }

    public static function is_handler_registered($action) {
        return isset(self::$handlers[$action]);
    }

    public static function get_registered_actions() {
        return array_keys(self::$handlers);
    }
}

add_action('plugins_loaded', ['HRS_Ajax_Registry', 'init'], 15);