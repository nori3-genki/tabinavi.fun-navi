<?php
/**
 * 口コミ収集クラス
 * 
 * Google CSE経由で各OTAの口コミスニペットを収集し、
 * ペルソナ別に分類・整形する
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Review_Collector {

    /** @var string Google CSE API Key */
    private $cse_api_key;

    /** @var string Google CSE ID */
    private $cse_id;

    /** @var array 口コミ検索用のOTAドメイン */
    private $review_domains = array(
        'rakuten' => 'travel.rakuten.co.jp',
        'jalan'   => 'jalan.net',
        'ikyu'    => 'ikyu.com',
        'yahoo'   => 'travel.yahoo.co.jp',
    );

    /** @var array ペルソナ判定キーワード */
    private $persona_keywords = array(
        'family' => array(
            '子供', '子ども', 'こども', 'キッズ', '家族', 'ファミリー',
            '赤ちゃん', 'ベビー', '息子', '娘', '孫', '三世代',
            'お子様', '子連れ', '幼児', '小学生', '中学生',
        ),
        'couple' => array(
            '彼女', '彼氏', '妻', '夫', '嫁', '旦那', '主人',
            'カップル', '二人', 'ふたり', '記念日', '誕生日',
            '結婚記念', 'プロポーズ', 'デート', '新婚',
        ),
        'solo' => array(
            '一人', 'ひとり', '1人', 'ソロ', '単身', 'おひとりさま',
            '一人旅', 'ひとり旅', '出張', 'ビジネス',
        ),
        'senior' => array(
            '母', '父', '両親', '祖父', '祖母', 'おばあちゃん', 'おじいちゃん',
            '還暦', '古希', '喜寿', '傘寿', '米寿', '親孝行',
            '足が悪い', '車椅子', 'バリアフリー', '高齢',
        ),
        'luxury' => array(
            '特別', 'スイート', 'VIP', 'ラグジュアリー', '高級',
            'エグゼクティブ', 'プレミアム', '極上', '贅沢',
        ),
        'budget' => array(
            'コスパ', 'お得', '安い', 'リーズナブル', '格安',
            '値段の割に', '価格以上', 'この値段で', 'お値打ち',
        ),
    );

    /** @var array ポジティブキーワード */
    private $positive_keywords = array(
        '最高', '素晴らしい', '良い', '良かった', 'よかった',
        '満足', '感動', '嬉しい', 'うれしい', '喜び', '喜んで',
        '綺麗', 'きれい', '清潔', '美味しい', 'おいしい',
        '親切', '丁寧', '快適', 'リラックス', '癒し', '癒やし',
        'おすすめ', 'オススメ', 'また来たい', 'リピート',
        '大満足', '期待以上', '想像以上', '最高でした',
    );

    /** @var array 体験キーワード（具体的なシーン） */
    private $experience_keywords = array(
        // 食事系
        '朝食', '夕食', 'ディナー', 'バイキング', 'ビュッフェ',
        '食べ放題', '会席', '懐石', 'コース', '料理',
        // 温泉・風呂系
        '温泉', '露天風呂', '大浴場', '貸切風呂', '客室露天',
        '泉質', '湯', 'サウナ', 'スパ',
        // 部屋系
        '部屋', '客室', 'ベッド', '布団', '眺め', '景色', 'オーシャンビュー',
        // サービス系
        'スタッフ', '接客', 'サービス', 'おもてなし', 'チェックイン',
        // アクティビティ系
        'プール', 'キッズルーム', 'ゲームコーナー', 'カラオケ',
        'アクティビティ', 'イベント', 'ショー',
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->cse_api_key = get_option('hrs_google_cse_api_key', '');
        $this->cse_id = get_option('hrs_google_cse_id', '');
    }

    /**
     * ホテルの口コミを収集
     * 
     * @param string $hotel_name ホテル名
     * @param string $location 地域（オプション）
     * @return array
     */
    public function collect_reviews($hotel_name, $location = '') {
        if (empty($hotel_name)) {
            return $this->empty_result();
        }

        error_log('[HRS Review Collector] Start collecting reviews for: ' . $hotel_name);

        $all_reviews = array();
        $base_query = $hotel_name;
        if (!empty($location)) {
            $base_query .= ' ' . $location;
        }

        // 各OTAから口コミを収集
        foreach ($this->review_domains as $ota_id => $domain) {
            $reviews = $this->search_reviews_from_ota($base_query, $ota_id, $domain);
            $all_reviews = array_merge($all_reviews, $reviews);
            
            // API制限対策
            usleep(300000); // 300ms
        }

        // 重複排除
        $all_reviews = $this->deduplicate_reviews($all_reviews);

        // ペルソナ分類
        $all_reviews = $this->classify_by_persona($all_reviews);

        // サマリー生成
        $summary = $this->generate_summary($all_reviews, $hotel_name);

        error_log('[HRS Review Collector] Collected ' . count($all_reviews) . ' reviews');

        return array(
            'reviews' => $all_reviews,
            'summary' => $summary,
        );
    }

    /**
     * OTAから口コミを検索
     * 
     * @param string $query 検索クエリ
     * @param string $ota_id OTA識別子
     * @param string $domain ドメイン
     * @return array
     */
    private function search_reviews_from_ota($query, $ota_id, $domain) {
        $reviews = array();

        // CSEで検索
        if ($this->is_cse_configured()) {
            $cse_reviews = $this->search_via_cse($query . ' 口コミ', $domain);
            foreach ($cse_reviews as $review) {
                $review['source'] = $ota_id;
                $reviews[] = $review;
            }
        }

        // CSEがない場合はGoogle検索スクレイピング
        if (empty($reviews)) {
            $scrape_reviews = $this->search_via_google($query . ' 口コミ site:' . $domain);
            foreach ($scrape_reviews as $review) {
                $review['source'] = $ota_id;
                $reviews[] = $review;
            }
        }

        return $reviews;
    }

    /**
     * Google CSEで口コミ検索
     * 
     * @param string $query 検索クエリ
     * @param string $domain 対象ドメイン
     * @return array
     */
    private function search_via_cse($query, $domain) {
        $params = array(
            'key' => $this->cse_api_key,
            'cx'  => $this->cse_id,
            'q'   => $query . ' site:' . $domain,
            'num' => 5,
            'lr'  => 'lang_ja',
        );

        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            error_log('[HRS Review Collector] CSE error: ' . $response->get_error_message());
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            error_log('[HRS Review Collector] CSE API error: ' . ($data['error']['message'] ?? 'unknown'));
            return array();
        }

        $reviews = array();
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $snippet = isset($item['snippet']) ? $item['snippet'] : '';
                
                // 口コミらしい内容かチェック
                if ($this->is_review_content($snippet)) {
                    $reviews[] = array(
                        'text'      => $this->clean_snippet($snippet),
                        'title'     => isset($item['title']) ? $item['title'] : '',
                        'url'       => isset($item['link']) ? $item['link'] : '',
                        'source'    => '',
                        'sentiment' => $this->analyze_sentiment($snippet),
                        'keywords'  => $this->extract_keywords($snippet),
                    );
                }
            }
        }

        return $reviews;
    }

    /**
     * Google検索スクレイピング（CSEがない場合のフォールバック）
     * 
     * @param string $query 検索クエリ
     * @return array
     */
    private function search_via_google($query) {
        $search_url = 'https://www.google.com/search?q=' . urlencode($query) . '&hl=ja&num=5';

        $response = wp_remote_get($search_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'ja-JP,ja;q=0.9',
            ),
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $reviews = array();

        // スニペットを抽出
        if (preg_match_all('/<span[^>]*class="[^"]*"[^>]*>([^<]{50,300})<\/span>/u', $body, $matches)) {
            foreach ($matches[1] as $snippet) {
                $clean = strip_tags(html_entity_decode($snippet, ENT_QUOTES, 'UTF-8'));
                
                if ($this->is_review_content($clean)) {
                    $reviews[] = array(
                        'text'      => $this->clean_snippet($clean),
                        'title'     => '',
                        'url'       => '',
                        'source'    => '',
                        'sentiment' => $this->analyze_sentiment($clean),
                        'keywords'  => $this->extract_keywords($clean),
                    );
                }
            }
        }

        return array_slice($reviews, 0, 5);
    }

    /**
     * 口コミらしい内容かチェック
     * 
     * @param string $text
     * @return bool
     */
    private function is_review_content($text) {
        // 最低文字数
        if (mb_strlen($text) < 30) {
            return false;
        }

        // 体験キーワードが含まれているか
        foreach ($this->experience_keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                return true;
            }
        }

        // 感情キーワードが含まれているか
        foreach ($this->positive_keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * スニペットをクリーンアップ
     * 
     * @param string $text
     * @return string
     */
    private function clean_snippet($text) {
        // HTMLエンティティをデコード
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // 余分な空白を整理
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 日付パターンを除去
        $text = preg_replace('/\d{4}年\d{1,2}月\d{1,2}日/', '', $text);
        $text = preg_replace('/\d{4}\/\d{1,2}\/\d{1,2}/', '', $text);
        
        // 評価パターンを除去
        $text = preg_replace('/[\d\.]+点/', '', $text);
        $text = preg_replace('/★+/', '', $text);
        
        // 「...」で始まる場合は除去
        $text = preg_replace('/^\.{2,}/', '', $text);
        
        return trim($text);
    }

    /**
     * 感情分析
     * 
     * @param string $text
     * @return string positive|negative|neutral
     */
    private function analyze_sentiment($text) {
        $positive_count = 0;
        $negative_count = 0;

        foreach ($this->positive_keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                $positive_count++;
            }
        }

        $negative_keywords = array(
            '残念', 'がっかり', '悪い', '汚い', '古い', '臭い',
            '高い', '狭い', 'うるさい', '不満', '期待外れ',
        );

        foreach ($negative_keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                $negative_count++;
            }
        }

        if ($positive_count > $negative_count) {
            return 'positive';
        } elseif ($negative_count > $positive_count) {
            return 'negative';
        }

        return 'neutral';
    }

    /**
     * キーワード抽出
     * 
     * @param string $text
     * @return array
     */
    private function extract_keywords($text) {
        $found = array();

        foreach ($this->experience_keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                $found[] = $keyword;
            }
        }

        return array_slice(array_unique($found), 0, 5);
    }

    /**
     * 重複排除
     * 
     * @param array $reviews
     * @return array
     */
    private function deduplicate_reviews($reviews) {
        $unique = array();
        $seen_texts = array();

        foreach ($reviews as $review) {
            // 短縮テキストでハッシュ
            $short = mb_substr($review['text'], 0, 50);
            $hash = md5($short);

            if (!isset($seen_texts[$hash])) {
                $seen_texts[$hash] = true;
                $unique[] = $review;
            }
        }

        return $unique;
    }

    /**
     * ペルソナ分類
     * 
     * @param array $reviews
     * @return array
     */
    private function classify_by_persona($reviews) {
        foreach ($reviews as &$review) {
            $review['persona'] = $this->detect_persona($review['text']);
        }

        return $reviews;
    }

    /**
     * ペルソナ検出
     * 
     * @param string $text
     * @return string
     */
    private function detect_persona($text) {
        $scores = array();

        foreach ($this->persona_keywords as $persona => $keywords) {
            $scores[$persona] = 0;
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $scores[$persona]++;
                }
            }
        }

        // 最高スコアのペルソナを返す
        arsort($scores);
        $top_persona = key($scores);

        // スコアが0なら general
        if ($scores[$top_persona] === 0) {
            return 'general';
        }

        return $top_persona;
    }

    /**
     * サマリー生成
     * 
     * @param array $reviews
     * @param string $hotel_name
     * @return array
     */
    private function generate_summary($reviews, $hotel_name) {
        $summary = array(
            'hotel_name'     => $hotel_name,
            'total_count'    => count($reviews),
            'positive_count' => 0,
            'negative_count' => 0,
            'by_persona'     => array(),
            'by_source'      => array(),
            'highlights'     => array(),
            'top_keywords'   => array(),
        );

        $all_keywords = array();

        foreach ($reviews as $review) {
            // 感情カウント
            if ($review['sentiment'] === 'positive') {
                $summary['positive_count']++;
            } elseif ($review['sentiment'] === 'negative') {
                $summary['negative_count']++;
            }

            // ペルソナ別カウント
            $persona = $review['persona'];
            if (!isset($summary['by_persona'][$persona])) {
                $summary['by_persona'][$persona] = 0;
            }
            $summary['by_persona'][$persona]++;

            // ソース別カウント
            $source = $review['source'];
            if (!isset($summary['by_source'][$source])) {
                $summary['by_source'][$source] = 0;
            }
            $summary['by_source'][$source]++;

            // キーワード集計
            foreach ($review['keywords'] as $kw) {
                $all_keywords[] = $kw;
            }
        }

        // 頻出キーワード
        $keyword_counts = array_count_values($all_keywords);
        arsort($keyword_counts);
        $summary['top_keywords'] = array_slice(array_keys($keyword_counts), 0, 10);

        // ハイライト（ポジティブな口コミから抜粋）
        $positive_reviews = array_filter($reviews, function($r) {
            return $r['sentiment'] === 'positive' && mb_strlen($r['text']) > 50;
        });
        
        // ペルソナが分散するように選択
        $highlights_by_persona = array();
        foreach ($positive_reviews as $review) {
            $persona = $review['persona'];
            if (!isset($highlights_by_persona[$persona])) {
                $highlights_by_persona[$persona] = $review['text'];
            }
        }
        $summary['highlights'] = array_slice(array_values($highlights_by_persona), 0, 5);

        return $summary;
    }

    /**
     * ペルソナ別に口コミを取得
     * 
     * @param array $reviews
     * @param string $persona
     * @param int $limit
     * @return array
     */
    public function get_reviews_by_persona($reviews, $persona, $limit = 3) {
        $filtered = array_filter($reviews, function($r) use ($persona) {
            return $r['persona'] === $persona && $r['sentiment'] === 'positive';
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * プロンプト用の口コミテキスト生成
     * 
     * @param array $review_data collect_reviews()の戻り値
     * @param string $persona ターゲットペルソナ
     * @return string
     */
    public function generate_prompt_text($review_data, $persona = 'general') {
        if (empty($review_data['reviews'])) {
            return '';
        }

        $reviews = $review_data['reviews'];
        $summary = $review_data['summary'];

        $prompt = "【参考口コミ情報】\n";
        $prompt .= "このホテルには複数の口コミがあります。\n\n";

        // ペルソナ別の口コミを優先表示
        $persona_reviews = $this->get_reviews_by_persona($reviews, $persona, 3);
        
        if (!empty($persona_reviews)) {
            $persona_labels = array(
                'family'  => 'ファミリー層',
                'couple'  => 'カップル',
                'solo'    => '一人旅',
                'senior'  => 'シニア層',
                'luxury'  => 'ラグジュアリー志向',
                'budget'  => 'コスパ重視層',
                'general' => '一般',
            );
            
            $label = $persona_labels[$persona] ?? '一般';
            $prompt .= "■ {$label}の声\n";
            
            foreach ($persona_reviews as $review) {
                $text = mb_substr($review['text'], 0, 100);
                if (mb_strlen($review['text']) > 100) {
                    $text .= '...';
                }
                $prompt .= "・「{$text}」\n";
            }
            $prompt .= "\n";
        }

        // 特に評価が高いポイント
        if (!empty($summary['top_keywords'])) {
            $prompt .= "■ よく言及されるポイント\n";
            $prompt .= "・" . implode('、', array_slice($summary['top_keywords'], 0, 5)) . "\n\n";
        }

        // ハイライト
        if (!empty($summary['highlights'])) {
            $prompt .= "■ 印象的な口コミ\n";
            foreach (array_slice($summary['highlights'], 0, 2) as $highlight) {
                $text = mb_substr($highlight, 0, 80);
                if (mb_strlen($highlight) > 80) {
                    $text .= '...';
                }
                $prompt .= "・「{$text}」\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "これらの実際の声を参考に、リアリティのある描写を心がけてください。\n\n";

        return $prompt;
    }

    /**
     * 空の結果を返す
     * 
     * @return array
     */
    private function empty_result() {
        return array(
            'reviews' => array(),
            'summary' => array(
                'total_count'    => 0,
                'positive_count' => 0,
                'negative_count' => 0,
                'by_persona'     => array(),
                'by_source'      => array(),
                'highlights'     => array(),
                'top_keywords'   => array(),
            ),
        );
    }

    /**
     * CSE設定確認
     * 
     * @return bool
     */
    private function is_cse_configured() {
        return !empty($this->cse_api_key) && !empty($this->cse_id);
    }
}