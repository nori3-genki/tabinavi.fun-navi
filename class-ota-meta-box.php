<?php
/**
 * OTA情報メタボックス（簡略版）
 * 
 * 基本情報と楽天トラベル施設番号のみ
 * OTAリンクは別のメタボックスで管理
 * 
 * @package Hotel_Review_System
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_OTA_Meta_Box {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_hotel-review', array($this, 'save_meta_box'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_box() {
        add_meta_box(
            'hrs_ota_info',
            '🏨 基本情報・API設定',
            array($this, 'render_meta_box'),
            'hotel-review',
            'normal',
            'high'
        );
    }

    /**
     * メタボックスを表示
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_meta_box($post) {
        // Nonce
        wp_nonce_field('hrs_ota_meta_box', 'hrs_ota_meta_box_nonce');

        // 現在の値を取得
        $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
        $rakuten_hotel_no = get_post_meta($post->ID, '_hrs_rakuten_hotel_no', true);
        
        // 料金情報
        $rakuten_min_charge = get_post_meta($post->ID, '_hrs_rakuten_min_charge', true);
        $rakuten_price_updated = get_post_meta($post->ID, '_hrs_rakuten_price_updated', true);
        ?>
        
        <div class="hrs-ota-meta-box">
            <!-- 基本情報 -->
            <div class="hrs-meta-section">
                <h4>📋 基本情報</h4>
                <table class="hrs-meta-table">
                    <tr>
                        <th><label for="hrs_hotel_name">ホテル名</label></th>
                        <td>
                            <input type="text" 
                                   id="hrs_hotel_name" 
                                   name="hrs_hotel_name" 
                                   value="<?php echo esc_attr($hotel_name); ?>" 
                                   class="large-text"
                                   placeholder="例: ホテルニューグランド">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 楽天トラベル施設番号（API用） -->
            <div class="hrs-meta-section hrs-meta-rakuten">
                <h4>
                    <span class="hrs-ota-badge hrs-badge-rakuten">楽天トラベル</span>
                    <span class="hrs-api-badge">API料金取得対応</span>
                </h4>
                <table class="hrs-meta-table">
                    <tr>
                        <th><label for="hrs_rakuten_hotel_no">施設番号</label></th>
                        <td>
                            <input type="text" 
                                   id="hrs_rakuten_hotel_no" 
                                   name="hrs_rakuten_hotel_no" 
                                   value="<?php echo esc_attr($rakuten_hotel_no); ?>" 
                                   class="regular-text"
                                   placeholder="例: 123456">
                            <p class="description">
                                楽天トラベルの施設番号（URLの f_no= の値）<br>
                                ※ 予約リンクは下の「OTAリンク」で設定してください
                            </p>
                        </td>
                    </tr>
                    <?php if ($rakuten_min_charge): ?>
                    <tr>
                        <th>取得済み料金</th>
                        <td>
                            <span class="hrs-price-display">
                                <?php echo number_format($rakuten_min_charge); ?>円〜
                            </span>
                            <span class="hrs-price-updated">
                                (更新: <?php echo esc_html($rakuten_price_updated); ?>)
                            </span>
                            <button type="button" class="button button-small hrs-refresh-price" data-post-id="<?php echo $post->ID; ?>">
                                <span class="dashicons dashicons-update"></span> 料金を更新
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- ヘルプ -->
            <div class="hrs-meta-help">
                <details>
                    <summary>💡 施設番号の取得方法</summary>
                    <div class="hrs-help-content">
                        <ol>
                            <li>楽天トラベルでホテルを検索</li>
                            <li>ホテル詳細ページを開く</li>
                            <li>URLの <code>f_no=</code> の後ろの数字が施設番号</li>
                        </ol>
                        <p>例: https://travel.rakuten.co.jp/.../hotel_no=<strong>123456</strong></p>
                    </div>
                </details>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 料金更新ボタン
            $('.hrs-refresh-price').on('click', function() {
                var $btn = $(this);
                var postId = $btn.data('post-id');
                
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrs_refresh_rakuten_price',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('hrs_refresh_price'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('エラー: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * メタボックスを保存
     *
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     */
    public function save_meta_box($post_id, $post) {
        // Nonceチェック
        if (!isset($_POST['hrs_ota_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['hrs_ota_meta_box_nonce'], 'hrs_ota_meta_box')) {
            return;
        }

        // 自動保存をスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // フィールドを保存
        $fields = array(
            'hrs_hotel_name' => '_hrs_hotel_name',
            'hrs_rakuten_hotel_no' => '_hrs_rakuten_hotel_no',
        );

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }

    /**
     * スタイルを読み込み
     *
     * @param string $hook フック名
     */
    public function enqueue_styles($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'hotel-review') {
            wp_add_inline_style('wp-admin', $this->get_inline_styles());
        }
    }

    /**
     * インラインスタイルを取得
     *
     * @return string
     */
    private function get_inline_styles() {
        return '
            .hrs-ota-meta-box {
                padding: 10px 0;
            }
            .hrs-meta-section {
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
            }
            .hrs-meta-section h4 {
                margin: 0 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .hrs-meta-rakuten {
                border-color: #bf0000;
                background: #fff5f5;
            }
            .hrs-meta-table {
                width: 100%;
                border-collapse: collapse;
            }
            .hrs-meta-table th {
                width: 120px;
                padding: 8px 10px 8px 0;
                vertical-align: top;
                font-weight: 500;
            }
            .hrs-meta-table td {
                padding: 8px 0;
            }
            .hrs-meta-table input[type="text"] {
                width: 100%;
            }
            .hrs-ota-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
            }
            .hrs-badge-rakuten { background: #bf0000; }
            .hrs-api-badge {
                background: #46b450;
                color: #fff;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
            }
            .hrs-price-display {
                font-size: 16px;
                font-weight: 600;
                color: #bf0000;
            }
            .hrs-price-updated {
                color: #666;
                font-size: 12px;
                margin-left: 10px;
            }
            .hrs-refresh-price {
                margin-left: 10px !important;
            }
            .hrs-refresh-price .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
                margin-top: 3px;
            }
            .hrs-refresh-price .dashicons.spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                100% { transform: rotate(360deg); }
            }
            .hrs-meta-help {
                margin-top: 15px;
            }
            .hrs-meta-help summary {
                cursor: pointer;
                color: #0073aa;
                font-weight: 500;
            }
            .hrs-help-content {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                margin-top: 10px;
            }
            .hrs-help-content ol {
                margin-left: 20px;
            }
            .hrs-help-content code {
                background: #f0f0f0;
                padding: 2px 5px;
                border-radius: 3px;
            }
        ';
    }
}

// 初期化
new HRS_OTA_Meta_Box();

// ============================================
// 【AJAXハンドラー】料金更新
// ============================================

/**
 * 楽天料金を手動更新
 */
function hrs_ajax_refresh_rakuten_price() {
    check_ajax_referer('hrs_refresh_price', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => '権限がありません'));
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (!$post_id) {
        wp_send_json_error(array('message' => '投稿IDが不正です'));
    }
    
    // 料金更新クラスを使用
    if (function_exists('hrs_rakuten_price_updater')) {
        $updater = hrs_rakuten_price_updater();
        $result = $updater->update_price_for_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        if ($result['available']) {
            wp_send_json_success(array(
                'message' => '料金を更新しました',
                'price' => $result['min_charge'],
            ));
        } else {
            wp_send_json_error(array('message' => '料金情報を取得できませんでした'));
        }
    } else {
        wp_send_json_error(array('message' => '料金更新機能が無効です'));
    }
}
add_action('wp_ajax_hrs_refresh_rakuten_price', 'hrs_ajax_refresh_rakuten_price');