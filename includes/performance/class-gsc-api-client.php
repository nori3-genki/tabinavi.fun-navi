<?php
/**
 * HRS GSC API Client
 * Google Search Console API クライアント
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_GSC_API_Client {
    
    /** @var string Search Console API エンドポイント */
    private $api_endpoint = 'https://www.googleapis.com/webmasters/v3/sites/';
    
    /** @var string OAuth2 トークンエンドポイント */
    private $token_endpoint = 'https://oauth2.googleapis.com/token';
    
    /** @var string API スコープ */
    private $scope = 'https://www.googleapis.com/auth/webmasters.readonly';
    
    /** @var HRS_GA4_API_Client GA4クライアント（認証共有用） */
    private $ga4_client = null;
    
    /** @var string サイトURL */
    private $site_url = '';
    
    /** @var string アクセストークン */
    private $access_token = null;
    
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
        // サイトURL
        $this->site_url = get_option('hrs_gsc_site_url', '');
        
        // サイトURLが空の場合はホームURLを使用
        if (empty($this->site_url)) {
            $this->site_url = home_url('/');
        }
    }
    
    /**
     * 認証（GA4クライアントのトークンを利用）
     * 
     * @return bool 成功/失敗
     */
    public function authenticate() {
        // GA4クライアントから認証情報を取得
        if (!$this->ga4_client) {
            if (!class_exists('HRS_GA4_API_Client')) {
                $this->add_error('GA4 API Clientが読み込まれていません');
                return false;
            }
            $this->ga4_client = new HRS_GA4_API_Client();
        }
        
        // サービスアカウントがあるか確認
        $service_account = $this->ga4_client->get_service_account();
        if (!$service_account) {
            $this->add_error('サービスアカウント情報が設定されていません');
            return false;
        }
        
        // GSC用のトークンを取得（スコープが異なるため別途取得）
        $token = $this->get_gsc_token($service_account);
        if (!$token) {
            return false;
        }
        
        $this->access_token = $token;
        return true;
    }
    
    /**
     * GSC用アクセストークンを取得
     * 
     * @param array $service_account サービスアカウント情報
     * @return string|false アクセストークンまたはfalse
     */
    private function get_gsc_token($service_account) {
        // キャッシュチェック
        $cached = get_transient('hrs_gsc_access_token');
        if ($cached && isset($cached['token']) && time() < $cached['expires'] - 60) {
            return $cached['token'];
        }
        
        // JWT作成
        $jwt = $this->create_jwt($service_account);
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
        
        $token = $body['access_token'];
        $expires = time() + intval($body['expires_in']);
        
        // キャッシュ（50分間）
        set_transient('hrs_gsc_access_token', array(
            'token' => $token,
            'expires' => $expires
        ), 3000);
        
        return $token;
    }
    
    /**
     * JWT（JSON Web Token）を作成
     * 
     * @param array $service_account サービスアカウント情報
     * @return string|false JWT文字列またはfalse
     */
    private function create_jwt($service_account) {
        if (!isset($service_account['private_key']) || !isset($service_account['client_email'])) {
            $this->add_error('サービスアカウントJSONの形式が不正です');
            return false;
        }
        
        $now = time();
        $expires = $now + 3600;
        
        // Header
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        // Payload（GSC用スコープ）
        $payload = array(
            'iss' => $service_account['client_email'],
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
        $private_key = openssl_pkey_get_private($service_account['private_key']);
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
     * Search Analyticsデータを取得
     * 
     * @param array $params パラメータ
     * @return array|false レポートデータまたはfalse
     */
    public function get_search_analytics($params = array()) {
        if (!$this->authenticate()) {
            return false;
        }
        
        if (empty($this->site_url)) {
            $this->add_error('サイトURLが設定されていません');
            return false;
        }
        
        $defaults = array(
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d', strtotime('-1 day')),
            'row_limit' => 1000,
            'start_row' => 0
        );
        
        $params = wp_parse_args($params, $defaults);
        
        // サイトURLをエンコード
        $encoded_site_url = urlencode($this->site_url);
        $url = $this->api_endpoint . $encoded_site_url . '/searchAnalytics/query';
        
        $request_body = array(
            'startDate' => $params['start_date'],
            'endDate' => $params['end_date'],
            'dimensions' => array('page', 'date'),
            'rowLimit' => $params['row_limit'],
            'startRow' => $params['start_row']
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
            $this->add_error('GSC API エラー (' . $code . '): ' . $error_msg);
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
        
        $all_data = array();
        $start_row = 0;
        $row_limit = 1000;
        
        // ページングで全データ取得
        do {
            $report = $this->get_search_analytics(array(
                'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                'end_date' => date('Y-m-d', strtotime('-1 day')),
                'row_limit' => $row_limit,
                'start_row' => $start_row
            ));
            
            if ($report === false) {
                $result['errors'] = $this->errors;
                return $result;
            }
            
            $parsed = $this->parse_response($report);
            $all_data = array_merge($all_data, $parsed);
            
            $rows_returned = isset($report['rows']) ? count($report['rows']) : 0;
            $start_row += $row_limit;
            
            // 1000件未満ならループ終了
            if ($rows_returned < $row_limit) {
                break;
            }
            
            // 安全策：最大5000件まで
            if ($start_row >= 5000) {
                break;
            }
            
        } while (true);
        
        $result['success'] = true;
        $result['data'] = $all_data;
        $result['count'] = count($all_data);
        
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
            $page_url = $row['keys'][0] ?? '';
            $date = $row['keys'][1] ?? '';
            
            $clicks = intval($row['clicks'] ?? 0);
            $impressions = intval($row['impressions'] ?? 0);
            $ctr = floatval($row['ctr'] ?? 0) * 100; // 0-1 → 0-100
            $position = floatval($row['position'] ?? 0);
            
            $data[] = array(
                'page_url' => $page_url,
                'date' => $date,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round($ctr, 2),
                'avg_position' => round($position, 1)
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
            $url = $row['page_url'];
            
            if (!isset($grouped[$date])) {
                $grouped[$date] = array();
            }
            
            // 同じURLのデータがあれば平均を取る
            if (isset($grouped[$date][$url])) {
                $existing = $grouped[$date][$url];
                $grouped[$date][$url] = array(
                    'page_url' => $url,
                    'date' => $date,
                    'clicks' => $existing['clicks'] + $row['clicks'],
                    'impressions' => $existing['impressions'] + $row['impressions'],
                    'ctr' => ($existing['ctr'] + $row['ctr']) / 2,
                    'avg_position' => ($existing['avg_position'] + $row['avg_position']) / 2
                );
            } else {
                $grouped[$date][$url] = $row;
            }
        }
        
        // 保存
        foreach ($grouped as $date => $urls) {
            foreach ($urls as $url => $row) {
                $post_id = $importer->url_to_post_id($url);
                
                if (!$post_id) {
                    $result['skip']++;
                    continue;
                }
                
                // 既存データを取得してマージ
                $existing = $tracker->get_data_by_post($post_id, $date);
                
                $save_data = array(
                    'post_id' => $post_id,
                    'avg_time_on_page' => $existing ? $existing->avg_time_on_page : 0,
                    'bounce_rate' => $existing ? $existing->bounce_rate : 0,
                    'ctr' => $row['ctr'],
                    'avg_position' => $row['avg_position'],
                    'impressions' => $row['impressions'],
                    'data_date' => $date,
                    'source' => 'gsc_api'
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
        
        // サイトURLチェック
        if (empty($this->site_url)) {
            $result['message'] = 'サイトURLが設定されていません';
            return $result;
        }
        
        // APIテスト（1件だけ取得）
        $test_report = $this->get_search_analytics(array(
            'start_date' => date('Y-m-d', strtotime('-3 days')),
            'end_date' => date('Y-m-d', strtotime('-1 day')),
            'row_limit' => 1
        ));
        
        if ($test_report === false) {
            $result['message'] = 'API呼び出しに失敗しました';
            $result['details']['errors'] = $this->errors;
            return $result;
        }
        
        $result['success'] = true;
        $result['message'] = 'Search Console API 接続成功';
        $result['details']['site_url'] = $this->site_url;
        $result['details']['row_count'] = isset($test_report['rows']) ? count($test_report['rows']) : 0;
        
        return $result;
    }
    
    /**
     * サイトURLを保存
     * 
     * @param string $site_url サイトURL
     * @return bool 成功/失敗
     */
    public function save_site_url($site_url) {
        // URLバリデーション
        $site_url = esc_url_raw($site_url);
        
        if (empty($site_url)) {
            return false;
        }
        
        // 末尾のスラッシュを確保
        $site_url = trailingslashit($site_url);
        
        update_option('hrs_gsc_site_url', $site_url);
        $this->site_url = $site_url;
        
        return true;
    }
    
    /**
     * 設定状態を取得
     * 
     * @return array 設定状態
     */
    public function get_status() {
        // GA4クライアントの状態も確認
        $ga4_configured = false;
        if (class_exists('HRS_GA4_API_Client')) {
            $ga4 = new HRS_GA4_API_Client();
            $ga4_status = $ga4->get_status();
            $ga4_configured = $ga4_status['service_account_configured'];
        }
        
        return array(
            'service_account_configured' => $ga4_configured,
            'site_url_configured' => !empty($this->site_url),
            'site_url' => $this->site_url,
            'last_sync' => get_option('hrs_gsc_last_sync', ''),
            'sync_status' => get_option('hrs_gsc_sync_status', 'never')
        );
    }
    
    /**
     * 最終同期状態を更新
     * 
     * @param string $status ステータス
     * @param int $count 件数
     */
    public function update_sync_status($status, $count = 0) {
        update_option('hrs_gsc_last_sync', current_time('mysql'));
        update_option('hrs_gsc_sync_status', $status);
        update_option('hrs_gsc_last_count', $count);
    }
    
    /**
     * サイト一覧を取得（設定用）
     * 
     * @return array|false サイト一覧またはfalse
     */
    public function get_sites_list() {
        if (!$this->authenticate()) {
            return false;
        }
        
        $url = 'https://www.googleapis.com/webmasters/v3/sites';
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            )
        ));
        
        if (is_wp_error($response)) {
            $this->add_error('サイト一覧取得エラー: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            return false;
        }
        
        $sites = array();
        if (isset($body['siteEntry']) && is_array($body['siteEntry'])) {
            foreach ($body['siteEntry'] as $entry) {
                $sites[] = array(
                    'url' => $entry['siteUrl'],
                    'permission' => $entry['permissionLevel']
                );
            }
        }
        
        return $sites;
    }
    
    /**
     * エラーを追加
     * 
     * @param string $message エラーメッセージ
     */
    private function add_error($message) {
        $this->errors[] = $message;
        hrs_log('GSC API Error: ' . $message, 'error');
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
}