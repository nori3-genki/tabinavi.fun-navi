<?php
/**
 * Settings Page - メインクラス
 * 
 * @package Hotel_Review_System
 * @version 6.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 分割ファイル読み込み
require_once __DIR__ . '/settings/class-settings-styles.php';
require_once __DIR__ . '/settings/class-settings-scripts.php';
require_once __DIR__ . '/settings/class-settings-tabs.php';

class HRS_Settings_Page {

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        // API設定
        register_setting('hrs_settings_group', 'hrs_chatgpt_api_key');
        register_setting('hrs_settings_group', 'hrs_openai_model');
        register_setting('hrs_settings_group', 'hrs_google_cse_api_key');
        register_setting('hrs_settings_group', 'hrs_google_cse_id');
        register_setting('hrs_settings_group', 'hrs_rakuten_app_id');
        
        // アフィリエイト設定
        register_setting('hrs_settings_group', 'hrs_moshimo_affiliate_id');
        register_setting('hrs_settings_group', 'hrs_ota_tier_1', ['sanitize_callback' => [$this, 'sanitize_ota_array']]);
        register_setting('hrs_settings_group', 'hrs_ota_tier_2', ['sanitize_callback' => [$this, 'sanitize_ota_array']]);
        register_setting('hrs_settings_group', 'hrs_ota_tier_3', ['sanitize_callback' => [$this, 'sanitize_ota_array']]);
        
        // デフォルト設定
        register_setting('hrs_settings_group', 'hrs_default_words');
        register_setting('hrs_settings_group', 'hrs_default_status');
        
        // 【追加】HQC品質チェック設定
        register_setting('hrs_settings_group', 'hrs_hqc_threshold', [
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => 'absint',
        ]);
        register_setting('hrs_settings_group', 'hrs_hqc_enabled', [
            'type' => 'boolean',
            'default' => 1,
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
        ]);
    }

    public function sanitize_ota_array($value) {
        if (!is_array($value)) {
            return [];
        }
        return array_map('sanitize_text_field', $value);
    }

    /**
     * 【追加】チェックボックスのサニタイズ
     */
    public function sanitize_checkbox($value) {
        return $value ? 1 : 0;
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', '5d-review-builder'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'api';
        $tabs = [
            'api' => ['icon' => 'admin-network', 'label' => 'ChatGPT API'],
            'google' => ['icon' => 'search', 'label' => 'Google CSE'],
            'rakuten' => ['icon' => 'format-image', 'label' => '楽天API'],
            'affiliate' => ['icon' => 'money-alt', 'label' => 'アフィリエイト'],
            'defaults' => ['icon' => 'admin-generic', 'label' => 'デフォルト'],
        ];
        ?>
        <div class="wrap hrs-settings-wrap">
            <div class="hrs-settings-header">
                <h1><span class="dashicons dashicons-admin-settings"></span> 5D Review Builder 設定</h1>
                <p class="hrs-subtitle">APIキーの設定とプラグインの動作をカスタマイズ</p>
            </div>

            <div class="hrs-tab-nav">
                <?php foreach ($tabs as $tab_id => $tab): ?>
                <a href="?page=5d-review-builder-settings&tab=<?php echo esc_attr($tab_id); ?>" 
                   class="hrs-tab-link <?php echo $active_tab === $tab_id ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php echo esc_html($tab['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <form method="post" action="options.php">
                <?php 
                settings_fields('hrs_settings_group');
                $this->render_hidden_fields($active_tab);
                
                switch ($active_tab) {
                    case 'google':
                        HRS_Settings_Tabs::render_google_tab();
                        break;
                    case 'rakuten':
                        HRS_Settings_Tabs::render_rakuten_tab();
                        break;
                    case 'affiliate':
                        HRS_Settings_Tabs::render_affiliate_tab();
                        break;
                    case 'defaults':
                        HRS_Settings_Tabs::render_defaults_tab();
                        break;
                    default:
                        HRS_Settings_Tabs::render_api_tab();
                        break;
                }
                ?>
                <div class="hrs-form-footer">
                    <?php submit_button('設定を保存', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
        HRS_Settings_Styles::render();
        HRS_Settings_Scripts::render();
    }

    private function render_hidden_fields($active_tab) {
        $fields = [
            'api' => ['hrs_google_cse_api_key', 'hrs_google_cse_id', 'hrs_rakuten_app_id', 'hrs_moshimo_affiliate_id', 'hrs_hqc_threshold', 'hrs_hqc_enabled', 'hrs_default_words', 'hrs_default_status'],
            'google' => ['hrs_chatgpt_api_key', 'hrs_openai_model', 'hrs_rakuten_app_id', 'hrs_moshimo_affiliate_id', 'hrs_hqc_threshold', 'hrs_hqc_enabled', 'hrs_default_words', 'hrs_default_status'],
            'rakuten' => ['hrs_chatgpt_api_key', 'hrs_openai_model', 'hrs_google_cse_api_key', 'hrs_google_cse_id', 'hrs_moshimo_affiliate_id', 'hrs_hqc_threshold', 'hrs_hqc_enabled', 'hrs_default_words', 'hrs_default_status'],
            'affiliate' => ['hrs_chatgpt_api_key', 'hrs_openai_model', 'hrs_google_cse_api_key', 'hrs_google_cse_id', 'hrs_rakuten_app_id', 'hrs_hqc_threshold', 'hrs_hqc_enabled', 'hrs_default_words', 'hrs_default_status'],
            'defaults' => ['hrs_chatgpt_api_key', 'hrs_openai_model', 'hrs_google_cse_api_key', 'hrs_google_cse_id', 'hrs_rakuten_app_id', 'hrs_moshimo_affiliate_id'],
        ];
        
        // OTA Tier は配列なので別処理
        $ota_tiers = ['hrs_ota_tier_1', 'hrs_ota_tier_2', 'hrs_ota_tier_3'];
        
        if (isset($fields[$active_tab])) {
            foreach ($fields[$active_tab] as $field) {
                echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr(get_option($field, '')) . '">';
            }
            
            // affiliateタブ以外ではOTA Tierをhiddenで保持
            if ($active_tab !== 'affiliate') {
                foreach ($ota_tiers as $tier) {
                    $values = get_option($tier, []);
                    if (is_array($values)) {
                        foreach ($values as $val) {
                            echo '<input type="hidden" name="' . esc_attr($tier) . '[]" value="' . esc_attr($val) . '">';
                        }
                    }
                }
            }
        }
    }
}