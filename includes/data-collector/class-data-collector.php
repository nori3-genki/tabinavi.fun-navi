<?php
if (!defined('ABSPATH')) exit;

// 依存クラスを読み込み
require_once __DIR__ . '/class-review-collector.php';
require_once __DIR__ . '/class-ota-search-engine.php';
require_once __DIR__ . '/class-text-extractor.php';
require_once __DIR__ . '/class-ota-id-mapper.php';

/**
 * ホテルデータ収集クラス
 * 
 * 楽天API → 他OTA ID抽出 → JTB・ゆこゆこ・じゃらんHTMLパースで
 * プラン・特典・口コミを収集。じゃらんAPI不要。
 *
 * @package HRS
 * @version 4.9.4-CLASS-NAME-FIX
 * 
 * 変更履歴:
 * - 4.9.2-FIX: urls キーを追加（class-article-generator.php との互換性）
 * - 4.9.3-ADDRESS-FIX: address/prefecture キーを追加（所在地保存の互換性修正）
 * - 4.9.4-CLASS-NAME-FIX: HRS_Ota_Search_Engine → HRS_OTA_Search_Engine（クラス名修正）
 */
class HRS_Data_Collector {

    private $review_collector;
    private $ota_search_engine;
    private $text_extractor;

    public function __construct() {
        $this->review_collector = new HRS_Review_Collector();
        // ★ v4.9.4修正: クラス名を HRS_OTA_Search_Engine に変更
        $this->ota_search_engine = new HRS_OTA_Search_Engine();
        $this->text_extractor = new HRS_Text_Extractor();
    }

    /**
     * ホテル情報を一括収集
     */
    public function collect_hotel_data($hotel_name, $location = '') {
        error_log("[HRS Data Collector] collect_hotel_data: {$hotel_name} | {$location}");

        $result = array(
            'hotel_name' => $hotel_name,
            'location' => $location,
            'address' => '',           // ★ 追加：詳細住所（_hrs_hotel_address 用）
            'prefecture' => '',        // ★ 追加：都道府県（_hrs_location 用）
            'otas' => array(),
            'urls' => array(),         // ★ class-article-generator.php との互換性
            'plans' => array(),
            'special_features' => array(),
            'reviews' => array(),
            'access_info' => '',
            'parking_info' => '',
        );

        // === ステップ1: OTA URL収集（詳細URLのみ、取れなければ空欄）===
        $ota_urls = $this->ota_search_engine->search_all_otas($hotel_name, $location);
        $result['otas'] = $ota_urls;
        $result['urls'] = $ota_urls;

        // === ステップ2: 楽天APIでID取得＋他OTA ID抽出 ===
        $rakuten_data = $this->get_rakuten_hotel_data($hotel_name, $location);
        if ($rakuten_data['success'] && !empty($rakuten_data['hotel'])) {
            $result = $this->merge_rakuten_data($result, $rakuten_data['hotel']);

            // ✅ OTA IDマッピング（天成園実データ対応）
            $mapper = new HRS_Ota_Id_Mapper();
            $partner_ids = $mapper->get_ids_and_features($hotel_name);
            
            if (!empty($partner_ids)) {
                $result['partner_ids'] = $partner_ids;
                
                // JTBプラン特典を結果に統合
                if (!empty($partner_ids['jtb_plan_codes'])) {
                    $result['plans'] = $partner_ids['jtb_plan_codes'];
                }
                
                // 高価値特典を統合
                if (!empty($partner_ids['high_value_features'])) {
                    $result['special_features'] = $partner_ids['high_value_features'];
                }
                
                // アクセス・駐車場情報を統合
                if (!empty($partner_ids['access'])) {
                    $result['access_info'] = $partner_ids['access'];
                }
                if (!empty($partner_ids['parking'])) {
                    $result['parking_info'] = $partner_ids['parking'];
                }
            }
        }

        // === ステップ3: 口コミ収集（複数OTA対応）===
        $review_sources = array();
        if (!empty($ota_urls['rakuten'])) $review_sources['rakuten'] = $ota_urls['rakuten'];
        if (!empty($ota_urls['jalan'])) $review_sources['jalan'] = $ota_urls['jalan'];
        if (!empty($ota_urls['ikyu'])) $review_sources['ikyu'] = $ota_urls['ikyu'];

        $reviews_result = $this->review_collector->collect_reviews($hotel_name, $location);
        $result['reviews'] = $reviews_result['reviews'];
        $result['review_summary'] = $reviews_result['summary'];

        // === ステップ4: 特典の高価値キーワード抽出（SEO・変換率最適化）===
        if (!isset($result['high_value_features'])) {
            $result['high_value_features'] = $this->extract_high_value_features($result);
        }

        // ★ 追加：所在地が空の場合、prefectureで補完
        if (empty($result['location']) && !empty($result['prefecture'])) {
            $result['location'] = $result['prefecture'];
        }

        error_log("[HRS Data Collector] Complete: {$hotel_name} | Address: " . $result['address'] . " | Prefecture: " . $result['prefecture'] . " | URLs: " . count($result['urls']));
        return $result;
    }

