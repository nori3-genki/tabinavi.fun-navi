<?php

/**
 * HRS News & Plan Updater
 *
 * Google CSEを使用してホテルのニュース・新プラン情報を取得
 * グローバルニュース対応版（wp_optionsに統合保存）
 *
 * @package HRS
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_News_Plan_Updater
{

    private static $instance = null;
    private $cse_api_key;
    private $cse_id;
    private $moshimo_id;

    // グローバルニュース・プラン保存用
    private $global_news = [];
    private $global_plans = [];

    /**
     * シングルトンインスタンス取得
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->cse_api_key = get_option('hrs_google_cse_api_key', '');
        $this->cse_id = get_option('hrs_google_cse_id', '');
        $this->moshimo_id = get_option('hrs_moshimo_affiliate_id', '');

        // Cronフック登録
        add_action('hrs_weekly_news_update', [$this, 'run_weekly_update']);
        add_action('admin_init', [$this, 'schedule_cron']);

        // 手動実行用Ajax
        add_action('wp_ajax_hrs_manual_news_update', [$this, 'ajax_manual_update']);
    }

    /**
     * Cronスケジュール設定
     */
    public function schedule_cron()
    {
        // 既存スケジュールをクリア
        $timestamp = wp_next_scheduled('hrs_weekly_news_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hrs_weekly_news_update');
        }

        // 新しいスケジュールを設定
        if (get_option('hrs_news_enabled', 0)) {
            $day = get_option('hrs_news_update_day', 'monday');
            $time = get_option('hrs_news_update_time', '04:00');

            $days = [
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6
            ];

            $target_day = $days[$day] ?? 1;
            $current_day = (int) current_time('w');
            $days_until = ($target_day - $current_day + 7) % 7;
            if ($days_until === 0) $days_until = 7;

            $time_parts = explode(':', $time);
            $hour = (int) ($time_parts[0] ?? 4);
            $minute = (int) ($time_parts[1] ?? 0);

            $next_run = strtotime("+{$days_until} days", strtotime(current_time('Y-m-d')));
            $next_run = strtotime("{$hour}:{$minute}:00", $next_run);

            wp_schedule_event($next_run, 'weekly', 'hrs_weekly_news_update');

            $this->log("Cron scheduled: " . date('Y-m-d H:i:s', $next_run));
        }
    }

    /**
     * 週次更新実行
     */
    public function run_weekly_update()
    {
        $this->log('=== 週次ニュース・プラン更新開始 ===');

        // API設定チェック
        if (empty($this->cse_api_key) || empty($this->cse_id)) {
            $error_msg = 'Google CSE API未設定のため更新できません';
            $this->log('ERROR: ' . $error_msg);
            update_option('hrs_news_last_updated', current_time('mysql'));
            update_option('hrs_news_last_results', [
                'total' => 0,
                'news_found' => 0,
                'plans_found' => 0,
                'errors' => 1,
                'error_message' => $error_msg,
            ]);
            return ['success' => false, 'message' => $error_msg];
        }

        // 取得設定確認
        $fetch_news = get_option('hrs_news_fetch_news', 1);
        $fetch_plans = get_option('hrs_news_fetch_plans', 1);

        if (!$fetch_news && !$fetch_plans) {
            $this->log('INFO: ニュース・プラン両方とも取得無効');
            return ['success' => true, 'message' => '取得設定が無効です'];
        }

        // グローバルデータ初期化
        $this->global_news = [];
        $this->global_plans = [];

        // 全ホテル記事を取得（公開済みのみ）
        $posts = get_posts([
            'post_type' => 'hotel-review',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_hrs_hotel_name',
        ]);

        $results = [
            'total' => count($posts),
            'news_found' => 0,
            'plans_found' => 0,
            'updated' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        foreach ($posts as $post) {
            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
            if (empty($hotel_name)) {
                $this->log("SKIP: No hotel name for post {$post->ID}");
                continue;
            }

            try {
                $updated = false;

                // ニュース取得
                if ($fetch_news) {
                    $news = $this->fetch_news($hotel_name);
                    if (!empty($news)) {
                        // ホテル情報を追加
                        foreach ($news as &$item) {
                            $item['post_id'] = $post->ID;
                            $item['hotel_name'] = $hotel_name;
                            $item['post_url'] = get_permalink($post->ID);
                        }

                        $this->save_news($post->ID, $news);
                        $results['news_found'] += count($news);
                        $updated = true;
                    }
                }

                // 新プラン取得
                if ($fetch_plans) {
                    $plans = $this->fetch_new_plans($hotel_name);
                    if (!empty($plans)) {
                        // ホテル情報を追加
                        foreach ($plans as &$item) {
                            $item['post_id'] = $post->ID;
                            $item['hotel_name'] = $hotel_name;
                            $item['post_url'] = get_permalink($post->ID);
                        }

                        $this->save_plans($post->ID, $plans);
                        $results['plans_found'] += count($plans);
                        $updated = true;
                    }
                }

                if ($updated) {
                    $results['updated']++;
                }

                // API制限対策：リクエスト間隔
                usleep(500000); // 0.5秒

            } catch (Exception $e) {
                $error_msg = "Error for {$hotel_name}: " . $e->getMessage();
                $this->log($error_msg);
                $results['errors']++;
                $results['error_details'][] = $error_msg;
            }
        }

        // グローバルニュース・プランを保存
        $this->save_global_data();

        // 更新日時保存
        update_option('hrs_news_last_updated', current_time('mysql'));
        update_option('hrs_news_last_results', $results);

        $this->log("=== 更新完了: 記事{$results['updated']}件更新, ニュース{$results['news_found']}件, プラン{$results['plans_found']}件 ===");

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * ニュース取得（Google CSE）
     */
    public function fetch_news($hotel_name)
    {
        if (empty($this->cse_api_key) || empty($this->cse_id)) {
            return [];
        }

        $days_limit = (int) get_option('hrs_news_days_limit', 30);
        $query = $hotel_name . ' ニュース OR リニューアル OR 新オープン OR イベント OR 開業';
        $results = $this->search_cse($query, 'news', $days_limit);

        return $this->filter_recent_results($results, $days_limit);
    }

    /**
     * 新プラン取得（Google CSE - OTAサイト限定）
     */
    public function fetch_new_plans($hotel_name)
    {
        if (empty($this->cse_api_key) || empty($this->cse_id)) {
            return [];
        }

        $days_limit = (int) get_option('hrs_news_days_limit', 30);
        $ota_sites = 'site:travel.rakuten.co.jp OR site:jalan.net OR site:ikyu.com OR site:jtb.co.jp OR site:rurubu.travel';
        $query = $hotel_name . ' 新プラン OR 期間限定 OR キャンペーン OR 特別プラン ' . $ota_sites;
        $results = $this->search_cse($query, 'plan', $days_limit * 2);

        return $this->filter_recent_results($results, $days_limit * 2);
    }

    /**
     * Google CSE検索実行
     */
    private function search_cse($query, $type = 'news', $days_limit = 30)
    {
        $date_restrict = 'd' . $days_limit; // 日数指定

        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => $this->cse_api_key,
            'cx' => $this->cse_id,
            'q' => $query,
            'num' => 10,
            'lr' => 'lang_ja',
            'sort' => 'date',
            'dateRestrict' => $date_restrict,
        ]);

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            $this->log('CSE Error: ' . $response->get_error_message());
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log("CSE HTTP Error: {$code}");
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['items'])) {
            return [];
        }

        $results = [];
        foreach ($body['items'] as $item) {
            $url = $item['link'] ?? '';

            // もしもアフィリエイトリンクに変換（楽天のみ）
            $affiliate_url = $this->convert_to_moshimo_link($url);

            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $affiliate_url, // もしもリンク
                'original_url' => $url,   // 元のURL（参考用）
                'snippet' => $item['snippet'] ?? '',
                'source' => $this->extract_source($url),
                'date' => $this->extract_date($item),
                'type' => $type,
                'fetched_at' => current_time('mysql'),
            ];
        }

        return $results;
    }

    /**
     * もしもアフィリエイトリンクに変換（楽天のみ）
     */
    private function convert_to_moshimo_link($url)
    {
        // もしもID未設定の場合は元のURLをそのまま返す
        if (empty($this->moshimo_id)) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // 楽天トラベルのみ対応
        if (strpos($host, 'travel.rakuten.co.jp') === false) {
            return $url;
        }

        // もしもアフィリエイトリンク生成
        $moshimo_url = 'https://af.moshimo.com/af/c/click?a_id=' . $this->moshimo_id
            . '&p_id=54&pc_id=54&pl_id=616&url=' . urlencode($url);

        return $moshimo_url;
    }

    /**
     * ソース名抽出
     */
    private function extract_source($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        $sources = [
            'travel.rakuten.co.jp' => '楽天トラベル',
            'jalan.net' => 'じゃらん',
            'ikyu.com' => '一休.com',
            'jtb.co.jp' => 'JTB',
            'rurubu.travel' => 'るるぶ',
            'booking.com' => 'Booking.com',
            'yahoo.co.jp' => 'Yahoo!',
            'hotels.com' => 'Hotels.com',
        ];

        foreach ($sources as $domain => $name) {
            if (strpos($host, $domain) !== false) {
                return $name;
            }
        }

        return $host;
    }

    /**
     * 日付抽出
     */
    private function extract_date($item)
    {
        // メタデータから日付取得を試行
        if (!empty($item['pagemap']['metatags'][0]['article:published_time'])) {
            return date('Y-m-d', strtotime($item['pagemap']['metatags'][0]['article:published_time']));
        }

        if (!empty($item['pagemap']['metatags'][0]['og:updated_time'])) {
            return date('Y-m-d', strtotime($item['pagemap']['metatags'][0]['og:updated_time']));
        }

        // snippetから日付パターンを探す
        $snippet = $item['snippet'] ?? '';
        if (preg_match('/(\d{4})[年\/\-](\d{1,2})[月\/\-](\d{1,2})/', $snippet, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        return current_time('Y-m-d');
    }

    /**
     * 最近の結果のみフィルタ
     */
    private function filter_recent_results($results, $days = 30)
    {
        $cutoff = strtotime("-{$days} days");

        return array_filter($results, function ($item) use ($cutoff) {
            $date = strtotime($item['date'] ?? '');
            return $date >= $cutoff;
        });
    }

    /**
     * ニュース保存（個別記事 + グローバル）
     */
    private function save_news($post_id, $news)
    {
        // 個別記事に保存
        $existing = get_post_meta($post_id, '_hrs_news_items', true) ?: [];

        // 重複除去（元のURLベース）
        $existing_urls = array_column($existing, 'original_url');
        foreach ($news as $item) {
            $original = $item['original_url'] ?? $item['url'];
            if (!in_array($original, $existing_urls)) {
                $existing[] = $item;
                $existing_urls[] = $original;
            }
        }

        // 日付で降順ソート
        usort($existing, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        // 最新30件のみ保持
        $existing = array_slice($existing, 0, 30);

        update_post_meta($post_id, '_hrs_news_items', $existing);
        update_post_meta($post_id, '_hrs_news_updated', current_time('mysql'));

        // グローバルニュースに追加
        $this->global_news = array_merge($this->global_news, $news);
    }

    /**
     * プラン保存（個別記事 + グローバル）
     */
    private function save_plans($post_id, $plans)
    {
        // 個別記事に保存
        $existing = get_post_meta($post_id, '_hrs_plan_items', true) ?: [];

        // 重複除去（元のURLベース）
        $existing_urls = array_column($existing, 'original_url');
        foreach ($plans as $item) {
            $original = $item['original_url'] ?? $item['url'];
            if (!in_array($original, $existing_urls)) {
                $existing[] = $item;
                $existing_urls[] = $original;
            }
        }

        // 日付で降順ソート
        usort($existing, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        // 最新30件のみ保持
        $existing = array_slice($existing, 0, 30);

        update_post_meta($post_id, '_hrs_plan_items', $existing);
        update_post_meta($post_id, '_hrs_plan_updated', current_time('mysql'));

        // グローバルプランに追加
        $this->global_plans = array_merge($this->global_plans, $plans);
    }

    /**
     * グローバルデータ保存
     */
    private function save_global_data()
    {
        // 重複除去
        $unique_news = [];
        $news_urls = [];
        foreach ($this->global_news as $item) {
            $original = $item['original_url'] ?? $item['url'];
            if (!in_array($original, $news_urls)) {
                $unique_news[] = $item;
                $news_urls[] = $original;
            }
        }

        $unique_plans = [];
        $plan_urls = [];
        foreach ($this->global_plans as $item) {
            $original = $item['original_url'] ?? $item['url'];
            if (!in_array($original, $plan_urls)) {
                $unique_plans[] = $item;
                $plan_urls[] = $original;
            }
        }

        // 日付で降順ソート
        usort($unique_news, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        usort($unique_plans, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        // 最新50件のみ保持
        $unique_news = array_slice($unique_news, 0, 50);
        $unique_plans = array_slice($unique_plans, 0, 50);

        // wp_optionsに保存
        update_option('hrs_global_news_items', $unique_news);
        update_option('hrs_global_plan_items', $unique_plans);

        $this->log("グローバルデータ保存: ニュース" . count($unique_news) . "件, プラン" . count($unique_plans) . "件");
    }

    /**
     * トップページ用：最新ニュース取得（グローバル）
     */
    public static function get_latest_news($limit = 10)
    {
        $news = get_option('hrs_global_news_items', []);

        if (!is_array($news)) {
            return [];
        }

        // 日付で降順ソート（念のため）
        usort($news, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        return array_slice($news, 0, $limit);
    }

    /**
     * トップページ用：最新プラン取得（グローバル）
     */
    public static function get_latest_plans($limit = 10)
    {
        $plans = get_option('hrs_global_plan_items', []);

        if (!is_array($plans)) {
            return [];
        }

        // 日付で降順ソート（念のため）
        usort($plans, function ($a, $b) {
            return strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0);
        });

        return array_slice($plans, 0, $limit);
    }

    /**
     * 手動更新Ajax
     */
    public function ajax_manual_update()
    {
        check_ajax_referer('hrs_news_update_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $result = $this->run_weekly_update();

        if ($result['success']) {
            $results = $result['results'];
            wp_send_json_success([
                'message' => sprintf(
                    '更新完了: 記事%d件更新, ニュース%d件, プラン%d件を取得しました',
                    $results['updated'],
                    $results['news_found'],
                    $results['plans_found']
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
    private function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS News Updater] ' . $message);
        }
    }
}

// 初期化
add_action('plugins_loaded', function () {
    HRS_News_Plan_Updater::get_instance();
});