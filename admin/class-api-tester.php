<?php
/**
 * Hotel Review System - API Tester
 * 
 * APIキー検証とテスト機能を提供（REST API対応版）
 * 
 * @package HotelReviewSystem
 * @version 3.1.1-DEBUG-ENHANCED
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_API_Tester {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * エラーコード定数
     */
    const ERROR_NO_KEY = 'NO_KEY';
    const ERROR_INVALID_FORMAT = 'INVALID_FORMAT';
    const ERROR_CONNECTION = 'CONNECTION_ERROR';
    const ERROR_API_ERROR = 'API_ERROR';
    const ERROR_INVALID_RESPONSE = 'INVALID_RESPONSE';
    const ERROR_NO_PERMISSION = 'NO_PERMISSION';
    const ERROR_UNKNOWN_TYPE = 'UNKNOWN_TYPE';
    
    /**
     * 静的初期化メソッド
     * 
     * @return HRS_API_Tester
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ（private）
     */
    private function __construct() {
        // AJAX エンドポイント（管理画面用・後方互換性維持）
        add_action('wp_ajax_hrs_test_api', array($this, 'ajax_test_api'));
        
        // REST API エンドポイント（将来の拡張用）
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * REST API ルート登録
     */
    public function register_rest_routes() {
        register_rest_route('hrs/v1', '/test-api', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_test_api'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => array(
                'api_type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('chatgpt', 'google_cse', 'rakuten')
                )
            )
        ));
    }
    
    /**
     * REST API エンドポイント
     * 
     * @param WP_REST_Request $request リクエストオブジェクト
     * @return WP_REST_Response レスポンス
     */
    public function rest_test_api($request) {
        $api_type = $request->get_param('api_type');
        
        $result = $this->test_api_by_type($api_type);
        
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }
    
    /**
     * AJAX エンドポイント（後方互換性維持）
     */
    public function ajax_test_api() {
        check_ajax_referer('hrs_test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'success' => false,
                'error_code' => self::ERROR_NO_PERMISSION,
                'message' => '権限がありません。'
            ));
            return;
        }
        
        $api_type = isset($_POST['api_type']) ? sanitize_text_field($_POST['api_type']) : '';
        
        $result = $this->test_api_by_type($api_type);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * APIタイプ別テスト実行
     * 
     * @param string $api_type APIタイプ
     * @return array 統一フォーマットの結果
     */
    private function test_api_by_type($api_type) {
        switch ($api_type) {
            case 'chatgpt':
                return $this->test_chatgpt_api();
            case 'google_cse':
                return $this->test_google_cse_api();
            case 'rakuten':
                return $this->test_rakuten_api();
            default:
                return array(
                    'success' => false,
                    'error_code' => self::ERROR_UNKNOWN_TYPE,
                    'message' => '不明なAPIタイプです。'
                );
        }
    }
    
    /**
     * ChatGPT API接続テスト
     * 
     * @return array 統一フォーマットの結果
     */
    private function test_chatgpt_api() {
        $api_key = get_option('hrs_chatgpt_api_key', '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_NO_KEY,
                'message' => 'ChatGPT APIキーが設定されていません。'
            );
        }
        
        // APIキー形式チェック
        if (!preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $api_key)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_INVALID_FORMAT,
                'message' => 'ChatGPT APIキーの形式が正しくありません。'
            );
        }
        
        // 最新推奨モデルを使用
        $model = get_option('hrs_chatgpt_model', 'gpt-4o-mini');
        
        // APIリクエスト送信（タイムアウト10秒）
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'テスト接続です。「OK」とだけ返してください。'
                    )
                ),
                'max_tokens' => 10,
                'temperature' => 0.1
            ))
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_CONNECTION,
                'message' => 'API接続エラー: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_API_ERROR,
                'message' => 'APIエラー: ' . $data['error']['message'],
                'details' => array(
                    'type' => isset($data['error']['type']) ? $data['error']['type'] : 'unknown',
                    'status_code' => $status_code
                )
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'error_code' => null,
                'message' => 'ChatGPT APIへの接続に成功しました。',
                'details' => array(
                    'model' => isset($data['model']) ? $data['model'] : 'N/A',
                    'response' => $data['choices'][0]['message']['content'],
                    'usage' => isset($data['usage']) ? $data['usage'] : null
                )
            );
        }
        
        return array(
            'success' => false,
            'error_code' => self::ERROR_INVALID_RESPONSE,
            'message' => '予期しないレスポンス形式です。',
            'details' => array(
                'status_code' => $status_code,
                'raw_response' => substr($body, 0, 200) // デバッグ用に先頭200文字
            )
        );
    }
    
    /**
     * Google CSE API接続テスト
     * 
     * @return array 統一フォーマットの結果
     */
    private function test_google_cse_api() {
        $api_key = get_option('hrs_google_cse_api_key', '');
        $search_engine_id = get_option('hrs_google_cse_id', '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_NO_KEY,
                'message' => 'Google CSE APIキーが設定されていません。'
            );
        }
        
        if (empty($search_engine_id)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_NO_KEY,
                'message' => '検索エンジンIDが設定されていません。'
            );
        }
        
        // APIキー形式チェック
        if (!preg_match('/^[A-Za-z0-9_-]{30,50}$/', $api_key)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_INVALID_FORMAT,
                'message' => 'Google CSE APIキーの形式が正しくありません。'
            );
        }
        
        // テスト検索実行（safe=offで結果を安定化）
        $url = add_query_arg(array(
            'key' => $api_key,
            'cx' => $search_engine_id,
            'q' => 'test hotel',
            'num' => 1,
            'safe' => 'off' // セーフサーチ無効化で検索結果を安定化
        ), 'https://www.googleapis.com/customsearch/v1');
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_CONNECTION,
                'message' => 'API接続エラー: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_API_ERROR,
                'message' => 'APIエラー: ' . $data['error']['message'],
                'details' => array(
                    'code' => isset($data['error']['code']) ? $data['error']['code'] : 'unknown',
                    'status_code' => $status_code
                )
            );
        }
        
        if (isset($data['searchInformation'])) {
            return array(
                'success' => true,
                'error_code' => null,
                'message' => 'Google CSE APIへの接続に成功しました。',
                'details' => array(
                    'total_results' => isset($data['searchInformation']['totalResults']) 
                        ? number_format((int)$data['searchInformation']['totalResults']) 
                        : 'N/A',
                    'search_time' => isset($data['searchInformation']['searchTime']) 
                        ? number_format($data['searchInformation']['searchTime'], 3) . '秒' 
                        : 'N/A',
                    'items_found' => isset($data['items']) ? count($data['items']) : 0
                )
            );
        }
        
        return array(
            'success' => false,
            'error_code' => self::ERROR_INVALID_RESPONSE,
            'message' => '予期しないレスポンス形式です。',
            'details' => array(
                'status_code' => $status_code,
                'raw_response' => substr($body, 0, 200)
            )
        );
    }
    
    /**
     * 楽天トラベルAPI接続テスト
     * 
     * @return array 統一フォーマットの結果
     */
    private function test_rakuten_api() {
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        if (empty($app_id)) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_NO_KEY,
                'message' => '楽天アプリケーションIDが設定されていません。'
            );
        }
        
        // テスト検索（東京駅周辺のホテル）
        $url = add_query_arg(array(
            'applicationId' => $app_id,
            'format' => 'json',
            'datumType' => 1,
            'latitude' => 35.681236,
            'longitude' => 139.767125,
            'searchRadius' => 3,
            'hits' => 1
        ), 'https://app.rakuten.co.jp/services/api/Travel/SimpleHotelSearch/20170426');
        
        error_log('Rakuten API Test - Request URL: ' . $url);
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            error_log('Rakuten API Test - WP Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'error_code' => self::ERROR_CONNECTION,
                'message' => 'API接続エラー: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log('Rakuten API Test - Status Code: ' . $status_code);
        error_log('Rakuten API Test - Response Body: ' . $body);
        
        if ($status_code !== 200) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_API_ERROR,
                'message' => 'HTTPエラー: ' . $status_code,
                'details' => array(
                    'status_code' => $status_code,
                    'raw_response' => $body
                )
            );
        }
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'error_code' => self::ERROR_API_ERROR,
                'message' => 'APIエラー: ' . ($data['error_description'] ?? $data['error']),
                'details' => array(
                    'error' => $data['error'],
                    'raw_response' => $body
                )
            );
        }
        
        if (isset($data['hotels'])) {
            return array(
                'success' => true,
                'error_code' => null,
                'message' => '楽天トラベルAPIへの接続に成功しました。',
                'details' => array(
                    'hotels_found' => count($data['hotels']),
                    'page_count' => isset($data['pageCount']) ? $data['pageCount'] : 'N/A'
                )
            );
        }
        
        return array(
            'success' => false,
            'error_code' => self::ERROR_INVALID_RESPONSE,
            'message' => '予期しないレスポンス形式です。',
            'details' => array(
                'status_code' => $status_code,
                'raw_response' => $body
            )
        );
    }
}

// 静的初期化
HRS_API_Tester::init();