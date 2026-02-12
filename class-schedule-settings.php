<?php
/**
 * スケジュール自動生成設定
 *
 * @package 5D_Review_Builder
 * @version 1.1.0
 * 
 * CHANGELOG v1.1.0 (2026-02-10):
 * - ★ ajax_clear_generation_log() ハンドラー追加（履歴クリア修正）
 * - ★ wp_ajax_hrs_clear_generation_log アクション登録追加
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_Schedule_Settings {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 30);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_hrs_test_schedule', array($this, 'ajax_test_schedule'));
        add_action('wp_ajax_hrs_save_hotel_list', array($this, 'ajax_save_hotel_list'));

        // ★★★ 履歴クリア用AJAXハンドラー追加 ★★★
        add_action('wp_ajax_hrs_clear_generation_log', array($this, 'ajax_clear_generation_log'));

        // Cronイベント登録
        add_action('hrs_scheduled_generation', array($this, 'run_scheduled_generation'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    /**
     * Cronインターバル追加
     */
    public function add_cron_intervals($schedules) {
        $schedules['hrs_every_6_hours'] = array(
            'interval' => 21600,
            'display'  => __('6時間ごと', '5d-review-builder'),
        );
        $schedules['hrs_every_12_hours'] = array(
            'interval' => 43200,
            'display'  => __('12時間ごと', '5d-review-builder'),
        );
        return $schedules;
    }

    /**
     * メニュー追加
     */
    public function add_menu() {
        add_submenu_page(
            '5d-review-builder',
            'スケジュール生成',
            '⏰ スケジュール生成',
            'manage_options',
            'hrs-schedule-settings',
            array($this, 'render_page')
        );
    }

    /**
     * 設定登録
     */
    public function register_settings() {
        register_setting('hrs_schedule_settings', 'hrs_schedule_enabled');
        register_setting('hrs_schedule_settings', 'hrs_schedule_frequency');
        register_setting('hrs_schedule_settings', 'hrs_schedule_time');
        register_setting('hrs_schedule_settings', 'hrs_schedule_max_per_day');
        register_setting('hrs_schedule_settings', 'hrs_schedule_post_status');
        register_setting('hrs_schedule_settings', 'hrs_schedule_hotel_list');
        register_setting('hrs_schedule_settings', 'hrs_schedule_min_hqc');

        // 新規追加（生成設定）
        register_setting('hrs_schedule_settings', 'hrs_schedule_persona');
        register_setting('hrs_schedule_settings', 'hrs_schedule_purpose');
        register_setting('hrs_schedule_settings', 'hrs_schedule_depth');
        register_setting('hrs_schedule_settings', 'hrs_schedule_tone');
        register_setting('hrs_schedule_settings', 'hrs_schedule_structure');
    }

    /**
     * 設定ページ表示
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }

        // 設定保存処理
        if (isset($_POST['hrs_schedule_save']) && check_admin_referer('hrs_schedule_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
        }

        // 現在の設定取得
        $enabled     = get_option('hrs_schedule_enabled', 0);
        $frequency   = get_option('hrs_schedule_frequency', 'daily');
        $time        = get_option('hrs_schedule_time', '03:00');
        $max_per_day = get_option('hrs_schedule_max_per_day', 3);
        $post_status = get_option('hrs_schedule_post_status', 'draft');
        $hotel_list  = get_option('hrs_schedule_hotel_list', '');
        $min_hqc     = get_option('hrs_schedule_min_hqc', 50);

        // 新規追加の設定
        $persona     = get_option('hrs_schedule_persona', 'general');
        $purpose     = get_option('hrs_schedule_purpose', 'sightseeing');
        $depth       = get_option('hrs_schedule_depth', 'L2');
        $tone        = get_option('hrs_schedule_tone', 'journalistic');
        $structure   = get_option('hrs_schedule_structure', 'review');

        // 次回実行予定
        $next_scheduled = wp_next_scheduled('hrs_scheduled_generation');

        // 生成履歴
        $generation_log = get_option('hrs_generation_log', array());
        ?>

        <div class="wrap hrs-schedule-settings">
            <h1>⏰ スケジュール自動生成</h1>
            <p class="description">指定した時間に自動で記事を生成します</p>

            <form method="post" action="">
                <?php wp_nonce_field('hrs_schedule_nonce'); ?>

                <div class="hrs-settings-grid">

                    <!-- 基本設定 -->
                    <div class="hrs-card">
                        <h2>📋 基本設定</h2>
                        <table class="form-table">
                            <tr>
                                <th>自動生成</th>
                                <td>
                                    <label class="hrs-switch">
                                        <input type="checkbox" name="hrs_schedule_enabled" value="1" <?php checked($enabled, 1); ?>>
                                        <span class="hrs-slider"></span>
                                    </label>
                                    <span class="description">有効にすると自動生成が開始されます</span>
                                </td>
                            </tr>
                            <tr>
                                <th>実行頻度</th>
                                <td>
                                    <select name="hrs_schedule_frequency">
                                        <option value="hourly" <?php selected($frequency, 'hourly'); ?>>1時間ごと</option>
                                        <option value="hrs_every_6_hours" <?php selected($frequency, 'hrs_every_6_hours'); ?>>6時間ごと</option>
                                        <option value="hrs_every_12_hours" <?php selected($frequency, 'hrs_every_12_hours'); ?>>12時間ごと</option>
                                        <option value="daily" <?php selected($frequency, 'daily'); ?>>1日1回</option>
                                        <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>>1日2回</option>
                                        <option value="weekly" <?php selected($frequency, 'weekly'); ?>>週1回</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>開始時刻</th>
                                <td>
                                    <input type="time" name="hrs_schedule_time" value="<?php echo esc_attr($time); ?>">
                                    <p class="description">サーバー時刻基準（現在: <?php echo current_time('H:i'); ?>）</p>
                                </td>
                            </tr>
                            <tr>
                                <th>1日の最大生成数</th>
                                <td>
                                    <input type="number" name="hrs_schedule_max_per_day" value="<?php echo esc_attr($max_per_day); ?>" min="1" max="20">
                                    <p class="description">API制限を考慮して設定してください</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- 生成設定（拡張版） -->
                    <div class="hrs-card">
                        <h2>⚙️ 生成設定</h2>
                        <table class="form-table">
                            <tr>
                                <th>投稿ステータス</th>
                                <td>
                                    <select name="hrs_schedule_post_status">
                                        <option value="draft" <?php selected($post_status, 'draft'); ?>>下書き</option>
                                        <option value="publish" <?php selected($post_status, 'publish'); ?>>公開</option>
                                        <option value="pending" <?php selected($post_status, 'pending'); ?>>レビュー待ち</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>ペルソナ</th>
                                <td>
                                    <select name="hrs_schedule_persona">
                                        <option value="general" <?php selected($persona, 'general'); ?>>一般・観光</option>
                                        <option value="solo" <?php selected($persona, 'solo'); ?>>一人旅</option>
                                        <option value="couple" <?php selected($persona, 'couple'); ?>>カップル・夫婦</option>
                                        <option value="family" <?php selected($persona, 'family'); ?>>ファミリー</option>
                                        <option value="senior" <?php selected($persona, 'senior'); ?>>シニア</option>
                                        <option value="workation" <?php selected($persona, 'workation'); ?>>ワーケーション</option>
                                        <option value="luxury" <?php selected($persona, 'luxury'); ?>>ラグジュアリー</option>
                                        <option value="budget" <?php selected($persona, 'budget'); ?>>コスパ重視</option>
                                        <option value="random" <?php selected($persona, 'random'); ?>>ランダム</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>旅の目的</th>
                                <td>
                                    <select name="hrs_schedule_purpose">
                                        <option value="sightseeing" <?php selected($purpose, 'sightseeing'); ?>>観光・周遊</option>
                                        <option value="onsen" <?php selected($purpose, 'onsen'); ?>>温泉</option>
                                        <option value="gourmet" <?php selected($purpose, 'gourmet'); ?>>グルメ</option>
                                        <option value="anniversary" <?php selected($purpose, 'anniversary'); ?>>記念日</option>
                                        <option value="workation" <?php selected($purpose, 'workation'); ?>>ワーケーション</option>
                                        <option value="relaxation" <?php selected($purpose, 'relaxation'); ?>>癒し・リラックス</option>
                                        <option value="family_trip" <?php selected($purpose, 'family_trip'); ?>>家族旅行</option>
                                        <option value="budget_trip" <?php selected($purpose, 'budget_trip'); ?>>節約旅行</option>
                                        <option value="random" <?php selected($purpose, 'random'); ?>>ランダム</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>情報深度</th>
                                <td>
                                    <select name="hrs_schedule_depth">
                                        <option value="L1" <?php selected($depth, 'L1'); ?>>L1 - 概要</option>
                                        <option value="L2" <?php selected($depth, 'L2'); ?>>L2 - 標準</option>
                                        <option value="L3" <?php selected($depth, 'L3'); ?>>L3 - 詳細</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>トーン</th>
                                <td>
                                    <select name="hrs_schedule_tone">
                                        <option value="journalistic" <?php selected($tone, 'journalistic'); ?>>ジャーナリスティック - 客観的で情報重視</option>
                                        <option value="casual" <?php selected($tone, 'casual'); ?>>カジュアル - 親しみやすい</option>
                                        <option value="luxury" <?php selected($tone, 'luxury'); ?>>ラグジュアリー - 高級感</option>
                                        <option value="emotional" <?php selected($tone, 'emotional'); ?>>エモーショナル - 感情的</option>
                                        <option value="random" <?php selected($tone, 'random'); ?>>ランダム</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>構造</th>
                                <td>
                                    <select name="hrs_schedule_structure">
                                        <option value="review" <?php selected($structure, 'review'); ?>>レビュー形式 - 評価ポイント別</option>
                                        <option value="story" <?php selected($structure, 'story'); ?>>ストーリー形式 - 時系列</option>
                                        <option value="guide" <?php selected($structure, 'guide'); ?>>ガイド形式 - 情報整理型</option>
                                        <option value="random" <?php selected($structure, 'random'); ?>>ランダム</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>最低HQCスコア</th>
                                <td>
                                    <input type="number" name="hrs_schedule_min_hqc" value="<?php echo esc_attr($min_hqc); ?>" min="0" max="100">
                                    <p class="description">この点数未満は下書き保存</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ホテルリスト -->
                    <div class="hrs-card hrs-card-full">
                        <h2>🏨 生成対象ホテルリスト</h2>
                        <p class="description">1行に1ホテル。形式: ホテル名, 所在地（所在地は省略可）</p>
                        <textarea name="hrs_schedule_hotel_list" rows="10" class="large-text code"><?php echo esc_textarea($hotel_list); ?></textarea>
                        <div class="hrs-hotel-list-actions">
                            <span class="hrs-hotel-count">
                                登録ホテル数: <strong><?php echo count(array_filter(explode("\n", $hotel_list))); ?></strong>件
                            </span>
                            <button type="button" id="hrs-import-csv" class="button">CSVインポート</button>
                            <input type="file" id="hrs-csv-file" accept=".csv" style="display:none;">
                        </div>
                    </div>

                    <!-- ステータス -->
                    <div class="hrs-card">
                        <h2>📊 ステータス</h2>
                        <div class="hrs-status-item">
                            <span class="label">現在の状態:</span>
                            <span class="value <?php echo $enabled ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $enabled ? '✅ 有効' : '⏸️ 停止中'; ?>
                            </span>
                        </div>
                        <div class="hrs-status-item">
                            <span class="label">次回実行予定:</span>
                            <span class="value">
                                <?php
                                if ($next_scheduled) {
                                    echo date_i18n('Y/m/d H:i:s', $next_scheduled + (9 * 3600));
                                } else {
                                    echo '未スケジュール';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="hrs-status-item">
                            <span class="label">本日の生成数:</span>
                            <span class="value">
                                <?php echo $this->get_today_count(); ?> / <?php echo $max_per_day; ?>件
                            </span>
                        </div>
                        <button type="button" id="hrs-test-run" class="button button-secondary">
                            🧪 テスト実行（1件）
                        </button>
                    </div>

                    <!-- 生成履歴 -->
                    <div class="hrs-card">
                        <h2>📜 最近の生成履歴</h2>
                        <?php if (empty($generation_log)): ?>
                            <p class="description">まだ生成履歴がありません</p>
                        <?php else: ?>
                            <ul class="hrs-log-list">
                                <?php
                                $recent_log = array_slice(array_reverse($generation_log), 0, 10);
                                foreach ($recent_log as $log):
                                ?>
                                    <li class="<?php echo $log['success'] ? 'success' : 'error'; ?>">
                                        <span class="time"><?php echo $log['time']; ?></span>
                                        <span class="hotel"><?php echo esc_html($log['hotel']); ?></span>
                                        <?php if ($log['success']): ?>
                                            <span class="score">HQC: <?php echo $log['hqc_score']; ?></span>
                                        <?php else: ?>
                                            <span class="error-msg"><?php echo esc_html($log['error']); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" id="hrs-clear-log" class="button button-link-delete">履歴をクリア</button>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit" name="hrs_schedule_save" class="button button-primary button-large" value="設定を保存">
                </p>
            </form>
        </div>

        <style>
        .hrs-schedule-settings { max-width: 1200px; }
        .hrs-settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
        .hrs-card { background: #fff; padding: 20px; border: 1px solid #e2e4e7; border-radius: 8px; }
        .hrs-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 16px; }
        .hrs-card-full { grid-column: 1 / -1; }
        .hrs-card .form-table th { padding: 10px 0; width: 140px; }
        .hrs-card .form-table td { padding: 10px 0; }

        /* スイッチ */
        .hrs-switch { position: relative; display: inline-block; width: 50px; height: 26px; vertical-align: middle; margin-right: 10px; }
        .hrs-switch input { opacity: 0; width: 0; height: 0; }
        .hrs-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px; }
        .hrs-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        .hrs-switch input:checked + .hrs-slider { background-color: #2196F3; }
        .hrs-switch input:checked + .hrs-slider:before { transform: translateX(24px); }

        /* ステータス */
        .hrs-status-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .hrs-status-item .label { color: #666; }
        .hrs-status-item .value { font-weight: 600; }
        .status-active { color: #46b450; }
        .status-inactive { color: #999; }

        /* ホテルリスト */
        .hrs-hotel-list-actions { margin-top: 10px; display: flex; align-items: center; gap: 15px; }
        .hrs-hotel-count { color: #666; }

        /* ログ */
        .hrs-log-list { list-style: none; padding: 0; margin: 0; max-height: 300px; overflow-y: auto; }
        .hrs-log-list li { padding: 8px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 10px; font-size: 13px; }
        .hrs-log-list li.success { background: #f7fff7; }
        .hrs-log-list li.error { background: #fff7f7; }
        .hrs-log-list .time { color: #999; min-width: 80px; }
        .hrs-log-list .hotel { flex: 1; }
        .hrs-log-list .score { color: #46b450; }
        .hrs-log-list .error-msg { color: #dc3232; }

        @media (max-width: 782px) {
            .hrs-settings-grid { grid-template-columns: 1fr; }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // テスト実行
            $('#hrs-test-run').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('実行中...');

                $.post(ajaxurl, {
                    action: 'hrs_test_schedule',
                    nonce: '<?php echo wp_create_nonce('hrs_schedule_nonce'); ?>'
                }, function(res) {
                    if (res.success) {
                        alert('✅ テスト生成完了\n\nホテル: ' + res.data.hotel + '\nHQCスコア: ' + res.data.hqc_score);
                        location.reload();
                    } else {
                        alert('❌ エラー: ' + res.data.message);
                    }
                    $btn.prop('disabled', false).text('🧪 テスト実行（1件）');
                });
            });

            // CSVインポート
            $('#hrs-import-csv').on('click', function() {
                $('#hrs-csv-file').click();
            });
            $('#hrs-csv-file').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function(e) {
                    var content = e.target.result;
                    var lines = content.split('\n');
                    var hotelList = [];

                    lines.forEach(function(line) {
                        line = line.trim();
                        if (line && !line.startsWith('#')) {
                            hotelList.push(line);
                        }
                    });

                    var textarea = $('textarea[name="hrs_schedule_hotel_list"]');
                    var existing = textarea.val().trim();
                    if (existing) {
                        textarea.val(existing + '\n' + hotelList.join('\n'));
                    } else {
                        textarea.val(hotelList.join('\n'));
                    }

                    alert('✅ ' + hotelList.length + '件のホテルをインポートしました');
                };
                reader.readAsText(file);
            });

            // ★★★ 履歴クリア（修正版） ★★★
            $('#hrs-clear-log').on('click', function() {
                if (confirm('生成履歴をクリアしますか？')) {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('クリア中...');

                    $.post(ajaxurl, {
                        action: 'hrs_clear_generation_log',
                        nonce: '<?php echo wp_create_nonce('hrs_schedule_nonce'); ?>'
                    }, function(res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert('❌ エラー: ' + (res.data && res.data.message ? res.data.message : '不明なエラー'));
                            $btn.prop('disabled', false).text('履歴をクリア');
                        }
                    }).fail(function() {
                        alert('❌ 通信エラー');
                        $btn.prop('disabled', false).text('履歴をクリア');
                    });
                }
            });

            // ホテル数カウント更新
            $('textarea[name="hrs_schedule_hotel_list"]').on('input', function() {
                var lines = $(this).val().split('\n').filter(function(line) {
                    return line.trim() !== '';
                });
                $('.hrs-hotel-count strong').text(lines.length);
            });
        });
        </script>
        <?php
    }

    /**
     * 設定保存
     */
    private function save_settings() {
        $enabled     = isset($_POST['hrs_schedule_enabled']) ? 1 : 0;
        $frequency   = sanitize_text_field($_POST['hrs_schedule_frequency'] ?? 'daily');
        $time        = sanitize_text_field($_POST['hrs_schedule_time'] ?? '03:00');
        $max_per_day = intval($_POST['hrs_schedule_max_per_day'] ?? 3);
        $post_status = sanitize_text_field($_POST['hrs_schedule_post_status'] ?? 'draft');
        $hotel_list  = sanitize_textarea_field($_POST['hrs_schedule_hotel_list'] ?? '');
        $min_hqc     = intval($_POST['hrs_schedule_min_hqc'] ?? 50);

        // 新規追加の設定保存
        $persona     = sanitize_text_field($_POST['hrs_schedule_persona'] ?? 'general');
        $purpose     = sanitize_text_field($_POST['hrs_schedule_purpose'] ?? 'sightseeing');
        $depth       = sanitize_text_field($_POST['hrs_schedule_depth'] ?? 'L2');
        $tone        = sanitize_text_field($_POST['hrs_schedule_tone'] ?? 'journalistic');
        $structure   = sanitize_text_field($_POST['hrs_schedule_structure'] ?? 'review');

        update_option('hrs_schedule_enabled', $enabled);
        update_option('hrs_schedule_frequency', $frequency);
        update_option('hrs_schedule_time', $time);
        update_option('hrs_schedule_max_per_day', $max_per_day);
        update_option('hrs_schedule_post_status', $post_status);
        update_option('hrs_schedule_hotel_list', $hotel_list);
        update_option('hrs_schedule_min_hqc', $min_hqc);

        update_option('hrs_schedule_persona', $persona);
        update_option('hrs_schedule_purpose', $purpose);
        update_option('hrs_schedule_depth', $depth);
        update_option('hrs_schedule_tone', $tone);
        update_option('hrs_schedule_structure', $structure);

        // Cronスケジュール更新
        $this->update_cron_schedule($enabled, $frequency, $time);
    }

    /**
     * Cronスケジュール更新
     */
    private function update_cron_schedule($enabled, $frequency, $time) {
        wp_clear_scheduled_hook('hrs_scheduled_generation');
        if (!$enabled) {
            return;
        }

        list($hour, $minute) = explode(':', $time);
        $timestamp = strtotime("today {$hour}:{$minute}");

        if ($timestamp < time()) {
            $timestamp = strtotime("tomorrow {$hour}:{$minute}");
        }

        wp_schedule_event($timestamp, $frequency, 'hrs_scheduled_generation');
    }

    /**
     * スケジュール生成実行
     */
    public function run_scheduled_generation() {
        $enabled = get_option('hrs_schedule_enabled', 0);
        if (!$enabled) {
            return;
        }

        $max_per_day = get_option('hrs_schedule_max_per_day', 3);
        $today_count = $this->get_today_count();
        if ($today_count >= $max_per_day) {
            $this->log_generation(array(
                'success' => false,
                'hotel'   => '-',
                'error'   => '本日の生成上限に達しました',
            ));
            return;
        }

        $hotel = $this->get_next_hotel();
        if (!$hotel) {
            $this->log_generation(array(
                'success' => false,
                'hotel'   => '-',
                'error'   => '生成対象ホテルがありません',
            ));
            return;
        }

        $result = $this->generate_article($hotel);
        $this->log_generation($result);
    }

    /**
     * 次のホテルを取得
     */
    private function get_next_hotel() {
        $hotel_list = get_option('hrs_schedule_hotel_list', '');
        $generated  = get_option('hrs_generated_hotels', array());
        $lines      = array_filter(array_map('trim', explode("\n", $hotel_list)));

        foreach ($lines as $line) {
            $parts      = array_map('trim', explode(',', $line));
            $hotel_name = $parts[0];
            $location   = $parts[1] ?? '';

            if (in_array($hotel_name, $generated)) {
                continue;
            }

            $existing = get_posts(array(
                'post_type'      => 'hotel-review',
                'meta_key'       => '_hrs_hotel_name',
                'meta_value'     => $hotel_name,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ));

            if (empty($existing)) {
                return array(
                    'name'     => $hotel_name,
                    'location' => $location,
                );
            }
        }
        return null;
    }

    /**
     * 記事生成
     */
    private function generate_article($hotel) {
        $post_status = get_option('hrs_schedule_post_status', 'draft');
        $min_hqc     = get_option('hrs_schedule_min_hqc', 50);

        if (!class_exists('HRS_Article_Generator')) {
            return array(
                'success' => false,
                'hotel'   => $hotel['name'],
                'error'   => 'Article Generator not found',
            );
        }

        $generator = new HRS_Article_Generator();

        // 新しい生成設定を渡す
        $options = array(
            'location'   => $hotel['location'],
            'persona'    => get_option('hrs_schedule_persona', 'general'),
            'purpose'    => get_option('hrs_schedule_purpose', 'sightseeing'),
            'depth'      => get_option('hrs_schedule_depth', 'L2'),
            'tone'       => get_option('hrs_schedule_tone', 'journalistic'),
            'structure'  => get_option('hrs_schedule_structure', 'review'),
        );

        // random処理はGenerator側で対応することを想定
        $result = $generator->generate($hotel['name'], $options);

        if ($result['success']) {
            // ★ 修正: post_metaから記事分析後の正確なスコアを取得
            $stored_score = get_post_meta($result['post_id'], '_hrs_hqc_score', true);
            if (!empty($stored_score)) {
                $hqc_score = floatval($stored_score);
                // 0-1スケールなら100倍
                if ($hqc_score <= 1) {
                    $hqc_score = $hqc_score * 100;
                }
            } else {
                // フォールバック: generate()の返り値を使用
                $hqc_score = floatval($result['hqc_score']);
                if ($hqc_score <= 1) {
                    $hqc_score = $hqc_score * 100;
                }
            }

            $final_status = ($hqc_score >= $min_hqc) ? $post_status : 'draft';

            wp_update_post(array(
                'ID'            => $result['post_id'],
                'post_status'   => $final_status,
            ));

            $generated = get_option('hrs_generated_hotels', array());
            $generated[] = $hotel['name'];
            update_option('hrs_generated_hotels', $generated);

            return array(
                'success'    => true,
                'hotel'      => $hotel['name'],
                'hqc_score'  => round($hqc_score, 1),
                'post_id'    => $result['post_id'],
            );
        } else {
            return array(
                'success' => false,
                'hotel'   => $hotel['name'],
                'error'   => $result['error_code'] ?? 'Unknown error',
            );
        }
    }

    /**
     * 本日の生成数を取得
     */
    private function get_today_count() {
        $log   = get_option('hrs_generation_log', array());
        $today = date('Y-m-d');
        $count = 0;
        foreach ($log as $entry) {
            if (isset($entry['date']) && $entry['date'] === $today && $entry['success']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 生成ログ記録
     */
    private function log_generation($data) {
        $log = get_option('hrs_generation_log', array());

        $log[] = array(
            'time'       => current_time('H:i'),
            'date'       => date('Y-m-d'),
            'success'    => $data['success'],
            'hotel'      => $data['hotel'],
            'hqc_score'  => $data['hqc_score'] ?? null,
            'error'      => $data['error'] ?? null,
        );

        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        update_option('hrs_generation_log', $log);
    }

    /**
     * Ajax: テスト実行
     */
    public function ajax_test_schedule() {
        check_ajax_referer('hrs_schedule_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }

        $hotel = $this->get_next_hotel();
        if (!$hotel) {
            wp_send_json_error(array('message' => '生成対象ホテルがありません'));
        }

        $result = $this->generate_article($hotel);
        $this->log_generation($result);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    /**
     * ★★★ Ajax: 履歴クリア（新規追加） ★★★
     */
    public function ajax_clear_generation_log() {
        check_ajax_referer('hrs_schedule_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }

        update_option('hrs_generation_log', array());
        wp_send_json_success(array('message' => '履歴をクリアしました'));
    }
}

// 初期化
add_action('plugins_loaded', function() {
    HRS_Schedule_Settings::get_instance();
});