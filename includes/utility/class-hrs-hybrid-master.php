<?php
/**
 * HRS Hybrid OTA Master (Full Version)
 * 11社のOTA入力を2列レイアウトで統合管理
 */

if (!defined('ABSPATH')) exit;

class HRS_Hybrid_Master {

    private $mapping_manager;

    public function __construct() {
        // マッピングマネージャーの読み込み
        if (class_exists('HRS_Mapping_Manager')) {
            $this->mapping_manager = new HRS_Mapping_Manager();
        }

        // 管理画面: 入力欄の追加
        add_action('add_meta_boxes', array($this, 'add_ota_input_metabox'));
        // 管理画面: 保存処理
        add_action('save_post', array($this, 'save_ota_input_data'));
        
        // フロント表示: ボタンの出力
        add_filter('the_content', array($this, 'inject_hybrid_ota_buttons'), 30);
    }

    /**
     * 表示・管理するOTAの定義（JTB・るるぶ・ゆこゆこ・Expediaを含む全11社）
     */
    private function get_target_otas() {
        return array(
            'rakuten'  => '楽天トラベル',
            'jalan'    => 'じゃらん',
            'ikyu'     => '一休.com',
            'relux'    => 'Relux',
            'booking'  => 'Booking.com',
            'yahoo'    => 'Yahoo!トラベル',
            'jtb'      => 'JTB',
            'rurubu'   => 'るるぶトラベル',
            'yukoyuko' => 'ゆこゆこ',
            'expedia'  => 'Expedia',
            'official' => '公式サイト',
        );
    }

    /**
     * カスタム投稿「ホテルレビュー」に入力ボックスを追加
     */
    public function add_ota_input_metabox() {
        add_meta_box(
            'hrs_hybrid_ota_links',
            'OTAリンク（優先設定・2列表示）',
            array($this, 'render_ota_input_fields'),
            'hotel-review',
            'normal',
            'high'
        );
    }

    /**
     * 入力画面の描画（2列グリッドレイアウト）
     */
    public function render_ota_input_fields($post) {
        $hotel_name = get_the_title($post->ID);
        $saved_urls = $this->mapping_manager ? $this->mapping_manager->get_urls($hotel_name) : array();
        
        // CSSによるレイアウト調整
        echo '<style>
            .hrs-ota-grid {
                display: grid;
                grid-template-columns: 1fr 1fr; /* 2列 */
                gap: 15px 25px;
                padding: 15px 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .hrs-ota-item {
                display: flex;
                flex-direction: column;
            }
            .hrs-ota-item label {
                font-weight: bold;
                margin-bottom: 6px;
                font-size: 13px;
                color: #333;
            }
            .hrs-ota-item input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            @media (max-width: 782px) {
                .hrs-ota-grid { grid-template-columns: 1fr; } /* スマホでは1列 */
            }
        </style>';

        echo '<div class="hrs-ota-grid">';
        foreach ($this->get_target_otas() as $id => $label) {
            $val = isset($saved_urls[$id]) ? esc_url($saved_urls[$id]) : '';
            echo '<div class="hrs-ota-item">';
            echo '<label>' . esc_html($label) . '</label>';
            echo '<input type="url" name="hrs_hybrid_ota[' . esc_attr($id) . ']" value="' . esc_attr($val) . '" placeholder="https://...">';
            echo '</div>';
        }
        echo '</div>';
        echo '<p style="margin-top:10px; color:#666; font-size:12px;">※JTB、るるぶ、ゆこゆこ、Expedia等のURLを入力すると、公開画面で最優先表示されます。</p>';
    }

    /**
     * データの保存処理
     */
    public function save_ota_input_data($post_id) {
        // 保存権限やPOSTチェック
        if (!isset($_POST['hrs_hybrid_ota']) || !is_array($_POST['hrs_hybrid_ota'])) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $hotel_name = get_the_title($post_id);
        $urls = array_map('esc_url_raw', $_POST['hrs_hybrid_ota']);
        
        // Mapping Manager経由で ota-known-mappings.php に書き込み
        if ($this->mapping_manager) {
            $this->mapping_manager->set_urls($hotel_name, $urls);
        }
    }

    /**
     * フロント公開画面への表示
     */
    public function inject_hybrid_ota_buttons($content) {
        // シングルページ以外は対象外
        if (!is_singular('hotel-review')) return $content;

        $hotel_name = get_the_title();
        $manual_urls = $this->mapping_manager ? $this->mapping_manager->get_urls($hotel_name) : array();

        $btn_html = '<div class="ota-hybrid-buttons" style="display:flex; flex-wrap:wrap; gap:12px; margin: 30px 0;">';
        $has_buttons = false;

        foreach ($this->get_target_otas() as $id => $label) {
            // URLが入っている項目のみボタンを作成
            if (!empty($manual_urls[$id])) {
                $btn_html .= sprintf(
                    '<a href="%1$s" target="_blank" class="ota-btn ota-%2$s" style="background:#0073aa; color:#fff; padding:12px 24px; text-decoration:none; border-radius:5px; font-weight:bold;">%3$sで予約</a>',
                    esc_url($manual_urls[$id]),
                    esc_attr($id),
                    esc_html($label)
                );
                $has_buttons = true;
            }
        }
        $btn_html .= '</div>';

        // 記事の末尾にボタンを追加
        return $has_buttons ? $content . $btn_html : $content;
    }
}

// 実行
new HRS_Hybrid_Master();