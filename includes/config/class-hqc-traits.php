<?php
/**
 * HQC トレイト
 * 
 * HQC Framework 共通処理をトレイトとして提供
 * 各クラスで use HRS_HQC_Trait; で利用可能
 * 
 * @package HRS
 * @version 4.4.0-UNIFIED
 * 
 * 変更履歴:
 * - 4.4.0: HRS_HQC_Analyzerに統一、calculate_hqc_scoreを修正
 */

if (!defined('ABSPATH')) {
    exit;
}

trait HRS_HQC_Trait {

    /**
     * HQC設定
     */
    protected $hqc_settings = null;

    /**
     * HQC設定を読み込み
     * 
     * @return array
     */
    protected function load_hqc_settings() {
        if ($this->hqc_settings === null) {
            $this->hqc_settings = get_option('hrs_hqc_settings', $this->get_default_hqc_settings());
        }
        return $this->hqc_settings;
    }

    /**
     * デフォルトHQC設定
     * 
     * @return array
     */
    protected function get_default_hqc_settings() {
        return array(
            'h' => array(
                'persona' => 'general',
                'purpose' => array('sightseeing'),
                'depth' => 2,
            ),
            'q' => array(
                'tone' => 'casual',
                'structure' => 'timeline',
                'sensory' => 2,
                'story' => 2,
                'info' => 2,
            ),
            'c' => array(
                'commercial' => 'seo',
                'experience' => 'record',
            ),
        );
    }

    /**
     * HQC設定を保存
     * 
     * @param array $settings
     * @return bool
     */
    protected function save_hqc_settings($settings) {
        $sanitized = $this->sanitize_hqc_settings($settings);
        $result = update_option('hrs_hqc_settings', $sanitized);
        if ($result) {
            $this->hqc_settings = $sanitized;
        }
        return $result;
    }

    /**
     * HQC設定をサニタイズ
     * 
     * @param array $settings
     * @return array
     */
    protected function sanitize_hqc_settings($settings) {
        $defaults = $this->get_default_hqc_settings();
        $sanitized = array();

        // H-Layer
        $sanitized['h'] = array(
            'persona' => $this->sanitize_persona($settings['h']['persona'] ?? $defaults['h']['persona']),
            'purpose' => $this->sanitize_purposes($settings['h']['purpose'] ?? $defaults['h']['purpose']),
            'depth' => $this->sanitize_level($settings['h']['depth'] ?? $defaults['h']['depth']),
        );

        // Q-Layer
        $sanitized['q'] = array(
            'tone' => $this->sanitize_tone($settings['q']['tone'] ?? $defaults['q']['tone']),
            'structure' => $this->sanitize_structure($settings['q']['structure'] ?? $defaults['q']['structure']),
            'sensory' => $this->sanitize_level($settings['q']['sensory'] ?? $defaults['q']['sensory']),
            'story' => $this->sanitize_level($settings['q']['story'] ?? $defaults['q']['story']),
            'info' => $this->sanitize_level($settings['q']['info'] ?? $defaults['q']['info']),
        );

        // C-Layer
        $sanitized['c'] = array(
            'commercial' => $this->sanitize_commercial($settings['c']['commercial'] ?? $defaults['c']['commercial']),
            'experience' => $this->sanitize_experience($settings['c']['experience'] ?? $defaults['c']['experience']),
        );

        return $sanitized;
    }

    /**
     * ペルソナをサニタイズ
     */
    protected function sanitize_persona($persona) {
        $valid = array('general', 'solo', 'couple', 'family', 'senior', 'workation', 'luxury', 'budget');
        return in_array($persona, $valid) ? $persona : 'general';
    }

    /**
     * 目的をサニタイズ
     */
    protected function sanitize_purposes($purposes) {
        if (!is_array($purposes)) {
            $purposes = array($purposes);
        }
        $valid = array('sightseeing', 'onsen', 'gourmet', 'anniversary', 'workation', 'healing', 'family', 'budget');
        return array_values(array_intersect($purposes, $valid)) ?: array('sightseeing');
    }

    /**
     * トーンをサニタイズ
     */
    protected function sanitize_tone($tone) {
        $valid = array('casual', 'luxury', 'emotional', 'cinematic', 'journalistic');
        return in_array($tone, $valid) ? $tone : 'casual';
    }

