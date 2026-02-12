<?php
/**
 * データ収集クラス（HQC対応完全版）
 * 
 * Google CSE から10サイト検索
 * HQC（High Quality Content）評価対応
 * - ソース品質評価（E-E-A-T加点）
 * - 信頼性加重統合
 * - 感情価値抽出
 * - コンテンツギャップ検出
 * - OTA URL正規化（一休・じゃらん対応）
 * 
 * @package HRS
 * @version 4.3.1-URL-FIX
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Data_Collector {

    /**
     * API設定
     */
    private $api_key;
    private $cse_id;

    /**
     * ソース信頼性スコア（E-E-A-T基準）
     */
    private $source_trust_scores = array(
        'official'    => 0.95,  // 公式サイト
        'rakuten'     => 0.90,  // 楽天トラベル
        'ikyu'        => 0.90,  // 一休.com
        'jalan'       => 0.85,  // じゃらん
        'booking'     => 0.80,  // Booking.com
        'jtb'         => 0.85,  // JTB
        'rurubu'      => 0.80,  // るるぶトラベル
        'yahoo'       => 0.75,  // Yahoo!トラベル
        'expedia'     => 0.75,  // Expedia
        'yukoyuko'    => 0.80,  // ゆこゆこネット
        'tripadvisor' => 0.70,  // TripAdvisor（レビュー系）
        'google'      => 0.65,  // Googleマップ等
        'other'       => 0.50,  // その他
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->api_key = get_option('hrs_google_cse_api_key', '');
        $this->cse_id = get_option('hrs_google_cse_id', '');
    }

    /**
     * ホテルデータを収集（HQC対応）
     * 
     * @param string $hotel_name ホテル名
     * @param string $location 所在地（任意）
     * @return array|false
     */
    public function collect_hotel_data($hotel_name, $location = '') {
        error_log("[HRS Data Collector] 「{$hotel_name}」のデータ収集開始（HQC対応）");

        // API設定チェック
        if (empty($this->api_key) || empty($this->cse_id)) {
            error_log("[HRS Data Collector] Google CSE APIキーまたはCSE IDが未設定");
            throw new Exception('Google CSE APIキーまたはSearch Engine IDが設定されていません');
        }

        // 検索クエリ構築
        $query = $hotel_name;
        if (!empty($location)) {
            $query .= ' ' . $location;
        }

        // Google CSE検索実行
        $search_results = $this->search_google_cse($query);

        if (empty($search_results)) {
            error_log("[HRS Data Collector] 検索結果が0件");
            return false;
        }

        // ホテル情報を抽出・統合（HQC対応）
        $hotel_data = $this->extract_hotel_info_hqc($search_results, $hotel_name);

        if (empty($hotel_data)) {
            error_log("[HRS Data Collector] ホテル情報の抽出に失敗");
            return false;
        }

        // HQCスコアを計算
        $hotel_data['hqc_score'] = $this->calculate_hqc_score($hotel_data);

        error_log("[HRS Data Collector] データ収集完了: " . $hotel_data['hotel_name'] . " (HQC: " . $hotel_data['hqc_score'] . ")");
        return $hotel_data;
    }

    /**
     * Google CSE検索
     * 
     * @param string $query 検索クエリ
     * @return array
     */
    private function search_google_cse($query) {
        $url = 'https://www.googleapis.com/customsearch/v1';
        $params = array(
            'key' => $this->api_key,
            'cx' => $this->cse_id,
            'q' => $query,
            'num' => 10,
            'lr' => 'lang_ja',
        );

        $request_url = $url . '?' . http_build_query($params);

        $response = wp_remote_get($request_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            error_log("[HRS Data Collector] API Error: " . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            error_log("[HRS Data Collector] CSE Error: " . $data['error']['message']);
            return array();
        }

        if (!isset($data['items']) || empty($data['items'])) {
            error_log("[HRS Data Collector] 検索結果なし");
            return array();
        }

        return $data['items'];
    }

    /**
     * OTA URLを正規化
     * 
     * 一休: restaurant.ikyu.com → www.ikyu.com に変換
     * じゃらん: /photo/ や /photo を削除
     * 
     * @param string $url
     * @param string $source
     * @return string
     */
    private function normalize_ota_url($url, $source) {
    if (empty($url)) {
        return $url;
    }

    switch ($source) {
        case 'ikyu':
            // restaurant.ikyu.com → www.ikyu.com
            $url = preg_replace('/restaurant\.ikyu\.com/', 'www.ikyu.com', $url);
            // /restaurant/ パス削除
            $url = preg_replace('/\/restaurant\//', '/', $url);
            // /review/ や /review を削除（★追加）
            $url = preg_replace('/\/review\/?$/', '/', $url);
            $url = preg_replace('/\/review\?.*$/', '/', $url);
            break;

        case 'jalan':
            // /photo/ や /photo を削除
            $url = preg_replace('/\/photo\/?$/', '/', $url);
            $url = preg_replace('/\/photo\?.*$/', '/', $url);
            // /review/ も削除（★追加）
            $url = preg_replace('/\/review\/?$/', '/', $url);
            // /kuchikomi/ も削除（★追加）
            $url = preg_replace('/\/kuchikomi\/?.*$/', '/', $url);
            break;

        case 'rakuten':
            // /review/ 削除（★追加）
            $url = preg_replace('/\/review\/?$/', '/', $url);
            break;

        case 'booking':
            // /reviews 削除（★追加）
            $url = preg_replace('/\/reviews\..*$/', '.html', $url);
            break;

        case 'tripadvisor':
            // トリップアドバイザーはレビューページが多いのでスキップしない
            break;
    }

    return $url;
}
    /**
     * 検索結果からホテル情報を抽出（HQC対応版）
     * 
     * @param array $results 検索結果
     * @param string $hotel_name ホテル名
     * @return array
     */
    private function extract_hotel_info_hqc($results, $hotel_name) {
        $hotel_data = array(
            'hotel_name' => $hotel_name,
            'address' => '',
            'description' => '',
            'features' => array(),
            'emotions' => array(),           // 感情価値（HQC追加）
            'experience_keywords' => array(), // 体験キーワード（HQC追加）
            'urls' => array(),
            'sources' => array(),
            'content_gaps' => array(),       // コンテンツギャップ（HQC追加）
        );

        $descriptions = array();
        $features = array();
        $emotions = array();
        $weighted_descriptions = array(); // 信頼性加重用

        foreach ($results as $result) {
            $title = isset($result['title']) ? $result['title'] : '';
            $snippet = isset($result['snippet']) ? $result['snippet'] : '';
            $link = isset($result['link']) ? $result['link'] : '';
            $source = $this->detect_source($link);
            $trust_score = $this->source_trust_scores[$source] ?? 0.5;

            // ホテル名の一致チェック（70%ルール）
            if (!$this->is_hotel_match($title, $snippet, $hotel_name)) {
                continue;
            }

            // URL正規化してから保存
            if (!empty($link)) {
                $normalized_link = $this->normalize_ota_url($link, $source);
                $hotel_data['urls'][$source] = $normalized_link;
                $hotel_data['sources'][] = array(
                    'name' => $source,
                    'url' => $normalized_link,
                    'title' => $title,
                    'snippet' => $snippet,
                    'trust_score' => $trust_score,
                );
            }

            // 説明文収集（信頼性加重）
            if (!empty($snippet)) {
                $weighted_descriptions[] = array(
                    'text' => $snippet,
                    'weight' => $trust_score,
                );
                $descriptions[] = $snippet;
            }

            // 特徴抽出
            $extracted_features = $this->extract_features($snippet);
            $features = array_merge($features, $extracted_features);

            // 感情価値抽出（HQC）
            $extracted_emotions = $this->extract_emotions($snippet);
            $emotions = array_merge($emotions, $extracted_emotions);

            // 住所抽出
            if (empty($hotel_data['address'])) {
                $address = $this->extract_address($snippet);
                if (!empty($address)) {
                    $hotel_data['address'] = $address;
                }
            }
        }

        // 説明文を信頼性加重で統合
        if (!empty($weighted_descriptions)) {
            $hotel_data['description'] = $this->merge_descriptions_weighted($weighted_descriptions);
        }

        // 特徴を重複排除
        $hotel_data['features'] = array_values(array_unique($features));

        // 感情価値を重複排除
        $hotel_data['emotions'] = array_values(array_unique($emotions));

        // 体験キーワード抽出
        $hotel_data['experience_keywords'] = $this->extract_experience_keywords($descriptions);

        // コンテンツギャップ検出
        $hotel_data['content_gaps'] = $this->detect_content_gaps($hotel_data);

        // 最低限のデータがあるかチェック
        if (empty($hotel_data['sources'])) {
            return array();
        }

        return $hotel_data;
    }

    /**
     * HQCスコアを計算
     * 
     * @param array $hotel_data
     * @return float
     */
    private function calculate_hqc_score($hotel_data) {
        $score = 0;

        // ① ソース品質加点（40%）
        $source_score = 0;
        if (!empty($hotel_data['sources'])) {
            foreach ($hotel_data['sources'] as $source) {
                $source_score += $source['trust_score'] ?? 0.5;
            }
            $source_score = min(1.0, $source_score / count($hotel_data['sources']));
        }

        // ② 情報量スコア（25%）
        $description_length = mb_strlen($hotel_data['description']);
        $info_score = min(1.0, $description_length / 800);

        // ③ 特徴多様性スコア（15%）
        $feature_count = count($hotel_data['features']);
        $feature_score = min(1.0, $feature_count / 10);

        // ④ 感情価値スコア（10%）
        $emotion_count = count($hotel_data['emotions']);
        $emotion_score = min(1.0, $emotion_count / 5);

        // ⑤ コンテンツ完全性スコア（10%）
        $gap_count = count($hotel_data['content_gaps']);
        $completeness_score = max(0, 1.0 - ($gap_count * 0.15));

        // 総合スコア
        $score = ($source_score * 0.40)
               + ($info_score * 0.25)
               + ($feature_score * 0.15)
               + ($emotion_score * 0.10)
               + ($completeness_score * 0.10);

        return round($score, 3);
    }

    /**
     * ホテル名一致チェック（70%ルール）
     * 
     * @param string $title タイトル
     * @param string $snippet スニペット
     * @param string $hotel_name ホテル名
     * @return bool
     */
    private function is_hotel_match($title, $snippet, $hotel_name) {
        $combined = $title . ' ' . $snippet;
        
        // 正規化
        $normalized_combined = $this->normalize_text($combined);
        $normalized_hotel = $this->normalize_text($hotel_name);

        // 完全一致
        if (strpos($normalized_combined, $normalized_hotel) !== false) {
            return true;
        }

        // キーワード分割マッチング（70%ルール）
        $keywords = $this->extract_keywords($hotel_name);
        if (empty($keywords)) {
            return false;
        }

        $match_count = 0;
        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) >= 2 && strpos($normalized_combined, $keyword) !== false) {
                $match_count++;
            }
        }

        $threshold = ceil(count($keywords) * 0.7);
        return $match_count >= $threshold;
    }

    /**
     * テキスト正規化
     * 
     * @param string $text
     * @return string
     */
    private function normalize_text($text) {
        // 全角スペース→半角
        $text = str_replace('　', ' ', $text);
        // 連続スペース削除
        $text = preg_replace('/\s+/', ' ', $text);
        // 小文字統一
        $text = mb_strtolower($text);
        return trim($text);
    }

    /**
     * ホテル名からキーワード抽出
     * 
     * @param string $hotel_name
     * @return array
     */
    private function extract_keywords($hotel_name) {
        // 記号を除去
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $hotel_name);
        $parts = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($parts, function($p) {
            return mb_strlen($p) >= 2;
        }));
    }

    /**
     * ソース検出（E-E-A-T対応）
     * 
     * @param string $url
     * @return string
     */
    private function detect_source($url) {
        if (empty($url)) {
            return 'other';
        }

        $patterns = array(
            'rakuten'     => '/travel\.rakuten\.co\.jp/i',
            'jalan'       => '/jalan\.net/i',
            'ikyu'        => '/ikyu\.com/i',
            'booking'     => '/booking\.com/i',
            'jtb'         => '/jtb\.co\.jp/i',
            'rurubu'      => '/rurubu\.travel/i',
            'yahoo'       => '/travel\.yahoo\.co\.jp/i',
            'expedia'     => '/expedia\./i',
            'yukoyuko'    => '/yukoyuko\.net/i',
            'tripadvisor' => '/tripadvisor\./i',
            'google'      => '/google\./i',
        );

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $url)) {
                return $name;
            }
        }

        // 公式サイト判定
        if (preg_match('/\.(co\.jp|jp|com)$/i', parse_url($url, PHP_URL_HOST))) {
            return 'official';
        }

        return 'other';
    }

    /**
     * 特徴抽出（拡張版）
     * 
     * @param string $text
     * @return array
     */
    private function extract_features($text) {
        $features_map = array(
            // 温泉・風呂
            '温泉' => '温泉',
            '大浴場' => '大浴場',
            '露天風呂' => '露天風呂',
            '貸切風呂' => '貸切風呂',
            '源泉掛け流し' => '源泉掛け流し',
            '天然温泉' => '天然温泉',
            'サウナ' => 'サウナ',
            '岩盤浴' => '岩盤浴',
            
            // 食事
            '朝食' => '朝食が好評',
            '夕食' => '夕食が好評',
            'バイキング' => 'バイキング',
            'ビュッフェ' => 'ビュッフェ',
            '懐石' => '懐石料理',
            '会席' => '会席料理',
            'レストラン' => 'レストラン併設',
            '部屋食' => '部屋食対応',
            
            // ロケーション
            '駅近' => '駅から近い',
            '駅前' => '駅前立地',
            '送迎' => '送迎サービス',
            '展望' => '展望が良い',
            '景色' => '景色が良い',
            'オーシャンビュー' => 'オーシャンビュー',
            '海' => '海が近い',
            '山' => '山の景色',
            '川沿い' => '川沿い',
            '夜景' => '夜景が見える',
            
            // 設備・サービス
            'スパ' => 'スパ施設',
            'エステ' => 'エステ',
            'プール' => 'プール',
            'ジム' => 'フィットネスジム',
            'WiFi' => 'WiFi完備',
            'Wi-Fi' => 'WiFi完備',
            '駐車場' => '駐車場あり',
            'ルームサービス' => 'ルームサービス',
            
            // ターゲット
            'ペット' => 'ペット可',
            '家族' => '家族向け',
            'ファミリー' => 'ファミリー向け',
            'カップル' => 'カップル向け',
            '記念日' => '記念日におすすめ',
            'ビジネス' => 'ビジネス利用可',
        );

        $found = array();
        foreach ($features_map as $keyword => $label) {
            if (mb_strpos($text, $keyword) !== false) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * 感情価値抽出（HQC対応）
     * 
     * @param string $text
     * @return array
     */
    private function extract_emotions($text) {
        $emotion_keywords = array(
            // ポジティブ感情
            '落ち着く' => '落ち着ける雰囲気',
            '癒される' => '癒し',
            'リラックス' => 'リラックス',
            '贅沢' => '贅沢感',
            '至福' => '至福のひととき',
            '感動' => '感動体験',
            '素敵' => '素敵',
            '最高' => '最高の体験',
            '満足' => '満足度が高い',
            
            // 雰囲気
            '静か' => '静かな環境',
            '綺麗' => '綺麗',
            '清潔' => '清潔感',
            'おしゃれ' => 'おしゃれ',
            'モダン' => 'モダンな雰囲気',
            '和風' => '和の趣',
            '高級感' => '高級感',
            'ラグジュアリー' => 'ラグジュアリー',
            
            // 体験
            '思い出' => '思い出に残る',
            '特別' => '特別な時間',
            'ゆったり' => 'ゆったり過ごせる',
            'のんびり' => 'のんびり',
            'リフレッシュ' => 'リフレッシュ',
            '非日常' => '非日常体験',
        );

        $found = array();
        foreach ($emotion_keywords as $keyword => $label) {
            if (mb_strpos($text, $keyword) !== false) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * 体験キーワード抽出
     * 
     * @param array $descriptions
     * @return array
     */
    private function extract_experience_keywords($descriptions) {
        $text = implode(' ', $descriptions);
        
        $experience_patterns = array(
            '宿泊', '滞在', 'チェックイン', 'チェックアウト',
            '到着', '出発', '利用', '体験', '堪能',
            '朝', '夜', '夕方', '昼', '深夜',
            '散歩', '観光', '食事', '入浴', '就寝',
        );

        $found = array();
        foreach ($experience_patterns as $pattern) {
            if (mb_strpos($text, $pattern) !== false) {
                $found[] = $pattern;
            }
        }

        return array_unique($found);
    }

    /**
     * 住所抽出
     * 
     * @param string $text
     * @return string
     */
    private function extract_address($text) {
        // 都道府県から始まる住所パターン
        $prefectures = '北海道|青森県|岩手県|宮城県|秋田県|山形県|福島県|茨城県|栃木県|群馬県|埼玉県|千葉県|東京都|神奈川県|新潟県|富山県|石川県|福井県|山梨県|長野県|岐阜県|静岡県|愛知県|三重県|滋賀県|京都府|大阪府|兵庫県|奈良県|和歌山県|鳥取県|島根県|岡山県|広島県|山口県|徳島県|香川県|愛媛県|高知県|福岡県|佐賀県|長崎県|熊本県|大分県|宮崎県|鹿児島県|沖縄県';
        
        $pattern = '/(' . $prefectures . ')[^\s、。\n]{5,50}/u';

        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * 説明文の信頼性加重統合
     * 
     * @param array $weighted_descriptions
     * @return string
     */
    private function merge_descriptions_weighted($weighted_descriptions) {
        // 信頼性でソート（降順）
        usort($weighted_descriptions, function($a, $b) {
            return $b['weight'] <=> $a['weight'];
        });

        // 上位3つを連結
        $top = array_slice($weighted_descriptions, 0, 3);
        $texts = array_column($top, 'text');

        // 重複文を除去
        $unique_texts = array();
        foreach ($texts as $text) {
            $normalized = $this->normalize_text($text);
            $is_duplicate = false;
            
            foreach ($unique_texts as $existing) {
                if (similar_text($normalized, $this->normalize_text($existing)) > 80) {
                    $is_duplicate = true;
                    break;
                }
            }
            
            if (!$is_duplicate) {
                $unique_texts[] = $text;
            }
        }

        $merged = implode(' ', $unique_texts);

        // 長すぎる場合は切り詰め
        if (mb_strlen($merged) > 600) {
            $merged = mb_substr($merged, 0, 597) . '...';
        }

        return $merged;
    }

    /**
     * コンテンツギャップ検出
     * 
     * @param array $hotel_data
     * @return array
     */
    private function detect_content_gaps($hotel_data) {
        $gaps = array();

        // 必須情報のチェック
        if (empty($hotel_data['address'])) {
            $gaps[] = '住所情報が不足';
        }

        if (mb_strlen($hotel_data['description']) < 100) {
            $gaps[] = '説明文が短い';
        }

        if (count($hotel_data['features']) < 3) {
            $gaps[] = '特徴情報が不足';
        }

        if (empty($hotel_data['emotions'])) {
            $gaps[] = '感情的価値の情報が不足';
        }

        // ソースの多様性チェック
        $source_count = count($hotel_data['sources']);
        if ($source_count < 3) {
            $gaps[] = '情報ソースが少ない';
        }

        // 高信頼ソースの存在チェック
        $has_high_trust = false;
        foreach ($hotel_data['sources'] as $source) {
            if (($source['trust_score'] ?? 0) >= 0.85) {
                $has_high_trust = true;
                break;
            }
        }
        if (!$has_high_trust) {
            $gaps[] = '高信頼ソースが不足';
        }

        return $gaps;
    }

    /**
     * APIキー設定チェック
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->cse_id);
    }

    /**
     * テスト用: API接続確認
     * 
     * @return array
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'APIキーまたはCSE IDが未設定です',
            );
        }

        $results = $this->search_google_cse('テスト');

        if (!empty($results)) {
            return array(
                'success' => true,
                'message' => 'API接続成功',
                'results_count' => count($results),
            );
        }

        return array(
            'success' => false,
            'message' => 'API接続失敗',
        );
    }

    /**
     * HQCスコアの評価ラベルを取得
     * 
     * @param float $score
     * @return string
     */
    public function get_hqc_label($score) {
        if ($score >= 0.85) {
            return 'excellent';
        } elseif ($score >= 0.70) {
            return 'good';
        } elseif ($score >= 0.50) {
            return 'fair';
        } else {
            return 'poor';
        }
    }
}