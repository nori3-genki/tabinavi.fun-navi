<?php
/**
 * API連動設定管理画面
 * 
 * @package HRS
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_API_Settings_Page {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_hrs_bulk_update_prices', array($this, 'ajax_bulk_update_prices'));
        add_action('wp_ajax_hrs_test_rakuten_api', array($this, 'ajax_test_rakuten_api'));
    }

    /**
     * 設定ページをメニューに追加
     */
    public function add_settings_page() {
        add_submenu_page(
            'hrs-5d-review-builder',  // 親メニュースラッグ
            'API連動設定',
            'API連動設定',
            'manage_options',
            'hrs-api-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * 設定を登録
     */
    public function register_settings() {
        // === 楽天API設定 ===
        register_setting('hrs_api_settings', 'hrs_rakuten_app_id');
        register_setting('hrs_api_settings', 'hrs_rakuten_affiliate_id');
        
        // === 自動更新設定 ===
        register_setting('hrs_api_settings', 'hrs_price_auto_update', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('hrs_api_settings', 'hrs_price_update_interval', array(
            'type' => 'integer',
            'default' => 24,
        ));
        register_setting('hrs_api_settings', 'hrs_ranking_enabled', array(
            'type' => 'boolean',
            'default' => true,
        ));
        register_setting('hrs_api_settings', 'hrs_reviews_enabled', array(
            'type' => 'boolean',
            'default' => true,
        ));
        
        // === 表示設定 ===
        register_setting('hrs_api_settings', 'hrs_price_display_position', array(
            'type' => 'string',
            'default' => 'after_content',
        ));
        register_setting('hrs_api_settings', 'hrs_ranking_display_position', array(
            'type' => 'string',
            'default' => 'after_content',
        ));
        register_setting('hrs_api_settings', 'hrs_reviews_display_position', array(
            'type' => 'string',
            'default' => 'after_content',
        ));
        
        // === キャッシュ設定 ===
        register_setting('hrs_api_settings', 'hrs_api_cache_duration', array(
            'type' => 'integer',
            'default' => 24,
        ));
    }

    /**
     * 設定ページを描画
     */
    public function render_settings_page() {
        // 現在の設定値を取得
        $rakuten_app_id = get_option('hrs_rakuten_app_id', '');
        $rakuten_affiliate_id = get_option('hrs_rakuten_affiliate_id', '');
        $price_auto_update = get_option('hrs_price_auto_update', true);
        $price_update_interval = get_option('hrs_price_update_interval', 24);
        $ranking_enabled = get_option('hrs_ranking_enabled', true);
        $reviews_enabled = get_option('hrs_reviews_enabled', true);
        $price_display_position = get_option('hrs_price_display_position', 'after_content');
        $ranking_display_position = get_option('hrs_ranking_display_position', 'after_content');
        $reviews_display_position = get_option('hrs_reviews_display_position', 'after_content');
        $cache_duration = get_option('hrs_api_cache_duration', 24);
        
        // 統計情報を取得
        $stats = $this->get_update_stats();
        ?>
        <div class="wrap">
            <h1>🔌 API連動設定</h1>
            
            <!-- ステータスダッシュボード -->
            <div class="hrs-api-dashboard" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0;">
                <div class="hrs-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['total_articles']); ?></div>
                    <div style="color: #666;">API連動記事数</div>
                </div>
                <div class="hrs-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html($stats['updated_today']); ?></div>
                    <div style="color: #666;">本日更新済み</div>
                </div>
                <div class="hrs-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #dba617;"><?php echo esc_html($stats['needs_update']); ?></div>
                    <div style="color: #666;">要更新</div>
                </div>
                <div class="hrs-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html($stats['errors']); ?></div>
                    <div style="color: #666;">エラー</div>
                </div>
            </div>

            <!-- 一括操作ボタン -->
            <div class="hrs-bulk-actions" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;">⚡ 一括操作</h2>
                <p>全記事の価格・ランキング・口コミを一括で更新します。記事数が多い場合は時間がかかります。</p>
                <button type="button" id="hrs-bulk-update-prices" class="button button-primary button-hero">
                    🔄 全記事の価格を一括更新
                </button>
                <button type="button" id="hrs-clear-cache" class="button button-secondary" style="margin-left: 10px;">
                    🗑️ APIキャッシュをクリア
                </button>
                <div id="hrs-bulk-progress" style="display: none; margin-top: 15px;">
                    <div style="background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden;">
                        <div id="hrs-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="hrs-progress-text" style="margin-top: 5px;">処理中...</p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('hrs_api_settings'); ?>
                
                <!-- 楽天API設定 -->
                <div class="hrs-settings-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">🔑 楽天API設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="hrs_rakuten_app_id">アプリケーションID</label>
                            </th>
                            <td>
                                <input type="text" id="hrs_rakuten_app_id" name="hrs_rakuten_app_id" 
                                       value="<?php echo esc_attr($rakuten_app_id); ?>" class="regular-text">
                                <p class="description">
                                    <a href="https://webservice.rakuten.co.jp/" target="_blank">楽天ウェブサービス</a>で取得
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="hrs_rakuten_affiliate_id">アフィリエイトID</label>
                            </th>
                            <td>
                                <input type="text" id="hrs_rakuten_affiliate_id" name="hrs_rakuten_affiliate_id" 
                                       value="<?php echo esc_attr($rakuten_affiliate_id); ?>" class="regular-text">
                                <p class="description">楽天アフィリエイトIDを入力</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API接続テスト</th>
                            <td>
                                <button type="button" id="hrs-test-api" class="button">
                                    🔍 接続テスト
                                </button>
                                <span id="hrs-api-test-result" style="margin-left: 10px;"></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 自動更新設定 -->
                <div class="hrs-settings-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">🔄 自動更新設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">価格の自動更新</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hrs_price_auto_update" value="1" 
                                           <?php checked($price_auto_update, true); ?>>
                                    有効にする
                                </label>
                                <p class="description">WP-Cronで定期的に価格を更新します</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="hrs_price_update_interval">更新間隔</label>
                            </th>
                            <td>
                                <select id="hrs_price_update_interval" name="hrs_price_update_interval">
                                    <option value="6" <?php selected($price_update_interval, 6); ?>>6時間ごと</option>
                                    <option value="12" <?php selected($price_update_interval, 12); ?>>12時間ごと</option>
                                    <option value="24" <?php selected($price_update_interval, 24); ?>>24時間ごと（推奨）</option>
                                    <option value="48" <?php selected($price_update_interval, 48); ?>>48時間ごと</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ランキング表示</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hrs_ranking_enabled" value="1" 
                                           <?php checked($ranking_enabled, true); ?>>
                                    有効にする
                                </label>
                                <p class="description">エリアランキングを記事に自動挿入</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">口コミ表示</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hrs_reviews_enabled" value="1" 
                                           <?php checked($reviews_enabled, true); ?>>
                                    有効にする
                                </label>
                                <p class="description">楽天の口コミ・評価を記事に自動挿入</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 表示位置設定 -->
                <div class="hrs-settings-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">📍 表示位置設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">価格セクション</th>
                            <td>
                                <select name="hrs_price_display_position">
                                    <option value="before_content" <?php selected($price_display_position, 'before_content'); ?>>記事の先頭</option>
                                    <option value="after_first_h2" <?php selected($price_display_position, 'after_first_h2'); ?>>最初のH2の後</option>
                                    <option value="after_content" <?php selected($price_display_position, 'after_content'); ?>>記事の末尾</option>
                                    <option value="shortcode" <?php selected($price_display_position, 'shortcode'); ?>>ショートコードで指定</option>
                                </select>
                                <p class="description">ショートコード: <code>[hrs_price]</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">ランキングセクション</th>
                            <td>
                                <select name="hrs_ranking_display_position">
                                    <option value="before_content" <?php selected($ranking_display_position, 'before_content'); ?>>記事の先頭</option>
                                    <option value="after_content" <?php selected($ranking_display_position, 'after_content'); ?>>記事の末尾</option>
                                    <option value="shortcode" <?php selected($ranking_display_position, 'shortcode'); ?>>ショートコードで指定</option>
                                </select>
                                <p class="description">ショートコード: <code>[hrs_ranking]</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">口コミセクション</th>
                            <td>
                                <select name="hrs_reviews_display_position">
                                    <option value="before_content" <?php selected($reviews_display_position, 'before_content'); ?>>記事の先頭</option>
                                    <option value="after_content" <?php selected($reviews_display_position, 'after_content'); ?>>記事の末尾</option>
                                    <option value="shortcode" <?php selected($reviews_display_position, 'shortcode'); ?>>ショートコードで指定</option>
                                </select>
                                <p class="description">ショートコード: <code>[hrs_reviews]</code></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- キャッシュ設定 -->
                <div class="hrs-settings-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">💾 キャッシュ設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="hrs_api_cache_duration">キャッシュ有効時間</label>
                            </th>
                            <td>
                                <select id="hrs_api_cache_duration" name="hrs_api_cache_duration">
                                    <option value="1" <?php selected($cache_duration, 1); ?>>1時間</option>
                                    <option value="6" <?php selected($cache_duration, 6); ?>>6時間</option>
                                    <option value="12" <?php selected($cache_duration, 12); ?>>12時間</option>
                                    <option value="24" <?php selected($cache_duration, 24); ?>>24時間（推奨）</option>
                                </select>
                                <p class="description">APIレスポンスをキャッシュする時間。短いとAPI制限に達しやすくなります。</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('設定を保存'); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // API接続テスト
            $('#hrs-test-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#hrs-api-test-result');
                
                $btn.prop('disabled', true).text('テスト中...');
                $result.html('');
                
                $.post(ajaxurl, {
                    action: 'hrs_test_rakuten_api',
                    nonce: '<?php echo wp_create_nonce('hrs_api_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;">✅ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: #d63638;">❌ ' + response.data.message + '</span>');
                    }
                    $btn.prop('disabled', false).text('🔍 接続テスト');
                });
            });

            // 一括更新
            $('#hrs-bulk-update-prices').on('click', function() {
                if (!confirm('全記事の価格を更新します。この処理には時間がかかる場合があります。続行しますか？')) {
                    return;
                }
                
                var $btn = $(this);
                var $progress = $('#hrs-bulk-progress');
                var $bar = $('#hrs-progress-bar');
                var $text = $('#hrs-progress-text');
                
                $btn.prop('disabled', true);
                $progress.show();
                
                $.post(ajaxurl, {
                    action: 'hrs_bulk_update_prices',
                    nonce: '<?php echo wp_create_nonce('hrs_api_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $bar.css('width', '100%');
                        $text.html('✅ ' + response.data.message);
                    } else {
                        $text.html('❌ ' + response.data.message);
                    }
                    $btn.prop('disabled', false);
                });
            });

            // キャッシュクリア
            $('#hrs-clear-cache').on('click', function() {
                if (!confirm('APIキャッシュをクリアしますか？')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('クリア中...');
                
                $.post(ajaxurl, {
                    action: 'hrs_clear_api_cache',
                    nonce: '<?php echo wp_create_nonce('hrs_api_nonce'); ?>'
                }, function(response) {
                    alert(response.success ? 'キャッシュをクリアしました' : 'エラーが発生しました');
                    $btn.prop('disabled', false).text('🗑️ APIキャッシュをクリア');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 更新統計を取得
     */
    private function get_update_stats() {
        global $wpdb;
        
        $stats = array(
            'total_articles' => 0,
            'updated_today' => 0,
            'needs_update' => 0,
            'errors' => 0,
        );
        
        // API連動記事数（楽天ホテルIDが設定されている記事）
        $stats['total_articles'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_hrs_rakuten_hotel_id' AND meta_value != ''"
        );
        
        // 本日更新済み
        $today_start = date('Y-m-d 00:00:00');
        $stats['updated_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_hrs_price_last_updated' AND meta_value >= %s",
            $today_start
        ));
        
        // 要更新（24時間以上経過）
        $threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stats['needs_update'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
             INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             WHERE pm1.meta_key = '_hrs_rakuten_hotel_id' AND pm1.meta_value != ''
             AND pm2.meta_key = '_hrs_price_last_updated' AND pm2.meta_value < %s",
            $threshold
        ));
        
        // エラー数
        $stats['errors'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_hrs_api_error' AND meta_value != ''"
        );
        
        return $stats;
    }

    /**
     * AJAX: 一括価格更新
     */
    public function ajax_bulk_update_prices() {
        check_ajax_referer('hrs_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        // 楽天ホテルIDが設定されている記事を取得
        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_hrs_rakuten_hotel_id' AND meta_value != ''"
        );
        
        if (empty($post_ids)) {
            wp_send_json_error(array('message' => '更新対象の記事がありません'));
        }
        
        $updated = 0;
        $errors = 0;
        
        if (class_exists('HRS_Rakuten_Price_Updater')) {
            $updater = HRS_Rakuten_Price_Updater::get_instance();
            
            foreach ($post_ids as $post_id) {
                $result = $updater->update_price($post_id);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                }
                
                // API制限対策：少し待機
                usleep(500000); // 0.5秒
            }
        }
        
        wp_send_json_success(array(
            'message' => "{$updated}件更新完了、{$errors}件エラー"
        ));
    }

    /**
     * AJAX: API接続テスト
     */
    public function ajax_test_rakuten_api() {
        check_ajax_referer('hrs_api_nonce', 'nonce');
        
        $app_id = get_option('hrs_rakuten_app_id', '');
        
        if (empty($app_id)) {
            wp_send_json_error(array('message' => 'アプリケーションIDが設定されていません'));
        }
        
        // テストリクエスト
        $url = 'https://app.rakuten.co.jp/services/api/Travel/SimpleHotelSearch/20170426';
        $url .= '?format=json&applicationId=' . urlencode($app_id);
        $url .= '&largeClassCode=japan&middleClassCode=akita&smallClassCode=katagami&hits=1';
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => '接続エラー: ' . $response->get_error_message()));
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            wp_send_json_success(array('message' => '接続成功！APIは正常に動作しています。'));
        } else {
            wp_send_json_error(array('message' => "APIエラー (コード: {$code})"));
        }
    }
}

// 初期化
HRS_API_Settings_Page::get_instance();