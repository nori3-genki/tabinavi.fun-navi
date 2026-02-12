<?php
/**
 * OTA検索エンジンクラス
 *
 * LinkSwitch対応設計
 * 
 * ============================================================
 * 【設計思想】
 * ① URLは「LinkSwitch可否」で分類する
 * ② じゃらん・一休は「IDがないなら出さない」（最適化）
 * ③ LinkSwitchは「後段で一括適用」（DB:生URL → HTML:そのまま → JS/ASP:変換）
 * 
 * 【フォールバック順】
 * ① Google site検索（OTA別詳細URL）
 * ② Google CSE（詳細URL取得）
 * ③ 楽天API（確定）
 * ④ 検索URLフォールバック（allow_search=true のみ）
 * ⑤ 表示しない（404を出さない）
 * 
 * 👉 「空欄」は失敗ではない
 * 👉 「404リンク」は明確な失敗
 * ============================================================
 *
 * @package HRS
 * @version 5.3.2-URL-NORMALIZE-FIX
 * @change 5.3.2: るるぶ末尾スラッシュ削除、JTBサブパス削除対応
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_OTA_Search_Engine {

    private $cse_api_key;
    private $cse_id;
    private $rakuten_app_id;
    private $moshimo_affiliate_id;

    /** @var array OTA ドメイン定義 */
    private $ota_domains = array(
        'rakuten'  => 'travel.rakuten.co.jp',
        'jalan'    => 'jalan.net',
        'ikyu'     => 'ikyu.com',
        'booking'  => 'booking.com',
        'yahoo'    => 'travel.yahoo.co.jp',
        'jtb'      => 'jtb.co.jp',
        'rurubu'   => 'rurubu.travel',
        'relux'    => 'rlx.jp',
        'yukoyuko' => 'yukoyuko.net',
        'expedia'  => 'expedia.co.jp',
    );

    /**
     * ============================================================
     * OTA別 LinkSwitch ルール定義
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
            'require_id'    => true,   // ★ 数字ID必須（/XXXXX/）
            'allow_search'  => false,  // ★ 検索URL不可
            'search_url'    => null,
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
        $this->cse_api_key = get_option('hrs_google_cse_api_key', '');
        $this->cse_id = get_option('hrs_google_cse_id', '');
        $this->rakuten_app_id = get_option('hrs_rakuten_app_id', '');
        $this->moshimo_affiliate_id = get_option('hrs_moshimo_affiliate_id', '5247247');

        $custom_tier1 = get_option('hrs_ota_tier_1', array());
        if (!empty($custom_tier1) && is_array($custom_tier1)) {
            foreach ($custom_tier1 as $k => $v) {
                if (is_string($k) && is_string($v) && !empty($v)) {
                    $this->ota_domains[$k] = $v;
                }
            }
        }
    }

    /**
     * 全OTAのURL取得
     * 
     * ============================================================
     * 【フォールバック順】
     * ① Google site検索（OTA別詳細URL取得）
     * ② Google CSE（詳細URL取得）
     * ③ 楽天API（確定）
     * ④ 検索URLフォールバック（allow_search=true のOTAのみ）
     * ⑤ 表示しない（404を出さない）
     * ============================================================
     */
    public function search_all_otas($hotel_name, $location = '') {
        error_log('[HRS OTA Search] === START search_all_otas v5.3.2 ===');
        error_log('[HRS OTA Search] Hotel: ' . $hotel_name . ' | Location: ' . $location);

        $urls = array();

        // ========================================
        // ステップ1: Google site検索（OTA別詳細URL取得）
        // ========================================
        foreach ($this->ota_domains as $ota_id => $domain) {
            $found = $this->search_google_site($hotel_name, $domain);
            if (!empty($found) && $this->is_valid_detail_url($found, $ota_id)) {
                $urls[$ota_id] = $this->normalize_url($found, $ota_id);
                error_log("[HRS OTA Search] ① Site search found: {$ota_id} => {$urls[$ota_id]}");
            }
            usleep(300000);
        }

        // ========================================
        // ステップ2: Google CSE（site検索で取れなかったOTA）
        // ========================================
        if ($this->is_cse_configured()) {
            $base_query = $hotel_name;
            if (!empty($location)) {
                $prefecture = $this->extract_prefecture($location);
                if (!empty($prefecture)) {
                    $base_query .= ' ' . $prefecture;
                }
            }

            error_log('[HRS OTA Search] ② CSE search: ' . $base_query);
            $cse_items = $this->search_google_cse($base_query);
            
            foreach ($cse_items as $item) {
                $link = isset($item['link']) ? $item['link'] : '';
                $detected = $this->detect_ota($link);
                
                // 既に取得済みならスキップ
                if (isset($urls[$detected])) continue;
                
                // 詳細URLかチェック
                if ($detected !== 'other' && $this->is_valid_detail_url($link, $detected)) {
                    $urls[$detected] = $this->normalize_url($link, $detected);
                    error_log("[HRS OTA Search] ② CSE found: {$detected} => {$urls[$detected]}");
                }
            }
        }

        // ========================================
        // ステップ3: 楽天API（まだ取得できていない場合）
        // ========================================
        if (!isset($urls['rakuten']) && $this->is_rakuten_configured()) {
            $rakuten_url = $this->search_rakuten_api($hotel_name, $location);
            if (!empty($rakuten_url)) {
                $urls['rakuten'] = $this->apply_moshimo_affiliate($rakuten_url);
                error_log('[HRS OTA Search] ③ Rakuten API: ' . $urls['rakuten']);
            }
        }

        // ========================================
        // ステップ4: 検索URLフォールバック（allow_search=true のみ）
        // ========================================
        $keyword = urlencode($hotel_name);
        foreach ($this->ota_rules as $ota_id => $rule) {
            // 既に取得済みならスキップ
            if (isset($urls[$ota_id])) continue;
            
            // allow_search=false のOTAは空欄のまま（じゃらん・一休・JTB・るるぶ）
            if (empty($rule['allow_search']) || empty($rule['search_url'])) {
                error_log("[HRS OTA Search] ⑤ {$ota_id}: ID必須のため空欄（検索URL不可）");
                continue;
            }
            
            // 検索URLを生成
            $search_url = str_replace('{keyword}', $keyword, $rule['search_url']);
            $urls[$ota_id] = $search_url;
            error_log("[HRS OTA Search] ④ Search URL fallback: {$ota_id} => {$search_url}");
        }

        error_log('[HRS OTA Search] === END search_all_otas ===');
        error_log('[HRS OTA Search] Found: ' . implode(', ', array_keys($urls)));
        
        return $urls;
    }

    /**
     * 詳細ページURLかどうか検証
     * 
     * OTA別のID形式をチェック
     */
    public function is_valid_detail_url($url, $ota_id) {
        if (empty($url)) return false;

        // 検索URLパターンは除外
        $search_patterns = array(
            '/search', '/list?', '/keyword=', '?q=', '?ss=',
            '?destination=', '/searchresults', '/uwp', '/uww',
        );

        foreach ($search_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }

        // OTA別の詳細ページ検証
        switch ($ota_id) {
            case 'jalan':
                // /yadXXXXXX/ 形式のみ許可
                return (bool) preg_match('/\/yad\d+/i', $url);
                
            case 'ikyu':
                // /XXXXXXXX/ 数字ID形式のみ許可
                return (bool) preg_match('/ikyu\.com\/\d+/i', $url);
                
            case 'rakuten':
                // /HOTEL/XXXXX/ 形式
                return (bool) preg_match('/\/HOTEL\/\d+/i', $url);
                
            case 'booking':
                // /hotel/xx/xxxxx.html 形式
                return (bool) preg_match('/\/hotel\/[a-z]{2}\/.+\.html/i', $url);
                
            case 'relux':
                // /XXXXX/ 数字ID形式
                return (bool) preg_match('/rlx\.jp\/\d+/i', $url);
                
            case 'jtb':
                // /kokunai-hotel/htl/XXXXXXX/ 形式（JTB独自ID）
                return (bool) preg_match('/\/htl\/\d+/i', $url);
                
            case 'rurubu':
                // /hotel/japan/{地名}/{ホテルスラッグ} 形式（すべて英語）
                return (bool) preg_match('/\/hotel\/japan\/[a-z0-9\-]+\/[a-z0-9\-]+/i', $url);
        }

        // コンテンツページは除外
        $exclude_paths = array('/review/', '/access/', '/plan/', '/photo/', '/kuchikomi/', '/map/');
        foreach ($exclude_paths as $path) {
            if (stripos($url, $path) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 住所から都道府県を抽出
     */
    private function extract_prefecture($address) {
        if (empty($address)) return '';
        
        $prefectures = array(
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
        );
        
        foreach ($prefectures as $pref) {
            if (mb_strpos($address, $pref) !== false) {
                return $pref;
            }
        }
        
        return '';
    }

    /**
     * MOSHIMOアフィリエイトリンク適用
     */
    private function apply_moshimo_affiliate($url) {
        if (empty($this->moshimo_affiliate_id)) {
            return $url;
        }
        $encoded_url = urlencode($url);
        return "//af.moshimo.com/af/c/click?a_id={$this->moshimo_affiliate_id}&p_id=55&pc_id=55&pl_id=624&url={$encoded_url}";
    }

    /**
     * Google site検索
     */
    public function search_google_site($query, $domain) {
        $q = $query . ' site:' . $domain;
        $search_url = 'https://www.google.com/search?q=' . urlencode($q) . '&hl=ja&num=5';

        $response = wp_remote_get($search_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ja-JP,ja;q=0.9',
            ),
        ));

        if (is_wp_error($response)) {
            error_log('[HRS OTA Search] Site search error: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) return null;

        return $this->extract_url_from_html($body, $domain);
    }

    /**
     * HTMLからURL抽出
     */
    private function extract_url_from_html($html, $domain) {
        $domain_pattern = preg_quote($domain, '/');

        if (preg_match('/\/url\?q=(https?:\/\/[^&"\']*' . $domain_pattern . '[^&"\']*)/i', $html, $match)) {
            $url = urldecode($match[1]);
            $url = preg_replace('/\?.*$/', '', $url);
            return $url;
        }

        return null;
    }

    /**
     * Google CSE検索
     */
    public function search_google_cse($query) {
        if (!$this->is_cse_configured()) return array();

        $params = array(
            'key' => $this->cse_api_key,
            'cx'  => $this->cse_id,
            'q'   => $query,
            'num' => 10,
            'lr'  => 'lang_ja',
        );

        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            error_log('[HRS OTA Search] CSE error: ' . $response->get_error_message());
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            error_log('[HRS OTA Search] CSE API error: ' . ($data['error']['message'] ?? 'unknown'));
            return array();
        }

        return isset($data['items']) ? $data['items'] : array();
    }

    /**
     * 楽天トラベルAPI検索
     */
    public function search_rakuten_api($hotel_name, $location = '') {
        if (!$this->is_rakuten_configured()) return null;

        $keyword = $hotel_name;
        if (!empty($location)) {
            $prefecture = $this->extract_prefecture($location);
            if (!empty($prefecture)) {
                $keyword .= ' ' . $prefecture;
            }
        }

        $params = array(
            'applicationId' => $this->rakuten_app_id,
            'format' => 'json',
            'keyword' => $keyword,
            'hits' => 3,
            'datumType' => 1,
        );

        $endpoint = 'https://app.rakuten.co.jp/services/api/Travel/KeywordHotelSearch/20170426?' . http_build_query($params);

        $response = wp_remote_get($endpoint, array(
            'timeout' => 15,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            error_log('[HRS OTA Search] Rakuten API error: ' . $response->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['hotels'][0]['hotel'][0]['hotelBasicInfo']['hotelInformationUrl'])) {
            return $data['hotels'][0]['hotel'][0]['hotelBasicInfo']['hotelInformationUrl'];
        }

        return null;
    }

    /**
     * URL正規化
     * 
     * @version 5.3.2 - るるぶ末尾スラッシュ削除、JTBサブパス削除対応
     */
    public function normalize_url($url, $ota_id) {
        if (empty($url)) return '';

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        // ========================================
        // OTA別の特殊正規化ルール
        // ========================================
        
        // るるぶ: 末尾の / を削除
        // https://www.rurubu.travel/hotel/japan/atami/xxx/ → https://www.rurubu.travel/hotel/japan/atami/xxx
        if ($ota_id === 'rurubu') {
            $url = preg_replace('/\?.*$/', '', $url); // クエリ除去
            $url = rtrim($url, '/'); // 末尾スラッシュ削除
            return $url;
        }
        
        // JTB: /access/ などのサブパスを削除
        // https://www.jtb.co.jp/kokunai-hotel/htl/4339023/access/ → https://www.jtb.co.jp/kokunai-hotel/htl/4339023/
        if ($ota_id === 'jtb') {
            $url = preg_replace('/\?.*$/', '', $url); // クエリ除去
            // /htl/数字/ の後のサブパスを削除
            $url = preg_replace('/(\/htl\/\d+)\/[a-z]+\/?$/i', '$1/', $url);
            if (!preg_match('/\/$/', $url)) {
                $url .= '/';
            }
            return $url;
        }

        // ========================================
        // 標準の正規化処理
        // ========================================
        
        // 検索URLはクエリを保持
        $rule = isset($this->ota_rules[$ota_id]) ? $this->ota_rules[$ota_id] : array();
        if (empty($rule['require_id'])) {
            // 検索URL可のOTAはクエリ保持
            if (!preg_match('/\/$|\.html$/i', $url)) {
                $url = rtrim($url, '/') . '/';
            }
            return $url;
        }

        // ID必須OTAはクエリ除去
        $url = preg_replace('/\?.*$/', '', $url);

        if (!preg_match('/\/$|\.html$/i', $url)) {
            $url .= '/';
        }

        return $url;
    }

    /**
     * URLからOTA判定
     */
    public function detect_ota($url) {
        if (empty($url)) return 'other';

        $patterns = array(
            'rakuten'     => '/travel\.rakuten\.co\.jp/i',
            'jalan'       => '/jalan\.net/i',
            'ikyu'        => '/ikyu\.com/i',
            'relux'       => '/rlx\.jp|relux\.com/i',
            'booking'     => '/booking\.com/i',
            'jtb'         => '/jtb\.co\.jp/i',
            'rurubu'      => '/rurubu\.travel/i',
            'yahoo'       => '/travel\.yahoo\.co\.jp/i',
            'yukoyuko'    => '/yukoyuko\.net/i',
            'expedia'     => '/expedia\.co\.jp|expedia\.com/i',
        );

        foreach ($patterns as $name => $pat) {
            if (preg_match($pat, $url)) return $name;
        }

        return 'other';
    }

    /**
     * OTAルール取得
     */
    public function get_ota_rule($ota_id) {
        return isset($this->ota_rules[$ota_id]) ? $this->ota_rules[$ota_id] : null;
    }

    /**
     * 公式サイト検索
     */
    public function search_official_site($query, $hotel_name = '') {
        if (!$this->is_cse_configured()) return null;

        $cse_items = $this->search_google_cse($query . ' 公式');
        
        foreach ($cse_items as $item) {
            $link = isset($item['link']) ? $item['link'] : '';
            $title = isset($item['title']) ? $item['title'] : '';
            
            if ($this->detect_ota($link) !== 'other') continue;
            
            if (preg_match('/(公式|オフィシャル|official)/iu', $title)) {
                return $this->normalize_url($link, 'official');
            }
        }

        return null;
    }

    public function is_cse_configured() {
        return !empty($this->cse_api_key) && !empty($this->cse_id);
    }

    public function is_rakuten_configured() {
        return !empty($this->rakuten_app_id);
    }

    public function get_ota_domains() {
        return $this->ota_domains;
    }

    public function get_ota_rules() {
        return $this->ota_rules;
    }

    public function test_connections() {
        $results = array();

        if ($this->is_cse_configured()) {
            $r = $this->search_google_cse('テスト');
            $results['cse'] = array(
                'configured' => true,
                'success' => !empty($r),
                'message' => !empty($r) ? 'CSE接続成功' : 'CSE接続失敗',
            );
        } else {
            $results['cse'] = array(
                'configured' => false,
                'success' => false,
                'message' => 'CSE未設定',
            );
        }

        if ($this->is_rakuten_configured()) {
            $r = $this->search_rakuten_api('東京');
            $results['rakuten'] = array(
                'configured' => true,
                'success' => !empty($r),
                'message' => !empty($r) ? '楽天API接続成功' : '楽天API接続失敗',
            );
        } else {
            $results['rakuten'] = array(
                'configured' => false,
                'success' => false,
                'message' => '楽天API未設定',
            );
        }

        return $results;
    }
}