<?php
/**
 * 楽天トラベル料金自動更新クラス
 *
 * @package Hotel_Review_System
 * @since 8.1.0
 * @version 8.1.4
 * 
 * CHANGELOG v8.1.4 (2026-02-12):
 * - ★ get_price_section_html() のOTA URLメタキーを修正
 *   article-generator が保存する hrp_booking_*_url に合わせた
 * - ★ 下段: じゃらん/一休/JTB/Booking.com 4横並び（2段対応）
 */

if (!defined('ABSPATH')) {
    exit;
}

$_hrs_color_utils_path = dirname(__DIR__) . '/utils/color-utils.php';
if (file_exists($_hrs_color_utils_path)) {
    require_once $_hrs_color_utils_path;
}
unset($_hrs_color_utils_path);

if (class_exists('HRS_Rakuten_Price_Updater')) {
    return;
}

class HRS_Rakuten_Price_Updater {

    private static $instance = null;
    private $api_endpoint = 'https://app.rakuten.co.jp/services/api/Travel/HotelDetailSearch/20170426';
    private $app_id = '';
    private $affiliate_id = '';
    private $moshimo_id = '';
    private $api_call_count = 0;
    private $api_call_timestamp = 0;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->app_id = $this->get_option_value(array(
            'hrs_rakuten_app_id', 'hrs_rakuten_application_id', 'hrs_rakuten_appid', 'rakuten_app_id',
        ));
        $this->affiliate_id = $this->get_option_value(array(
            'hrs_rakuten_affiliate_id', 'hrs_rakuten_moshimo_id', 'hrs_moshimo_id', 'rakuten_moshimo_id',
        ));
        $this->moshimo_id = $this->get_option_value(array(
            'hrs_moshimo_affiliate_id', 'hrs_moshimo_id',
        ));
        
