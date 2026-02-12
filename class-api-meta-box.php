<?php
/**
 * API連動メタボックス
 * 
 * 投稿編集画面で楽天ホテルIDや更新状況を表示・管理
 * 
 * @package HRS
 * @version 6.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_API_Meta_Box {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box'));
        add_action('wp_ajax_hrs_update_single_price', array($this, 'ajax_update_single_price'));
        add_action('wp_ajax_hrs_search_rakuten_hotel', array($this, 'ajax_search_hotel'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'hrs_api_connection',
            '🔌 API連動設定',
            array($this, 'render_meta_box'),
            array('post', 'hotel-review'),
            'side',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('hrs_api_metabox', 'hrs_api_metabox_nonce');
        
        // メタキーを _hrs_rakuten_hotel_no に統一
        $rakuten_hotel_id = get_post_meta($post->ID, '_hrs_rakuten_hotel_no', true);
        $last_updated = get_post_meta($post->ID, '_hrs_rakuten_price_updated', true);
        $cached_price = get_post_meta($post->ID, '_hrs_rakuten_min_charge', true);
        $api_error = get_post_meta($post->ID, '_hrs_api_error', true);
        
        $status_message = '未設定';
        $status_color = '#666';
        
        if (!empty($rakuten_hotel_id)) {
            if (!empty($api_error)) {
                $status_message = 'エラー';
                $status_color = '#d63638';
            } elseif (empty($last_updated)) {
                $status_message = '未取得';
                $status_color = '#dba617';
            } else {
                $hours_ago = (time() - strtotime($last_updated)) / 3600;
                if ($hours_ago < 24) {
                    $status_message = '最新 (' . round($hours_ago, 1) . '時間前)';
                    $status_color = '#00a32a';
                } else {
                    $status_message = '要更新 (' . round($hours_ago / 24, 1) . '日前)';
                    $status_color = '#dba617';
                }
            }
        }
        ?>
        <style>
            .hrs-api-metabox label { display: block; margin-bottom: 5px; font-weight: 600; }
            .hrs-api-metabox input[type="text"] { width: 100%; margin-bottom: 10px; }
            .hrs-api-metabox .hrs-status { padding: 8px; border-radius: 4px; margin-bottom: 10px; }
            .hrs-api-metabox .hrs-button-row { display: flex; gap: 5px; margin-top: 10px; }
            .hrs-api-metabox .hrs-button-row button { flex: 1; }
        </style>

        <div class="hrs-api-metabox">
            <div class="hrs-status" style="background: <?php echo esc_attr($status_color); ?>20; border-left: 4px solid <?php echo esc_attr($status_color); ?>;">
                <strong>ステータス:</strong> 
                <span style="color: <?php echo esc_attr($status_color); ?>;"><?php echo esc_html($status_message); ?></span>
            </div>

            <label for="hrs_rakuten_hotel_id">楽天ホテルID</label>
            <input type="text" id="hrs_rakuten_hotel_id" name="hrs_rakuten_hotel_id" value="<?php echo esc_attr($rakuten_hotel_id); ?>" placeholder="例: 12345">
            
            <p style="font-size: 11px; color: #666;"><a href="#" id="hrs-search-hotel">🔍 ホテル名で検索</a></p>

            <div id="hrs-hotel-search-modal" style="display: none; padding: 10px; background: #f9f9f9; border-radius: 4px; margin-bottom: 10px;">
                <input type="text" id="hrs-hotel-search-input" placeholder="ホテル名を入力" style="width: 100%; margin-bottom: 5px;">
                <button type="button" id="hrs-do-search" class="button button-small">検索</button>
                <div id="hrs-search-results" style="max-height: 150px; overflow-y: auto; margin-top: 10px;"></div>
            </div>

            <?php if (!empty($cached_price)) : ?>
                <div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #4caf50;">
                    <strong>💰 取得済み料金</strong><br>
                    <span style="font-size: 18px; font-weight: bold; color: #2e7d32;">¥<?php echo number_format($cached_price); ?>〜</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($api_error)) : ?>
                <div style="background: #fce4e4; padding: 8px; border-radius: 4px; margin-bottom: 10px; color: #d63638;">
                    <strong>⚠️ エラー:</strong> <?php echo esc_html($api_error); ?>
                </div>
            <?php endif; ?>

            <div class="hrs-button-row">
                <button type="button" id="hrs-update-price" class="button button-primary" <?php echo empty($rakuten_hotel_id) ? 'disabled' : ''; ?>>🔄 今すぐ更新</button>
            </div>

            <p style="font-size: 11px; color: #666; margin-top: 10px;">
                最終更新: <?php echo $last_updated ? esc_html($last_updated) : '未更新'; ?>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var ajaxNonce = '<?php echo wp_create_nonce('hrs_api_metabox_nonce'); ?>';
            var postId = <?php echo $post->ID; ?>;

            $('#hrs-search-hotel').on('click', function(e) {
                e.preventDefault();
                $('#hrs-hotel-search-modal').slideToggle();
            });

            $('#hrs-do-search').on('click', function() {
                var keyword = $('#hrs-hotel-search-input').val();
                if (!keyword) return;
                
                $('#hrs-search-results').html('<p>検索中...</p>');
                $.post(ajaxurl, {
                    action: 'hrs_search_rakuten_hotel',
                    nonce: ajaxNonce,
                    keyword: keyword
                }, function(response) {
                    if (response.success && response.data.hotels) {
                        var html = '';
                        response.data.hotels.forEach(function(hotel) {
                            html += '<div style="padding:5px;border-bottom:1px solid #eee;cursor:pointer;" data-id="' + hotel.id + '" class="hrs-hotel-result"><strong>' + hotel.name + '</strong><br><small>' + hotel.area + '</small></div>';
                        });
                        $('#hrs-search-results').html(html || '<p>見つかりませんでした</p>');
                    } else {
                        $('#hrs-search-results').html('<p>エラーが発生しました</p>');
                    }
                });
            });

            $(document).on('click', '.hrs-hotel-result', function() {
                $('#hrs_rakuten_hotel_id').val($(this).data('id'));
                $('#hrs-hotel-search-modal').slideUp();
                $('#hrs-update-price').prop('disabled', false);
            });

            $('#hrs-update-price').on('click', function() {
                var $btn = $(this);
                var hotelId = $('#hrs_rakuten_hotel_id').val();
                if (!hotelId) { 
                    alert('楽天ホテルIDを入力してください'); 
                    return; 
                }
                
                $btn.prop('disabled', true).text('更新中...');
                $.post(ajaxurl, {
                    action: 'hrs_update_single_price',
                    nonce: ajaxNonce,
                    post_id: postId,
                    hotel_id: hotelId
                }, function(response) {
                    if (response.success) {
                        alert('更新完了: ' + response.data.message);
                        location.reload();
                    } else {
                        alert('エラー: ' + response.data.message);
                    }
                    $btn.prop('disabled', false).text('🔄 今すぐ更新');
                });
            });
        });
        </script>
        <?php
    }

    public function save_meta_box($post_id) {
        if (!isset($_POST['hrs_api_metabox_nonce']) || !wp_verify_nonce($_POST['hrs_api_metabox_nonce'], 'hrs_api_metabox')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['hrs_rakuten_hotel_id'])) {
            $hotel_id = sanitize_text_field($_POST['hrs_rakuten_hotel_id']);
            // メタキーを _hrs_rakuten_hotel_no に統一
            update_post_meta($post_id, '_hrs_rakuten_hotel_no', $hotel_id);
            if (!empty($hotel_id)) {
                delete_post_meta($post_id, '_hrs_api_error');
            }
        }
    }

    public function ajax_update_single_price() {
        check_ajax_referer('hrs_api_metabox_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $hotel_id = sanitize_text_field($_POST['hotel_id']);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        // メタキーを _hrs_rakuten_hotel_no に統一
        update_post_meta($post_id, '_hrs_rakuten_hotel_no', $hotel_id);
        
        if (function_exists('hrs_rakuten_price_updater')) {
            $updater = hrs_rakuten_price_updater();
            $result = $updater->update_price_for_post($post_id);
            
            if (!is_wp_error($result) && $result['available']) {
                wp_send_json_success(array('message' => '価格を更新しました: ' . number_format($result['min_charge']) . '円'));
            } else {
                $error = is_wp_error($result) ? $result->get_error_message() : '料金情報を取得できませんでした';
                wp_send_json_error(array('message' => $error));
            }
        } else {
            wp_send_json_error(array('message' => '価格更新モジュールが見つかりません'));
        }
    }

    public function ajax_search_hotel() {
        check_ajax_referer('hrs_api_metabox_nonce', 'nonce');
        
        $keyword = sanitize_text_field($_POST['keyword']);
        if (empty($keyword)) {
            wp_send_json_error(array('message' => 'キーワードを入力してください'));
        }
        
        $app_id = get_option('hrs_rakuten_app_id', '');
        if (empty($app_id)) {
            wp_send_json_error(array('message' => '楽天APIが設定されていません'));
        }
        
        $url = 'https://app.rakuten.co.jp/services/api/Travel/KeywordHotelSearch/20170426';
        $url .= '?format=json';
        $url .= '&applicationId=' . urlencode($app_id);
        $url .= '&keyword=' . urlencode($keyword);
        $url .= '&hits=10';
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => '検索エラー: ' . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $hotels = array();
        
        if (!empty($body['hotels'])) {
            foreach ($body['hotels'] as $hotel) {
                $info = $hotel['hotel'][0]['hotelBasicInfo'];
                $hotels[] = array(
                    'id' => $info['hotelNo'],
                    'name' => $info['hotelName'],
                    'area' => $info['address1'] . $info['address2'],
                );
            }
        }
        
        wp_send_json_success(array('hotels' => $hotels));
    }
}

// 初期化
HRS_API_Meta_Box::get_instance();