<?php
/**
 * HQCプロンプト最適化
 * 
 * 学習データに基づいてプロンプトを自動最適化
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Prompt_Optimizer {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * 補強パターン
     */
    private $boost_patterns = array(
        'timeline' => "\n【時系列構成】到着→チェックイン→客室→夕食→入浴→就寝→朝食→チェックアウトの流れで体験を描写してください。",
        'five_senses' => "\n【五感描写】視覚（景色・デザイン）、聴覚（音・静けさ）、嗅覚（香り）、味覚（料理）、触覚（肌触り・温度）を意識的に含めてください。",
        'emotion' => "\n【感情表現】「思わず」「感動」「幸せ」「最高」など、読者の心に響く感情表現を豊富に使ってください。",
        'scene' => "\n【シーン描写】「窓から見える景色」「ベッドに横たわる瞬間」など、具体的な場面を描写してください。",
        'first_person' => "\n【一人称視点】「私は」「私が」を使い、実際に体験したような臨場感のある文章にしてください。",
        'cuisine' => "\n【料理詳細】食材、調理法、盛り付け、味わいを具体的に描写してください。地元食材や季節感も重要です。",
        'facility' => "\n【施設情報】客室設備、温泉・浴場、アメニティ、Wi-Fiなどの実用情報を含めてください。",
    );

    /**
     * 補強レベル係数
     */
    private $boost_levels = array(
        'light' => 0.3,
        'moderate' => 0.5,
        'strong' => 0.7,
        'maximum' => 1.0,
    );

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * プロンプトを最適化
     * 
     * @param string $prompt 元のプロンプト
     * @param string $hotel_name ホテル名
     * @param array $options オプション
     * @return array 最適化結果
     */
    public function optimize($prompt, $hotel_name, $options = array()) {
        $boost_level = $options['boost_level'] ?? 'moderate';
        $force_patterns = $options['force_patterns'] ?? array();

        // ホテル別学習データを取得
        $hotel_learning = $this->get_hotel_learning($hotel_name);
        
        // 適用するパターンを決定
        $patterns_to_apply = $this->determine_patterns($hotel_learning, $force_patterns);
        
        // 補強を適用
        $optimized_prompt = $this->apply_boost($prompt, $patterns_to_apply, $boost_level);

        return array(
            'prompt' => $optimized_prompt,
            'patterns_applied' => $patterns_to_apply,
            'boost_level' => $boost_level,
            'hotel_learning' => $hotel_learning,
            'boost_applied' => !empty($patterns_to_apply),
        );
    }

    /**
     * 80点を目指す自動最適化
     */
    public function optimize_for_80($prompt, $hotel_name) {
        $hotel_learning = $this->get_hotel_learning($hotel_name);
        
        // 平均スコアに基づいて補強レベルを決定
        $avg_score = $hotel_learning['avg_score'] ?? 0;
        
        if ($avg_score < 40) {
            $boost_level = 'maximum';
        } elseif ($avg_score < 55) {
            $boost_level = 'strong';
        } elseif ($avg_score < 70) {
            $boost_level = 'moderate';
        } else {
            $boost_level = 'light';
        }

        // 慢性的弱点から優先パターンを決定
        $priority_patterns = $this->get_priority_patterns($hotel_learning);
        
        $result = $this->optimize($prompt, $hotel_name, array(
            'boost_level' => $boost_level,
            'force_patterns' => $priority_patterns,
        ));

        // 予測改善度を追加
        $result['predicted_improvement'] = $this->predict_improvement($avg_score, $boost_level, count($priority_patterns));

        return $result;
    }

    /**
     * ホテル学習データを取得
     */
    private function get_hotel_learning($hotel_name) {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return array(
                'generation_count' => 0,
                'avg_score' => 0,
                'best_score' => 0,
                'chronic_weak_points' => array(),
            );
        }

        $learning = HRS_HQC_Learning_Module::get_instance();
        $data = $learning->get_hotel_learning($hotel_name);

        if (!$data) {
            return array(
                'generation_count' => 0,
                'avg_score' => 0,
                'best_score' => 0,
                'chronic_weak_points' => array(),
            );
        }

        return $data;
    }

    /**
     * 適用パターンを決定
     */
    private function determine_patterns($hotel_learning, $force_patterns) {
        $patterns = array();

        // 強制パターン
        foreach ($force_patterns as $p) {
            if (isset($this->boost_patterns[$p])) {
                $patterns[] = $p;
            }
        }

        // 慢性的弱点からパターンを追加
        $weak_points = $hotel_learning['chronic_weak_points'] ?? array();
        $pattern_map = array(
            'H_timeline' => 'timeline',
            'H_emotion' => 'emotion',
            'H_scene' => 'scene',
            'H_first_person' => 'first_person',
            'Q_five_senses' => 'five_senses',
            'Q_cuisine' => 'cuisine',
            'Q_facility' => 'facility',
        );

        foreach ($weak_points as $key => $wp) {
            if (isset($pattern_map[$key]) && !in_array($pattern_map[$key], $patterns)) {
                if (($wp['count'] ?? 0) >= 2) {
                    $patterns[] = $pattern_map[$key];
                }
            }
        }

        // 新規ホテルは基本パターンを追加
        if (($hotel_learning['generation_count'] ?? 0) === 0) {
            $default_patterns = array('timeline', 'five_senses', 'emotion');
            foreach ($default_patterns as $p) {
                if (!in_array($p, $patterns)) {
                    $patterns[] = $p;
                }
            }
        }

        return array_unique($patterns);
    }

    /**
     * 優先パターンを取得
     */
    private function get_priority_patterns($hotel_learning) {
        $weak_points = $hotel_learning['chronic_weak_points'] ?? array();
        
        // カウントでソート
        uasort($weak_points, function($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });

        $pattern_map = array(
            'H_timeline' => 'timeline',
            'H_emotion' => 'emotion',
            'H_scene' => 'scene',
            'H_first_person' => 'first_person',
            'Q_five_senses' => 'five_senses',
            'Q_cuisine' => 'cuisine',
            'Q_facility' => 'facility',
        );

        $priority = array();
        foreach (array_slice(array_keys($weak_points), 0, 3) as $key) {
            if (isset($pattern_map[$key])) {
                $priority[] = $pattern_map[$key];
            }
        }

        return $priority;
    }

    /**
     * 補強を適用
     */
    private function apply_boost($prompt, $patterns, $level) {
        if (empty($patterns)) {
            return $prompt;
        }

        $coefficient = $this->boost_levels[$level] ?? 0.5;
        $boost_text = "\n\n【品質向上指示（学習システム自動適用）】";

        foreach ($patterns as $pattern) {
            if (isset($this->boost_patterns[$pattern])) {
                $boost_text .= $this->boost_patterns[$pattern];
            }
        }

        // 係数に基づいて強調度を調整
        if ($coefficient >= 0.7) {
            $boost_text .= "\n\n※上記の指示を特に重視し、高品質な記事を作成してください。";
        }

        return $prompt . $boost_text;
    }

    /**
     * 改善予測
     */
    private function predict_improvement($current_avg, $boost_level, $pattern_count) {
        if ($current_avg === 0) {
            return null;
        }

        $base_improvement = array(
            'light' => 3,
            'moderate' => 7,
            'strong' => 12,
            'maximum' => 18,
        );

        $improvement = ($base_improvement[$boost_level] ?? 5) + ($pattern_count * 2);
        $predicted_score = min(100, $current_avg + $improvement);

        return array(
            'current_avg' => $current_avg,
            'expected_improvement' => $improvement,
            'predicted_score' => round($predicted_score, 1),
            'confidence' => $pattern_count >= 3 ? 'high' : 'medium',
        );
    }

    /**
     * 推奨設定を取得
     */
    public function get_recommended_settings($hotel_name) {
        $learning = $this->get_hotel_learning($hotel_name);

        if (($learning['generation_count'] ?? 0) === 0) {
            return array(
                'boost_level' => 'moderate',
                'patterns' => array('timeline', 'five_senses', 'emotion'),
                'reason' => '新規ホテル - 標準設定を推奨',
            );
        }

        $avg = $learning['avg_score'] ?? 0;
        $priority_patterns = $this->get_priority_patterns($learning);

        if ($avg < 50) {
            $boost_level = 'maximum';
        } elseif ($avg < 60) {
            $boost_level = 'strong';
        } elseif ($avg < 70) {
            $boost_level = 'moderate';
        } else {
            $boost_level = 'light';
        }

        return array(
            'boost_level' => $boost_level,
            'patterns' => $priority_patterns,
            'reason' => sprintf('平均スコア%.1f%% - %s補強を推奨', $avg, $boost_level),
            'hotel_data' => array(
                'generation_count' => $learning['generation_count'],
                'avg_score' => $learning['avg_score'],
                'best_score' => $learning['best_score'],
            ),
        );
    }
}