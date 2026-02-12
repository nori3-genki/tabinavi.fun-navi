<?php
/**
 * OTA選択ロジッククラス
 * 
 * OTA優先度設定（Tier1/2/3）を参照してOTAサイトの選択と優先順位付け
 * 
 * 【修正】検索URLを除外、詳細ページURLのみ表示
 * 
 * @package HRS
 * @version 4.6.0-DETAIL-ONLY
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_OTA_Selector {

    /**
     * OTAサイト定義
     */
    private $ota_sites = array(
        'rakuten' => array(
            'name' => '楽天トラベル',
            'url_base' => 'https://travel.rakuten.co.jp/',
            'affiliate_type' => 'moshimo',
            'strengths' => array('ポイント還元', '国内宿数最大', 'クーポン豊富'),
        ),
        'jalan' => array(
            'name' => 'じゃらん',
            'url_base' => 'https://www.jalan.net/',
            'affiliate_type' => 'direct',
            'strengths' => array('口コミ充実', 'Pontaポイント', '温泉特化'),
        ),
        'ikyu' => array(
            'name' => '一休.com',
            'url_base' => 'https://www.ikyu.com/',
            'affiliate_type' => 'direct',
            'strengths' => array('高級宿特化', 'ポイント即時利用', '限定プラン'),
        ),
        'relux' => array(
            'name' => 'Relux',
            'url_base' => 'https://rlx.jp/',
            'affiliate_type' => 'direct',
            'strengths' => array('厳選高級宿', 'コンシェルジュ', '会員特典'),
        ),
        'booking' => array(
            'name' => 'Booking.com',
            'url_base' => 'https://www.booking.com/',
            'affiliate_type' => 'direct',
            'strengths' => array('海外対応', '無料キャンセル多', '多言語'),
        ),
        'yahoo' => array(
            'name' => 'Yahoo!トラベル',
            'url_base' => 'https://travel.yahoo.co.jp/',
            'affiliate_type' => 'direct',
            'strengths' => array('PayPayポイント', 'クーポン豊富'),
        ),
        'jtb' => array(
            'name' => 'JTB',
            'url_base' => 'https://www.jtb.co.jp/',
            'affiliate_type' => 'direct',
            'strengths' => array('大手信頼性', 'パッケージ充実', 'サポート'),
        ),
        'rurubu' => array(
            'name' => 'るるぶトラベル',
            'url_base' => 'https://rurubu.travel/',
            'affiliate_type' => 'direct',
            'strengths' => array('観光情報連携', 'JTBグループ'),
        ),
        'yukoyuko' => array(
            'name' => 'ゆこゆこ',
            'url_base' => 'https://www.yukoyuko.net/',
            'affiliate_type' => 'direct',
            'strengths' => array('温泉特化', 'シニア向け', '電話予約可'),
        ),
        'expedia' => array(
            'name' => 'Expedia',
            'url_base' => 'https://www.expedia.co.jp/',
            'affiliate_type' => 'direct',
            'strengths' => array('海外ホテル', '航空券セット', 'グローバル'),
        ),
    );

    /**
     * 検索URLパターン（これにマッチするURLは除外）
     */
    private $search_url_patterns = array(
        '/keyword=/i',
        '/search\?/i',
        '/search\//i',
        '/uwp1100/i',
        '/uwp1200/i',
        '/\?q=/i',
        '/\?ss=/i',
        '/\?destination=/i',
        '/searchresults/i',
        '/list\?/i',
        '/KeywordHotelSearch/i',
        '/Hotel-Search/i',
    );

    /**
     * 優先度記号
     */
    const PRIORITY_HIGH = '◎';
    const PRIORITY_MEDIUM = '◯';
    const PRIORITY_LOW = '△';

    /**
     * OTA優先度設定を取得
     * 
     * @return array Tier別のOTA配列
     */
    private function get_tier_settings() {
        return array(
            'tier_1' => get_option('hrs_ota_tier_1', array('rakuten', 'jalan', 'ikyu', 'jtb', 'relux', 'yukoyuko')),
            'tier_2' => get_option('hrs_ota_tier_2', array('booking', 'yahoo')),
            'tier_3' => get_option('hrs_ota_tier_3', array('rurubu', 'expedia')),
        );
    }

    /**
     * OTAがどのTierに属するか取得
     * 
     * @param string $ota_id OTA ID
     * @return int Tier番号（1, 2, 3）。どこにも属さない場合は0
     */
    private function get_ota_tier($ota_id) {
        $tiers = $this->get_tier_settings();
        
        if (in_array($ota_id, (array)$tiers['tier_1'])) {
            return 1;
        }
        if (in_array($ota_id, (array)$tiers['tier_2'])) {
            return 2;
        }
        if (in_array($ota_id, (array)$tiers['tier_3'])) {
            return 3;
        }
        
        return 0; // どのTierにも属さない
    }

    /**
     * OTAがTier1に属するかチェック
     * 
     * @param string $ota_id OTA ID
     * @return bool
     */
    public function is_tier_1($ota_id) {
        return $this->get_ota_tier($ota_id) === 1;
    }

    /**
     * Tier1のOTAリストを取得
     * 
     * @return array
     */
    public function get_tier_1_otas() {
        $tiers = $this->get_tier_settings();
        return (array)$tiers['tier_1'];
    }

    /**
     * 検索URLかどうかをチェック
     * 
     * @param string $url
     * @return bool
     */
    private function is_search_url($url) {
        if (empty($url)) {
            return true; // 空URLも除外
        }

        foreach ($this->search_url_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 設定に基づいてOTAを選択（Tier順）
     * 
     * @param array $options オプション
     * @return array 優先順位付きOTAリスト
     */
    public function select_by_tier($options = array()) {
        $results = array();
        $tiers = $this->get_tier_settings();
        
        // Tier1
        foreach ((array)$tiers['tier_1'] as $ota_id) {
            if (isset($this->ota_sites[$ota_id])) {
                $results[] = $this->build_ota_result($ota_id, 1);
            }
        }
        
        // Tier2
        foreach ((array)$tiers['tier_2'] as $ota_id) {
            if (isset($this->ota_sites[$ota_id])) {
                $results[] = $this->build_ota_result($ota_id, 2);
            }
        }
        
        // Tier3
        foreach ((array)$tiers['tier_3'] as $ota_id) {
            if (isset($this->ota_sites[$ota_id])) {
                $results[] = $this->build_ota_result($ota_id, 3);
            }
        }
        
        return $results;
    }

    /**
     * OTA結果を構築
     */
    private function build_ota_result($ota_id, $tier) {
        $ota = $this->ota_sites[$ota_id];
        $priority = $this->tier_to_priority($tier);
        
        return array(
            'id' => $ota_id,
            'name' => $ota['name'],
            'url_base' => $ota['url_base'],
            'tier' => $tier,
            'priority' => $priority,
            'priority_symbol' => $this->get_priority_symbol($priority),
            'affiliate_type' => $ota['affiliate_type'],
            'strengths' => $ota['strengths'],
        );
    }

    /**
     * Tierを優先度スコアに変換
     */
    private function tier_to_priority($tier) {
        switch ($tier) {
            case 1: return 100;
            case 2: return 70;
            case 3: return 40;
            default: return 0;
        }
    }

    /**
     * 優先度記号取得
     */
    private function get_priority_symbol($priority) {
        if ($priority >= 80) {
            return self::PRIORITY_HIGH;
        } elseif ($priority >= 60) {
            return self::PRIORITY_MEDIUM;
        }
        return self::PRIORITY_LOW;
    }

    /**
     * Tier1のOTAのみ取得（記事表示用）
     * 
     * @param array $existing_urls 既存URL（OTAごと）
     * @return array Tier1でURLが存在するOTAのみ
     */
    public function get_tier_1_with_urls($existing_urls = array()) {
        $tier_1_otas = $this->get_tier_1_otas();
        $results = array();
        
        foreach ($tier_1_otas as $ota_id) {
            // URLが存在し、かつ検索URLでない場合のみ
            if (!empty($existing_urls[$ota_id]) && !$this->is_search_url($existing_urls[$ota_id])) {
                $ota = $this->ota_sites[$ota_id] ?? null;
                if ($ota) {
                    $results[] = array(
                        'id' => $ota_id,
                        'name' => $ota['name'],
                        'url' => $existing_urls[$ota_id],
                        'affiliate_type' => $ota['affiliate_type'],
                    );
                }
            }
        }
        
        return $results;
    }

    /**
     * 記事内リンク用のOTA情報を取得
     * 
     * 【修正】検索URLを除外、詳細ページURLのみ表示
     * 
     * @param string $hotel_name ホテル名
     * @param array $existing_urls 既存URL（OTAごと）
     * @param bool $tier_1_only 廃止（互換性のため残す）
     * @return array
     */
    public function get_article_links($hotel_name, $existing_urls = array(), $tier_1_only = true) {
        $links = array();
        $moshimo_id = get_option('hrs_moshimo_affiliate_id', '5247247');
        
        foreach ($existing_urls as $ota_id => $url) {
            // 空URLまたは検索URLは除外
            if (empty($url) || $this->is_search_url($url)) {
                continue;
            }
            
            $ota = $this->ota_sites[$ota_id] ?? null;
            if (!$ota) {
                continue;
            }
            
            $tier = $this->get_ota_tier($ota_id);
            
            // 楽天のみMOSHIMO経由（まだ適用されていない場合）
            if ($ota_id === 'rakuten' && $ota['affiliate_type'] === 'moshimo' && !empty($moshimo_id)) {
                if (strpos($url, 'moshimo.com') === false) {
                    $url = $this->generate_moshimo_link($url, $moshimo_id);
                }
            }
            
            $links[] = array(
                'id' => $ota_id,
                'name' => $ota['name'],
                'url' => $url,
                'tier' => $tier,
                'priority_symbol' => $this->get_priority_symbol($this->tier_to_priority($tier)),
                'cta_text' => $this->generate_cta_text($ota_id),
            );
        }
        
        // Tier順にソート（Tier 1 → 2 → 3 → 未設定）
        usort($links, function($a, $b) {
            $tier_a = $a['tier'] ?: 99;
            $tier_b = $b['tier'] ?: 99;
            return $tier_a - $tier_b;
        });
        
        return $links;
    }

    /**
     * MOSHIMOアフィリエイトリンク生成
     * 
     * @param string $original_url 元URL
     * @param string $moshimo_id もしもID
     * @return string
     */
    public function generate_moshimo_link($original_url, $moshimo_id = null) {
        if (empty($moshimo_id)) {
            $moshimo_id = get_option('hrs_moshimo_affiliate_id', '5247247');
        }
        
        $encoded_url = urlencode($original_url);
        
        // 楽天トラベル用のもしもリンク
        return "//af.moshimo.com/af/c/click?a_id={$moshimo_id}&p_id=55&pc_id=55&pl_id=624&url={$encoded_url}";
    }

    /**
     * CTA（Call to Action）テキスト生成
     */
    private function generate_cta_text($ota_id) {
        $cta_texts = array(
            'rakuten' => '楽天トラベルで予約する',
            'jalan' => 'じゃらんで予約する',
            'ikyu' => '一休.comで予約する',
            'relux' => 'Reluxで予約する',
            'booking' => 'Booking.comで予約する',
            'yahoo' => 'Yahoo!トラベルで予約する',
            'jtb' => 'JTBで予約する',
            'rurubu' => 'るるぶトラベルで予約する',
            'yukoyuko' => 'ゆこゆこで予約する',
            'expedia' => 'Expediaで予約する',
        );
        
        return $cta_texts[$ota_id] ?? '予約・詳細を見る';
    }

    /**
     * OTA情報取得
     * 
     * @param string $ota_id OTA ID
     * @return array|null
     */
    public function get_ota_info($ota_id) {
        return $this->ota_sites[$ota_id] ?? null;
    }

    /**
     * 全OTA一覧取得
     * 
     * @return array
     */
    public function get_all_otas() {
        return $this->ota_sites;
    }

    /**
     * URLからOTA IDを判定
     * 
     * @param string $url URL
     * @return string|null OTA ID
     */
    public function detect_ota_from_url($url) {
        $patterns = array(
            'rakuten' => '/travel\.rakuten\.co\.jp/i',
            'jalan' => '/jalan\.net/i',
            'ikyu' => '/ikyu\.com/i',
            'relux' => '/rlx\.jp/i',
            'booking' => '/booking\.com/i',
            'yahoo' => '/travel\.yahoo\.co\.jp/i',
            'jtb' => '/jtb\.co\.jp/i',
            'rurubu' => '/rurubu\.travel/i',
            'yukoyuko' => '/yukoyuko\.net/i',
            'expedia' => '/expedia\./i',
        );
        
        foreach ($patterns as $ota_id => $pattern) {
            if (preg_match($pattern, $url)) {
                return $ota_id;
            }
        }
        
        return null;
    }
}