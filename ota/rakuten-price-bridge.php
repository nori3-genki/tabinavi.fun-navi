<?php
/**
 * 楽天価格更新ブリッジ v1.3
 * 配置先: includes/ota/rakuten-price-bridge.php
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('hrs_rakuten_price_updater')) {
    function hrs_rakuten_price_updater() {
        static $instance = null;
        if (null === $instance && class_exists('HRS_Rakuten_Price_Updater')) {
            $instance = new HRS_Rakuten_Price_Updater();
        }
        return $instance;
    }
}

// ショートコード [hrs_price_section]
add_shortcode('hrs_price_section', 'hrs_bridge_price_section_shortcode');
function hrs_bridge_price_section_shortcode($atts) {
    global $post;
    if (!$post) return '';
    if (class_exists('HRS_Rakuten_Price_Updater')) {
        $updater = hrs_rakuten_price_updater();
        if ($updater && method_exists($updater, 'get_price_section_html')) {
            $html = $updater->get_price_section_html($post->ID);
            if (!empty($html)) return $html;
        }
    }
    return '<!-- hrs_price_section: データ未取得 -->';
}

// AJAX「今すぐ更新」修正
add_action('plugins_loaded', function() {
    if (class_exists('HRS_API_Meta_Box')) {
        $mb = HRS_API_Meta_Box::get_instance();
        remove_action('wp_ajax_hrs_update_single_price', array($mb, 'ajax_update_single_price'));
    }
}, 20);

add_action('wp_ajax_hrs_update_single_price', 'hrs_bridge_update_single_price');
function hrs_bridge_update_single_price() {
    check_ajax_referer('hrs_api_metabox_nonce', 'nonce');
    $post_id  = intval($_POST['post_id'] ?? 0);
    $hotel_id = sanitize_text_field($_POST['hotel_id'] ?? '');
    if (!$post_id || !current_user_can('edit_post', $post_id)) wp_send_json_error(array('message' => '権限がありません'));
    if (!empty($hotel_id)) update_post_meta($post_id, '_hrs_rakuten_hotel_no', $hotel_id);
    
    $hotel_no = get_post_meta($post_id, '_hrs_rakuten_hotel_no', true);
    if (empty($hotel_no)) wp_send_json_error(array('message' => '楽天ホテルIDが設定されていません'));
    
    $app_id = '';
    foreach (array('hrs_rakuten_app_id', 'hrs_rakuten_application_id', 'hrs_rakuten_appid', 'rakuten_app_id') as $k) {
        $v = get_option($k, ''); if (!empty($v)) { $app_id = $v; break; }
    }
    if (empty($app_id)) wp_send_json_error(array('message' => '楽天APIのApp IDが未設定です'));
    
    $api_url = 'https://app.rakuten.co.jp/services/api/Travel/HotelDetailSearch/20170426'
        . '?applicationId=' . urlencode($app_id) . '&hotelNo=' . intval($hotel_no) . '&responseType=small&format=json';
    $response = wp_remote_get($api_url, array('timeout' => 10));
    if (is_wp_error($response)) wp_send_json_error(array('message' => 'API通信エラー: ' . $response->get_error_message()));
    
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) wp_send_json_error(array('message' => 'APIエラー HTTP ' . $code));
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['hotels'][0]['hotel'][0]['hotelBasicInfo'])) wp_send_json_error(array('message' => 'ホテル情報なし (hotel_no=' . $hotel_no . ')'));
    
    $hotel = $data['hotels'][0]['hotel'][0]['hotelBasicInfo'];
    $min_charge   = isset($hotel['hotelMinCharge']) ? (int)$hotel['hotelMinCharge'] : 0;
    $hotel_name   = $hotel['hotelName'] ?? '';
    $review_avg   = isset($hotel['reviewAverage']) ? (float)$hotel['reviewAverage'] : 0;
    $review_count = isset($hotel['reviewCount']) ? (int)$hotel['reviewCount'] : 0;
    $reserve_url  = $hotel['planListUrl'] ?? '';
    
    $moshimo_id = '';
    foreach (array('hrs_moshimo_affiliate_id', 'hrs_moshimo_id') as $k) {
        $v = get_option($k, ''); if (!empty($v)) { $moshimo_id = $v; break; }
    }
    if (!empty($moshimo_id) && !empty($reserve_url)) {
        $reserve_url = 'https://af.moshimo.com/af/c/click?a_id=' . $moshimo_id . '&p_id=54&pc_id=54&pl_id=616&url=' . urlencode($reserve_url);
    }
    
    $price_data = array('hotel_no' => (int)$hotel_no, 'hotel_name' => $hotel_name, 'min_charge' => $min_charge, 'reserve_url' => $reserve_url, 'review_avg' => $review_avg, 'review_count' => $review_count, 'updated_at' => current_time('mysql'));
    update_post_meta($post_id, '_hrs_rakuten_price_data', $price_data);
    update_post_meta($post_id, '_hrs_rakuten_price_updated', current_time('mysql'));
    update_post_meta($post_id, '_hrs_rakuten_min_charge', $min_charge);
    update_post_meta($post_id, '_hrs_min_price', $min_charge);
    update_post_meta($post_id, '_hrs_price_last_updated', current_time('Y/m/d'));
    if (!empty($reserve_url)) update_post_meta($post_id, 'hrp_rakuten_travel_url', $reserve_url);
    delete_post_meta($post_id, '_hrs_api_error');
    
    wp_send_json_success(array('message' => '価格を更新しました: ¥' . number_format($min_charge) . '〜 (' . $hotel_name . ')', 'available' => true, 'min_charge' => $min_charge));
}