    /**
     * 楽天APIでホテル検索＋詳細取得
     */
    private function get_rakuten_hotel_data($hotel_name, $location) {
        $api = new HRS_Rakuten_API_Test_Endpoint();

        // 戦略：normal + region（例：天成園 箱根）
        $region = $this->extract_region($hotel_name);
        $keyword = trim("{$hotel_name} {$region}");

        $search = $api->search_hotel($keyword, 1);
        if ($search['success'] && !empty($search['hotels'][0])) {
            $hotel_no = $search['hotels'][0]['hotel_no'];
            $detail = $api->get_hotel_detail($hotel_no);
            if ($detail['success']) {
                return array('success' => true, 'hotel' => $detail['hotel']);
            }
        }

        return array('success' => false);
    }

    /**
     * 楽天データをマージ
     */
    private function merge_rakuten_data($result, $rakuten_hotel) {
        $result['hotel_name'] = $rakuten_hotel['name'] ?? $result['hotel_name'];
        $result['access_info'] = $rakuten_hotel['access'] ?? $result['access_info'];
        $result['parking_info'] = $rakuten_hotel['parking'] ?? $result['parking_info'];
        $result['rakuten_data'] = $rakuten_hotel;
        
        // ★ 追加：詳細住所（_hrs_hotel_address 用）
        if (!empty($rakuten_hotel['address'])) {
            $result['address'] = $rakuten_hotel['address'];
            // location にも設定（後方互換性）
            $result['location'] = $rakuten_hotel['address'];
        }
        
        // ★ 追加：都道府県（_hrs_location 用）
        if (!empty($rakuten_hotel['address'])) {
            $prefecture = $this->extract_prefecture($rakuten_hotel['address']);
            if (!empty($prefecture)) {
                $result['prefecture'] = $prefecture;
            }
        }
        
        // ★ 追加：楽天施設番号を保存（料金自動取得用）
        if (!empty($rakuten_hotel['hotel_no'])) {
            $result['rakuten_hotel_no'] = $rakuten_hotel['hotel_no'];
        }
        
        return $result;
    }

    /**
     * 住所から都道府県を抽出
     */
    private function extract_prefecture($address) {
        if (empty($address)) {
            return '';
        }
        
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
     * 高価値特典キーワード抽出（SEO・変換率最適化）
     */
    private function extract_high_value_features($result) {
        $features = array();

        // 全OTAの特典リストを統合
        $all_features = array_merge(
            $result['special_features'] ?? array(),
            $result['jtb_data']['special_features'] ?? array(),
            $result['yukoyuko_data']['yukoyuko_exclusive'] ?? array()
        );

        // 高変換キーワードを優先抽出（天成園実データ対応）
        $high_value_keywords = array(
            '貸切風呂' => '源泉掛け流しの貸切風呂（1時間無料）',
            'レイトチェックアウト' => 'レイトチェックアウト（11:00までOK）',
            '梅酒' => 'スタッフ厳選・梅酒3種飲み比べ',
            'カップル' => 'カップル限定プラン（記念日サプライズ対応）',
            '女子旅' => 'JILLSTUART浴衣＆フェイスマスク付き',
            '天空露天' => '全長17m・箱根の山々を一望の天空大露天風呂',
            'ライブキッチン' => 'シェフ直営・約60種のライブキッチン付きバイキング',
        );

        foreach ($high_value_keywords as $keyword => $text) {
            foreach ($all_features as $f) {
                if (strpos($f, $keyword) !== false) {
                    $features[] = $text;
                    break;
                }
            }
        }

        return array_unique($features);
    }

    /**
     * 地域名抽出（例：天成園 → 箱根）
     */
    private function extract_region($hotel_name) {
        $regions = array('箱根', '熱海', '伊豆', '草津', '由布院', '別府');
        foreach ($regions as $region) {
            if (strpos($hotel_name, $region) !== false) {
                return $region;
            }
        }
        return '';
    }

    /**
     * HQC学習用に best_params を構築
     */
    public function build_best_params($result) {
        $params = array(
            'hotel_name' => $result['hotel_name'],
            'location' => $result['location'],
        );

        // IDマッピング保存
        if (!empty($result['rakuten_data']['hotel_no'])) {
            $params['rakuten_id'] = $result['rakuten_data']['hotel_no'];
        }
        if (!empty($result['partner_ids'])) {
            $pid = $result['partner_ids'];
            $params['jalan_id'] = $pid['jalan_id'] ?? null;
            $params['ikyu_id'] = $pid['ikyu_id'] ?? null;
            $params['yukoyuko_id'] = $pid['yukoyuko_id'] ?? null;
            $params['jtb_codes'] = array_keys($pid['jtb_plan_codes'] ?? array());
        }

        // 高価値特典保存
        $params['high_value_features'] = $result['high_value_features'];

        // 最適ペルソナ推定（特典から）
        $persona = 'general';
        if (in_array('カップル限定プラン', implode(' ', $result['high_value_features'] ?? array()))) {
            $persona = 'couple';
        } elseif (in_array('女子旅', implode(' ', $result['high_value_features'] ?? array()))) {
            $persona = 'solo';
        } elseif (strpos($result['hotel_name'], 'ファミリー') !== false) {
            $persona = 'family';
        }
        $params['best_persona'] = $persona;

        return $params;
    }
}