<?php
/**
 * OTAマッピングUI クラス
 * 
 * @package HRS
 * @version 1.3.0 - サイドバーメタボックス削除
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Mapping_UI {

    private $manager;

    /**
     * 全OTA定義
     */
    private $all_ota_names = array(
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
    );

    public function __construct($manager) {
        $this->manager = $manager;
        // メタボックスは削除（不要のため）
        // add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_hrs_check_mapping', array($this, 'ajax_check_mapping'));
        add_action('wp_ajax_hrs_submit_mapping_request', array($this, 'ajax_submit_request'));

        // cron 登録（初回のみ）
        if (!wp_next_scheduled('hrs_mapping_revalidate')) {
            wp_schedule_event(strtotime('first day of next month 03:00:00'), 'monthly', 'hrs_mapping_revalidate');
        }
        add_action('hrs_mapping_revalidate', array($this->manager, 'cron_revalidate'));
    }

    /**
     * Tier 1以外のOTAを取得
     */
    private function get_non_tier1_otas() {
        $all_ota_ids = array_keys($this->all_ota_names);
        $tier1 = get_option('hrs_ota_tier_1', array('rakuten', 'jalan', 'ikyu', 'jtb', 'relux', 'yukoyuko'));
        
        // Tier 1以外を返す
        return array_diff($all_ota_ids, (array)$tier1);
    }

    public function ajax_check_mapping() {
        check_ajax_referer('hrs-mapping', 'nonce');

        $hotel_name = sanitize_text_field($_POST['hotel_name']);
        $mapping = $this->manager->check_mapping($hotel_name);

        if ($mapping) {
            $html = '<div style="background:#e6ffe6; padding:8px; border:1px solid #4CAF50; border-radius:4px;">';
            $html .= '<strong>✅ マッピング登録済み</strong><br>';
            foreach ($mapping as $ota => $url) {
                $label = isset($this->all_ota_names[$ota]) ? $this->all_ota_names[$ota] : ucfirst($ota);
                $html .= sprintf('- %s: <a href="%s" target="_blank">%s</a><br>', 
                    esc_html($label), esc_url($url), esc_html($url));
            }
            $html .= '</div>';
        } else {
            $html = '<div style="background:#fff3cd; padding:8px; border:1px solid #ffc107; border-radius:4px;">';
            $html .= '<strong>⚠️ 未登録</strong><br>';
            $html .= '「<strong>' . esc_html($hotel_name) . '</strong>」のマッピングがありません。<br><br>';
            $html .= '<button type="button" class="button button-primary" id="hrs-request-mapping">マッピング追加申請</button>';
            $html .= '<div id="hrs-mapping-form" style="display:none; margin-top:10px;">';
            
            // Tier 1以外のOTAのみ入力フォームを表示
            $non_tier1_otas = $this->get_non_tier1_otas();
            
            foreach ($non_tier1_otas as $ota_id) {
                if (isset($this->all_ota_names[$ota_id])) {
                    $html .= '<p><label>' . esc_html($this->all_ota_names[$ota_id]) . 'URL:<br>';
                    $html .= '<input type="url" name="' . esc_attr($ota_id) . '_url" class="widefat" placeholder="https://..."></label></p>';
                }
            }
            
            $html .= '<p><label>住所:<br><input type="text" name="address" class="widefat" placeholder="例：静岡県熱海市熱海1993-250"></label></p>';
            $html .= '<p><button type="button" class="button" id="hrs-submit-request">申請する</button></p>';
            $html .= '</div>';
            
            // JavaScript for form submission
            $html .= '<script>
                jQuery("#hrs-request-mapping").on("click", function() {
                    jQuery("#hrs-mapping-form").show();
                });
                jQuery("#hrs-submit-request").on("click", function() {
                    var data = {
                        action: "hrs_submit_mapping_request",
                        hotel_name: "' . esc_js($hotel_name) . '",
                        address: jQuery("[name=address]").val(),
                        nonce: "' . wp_create_nonce('hrs-mapping') . '"
                    };
                    
                    // 全OTAのURLを収集
                    var otaIds = ["rakuten", "jalan", "ikyu", "booking", "yahoo", "jtb", "rurubu", "relux", "yukoyuko", "expedia"];
                    otaIds.forEach(function(otaId) {
                        var field = jQuery("[name=" + otaId + "_url]");
                        if (field.length && field.val()) {
                            data[otaId + "_url"] = field.val();
                        }
                    });
                    
                    jQuery.post(ajaxurl, data, function(res) {
                        if (res.success) {
                            alert("申請を受け付けました。管理者の承認後、マッピングに追加されます。");
                            location.reload();
                        } else {
                            alert("エラー: " + (res.data || "不明なエラー"));
                        }
                    });
                });
            </script>';
            $html .= '</div>';
        }

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_submit_request() {
        check_ajax_referer('hrs-mapping', 'nonce');

        $data = array(
            'hotel_name' => sanitize_text_field($_POST['hotel_name']),
            'urls' => array(),
            'address' => sanitize_text_field($_POST['address']),
        );

        // 全OTAのURLを動的に取得
        $ota_ids = array_keys($this->all_ota_names);
        foreach ($ota_ids as $ota_id) {
            $field_name = $ota_id . '_url';
            if (!empty($_POST[$field_name])) {
                $data['urls'][$ota_id] = esc_url_raw($_POST[$field_name]);
            }
        }

        if (empty($data['hotel_name']) || empty($data['urls'])) {
            wp_send_json_error('ホテル名またはURLを入力してください。');
        }

        $this->manager->add_pending_request($data);
        wp_send_json_success();
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'post-new.php' && $hook !== 'post.php') return;
        global $post;
        if (empty($post) || $post->post_type !== 'hotel-review') return;

        wp_enqueue_script('hrs-mapping', plugin_dir_url(__FILE__) . 'js/mapping.js', array('jquery'), '1.3', true);
        wp_localize_script('hrs-mapping', 'hrs_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}