        add_action('hrs_rakuten_price_update', array($this, 'run_scheduled_update'));
        if (!wp_next_scheduled('hrs_rakuten_price_update')) {
            wp_schedule_event(time() + 3600, 'weekly', 'hrs_rakuten_price_update');
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $next = wp_next_scheduled('hrs_rakuten_price_update');
            if ($next) {
                error_log('[HRS価格更新] 価格更新のcronスケジュール: ' . date('Y年m月d日 H:i:s', $next));
            }
        }
    }
    
    private function get_option_value($candidates) {
        foreach ($candidates as $name) {
            $val = get_option($name, '');
            if (!empty($val)) return $val;
        }
        return '';
    }

    public function fetch_price($hotel_no) {
        if (empty($this->app_id)) { error_log('[HRS価格更新] app_id が未設定'); return false; }
        if (!$this->check_rate_limit()) { error_log('[HRS価格更新] レート制限超過'); return false; }
        
        $params = array(
            'applicationId' => $this->app_id,
            'hotelNo'       => (int) $hotel_no,
            'responseType'  => 'small',
        );
        if (!empty($this->affiliate_id)) {
            $params['affiliateId'] = sanitize_text_field($this->affiliate_id);
        }
        
        $url = $this->api_endpoint . '?' . http_build_query($params);
        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'headers' => array('Accept' => 'application/json', 'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' HRS/8.1.4'),
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) { error_log('[HRS価格更新] APIエラー: ' . $response->get_error_message()); return false; }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) { error_log('[HRS価格更新] HTTPステータス: ' . $code); return false; }
        
        $data = @json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || !isset($data['hotels'][0]['hotel'][0]['hotelBasicInfo'])) { error_log('[HRS価格更新] レスポンス解析失敗'); return false; }
        
        $hotel = $data['hotels'][0]['hotel'][0]['hotelBasicInfo'];
        $reserve_url = !empty($hotel['planListUrl']) ? $hotel['planListUrl'] : '';
        if (!empty($this->moshimo_id) && !empty($reserve_url)) {
            $reserve_url = $this->convert_to_moshimo_url($reserve_url);
        }
        
        return array(
            'hotel_no'     => (int) $hotel['hotelNo'],
            'hotel_name'   => sanitize_text_field($hotel['hotelName']),
            'min_charge'   => isset($hotel['hotelMinCharge']) ? (int) $hotel['hotelMinCharge'] : 0,
            'reserve_url'  => esc_url($reserve_url),
            'review_avg'   => isset($hotel['reviewAverage']) ? (float) $hotel['reviewAverage'] : 0,
            'review_count' => isset($hotel['reviewCount']) ? (int) $hotel['reviewCount'] : 0,
            'updated_at'   => current_time('mysql'),
        );
    }
    
    private function convert_to_moshimo_url($url) {
        if (empty($this->moshimo_id)) return $url;
        return 'https://af.moshimo.com/af/c/click?a_id=' . $this->moshimo_id . '&p_id=54&pc_id=54&pl_id=616&url=' . urlencode($url);
    }

    private function get_hotel_no_for_post($post_id) {
        $hotel_no = get_post_meta($post_id, '_hrs_rakuten_hotel_no', true);
        if (!empty($hotel_no)) return $hotel_no;
        return get_post_meta($post_id, '_hrs_rakuten_hotel_id', true);
    }
    
    public function update_post_price($post_id) {
        $hotel_no = $this->get_hotel_no_for_post($post_id);
        if (empty($hotel_no)) return false;
        
        $price_data = $this->fetch_price($hotel_no);
        if (!$price_data) return false;
        
        update_post_meta($post_id, '_hrs_rakuten_price_data', $price_data);
        update_post_meta($post_id, '_hrs_rakuten_price_updated', current_time('mysql'));
        update_post_meta($post_id, '_hrs_min_price', $price_data['min_charge']);
        update_post_meta($post_id, '_hrs_price_last_updated', current_time('Y年n月j日 H:i'));
        if (!empty($price_data['reserve_url'])) {
            update_post_meta($post_id, 'hrp_rakuten_travel_url', $price_data['reserve_url']);
        }
        error_log('[HRS価格更新] 更新完了: post_id=' . $post_id . ' | hotel_no=' . $hotel_no . ' | min_charge=' . $price_data['min_charge']);
        return true;
    }

    public function update_price_for_post($post_id) { return $this->update_post_price($post_id); }
    public function update_price($post_id) { return $this->update_post_price($post_id); }
    
    public function run_scheduled_update() {
        $posts = get_posts(array(
            'post_type' => array('hotel-review', 'post'), 'posts_per_page' => -1,
            'meta_query' => array('relation' => 'OR',
                array('key' => '_hrs_rakuten_hotel_no', 'compare' => 'EXISTS'),
                array('key' => '_hrs_rakuten_hotel_id', 'compare' => 'EXISTS'),
            ), 'fields' => 'ids',
        ));
        if (empty($posts)) { error_log('[HRS価格更新] 更新対象の記事がありません'); return; }
        $updated = 0; $failed = 0;
        foreach ($posts as $post_id) {
            $this->update_post_price($post_id) ? $updated++ : $failed++;
            usleep(500000);
        }
        error_log('[HRS価格更新] スケジュール更新完了: 成功=' . $updated . ' | 失敗=' . $failed . ' | 合計=' . count($posts));
    }

    /**
     * ★ OTA URLをフォールバック付きで取得
     */
    private function get_ota_url($post_id, $keys) {
        foreach ($keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (!empty($val)) return $val;
        }
        return '';
    }

    /**
     * 料金セクションHTML生成
     * 上段: 楽天トラベル（料金 + ボタン）
     * 下段: じゃらん / 一休 / JTB / Booking.com（均等幅・2段対応）
     */
    public function get_price_section_html($post_id) {
        $rakuten_price = get_post_meta($post_id, '_hrs_rakuten_price_data', true);
        
        // ★ article-generator が保存するメタキー（hrp_booking_*_url）を優先
        $jalan_url   = $this->get_ota_url($post_id, array('hrp_booking_jalan_url', '_hrs_jalan_url'));
        $ikyu_url    = $this->get_ota_url($post_id, array('hrp_booking_ikyu_url', '_hrs_ikyu_url'));
        $jtb_url     = $this->get_ota_url($post_id, array('hrp_booking_jtb_url', '_hrs_jtb_url'));
        $booking_url = $this->get_ota_url($post_id, array('hrp_booking_bookingcom_url', '_hrs_booking_url', 'hrp_booking_com_url'));
        
        if (empty($rakuten_price) && empty($jalan_url) && empty($ikyu_url) && empty($jtb_url) && empty($booking_url)) {
            return '';
        }

        ob_start();
        ?>
        <div class="hrs-price-section">
            <h3>💰 料金・予約情報</h3>
            
            <?php if ($rakuten_price && !empty($rakuten_price['reserve_url'])): ?>
            <div class="hrs-price-rakuten">
                <div class="hrs-price-header">
                    <span class="hrs-ota-name">🏨 楽天トラベル</span>
                </div>
                <div class="hrs-price-top-row">
                    <div class="hrs-price-amount">
                        <span class="hrs-price-label">最安値</span>
                        <span class="hrs-price-value"><?php echo number_format($rakuten_price['min_charge']); ?>円〜</span>
                        <span class="hrs-price-unit">/ 1泊</span>
                    </div>
                    <a href="<?php echo esc_url($rakuten_price['reserve_url']); ?>" 
                       class="hrs-price-button hrs-price-button-rakuten" 
                       target="_blank" rel="nofollow noopener">
                        楽天トラベル
                    </a>
                </div>
                
                <?php
                $sub_buttons = array();
                if (!empty($jalan_url))   $sub_buttons[] = array('url' => $jalan_url,   'class' => 'jalan',   'label' => '🏨 じゃらん');
                if (!empty($ikyu_url))    $sub_buttons[] = array('url' => $ikyu_url,    'class' => 'ikyu',    'label' => '✨ 一休.com');
                if (!empty($jtb_url))     $sub_buttons[] = array('url' => $jtb_url,     'class' => 'jtb',     'label' => '🌐 JTB');
                if (!empty($booking_url)) $sub_buttons[] = array('url' => $booking_url, 'class' => 'booking', 'label' => '🌍 Booking.com');
                ?>
                <?php if (!empty($sub_buttons)): ?>
                <div class="hrs-price-sub-buttons" data-count="<?php echo count($sub_buttons); ?>">
                    <?php foreach ($sub_buttons as $btn): ?>
                    <a href="<?php echo esc_url($btn['url']); ?>" 
                       class="hrs-price-button hrs-price-button-<?php echo esc_attr($btn['class']); ?>" 
                       target="_blank" rel="nofollow noopener">
                        <?php echo esc_html($btn['label']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($rakuten_price['review_avg'])): ?>
                <div class="hrs-price-review">
                    <span class="hrs-review-stars">⭐ <?php echo number_format($rakuten_price['review_avg'], 1); ?></span>
                    <span class="hrs-review-count">(<?php echo number_format($rakuten_price['review_count']); ?>件)</span>
                </div>
                <?php endif; ?>
                <p class="hrs-price-updated">
                    最終更新: <?php echo esc_html(date('Y/m/d', strtotime($rakuten_price['updated_at']))); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <p class="hrs-price-note">※ 料金は時期・プランにより変動します。最新の空室状況は各サイトでご確認ください。<br>※ リンク先で宿泊プランが見つからない場合があります。</p>
        </div>

        <style>
        .hrs-price-section{background:#f8f9fa;border-radius:8px;padding:20px;margin:20px 0}
        .hrs-price-section h3{margin:0 0 15px;font-size:18px}
        .hrs-price-rakuten{background:#fff;border:2px solid #e63946;border-radius:8px;padding:15px;margin-bottom:15px}
        .hrs-price-header{margin-bottom:10px}
        .hrs-ota-name{font-weight:bold;font-size:16px}
        .hrs-price-top-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;margin:10px 0}
        .hrs-price-amount{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px}
        .hrs-price-label{font-size:14px;color:#666;font-weight:bold}
        .hrs-price-value{font-size:32px;font-weight:bold;color:#e63946}
        .hrs-price-unit{font-size:14px;color:#666}
        .hrs-price-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:6px;text-decoration:none!important;font-weight:bold;font-size:14px;color:#fff!important;white-space:nowrap;box-shadow:0 2px 4px rgba(0,0,0,.1);transition:all .2s ease}
        .hrs-price-button:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.15);color:#fff!important;text-decoration:none!important}
        .hrs-price-top-row .hrs-price-button{width:100%;padding:12px 16px;font-size:15px}
        .hrs-price-button-rakuten{background:#bf0000}
        .hrs-price-button-jalan{background:#ff6600}
        .hrs-price-button-ikyu{background:#1a1a2e}
        .hrs-price-button-jtb{background:#e63e12}
        .hrs-price-button-booking{background:#003580}
        .hrs-price-sub-buttons{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:12px 0 0}
        .hrs-price-sub-buttons[data-count="3"]{grid-template-columns:repeat(3,1fr)}
        .hrs-price-sub-buttons .hrs-price-button{width:100%;padding:10px 8px;font-size:13px}
        .hrs-price-review{margin:8px 0 0;font-size:14px}
        .hrs-review-stars{color:#f59e0b;font-weight:bold}
        .hrs-review-count{color:#666;font-size:12px;margin-left:4px}
        .hrs-price-updated{margin:10px 0 0;font-size:12px;color:#666}
        .hrs-price-note{font-size:11px;color:#999;margin-top:15px;line-height:1.6}
        @media(max-width:600px){
            .hrs-price-section{padding:15px}
            .hrs-price-top-row{grid-template-columns:1fr}
            .hrs-price-sub-buttons{grid-template-columns:1fr}
            .hrs-price-value{font-size:26px}
        }
        </style>
        <?php
        return ob_get_clean();
    }

    private function check_rate_limit() {
        $max_calls = 30;
        $now = time();
        if ($now - $this->api_call_timestamp >= 60) { $this->api_call_count = 0; $this->api_call_timestamp = $now; }
        if ($this->api_call_count >= $max_calls) return false;
        $this->api_call_count++;
        return true;
    }
    
    public function manual_update($post_id) {
        $start = microtime(true);
        $result = $this->update_post_price($post_id);
        $elapsed = round((microtime(true) - $start) * 1000);
        if ($result) {
            $price_data = get_post_meta($post_id, '_hrs_rakuten_price_data', true);
            return array('success' => true, 'message' => '料金を更新しました', 'min_charge' => $price_data['min_charge'], 'elapsed' => $elapsed . 'ms');
        }
        return array('success' => false, 'message' => '料金の取得に失敗しました', 'elapsed' => $elapsed . 'ms');
    }
    
    public static function deactivate() {
        $ts = wp_next_scheduled('hrs_rakuten_price_update');
        if ($ts) wp_unschedule_event($ts, 'hrs_rakuten_price_update');
    }
}

if (!function_exists('hrs_rakuten_price_updater')) {
    function hrs_rakuten_price_updater() {
        return HRS_Rakuten_Price_Updater::get_instance();
    }
}