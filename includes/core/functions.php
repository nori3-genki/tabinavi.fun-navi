<?php
/**
 * HRS Utility Functions
 * ユーティリティ関数群
 * 
 * @package Hotel_Review_System
 * @version 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグインインスタンスを取得
 * 
 * @return object|null
 */
function hrs() {
    if (class_exists('HRS_Hotel_Review_System')) {
        return HRS_Hotel_Review_System::get_instance();
    }
    return null;
}

/**
 * ログ出力
 * 
 * @param string $message メッセージ
 * @param string $level ログレベル (info, warning, error)
 */
function hrs_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $prefix = '[5DRB]';
        if ($level !== 'info') {
            $prefix .= ' [' . strtoupper($level) . ']';
        }
        error_log($prefix . ' ' . $message);
    }
}

/**
 * オプション取得（プレフィックス付き）
 * 
 * @param string $option_name オプション名
 * @param mixed $default デフォルト値
 * @return mixed
 */
function hrs_get_option($option_name, $default = '') {
    return get_option('hrs_' . $option_name, $default);
}

/**
 * オプション更新（プレフィックス付き）
 * 
 * @param string $option_name オプション名
 * @param mixed $value 値
 * @param bool $autoload 自動読み込み
 * @return bool
 */
function hrs_update_option($option_name, $value, $autoload = true) {
    $option_key = 'hrs_' . $option_name;
    $autoload_value = $autoload ? 'yes' : 'no';
    
    // 自動読み込みしないオプション
    $no_autoload_options = array(
        'hrs_google_cse_api_key',
        'hrs_google_cse_id',
        'hrs_chatgpt_api_key',
        'hrs_rakuten_app_id',
        'hrs_rakuten_affiliate_id',
    );
    
    if (in_array($option_key, $no_autoload_options, true)) {
        $autoload_value = 'no';
    }
    
    return update_option($option_key, $value, $autoload_value);
}

/**
 * プラグイン情報取得
 * 
 * @return array
 */
function hrs_get_plugin_info() {
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return get_plugin_data(HRS_PLUGIN_FILE);
}

/**
 * システム情報取得
 * 
 * @return array
 */
function hrs_get_system_info() {
    global $wpdb;
    
    return array(
        'plugin_name' => '5D Review Builder',
        'plugin_version' => HRS_VERSION,
        'php_version' => PHP_VERSION,
        'wp_version' => $GLOBALS['wp_version'],
        'mysql_version' => $wpdb->db_version(),
        'memory_limit' => ini_get('memory_limit'),
        'google_api_configured' => !empty(get_option('hrs_google_cse_api_key')),
        'chatgpt_api_configured' => !empty(get_option('hrs_chatgpt_api_key')),
        'rakuten_api_configured' => !empty(get_option('hrs_rakuten_app_id')),
        'hqc_generator_active' => class_exists('HRS_HQC_Generator'),
        'custom_post_type_active' => class_exists('HRS_Custom_Post_Type'),
        'learning_system_active' => class_exists('HRS_HQC_Learning_Module'),
        'learning_enabled' => (bool) get_option('hrs_hqc_learning_enabled', true),
        'performance_tracker_active' => class_exists('HRS_Performance_Tracker'),
        'performance_dashboard_active' => class_exists('HRS_Performance_Admin_Page'),
    );
}

/**
 * デフォルトカテゴリを一括作成
 * 
 * @return int 作成件数
 */
