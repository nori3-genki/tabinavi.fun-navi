<?php
/**
 * HRS GA4 API Client
 * Google Analytics 4 Data API クライアント
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_GA4_API_Client {
    
    /** @var string GA4 Data API エンドポイント */
    private $api_endpoint = 'https://analyticsdata.googleapis.com/v1beta/properties/';
    
    /** @var string OAuth2 トークンエンドポイント */
    private $token_endpoint = 'https://oauth2.googleapis.com/token';
    
    /** @var string API スコープ */
    private $scope = 'https://www.googleapis.com/auth/analytics.readonly';
    
    /** @var array サービスアカウント情報 */
    private $service_account = null;
    
    /** @var string プロパティID */
    private $property_id = '';
    
    /** @var string アクセストークン */
    private $access_token = null;
    
    /** @var int トークン有効期限 */
    private $token_expires = 0;
    
    /** @var string トークンキャッシュ用オプション名 */
    private $token_cache_key = 'hrs_google_access_token';
    
    /** @var array エラーログ */
    private $errors = array();
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * 認証情報を読み込み
     */
    private function load_credentials() {
        // サービスアカウントJSON
        $json_encrypted = get_option('hrs_ga4_service_account_json', '');
        if (!empty($json_encrypted)) {
            $json = $this->decrypt_data($json_encrypted);
            if ($json) {
                $this->service_account = json_decode($json, true);
            }
        }
        
        // プロパティID
        $this->property_id = get_option('hrs_ga4_property_id', '');
        
        // キャッシュされたトークン
        $cached = get_transient($this->token_cache_key);
        if ($cached) {
            $this->access_token = $cached['token'];
            $this->token_expires = $cached['expires'];
        }
    }
    
    /**
     * 認証（アクセストークン取得）
     * 
     * @return bool 成功/失敗
     */
    public function authenticate() {
        // 既存トークンが有効ならそのまま使用
        if ($this->access_token && time() < $this->token_expires - 60) {
            return true;
        }
        
        if (!$this->service_account) {
            $this->add_error('サービスアカウント情報が設定されていません');
            return false;
        }
        
        // JWT作成
        $jwt = $this->create_jwt();
        if (!$jwt) {
            return false;
        }
        
        // トークン取得
        $response = wp_remote_post($this->token_endpoint, array(
            'timeout' => 30,
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            )
        ));
        
        if (is_wp_error($response)) {
            $this->add_error('トークン取得エラー: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200 || !isset($body['access_token'])) {
            $error_msg = isset($body['error_description']) ? $body['error_description'] : 'Unknown error';
            $this->add_error('認証失敗: ' . $error_msg);
            return false;
        }
        
        // トークン保存
        $this->access_token = $body['access_token'];
        $this->token_expires = time() + intval($body['expires_in']);
        
        // キャッシュ（50分間）
        set_transient($this->token_cache_key, array(
            'token' => $this->access_token,
            'expires' => $this->token_expires
        ), 3000);
        
        return true;
    }
    
    /**
     * JWT（JSON Web Token）を作成
     * 
     * @return string|false JWT文字列またはfalse
     */
    private function create_jwt() {
        if (!isset($this->service_account['private_key']) || !isset($this->service_account['client_email'])) {
            $this->add_error('サービスアカウントJSONの形式が不正です');
            return false;
        }
        
        $now = time();
        $expires = $now + 3600; // 1時間
        
        // Header
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        // Payload
        $payload = array(
            'iss' => $this->service_account['client_email'],
            'scope' => $this->scope,
            'aud' => $this->token_endpoint,
            'iat' => $now,
            'exp' => $expires
        );
        
        // Base64URL エンコード
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));
        
        $data = $header_encoded . '.' . $payload_encoded;
        
        // 署名
        $private_key = openssl_pkey_get_private($this->service_account['private_key']);
        if (!$private_key) {
            $this->add_error('秘密鍵の読み込みに失敗しました');
            return false;
        }
        
        $signature = '';
        if (!openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            $this->add_error('署名の作成に失敗しました');
            return false;
        }
        
        $signature_encoded = $this->base64url_encode($signature);
        
        return $data . '.' . $signature_encoded;
    }
    
    /**
     * GA4レポートを取得
     * 
     * @param array $params パラメータ
     * @return array|false レポートデータまたはfalse
     */
    public function get_report($params = array()) {
        if (!$this->authenticate()) {
            return false;
        }
        
        if (empty($this->property_id)) {
            $this->add_error('GA4プロパティIDが設定されていません');
            return false;
        }
        
        $defaults = array(
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d', strtotime('-1 day')),
            'limit' => 1000
        );
        
        $params = wp_parse_args($params, $defaults);
        
        $url = $this->api_endpoint . $this->property_id . ':runReport';
        
        $request_body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $params['start_date'],
                    'endDate' => $params['end_date']
                )
            ),
            'dimensions' => array(
                array('name' => 'pagePath'),
                array('name' => 'date')
            ),
            'metrics' => array(
                array('name' => 'averageSessionDuration'),
                array('name' => 'bounceRate'),
                array('name' => 'screenPageViews')
            ),
            'limit' => $params['limit'],
            'keepEmptyRows' => false
        );
        
        $response = wp_remote_post($url, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body)
        ));
        
        if (is_wp_error($response)) {
            $this->add_error('API呼び出しエラー: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            $this->add_error('GA4 API エラー (' . $code . '): ' . $error_msg);
            return false;
        }
        
        return $body;
    }
    
    /**
     * 全記事のパフォーマンスデータを取得
     * 
     * @param int $days 取得日数
     * @return array 取得結果
     */
    public function fetch_performance_data($days = 7) {
        $result = array(
            'success' => false,
            'data' => array(),
            'count' => 0,
            'errors' => array()
        );
        
        $report = $this->get_report(array(
            'start_date' => date('Y-m-d', strtotime("-{$days} days")),
            'end_date' => date('Y-m-d', strtotime('-1 day'))
        ));
        
        if (!$report) {
            $result['errors'] = $this->errors;
            return $result;
        }
        
        $parsed = $this->parse_response($report);
        
        if (empty($parsed)) {
            $result['success'] = true;
            $result['errors'][] = 'データがありません';
            return $result;
        }
        
        $result['success'] = true;
        $result['data'] = $parsed;
        $result['count'] = count($parsed);
        
        return $result;
    }
    
    /**
     * APIレスポンスをパース
     * 
     * @param array $response APIレスポンス
     * @return array パース済みデータ
     */
    public function parse_response($response) {
        $data = array();
        
        if (!isset($response['rows']) || empty($response['rows'])) {
            return $data;
        }
        
        foreach ($response['rows'] as $row) {
            $page_path = $row['dimensionValues'][0]['value'] ?? '';
            $date = $row['dimensionValues'][1]['value'] ?? '';
            
            // 日付フォーマット変換（YYYYMMDD → YYYY-MM-DD）
            if (strlen($date) === 8) {
                $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            }
            
            $avg_session_duration = floatval($row['metricValues'][0]['value'] ?? 0);
            $bounce_rate = floatval($row['metricValues'][1]['value'] ?? 0) * 100; // 0-1 → 0-100
            $page_views = intval($row['metricValues'][2]['value'] ?? 0);
            
            $data[] = array(
                'page_path' => $page_path,
                'date' => $date,
                'avg_time_on_page' => round($avg_session_duration, 1),
                'bounce_rate' => round($bounce_rate, 1),
                'page_views' => $page_views
            );
        }
        
        return $data;
    }
    
    /**
     * Performance Trackerに保存
     * 
     * @param array $data パース済みデータ
     * @return array 保存結果
     */
    public function save_to_tracker($data) {
        $result = array(
            'success' => 0,
            'skip' => 0,
            'error' => 0
        );
        
        if (!class_exists('HRS_Performance_Tracker')) {
            return $result;
        }
        
        if (!class_exists('HRS_CSV_Importer')) {
            return $result;
        }
        
        $tracker = new HRS_Performance_Tracker();
        $importer = new HRS_CSV_Importer();
        
        // 日付ごとにグループ化
        $grouped = array();
        foreach ($data as $row) {
            $date = $row['date'];
            $path = $row['page_path'];
            
            if (!isset($grouped[$date])) {
                $grouped[$date] = array();
            }
            
            // 同じパスのデータがあれば平均を取る
            if (isset($grouped[$date][$path])) {
                $grouped[$date][$path]['avg_time_on_page'] = 
                    ($grouped[$date][$path]['avg_time_on_page'] + $row['avg_time_on_page']) / 2;
                $grouped[$date][$path]['bounce_rate'] = 
                    ($grouped[$date][$path]['bounce_rate'] + $row['bounce_rate']) / 2;
            } else {
                $grouped[$date][$path] = $row;
            }
        }
        
        // 保存
        foreach ($grouped as $date => $paths) {
            foreach ($paths as $path => $row) {
                $post_id = $importer->url_to_post_id($path);
                
                if (!$post_id) {
                    $result['skip']++;
                    continue;
                }
                
                // 既存データを取得してマージ
                $existing = $tracker->get_data_by_post($post_id, $date);
                
                $save_data = array(
                    'post_id' => $post_id,
                    'avg_time_on_page' => $row['avg_time_on_page'],
                    'bounce_rate' => $row['bounce_rate'],
                    'ctr' => $existing ? $existing->ctr : 0,
                    'avg_position' => $existing ? $existing->avg_position : 0,
                    'impressions' => $existing ? $existing->impressions : 0,
                    'data_date' => $date,
                    'source' => 'ga4_api'
                );
                
                $saved = $tracker->save_data($save_data);
                
                if ($saved) {
                    $result['success']++;
                } else {
                    $result['error']++;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 接続テスト
     * 
     * @return array テスト結果
     */
    public function test_connection() {
        $result = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        // 認証テスト
        if (!$this->authenticate()) {
            $result['message'] = '認証に失敗しました';
            $result['details'] = $this->errors;
            return $result;
        }
        
        $result['details']['auth'] = '認証成功';
        
        // プロパティIDチェック
        if (empty($this->property_id)) {
            $result['message'] = 'プロパティIDが設定されていません';
            return $result;
        }
        
        // APIテスト（1件だけ取得）
        $test_report = $this->get_report(array(
            'start_date' => date('Y-m-d', strtotime('-3 days')),
            'end_date' => date('Y-m-d', strtotime('-1 day')),
            'limit' => 1
        ));
        
        if ($test_report === false) {
            $result['message'] = 'API呼び出しに失敗しました';
            $result['details']['errors'] = $this->errors;
            return $result;
        }
        
        $result['success'] = true;
        $result['message'] = 'GA4 API 接続成功';
        $result['details']['property_id'] = $this->property_id;
        $result['details']['row_count'] = isset($test_report['rowCount']) ? $test_report['rowCount'] : 0;
        
        return $result;
    }
    
    /**
     * サービスアカウントJSONを保存
     * 
     * @param string $json JSON文字列
     * @return bool 成功/失敗
     */
    public function save_service_account($json) {
        // JSONバリデーション
        $decoded = json_decode($json, true);
        if (!$decoded) {
            return false;
        }
        
        // 必須フィールドチェック
        $required = array('type', 'project_id', 'private_key', 'client_email');
        foreach ($required as $field) {
            if (!isset($decoded[$field])) {
                return false;
            }
        }
        
        if ($decoded['type'] !== 'service_account') {
            return false;
        }
        
        // 暗号化して保存
        $encrypted = $this->encrypt_data($json);
        update_option('hrs_ga4_service_account_json', $encrypted);
        
        // メモリ上も更新
        $this->service_account = $decoded;
        
        // トークンキャッシュをクリア
        delete_transient($this->token_cache_key);
        
        return true;
    }
    
    /**
     * プロパティIDを保存
     * 
     * @param string $property_id プロパティID
     * @return bool 成功/失敗
     */
    public function save_property_id($property_id) {
        // 数字のみ許可
        $property_id = preg_replace('/[^0-9]/', '', $property_id);
        
        if (empty($property_id)) {
            return false;
        }
        
        update_option('hrs_ga4_property_id', $property_id);
        $this->property_id = $property_id;
        
        return true;
    }
    
    /**
     * 設定状態を取得
     * 
     * @return array 設定状態
     */
    public function get_status() {
        return array(
            'service_account_configured' => !empty($this->service_account),
            'property_id_configured' => !empty($this->property_id),
            'property_id' => $this->property_id,
            'last_sync' => get_option('hrs_ga4_last_sync', ''),
            'sync_status' => get_option('hrs_ga4_sync_status', 'never')
        );
    }
    
    /**
     * 最終同期状態を更新
     * 
     * @param string $status ステータス
     * @param int $count 件数
     */
    public function update_sync_status($status, $count = 0) {
        update_option('hrs_ga4_last_sync', current_time('mysql'));
        update_option('hrs_ga4_sync_status', $status);
        update_option('hrs_ga4_last_count', $count);
    }
    
    /**
     * エラーを追加
     * 
     * @param string $message エラーメッセージ
     */
    private function add_error($message) {
        $this->errors[] = $message;
        hrs_log('GA4 API Error: ' . $message, 'error');
    }
    
    /**
     * エラーを取得
     * 
     * @return array エラー配列
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Base64URL エンコード
     * 
     * @param string $data データ
     * @return string エンコード済み文字列
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * データを暗号化
     * 
     * @param string $data データ
     * @return string 暗号化済みデータ
     */
    private function encrypt_data($data) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * データを復号
     * 
     * @param string $data 暗号化データ
     * @return string|false 復号済みデータまたはfalse
     */
    private function decrypt_data($data) {
        $key = $this->get_encryption_key();
        $data = base64_decode($data);
        if (strlen($data) < 16) {
            return false;
        }
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * 暗号化キーを取得
     * 
     * @return string 暗号化キー
     */
    private function get_encryption_key() {
        // AUTH_KEYが定義されていればそれを使用、なければサイトURLから生成
        if (defined('AUTH_KEY') && AUTH_KEY) {
            return hash('sha256', AUTH_KEY, true);
        }
        return hash('sha256', home_url(), true);
    }
    
    /**
     * アクセストークンを取得（外部共有用）
     * 
     * @return string|null アクセストークン
     */
    public function get_access_token() {
        if ($this->authenticate()) {
            return $this->access_token;
        }
        return null;
    }
    
    /**
     * サービスアカウント情報を取得（外部共有用）
     * 
     * @return array|null サービスアカウント情報
     */
    public function get_service_account() {
        return $this->service_account;
    }
}