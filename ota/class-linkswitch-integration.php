<?php
/**
 * LinkSwitch 統合クラス（HQC対応版）
 * 
 * バリューコマース LinkSwitch およびアフィリエイトリンク管理
 * MOSHIMO（もしもアフィリエイト）統合
 * 
 * ============================================================
 * 【設計思想】class-ota-search-engine.php と統一
 * ① URLは「LinkSwitch可否」で分類する
 * ② じゃらん・一休は「IDがないなら出さない」（最適化）
 * ③ LinkSwitchは「後段で一括適用」
 * 
 * 👉 「空欄」は失敗ではない
 * 👉 「404リンク」は明確な失敗
 * ============================================================
 * 
 * @package HRS
 * @version 4.4.1-JTB-RURUBU-ID
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_LinkSwitch_Integration {

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * MOSHIMO アフィリエイトID
     */
    private $moshimo_id = '5247247';

    /**
     * LinkSwitch有効化フラグ
     */
    private $linkswitch_enabled = false;

    /**
     * アフィリエイトネットワーク設定
     */
    private $networks = array();

    /**
     * ============================================================
     * OTA別 LinkSwitch ルール定義（class-ota-search-engine.php と統一）
     * ============================================================
     * require_id    : true = ID付き詳細URLのみ許可、検索URL不可
     * allow_search  : true = 検索URLフォールバック許可
     * search_url    : 検索URLテンプレート（{keyword}を置換）
     * linkswitch    : true = LinkSwitch対応
     * ============================================================
     */
    private $ota_rules = array(
        'rakuten' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://travel.rakuten.co.jp/keyword/search.html?f_keyword={keyword}',
            'linkswitch'    => true,
        ),
        'jalan' => array(
            'require_id'    => true,  // ★ ID必須：/yadXXXXXX/ のみ
            'allow_search'  => false, // ★ 検索URL廃止済み
            'search_url'    => null,
            'linkswitch'    => true,
        ),
        'ikyu' => array(
            'require_id'    => true,  // ★ ID必須：/XXXXXXXX/ のみ
            'allow_search'  => false, // ★ 検索URL廃止済み
            'search_url'    => null,
            'linkswitch'    => true,
        ),
        'yahoo' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://travel.yahoo.co.jp/dhotel/shisetsu/HT10{keyword}/',
            'linkswitch'    => true,
        ),
        'booking' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://www.booking.com/searchresults.ja.html?ss={keyword}',
            'linkswitch'    => true,
        ),
        'jtb' => array(
            'require_id'    => true,   // ★ JTB独自ID必須（/htl/XXXXXXX/）
            'allow_search'  => false,  // ★ 検索URLは詳細ページではない
            'search_url'    => null,
            'linkswitch'    => true,
        ),
        'rurubu' => array(
            'require_id'    => true,   // ★ ホテルスラッグ必須（/hotel/japan/.../xxx/）
            'allow_search'  => false,  // ★ 検索URLは詳細ページではない
            'search_url'    => null,
            'linkswitch'    => true,
        ),
        'relux' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://rlx.jp/search/?word={keyword}',
            'linkswitch'    => true,
        ),
        'yukoyuko' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://www.yukoyuko.net/search?q={keyword}',
            'linkswitch'    => true,
        ),
        'expedia' => array(
            'require_id'    => false,
            'allow_search'  => true,
            'search_url'    => 'https://www.expedia.co.jp/Hotel-Search?destination={keyword}',
            'linkswitch'    => true,
        ),
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init_settings();
        
        // LinkSwitchスクリプト挿入
        if ($this->linkswitch_enabled) {
            add_action('wp_head', array($this, 'insert_linkswitch_script'));
        }
        
        // コンテンツフィルター
        add_filter('the_content', array($this, 'process_affiliate_links'), 20);
    }

    /**
     * 設定の初期化
     */
    private function init_settings() {
        $this->moshimo_id = get_option('hrs_moshimo_affiliate_id', '5247247');
        $this->linkswitch_enabled = get_option('hrs_linkswitch_enabled', false);
        
        $this->networks = array(
            'moshimo' => array(
                'name' => 'もしもアフィリエイト',
                'enabled' => true,
                'priority' => 1,
                'supported_otas' => array('rakuten'),
                'template' => 'https://af.moshimo.com/af/c/click?a_id={affiliate_id}&p_id=54&pc_id=54&pl_id=616&url={encoded_url}',
            ),
            'valuecommerce' => array(
                'name' => 'バリューコマース',
                'enabled' => $this->linkswitch_enabled,
                'priority' => 2,
                'supported_otas' => array('jalan', 'ikyu', 'yahoo', 'jtb'),
                'linkswitch' => true,
            ),
            'a8' => array(
                'name' => 'A8.net',
                'enabled' => get_option('hrs_a8_enabled', false),
                'priority' => 3,
                'supported_otas' => array('booking', 'expedia'),
            ),
        );
    }

    /**
     * LinkSwitchスクリプトを挿入
     */
    public function insert_linkswitch_script() {
        if (!is_singular($this->post_type)) {
            return;
        }
        
        $sid = get_option('hrs_valuecommerce_sid', '');
        $pid = get_option('hrs_valuecommerce_pid', '');
        
        if (empty($sid) || empty($pid)) {
            return;
        }
        
        echo '<script type="text/javascript" src="//aml.valuecommerce.com/vcdal.js" async></script>';
        echo '<script type="text/javascript">';
        echo 'var vc_pid = "' . esc_js($pid) . '";';
        echo 'var vc_sid = "' . esc_js($sid) . '";';
        echo '</script>';
    }

    /**
     * アフィリエイトリンクを処理
     * 
     * @param string $content
     * @return string
     */
    public function process_affiliate_links($content) {
        if (!is_singular($this->post_type)) {
            return $content;
        }
        
        // 楽天リンクをMOSHIMOに変換
        $content = $this->convert_rakuten_links($content);
        
        return $content;
    }

    /**
     * 楽天リンクをMOSHIMOアフィリエイトリンクに変換
     * 
     * @param string $content
     * @return string
     */
    private function convert_rakuten_links($content) {
        // 楽天トラベルのURLパターン
        $patterns = array(
            '#https?://travel\.rakuten\.co\.jp/[^\s"\'<>]+#',
            '#https?://hb\.afl\.rakuten\.co\.jp/[^\s"\'<>]+#',
        );
        
        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, array($this, 'wrap_rakuten_url'), $content);
        }
        
        return $content;
    }

    /**
     * 楽天URLをMOSHIMOでラップ
     * 
     * @param array $matches
     * @return string
     */
    private function wrap_rakuten_url($matches) {
        $original_url = $matches[0];
        
        // 既にMOSHIMOリンクの場合はスキップ
        if (strpos($original_url, 'moshimo.com') !== false) {
            return $original_url;
        }
        
        return $this->generate_moshimo_link($original_url);
    }

    /**
     * MOSHIMOアフィリエイトリンクを生成
     * 
     * @param string $url 元URL
     * @param string $ota OTA ID（オプション）
     * @return string
     */
    public function generate_moshimo_link($url, $ota = 'rakuten') {
        if (empty($this->moshimo_id)) {
            return $url;
        }
        
        $encoded_url = urlencode($url);
        
        // 楽天トラベル用のMOSHIMOリンク
        $moshimo_url = sprintf(
            'https://af.moshimo.com/af/c/click?a_id=%s&p_id=54&pc_id=54&pl_id=616&url=%s',
            $this->moshimo_id,
            $encoded_url
        );
        
        return $moshimo_url;
    }

    /**
     * OTAサイト用のアフィリエイトリンクを生成
     * 
     * @param string $url 元URL
     * @param string $ota OTA ID
     * @return array リンク情報
     */
    public function generate_affiliate_link($url, $ota) {
        $result = array(
            'original_url' => $url,
            'affiliate_url' => $url,
            'network' => null,
            'is_affiliate' => false,
        );
        
        // OTAに対応するネットワークを探す
        foreach ($this->networks as $network_id => $network) {
            if (!$network['enabled']) {
                continue;
            }
            
            if (in_array($ota, $network['supported_otas'])) {
                if ($network_id === 'moshimo') {
                    $result['affiliate_url'] = $this->generate_moshimo_link($url, $ota);
                    $result['network'] = 'moshimo';
                    $result['is_affiliate'] = true;
                } elseif ($network_id === 'valuecommerce' && !empty($network['linkswitch'])) {
                    // LinkSwitchは自動変換なのでURLはそのまま
                    $result['network'] = 'valuecommerce';
                    $result['is_affiliate'] = true;
                }
                break;
            }
        }
        
        return $result;
    }

    /**
     * OTA検索URLを生成
     * 
     * ============================================================
     * 【重要】じゃらん・一休は検索URL廃止済みのためnullを返す
     * ============================================================
     * 
     * @param string $ota OTA ID
     * @param string $hotel_name ホテル名
     * @return string|null 検索URL（生成不可の場合はnull）
     */
    public function generate_ota_search_url($ota, $hotel_name) {
        $rule = isset($this->ota_rules[$ota]) ? $this->ota_rules[$ota] : null;
        
        // ルールがない、または検索URL不可の場合
        if (empty($rule) || empty($rule['allow_search']) || empty($rule['search_url'])) {
            // じゃらん・一休はID必須、検索URL不可
            error_log("[HRS LinkSwitch] {$ota}: 検索URL生成不可（ID必須）");
            return null;
        }
        
        $encoded_name = urlencode($hotel_name);
        return str_replace('{keyword}', $encoded_name, $rule['search_url']);
    }

    /**
     * アフィリエイト付きOTAリンクを生成
     * 
     * @param string $ota OTA ID
     * @param string $hotel_name ホテル名
     * @return array|null リンク情報（生成不可の場合はnull）
     */
    public function generate_ota_affiliate_link($ota, $hotel_name) {
        $base_url = $this->generate_ota_search_url($ota, $hotel_name);
        
        // 検索URL生成不可（じゃらん・一休など）の場合はnull
        if (empty($base_url)) {
            return null;
        }
        
        return $this->generate_affiliate_link($base_url, $ota);
    }

    /**
     * 記事用のOTAリンクセクションを生成
     * 
     * @param string $hotel_name ホテル名
     * @param array $otas OTA一覧（優先順）
     * @param string $persona ペルソナ
     * @return string HTML
     */
    public function generate_ota_section($hotel_name, $otas = array(), $persona = 'general') {
        if (empty($otas)) {
            $otas = array('rakuten', 'jalan', 'ikyu');
        }
        
        $ota_names = array(
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
        
        $cta_texts = $this->get_cta_texts($persona);
        
        $html = '<div class="hrs-booking-section">';
        $html .= '<h3>🏨 ' . esc_html($hotel_name) . ' の予約はこちら</h3>';
        $html .= '<ul class="hrs-booking-links">';
        
        foreach ($otas as $ota) {
            $link_info = $this->generate_ota_affiliate_link($ota, $hotel_name);
            
            // 【重要】リンク生成不可の場合はスキップ（404を出さない）
            if (empty($link_info)) {
                continue;
            }
            
            $ota_name = $ota_names[$ota] ?? $ota;
            $cta = $cta_texts[$ota] ?? '予約する';
            $priority = $this->get_ota_priority($ota, $persona);
            
            $html .= '<li class="hrs-booking-link priority-' . esc_attr($priority) . '">';
            $html .= '<a href="' . esc_url($link_info['affiliate_url']) . '" target="_blank" rel="noopener sponsored">';
            $html .= '<span class="ota-name">' . esc_html($ota_name) . '</span>';
            $html .= '<span class="cta-text">' . esc_html($cta) . '</span>';
            $html .= '</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * ペルソナ別CTAテキストを取得
     * 
     * @param string $persona
     * @return array
     */
    private function get_cta_texts($persona) {
        $defaults = array(
            'rakuten' => 'ポイント貯まる！',
            'jalan' => '口コミをチェック',
            'ikyu' => '限定プランを見る',
            'booking' => '海外からも予約OK',
            'yahoo' => 'PayPayポイント貯まる',
            'jtb' => '安心のJTB',
            'rurubu' => 'るるぶで予約',
            'relux' => '高級宿専門',
            'yukoyuko' => 'シニアに人気',
            'expedia' => '世界最大級',
        );
        
        $persona_ctas = array(
            'budget' => array(
                'rakuten' => 'ポイント還元でお得！',
                'yahoo' => 'クーポンでお得！',
            ),
            'luxury' => array(
                'ikyu' => '最高のおもてなしを',
                'rakuten' => '上質な滞在を',
                'relux' => '厳選された高級宿',
            ),
            'family' => array(
                'rakuten' => '家族旅行に最適！',
                'jalan' => '子供も大満足！',
            ),
            'couple' => array(
                'ikyu' => '二人だけの特別な時間',
                'rakuten' => '記念日プランあり',
            ),
            'senior' => array(
                'jtb' => '安心サポート',
                'yukoyuko' => 'シニア限定プラン',
            ),
        );
        
        return array_merge($defaults, $persona_ctas[$persona] ?? array());
    }

    /**
     * OTA優先度を取得
     * 
     * @param string $ota
     * @param string $persona
     * @return string high/medium/low
     */
    private function get_ota_priority($ota, $persona) {
        // 楽天は常に最優先（アフィリエイト収益のため）
        if ($ota === 'rakuten') {
            return 'high';
        }
        
        $priorities = array(
            'luxury' => array('ikyu' => 'high', 'relux' => 'high', 'rakuten' => 'medium', 'jalan' => 'medium'),
            'budget' => array('rakuten' => 'high', 'yahoo' => 'high', 'jalan' => 'medium'),
            'family' => array('rakuten' => 'high', 'jalan' => 'high', 'yahoo' => 'medium'),
            'senior' => array('jtb' => 'high', 'yukoyuko' => 'high', 'jalan' => 'medium', 'rakuten' => 'medium'),
            'couple' => array('ikyu' => 'high', 'relux' => 'high', 'rakuten' => 'medium'),
        );
        
        return $priorities[$persona][$ota] ?? 'medium';
    }

    /**
     * OTAルール取得
     * 
     * @param string $ota_id
     * @return array|null
     */
    public function get_ota_rule($ota_id) {
        return isset($this->ota_rules[$ota_id]) ? $this->ota_rules[$ota_id] : null;
    }

    /**
     * 全OTAルール取得
     * 
     * @return array
     */
    public function get_ota_rules() {
        return $this->ota_rules;
    }

    /**
     * アフィリエイトネットワーク情報を取得
     * 
     * @return array
     */
    public function get_networks() {
        return $this->networks;
    }

    /**
     * MOSHIMO ID を取得
     * 
     * @return string
     */
    public function get_moshimo_id() {
        return $this->moshimo_id;
    }

    /**
     * 設定状態を確認
     * 
     * @return array
     */
    public function get_status() {
        return array(
            'moshimo_configured' => !empty($this->moshimo_id),
            'linkswitch_enabled' => $this->linkswitch_enabled,
            'networks' => array_map(function($n) {
                return array(
                    'name' => $n['name'],
                    'enabled' => $n['enabled'],
                );
            }, $this->networks),
            'ota_rules' => array_map(function($r) {
                return array(
                    'require_id' => $r['require_id'],
                    'allow_search' => $r['allow_search'],
                );
            }, $this->ota_rules),
        );
    }

    /**
     * テスト用のサンプルリンクを生成
     * 
     * @return array
     */
    public function test_links() {
        $hotel_name = '星野リゾート';
        
        $results = array();
        foreach (array_keys($this->ota_rules) as $ota) {
            $link = $this->generate_ota_affiliate_link($ota, $hotel_name);
            $results[$ota] = array(
                'link' => $link,
                'rule' => $this->ota_rules[$ota],
                'generated' => !empty($link),
            );
        }
        
        return $results;
    }
}

// 初期化
new HRS_LinkSwitch_Integration();