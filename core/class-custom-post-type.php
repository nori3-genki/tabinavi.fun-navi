<?php
/**
 * カスタム投稿タイプ登録クラス
 * - hotel-review カスタム投稿タイプ
 * - OpenAI API連携（設定画面同期）
 * - 左右2列のモダンUI
 * - 管理画面一覧へのHQCスコア表示
 * 
 * @package HRS
 * @version 1.0.3 - タクソノミー登録をclass-category-tag-manager.phpに統一
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Custom_Post_Type {

    private $post_type = 'hotel-review';

    public function __construct() {
        // 投稿タイプ登録
        add_action('init', array($this, 'register_post_type'));
        
        // メタボックス関連
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . $this->post_type, array($this, 'save_meta_boxes'), 10, 2);
        
        // Ajaxアクション
        add_action('wp_ajax_hrs_fetch_hotel_data', array($this, 'ajax_fetch_hotel_data'));

        // 管理画面一覧のカラムカスタマイズ
        add_filter("manage_{$this->post_type}_posts_columns", array($this, 'add_hqc_column'));
        add_action("manage_{$this->post_type}_posts_custom_column", array($this, 'render_hqc_column'), 10, 2);
    }

    /**
     * カスタム投稿タイプの登録
     */
    public function register_post_type() {
        register_post_type($this->post_type, array(
            'labels' => array(
                'name' => 'ホテルレビュー',
                'singular_name' => 'ホテルレビュー',
                'menu_name' => 'レビュー一覧',
                'add_new' => '新規追加',
                'add_new_item' => '新しいレビューを追加',
                'edit_item' => 'レビューを編集'
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => '5d-review-builder',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest' => true,
            'has_archive' => true,
            'taxonomies' => array('hotel-category', 'hotel-tag'),
        ));
    }

    /**
     * メタボックスの追加
     */
    public function add_meta_boxes() {
        add_meta_box(
            'hrs_hotel_info',
            'ホテル詳細・AIアシスタント',
            array($this, 'render_hotel_info_metabox'),
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * メタボックスの描画（2列モダンUI）
     */
    public function render_hotel_info_metabox($post) {
        wp_nonce_field('hrs_hotel_info_nonce', 'hrs_hotel_info_nonce_field');

        $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
        $hotel_address = get_post_meta($post->ID, '_hrs_hotel_address', true);
        $ota_urls = get_post_meta($post->ID, '_hrs_ota_urls', true) ?: array();

        $otas = array(
            'rakuten' => '楽天トラベル', 'jalan' => 'じゃらん', 'ikyu' => '一休.com', 
            'relux' => 'Relux', 'booking' => 'Booking.com', 'yahoo' => 'Yahoo!トラベル',
            'official' => '公式サイト', 'jtb' => 'JTB', 'rurubu' => 'るるぶトラベル',
            'yukoyuko' => 'ゆこゆこ', 'expedia' => 'Expedia'
        );
        ?>
        <style>
            .hrs-ui-wrapper { display: flex; gap: 20px; background: #f0f0f1; padding: 15px; border-radius: 4px; }
            .hrs-ui-col { flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
            .hrs-section-head { font-weight: bold; font-size: 14px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #2271b1; display: flex; align-items: center; gap: 8px; color: #1d2327; }
            .hrs-form-group { margin-bottom: 15px; }
            .hrs-form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; }
            .hrs-form-group input[type="text"], .hrs-form-group input[type="url"] { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; }
            .hrs-ai-panel { background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px dashed #c3c4c7; }
            #hrs-fetch-btn { width: 100%; padding: 10px; background: #2271b1; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px; transition: 0.2s; }
            #hrs-fetch-btn:hover { background: #135e96; }
            #hrs-fetch-btn:disabled { background: #a7aaad; cursor: not-allowed; }
            .hrs-ota-scroll { max-height: 450px; overflow-y: auto; padding-right: 10px; }
        </style>

        <div class="hrs-ui-wrapper">
            <div class="hrs-ui-col">
                <div class="hrs-section-head"><span class="dashicons dashicons-admin-home"></span> 基本情報 & AI取得</div>
                
                <div class="hrs-form-group">
                    <label for="hrs_hotel_name">ホテル名</label>
                    <input type="text" id="hrs_hotel_name" name="hrs_hotel_name" value="<?php echo esc_attr($hotel_name); ?>" placeholder="例：グランドメルキュール淡路島">
                </div>

                <div class="hrs-form-group">
                    <label for="hrs_hotel_address">所在地</label>
                    <input type="text" id="hrs_hotel_address" name="hrs_hotel_address" value="<?php echo esc_attr($hotel_address); ?>" placeholder="自動取得で入力されます">
                </div>

                <div class="hrs-ai-panel">
                    <p style="margin-top:0; font-size:12px; color:#64748b;">設定画面のAPIキーを使用して、住所とOTA各社のURLを検索・補完します。</p>
                    <button type="button" id="hrs-fetch-btn">
                        <span class="dashicons dashicons-update"></span> ChatGPTで情報を自動取得
                    </button>
                </div>
            </div>

            <div class="hrs-ui-col">
                <div class="hrs-section-head"><span class="dashicons dashicons-admin-links"></span> 各種予約サイトURL</div>
                <div class="hrs-ota-scroll">
                    <?php foreach ($otas as $key => $label) : ?>
                        <div class="hrs-form-group">
                            <label style="font-size: 11px;"><?php echo esc_html($label); ?></label>
                            <input type="url" name="hrs_ota_urls[<?php echo esc_attr($key); ?>]" id="ota_<?php echo esc_attr($key); ?>" value="<?php echo esc_url($ota_urls[$key] ?? ''); ?>" placeholder="https://...">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#hrs-fetch-btn').on('click', function() {
                const hotelName = $('#hrs_hotel_name').val();
                if(!hotelName) { alert('ホテル名を入力してください'); return; }

                const btn = $(this);
                const originalHtml = btn.html();
                btn.prop('disabled', true).text('探索中...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrs_fetch_hotel_data',
                        hotel_name: hotelName
                    },
                    success: function(response) {
                        if(response.success) {
                            const data = response.data;
                            if(data.address) $('#hrs_hotel_address').val(data.address);
                            $.each(data.urls, function(key, url) {
                                if(url) $('#ota_' + key).val(url);
                            });
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() { alert('通信に失敗しました。'); },
                    complete: function() { btn.prop('disabled', false).html(originalHtml); }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Ajax：ChatGPT APIを使用して情報を取得
     */
    public function ajax_fetch_hotel_data() {
        $hotel_name = sanitize_text_field($_POST['hotel_name']);
        $settings = get_option('hrs_settings'); 
        $api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        $model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-4o-mini';

        if (empty($api_key)) {
            wp_send_json_error('設定画面でAPIキーが設定されていません。');
        }

        $prompt = "以下のホテルの情報を特定してください。出力は必ずJSON形式のみとし、不明な項目は空文字にしてください。\n";
        $prompt .= "ホテル名: {$hotel_name}\n";
        $prompt .= "JSON形式: { \"address\": \"住所\", \"urls\": { \"rakuten\": \"\", \"jalan\": \"\", \"ikyu\": \"\", \"relux\": \"\", \"booking\": \"\", \"yahoo\": \"\", \"official\": \"\", \"jtb\": \"\", \"rurubu\": \"\", \"yukoyuko\": \"\", \"expedia\": \"\" } }";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'   => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'response_format' => array('type' => 'json_object')
            )),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('APIリクエストに失敗しました。');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            wp_send_json_error($body['error']['message']);
        }

        $result = json_decode($body['choices'][0]['message']['content'], true);
        wp_send_json_success($result);
    }

    /**
     * 保存処理
     */
    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['hrs_hotel_info_nonce_field']) || !wp_verify_nonce($_POST['hrs_hotel_info_nonce_field'], 'hrs_hotel_info_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        if (isset($_POST['hrs_hotel_name'])) update_post_meta($post_id, '_hrs_hotel_name', sanitize_text_field($_POST['hrs_hotel_name']));
        if (isset($_POST['hrs_hotel_address'])) update_post_meta($post_id, '_hrs_hotel_address', sanitize_text_field($_POST['hrs_hotel_address']));
        if (isset($_POST['hrs_ota_urls'])) {
            $sanitized_urls = array_map('esc_url_raw', $_POST['hrs_ota_urls']);
            update_post_meta($post_id, '_hrs_ota_urls', $sanitized_urls);
        }
    }

    /**
     * HQCカラム追加
     */
    public function add_hqc_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['hqc_score'] = 'HQC';
            }
        }
        return $new_columns;
    }

    /**
     * HQCカラム表示
     */
    public function render_hqc_column($column, $post_id) {
        if ($column === 'hqc_score') {
            $total = get_post_meta($post_id, '_hrs_hqc_score', true);
            $h = get_post_meta($post_id, '_hrs_hqc_h_score', true);
            $q = get_post_meta($post_id, '_hrs_hqc_q_score', true);
            $c = get_post_meta($post_id, '_hrs_hqc_c_score', true);
            
            if ($total !== '' && $total !== false) {
                echo '<div style="line-height:1.4;">';
                echo '<strong style="font-size:14px; color:#2271b1;">' . esc_html($total) . '</strong><br>';
                echo '<small style="color:#64748b; font-size:10px;">H:' . esc_html($h) . ' Q:' . esc_html($q) . ' C:' . esc_html($c) . '</small>';
                echo '</div>';
            } else {
                echo '<span style="color:#999; font-size:12px;">未分析</span>';
            }
        }
    }
}

new HRS_Custom_Post_Type();