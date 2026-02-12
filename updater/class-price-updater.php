<?php
/**
 * HRS Price Updater
 * 
 * 全ホテル記事の価格・評価を定期的に楽天APIから更新
 *
 * @package HRS
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Price_Updater {

    private static $instance = null;

    /**
     * シングルトン
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    public function __construct() {
        // Cronフック登録（ニュース更新の1時間後）
        add_action('hrs_weekly_price_update', [$this, 'run_price_update']);
        add_action('admin_init', [$this, 'schedule_cron']);

        // 手動実行用Ajax
        add_action('wp_ajax_hrs_manual_price_update', [$this, 'ajax_manual_update']);
    }

    /**
     * Cronスケジュール設定
     */
    public function schedule_cron() {
        // 既存スケジュールをクリア
        $timestamp = wp_next_scheduled('hrs_weekly_price_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hrs_weekly_price_update');
        }

        // ニュース更新が有効な場合のみスケジュール
        if (get_option('hrs_news_enabled', 0)) {
            $day = get_option('hrs_news_update_day', 'monday');
            $time = get_option('hrs_news_update_time', '04:00');
            
            $days = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            
            $target_day = $days[$day] ?? 1;
            $current_day = (int) current_time('w');
            $days_until = ($target_day - $current_day + 7) % 7;
            if ($days_until === 0) $days_until = 7;
            
            $time_parts = explode(':', $time);
            $hour = (int) ($time_parts[0] ?? 4) + 1; // ニュース更新の1時間後
            $minute = (int) ($time_parts[1] ?? 0);
            
            $next_run = strtotime("+{$days_until} days", strtotime(current_time('Y-m-d')));
            $next_run = strtotime("{$hour}:{$minute}:00", $next_run);
            
            wp_schedule_event($next_run, 'weekly', 'hrs_weekly_price_update');
            
            $this->log("Price update cron scheduled: " . date('Y-m-d H:i:s', $next_run));
        }
    }

    /**
     * 価格更新実行
     */
    public function run_price_update() {
        $this->log('=== 週次価格更新開始 ===');

        // 楽天APIクラス確認
        if (!class_exists('HRS_Rakuten_API_Test_Endpoint')) {
            $this->log('ERROR: HRS_Rakuten_API_Test_Endpoint not found');
            return ['success' => false, 'message' => 'API class not found'];
        }

        // 楽天App ID確認
        $app_id = get_option('hrs_rakuten_app_id', '');
        if (empty($app_id)) {
            $this->log('ERROR: Rakuten App ID not configured');
            update_option('hrs_price_last_updated', current_time('mysql'));
            update_option('hrs_price_last_results', [
                'total' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 1,
                'error_message' => '楽天App ID未設定',
            ]);
            return ['success' => false, 'message' => '楽天App ID未設定'];
        }

        // 全ホテル記事を取得（公開済みのみ）
        $posts = get_posts([
            'post_type' => 'hotel-review',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_hrs_hotel_name',
        ]);

        $results = [
            'total' => count($posts),
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        $api = new HRS_Rakuten_API_Test_Endpoint();

        foreach ($posts as $post) {
            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
            if (empty($hotel_name)) {
                $results['skipped']++;
                continue;
            }

            try {
                $price_data = $this->fetch_hotel_data($api, $hotel_name);

                if ($price_data) {
                    // 価格保存
                    if (!empty($price_data['min_price'])) {
                        update_post_meta($post->ID, '_hrs_min_price', (int) $price_data['min_price']);
                    }
                    if (!empty($price_data['max_price'])) {
                        update_post_meta($post->ID, '_hrs_max_price', (int) $price_data['max_price']);
                    }
                    
                    // 評価保存
                    if (!empty($price_data['rating'])) {
                        update_post_meta($post->ID, '_hrs_rakuten_rating', (float) $price_data['rating']);
                    }
                    
                    // レビュー件数保存
                    if (!empty($price_data['review_count'])) {
                        update_post_meta($post->ID, '_hrs_rakuten_review_count', (int) $price_data['review_count']);
                    }

                    // 更新日時保存
                    update_post_meta($post->ID, '_hrs_price_updated', current_time('mysql'));

                    $results['updated']++;
                    $this->log("Updated: {$hotel_name} - ¥" . number_format($price_data['min_price']) . "〜 / ★{$price_data['rating']}");
                } else {
                    $results['skipped']++;
                    $this->log("Skipped: {$hotel_name} - No data found");
                }

                // API制限対策：リクエスト間隔（楽天は1秒1リクエスト）
                sleep(1);

            } catch (Exception $e) {
                $error_msg = "Error for {$hotel_name}: " . $e->getMessage();
                $this->log($error_msg);
                $results['errors']++;
                $results['error_details'][] = $error_msg;
            }
        }

        // 全体の更新日時保存
        update_option('hrs_price_last_updated', current_time('mysql'));
        update_option('hrs_price_last_results', $results);

        $this->log("=== 価格更新完了: {$results['updated']}件更新 / {$results['skipped']}件スキップ / {$results['errors']}件エラー ===");

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * 楽天APIからホテルデータ取得
     */
    private function fetch_hotel_data($api, $hotel_name) {
        try {
            $result = $api->search_hotel($hotel_name, 1);

            if (empty($result['success']) || empty($result['hotels'][0])) {
                return null;
            }

            $hotel = $result['hotels'][0];

            return [
                'min_price' => $hotel['min_charge'] ?? 0,
                'max_price' => $hotel['min_charge'] ?? 0, // 楽天APIはmax_priceを返さないため
                'rating' => $hotel['review_average'] ?? 0,
                'review_count' => $hotel['review_count'] ?? 0,
            ];
        } catch (Exception $e) {
            $this->log('API Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 単一記事の価格更新
     */
    public function update_single_post($post_id) {
        if (!class_exists('HRS_Rakuten_API_Test_Endpoint')) {
            return false;
        }

        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            return false;
        }

        $api = new HRS_Rakuten_API_Test_Endpoint();
        $data = $this->fetch_hotel_data($api, $hotel_name);

        if ($data) {
            if (!empty($data['min_price'])) {
                update_post_meta($post_id, '_hrs_min_price', (int) $data['min_price']);
            }
            if (!empty($data['max_price'])) {
                update_post_meta($post_id, '_hrs_max_price', (int) $data['max_price']);
            }
            if (!empty($data['rating'])) {
                update_post_meta($post_id, '_hrs_rakuten_rating', (float) $data['rating']);
            }
            if (!empty($data['review_count'])) {
                update_post_meta($post_id, '_hrs_rakuten_review_count', (int) $data['review_count']);
            }
            update_post_meta($post_id, '_hrs_price_updated', current_time('mysql'));
            
            return true;
        }

        return false;
    }

    /**
     * 価格更新日時を取得（フォーマット済み）
     */
    public static function get_price_updated_date($post_id, $format = 'n/j') {
        $updated = get_post_meta($post_id, '_hrs_price_updated', true);
        
        if (empty($updated)) {
            return '';
        }

        return date($format, strtotime($updated));
    }

    /**
     * 価格情報を取得
     */
    public static function get_price_info($post_id) {
        return [
            'min_price' => (int) get_post_meta($post_id, '_hrs_min_price', true),
            'max_price' => (int) get_post_meta($post_id, '_hrs_max_price', true),
            'rating' => (float) get_post_meta($post_id, '_hrs_rakuten_rating', true),
            'review_count' => (int) get_post_meta($post_id, '_hrs_rakuten_review_count', true),
            'updated' => get_post_meta($post_id, '_hrs_price_updated', true),
        ];
    }

    /**
     * 手動更新Ajax
     */
    public function ajax_manual_update() {
        check_ajax_referer('hrs_price_update_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $result = $this->run_price_update();

        if ($result['success']) {
            $results = $result['results'];
            wp_send_json_success([
                'message' => sprintf(
                    '価格更新完了: %d件更新 / %d件スキップ / %d件エラー',
                    $results['updated'],
                    $results['skipped'],
                    $results['errors']
                ),
                'results' => $results,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? 'エラーが発生しました',
            ]);
        }
    }

    /**
     * ログ出力
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Price Updater] ' . $message);
        }
    }
}

// 初期化
add_action('plugins_loaded', function() {
    HRS_Price_Updater::get_instance();
});