<?php
/**
 * HRS API Settings Extension
 * パフォーマンス管理画面のAPI設定タブ機能
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_API_Settings_Extension {
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('wp_ajax_hrs_save_api_settings', array($this, 'handle_save_api_settings'));
        add_action('wp_ajax_hrs_test_ga4_connection', array($this, 'handle_test_ga4_connection'));
        add_action('wp_ajax_hrs_test_gsc_connection', array($this, 'handle_test_gsc_connection'));
        add_action('wp_ajax_hrs_manual_sync', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_hrs_toggle_auto_sync', array($this, 'handle_toggle_auto_sync'));
    }
    
    /**
     * API設定タブを描画
     */
    public static function render_api_settings_tab() {
        // GA4クライアント状態取得
        $ga4_status = array('service_account_configured' => false, 'property_id' => '', 'property_id_configured' => false, 'last_sync' => '', 'sync_status' => '');
        if (class_exists('HRS_GA4_API_Client')) {
            $ga4_client = new HRS_GA4_API_Client();
            $ga4_status = $ga4_client->get_status();
        }
        
        // GSCクライアント状態取得
        $gsc_status = array('site_url' => home_url('/'), 'site_url_configured' => false, 'last_sync' => '', 'sync_status' => '');
        if (class_exists('HRS_GSC_API_Client')) {
            $gsc_client = new HRS_GSC_API_Client();
            $gsc_status = $gsc_client->get_status();
        }
        
        // スケジューラー状態取得
        $schedule_status = array('enabled' => false, 'time' => '03:00', 'next_run' => null, 'next_run_human' => '');
        $sync_log = array();
        if (class_exists('HRS_API_Scheduler')) {
            $scheduler = new HRS_API_Scheduler();
            $schedule_status = $scheduler->get_schedule_status();
            $sync_log = $scheduler->get_sync_log(5);
        }
        
        ?>
        <div class="hrs-api-settings">
            <!-- サービスアカウント設定 -->
            <div class="hrs-section">
                <h2>🔑 サービスアカウント設定</h2>
                <p class="description">Google Cloud Platformで作成したサービスアカウントのJSONキーを設定します。GA4とSearch Consoleで共通で使用します。</p>
                
                <div class="hrs-status-badge <?php echo $ga4_status['service_account_configured'] ? 'connected' : 'disconnected'; ?>">
                    <?php echo $ga4_status['service_account_configured'] ? '✅ 設定済み' : '❌ 未設定'; ?>
                </div>
                
                <form id="hrs-service-account-form" class="hrs-api-form">
                    <input type="hidden" name="action" value="hrs_save_api_settings">
                    <input type="hidden" name="setting_type" value="service_account">
                    <?php wp_nonce_field('hrs_api_settings', 'hrs_api_nonce'); ?>
                    
                    <div class="hrs-form-row">
                        <label>サービスアカウントJSON:</label>
                        <textarea name="service_account_json" rows="6" placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
                        <p class="description">GCPコンソール → IAM → サービスアカウント → キー作成 からダウンロードしたJSONの内容を貼り付け</p>
                    </div>
                    
                    <button type="submit" class="button button-primary">保存</button>
                </form>
                
                <div id="hrs-service-account-result" class="hrs-api-result"></div>
                
                <div class="hrs-setup-guide" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                    <h4 style="margin-top: 0;">📖 設定手順</h4>
                    <ol style="margin-bottom: 0;">
                        <li><a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> でプロジェクトを作成</li>
                        <li>「APIとサービス」→「ライブラリ」で以下を有効化:
                            <ul>
                                <li>Google Analytics Data API</li>
                                <li>Google Search Console API</li>
                            </ul>
                        </li>
                        <li>「IAM と管理」→「サービスアカウント」で新規作成</li>
                        <li>作成したアカウントの「キー」タブでJSONキーを作成・ダウンロード</li>
                        <li>GA4管理画面でサービスアカウントのメールアドレスを「閲覧者」として追加</li>
                        <li>Search Consoleでサービスアカウントのメールアドレスを「フル」権限で追加</li>
                    </ol>
                </div>
            </div>
            
            <!-- GA4設定 -->
            <div class="hrs-section">
                <h2>📊 GA4 設定</h2>
                
                <div class="hrs-status-badge <?php echo $ga4_status['property_id_configured'] ? 'connected' : 'disconnected'; ?>">
                    <?php echo $ga4_status['property_id_configured'] ? '✅ 設定済み' : '❌ 未設定'; ?>
                </div>
                
                <form id="hrs-ga4-form" class="hrs-api-form">
                    <input type="hidden" name="action" value="hrs_save_api_settings">
                    <input type="hidden" name="setting_type" value="ga4">
                    <?php wp_nonce_field('hrs_api_settings', 'hrs_api_nonce_ga4'); ?>
                    
                    <div class="hrs-form-row">
                        <label>プロパティID:</label>
                        <input type="text" name="property_id" value="<?php echo esc_attr($ga4_status['property_id']); ?>" placeholder="123456789">
                        <p class="description">GA4 管理画面 → プロパティ設定 → プロパティID（数字のみ）</p>
                    </div>
                    
                    <div class="hrs-form-row">
                        <label>取得期間:</label>
                        <select name="fetch_days">
                            <option value="7" <?php selected(get_option('hrs_ga4_fetch_days', 7), 7); ?>>過去7日間</option>
                            <option value="14" <?php selected(get_option('hrs_ga4_fetch_days', 7), 14); ?>>過去14日間</option>
                            <option value="30" <?php selected(get_option('hrs_ga4_fetch_days', 7), 30); ?>>過去30日間</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button button-primary">保存</button>
                    <button type="button" id="hrs-test-ga4" class="button">接続テスト</button>
                </form>
                
                <div id="hrs-ga4-result" class="hrs-api-result"></div>
                
                <?php if ($ga4_status['last_sync']) : ?>
                <p class="hrs-last-sync">最終同期: <?php echo esc_html($ga4_status['last_sync']); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Search Console設定 -->
            <div class="hrs-section">
                <h2>🔍 Search Console 設定</h2>
                
                <div class="hrs-status-badge <?php echo $gsc_status['site_url_configured'] ? 'connected' : 'disconnected'; ?>">
                    <?php echo $gsc_status['site_url_configured'] ? '✅ 設定済み' : '❌ 未設定'; ?>
                </div>
                
                <form id="hrs-gsc-form" class="hrs-api-form">
                    <input type="hidden" name="action" value="hrs_save_api_settings">
                    <input type="hidden" name="setting_type" value="gsc">
                    <?php wp_nonce_field('hrs_api_settings', 'hrs_api_nonce_gsc'); ?>
                    
                    <div class="hrs-form-row">
                        <label>サイトURL:</label>
                        <input type="url" name="site_url" value="<?php echo esc_attr($gsc_status['site_url']); ?>" placeholder="https://example.com/">
                        <p class="description">Search Console に登録しているプロパティURLと完全一致させてください</p>
                    </div>
                    
                    <div class="hrs-form-row">
                        <label>取得期間:</label>
                        <select name="fetch_days">
                            <option value="7" <?php selected(get_option('hrs_gsc_fetch_days', 7), 7); ?>>過去7日間</option>
                            <option value="14" <?php selected(get_option('hrs_gsc_fetch_days', 7), 14); ?>>過去14日間</option>
                            <option value="30" <?php selected(get_option('hrs_gsc_fetch_days', 7), 30); ?>>過去30日間</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button button-primary">保存</button>
                    <button type="button" id="hrs-test-gsc" class="button">接続テスト</button>
                </form>
                
                <div id="hrs-gsc-result" class="hrs-api-result"></div>
                
                <?php if (!empty($gsc_status['last_sync'])) : ?>
                <p class="hrs-last-sync">最終同期: <?php echo esc_html($gsc_status['last_sync']); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- 同期スケジュール -->
            <div class="hrs-section">
                <h2>⏰ 自動同期スケジュール</h2>
                <p class="description">毎日指定した時刻にAPI経由でデータを自動取得します。</p>
                
                <div class="hrs-schedule-toggle">
                    <label class="hrs-toggle-switch">
                        <input type="checkbox" id="hrs-auto-sync-toggle" <?php checked($schedule_status['enabled']); ?>>
                        <span class="slider round"></span>
                    </label>
                    <span class="toggle-label">自動同期 <strong><?php echo $schedule_status['enabled'] ? 'ON' : 'OFF'; ?></strong></span>
                </div>
                
                <form id="hrs-schedule-form" class="hrs-api-form" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="hrs_save_api_settings">
                    <input type="hidden" name="setting_type" value="schedule">
                    <?php wp_nonce_field('hrs_api_settings', 'hrs_api_nonce_schedule'); ?>
                    
                    <div class="hrs-form-row">
                        <label>実行時刻:</label>
                        <input type="time" name="sync_time" value="<?php echo esc_attr($schedule_status['time']); ?>">
                        <p class="description">サーバー負荷の少ない深夜帯がおすすめです</p>
                    </div>
                    
                    <button type="submit" class="button">時刻を保存</button>
                </form>
                
                <div id="hrs-schedule-result" class="hrs-api-result"></div>
                
                <?php if ($schedule_status['next_run']) : ?>
                <p class="hrs-next-run">次回実行予定: <?php echo esc_html($schedule_status['next_run']); ?> (<?php echo esc_html($schedule_status['next_run_human']); ?>)</p>
                <?php endif; ?>
            </div>
            
            <!-- 手動実行 -->
            <div class="hrs-section">
                <h2>🔄 手動同期</h2>
                <p class="description">今すぐAPI経由でデータを取得します。</p>
                
                <button type="button" id="hrs-manual-sync" class="button button-primary button-hero">
                    <span class="dashicons dashicons-update" style="margin-top: 4px;"></span> 今すぐ同期
                </button>
                
                <div id="hrs-sync-progress" class="hrs-sync-progress" style="display: none; margin-top: 15px;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <span class="progress-text">同期中... しばらくお待ちください</span>
                </div>
                
                <div id="hrs-manual-sync-result" class="hrs-api-result"></div>
            </div>
            
            <!-- 同期ログ -->
            <div class="hrs-section">
                <h2>📋 同期履歴</h2>
                <?php if (empty($sync_log)) : ?>
                    <p>同期履歴はありません。</p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>実行日時</th>
                                <th>GA4</th>
                                <th>GSC</th>
                                <th>更新記事</th>
                                <th>ステータス</th>
                                <th>処理時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sync_log as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log['started_at'] ?? '-'); ?></td>
                                <td>
                                    <?php if (isset($log['ga4']['success']) && $log['ga4']['success']) : ?>
                                        <span style="color: #28a745;">✅ <?php echo intval($log['ga4']['count']); ?>件</span>
                                    <?php else : ?>
                                        <span style="color: #dc3545;">❌ エラー</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($log['gsc']['success']) && $log['gsc']['success']) : ?>
                                        <span style="color: #28a745;">✅ <?php echo intval($log['gsc']['count']); ?>件</span>
                                    <?php else : ?>
                                        <span style="color: #dc3545;">❌ エラー</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($log['scores_updated'] ?? 0); ?>件</td>
                                <td>
                                    <?php 
                                    $status = $log['status'] ?? 'unknown';
                                    if ($status === 'success') {
                                        echo '<span style="color: #28a745;">✅ 成功</span>';
                                    } elseif ($status === 'partial') {
                                        echo '<span style="color: #ffc107;">⚠️ 一部成功</span>';
                                    } else {
                                        echo '<span style="color: #dc3545;">❌ エラー</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo isset($log['total_time']) ? $log['total_time'] . '秒' : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .hrs-api-settings .hrs-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .hrs-api-settings .hrs-section h2 { margin-top: 0; }
        .hrs-api-form .hrs-form-row { margin-bottom: 15px; }
        .hrs-api-form .hrs-form-row label { display: block; font-weight: bold; margin-bottom: 5px; }
        .hrs-api-form .hrs-form-row input[type="text"],
        .hrs-api-form .hrs-form-row input[type="url"],
        .hrs-api-form .hrs-form-row textarea { width: 100%; max-width: 500px; }
        .hrs-api-form .hrs-form-row select { min-width: 200px; }
        .hrs-api-result { margin-top: 15px; padding: 10px 15px; border-radius: 4px; display: none; }
        .hrs-api-result.success { background: #d4edda; color: #155724; display: block; }
        .hrs-api-result.error { background: #f8d7da; color: #721c24; display: block; }
        .hrs-status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 13px; margin-bottom: 15px; }
        .hrs-status-badge.connected { background: #d4edda; color: #155724; }
        .hrs-status-badge.disconnected { background: #f8d7da; color: #721c24; }
        .hrs-last-sync, .hrs-next-run { color: #666; font-size: 13px; margin-top: 10px; }
        .hrs-toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .hrs-toggle-switch input { opacity: 0; width: 0; height: 0; }
        .hrs-toggle-switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
        .hrs-toggle-switch .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
        .hrs-toggle-switch input:checked + .slider { background-color: #2271b1; }
        .hrs-toggle-switch input:checked + .slider:before { transform: translateX(24px); }
        .hrs-toggle-switch .slider.round { border-radius: 26px; }
        .hrs-toggle-switch .slider.round:before { border-radius: 50%; }
        .hrs-schedule-toggle { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .hrs-schedule-toggle .toggle-label { font-size: 14px; }
        .hrs-sync-progress { background: #f0f6fc; padding: 15px; border-radius: 4px; }
        .button-hero { padding: 10px 20px !important; height: auto !important; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var apiNonce = '<?php echo wp_create_nonce('hrs_api_settings'); ?>';
            
            // フォーム送信共通処理
            function submitApiForm($form, $result) {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: $form.serialize() + '&nonce=' + apiNonce,
                    success: function(response) {
                        $result.removeClass('success error').addClass(response.success ? 'success' : 'error').text(response.data.message).show();
                        if (response.success && response.data.reload) {
                            setTimeout(function() { location.reload(); }, 1500);
                        }
                    },
                    error: function() {
                        $result.removeClass('success').addClass('error').text('エラーが発生しました').show();
                    }
                });
            }
            
            // サービスアカウント保存
            $('#hrs-service-account-form').on('submit', function(e) {
                e.preventDefault();
                submitApiForm($(this), $('#hrs-service-account-result'));
            });
            
            // GA4設定保存
            $('#hrs-ga4-form').on('submit', function(e) {
                e.preventDefault();
                submitApiForm($(this), $('#hrs-ga4-result'));
            });
            
            // GSC設定保存
            $('#hrs-gsc-form').on('submit', function(e) {
                e.preventDefault();
                submitApiForm($(this), $('#hrs-gsc-result'));
            });
            
            // スケジュール設定保存
            $('#hrs-schedule-form').on('submit', function(e) {
                e.preventDefault();
                submitApiForm($(this), $('#hrs-schedule-result'));
            });
            
            // GA4接続テスト
            $('#hrs-test-ga4').on('click', function() {
                var $result = $('#hrs-ga4-result');
                $result.removeClass('success error').text('テスト中...').show();
                
                $.post(ajaxurl, { action: 'hrs_test_ga4_connection', nonce: apiNonce }, function(response) {
                    $result.removeClass('success error').addClass(response.success ? 'success' : 'error').text(response.data.message);
                });
            });
            
            // GSC接続テスト
            $('#hrs-test-gsc').on('click', function() {
                var $result = $('#hrs-gsc-result');
                $result.removeClass('success error').text('テスト中...').show();
                
                $.post(ajaxurl, { action: 'hrs_test_gsc_connection', nonce: apiNonce }, function(response) {
                    $result.removeClass('success error').addClass(response.success ? 'success' : 'error').text(response.data.message);
                });
            });
            
            // 自動同期トグル
            $('#hrs-auto-sync-toggle').on('change', function() {
                var enabled = $(this).is(':checked');
                $.post(ajaxurl, { action: 'hrs_toggle_auto_sync', nonce: apiNonce, enabled: enabled ? 1 : 0 }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
            
            // 手動同期
            $('#hrs-manual-sync').on('click', function() {
                var $btn = $(this);
                var $progress = $('#hrs-sync-progress');
                var $result = $('#hrs-manual-sync-result');
                
                $btn.prop('disabled', true);
                $progress.show();
                $result.hide();
                
                $.post(ajaxurl, { action: 'hrs_manual_sync', nonce: apiNonce }, function(response) {
                    $btn.prop('disabled', false);
                    $progress.hide();
                    $result.removeClass('success error').addClass(response.success ? 'success' : 'error').html(response.data.message).show();
                    
                    if (response.success) {
                        setTimeout(function() { location.reload(); }, 2000);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $progress.hide();
                    $result.removeClass('success').addClass('error').text('同期に失敗しました').show();
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * API設定保存処理
     */
    public function handle_save_api_settings() {
        check_ajax_referer('hrs_api_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $setting_type = sanitize_text_field($_POST['setting_type'] ?? '');
        
        switch ($setting_type) {
            case 'service_account':
                $json = stripslashes($_POST['service_account_json'] ?? '');
                if (empty($json)) {
                    wp_send_json_error(array('message' => 'JSONを入力してください'));
                }
                
                if (!class_exists('HRS_GA4_API_Client')) {
                    wp_send_json_error(array('message' => 'GA4クライアントが利用できません'));
                }
                
                $client = new HRS_GA4_API_Client();
                if ($client->save_service_account($json)) {
                    wp_send_json_success(array('message' => 'サービスアカウントを保存しました', 'reload' => true));
                } else {
                    wp_send_json_error(array('message' => 'JSONの形式が不正です'));
                }
                break;
                
            case 'ga4':
                $property_id = sanitize_text_field($_POST['property_id'] ?? '');
                $fetch_days = intval($_POST['fetch_days'] ?? 7);
                
                if (!class_exists('HRS_GA4_API_Client')) {
                    wp_send_json_error(array('message' => 'GA4クライアントが利用できません'));
                }
                
                $client = new HRS_GA4_API_Client();
                if ($client->save_property_id($property_id)) {
                    update_option('hrs_ga4_fetch_days', $fetch_days);
                    wp_send_json_success(array('message' => 'GA4設定を保存しました'));
                } else {
                    wp_send_json_error(array('message' => 'プロパティIDが不正です'));
                }
                break;
                
            case 'gsc':
                $site_url = esc_url_raw($_POST['site_url'] ?? '');
                $fetch_days = intval($_POST['fetch_days'] ?? 7);
                
                if (!class_exists('HRS_GSC_API_Client')) {
                    wp_send_json_error(array('message' => 'GSCクライアントが利用できません'));
                }
                
                $client = new HRS_GSC_API_Client();
                if ($client->save_site_url($site_url)) {
                    update_option('hrs_gsc_fetch_days', $fetch_days);
                    wp_send_json_success(array('message' => 'Search Console設定を保存しました'));
                } else {
                    wp_send_json_error(array('message' => 'サイトURLが不正です'));
                }
                break;
                
            case 'schedule':
                $sync_time = sanitize_text_field($_POST['sync_time'] ?? '03:00');
                
                if (!class_exists('HRS_API_Scheduler')) {
                    wp_send_json_error(array('message' => 'スケジューラーが利用できません'));
                }
                
                $scheduler = new HRS_API_Scheduler();
                if ($scheduler->update_sync_time($sync_time)) {
                    wp_send_json_success(array('message' => '実行時刻を保存しました', 'reload' => true));
                } else {
                    wp_send_json_error(array('message' => '時刻の形式が不正です'));
                }
                break;
                
            default:
                wp_send_json_error(array('message' => '不明な設定タイプです'));
        }
    }
    
    /**
     * GA4接続テスト
     */
    public function handle_test_ga4_connection() {
        check_ajax_referer('hrs_api_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        if (!class_exists('HRS_GA4_API_Client')) {
            wp_send_json_error(array('message' => 'GA4クライアントが利用できません'));
        }
        
        $client = new HRS_GA4_API_Client();
        $result = $client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => '✅ ' . $result['message']));
        } else {
            wp_send_json_error(array('message' => '❌ ' . $result['message']));
        }
    }
    
    /**
     * GSC接続テスト
     */
    public function handle_test_gsc_connection() {
        check_ajax_referer('hrs_api_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        if (!class_exists('HRS_GSC_API_Client')) {
            wp_send_json_error(array('message' => 'GSCクライアントが利用できません'));
        }
        
        $client = new HRS_GSC_API_Client();
        $result = $client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => '✅ ' . $result['message']));
        } else {
            wp_send_json_error(array('message' => '❌ ' . $result['message']));
        }
    }
    
    /**
     * 手動同期実行
     */
    public function handle_manual_sync() {
        check_ajax_referer('hrs_api_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        if (!class_exists('HRS_API_Scheduler')) {
            wp_send_json_error(array('message' => 'スケジューラーが利用できません'));
        }
        
        $scheduler = new HRS_API_Scheduler();
        $result = $scheduler->run_manual_sync();
        
        $message = '同期完了: ';
        $message .= 'GA4 ' . ($result['ga4']['success'] ? $result['ga4']['count'] . '件' : 'エラー') . ' / ';
        $message .= 'GSC ' . ($result['gsc']['success'] ? $result['gsc']['count'] . '件' : 'エラー') . ' / ';
        $message .= 'スコア更新 ' . $result['scores_updated'] . '件';
        
        if ($result['status'] === 'success') {
            wp_send_json_success(array('message' => '✅ ' . $message));
        } elseif ($result['status'] === 'partial') {
            wp_send_json_success(array('message' => '⚠️ ' . $message));
        } else {
            wp_send_json_error(array('message' => '❌ ' . $message));
        }
    }
    
    /**
     * 自動同期トグル
     */
    public function handle_toggle_auto_sync() {
        check_ajax_referer('hrs_api_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        if (!class_exists('HRS_API_Scheduler')) {
            wp_send_json_error(array('message' => 'スケジューラーが利用できません'));
        }
        
        $enabled = !empty($_POST['enabled']);
        $scheduler = new HRS_API_Scheduler();
        
        if ($enabled) {
            $scheduler->schedule_daily_sync();
            wp_send_json_success(array('message' => '自動同期を有効にしました'));
        } else {
            $scheduler->unschedule_sync();
            wp_send_json_success(array('message' => '自動同期を無効にしました'));
        }
    }
}