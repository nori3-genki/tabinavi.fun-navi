<?php
/**
 * HRS News Update Settings Page
 * 
 * ニュース・プラン更新の設定画面
 * Google CSE設定リンク、エラー表示強化版
 *
 * @package HRS
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_News_Settings_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu'], 30);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * メニュー追加
     */
    public function add_menu() {
        add_submenu_page(
            '5d-review-builder',
            'ニュース更新設定',
            '📰 ニュース更新',
            'manage_options',
            'hrs-news-settings',
            [$this, 'render']
        );
    }

    /**
     * 設定登録
     */
    public function register_settings() {
        register_setting('hrs_news_settings_group', 'hrs_news_enabled');
        register_setting('hrs_news_settings_group', 'hrs_news_update_day');
        register_setting('hrs_news_settings_group', 'hrs_news_update_time');
        register_setting('hrs_news_settings_group', 'hrs_news_fetch_news');
        register_setting('hrs_news_settings_group', 'hrs_news_fetch_plans');
        register_setting('hrs_news_settings_group', 'hrs_news_days_limit');
        
        // 記事内表示設定
        register_setting('hrs_news_settings_group', 'hrs_news_show_in_article');
        register_setting('hrs_news_settings_group', 'hrs_news_article_position');
        register_setting('hrs_news_settings_group', 'hrs_news_article_max_items');
    }

    /**
     * 設定画面表示
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }

        // Google CSE設定チェック
        $cse_api_key = get_option('hrs_google_cse_api_key', '');
        $cse_id = get_option('hrs_google_cse_id', '');
        $cse_configured = !empty($cse_api_key) && !empty($cse_id);

        // 手動更新処理
        if (isset($_POST['hrs_manual_update']) && check_admin_referer('hrs_news_manual_update')) {
            if (!$cse_configured) {
                echo '<div class="notice notice-error"><p>❌ Google CSE API未設定のため更新できません。<a href="' . esc_url(admin_url('admin.php?page=5d-review-builder-settings&tab=google')) . '">今すぐ設定する</a></p></div>';
            } else {
                $updater = HRS_News_Plan_Updater::get_instance();
                $result = $updater->run_weekly_update();
                
                if ($result['success']) {
                    $results = $result['results'];
                    echo '<div class="notice notice-success"><p>✅ ニュース更新完了: 記事' . $results['updated'] . '件更新 / ニュース' . $results['news_found'] . '件 / プラン' . $results['plans_found'] . '件</p></div>';
                    
                    if (!empty($results['errors'])) {
                        echo '<div class="notice notice-warning"><p>⚠️ ' . $results['errors'] . '件のエラーが発生しました</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>❌ エラー: ' . esc_html($result['message']) . '</p></div>';
                }
            }
        }

        // 価格手動更新処理
        if (isset($_POST['hrs_price_manual_update']) && check_admin_referer('hrs_price_manual_update')) {
            $price_updater = HRS_Price_Updater::get_instance();
            $results = $price_updater->run_price_update();
            echo '<div class="notice notice-success"><p>💰 価格更新完了: ' . $results['updated'] . '件更新 / ' . $results['errors'] . '件エラー</p></div>';
        }

        $enabled = get_option('hrs_news_enabled', 0);
        $update_day = get_option('hrs_news_update_day', 'monday');
        $update_time = get_option('hrs_news_update_time', '04:00');
        $fetch_news = get_option('hrs_news_fetch_news', 1);
        $fetch_plans = get_option('hrs_news_fetch_plans', 1);
        $days_limit = get_option('hrs_news_days_limit', 30);
        $last_updated = get_option('hrs_news_last_updated', '');
        $last_results = get_option('hrs_news_last_results', []);

        $days = [
            'sunday' => '日曜日',
            'monday' => '月曜日',
            'tuesday' => '火曜日',
            'wednesday' => '水曜日',
            'thursday' => '木曜日',
            'friday' => '金曜日',
            'saturday' => '土曜日',
        ];
        ?>
        <div class="wrap hrs-news-settings">
            <h1><span class="dashicons dashicons-megaphone"></span> ニュース・プラン更新設定</h1>
            <p class="description">ホテルの最新ニュースと新プラン情報を自動取得する設定です</p>

            <?php if (!$cse_configured): ?>
            <div class="notice notice-error">
                <p>
                    <strong>❌ Google CSE API未設定</strong><br>
                    ニュース・プラン情報の自動取得にはGoogle Custom Search Engine APIが必要です。
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-settings&tab=google')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings" style="margin-top:3px;"></span>
                        今すぐGoogle CSE APIを設定する
                    </a>
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="button button-secondary">
                        <span class="dashicons dashicons-external" style="margin-top:3px;"></span>
                        Google Cloud Consoleを開く
                    </a>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($last_updated): ?>
            <div class="hrs-status-box">
                <h3>📊 最終更新状況</h3>
                <table class="widefat striped">
                    <tr>
                        <th>最終更新日時</th>
                        <td><?php echo esc_html(date('Y/m/d H:i', strtotime($last_updated))); ?></td>
                    </tr>
                    <?php if (!empty($last_results)): ?>
                    <tr>
                        <th>処理記事数</th>
                        <td><?php echo esc_html($last_results['total'] ?? 0); ?>件</td>
                    </tr>
                    <tr>
                        <th>更新記事数</th>
                        <td><?php echo esc_html($last_results['updated'] ?? 0); ?>件</td>
                    </tr>
                    <tr>
                        <th>取得ニュース</th>
                        <td><?php echo esc_html($last_results['news_found'] ?? 0); ?>件</td>
                    </tr>
                    <tr>
                        <th>取得プラン</th>
                        <td><?php echo esc_html($last_results['plans_found'] ?? 0); ?>件</td>
                    </tr>
                    <?php if (!empty($last_results['errors'])): ?>
                    <tr>
                        <th>エラー</th>
                        <td style="color:red;"><?php echo esc_html($last_results['errors']); ?>件</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($last_results['error_message'])): ?>
                    <tr>
                        <th>エラー詳細</th>
                        <td style="color:red;"><?php echo esc_html($last_results['error_message']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('hrs_news_settings_group'); ?>

                <div class="hrs-settings-section">
                    <h2>🔧 基本設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">自動更新</th>
                            <td>
                                <label class="hrs-toggle">
                                    <input type="checkbox" name="hrs_news_enabled" value="1" <?php checked($enabled, 1); ?>>
                                    <span class="hrs-toggle-slider"></span>
                                </label>
                                <span class="description">有効にすると週1回自動で更新します</span>
                                <?php if (!$cse_configured): ?>
                                <br><span style="color:red;">※ Google CSE API設定が必要です</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">更新曜日</th>
                            <td>
                                <select name="hrs_news_update_day">
                                    <?php foreach ($days as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($update_day, $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">更新時刻</th>
                            <td>
                                <input type="time" name="hrs_news_update_time" value="<?php echo esc_attr($update_time); ?>">
                                <span class="description">サーバー時刻基準（現在: <?php echo current_time('H:i'); ?>）</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hrs-settings-section">
                    <h2>📥 取得設定</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">取得する情報</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hrs_news_fetch_news" value="1" <?php checked($fetch_news, 1); ?>>
                                    ニュース（リニューアル・イベント等）
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="hrs_news_fetch_plans" value="1" <?php checked($fetch_plans, 1); ?>>
                                    新プラン（期間限定・キャンペーン等）
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">取得期間</th>
                            <td>
                                <input type="number" name="hrs_news_days_limit" value="<?php echo esc_attr($days_limit); ?>" min="7" max="90" class="small-text">
                                日以内の情報を取得
                                <p class="description">ニュースは設定日数、プランは2倍の期間を取得します</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hrs-settings-section">
                    <h2>📄 記事内表示設定</h2>
                    <?php
                    $show_in_article = get_option('hrs_news_show_in_article', 1);
                    $article_position = get_option('hrs_news_article_position', 'bottom');
                    $article_max_items = get_option('hrs_news_article_max_items', 5);
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">記事内に表示</th>
                            <td>
                                <label class="hrs-toggle">
                                    <input type="checkbox" name="hrs_news_show_in_article" value="1" <?php checked($show_in_article, 1); ?>>
                                    <span class="hrs-toggle-slider"></span>
                                </label>
                                <span class="description">各ホテル記事内に最新情報セクションを自動表示</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">表示位置</th>
                            <td>
                                <select name="hrs_news_article_position">
                                    <option value="bottom" <?php selected($article_position, 'bottom'); ?>>記事の末尾</option>
                                    <option value="top" <?php selected($article_position, 'top'); ?>>記事の先頭</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">最大表示件数</th>
                            <td>
                                <input type="number" name="hrs_news_article_max_items" value="<?php echo esc_attr($article_max_items); ?>" min="1" max="20" class="small-text">
                                件（ニュース・プランそれぞれ）
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('設定を保存'); ?>
            </form>

            <div class="hrs-settings-section">
                <h2>🔄 手動更新</h2>
                <p>今すぐニュース・プラン情報を更新します。（Google CSE APIを使用）</p>
                <form method="post">
                    <?php wp_nonce_field('hrs_news_manual_update'); ?>
                    <button type="submit" name="hrs_manual_update" class="button button-primary button-large" <?php disabled(!$cse_configured); ?>>
                        <span class="dashicons dashicons-update" style="margin-top:4px;"></span>
                        今すぐニュース更新を実行
                    </button>
                    <?php if (!$cse_configured): ?>
                    <p style="color:red;">※ Google CSE API設定が必要です</p>
                    <?php endif; ?>
                </form>

                <hr style="margin: 20px 0;">

                <p>全ホテル記事の価格・評価を楽天APIから更新します。</p>
                <form method="post">
                    <?php wp_nonce_field('hrs_price_manual_update'); ?>
                    <button type="submit" name="hrs_price_manual_update" class="button button-secondary button-large">
                        <span class="dashicons dashicons-money-alt" style="margin-top:4px;"></span>
                        今すぐ価格更新を実行
                    </button>
                </form>

                <?php
                $price_last_updated = get_option('hrs_price_last_updated', '');
                $price_last_results = get_option('hrs_price_last_results', []);
                if ($price_last_updated):
                ?>
                <div class="hrs-price-status" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 3px solid #0073aa;">
                    <strong>💰 価格更新状況</strong><br>
                    最終更新: <?php echo esc_html(date('Y/m/d H:i', strtotime($price_last_updated))); ?><br>
                    <?php if (!empty($price_last_results)): ?>
                    更新: <?php echo esc_html($price_last_results['updated'] ?? 0); ?>件 / 
                    スキップ: <?php echo esc_html($price_last_results['skipped'] ?? 0); ?>件 / 
                    エラー: <?php echo esc_html($price_last_results['errors'] ?? 0); ?>件
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="hrs-settings-section">
                <h2>📌 使い方</h2>
                
                <h4>🎯 表示場所の設定</h4>
                <p><strong>トップページと投稿ページのサイドバーに自動表示されます</strong></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>トップページ（index.php, front-page.php）</li>
                    <li>投稿ページ（single.php）</li>
                </ul>
                <p>※ その他の場所に表示したい場合は、下記のウィジェットまたはショートコードを使用してください</p>

                <h4>🔧 ウィジェット</h4>
                <p>外観 → ウィジェット から「🏨 ホテル最新ニュース」を任意の場所に追加できます。</p>
                
                <h4>📝 ショートコード</h4>
                <p>固定ページなどで以下のショートコードを使用できます：</p>
                <code>[hrs_latest_news]</code> - ニュース・プラン両方表示<br>
                <code>[hrs_latest_news type="news" count="10"]</code> - ニュースのみ10件<br>
                <code>[hrs_latest_news type="plans" count="5"]</code> - プランのみ5件
                
                <h4>🔍 次回のCron実行</h4>
                <?php
                $next_run = wp_next_scheduled('hrs_weekly_news_update');
                if ($next_run && $enabled) {
                    echo '<p>次回実行予定: <strong>' . date('Y/m/d H:i', $next_run) . '</strong></p>';
                } else {
                    echo '<p style="color:#999;">自動更新が無効です</p>';
                }
                ?>
            </div>
        </div>

        <style>
        .hrs-news-settings .hrs-settings-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .hrs-news-settings h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .hrs-status-box {
            background: #f0f6fc;
            padding: 15px 20px;
            border-left: 4px solid #0073aa;
            margin: 20px 0;
        }
        .hrs-status-box h3 {
            margin-top: 0;
        }
        .hrs-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
            vertical-align: middle;
            margin-right: 10px;
        }
        .hrs-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .hrs-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 26px;
        }
        .hrs-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        .hrs-toggle input:checked + .hrs-toggle-slider {
            background-color: #0073aa;
        }
        .hrs-toggle input:checked + .hrs-toggle-slider:before {
            transform: translateX(24px);
        }
        code {
            background: #f1f1f1;
            padding: 3px 8px;
            border-radius: 3px;
            display: inline-block;
            margin: 3px 0;
        }
        </style>
        <?php
    }
}

// 初期化
new HRS_News_Settings_Page();