function hrs_create_default_categories() {
    // ペルソナカテゴリ（8種類）
    $persona_categories = array(
        array('name' => '一般・観光', 'slug' => 'general', 'description' => '一般的な観光旅行におすすめのホテル・旅館'),
        array('name' => '一人旅', 'slug' => 'solo', 'description' => '一人旅におすすめのホテル・旅館'),
        array('name' => 'カップル・夫婦', 'slug' => 'couple', 'description' => 'カップル・夫婦旅行におすすめのホテル・旅館'),
        array('name' => 'ファミリー', 'slug' => 'family', 'description' => '家族旅行におすすめのホテル・旅館'),
        array('name' => 'シニア', 'slug' => 'senior', 'description' => 'シニア・年配の方におすすめのホテル・旅館'),
        array('name' => 'ワーケーション', 'slug' => 'workation', 'description' => 'ワーケーションにおすすめのホテル・旅館'),
        array('name' => 'ラグジュアリー', 'slug' => 'luxury', 'description' => '高級・ラグジュアリーなホテル・旅館'),
        array('name' => 'コスパ重視', 'slug' => 'budget', 'description' => 'コストパフォーマンスに優れたホテル・旅館'),
    );
    
    // 都道府県カテゴリ
    $prefecture_categories = array(
        array('name' => '北海道', 'slug' => 'hokkaido'),
        array('name' => '青森', 'slug' => 'aomori'),
        array('name' => '岩手', 'slug' => 'iwate'),
        array('name' => '宮城', 'slug' => 'miyagi'),
        array('name' => '秋田', 'slug' => 'akita'),
        array('name' => '山形', 'slug' => 'yamagata'),
        array('name' => '福島', 'slug' => 'fukushima'),
        array('name' => '茨城', 'slug' => 'ibaraki'),
        array('name' => '栃木', 'slug' => 'tochigi'),
        array('name' => '群馬', 'slug' => 'gunma'),
        array('name' => '埼玉', 'slug' => 'saitama'),
        array('name' => '千葉', 'slug' => 'chiba'),
        array('name' => '東京', 'slug' => 'tokyo'),
        array('name' => '神奈川', 'slug' => 'kanagawa'),
        array('name' => '新潟', 'slug' => 'niigata'),
        array('name' => '富山', 'slug' => 'toyama'),
        array('name' => '石川', 'slug' => 'ishikawa'),
        array('name' => '福井', 'slug' => 'fukui'),
        array('name' => '山梨', 'slug' => 'yamanashi'),
        array('name' => '長野', 'slug' => 'nagano'),
        array('name' => '岐阜', 'slug' => 'gifu'),
        array('name' => '静岡', 'slug' => 'shizuoka'),
        array('name' => '愛知', 'slug' => 'aichi'),
        array('name' => '三重', 'slug' => 'mie'),
        array('name' => '滋賀', 'slug' => 'shiga'),
        array('name' => '京都', 'slug' => 'kyoto'),
        array('name' => '大阪', 'slug' => 'osaka'),
        array('name' => '兵庫', 'slug' => 'hyogo'),
        array('name' => '奈良', 'slug' => 'nara'),
        array('name' => '和歌山', 'slug' => 'wakayama'),
        array('name' => '鳥取', 'slug' => 'tottori'),
        array('name' => '島根', 'slug' => 'shimane'),
        array('name' => '岡山', 'slug' => 'okayama'),
        array('name' => '広島', 'slug' => 'hiroshima'),
        array('name' => '山口', 'slug' => 'yamaguchi'),
        array('name' => '徳島', 'slug' => 'tokushima'),
        array('name' => '香川', 'slug' => 'kagawa'),
        array('name' => '愛媛', 'slug' => 'ehime'),
        array('name' => '高知', 'slug' => 'kochi'),
        array('name' => '福岡', 'slug' => 'fukuoka'),
        array('name' => '佐賀', 'slug' => 'saga'),
        array('name' => '長崎', 'slug' => 'nagasaki'),
        array('name' => '熊本', 'slug' => 'kumamoto'),
        array('name' => '大分', 'slug' => 'oita'),
        array('name' => '宮崎', 'slug' => 'miyazaki'),
        array('name' => '鹿児島', 'slug' => 'kagoshima'),
        array('name' => '沖縄', 'slug' => 'okinawa'),
    );
    
    $created_count = 0;
    
    // ペルソナカテゴリ作成
    foreach ($persona_categories as $cat) {
        if (!term_exists($cat['name'], 'category') && !term_exists($cat['slug'], 'category')) {
            $result = wp_insert_term($cat['name'], 'category', array(
                'slug' => $cat['slug'],
                'description' => isset($cat['description']) ? $cat['description'] : '',
            ));
            if (!is_wp_error($result)) {
                $created_count++;
            }
        }
    }
    
    // 都道府県カテゴリ作成
    foreach ($prefecture_categories as $cat) {
        if (!term_exists($cat['name'], 'category') && !term_exists($cat['slug'], 'category')) {
            $result = wp_insert_term($cat['name'], 'category', array(
                'slug' => $cat['slug'],
            ));
            if (!is_wp_error($result)) {
                $created_count++;
            }
        }
    }
    
    hrs_log('Created ' . $created_count . ' default categories', 'info');
    
    return $created_count;
}

/**
 * AJAXハンドラーフォールバック
 */
if (!class_exists('HRS_Ajax_Handler')) {
    add_action('wp_ajax_hrs_generate_prompt', 'hrs_ajax_generate_prompt_fallback');
    
    function hrs_ajax_generate_prompt_fallback() {
        if (!isset($_POST['hrs_nonce']) || !wp_verify_nonce($_POST['hrs_nonce'], 'hrs_manual_generate')) {
            wp_send_json_error(array('message' => 'セキュリティ検証に失敗しました'));
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => '権限がありません'));
            return;
        }
        
        $hotel_name = isset($_POST['hotel_name']) ? sanitize_text_field($_POST['hotel_name']) : '';
        
        if (empty($hotel_name)) {
            wp_send_json_error(array('message' => 'ホテル名を入力してください'));
            return;
        }
        
        $prompt = "# {$hotel_name} の紹介記事を作成してください\n\n";
        $prompt .= "## 構成\n";
        $prompt .= "1. 導入\n2. 特徴\n3. 客室\n4. 料理\n5. 温泉\n6. アクセス\n7. まとめ\n\n";
        $prompt .= "## 要件\n- H2見出し6個以上\n- 1500-2000文字\n- HTML形式\n";
        
        wp_send_json_success(array(
            'prompt' => $prompt,
            'hotel_name' => $hotel_name,
            'engine' => 'fallback',
        ));
    }
}