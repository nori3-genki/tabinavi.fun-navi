<?php
/**
 * @version 4.7.0-MULTI-IMAGE
 * 楽天画像取得 - 3画像対応版
 */

if (!defined('ABSPATH')) exit;

class HRS_Rakuten_Image_Fetcher {
    
    private $keyword_endpoint = 'https://app.rakuten.co.jp/services/api/Travel/KeywordHotelSearch/20170426';
    private $detail_endpoint  = 'https://app.rakuten.co.jp/services/api/Travel/HotelDetailSearch/20170426';
    private $app_id = '';
    private $affiliate_id = '';
    private $image_cache = array();
    private $multi_image_cache = array();
    private $api_call_count = 0;
    private $api_call_timestamp = 0;
    private $option_app_id = '';
    private $option_affiliate_id = '';
    
    public function __construct() {
        $this->option_app_id = $this->get_correct_option_name('app_id');
        $this->option_affiliate_id = $this->get_correct_option_name('affiliate_id');
        
        $this->app_id = get_option($this->option_app_id, '');
        $this->affiliate_id = get_option($this->option_affiliate_id, '');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Rakuten] Initialized: app_id=' . $this->option_app_id . ' | affiliate_id=' . $this->option_affiliate_id);
            error_log('[HRS Rakuten] Values: app_id=' . substr($this->app_id, 0, 10) . '*** | affiliate_id=' . $this->affiliate_id);
        }
    }
    
    private function get_correct_option_name($field_type) {
        $candidates = array();
        
        if ($field_type === 'app_id') {
            $candidates = array(
                'hrs_rakuten_app_id',
                'hrs_rakuten_application_id',
                'hrs_rakuten_appid',
                'rakuten_app_id',
                'hrs_rakuten_api_key',
            );
        } elseif ($field_type === 'affiliate_id') {
            $candidates = array(
                'hrs_rakuten_affiliate_id',
                'hrs_rakuten_moshimo_id',
                'hrs_moshimo_id',
                'rakuten_moshimo_id',
                'hrs_rakuten_commission_id',
            );
        }
        
        foreach ($candidates as $option_name) {
            $value = get_option($option_name, '');
            if (!empty($value)) {
                error_log('[HRS Rakuten] Found option: ' . $option_name . ' = ' . substr($value, 0, 10) . '***');
                return $option_name;
            }
        }
        
        return ($field_type === 'app_id') 
            ? 'hrs_rakuten_app_id' 
            : 'hrs_rakuten_affiliate_id';
    }
    
    public function diagnose_api_config() {
        return array(
            'app_id_option' => $this->option_app_id,
            'app_id_value' => substr($this->app_id, 0, 10) . (strlen($this->app_id) > 10 ? '***' : ''),
            'app_id_empty' => empty($this->app_id),
            'affiliate_id_option' => $this->option_affiliate_id,
            'affiliate_id_value' => $this->affiliate_id,
            'affiliate_id_empty' => empty($this->affiliate_id),
            'keyword_endpoint' => $this->keyword_endpoint,
            'detail_endpoint' => $this->detail_endpoint,
            'ready' => !empty($this->app_id),
        );
    }
    
    public function fetch_hotel_images($hotel_name) {
        if (empty($this->app_id) || empty($hotel_name)) {
            error_log('[HRS Rakuten] Missing config: app_id=' . (empty($this->app_id) ? 'EMPTY' : 'OK'));
            return false;
        }
        
        $cache_key = md5($hotel_name . '_multi');
        if (isset($this->multi_image_cache[$cache_key])) {
            return $this->multi_image_cache[$cache_key];
        }
        
        try {
            if (!$this->check_rate_limit()) {
                error_log('[HRS Rakuten] Rate limit exceeded');
                return false;
            }
            
            $hotel_info = $this->search_hotel($hotel_name);
            if (!$hotel_info || empty($hotel_info['hotel_no'])) {
                error_log('[HRS Rakuten] No hotel found for: ' . $hotel_name);
                return false;
            }
            
            $detail_info = $this->fetch_hotel_detail($hotel_info['hotel_no']);
            $result = $this->merge_image_data($hotel_info, $detail_info);
            $this->multi_image_cache[$cache_key] = $result;
            
            error_log('[HRS Rakuten] Multi-image result: ' . count($result['all_images']) . ' images found for: ' . $hotel_name);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('[HRS Rakuten] Error in fetch_hotel_images: ' . $e->getMessage());
            return false;
        }
    }
    
    public function fetch_hotel_image($hotel_name) {
        if (empty($this->app_id) || empty($hotel_name)) {
            error_log('[HRS Rakuten] Missing config: app_id=' . (empty($this->app_id) ? 'EMPTY' : 'OK'));
            return false;
        }
        
        $cache_key = md5($hotel_name);
        if (isset($this->image_cache[$cache_key])) {
            return $this->image_cache[$cache_key];
        }
        
        try {
            if (!$this->check_rate_limit()) {
                error_log('[HRS Rakuten] Rate limit exceeded');
                return false;
            }
            
            $hotel_info = $this->search_hotel($hotel_name);
            if (!$hotel_info || empty($hotel_info['image_url'])) {
                error_log('[HRS Rakuten] No image found for: ' . $hotel_name);
                return false;
            }
            
            $this->image_cache[$cache_key] = $hotel_info['image_url'];
            return $hotel_info['image_url'];
            
        } catch (Exception $e) {
            error_log('[HRS Rakuten] Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function fetch_hotel_detail($hotel_no) {
        if (!$this->check_rate_limit()) {
            error_log('[HRS Rakuten] Rate limit for detail search');
            return false;
        }
        
        $params = array(
            'applicationId' => $this->app_id,
            'hotelNo'       => (int) $hotel_no,
            'responseType'  => 'large',
        );
        
        if (!empty($this->affiliate_id)) {
            $params['affiliateId'] = sanitize_text_field($this->affiliate_id);
        }
        
        $url = $this->detail_endpoint . '?' . http_build_query($params);
        
        error_log('[HRS Rakuten] Detail API URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' HRS/4.7.0',
            ),
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            error_log('[HRS Rakuten] Detail API WP Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            error_log('[HRS Rakuten] Detail API error - Status: ' . $code);
            return false;
        }
        
        $data = @json_decode($body, true);
        if ($data === null) {
            error_log('[HRS Rakuten] Detail API JSON parse failed');
            return false;
        }
        
        if (!isset($data['hotels'][0]['hotel'][0]['hotelBasicInfo'])) {
            error_log('[HRS Rakuten] Detail API unexpected structure');
            return false;
        }
        
        $hotel = $data['hotels'][0]['hotel'][0]['hotelBasicInfo'];
        
        return array(
            'hotel_no'        => isset($hotel['hotelNo']) ? (int) $hotel['hotelNo'] : 0,
            'hotel_name'      => isset($hotel['hotelName']) ? sanitize_text_field($hotel['hotelName']) : '',
            'hotel_image'     => !empty($hotel['hotelImageUrl']) ? esc_url($hotel['hotelImageUrl']) : '',
            'room_image'      => !empty($hotel['roomImageUrl']) ? esc_url($hotel['roomImageUrl']) : '',
            'map_image'       => !empty($hotel['hotelMapImageUrl']) ? esc_url($hotel['hotelMapImageUrl']) : '',
            'thumbnail'       => !empty($hotel['hotelThumbnailUrl']) ? esc_url($hotel['hotelThumbnailUrl']) : '',
            'room_thumbnail'  => !empty($hotel['roomThumbnailUrl']) ? esc_url($hotel['roomThumbnailUrl']) : '',
            'hotel_special'   => isset($hotel['hotelSpecial']) ? $hotel['hotelSpecial'] : '',
        );
    }
    
    private function merge_image_data($keyword_result, $detail_result) {
        $source = $detail_result ?: $keyword_result;
        
        $hotel_image    = !empty($source['hotel_image']) ? $source['hotel_image'] 
                        : (!empty($keyword_result['image_url']) ? $keyword_result['image_url'] : '');
        $room_image     = !empty($source['room_image']) ? $source['room_image'] : '';
        $map_image      = !empty($source['map_image']) ? $source['map_image'] : '';
        $thumbnail      = !empty($source['thumbnail']) ? $source['thumbnail'] 
                        : (!empty($keyword_result['thumbnail_url']) ? $keyword_result['thumbnail_url'] : '');
        $room_thumbnail = !empty($source['room_thumbnail']) ? $source['room_thumbnail'] : '';
        
        $all_images = array_values(array_filter(array(
            $hotel_image,
            $room_image,
            $map_image,
        )));
        
        $article_images = array_values(array_filter(array(
            $hotel_image,
            $room_image,
        )));
        
        return array(
            'hotel_no'        => isset($keyword_result['hotel_no']) ? $keyword_result['hotel_no'] : 0,
            'hotel_name'      => isset($keyword_result['hotel_name']) ? $keyword_result['hotel_name'] : '',
            'hotel_image'     => $hotel_image,
            'room_image'      => $room_image,
            'map_image'       => $map_image,
            'thumbnail'       => $thumbnail,
            'room_thumbnail'  => $room_thumbnail,
            'all_images'      => $all_images,
            'article_images'  => $article_images,
            'image_count'     => count($all_images),
        );
    }
    
    private function search_hotel($hotel_name) {
        $strategies = array(
            array('keyword' => $this->build_with_region($hotel_name), 'name' => 'normal+region'),
            array('keyword' => $this->sanitize_rakuten_keyword($hotel_name), 'name' => 'safe_normal'),
            array('keyword' => $this->extract_first_word($hotel_name), 'name' => 'first_word'),
            array('keyword' => $this->build_exact_with_region($hotel_name), 'name' => 'exact+region'),
            array('keyword' => '"' . sanitize_text_field($hotel_name) . '"', 'name' => 'exact'),
        );
        
        foreach ($strategies as $strategy) {
            if (empty($strategy['keyword'])) continue;
            
            try {
                error_log('[HRS Rakuten] Trying strategy: ' . $strategy['name'] . ' | keyword: ' . $strategy['keyword']);
                $result = $this->execute_search($strategy['keyword']);
                if ($result !== false) {
                    error_log('[HRS Rakuten] Success with strategy: ' . $strategy['name']);
                    return $result;
                }
            } catch (Exception $e) {
                error_log('[HRS Rakuten] Strategy failed: ' . $strategy['name'] . ' | ' . $e->getMessage());
                continue;
            }
        }
        
        error_log('[HRS Rakuten] All strategies failed for: ' . $hotel_name);
        return false;
    }
    
    private function execute_search($keyword) {
        $params = array(
            'applicationId' => $this->app_id,
            'keyword'       => $keyword,
            'hits'          => 1,
            'responseType'  => 'small',
        );
        
        if (!empty($this->affiliate_id)) {
            $params['affiliateId'] = sanitize_text_field($this->affiliate_id);
        }
        
        $url = $this->keyword_endpoint . '?' . http_build_query($params);
        
        error_log('[HRS Rakuten] API URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . ' HRS/4.7.0',
            ),
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('[HRS Rakuten] WP Error: ' . $error_msg);
            throw new Exception('API request failed: ' . $error_msg);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('[HRS Rakuten] Response code: ' . $code);
        
        if ($code !== 200) {
            error_log('[HRS Rakuten] API error - Status: ' . $code . ' | Body: ' . substr($body, 0, 200));
            throw new Exception('API error code ' . $code);
        }
        
        $data = @json_decode($body, true);
        if ($data === null) {
            error_log('[HRS Rakuten] JSON parse failed. Body: ' . substr($body, 0, 200));
            throw new Exception('JSON parse failed');
        }
        
        if (!isset($data['hotels']) || !is_array($data['hotels']) || empty($data['hotels'])) {
            error_log('[HRS Rakuten] No hotels in response');
            return false;
        }
        
        if (!isset($data['hotels'][0]['hotel'][0]['hotelBasicInfo'])) {
            error_log('[HRS Rakuten] Unexpected response structure');
            throw new Exception('Unexpected response structure');
        }
        
        $hotel = $data['hotels'][0]['hotel'][0]['hotelBasicInfo'];
        
        if (empty($hotel['hotelNo']) || empty($hotel['hotelName'])) {
            error_log('[HRS Rakuten] Missing hotel fields');
            throw new Exception('Missing required fields');
        }
        
        return array(
            'hotel_no'       => (int) $hotel['hotelNo'],
            'hotel_name'     => sanitize_text_field($hotel['hotelName']),
            'image_url'      => !empty($hotel['hotelImageUrl']) ? esc_url($hotel['hotelImageUrl']) : '',
            'thumbnail_url'  => !empty($hotel['hotelThumbnailUrl']) ? esc_url($hotel['hotelThumbnailUrl']) : '',
            'room_image'     => !empty($hotel['roomImageUrl']) ? esc_url($hotel['roomImageUrl']) : '',
            'map_image'      => !empty($hotel['hotelMapImageUrl']) ? esc_url($hotel['hotelMapImageUrl']) : '',
        );
    }
    
    private function sanitize_rakuten_keyword($keyword) {
        $keyword = trim($keyword);
        $keyword = str_replace(array('"', "'", "\xE3\x80\x80", "\xEF\xBD\x9E", "\xE2\x80\x98", "\xE2\x80\x99"), array('', '', ' ', '-', '', ''), $keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword);
        return mb_substr($keyword, 0, 100);
    }
    
    private function build_exact_with_region($hotel_name) {
        $keyword = '"' . sanitize_text_field($hotel_name) . '"';
        $region = $this->detect_region($hotel_name);
        return $region ? $keyword . ' ' . $region : $keyword;
    }
    
    private function build_with_region($hotel_name) {
        $keyword = $this->sanitize_rakuten_keyword($hotel_name);
        $region = $this->detect_region($hotel_name);
        return $region ? $keyword . ' ' . $region : $keyword;
    }
    
    private function detect_region($hotel_name) {
        $regions = array(
            '富士河口湖' => '河口湖',
            '河口湖' => '河口湖',
            '富士' => '富士',
            '札幌' => '札幌',
            '函館' => '函館',
            '旭川' => '旭川',
            '仙台' => '仙台',
            '東京' => '東京',
            '横浜' => '横浜',
            '京都' => '京都',
            '大阪' => '大阪',
            '神戸' => '神戸',
            '広島' => '広島',
            '福岡' => '福岡',
            '沖縄' => '沖縄',
            '那覇' => '那覇',
            '名古屋' => '名古屋',
            '金沢' => '金沢',
            '箱根' => '箱根',
            '熱海' => '熱海',
            '軽井沢' => '軽井沢',
            '日光' => '日光',
            '鎌倉' => '鎌倉',
            '伊豆' => '伊豆',
            '草津' => '草津',
            '有馬' => '有馬',
            '城崎' => '城崎',
            '別府' => '別府',
            '湯布院' => '湯布院',
        );
        
        uksort($regions, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });
        
        foreach ($regions as $pattern => $region) {
            if (mb_strpos($hotel_name, $pattern) !== false) {
                return $region;
            }
        }
        
        return '';
    }
    
    private function extract_first_word($hotel_name) {
        $cleaned = preg_replace('/^(ホテル|旅館|宿|リゾート|inn|hotel|the|the\s+|ザ|グランド)\s*/ui', '', $hotel_name);
        $cleaned = trim($cleaned, " \xE3\x80\x80");
        $words = preg_split('/[\s\xE3\x80\x80\-]+/u', $cleaned);
        return !empty($words[0]) ? sanitize_text_field($words[0]) : sanitize_text_field($hotel_name);
    }
    
    public function set_featured_image($post_id, $hotel_name) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return false;
        if (has_post_thumbnail($post_id)) return true;
        
        $image_url = $this->fetch_hotel_image($hotel_name);
        if (!$image_url) return false;
        
        $attachment_id = $this->download_image($image_url, $hotel_name, $post_id);
        if (!$attachment_id) {
            sleep(1);
            $attachment_id = $this->download_image($image_url, $hotel_name, $post_id);
        }
        
        return $attachment_id ? set_post_thumbnail($post_id, $attachment_id) : false;
    }
    
    public function download_all_images($post_id, $hotel_name, $include_map = false) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) return array();
        
        $images_data = $this->fetch_hotel_images($hotel_name);
        if (!$images_data) return array();
        
        $target_images = $include_map ? $images_data['all_images'] : $images_data['article_images'];
        
        $attachment_ids = array();
        $suffixes = array('hotel', 'room', 'map');
        
        foreach ($target_images as $index => $image_url) {
            if (empty($image_url)) continue;
            
            $suffix = isset($suffixes[$index]) ? $suffixes[$index] : $index;
            $attachment_id = $this->download_image($image_url, $hotel_name . '_' . $suffix, $post_id);
            
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
                error_log('[HRS Rakuten] Downloaded image ' . ($index + 1) . ': attachment_id=' . $attachment_id);
            }
            
            if ($index < count($target_images) - 1) {
                usleep(500000);
            }
        }
        
        error_log('[HRS Rakuten] Total downloaded: ' . count($attachment_ids) . ' images');
        return $attachment_ids;
    }
    
    public function get_images_html($hotel_name, $size = 'full') {
        $images_data = $this->fetch_hotel_images($hotel_name);
        if (!$images_data || empty($images_data['article_images'])) {
            return '';
        }
        
        $html = '';
        $labels = array(
            0 => $images_data['hotel_name'] . ' 外観',
            1 => $images_data['hotel_name'] . ' 客室',
        );
        
        foreach ($images_data['article_images'] as $index => $image_url) {
            if (empty($image_url)) continue;
            
            $alt = isset($labels[$index]) ? esc_attr($labels[$index]) : esc_attr($images_data['hotel_name']);
            $html .= '<figure class="hrs-hotel-image">';
            $html .= '<img src="' . esc_url($image_url) . '" alt="' . $alt . '" loading="lazy" />';
            $html .= '<figcaption>' . esc_html($alt) . '</figcaption>';
            $html .= '</figure>' . "\n";
        }
        
        return $html;
    }
    
    private function download_image($image_url, $hotel_name, $post_id) {
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) return false;
        
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        $tmp = download_url($image_url, 8);
        if (is_wp_error($tmp)) return false;
        
        $filename = sanitize_file_name($hotel_name . '_' . time() . '.jpg');
        $file_array = array('name' => $filename, 'tmp_name' => $tmp);
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            error_log('[HRS Rakuten] Image download failed: ' . $attachment_id->get_error_message());
            return false;
        }
        
        error_log('[HRS Rakuten] Image downloaded: attachment_id=' . $attachment_id);
        return $attachment_id;
    }
    
    private function check_rate_limit() {
        $max_calls = 30;
        $current_time = time();
        
        if ($current_time - $this->api_call_timestamp >= 60) {
            $this->api_call_count = 0;
            $this->api_call_timestamp = $current_time;
        }
        
        if ($this->api_call_count >= $max_calls) return false;
        
        $this->api_call_count++;
        return true;
    }
    
    public function clear_cache() {
        $this->image_cache = array();
        $this->multi_image_cache = array();
    }
}