    /**
     * 構造をサニタイズ
     */
    protected function sanitize_structure($structure) {
        $valid = array('timeline', 'hero_journey', 'five_sense', 'dialogue', 'review');
        return in_array($structure, $valid) ? $structure : 'timeline';
    }

    /**
     * レベル（1-3）をサニタイズ
     */
    protected function sanitize_level($level) {
        $level = intval($level);
        return max(1, min(3, $level));
    }

    /**
     * 商業方針をサニタイズ
     */
    protected function sanitize_commercial($commercial) {
        $valid = array('none', 'seo', 'conversion');
        return in_array($commercial, $valid) ? $commercial : 'seo';
    }

    /**
     * 体験タイプをサニタイズ
     */
    protected function sanitize_experience($experience) {
        $valid = array('record', 'immersive', 'drama');
        return in_array($experience, $valid) ? $experience : 'record';
    }

    /**
     * ペルソナ情報を取得
     */
    protected function get_persona_info($persona) {
        $personas = array(
            'general' => array('name' => '一般旅行者', 'description' => '幅広い読者層'),
            'solo' => array('name' => '一人旅', 'description' => '自由な旅を求める人'),
            'couple' => array('name' => 'カップル・夫婦', 'description' => '二人の特別な時間'),
            'family' => array('name' => 'ファミリー', 'description' => '子連れ家族'),
            'senior' => array('name' => 'シニア', 'description' => 'ゆったり快適な滞在'),
            'workation' => array('name' => 'ワーケーション', 'description' => '仕事と休暇の両立'),
            'luxury' => array('name' => 'ラグジュアリー', 'description' => '最高のおもてなし'),
            'budget' => array('name' => '節約志向', 'description' => 'コスパ重視'),
        );
        return $personas[$persona] ?? $personas['general'];
    }

    /**
     * トーン情報を取得
     */
    protected function get_tone_info($tone) {
        $tones = array(
            'casual' => array('name' => 'カジュアル', 'instruction' => '親しみやすい温かみのある文体'),
            'luxury' => array('name' => 'ラグジュアリー', 'instruction' => '上品で洗練された高級感ある文体'),
            'emotional' => array('name' => 'エモーショナル', 'instruction' => '心に響く情緒的な文体'),
            'cinematic' => array('name' => '映画的', 'instruction' => '映像が浮かぶドラマチックな描写'),
            'journalistic' => array('name' => '報道的', 'instruction' => '客観的で信頼性の高い文体'),
        );
        return $tones[$tone] ?? $tones['casual'];
    }

    /**
     * 構造情報を取得
     */
    protected function get_structure_info($structure) {
        $structures = array(
            'timeline' => array('name' => '時系列', 'instruction' => 'チェックインから時間順に紹介'),
            'hero_journey' => array('name' => '物語構造', 'instruction' => '出発→体験→感動→帰還の物語形式'),
            'five_sense' => array('name' => '五感描写', 'instruction' => '視覚・聴覚・嗅覚・味覚・触覚で構成'),
            'dialogue' => array('name' => '対話形式', 'instruction' => '会話を交えた親しみやすい構成'),
            'review' => array('name' => 'レビュー', 'instruction' => '評価ポイントごとに整理'),
        );
        return $structures[$structure] ?? $structures['timeline'];
    }

    /**
     * 旅の目的名を取得
     */
    protected function get_purpose_name($purpose) {
        $purposes = array(
            'sightseeing' => '観光', 'onsen' => '温泉', 'gourmet' => 'グルメ',
            'anniversary' => '記念日', 'workation' => 'ワーケーション',
            'healing' => '癒し', 'family' => '家族旅行', 'budget' => '節約旅',
        );
        return $purposes[$purpose] ?? $purpose;
    }

