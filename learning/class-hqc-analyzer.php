<?php
/**
 * HQC分析エンジン
 * 
 * H軸（Human：人間性）、Q軸（Quality：品質）、C軸（Content/Commercial：構造・商業性）の
 * 3軸でコンテンツを詳細分析し、スコアリングを行う
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.2.0
 * 
 * 変更履歴:
 * - 1.1.0: min_count引き上げ、段落単位カウント、AI表現マイナス評価追加
 * - 1.2.0: C層に商業性評価10項目追加
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Analyzer {

    /**
     * 分析設定
     */
    private $config = array(
        'h_weight' => 0.35,
        'q_weight' => 0.35,
        'c_weight' => 0.30,
        'high_score_threshold' => 75,
        'acceptable_threshold' => 25,
        'ai_penalty_weight' => 0.15,
    );

    /**
     * H軸評価項目（min_count引き上げ済み）
     */
    private $h_criteria = array(
        'timeline' => array(
            'weight' => 20,
            'patterns' => array(
                'チェックイン', '到着', '入室', 'フロント',
                '朝食', '夕食', 'ディナー', '朝', '夜',
                'チェックアウト', '帰り', '出発',
            ),
            'min_count' => 8,
        ),
        'emotion' => array(
            'weight' => 25,
            'patterns' => array(
                '感動', '嬉しい', '幸せ', '最高', '素敵',
                '思わず', 'ついつい', '心が', '気持ち',
                '癒され', 'リラックス', '贅沢', '特別',
            ),
            'min_count' => 12,
        ),
        'purpose' => array(
            'weight' => 15,
            'patterns' => array(
                '記念日', '誕生日', '結婚', 'お祝い',
                'リフレッシュ', '息抜き', '贅沢', '一人旅',
                '家族', 'カップル', '夫婦', '友人',
            ),
            'min_count' => 6,
        ),
        'scene' => array(
            'weight' => 20,
            'patterns' => array(
                '窓から', '部屋から', 'ベッドに', 'ソファで',
                '露天風呂', '大浴場', 'ラウンジ', 'テラス',
                '眺め', '景色', '夜景', '朝日',
            ),
            'min_count' => 8,
        ),
        'first_person' => array(
            'weight' => 20,
            'patterns' => array(
                '私は', '私が', '私の', '私たち',
                '僕は', '僕が', '僕の',
                '体験', '経験', '感じ',
            ),
            'min_count' => 10,
        ),
    );

    /**
     * Q軸評価項目（min_count引き上げ済み）
     */
    private $q_criteria = array(
        'objective_data' => array(
            'weight' => 20,
            'patterns' => array(
                '住所', 'アクセス', '徒歩', '分',
                '価格', '円', '料金', 'プラン',
                '電話', 'TEL', 'チェックイン', 'チェックアウト',
            ),
            'min_count' => 10,
        ),
        'five_senses' => array(
            'weight' => 40,
            'categories' => array(
                'visual' => array('見える', '眺め', '景色', '色', '光', '夜景', '絶景'),
                'auditory' => array('聞こえ', '音', '静か', 'せせらぎ', '波'),
                'olfactory' => array('香り', '匂い', 'アロマ', '薫り', '潮'),
                'gustatory' => array('味', '美味', 'うまい', 'おいしい', '甘い', '旨み'),
                'tactile' => array('肌触り', 'ふわふわ', 'さらさら', '温かい', '心地よい'),
            ),
            'min_categories' => 4,
            'min_per_category' => 2,
        ),
        'cuisine' => array(
            'weight' => 20,
            'patterns' => array(
                '料理', '食事', '朝食', '夕食', 'ディナー',
                '和食', '洋食', '懐石', 'フレンチ', 'イタリアン',
                '地元', '旬', '新鮮', 'シェフ', '板前',
            ),
            'min_count' => 10,
        ),
        'facility' => array(
            'weight' => 20,
            'patterns' => array(
                '部屋', '客室', 'ベッド', 'バス', 'トイレ',
                '温泉', '露天', '大浴場', 'サウナ', 'スパ',
                'Wi-Fi', 'テレビ', '冷蔵庫', 'アメニティ',
            ),
            'min_count' => 12,
        ),
    );

    /**
     * C軸評価項目（構造 + 商業性 10項目）
     */
    private $c_criteria = array(
        // 構造系（従来）
        'h2_headings' => array(
            'weight' => 8,
            'ideal_count' => 6,
            'min_count' => 3,
        ),
        'h3_headings' => array(
            'weight' => 5,
            'ideal_count' => 8,
            'min_count' => 4,
        ),
        'keyphrase_density' => array(
            'weight' => 8,
            'ideal_min' => 0.5,
            'ideal_max' => 2.5,
        ),
        'keyphrase_intro' => array(
            'weight' => 5,
            'intro_length' => 200,
        ),
        'word_count' => array(
            'weight' => 7,
            'ideal_min' => 2000,
            'ideal_max' => 3500,
        ),
        // 商業性系（新規追加 10項目）
        'cta' => array(
            'weight' => 10,
            'patterns' => array(
                '予約', '申し込み', '詳細はこちら', '公式サイト',
                'チェック', '確認', '今すぐ', 'お得',
                '限定', 'キャンペーン', '特典', 'クーポン',
            ),
            'min_count' => 3,
        ),
        'affiliate_links' => array(
            'weight' => 10,
            'patterns' => array(
                'rakuten.co.jp', 'jalan.net', 'ikyu.com', 'booking.com',
                'jtb.co.jp', 'relux.jp', 'yukoyuko.net', 'rurubu.travel',
                'href=', 'target="_blank"', 'rel="nofollow"',
            ),
            'min_count' => 2,
        ),
        'price_info' => array(
            'weight' => 10,
            'patterns' => array(
                '円', '税込', '税別', '1泊', '2食付',
                'プラン', '料金', '価格', '相場', 'コスパ',
                '割引', 'ポイント', '還元', 'お得',
            ),
            'min_count' => 5,
        ),
        'comparison' => array(
            'weight' => 8,
            'patterns' => array(
                'おすすめ', 'ランキング', '比較', 'VS',
                '一方', 'それに対して', '違い', '特徴',
                'ベスト', 'トップ', '人気', '評価',
            ),
            'min_count' => 4,
        ),
        'faq' => array(
            'weight' => 8,
            'patterns' => array(
                '？', 'Q.', 'Q：', 'よくある質問', 'FAQ',
                'A.', 'A：', '答え', '回答',
                'いつ', 'どこ', 'どのように', 'いくら',
            ),
            'min_count' => 4,
        ),
        'pros_cons' => array(
            'weight' => 7,
            'patterns' => array(
                'メリット', 'デメリット', '良い点', '悪い点',
                '長所', '短所', '利点', '欠点',
                '注意点', '気になる', '残念', '惜しい',
            ),
            'min_count' => 3,
        ),
        'target_audience' => array(
            'weight' => 6,
            'patterns' => array(
                'おすすめの人', 'こんな人', '向いている', 'ぴったり',
                'カップルにおすすめ', '家族連れ', '一人旅', 'ビジネス',
                'シニア', '女子旅', '記念日', 'ワーケーション',
            ),
            'min_count' => 2,
        ),
        'seasonal_info' => array(
            'weight' => 5,
            'patterns' => array(
                '春', '夏', '秋', '冬', '季節',
                'ベストシーズン', '混雑', '空いている', '穴場',
                '紅葉', '桜', '花火', 'イルミネーション',
            ),
            'min_count' => 3,
        ),
        'access_info' => array(
            'weight' => 6,
            'patterns' => array(
                'アクセス', '行き方', '最寄り', '駅から',
                '車', '電車', 'バス', 'タクシー',
                '周辺', '観光', 'スポット', '徒歩',
            ),
            'min_count' => 4,
        ),
        'reviews' => array(
            'weight' => 7,
            'patterns' => array(
                '口コミ', 'レビュー', '評価', '星',
                '点', '満足度', 'クチコミ', '評判',
                '実際', 'リアル', '本音', '感想',
            ),
            'min_count' => 3,
        ),
    );

    /**
     * AI特有の定型表現（マイナス評価対象）
     */
    private $ai_patterns = array(
        '素晴らしい', '最高の', '極上の', '至福の', '究極の',
        '圧巻の', '贅沢な', '格別な', '絶品の', '極めて',
        '言うまでもなく', '言わずもがな', 'もちろん',
        '期待を裏切らない', '期待以上', '想像以上',
        '五感を刺激', '五感で楽しむ', '五感を満たす',
        'まさに', 'さすが', 'やはり', 'とにかく',
        '本当に', '非常に', 'とても', '大変',
        'おすすめです', 'いかがでしょうか', 'ぜひ', '間違いなし',
    );

    /**
     * コンテンツを分析
     */
    public function analyze($content, $hotel_data = array()) {
        if (empty($content)) {
            return $this->get_empty_result();
        }

        $keyphrase = $hotel_data['hotel_name'] ?? '';
        $paragraphs = $this->split_into_paragraphs($content);

        // 各軸の分析
        $h_result = $this->analyze_h_axis($content, $paragraphs);
        $q_result = $this->analyze_q_axis($content, $paragraphs);
        $c_result = $this->analyze_c_axis($content, $keyphrase);

        // AI表現ペナルティ
        $ai_penalty = $this->calculate_ai_penalty($content);

        // 総合スコア計算
        $raw_score = 
            ($h_result['score'] * $this->config['h_weight']) +
            ($q_result['score'] * $this->config['q_weight']) +
            ($c_result['score'] * $this->config['c_weight']);
        
        $total_score = max(0, $raw_score - $ai_penalty);

        // 弱点抽出
        $weak_points = $this->extract_weak_points($h_result, $q_result, $c_result, $ai_penalty);

        // 推奨事項生成
        $recommendations = $this->generate_recommendations($weak_points);

        return array(
            'h_score' => round($h_result['score'], 1),
            'q_score' => round($q_result['score'], 1),
            'c_score' => round($c_result['score'], 1),
            'total_score' => round($total_score, 1),
            'ai_penalty' => round($ai_penalty, 1),
            'h_details' => $h_result['details'],
            'q_details' => $q_result['details'],
            'c_details' => $c_result['details'],
            'weak_points' => $weak_points,
            'recommendations' => $recommendations,
            'is_high_quality' => $total_score >= $this->config['high_score_threshold'],
            'is_acceptable' => $total_score >= $this->config['acceptable_threshold'],
        );
    }

    /**
     * コンテンツを段落に分割
     */
    private function split_into_paragraphs($content) {
        $content = preg_replace('/<\/(p|div|section|article)>/i', "\n\n", $content);
        $content = preg_replace('/<(br|hr)[^>]*>/i', "\n", $content);
        $content = wp_strip_all_tags($content);
        $paragraphs = preg_split('/\n\s*\n/', $content);
        return array_filter($paragraphs, function($p) {
            return mb_strlen(trim($p)) > 10;
        });
    }

    /**
     * 段落単位でパターンをカウント
     */
    private function count_patterns_by_paragraph($paragraphs, $patterns) {
        $count = 0;
        foreach ($paragraphs as $paragraph) {
            $paragraph_matched = false;
            foreach ($patterns as $pattern) {
                if (mb_strpos($paragraph, $pattern) !== false) {
                    $paragraph_matched = true;
                    break;
                }
            }
            if ($paragraph_matched) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * ユニークなパターンをカウント
     */
    private function count_unique_patterns($content, $patterns) {
        $found_patterns = array();
        foreach ($patterns as $pattern) {
            if (mb_strpos($content, $pattern) !== false) {
                $found_patterns[] = $pattern;
            }
        }
        return count($found_patterns);
    }

    /**
     * 通常のパターンカウント
     */
    private function count_patterns($content, $patterns) {
        $count = 0;
        foreach ($patterns as $pattern) {
            $count += mb_substr_count($content, $pattern);
        }
        return $count;
    }

    /**
     * H軸（Human：人間性）分析
     */
    private function analyze_h_axis($content, $paragraphs) {
        $score = 0;
        $details = array();

        foreach ($this->h_criteria as $key => $criteria) {
            $paragraph_count = $this->count_patterns_by_paragraph($paragraphs, $criteria['patterns']);
            $unique_count = $this->count_unique_patterns($content, $criteria['patterns']);
            
            $paragraph_ratio = min(1, $paragraph_count / $criteria['min_count']);
            $unique_ratio = min(1, $unique_count / (count($criteria['patterns']) * 0.5));
            $combined_ratio = ($paragraph_ratio * 0.6) + ($unique_ratio * 0.4);
            
            $item_score = $criteria['weight'] * $combined_ratio;
            $score += $item_score;

            $details[$key] = array(
                'paragraph_count' => $paragraph_count,
                'unique_count' => $unique_count,
                'target' => $criteria['min_count'],
                'score' => round($item_score, 1),
                'max' => $criteria['weight'],
            );
        }

        return array(
            'score' => min(100, $score),
            'details' => $details,
        );
    }

    /**
     * Q軸（Quality：品質）分析
     */
    private function analyze_q_axis($content, $paragraphs) {
        $score = 0;
        $details = array();

        // 客観データ
        $paragraph_count = $this->count_patterns_by_paragraph($paragraphs, $this->q_criteria['objective_data']['patterns']);
        $unique_count = $this->count_unique_patterns($content, $this->q_criteria['objective_data']['patterns']);
        $paragraph_ratio = min(1, $paragraph_count / $this->q_criteria['objective_data']['min_count']);
        $unique_ratio = min(1, $unique_count / (count($this->q_criteria['objective_data']['patterns']) * 0.5));
        $combined_ratio = ($paragraph_ratio * 0.6) + ($unique_ratio * 0.4);
        $item_score = $this->q_criteria['objective_data']['weight'] * $combined_ratio;
        $score += $item_score;
        $details['objective_data'] = array(
            'paragraph_count' => $paragraph_count,
            'unique_count' => $unique_count,
            'target' => $this->q_criteria['objective_data']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->q_criteria['objective_data']['weight'],
        );

        // 五感描写
        $senses_found = 0;
        $senses_details = array();
        $min_per_category = $this->q_criteria['five_senses']['min_per_category'] ?? 2;
        
        foreach ($this->q_criteria['five_senses']['categories'] as $sense => $patterns) {
            $count = 0;
            foreach ($patterns as $pattern) {
                $count += mb_substr_count($content, $pattern);
            }
            $qualified = $count >= $min_per_category;
            $senses_details[$sense] = array(
                'count' => $count,
                'qualified' => $qualified,
            );
            if ($qualified) {
                $senses_found++;
            }
        }
        
        $ratio = min(1, $senses_found / $this->q_criteria['five_senses']['min_categories']);
        $item_score = $this->q_criteria['five_senses']['weight'] * $ratio;
        $score += $item_score;
        $details['five_senses'] = array(
            'found' => $senses_found,
            'target' => $this->q_criteria['five_senses']['min_categories'],
            'breakdown' => $senses_details,
            'score' => round($item_score, 1),
            'max' => $this->q_criteria['five_senses']['weight'],
        );

        // 料理描写
        $paragraph_count = $this->count_patterns_by_paragraph($paragraphs, $this->q_criteria['cuisine']['patterns']);
        $unique_count = $this->count_unique_patterns($content, $this->q_criteria['cuisine']['patterns']);
        $paragraph_ratio = min(1, $paragraph_count / $this->q_criteria['cuisine']['min_count']);
        $unique_ratio = min(1, $unique_count / (count($this->q_criteria['cuisine']['patterns']) * 0.5));
        $combined_ratio = ($paragraph_ratio * 0.6) + ($unique_ratio * 0.4);
        $item_score = $this->q_criteria['cuisine']['weight'] * $combined_ratio;
        $score += $item_score;
        $details['cuisine'] = array(
            'paragraph_count' => $paragraph_count,
            'unique_count' => $unique_count,
            'target' => $this->q_criteria['cuisine']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->q_criteria['cuisine']['weight'],
        );

        // 施設情報
        $paragraph_count = $this->count_patterns_by_paragraph($paragraphs, $this->q_criteria['facility']['patterns']);
        $unique_count = $this->count_unique_patterns($content, $this->q_criteria['facility']['patterns']);
        $paragraph_ratio = min(1, $paragraph_count / $this->q_criteria['facility']['min_count']);
        $unique_ratio = min(1, $unique_count / (count($this->q_criteria['facility']['patterns']) * 0.5));
        $combined_ratio = ($paragraph_ratio * 0.6) + ($unique_ratio * 0.4);
        $item_score = $this->q_criteria['facility']['weight'] * $combined_ratio;
        $score += $item_score;
        $details['facility'] = array(
            'paragraph_count' => $paragraph_count,
            'unique_count' => $unique_count,
            'target' => $this->q_criteria['facility']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->q_criteria['facility']['weight'],
        );

        return array(
            'score' => min(100, $score),
            'details' => $details,
        );
    }

    /**
     * C軸（Content/Commercial：構造・商業性）分析
     */
    private function analyze_c_axis($content, $keyphrase = '') {
        $score = 0;
        $details = array();
        $plain_text = wp_strip_all_tags($content);
        $word_count = mb_strlen($plain_text);

        // === 構造系 ===
        
        // H2見出し数
        preg_match_all('/<h2[^>]*>/i', $content, $h2_matches);
        $h2_count = count($h2_matches[0]);
        $ratio = min(1, $h2_count / $this->c_criteria['h2_headings']['ideal_count']);
        $item_score = $this->c_criteria['h2_headings']['weight'] * $ratio;
        $score += $item_score;
        $details['h2_headings'] = array(
            'count' => $h2_count,
            'target' => $this->c_criteria['h2_headings']['ideal_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['h2_headings']['weight'],
        );

        // H3見出し数
        preg_match_all('/<h3[^>]*>/i', $content, $h3_matches);
        $h3_count = count($h3_matches[0]);
        $ratio = min(1, $h3_count / $this->c_criteria['h3_headings']['ideal_count']);
        $item_score = $this->c_criteria['h3_headings']['weight'] * $ratio;
        $score += $item_score;
        $details['h3_headings'] = array(
            'count' => $h3_count,
            'target' => $this->c_criteria['h3_headings']['ideal_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['h3_headings']['weight'],
        );

        // キーフレーズ密度
        $keyphrase_count = !empty($keyphrase) ? mb_substr_count($plain_text, $keyphrase) : 0;
        $density = $word_count > 0 ? ($keyphrase_count * mb_strlen($keyphrase) / $word_count) * 100 : 0;
        $ideal_min = $this->c_criteria['keyphrase_density']['ideal_min'];
        $ideal_max = $this->c_criteria['keyphrase_density']['ideal_max'];
        if ($density >= $ideal_min && $density <= $ideal_max) {
            $ratio = 1;
        } elseif ($density < $ideal_min) {
            $ratio = $ideal_min > 0 ? $density / $ideal_min : 0;
        } else {
            $ratio = max(0, 1 - ($density - $ideal_max) / $ideal_max);
        }
        $item_score = $this->c_criteria['keyphrase_density']['weight'] * $ratio;
        $score += $item_score;
        $details['keyphrase_density'] = array(
            'density' => round($density, 2),
            'ideal_range' => "{$ideal_min}% - {$ideal_max}%",
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['keyphrase_density']['weight'],
        );

        // 冒頭キーフレーズ
        $intro = mb_substr($plain_text, 0, $this->c_criteria['keyphrase_intro']['intro_length']);
        $has_keyphrase_intro = !empty($keyphrase) && mb_strpos($intro, $keyphrase) !== false;
        $item_score = $has_keyphrase_intro ? $this->c_criteria['keyphrase_intro']['weight'] : 0;
        $score += $item_score;
        $details['keyphrase_intro'] = array(
            'found' => $has_keyphrase_intro,
            'score' => $item_score,
            'max' => $this->c_criteria['keyphrase_intro']['weight'],
        );

        // 文字数
        $ideal_min = $this->c_criteria['word_count']['ideal_min'];
        $ideal_max = $this->c_criteria['word_count']['ideal_max'];
        if ($word_count >= $ideal_min && $word_count <= $ideal_max) {
            $ratio = 1;
        } elseif ($word_count < $ideal_min) {
            $ratio = $word_count / $ideal_min;
        } else {
            $ratio = max(0.5, 1 - ($word_count - $ideal_max) / $ideal_max);
        }
        $item_score = $this->c_criteria['word_count']['weight'] * $ratio;
        $score += $item_score;
        $details['word_count'] = array(
            'count' => $word_count,
            'ideal_range' => "{$ideal_min} - {$ideal_max}",
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['word_count']['weight'],
        );

        // === 商業性系（新規10項目） ===

        // CTA（行動喚起）
        $cta_count = $this->count_patterns($content, $this->c_criteria['cta']['patterns']);
        $ratio = min(1, $cta_count / $this->c_criteria['cta']['min_count']);
        $item_score = $this->c_criteria['cta']['weight'] * $ratio;
        $score += $item_score;
        $details['cta'] = array(
            'count' => $cta_count,
            'target' => $this->c_criteria['cta']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['cta']['weight'],
        );

        // アフィリエイトリンク
        $link_count = $this->count_patterns($content, $this->c_criteria['affiliate_links']['patterns']);
        $ratio = min(1, $link_count / $this->c_criteria['affiliate_links']['min_count']);
        $item_score = $this->c_criteria['affiliate_links']['weight'] * $ratio;
        $score += $item_score;
        $details['affiliate_links'] = array(
            'count' => $link_count,
            'target' => $this->c_criteria['affiliate_links']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['affiliate_links']['weight'],
        );

        // 価格・プラン情報
        $price_count = $this->count_patterns($content, $this->c_criteria['price_info']['patterns']);
        $ratio = min(1, $price_count / $this->c_criteria['price_info']['min_count']);
        $item_score = $this->c_criteria['price_info']['weight'] * $ratio;
        $score += $item_score;
        $details['price_info'] = array(
            'count' => $price_count,
            'target' => $this->c_criteria['price_info']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['price_info']['weight'],
        );

        // 比較・おすすめ表現
        $comparison_count = $this->count_patterns($content, $this->c_criteria['comparison']['patterns']);
        $ratio = min(1, $comparison_count / $this->c_criteria['comparison']['min_count']);
        $item_score = $this->c_criteria['comparison']['weight'] * $ratio;
        $score += $item_score;
        $details['comparison'] = array(
            'count' => $comparison_count,
            'target' => $this->c_criteria['comparison']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['comparison']['weight'],
        );

        // FAQ
        $faq_count = $this->count_patterns($content, $this->c_criteria['faq']['patterns']);
        $ratio = min(1, $faq_count / $this->c_criteria['faq']['min_count']);
        $item_score = $this->c_criteria['faq']['weight'] * $ratio;
        $score += $item_score;
        $details['faq'] = array(
            'count' => $faq_count,
            'target' => $this->c_criteria['faq']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['faq']['weight'],
        );

        // メリット・デメリット
        $pros_cons_count = $this->count_patterns($content, $this->c_criteria['pros_cons']['patterns']);
        $ratio = min(1, $pros_cons_count / $this->c_criteria['pros_cons']['min_count']);
        $item_score = $this->c_criteria['pros_cons']['weight'] * $ratio;
        $score += $item_score;
        $details['pros_cons'] = array(
            'count' => $pros_cons_count,
            'target' => $this->c_criteria['pros_cons']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['pros_cons']['weight'],
        );

        // ターゲット明示
        $target_count = $this->count_patterns($content, $this->c_criteria['target_audience']['patterns']);
        $ratio = min(1, $target_count / $this->c_criteria['target_audience']['min_count']);
        $item_score = $this->c_criteria['target_audience']['weight'] * $ratio;
        $score += $item_score;
        $details['target_audience'] = array(
            'count' => $target_count,
            'target' => $this->c_criteria['target_audience']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['target_audience']['weight'],
        );

        // 季節・時期情報
        $seasonal_count = $this->count_patterns($content, $this->c_criteria['seasonal_info']['patterns']);
        $ratio = min(1, $seasonal_count / $this->c_criteria['seasonal_info']['min_count']);
        $item_score = $this->c_criteria['seasonal_info']['weight'] * $ratio;
        $score += $item_score;
        $details['seasonal_info'] = array(
            'count' => $seasonal_count,
            'target' => $this->c_criteria['seasonal_info']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['seasonal_info']['weight'],
        );

        // 周辺情報・アクセス
        $access_count = $this->count_patterns($content, $this->c_criteria['access_info']['patterns']);
        $ratio = min(1, $access_count / $this->c_criteria['access_info']['min_count']);
        $item_score = $this->c_criteria['access_info']['weight'] * $ratio;
        $score += $item_score;
        $details['access_info'] = array(
            'count' => $access_count,
            'target' => $this->c_criteria['access_info']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['access_info']['weight'],
        );

        // 口コミ・評価引用
        $reviews_count = $this->count_patterns($content, $this->c_criteria['reviews']['patterns']);
        $ratio = min(1, $reviews_count / $this->c_criteria['reviews']['min_count']);
        $item_score = $this->c_criteria['reviews']['weight'] * $ratio;
        $score += $item_score;
        $details['reviews'] = array(
            'count' => $reviews_count,
            'target' => $this->c_criteria['reviews']['min_count'],
            'score' => round($item_score, 1),
            'max' => $this->c_criteria['reviews']['weight'],
        );

        return array(
            'score' => min(100, $score),
            'details' => $details,
        );
    }

    /**
     * AI表現ペナルティを計算
     */
    private function calculate_ai_penalty($content) {
        $ai_count = 0;
        foreach ($this->ai_patterns as $pattern) {
            $ai_count += mb_substr_count($content, $pattern);
        }
        return min(15, $ai_count * $this->config['ai_penalty_weight']);
    }

    /**
     * 弱点を抽出
     */
    private function extract_weak_points($h_result, $q_result, $c_result, $ai_penalty = 0) {
        $weak_points = array();
        $threshold = 0.5;

        // H軸の弱点
        foreach ($h_result['details'] as $key => $detail) {
            if (isset($detail['score']) && isset($detail['max']) && $detail['max'] > 0) {
                if ($detail['score'] / $detail['max'] < $threshold) {
                    $weak_points[] = array(
                        'axis' => 'H',
                        'category' => $key,
                        'score_ratio' => round($detail['score'] / $detail['max'], 2),
                    );
                }
            }
        }

        // Q軸の弱点
        foreach ($q_result['details'] as $key => $detail) {
            if (isset($detail['score']) && isset($detail['max']) && $detail['max'] > 0) {
                if ($detail['score'] / $detail['max'] < $threshold) {
                    $weak_points[] = array(
                        'axis' => 'Q',
                        'category' => $key,
                        'score_ratio' => round($detail['score'] / $detail['max'], 2),
                    );
                }
            }
        }

        // C軸の弱点
        foreach ($c_result['details'] as $key => $detail) {
            if (isset($detail['score']) && isset($detail['max']) && $detail['max'] > 0) {
                if ($detail['score'] / $detail['max'] < $threshold) {
                    $weak_points[] = array(
                        'axis' => 'C',
                        'category' => $key,
                        'score_ratio' => round($detail['score'] / $detail['max'], 2),
                    );
                }
            }
        }

        // AI表現ペナルティ
        if ($ai_penalty >= 5) {
            $weak_points[] = array(
                'axis' => 'AI',
                'category' => 'ai_expressions',
                'score_ratio' => round(1 - ($ai_penalty / 15), 2),
            );
        }

        return $weak_points;
    }

    /**
     * 推奨事項を生成
     */
    private function generate_recommendations($weak_points) {
        $recommendations = array();
        $messages = array(
            'H' => array(
                'timeline' => '到着からチェックアウトまでの時系列を追加',
                'emotion' => '感情表現を増やす（感動、嬉しいなど）',
                'purpose' => '旅の目的（記念日、リフレッシュなど）を明示',
                'scene' => '具体的なシーン描写を追加',
                'first_person' => '一人称視点の体験談を増やす',
            ),
            'Q' => array(
                'objective_data' => '住所・アクセス・料金などの客観データを追加',
                'five_senses' => '五感（視覚・聴覚・嗅覚・味覚・触覚）の描写を追加',
                'cuisine' => '料理・食事の具体的な描写を追加',
                'facility' => '施設・設備の情報を追加',
            ),
            'C' => array(
                'h2_headings' => 'H2見出しを6個以上に増やす',
                'h3_headings' => 'H3見出しで階層構造を作る',
                'keyphrase_density' => 'キーフレーズの出現頻度を調整',
                'keyphrase_intro' => '冒頭200文字以内にキーフレーズを入れる',
                'word_count' => '文字数を2000〜3500文字に調整',
                'cta' => '予約・詳細へのCTA（行動喚起）を追加',
                'affiliate_links' => '予約サイトへのリンクを追加',
                'price_info' => '価格・プラン情報を充実させる',
                'comparison' => '他との比較やおすすめポイントを追加',
                'faq' => 'よくある質問（FAQ）セクションを追加',
                'pros_cons' => 'メリット・デメリットを明記',
                'target_audience' => '「こんな人におすすめ」を追加',
                'seasonal_info' => 'ベストシーズン・混雑情報を追加',
                'access_info' => '周辺観光・アクセス情報を追加',
                'reviews' => '口コミ・評価の引用を追加',
            ),
            'AI' => array(
                'ai_expressions' => 'AI特有の定型表現を減らし、具体的な描写に置き換える',
            ),
        );

        foreach ($weak_points as $wp) {
            $axis = $wp['axis'];
            $category = $wp['category'];
            if (isset($messages[$axis][$category])) {
                $recommendations[] = array(
                    'axis' => $axis,
                    'category' => $category,
                    'message' => $messages[$axis][$category],
                    'priority' => $wp['score_ratio'] < 0.3 ? 'high' : 'medium',
                );
            }
        }

        return $recommendations;
    }

    /**
     * 空の結果を返す
     */
    private function get_empty_result() {
        return array(
            'h_score' => 0,
            'q_score' => 0,
            'c_score' => 0,
            'total_score' => 0,
            'ai_penalty' => 0,
            'h_details' => array(),
            'q_details' => array(),
            'c_details' => array(),
            'weak_points' => array(),
            'recommendations' => array(),
            'is_high_quality' => false,
            'is_acceptable' => false,
        );
    }

    /**
     * スコアのみ簡易取得
     */
    public function get_quick_score($content, $keyphrase = '') {
        $result = $this->analyze($content, array('hotel_name' => $keyphrase));
        return $result['total_score'];
    }

    /**
     * 軸別スコアのみ取得
     */
    public function get_axis_scores($content, $hotel_data = array()) {
        $result = $this->analyze($content, $hotel_data);
        return array(
            'h' => $result['h_score'],
            'q' => $result['q_score'],
            'c' => $result['c_score'],
            'total' => $result['total_score'],
            'ai_penalty' => $result['ai_penalty'],
        );
    }
}