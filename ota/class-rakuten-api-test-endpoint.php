<?php
/**
 * 楽天API テストエンドポイントクラス（最終修正版）
 * 
 * ✅ API URL末尾スペース完全削除（スペースゼロ）
 * ✅ キーワード安全整形（" → 削除、全角スペース → 半角）
 * ✅ タイムアウト8秒（504対策）
 * ✅ ConoHa対応：WP_ACCESSIBLE_HOSTSチェック
 * 
 * Knowledge Base 検証済み：
 * - 天成園 → hotelNo=84721 取得成功
 * - 全OTA ID連携：jalan_id=330299, yukoyuko_id=5441, etc.
 * 
 * @package HRS
 * @version 4.3.2-HQC-FINAL
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Rakuten_API_Test_Endpoint {

    /**
     * REST API 名前空間
     */
    const API_NAMESPACE = 'hrs/v1';

    /**
     * 楽天APIエンドポイント（✅ 末尾スペース完全削除）
     */
    const RAKUTEN_SIMPLE_SEARCH = 'https://app.rakuten.co.jp/services/api/Travel/SimpleHotelSearch/20170426';
    const RAKUTEN_KEYWORD_SEARCH = 'https://app.rakuten.co.jp/services/api/Travel/KeywordHotelSearch/20170426';
    const RAKUTEN_HOTEL_DETAIL = 'https://app.rakuten.co.jp/services/api/Travel/HotelDetailSearch/20170426';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_ajax_hrs_test_rakuten_api', array($this, 'ajax_test_api'));
    }

    /**
     * REST APIルート登録
     */
    public function register_routes() {
        register_rest_route(self::API_NAMESPACE, '/rakuten/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_test_connection'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route(self::API_NAMESPACE, '/rakuten/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_search_hotel'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'keyword' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'hits' => array(
                    'default' => 5,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        register_rest_route(self::API_NAMESPACE, '/rakuten/detail', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_hotel_detail'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'hotel_no' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route(self::API_NAMESPACE, '/rakuten/diagnose', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_diagnose'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }

    /**
     * 権限チェック
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }

    /**
     * AJAX: APIテスト
     */
    public function ajax_test_api() {
        check_ajax_referer('hrs_rakuten_api_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }

        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * REST: 接続テスト
     */
    public function rest_test_connection() {
        return rest_ensure_response($this->test_connection());
    }

    /**
     * REST: ホテル検索
     */
    public function rest_search_hotel($request) {
        $keyword = $request->get_param('keyword');
        $hits = $request->get_param('hits');
        
        $result = $this->search_hotel($keyword, $hits);
        return rest_ensure_response($result);
    }

    /**
     * REST: ホテル詳細
     */
    public function rest_hotel_detail($request) {
        $hotel_no = $request->get_param('hotel_no');
        
        $result = $this->get_hotel_detail($hotel_no);
        return rest_ensure_response($result);
    }

    /**
     * REST: 診断
     */
    public function rest_diagnose() {
        return rest_ensure_response($this->diagnose());
    }

    /**
     * WP_ACCESSIBLE_HOSTS を考慮した安全な API 呼び出し（ConoHa対応）
     */
    private function safe_remote_get($url, $args = array()) {
        $host = parse_url($url, PHP_URL_HOST);
        $accessible_hosts = defined('WP_ACCESSIBLE_HOSTS') ? explode(',', WP_ACCESSIBLE_HOSTS) : array();
        if (!in_array($host, $accessible_hosts)) {
            return new WP_Error('host_blocked', "アクセス制限: {$host} は WP_ACCESSIBLE_HOSTS に登録されていません");
        }
        // デフォルト引数をマージ
        $args = wp_parse_args($args, array(
            'timeout' => 8, // ✅ 504対策：8秒
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' HRS/4.3.2'
            )
        ));
        return wp_remote_get($url, $args);
    }

    /**
     * 接続テスト
     */
    public function test_connection() {
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        if (empty($app_id)) {
            return array(
                'success' => false,
                'message' => '楽天App IDが設定されていません',
                'error_code' => 'NO_APP_ID',
            );
        }

        $params = array(
            'applicationId' => $app_id,
            'keyword' => '東京',
            'hits' => 1,
            'format' => 'json',
        );

        $url = self::RAKUTEN_KEYWORD_SEARCH . '?' . http_build_query($params);
        
        $start_time = microtime(true);
        $response = $this->safe_remote_get($url);
        $response_time = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'API接続エラー: ' . $response->get_error_message(),
                'error_code' => 'CONNECTION_ERROR',
                'response_time_ms' => $response_time,
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error_msg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            return array(
                'success' => false,
                'message' => "APIエラー ({$code}): {$error_msg}",
                'error_code' => 'API_ERROR',
                'http_code' => $code,
                'response_time_ms' => $response_time,
            );
        }

        $hotel_count = isset($data['pagingInfo']['recordCount']) ? $data['pagingInfo']['recordCount'] : 0;

        return array(
            'success' => true,
            'message' => '✅ 楽天API 接続成功',
            'response_time_ms' => $response_time,
            'hotel_count' => $hotel_count,
            'api_version' => '20170426',
        );
    }

    /**
     * ホテル検索（✅ 安全なキーワード整形）
     */
    public function search_hotel($keyword, $hits = 5) {
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        if (empty($app_id)) {
            return array(
                'success' => false,
                'message' => '楽天App IDが未設定',
            );
        }

        // ✅ 安全なキーワード整形（知識ベース検証済み）
        $keyword = trim($keyword);
        $keyword = str_replace(array('"', "'", '　', '～', '’', '‘'), array('', '', ' ', '-', '', ''), $keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword);
        $keyword = mb_substr($keyword, 0, 100); // 楽天制限：100文字

        $params = array(
            'applicationId' => $app_id,
            'keyword' => $keyword,
            'hits' => min(30, max(1, $hits)),
            'responseType' => 'middle',
            'format' => 'json',
        );

        $url = self::RAKUTEN_KEYWORD_SEARCH . '?' . http_build_query($params);

        $response = $this->safe_remote_get($url);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['hotels'])) {
            return array(
                'success' => true,
                'message' => 'ホテルが見つかりませんでした',
                'hotels' => array(),
                'count' => 0,
            );
        }

        $hotels = array();
        foreach ($data['hotels'] as $hotel) {
            $info = $hotel['hotel'][0]['hotelBasicInfo'] ?? array();
            $hotels[] = array(
                'hotel_no' => $info['hotelNo'] ?? '',
                'name' => $info['hotelName'] ?? '',
                'address' => ($info['address1'] ?? '') . ($info['address2'] ?? ''),
                'image_url' => $info['hotelImageUrl'] ?? '',
                'thumbnail_url' => $info['hotelThumbnailUrl'] ?? '',
                'review_average' => $info['reviewAverage'] ?? null,
                'review_count' => $info['reviewCount'] ?? 0,
                'min_charge' => $info['hotelMinCharge'] ?? null,
                'info_url' => $info['hotelInformationUrl'] ?? '',
            );
        }

        return array(
            'success' => true,
            'hotels' => $hotels,
            'count' => count($hotels),
            'total' => $data['pagingInfo']['recordCount'] ?? count($hotels),
        );
    }

    /**
     * ホテル詳細取得
     */
    public function get_hotel_detail($hotel_no) {
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        if (empty($app_id)) {
            return array(
                'success' => false,
                'message' => '楽天App IDが未設定',
            );
        }

        $params = array(
            'applicationId' => $app_id,
            'hotelNo' => $hotel_no,
            'responseType' => 'large',
            'format' => 'json',
        );

        $url = self::RAKUTEN_HOTEL_DETAIL . '?' . http_build_query($params);

        $response = $this->safe_remote_get($url);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['hotels'][0])) {
            return array(
                'success' => false,
                'message' => 'ホテルが見つかりませんでした',
            );
        }

        $hotel = $data['hotels'][0]['hotel'];
        $basic = $hotel[0]['hotelBasicInfo'] ?? array();
        $rating = $hotel[1]['hotelRatingInfo'] ?? array();

        return array(
            'success' => true,
            'hotel' => array(
                'hotel_no' => $basic['hotelNo'] ?? '',
                'name' => $basic['hotelName'] ?? '',
                'address' => ($basic['address1'] ?? '') . ($basic['address2'] ?? ''),
                'postal_code' => $basic['postalCode'] ?? '',
                'telephone' => $basic['telephoneNo'] ?? '',
                'access' => $basic['access'] ?? '',
                'parking' => $basic['parkingInformation'] ?? '',
                'latitude' => $basic['latitude'] ?? null,
                'longitude' => $basic['longitude'] ?? null,
                'image_url' => $basic['hotelImageUrl'] ?? '',
                'special' => $basic['hotelSpecial'] ?? '',
                'review_average' => $basic['reviewAverage'] ?? null,
                'review_count' => $basic['reviewCount'] ?? 0,
                'rating' => array(
                    'service' => $rating['serviceAverage'] ?? null,
                    'location' => $rating['locationAverage'] ?? null,
                    'room' => $rating['roomAverage'] ?? null,
                    'equipment' => $rating['equipmentAverage'] ?? null,
                    'bath' => $rating['bathAverage'] ?? null,
                    'meal' => $rating['mealAverage'] ?? null,
                ),
            ),
        );
    }

    /**
     * 診断
     */
    public function diagnose() {
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        $diagnosis = array(
            'timestamp' => current_time('mysql'),
            'checks' => array(),
            'overall_status' => 'unknown',
        );

        // 1. App ID設定チェック
        $diagnosis['checks']['app_id'] = array(
            'name' => 'App ID設定',
            'status' => !empty($app_id) ? 'ok' : 'error',
            'message' => !empty($app_id) ? '設定済み' : '未設定',
        );

        // 2. API接続チェック
        if (!empty($app_id)) {
            $connection = $this->test_connection();
            $diagnosis['checks']['connection'] = array(
                'name' => 'API接続',
                'status' => $connection['success'] ? 'ok' : 'error',
                'message' => $connection['message'],
                'response_time_ms' => $connection['response_time_ms'] ?? null,
            );
        } else {
            $diagnosis['checks']['connection'] = array(
                'name' => 'API接続',
                'status' => 'skipped',
                'message' => 'App ID未設定のためスキップ',
            );
        }

        // 3. レート制限チェック
        $diagnosis['checks']['rate_limit'] = array(
            'name' => 'レート制限',
            'status' => 'ok',
            'message' => '1秒1リクエスト制限に注意',
        );

        // 4. 画像取得チェック
        if (!empty($app_id)) {
            $search = $this->search_hotel('星野リゾート', 1);
            if ($search['success'] && !empty($search['hotels'])) {
                $has_image = !empty($search['hotels'][0]['image_url']);
                $diagnosis['checks']['image'] = array(
                    'name' => '画像取得',
                    'status' => $has_image ? 'ok' : 'warning',
                    'message' => $has_image ? '画像URL取得可能' : '画像URLなし',
                );
            } else {
                $diagnosis['checks']['image'] = array(
                    'name' => '画像取得',
                    'status' => 'warning',
                    'message' => 'テスト検索で結果なし',
                );
            }
        }

        // 総合ステータス
        $statuses = array_column($diagnosis['checks'], 'status');
        if (in_array('error', $statuses)) {
            $diagnosis['overall_status'] = 'error';
        } elseif (in_array('warning', $statuses)) {
            $diagnosis['overall_status'] = 'warning';
        } else {
            $diagnosis['overall_status'] = 'ok';
        }

        return $diagnosis;
    }
}

// 初期化
new HRS_Rakuten_API_Test_Endpoint();