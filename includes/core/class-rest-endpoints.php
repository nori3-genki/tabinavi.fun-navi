<?php
/**
 * HRS REST Endpoints
 * REST APIエンドポイント
 * 
 * @package Hotel_Review_System
 * @version 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_REST_Endpoints {
    
    /**
     * エンドポイント登録
     */
    public static function register() {
        // ステータス
        register_rest_route('hrs/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_status'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // 診断情報
        register_rest_route('hrs/v1', '/diagnostics', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_diagnostics'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // Google API テスト
        register_rest_route('hrs/v1', '/test/google', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'test_google_api'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // OpenAI API テスト
        register_rest_route('hrs/v1', '/test/openai', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'test_openai_api'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // Rakuten API テスト
        register_rest_route('hrs/v1', '/test/rakuten', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'test_rakuten_api'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // 学習統計
        register_rest_route('hrs/v1', '/learning/stats', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_learning_stats'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // ホテル別学習データ
        register_rest_route('hrs/v1', '/learning/hotel', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_hotel_learning'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // パフォーマンスサマリー
        register_rest_route('hrs/v1', '/performance/summary', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_performance_summary'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
    }
    
    /**
     * 管理者権限チェック
     * 
     * @return bool
     */
    public static function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * ステータス取得
     * 
     * @return WP_REST_Response
     */
    public static function get_status() {
        return new WP_REST_Response(array(
            'version' => HRS_VERSION,
            'status' => 'active',
            'plugin_name' => '5D Review Builder',
            'php_version' => PHP_VERSION,
            'wp_version' => $GLOBALS['wp_version'],
            'google_api_configured' => !empty(get_option('hrs_google_cse_api_key')),
            'chatgpt_api_configured' => !empty(get_option('hrs_chatgpt_api_key')),
            'rakuten_api_configured' => !empty(get_option('hrs_rakuten_app_id')),
            'hqc_generator' => class_exists('HRS_HQC_Generator'),
            'custom_post_type' => class_exists('HRS_Custom_Post_Type'),
            'learning_system' => class_exists('HRS_HQC_Learning_Module'),
            'learning_enabled' => (bool) get_option('hrs_hqc_learning_enabled', true),
            'performance_dashboard' => class_exists('HRS_Performance_Admin_Page'),
            'performance_tracker' => class_exists('HRS_Performance_Tracker'),
        ), 200);
    }
    
    /**
     * 診断情報取得
     * 
     * @return WP_REST_Response
     */
    public static function get_diagnostics() {
        $tables_exist = false;
        if (class_exists('HRS_HQC_DB_Installer') && method_exists('HRS_HQC_DB_Installer', 'tables_exist')) {
            try {
                $tables_exist = HRS_HQC_DB_Installer::tables_exist();
            } catch (Throwable $e) {
                $tables_exist = false;
            }
        }
        
        return new WP_REST_Response(array(
            'loaded_files' => isset($GLOBALS['hrs_loaded_files']) ? $GLOBALS['hrs_loaded_files'] : array(),
            'skipped_files' => isset($GLOBALS['hrs_skipped_files']) ? $GLOBALS['hrs_skipped_files'] : array(),
            'load_errors' => isset($GLOBALS['hrs_load_errors']) ? $GLOBALS['hrs_load_errors'] : array(),
            'plugin_version' => HRS_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => $GLOBALS['wp_version'],
            'hqc_generator_active' => class_exists('HRS_HQC_Generator'),
            'custom_post_type_active' => class_exists('HRS_Custom_Post_Type'),
            'learning_system_active' => class_exists('HRS_HQC_Learning_Module'),
            'learning_tables_exist' => $tables_exist,
            'performance_tracker_active' => class_exists('HRS_Performance_Tracker'),
            'performance_admin_active' => class_exists('HRS_Performance_Admin_Page'),
        ), 200);
    }
    
    /**
     * Google API テスト
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function test_google_api($request) {
        $cse_id = $request->get_param('cse_id');
        $api_key = $request->get_param('api_key');
        
        if (empty($cse_id) || empty($api_key)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'CSE ID と API キーが必要です',
            ), 400);
        }
        
        $test_url = add_query_arg(array(
            'key' => $api_key,
            'cx' => $cse_id,
            'q' => 'ホテル',
            'num' => 1,
        ), 'https://www.googleapis.com/customsearch/v1');
        
        $response = wp_remote_get($test_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => '接続エラー',
                'details' => $response->get_error_message(),
            ), 500);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            update_option('hrs_google_cse_api_key', $api_key, false);
            update_option('hrs_google_cse_id', $cse_id, false);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => '✅ Google CSE 接続成功',
                'auto_saved' => true,
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => '接続失敗',
            'details' => 'HTTPコード: ' . $code,
        ), $code);
    }
    
    /**
     * OpenAI API テスト
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function test_openai_api($request) {
        $api_key = $request->get_param('api_key');
        
        if (empty($api_key)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'API キーが必要です',
            ), 400);
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array('role' => 'user', 'content' => 'ping'),
                ),
                'max_tokens' => 5,
            )),
            'timeout' => 10,
        ));
        
        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => '接続エラー',
                'details' => $response->get_error_message(),
            ), 500);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && isset($json['choices'][0]['message']['content'])) {
            update_option('hrs_chatgpt_api_key', $api_key, false);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => '✅ OpenAI API 接続成功',
                'auto_saved' => true,
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => '接続失敗',
            'details' => isset($json['error']) ? $json['error'] : $json,
        ), $code);
    }
    
    /**
     * Rakuten API テスト
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function test_rakuten_api($request) {
        $app_id = $request->get_param('app_id');
        
        if (empty($app_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'アプリケーション ID が必要です',
            ), 400);
        }
        
        $test_url = add_query_arg(array(
            'applicationId' => $app_id,
            'keyword' => '東京',
            'hits' => 1,
        ), 'https://app.rakuten.co.jp/services/api/Travel/KeywordHotelSearch/20170426');
        
        $response = wp_remote_get($test_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => '接続エラー',
                'details' => $response->get_error_message(),
            ), 500);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            update_option('hrs_rakuten_app_id', $app_id, false);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => '✅ 楽天トラベル API 接続成功',
                'auto_saved' => true,
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => '接続失敗',
            'details' => 'HTTPコード: ' . $code,
        ), $code);
    }
    
    /**
     * 学習統計取得
     * 
     * @return WP_REST_Response
     */
    public static function get_learning_stats() {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Learning module not available',
            ), 404);
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        $stats = $learning->get_statistics();
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $stats,
        ), 200);
    }
    
    /**
     * ホテル別学習データ取得
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_hotel_learning($request) {
        $hotel_name = $request->get_param('hotel_name');
        
        if (empty($hotel_name)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Hotel name required',
            ), 400);
        }
        
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Learning module not available',
            ), 404);
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        $data = $learning->get_hotel_learning($hotel_name);
        
        if ($data) {
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $data,
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No learning data found',
        ), 404);
    }
    
    /**
     * パフォーマンスサマリー取得
     * 
     * @return WP_REST_Response
     */
    public static function get_performance_summary() {
        if (!class_exists('HRS_Performance_Tracker')) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Performance tracker not available',
            ), 404);
        }
        
        try {
            $tracker = new HRS_Performance_Tracker();
            $summary = $tracker->get_summary();
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $summary,
            ), 200);
        } catch (Throwable $t) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $t->getMessage(),
            ), 500);
        }
    }
}