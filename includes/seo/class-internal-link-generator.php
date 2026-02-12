<?php
/**
 * 内部リンク生成クラス
 * 
 * @package HRS
 * @version 4.8.3-ACF-META-FIX
 * @change 4.8.3: hrp_*メタキーから直接URLを取得、検索URLフォールバック廃止
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Internal_Link_Generator {

    private static $instance = null;

    private $moshimo_a_id = '5247247';
    private $moshimo_p_id = '55';
    private $moshimo_pc_id = '55';
    private $moshimo_pl_id = '624';

    private $post_type = 'hotel-review';

    private $ota_config = array();

    /**
     * OTA別メタキーマッピング（v4.8.3追加）
     */
    private $ota_meta_keys = array(
        'rakuten'  => 'hrp_rakuten_travel_url',
        'jalan'    => 'hrp_booking_jalan_url',
        'ikyu'     => 'hrp_booking_ikyu_url',
        'yahoo'    => 'hrp_booking_yahoo_url',
        'jtb'      => 'hrp_booking_jtb_url',
        'rurubu'   => 'hrp_booking_rurubu_url',
        'relux'    => 'hrp_booking_relux_url',
        'yukoyuko' => 'hrp_booking_yukoyuko_url',
        'booking'  => 'hrp_booking_bookingcom_url',
        'expedia'  => 'hrp_booking_expedia_url',
    );

    /**
     * 検索URLパターン（これにマッチしたら「検索ページ」と判定）
     */
    private $search_url_patterns = array(
        '/[?&]keyword=/i',
        '/[?&]q=/i',
        '/[?&]ss=/i',
        '/[?&]destination=/i',
        '/\/search/i',
        '/\/uwp\d+/i',
        '/\/uww\d+/i',
        '/searchresults/i',
        '/hotellist\/search/i',
        '/Hotel-Search/i',
    );

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->load_ota_config();
        add_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20, 2);
    }

    private function load_ota_config() {
        $ota_names = array(
            'rakuten'  => '楽天トラベル',
            'jalan'    => 'じゃらん',
            'ikyu'     => '一休.com',
            'yahoo'    => 'Yahoo!トラベル',
            'jtb'      => 'JTB',
            'rurubu'   => 'るるぶトラベル',
            'relux'    => 'Relux',
            'yukoyuko' => 'ゆこゆこ',
            'booking'  => 'Booking.com',
            'expedia'  => 'Expedia',
        );

        $tier1 = $this->get_tier_option('tier1', array('rakuten', 'jalan', 'ikyu', 'yahoo', 'rurubu', 'relux'));
        $tier2 = $this->get_tier_option('tier2', array());
        $tier3 = $this->get_tier_option('tier3', array());

        $this->ota_config = array();
        
        foreach ($ota_names as $ota_id => $name) {
            $tier = 0;
            $enabled = false;

            if (in_array($ota_id, $tier1)) {
                $tier = 1;
                $enabled = true;
            } elseif (in_array($ota_id, $tier2)) {
                $tier = 2;
                $enabled = true;
            } elseif (in_array($ota_id, $tier3)) {
                $tier = 3;
                $enabled = true;
            }

            $this->ota_config[$ota_id] = array(
                'name' => $name,
                'tier' => $tier,
                'enabled' => $enabled,
            );
        }
    }

    private function get_tier_option($tier_key, $default = array()) {
        $tier_num = str_replace('tier', '', $tier_key);
        
        $option_names = array(
            'hrs_ota_tier_' . $tier_num,
            'hrs_ota_' . $tier_key,
            'hrs_affiliate_' . $tier_key,
            '5drb_ota_' . $tier_key,
        );

        foreach ($option_names as $option_name) {
            $value = get_option($option_name, null);
            if ($value !== null && is_array($value) && !empty($value)) {
                return $value;
            }
        }

        return $default;
    }

    public function get_enabled_otas() {
        $enabled = array_filter($this->ota_config, function($config) {
            return $config['enabled'];
        });

        uasort($enabled, function($a, $b) {
            return $a['tier'] <=> $b['tier'];
        });

        return $enabled;
    }

    /**
     * 投稿IDからOTA URLを取得（v4.8.3追加）
     */
    private function get_ota_urls_from_meta($post_id) {
        $urls = array();
        
        foreach ($this->ota_meta_keys as $ota_id => $meta_key) {
            $url = get_post_meta($post_id, $meta_key, true);
            if (!empty($url) && !$this->is_search_url($url)) {
                $urls[$ota_id] = $url;
            }
        }
        
        return $urls;
    }

    /**
     * 検索URLかどうかを判定
     */
    private function is_search_url($url) {
        if (empty($url)) {
            return true;
        }

        foreach ($this->search_url_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    public function add_links_on_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_type !== $this->post_type) return;
        if (strpos($post->post_content, 'hrs-booking-links') !== false) return;

        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            $hotel_name = $post->post_title;
            if (mb_strpos($hotel_name, 'の魅力') !== false) {
                $hotel_name = mb_substr($hotel_name, 0, mb_strpos($hotel_name, 'の魅力'));
            }
        }

        // v4.8.3: メタキーから直接取得
        $ota_urls = $this->get_ota_urls_from_meta($post_id);

        $link_section = $this->generate_link_section($hotel_name, $ota_urls);
        if (empty($link_section)) return;

        $content = $post->post_content . "\n\n" . $link_section;

        remove_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20);
        wp_update_post(array('ID' => $post_id, 'post_content' => $content));
        add_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20, 2);
    }

    /**
     * リンクセクションHTML生成（v4.8.3修正）
     * 
     * 検索URLフォールバック廃止 - 詳細URLがないOTAは表示しない
     */
    public function generate_link_section($hotel_name, $ota_urls = array()) {
        $links = array();
        $enabled_otas = $this->get_enabled_otas();

        foreach ($enabled_otas as $ota_id => $config) {
            $url = isset($ota_urls[$ota_id]) ? $ota_urls[$ota_id] : '';
            
            // ★ v4.8.3: 詳細URLがない場合はスキップ（検索URLフォールバック廃止）
            if (empty($url)) {
                continue;
            }

            $link_html = $this->generate_ota_link($ota_id, $config['name'], $url);
            
            if (!empty($link_html)) {
                $links[] = $link_html;
            }
        }

        if (empty($links)) {
            return '';
        }

        $html = '<div class="hrs-booking-links">';
        $html .= '<h2>🏨 ' . esc_html($hotel_name) . ' の予約・詳細</h2>';
        $html .= '<p>各予約サイトで最新の料金・空室状況をチェックできます。</p>';
        $html .= '<ul class="hrs-ota-list">';
        
        foreach ($links as $link) {
            $html .= '<li class="hrs-ota-item">' . $link . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }

    private function generate_ota_link($ota_id, $ota_name, $url) {
        if (empty($url)) {
            return '';
        }

        if ($ota_id === 'rakuten') {
            return $this->generate_rakuten_moshimo_link($ota_name, $url);
        }

        return $this->generate_direct_link($ota_name, $url);
    }

    private function generate_rakuten_moshimo_link($ota_name, $url) {
        $encoded_url = urlencode($url);
        
        $moshimo_url = '//af.moshimo.com/af/c/click?'
            . 'a_id=' . $this->moshimo_a_id
            . '&p_id=' . $this->moshimo_p_id
            . '&pc_id=' . $this->moshimo_pc_id
            . '&pl_id=' . $this->moshimo_pl_id
            . '&url=' . $encoded_url;

        $html = '<a href="' . esc_attr($moshimo_url) . '" rel="nofollow" referrerpolicy="no-referrer-when-downgrade" attributionsrc target="_blank">';
        $html .= esc_html($ota_name) . 'で予約する';
        $html .= '</a>';
        
        $impression_url = '//i.moshimo.com/af/i/impression?'
            . 'a_id=' . $this->moshimo_a_id
            . '&p_id=' . $this->moshimo_p_id
            . '&pc_id=' . $this->moshimo_pc_id
            . '&pl_id=' . $this->moshimo_pl_id;

        $html .= '<img src="' . esc_attr($impression_url) . '" width="1" height="1" style="border:none;" alt="" loading="lazy">';

        return $html;
    }

    private function generate_direct_link($ota_name, $url) {
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' 
            . esc_html($ota_name) . 'で予約する</a>';
    }

    public function add_links_to_post($post_id, $hotel_name = '', $ota_urls = array()) {
        $post = get_post($post_id);
        if (!$post) return false;

        if (strpos($post->post_content, 'hrs-booking-links') !== false) {
            return true;
        }

        if (empty($hotel_name)) {
            $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
            if (empty($hotel_name)) {
                $hotel_name = $post->post_title;
                if (mb_strpos($hotel_name, 'の魅力') !== false) {
                    $hotel_name = mb_substr($hotel_name, 0, mb_strpos($hotel_name, 'の魅力'));
                }
            }
        }

        // v4.8.3: メタキーから直接取得
        if (empty($ota_urls)) {
            $ota_urls = $this->get_ota_urls_from_meta($post_id);
        }

        $link_section = $this->generate_link_section($hotel_name, $ota_urls);
        if (empty($link_section)) return false;

        $content = $post->post_content . "\n\n" . $link_section;

        remove_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20);
        $result = wp_update_post(array('ID' => $post_id, 'post_content' => $content));
        add_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20, 2);

        return !is_wp_error($result);
    }

    /**
     * リンク再生成（v4.8.3修正）
     */
    public function regenerate_links($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->post_type) {
            return false;
        }

        $content = $post->post_content;
        $content = preg_replace('/<div class="hrs-booking-links">.*?<\/div>/s', '', $content);
        $content = trim($content);

        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            $hotel_name = $post->post_title;
            if (mb_strpos($hotel_name, 'の魅力') !== false) {
                $hotel_name = mb_substr($hotel_name, 0, mb_strpos($hotel_name, 'の魅力'));
            }
        }

        // v4.8.3: メタキーから直接取得
        $ota_urls = $this->get_ota_urls_from_meta($post_id);

        $link_section = $this->generate_link_section($hotel_name, $ota_urls);
        
        if (!empty($link_section)) {
            $content .= "\n\n" . $link_section;
        }

        remove_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20);
        $result = wp_update_post(array('ID' => $post_id, 'post_content' => $content));
        add_action('save_post_' . $this->post_type, array($this, 'add_links_on_save'), 20, 2);

        return !is_wp_error($result);
    }

    public function get_ota_config() {
        return $this->ota_config;
    }

    public function get_debug_info() {
        return array(
            'ota_config' => $this->ota_config,
            'enabled_otas' => $this->get_enabled_otas(),
            'meta_keys' => $this->ota_meta_keys,
            'tier1_option' => get_option('hrs_ota_tier_1'),
            'tier2_option' => get_option('hrs_ota_tier_2'),
            'tier3_option' => get_option('hrs_ota_tier_3'),
        );
    }
}

add_action('init', function() {
    HRS_Internal_Link_Generator::get_instance();
});