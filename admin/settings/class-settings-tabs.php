<?php
/**
 * Settings Tabs - 各タブのコンテンツ統合版
 * * @package Hotel_Review_System
 * @subpackage Settings
 * @version 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Settings_Tabs {

    /**
     * AIモデルの定義
     */
    private static $ai_models = [
        'gpt-4o-mini' => 'GPT-4o mini（推奨・低コスト）',
        'gpt-4o' => 'GPT-4o（高品質）',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo（最低コスト）',
    ];

    /**
     * OTA（オンライン旅行代理店）の定義
     */
    private static $ota_list = [
        'rakuten' => '楽天トラベル',
        'jalan' => 'じゃらん',
        'ikyu' => '一休.com',
        'booking' => 'Booking.com',
        'yahoo' => 'Yahoo!トラベル',
        'jtb' => 'JTB',
        'rurubu' => 'るるぶトラベル',
        'relux' => 'Relux',
        'yukoyuko' => 'ゆこゆこ',
        'expedia' => 'Expedia',
    ];

    /**
     * 1. ChatGPT APIタブ
     */
    public static function render_api_tab() {
        $chatgpt_key = get_option('hrs_chatgpt_api_key', '');
        $openai_model = get_option('hrs_openai_model', 'gpt-4o-mini');
        $is_configured = !empty($chatgpt_key);
        ?>
        <div class="hrs-settings-card">
            <div class="hrs-card-header">
                <h2><span class="dashicons dashicons-admin-network"></span> ChatGPT API設定</h2>
                <div class="hrs-status-badge <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                    <?php echo $is_configured ? '<span class="dashicons dashicons-yes-alt"></span> 設定済み' : '<span class="dashicons dashicons-warning"></span> 未設定'; ?>
                </div>
            </div>
            <div class="hrs-card-body">
                <div class="hrs-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>ChatGPT APIについて</strong>
                        <p>記事の自動生成にOpenAI ChatGPT APIを使用します。<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>でAPIキーを取得してください。</p>
                    </div>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label for="hrs_chatgpt_api_key">APIキー <span class="required">*</span></label></th>
                        <td>
                            <input type="password" id="hrs_chatgpt_api_key" name="hrs_chatgpt_api_key" value="<?php echo esc_attr($chatgpt_key); ?>" class="regular-text" placeholder="sk-proj-...">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hrs_openai_model">使用モデル</label></th>
                        <td>
                            <select id="hrs_openai_model" name="hrs_openai_model" class="regular-text">
                                <?php foreach (self::$ai_models as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($openai_model, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><strong>推奨: gpt-4o-mini</strong> - コストと品質のバランスが最適</p>
                        </td>
                    </tr>
                </table>
                <div class="hrs-test-section">
                    <h3><span class="dashicons dashicons-yes-alt"></span> API接続テスト</h3>
                    <button type="button" class="button button-secondary" id="test-chatgpt"><span class="dashicons dashicons-update"></span> ChatGPT API をテスト</button>
                    <div id="api-test-result"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 2. Google CSEタブ
     */
    public static function render_google_tab() {
        $google_cse_key = get_option('hrs_google_cse_api_key', '');
        $google_cse_id = get_option('hrs_google_cse_id', '');
        $is_configured = !empty($google_cse_key) && !empty($google_cse_id);
        ?>
        <div class="hrs-settings-card">
            <div class="hrs-card-header">
                <h2><span class="dashicons dashicons-search"></span> Google Custom Search Engine設定</h2>
                <div class="hrs-status-badge <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                    <?php echo $is_configured ? '<span class="dashicons dashicons-yes-alt"></span> 設定済み' : '<span class="dashicons dashicons-warning"></span> 未設定'; ?>
                </div>
            </div>
            <div class="hrs-card-body">
                <div class="hrs-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>Google CSEについて</strong>
                        <p>ホテル情報の検索に使用します。<a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>でAPIキーを取得してください。</p>
                    </div>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label for="hrs_google_cse_api_key">APIキー <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="hrs_google_cse_api_key" name="hrs_google_cse_api_key" value="<?php echo esc_attr($google_cse_key); ?>" class="regular-text" placeholder="AIza...">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hrs_google_cse_id">検索エンジンID <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="hrs_google_cse_id" name="hrs_google_cse_id" value="<?php echo esc_attr($google_cse_id); ?>" class="regular-text" placeholder="c1234567890abcdef">
                        </td>
                    </tr>
                </table>
                <div class="hrs-test-section">
                    <h3><span class="dashicons dashicons-yes-alt"></span> API接続テスト</h3>
                    <button type="button" class="button button-secondary" id="test-google-cse"><span class="dashicons dashicons-update"></span> Google CSE をテスト</button>
                    <div id="google-test-result"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 3. 楽天トラベル設定タブ（統合版）
     */
    public static function render_rakuten_tab() {
        $app_id = get_option('hrs_rakuten_app_id', '');
        $affiliate_id = get_option('hrs_rakuten_affiliate_id', '');
        $price_enabled = get_option('hrs_rakuten_price_update_enabled', 1);
        $cache_hours = get_option('hrs_rakuten_cache_hours', 24);
        $is_configured = !empty($app_id);
        ?>
        <div class="hrs-settings-card">
            <div class="hrs-card-header">
                <h2><span class="dashicons dashicons-cart"></span> 楽天トラベルAPI設定</h2>
                <div class="hrs-status-badge <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                    <?php echo $is_configured ? '<span class="dashicons dashicons-yes-alt"></span> 設定済み' : '<span class="dashicons dashicons-warning"></span> 未設定'; ?>
                </div>
            </div>
            <div class="hrs-card-body">
                <div class="hrs-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>楽天APIの用途</strong>
                        <p>ホテルのアイキャッチ画像取得および、最新宿泊料金の自動更新に使用します。<br>
                        <a href="https://webservice.rakuten.co.jp/" target="_blank">Rakuten Developers</a> でアプリIDを取得してください。</p>
                    </div>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="hrs_rakuten_app_id">アプリID <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="hrs_rakuten_app_id" name="hrs_rakuten_app_id" value="<?php echo esc_attr($app_id); ?>" class="regular-text" placeholder="1234567890123456">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hrs_rakuten_affiliate_id">楽天アフィリエイトID</label></th>
                        <td>
                            <input type="text" id="hrs_rakuten_affiliate_id" name="hrs_rakuten_affiliate_id" value="<?php echo esc_attr($affiliate_id); ?>" class="regular-text" placeholder="例: 12345678.90abcdef...">
                            <p class="description">楽天アフィリエイトIDを設定すると、予約リンクに自動で付与されます。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">料金自動更新</th>
                        <td>
                            <label class="hrs-toggle-label">
                                <input type="checkbox" name="hrs_rakuten_price_update_enabled" value="1" <?php checked($price_enabled, 1); ?>>
                                <span>毎日自動で料金を更新する</span>
                            </label>
                            <p class="description">WordPress Cronを使用して、毎日1回料金情報を更新します。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hrs_rakuten_cache_hours">キャッシュ時間</label></th>
                        <td>
                            <input type="number" id="hrs_rakuten_cache_hours" name="hrs_rakuten_cache_hours" value="<?php echo esc_attr($cache_hours); ?>" min="1" max="72" class="small-text"> 時間
                            <p class="description">API負荷を抑えるためのキャッシュ保持期間（推奨: 24時間）</p>
                        </td>
                    </tr>
                </table>

                <div class="hrs-test-section">
                    <h3><span class="dashicons dashicons-yes-alt"></span> API接続テスト</h3>
                    <button type="button" class="button button-secondary" id="hrs-rakuten-api-test">
                        <span class="dashicons dashicons-update"></span> 楽天トラベルをテスト
                    </button>
                    <span id="hrs-rakuten-api-test-result" style="margin-left: 10px;"></span>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#hrs-rakuten-api-test').on('click', function() {
                var $btn = $(this);
                var $result = $('#hrs-rakuten-api-test-result');
                $btn.prop('disabled', true);
                $result.html('<span style="color: #666;">テスト中...</span>');
                
                $.post(ajaxurl, {
                    action: 'hrs_test_rakuten_api',
                    nonce: '<?php echo wp_create_nonce('hrs_rakuten_api_test'); ?>',
                    app_id: $('#hrs_rakuten_app_id').val()
                }, function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                    }
                }).fail(function() {
                    $result.html('<span style="color: #dc3232;">✗ 通信エラー</span>');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 4. アフィリエイトタブ
     */
    public static function render_affiliate_tab() {
        $moshimo_id = get_option('hrs_moshimo_affiliate_id', '5247247');
        $tier_1 = get_option('hrs_ota_tier_1', ['rakuten', 'jalan', 'ikyu', 'booking', 'yahoo']);
        $tier_2 = get_option('hrs_ota_tier_2', ['jtb', 'rurubu', 'relux']);
        $tier_3 = get_option('hrs_ota_tier_3', ['yukoyuko', 'expedia']);
        ?>
        <div class="hrs-settings-card">
            <div class="hrs-card-header">
                <h2><span class="dashicons dashicons-money-alt"></span> アフィリエイト設定</h2>
            </div>
            <div class="hrs-card-body">
                <div class="hrs-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>アフィリエイトIDについて</strong>
                        <p>記事内のOTAリンクに付与されるアフィリエイトIDです。<a href="https://af.moshimo.com/" target="_blank">もしもアフィリエイト</a>でIDを取得してください。</p>
                    </div>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label for="hrs_moshimo_affiliate_id">もしもアフィリエイトID</label></th>
                        <td>
                            <input type="text" id="hrs_moshimo_affiliate_id" name="hrs_moshimo_affiliate_id" value="<?php echo esc_attr($moshimo_id); ?>" class="regular-text">
                            <p class="description">デフォルト: 5247247</p>
                        </td>
                    </tr>
                </table>
                <h3><span class="dashicons dashicons-chart-bar"></span> OTA優先度設定</h3>
                <div class="hrs-ota-tiers">
                    <div class="hrs-tier-section">
                        <h4><span class="tier-badge tier-1">◎</span> Tier 1（最優先）</h4>
                        <?php self::render_ota_checkboxes('hrs_ota_tier_1', $tier_1); ?>
                    </div>
                    <div class="hrs-tier-section">
                        <h4><span class="tier-badge tier-2">○</span> Tier 2（推奨）</h4>
                        <?php self::render_ota_checkboxes('hrs_ota_tier_2', $tier_2); ?>
                    </div>
                    <div class="hrs-tier-section">
                        <h4><span class="tier-badge tier-3">△</span> Tier 3（選択肢）</h4>
                        <?php self::render_ota_checkboxes('hrs_ota_tier_3', $tier_3); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 5. デフォルトタブ
     */
    public static function render_defaults_tab() {
        $default_words = get_option('hrs_default_words', '2000');
        $default_status = get_option('hrs_default_status', 'draft');
        $hqc_threshold = get_option('hrs_hqc_threshold', 50);
        $hqc_enabled = get_option('hrs_hqc_enabled', 1);
        ?>
        <div class="hrs-settings-card">
            <div class="hrs-card-header">
                <h2><span class="dashicons dashicons-admin-generic"></span> デフォルト設定</h2>
            </div>
            <div class="hrs-card-body">
                <div class="hrs-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>デフォルト設定について</strong>
                        <p>記事生成時のデフォルト値を設定します。HQC Generatorでより詳細な設定が可能です。</p>
                    </div>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label for="hrs_default_words">デフォルト文字数</label></th>
                        <td>
                            <select id="hrs_default_words" name="hrs_default_words">
                                <option value="1500" <?php selected($default_words, '1500'); ?>>1,500文字</option>
                                <option value="2000" <?php selected($default_words, '2000'); ?>>2,000文字（推奨）</option>
                                <option value="2500" <?php selected($default_words, '2500'); ?>>2,500文字</option>
                                <option value="3000" <?php selected($default_words, '3000'); ?>>3,000文字</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hrs_default_status">投稿ステータス</label></th>
                        <td>
                            <select id="hrs_default_status" name="hrs_default_status">
                                <option value="draft" <?php selected($default_status, 'draft'); ?>>下書き</option>
                                <option value="publish" <?php selected($default_status, 'publish'); ?>>公開</option>
                                <option value="pending" <?php selected($default_status, 'pending'); ?>>レビュー待ち</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <span class="dashicons dashicons-chart-line"></span> HQC品質チェック設定
                </h3>
                <table class="form-table">
                    <tr>
                        <th><label for="hrs_hqc_enabled">HQCチェック</label></th>
                        <td>
                            <label class="hrs-toggle-label">
                                <input type="checkbox" id="hrs_hqc_enabled" name="hrs_hqc_enabled" value="1" <?php checked($hqc_enabled, 1); ?>>
                                <span>有効にする</span>
                            </label>
                            <p class="description">OFFにするとHQCスコアに関係なく記事を生成します</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hrs_hqc_threshold">HQC閾値</label></th>
                        <td>
                            <select id="hrs_hqc_threshold" name="hrs_hqc_threshold">
                                <?php for($i=0; $i<=80; $i+=10): ?>
                                <option value="<?php echo $i; ?>" <?php selected($hqc_threshold, $i); ?>><?php echo $i; ?>%<?php echo ($i==50) ? '（デフォルト）' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">収集データの品質スコアがこの値未満の場合、記事生成を停止します</p>
                        </td>
                    </tr>
                </table>

                <?php self::render_preset_cards(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * ヘルパー：OTAチェックボックス
     */
    private static function render_ota_checkboxes($name, $selected) {
        echo '<div class="hrs-checkbox-grid">';
        foreach (self::$ota_list as $id => $label) {
            $checked = in_array($id, (array)$selected) ? 'checked' : '';
            echo '<label class="hrs-checkbox-label">';
            echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($id) . '" ' . $checked . '>';
            echo esc_html($label);
            echo '</label>';
        }
        echo '</div>';
    }

    /**
     * ヘルパー：プリセットカード
     */
    private static function render_preset_cards() {
        $presets = [
            ['icon' => '🚀', 'name' => 'Starter', 'desc' => '幅広い読者向け'],
            ['icon' => '💝', 'name' => 'Drama', 'desc' => '感動重視の記念日向け'],
            ['icon' => '📈', 'name' => 'SEO', 'desc' => '検索上位狙い'],
            ['icon' => '👨‍👩‍👧‍👦', 'name' => 'Family', 'desc' => '家族旅行向け'],
        ];
        ?>
        <div class="hrs-preset-gallery">
            <h3><span class="dashicons dashicons-portfolio"></span> 利用可能なプリセット</h3>
            <div class="hrs-preset-grid">
                <?php foreach ($presets as $p): ?>
                <div class="hrs-preset-card">
                    <div class="preset-icon"><?php echo $p['icon']; ?></div>
                    <h4><?php echo esc_html($p['name']); ?></h4>
                    <p><?php echo esc_html($p['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}