    /**
     * HQCスコアを計算（HRS_HQC_Analyzerに統一）
     * 
     * @param array $metrics 評価指標（後方互換用、実際はcontentとhotel_dataを使用）
     * @param string $content コンテンツ（新方式）
     * @param array $hotel_data ホテル情報（新方式）
     * @return array H/Q/C個別スコアを含む配列
     */
    protected function calculate_hqc_score($metrics, $content = '', $hotel_data = array()) {
        // HRS_HQC_Analyzerクラスを使用
        if (class_exists('HRS_HQC_Analyzer')) {
            $analyzer = new HRS_HQC_Analyzer();
            
            // コンテンツが渡されている場合は新方式
            if (!empty($content)) {
                $result = $analyzer->analyze($content, $hotel_data);
                return array(
                    'total' => $result['total_score'],
                    'h_score' => $result['h_score'],
                    'q_score' => $result['q_score'],
                    'c_score' => $result['c_score'],
                    'ai_penalty' => $result['ai_penalty'] ?? 0,
                    'weak_points' => $result['weak_points'] ?? array(),
                    'recommendations' => $result['recommendations'] ?? array(),
                    'is_high_quality' => $result['is_high_quality'],
                    'is_acceptable' => $result['is_acceptable'],
                );
            }
            
            // metricsにcontentが含まれている場合
            if (isset($metrics['content'])) {
                $result = $analyzer->analyze($metrics['content'], array(
                    'hotel_name' => $metrics['hotel_name'] ?? '',
                ));
                return array(
                    'total' => $result['total_score'],
                    'h_score' => $result['h_score'],
                    'q_score' => $result['q_score'],
                    'c_score' => $result['c_score'],
                    'ai_penalty' => $result['ai_penalty'] ?? 0,
                    'weak_points' => $result['weak_points'] ?? array(),
                    'recommendations' => $result['recommendations'] ?? array(),
                    'is_high_quality' => $result['is_high_quality'],
                    'is_acceptable' => $result['is_acceptable'],
                );
            }
        }
        
        // フォールバック: 旧方式（後方互換性）
        $weights = array(
            'eeat_score' => 0.25,
            'sensory_score' => 0.20,
            'emotion_score' => 0.20,
            'structure_score' => 0.20,
            'seo_score' => 0.15,
        );

        $score = 0;
        $total_weight = 0;

        foreach ($weights as $key => $weight) {
            if (isset($metrics[$key])) {
                $score += $metrics[$key] * $weight;
                $total_weight += $weight;
            }
        }

        $total = $total_weight > 0 ? round($score / $total_weight, 2) : 0;
        
        // 旧方式では個別スコアは推定値
        return array(
            'total' => $total,
            'h_score' => $total, // 推定
            'q_score' => $total, // 推定
            'c_score' => $total, // 推定
            'ai_penalty' => 0,
            'weak_points' => array(),
            'recommendations' => array(),
            'is_high_quality' => $total >= 75,
            'is_acceptable' => $total >= 25,
        );
    }

    /**
     * HQCスコアのラベルを取得
     * 
     * @param float $score
     * @return array
     */
    protected function get_hqc_score_label($score) {
        if ($score >= 0.85) {
            return array('label' => 'Excellent', 'color' => '#22c55e', 'icon' => '🌟');
        } elseif ($score >= 0.70) {
            return array('label' => 'Good', 'color' => '#84cc16', 'icon' => '✅');
        } elseif ($score >= 0.50) {
            return array('label' => 'Fair', 'color' => '#eab308', 'icon' => '⚠️');
        } else {
            return array('label' => 'Poor', 'color' => '#ef4444', 'icon' => '❌');
        }
    }

    /**
     * プリセットを取得
     * 
     * @param string $preset_id
     * @return array|null
     */
    protected function get_preset($preset_id) {
        $presets = $this->get_all_presets();
        return $presets[$preset_id] ?? null;
    }

    /**
     * 全プリセットを取得
     * 
     * @return array
     */
    protected function get_all_presets() {
        return array(
            'starter' => array(
                'name' => 'スターター',
                'description' => '初めての方におすすめの標準設定',
                'h' => array('persona' => 'general', 'purpose' => array('sightseeing'), 'depth' => 2),
                'q' => array('tone' => 'casual', 'structure' => 'timeline', 'sensory' => 2, 'story' => 2, 'info' => 2),
                'c' => array('commercial' => 'seo', 'experience' => 'record'),
            ),
            'drama' => array(
                'name' => 'ドラマチック',
                'description' => '感動的なストーリー展開',
                'h' => array('persona' => 'couple', 'purpose' => array('anniversary', 'healing'), 'depth' => 3),
                'q' => array('tone' => 'cinematic', 'structure' => 'hero_journey', 'sensory' => 3, 'story' => 3, 'info' => 2),
                'c' => array('commercial' => 'seo', 'experience' => 'drama'),
            ),
            'seo_starter' => array(
                'name' => 'SEO重視',
                'description' => '検索エンジン最適化に特化',
                'h' => array('persona' => 'general', 'purpose' => array('sightseeing'), 'depth' => 2),
                'q' => array('tone' => 'journalistic', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
                'c' => array('commercial' => 'seo', 'experience' => 'record'),
            ),
            'anniversary' => array(
                'name' => '記念日',
                'description' => '特別な日のための感動記事',
                'h' => array('persona' => 'couple', 'purpose' => array('anniversary'), 'depth' => 3),
                'q' => array('tone' => 'emotional', 'structure' => 'hero_journey', 'sensory' => 3, 'story' => 3, 'info' => 2),
                'c' => array('commercial' => 'conversion', 'experience' => 'immersive'),
            ),
            'premium' => array(
                'name' => 'プレミアム',
                'description' => '高級ホテル向けの上質な記事',
                'h' => array('persona' => 'luxury', 'purpose' => array('healing', 'gourmet'), 'depth' => 3),
                'q' => array('tone' => 'luxury', 'structure' => 'five_sense', 'sensory' => 3, 'story' => 2, 'info' => 3),
                'c' => array('commercial' => 'conversion', 'experience' => 'immersive'),
            ),
            'family_comfort' => array(
                'name' => 'ファミリー',
                'description' => '家族向けの安心・便利情報',
                'h' => array('persona' => 'family', 'purpose' => array('family', 'sightseeing'), 'depth' => 2),
                'q' => array('tone' => 'casual', 'structure' => 'timeline', 'sensory' => 2, 'story' => 2, 'info' => 3),
                'c' => array('commercial' => 'seo', 'experience' => 'record'),
            ),
            'workation' => array(
                'name' => 'ワーケーション',
                'description' => '仕事と休暇を両立',
                'h' => array('persona' => 'workation', 'purpose' => array('workation'), 'depth' => 2),
                'q' => array('tone' => 'journalistic', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
                'c' => array('commercial' => 'seo', 'experience' => 'record'),
            ),
            'fivesense' => array(
                'name' => '五感体験',
                'description' => '五感を刺激する没入型記事',
                'h' => array('persona' => 'general', 'purpose' => array('onsen', 'gourmet', 'healing'), 'depth' => 3),
                'q' => array('tone' => 'emotional', 'structure' => 'five_sense', 'sensory' => 3, 'story' => 3, 'info' => 2),
                'c' => array('commercial' => 'seo', 'experience' => 'immersive'),
            ),
            'cost_performance' => array(
                'name' => 'コスパ重視',
                'description' => '節約志向の読者向け',
                'h' => array('persona' => 'budget', 'purpose' => array('budget', 'sightseeing'), 'depth' => 2),
                'q' => array('tone' => 'casual', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
                'c' => array('commercial' => 'conversion', 'experience' => 'record'),
            ),
            'onsen' => array(
                'name' => '温泉特化',
                'description' => '温泉の魅力を最大限に伝える',
                'h' => array('persona' => 'general', 'purpose' => array('onsen', 'healing'), 'depth' => 3),
                'q' => array('tone' => 'emotional', 'structure' => 'five_sense', 'sensory' => 3, 'story' => 2, 'info' => 2),
                'c' => array('commercial' => 'seo', 'experience' => 'immersive'),
            ),
        );
    }

    /**
     * プリセットからHQC設定を適用
     * 
     * @param string $preset_id
     * @return array|false
     */
    protected function apply_preset($preset_id) {
        $preset = $this->get_preset($preset_id);
        if (!$preset) {
            return false;
        }

        $settings = array(
            'h' => $preset['h'],
            'q' => $preset['q'],
            'c' => $preset['c'],
        );

        $this->save_hqc_settings($settings);
        return $settings;
    }
}

/**
 * HQC Scoring トレイト（HRS_HQC_Analyzerに統一）
 */
trait HRS_HQC_Scoring_Trait {

    /**
     * E-E-A-T評価キーワード（後方互換用）
     */
    protected $eeat_keywords = array(
        'experience' => array('実際に', '体験', '宿泊して', '訪れて', '試して', '感じた'),
        'expertise' => array('専門', 'プロ', '詳しく', '知識', '経験豊富', '長年'),
        'authority' => array('公式', '認定', '受賞', '評価', 'ランキング', '人気'),
        'trust' => array('信頼', '安心', '実績', '確認済み', '検証', '口コミ'),
    );

    /**
     * 感情価値キーワード（後方互換用）
     */
    protected $emotion_keywords = array(
        'positive' => array('感動', '素晴らしい', '最高', '幸せ', '満足', '癒し', '贅沢', '特別'),
        'sensory' => array('香り', '音', '味', '触感', '眺め', '色彩', '温かい', '柔らかい'),
        'emotional' => array('心', '思い出', '記憶', '忘れられない', '胸', '涙', '笑顔'),
    );

    /**
     * E-E-A-Tスコアを計算（HRS_HQC_Analyzerを使用）
     * 
     * @param string $content
     * @return float 0.0-1.0
     */
    protected function calculate_eeat_score($content) {
        if (class_exists('HRS_HQC_Analyzer')) {
            $analyzer = new HRS_HQC_Analyzer();
            $result = $analyzer->analyze($content, array());
            return $result['h_score'] / 100; // 0-100を0-1に変換
        }
        
        // フォールバック
        $scores = array();
        foreach ($this->eeat_keywords as $category => $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                $count += mb_substr_count($content, $keyword);
            }
            $scores[$category] = min(1.0, $count / 3);
        }
        return array_sum($scores) / count($scores);
    }

    /**
     * 感情スコアを計算（HRS_HQC_Analyzerを使用）
     * 
     * @param string $content
     * @return float 0.0-1.0
     */
    protected function calculate_emotion_score($content) {
        if (class_exists('HRS_HQC_Analyzer')) {
            $analyzer = new HRS_HQC_Analyzer();
            $result = $analyzer->analyze($content, array());
            // H軸のemotionスコアを使用
            $h_details = $result['h_details'] ?? array();
            if (isset($h_details['emotion']['score']) && isset($h_details['emotion']['max'])) {
                return $h_details['emotion']['score'] / $h_details['emotion']['max'];
            }
            return $result['h_score'] / 100;
        }
        
        // フォールバック
        $total = 0;
        $count = 0;
        foreach ($this->emotion_keywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $total += mb_substr_count($content, $keyword);
            }
            $count += count($keywords);
        }
        return min(1.0, $total / ($count * 0.5));
    }

    /**
     * 五感スコアを計算（HRS_HQC_Analyzerを使用）
     * 
     * @param string $content
     * @return float 0.0-1.0
     */
    protected function calculate_sensory_score($content) {
        if (class_exists('HRS_HQC_Analyzer')) {
            $analyzer = new HRS_HQC_Analyzer();
            $result = $analyzer->analyze($content, array());
            // Q軸のfive_sensesスコアを使用
            $q_details = $result['q_details'] ?? array();
            if (isset($q_details['five_senses']['score']) && isset($q_details['five_senses']['max'])) {
                return $q_details['five_senses']['score'] / $q_details['five_senses']['max'];
            }
            return $result['q_score'] / 100;
        }
        
        // フォールバック
        $senses = array(
            'visual' => array('見える', '眺め', '景色', '色', '光', '美しい'),
            'auditory' => array('聞こえる', '音', '静か', '鳴く', '響く'),
            'olfactory' => array('香り', '匂い', '芳しい', 'アロマ'),
            'gustatory' => array('味', '美味', '甘い', '旨み', '風味'),
            'tactile' => array('触れ', '肌触り', '温かい', '柔らかい', 'ふわふわ'),
        );
        
        $detected = 0;
        foreach ($senses as $sense => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($content, $keyword) !== false) {
                    $detected++;
                    break;
                }
            }
        }
        
        return $detected / count($senses);
    }
}