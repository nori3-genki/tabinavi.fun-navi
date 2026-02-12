<?php
/**
 * HQC自動最適化エンジン - 改良版
 *
 * 再分析結果（weak_points, chronic_weak_points, success_patterns）から
 * HQCパラメータを自動調整し、次回生成の品質を向上させる
 *
 * 適用順序（重要）:
 *   1. ホテル別の慢性的弱点（chronic_weak_points） → 恒常補正（最優先）
 *   2. 今回のweak_points → 今回補正
 *   3. 成功パターン（success_patterns） → 最終ブースト（オプション）
 *
 * @package HRS
 * @subpackage Learning
 * @version 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Auto_Optimizer {

    private static $instance = null;

    /**
     * weak_point category → HQCパラメータのマッピング
     */
    private $weak_point_to_param_map = array(
        // ========================================
        // H軸 → H層パラメータ
        // ========================================
        'H_timeline' => array(
            'targets' => array(
                array('path' => 'q.structure', 'recommend' => 'timeline'),
                array('path' => 'q.story', 'action' => 'increase', 'levels' => array('S1', 'S2', 'S3')),
            ),
            'description' => '時系列描写が不足 → 構造をtimelineに、物語強度を上げる',
        ),
        'H_emotion' => array(
            'targets' => array(
                array('path' => 'q.story', 'action' => 'increase', 'levels' => array('S1', 'S2', 'S3')),
                array('path' => 'q.expression', 'action' => 'increase', 'levels' => array('E1', 'E2', 'E3')),
                array('path' => 'q.tone', 'recommend' => 'emotional'),
            ),
            'description' => '感情表現が不足 → 物語強度・表現スタイルを上げる',
        ),
        'H_purpose' => array(
            'targets' => array(
                array('path' => 'q.target', 'action' => 'increase', 'levels' => array('T1', 'T2', 'T3')),
                array('path' => 'h.purpose', 'action' => 'ensure_not_empty'),
            ),
            'description' => '旅の目的が不明確 → ターゲット最適化を上げる、目的を設定',
        ),
        'H_scene' => array(
            'targets' => array(
                array('path' => 'q.sensory', 'action' => 'increase', 'levels' => array('G1', 'G2', 'G3')),
                array('path' => 'q.expression', 'action' => 'increase', 'levels' => array('E1', 'E2', 'E3')),
            ),
            'description' => 'シーン描写が不足 → 五感強度・表現スタイルを上げる',
        ),
        'H_first_person' => array(
            'targets' => array(
                array('path' => 'q.story', 'action' => 'increase', 'levels' => array('S1', 'S2', 'S3')),
                array('path' => 'q.tone', 'recommend_any' => array('casual', 'emotional')),
            ),
            'description' => '一人称視点が不足 → 物語強度を上げ、カジュアル/エモーショナルなトーンに',
        ),

        // ========================================
        // Q軸 → Q層パラメータ
        // ========================================
        'Q_objective_data' => array(
            'targets' => array(
                array('path' => 'q.info', 'action' => 'increase', 'levels' => array('I1', 'I2', 'I3')),
                array('path' => 'q.reliability', 'action' => 'increase', 'levels' => array('R1', 'R2', 'R3')),
                array('path' => 'h.depth', 'action' => 'increase', 'levels' => array('L1', 'L2', 'L3')),
            ),
            'description' => '客観データ不足 → 情報強度・信頼性・情報深度を上げる',
        ),
        'Q_five_senses' => array(
            'targets' => array(
                array('path' => 'q.sensory', 'action' => 'increase', 'levels' => array('G1', 'G2', 'G3')),
                array('path' => 'q.expression', 'action' => 'increase', 'levels' => array('E1', 'E2', 'E3')),
            ),
            'description' => '五感描写が不足 → 五感強度を上げる',
        ),
        'Q_cuisine' => array(
            'targets' => array(
                array('path' => 'q.sensory', 'action' => 'increase', 'levels' => array('G1', 'G2', 'G3')),
                array('path' => 'q.volume', 'action' => 'increase', 'levels' => array('V1', 'V2', 'V3')),
            ),
            'description' => '料理描写が不足 → 五感強度・情報量を上げる',
        ),
        'Q_facility' => array(
            'targets' => array(
                array('path' => 'q.info', 'action' => 'increase', 'levels' => array('I1', 'I2', 'I3')),
                array('path' => 'q.volume', 'action' => 'increase', 'levels' => array('V1', 'V2', 'V3')),
                array('path' => 'h.depth', 'action' => 'increase', 'levels' => array('L1', 'L2', 'L3')),
            ),
            'description' => '施設情報が不足 → 情報強度・情報量・情報深度を上げる',
        ),

        // ========================================
        // C軸 → C層パラメータ + Q層
        // ========================================
        'C_h2_headings' => array(
            'targets' => array(
                array('path' => 'q.volume', 'action' => 'increase', 'levels' => array('V1', 'V2', 'V3')),
            ),
            'description' => 'H2見出し不足 → 情報量を上げる',
        ),
        'C_word_count' => array(
            'targets' => array(
                array('path' => 'q.volume', 'action' => 'increase', 'levels' => array('V1', 'V2', 'V3')),
                array('path' => 'h.depth', 'action' => 'increase', 'levels' => array('L1', 'L2', 'L3')),
            ),
            'description' => '文字数不足 → 情報量・情報深度を上げる',
        ),
        'C_keyphrase_density' => array(
            'targets' => array(
                array('path' => 'q.seo', 'action' => 'increase', 'levels' => array('SEO1', 'SEO2', 'SEO3')),
            ),
            'description' => 'キーフレーズ密度が低い → SEO強度を上げる',
        ),
        'C_keyphrase_intro' => array(
            'targets' => array(
                array('path' => 'q.seo', 'action' => 'increase', 'levels' => array('SEO1', 'SEO2', 'SEO3')),
            ),
            'description' => '冒頭にキーフレーズなし → SEO強度を上げる',
        ),
        'C_cta' => array(
            'targets' => array(
                array('path' => 'c.commercial', 'recommend' => 'conversion'),
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'cta'),
            ),
            'description' => 'CTA不足 → 商業性をCV重視に、CTAコンテンツ要素を有効化',
        ),
        'C_affiliate_links' => array(
            'targets' => array(
                array('path' => 'c.commercial', 'recommend_any' => array('seo', 'conversion')),
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'affiliate_links'),
            ),
            'description' => '予約リンク不足 → 商業性レベルを上げ、予約リンクを有効化',
        ),
        'C_price_info' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'price_info'),
                array('path' => 'q.info', 'action' => 'increase', 'levels' => array('I1', 'I2', 'I3')),
            ),
            'description' => '価格情報不足 → 価格情報コンテンツを有効化、情報強度を上げる',
        ),
        'C_comparison' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'comparison'),
            ),
            'description' => '比較表現不足 → 比較コンテンツ要素を有効化',
        ),
        'C_faq' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'faq'),
            ),
            'description' => 'FAQ不足 → FAQコンテンツ要素を有効化',
        ),
        'C_pros_cons' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'pros_cons'),
            ),
            'description' => 'メリデメ不足 → メリット・デメリット要素を有効化',
        ),
        'C_target_audience' => array(
            'targets' => array(
                array('path' => 'q.target', 'action' => 'increase', 'levels' => array('T1', 'T2', 'T3')),
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'target_audience'),
            ),
            'description' => 'ターゲット不明確 → ターゲット最適化を上げる、ターゲット訴求を有効化',
        ),
        'C_seasonal_info' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'seasonal_info'),
            ),
            'description' => '季節情報不足 → 季節情報コンテンツ要素を有効化',
        ),
        'C_access_info' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'access_info'),
            ),
            'description' => 'アクセス情報不足 → アクセス情報コンテンツ要素を有効化',
        ),
        'C_reviews' => array(
            'targets' => array(
                array('path' => 'c.contents', 'action' => 'ensure_contains', 'value' => 'reviews'),
            ),
            'description' => '口コミ不足 → 口コミコンテンツ要素を有効化',
        ),

        // ========================================
        // AI表現ペナルティ
        // ========================================
        'AI_ai_expressions' => array(
            'targets' => array(
                array('path' => 'q.expression', 'recommend' => 'E1'),
                array('path' => 'q.tone', 'recommend_any' => array('journalistic', 'casual')),
            ),
            'description' => 'AI定型表現が多い → 表現をシンプルに、トーンを報道的/カジュアルに',
        ),
    );


    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 再分析結果からパラメータを自動最適化
     *
     * @param array $current_settings 現在のHQCパラメータ
     * @param array $analysis_result HQC Analyzerの分析結果
     * @param array $options オプション（hotel_name, use_success_patterns 等）
     * @return array 最適化結果
     */
    public function optimize($current_settings, $analysis_result, $options = array()) {
        $optimized = $current_settings;
        $changes = array();
        $reasons = array();
        $weak_points = $analysis_result['weak_points'] ?? array();
        $hotel_name = $options['hotel_name'] ?? '';

        // ========================================
        // 適用順序（修正済み）
        // 1. 慢性的弱点（恒常補正） ← 最優先
        // 2. 今回のweak_points（今回補正）
        // 3. 成功パターン（最終ブースト）
        // ========================================

        // 1. ホテル別の慢性的弱点を考慮（恒常補正）
        if (!empty($hotel_name) && class_exists('HRS_HQC_Learning_Module')) {
            $learning = HRS_HQC_Learning_Module::get_instance();
            $hotel_data = $learning->get_hotel_learning($hotel_name);
            if ($hotel_data && !empty($hotel_data['chronic_weak_points'])) {
                $chronic_result = $this->apply_chronic_adjustments(
                    $optimized,
                    $hotel_data['chronic_weak_points']
                );
                $optimized = $chronic_result['settings'];
                $changes = array_merge($changes, $chronic_result['changes']);
                $reasons = array_merge($reasons, $chronic_result['reasons']);
            }
        }

        // 2. 今回のweak_pointsからパラメータ調整（今回補正）
        foreach ($weak_points as $wp) {
            $map_key = $wp['axis'] . '_' . $wp['category'];
            if (!isset($this->weak_point_to_param_map[$map_key])) {
                continue;
            }

            $mapping = $this->weak_point_to_param_map[$map_key];
            $priority = ($wp['score_ratio'] ?? 1) < 0.3 ? 'high' : 'medium';

            foreach ($mapping['targets'] as $target) {
                $result = $this->apply_adjustment($optimized, $target, $priority);
                if ($result['changed']) {
                    $optimized = $result['settings'];
                    $changes[] = array(
                        'param'     => $target['path'],
                        'from'      => $result['from'],
                        'to'        => $result['to'],
                        'priority'  => $priority,
                        'reason'    => $mapping['description'],
                    );
                }
            }
            $reasons[] = $mapping['description'];
        }

        // 3. 成功パターンからのブースト（最終ブースト・オプション）
        if (!empty($options['use_success_patterns'])) {
            $pattern_result = $this->apply_success_patterns($optimized);
            $optimized = $pattern_result['settings'];
            $changes = array_merge($changes, $pattern_result['changes']);
            // success_patterns由来のreasonはapply_success_patterns内で追加される
        }

        return array(
            'settings'      => $optimized,
            'changes'       => $changes,
            'reasons'       => array_unique($reasons),
            'change_count'  => count($changes),
            'original_score' => $analysis_result['total_score'] ?? 0,
        );
    }


    /**
     * パラメータ調整を適用（安全性を強化）
     */
    private function apply_adjustment($settings, $target, $priority = 'medium') {
        $path = $target['path'];
        $parts = explode('.', $path);

        if (count($parts) !== 2) {
            return array('changed' => false, 'settings' => $settings);
        }

        $layer = $parts[0]; // h, q, c
        $key   = $parts[1];

        // 重要: レイヤーが存在しない場合は空配列で初期化
        if (!isset($settings[$layer]) || !is_array($settings[$layer])) {
            $settings[$layer] = array();
        }

        $current_value = $settings[$layer][$key] ?? null;
        $new_value = $current_value;

        // アクション別処理
        if (isset($target['recommend'])) {
            $new_value = $target['recommend'];
        } elseif (isset($target['recommend_any'])) {
            if (!in_array($current_value, $target['recommend_any'], true)) {
                $new_value = $target['recommend_any'][0];
            }
        } elseif (isset($target['action'])) {
            switch ($target['action']) {
                case 'increase':
                    $levels = $target['levels'] ?? array();
                    $new_value = $this->increase_level($current_value, $levels, $priority);
                    break;

                case 'ensure_not_empty':
                    if (empty($current_value) || (is_array($current_value) && count($current_value) === 0)) {
                        $new_value = array('sightseeing'); // デフォルト目的
                    }
                    break;

                case 'ensure_contains':
                    $value_to_add = $target['value'] ?? '';
                    if (empty($value_to_add)) {
                        break;
                    }

                    // 型安全性を確保
                    if ($current_value === null) {
                        $current_value = array();
                    } elseif (!is_array($current_value)) {
                        // 配列でない場合は保護 → 変更しない
                        return array('changed' => false, 'settings' => $settings);
                    }

                    if (!in_array($value_to_add, $current_value, true)) {
                        $new_value = array_merge($current_value, array($value_to_add));
                    }
                    break;
            }
        }

        $changed = ($new_value !== $current_value);

        if ($changed) {
            $settings[$layer][$key] = $new_value;
        }

        return array(
            'changed'  => $changed,
            'settings' => $settings,
            'from'     => $current_value,
            'to'       => $new_value,
        );
    }


    /**
     * レベルを1段階（またはhigh優先時は2段階）上げる
     */
    private function increase_level($current, $levels, $priority = 'medium') {
        if (empty($levels)) {
            return $current;
        }

        $current_index = array_search($current, $levels, true);

        if ($current_index === false) {
            return $levels[0];
        }

        $step = ($priority === 'high') ? 2 : 1;
        $new_index = min($current_index + $step, count($levels) - 1);

        return $levels[$new_index];
    }


    /**
     * 慢性的弱点に基づく調整（high priority固定）
     */
    private function apply_chronic_adjustments($settings, $chronic_weak_points) {
        $changes = array();
        $reasons = array();

        foreach ($chronic_weak_points as $key => $wp) {
            if (!isset($wp['count']) || $wp['count'] < 3) {
                continue;
            }

            $map_key = $wp['axis'] . '_' . $wp['category'];
            if (!isset($this->weak_point_to_param_map[$map_key])) {
                continue;
            }

            $mapping = $this->weak_point_to_param_map[$map_key];

            foreach ($mapping['targets'] as $target) {
                $result = $this->apply_adjustment($settings, $target, 'high');

                if ($result['changed']) {
                    $settings = $result['settings'];
                    $changes[] = array(
                        'param'    => $target['path'],
                        'from'     => $result['from'],
                        'to'       => $result['to'],
                        'priority' => 'high',
                        'reason'   => '【慢性的弱点】' . $mapping['description'] . '（' . $wp['count'] . '回検出）',
                    );
                }
            }

            $reasons[] = '【慢性的】' . $mapping['description'];
        }

        return array(
            'settings' => $settings,
            'changes'  => $changes,
            'reasons'  => $reasons,
        );
    }


    /**
     * 成功パターンに基づく最終ブースト
     */
    private function apply_success_patterns($settings) {
        $changes = array();

        if (!class_exists('HRS_HQC_Learning_Module')) {
            return array('settings' => $settings, 'changes' => $changes);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hrs_success_patterns';

        $best_combo = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT pattern_key, avg_score_impact, usage_count
                 FROM {$table}
                 WHERE pattern_type = 'combo'
                   AND is_active = 1
                   AND usage_count >= 3
                 ORDER BY avg_score_impact DESC
                 LIMIT 1"
            ),
            ARRAY_A
        );

        if (!$best_combo) {
            return array('settings' => $settings, 'changes' => $changes);
        }

        $parts = explode('_', $best_combo['pattern_key']);
        if (count($parts) < 3) {
            return array('settings' => $settings, 'changes' => $changes);
        }

        $recommended_structure = $parts[0];
        $recommended_persona   = $parts[1];
        $recommended_tone      = $parts[2];

        $current_persona = $settings['h']['persona'] ?? 'general';

        // ペルソナが一致する場合にのみ構造・トーンを適用
        if ($current_persona === $recommended_persona) {
            // structure
            if (($settings['q']['structure'] ?? '') !== $recommended_structure) {
                $old = $settings['q']['structure'] ?? '';
                $settings['q']['structure'] = $recommended_structure;
                $changes[] = array(
                    'param'    => 'q.structure',
                    'from'     => $old,
                    'to'       => $recommended_structure,
                    'priority' => 'low',
                    'reason'   => '【成功パターン】高スコア実績のある構造（avg: ' . round($best_combo['avg_score_impact'], 1) . '点）',
                );
            }

            // tone
            if (($settings['q']['tone'] ?? '') !== $recommended_tone) {
                $old = $settings['q']['tone'] ?? '';
                $settings['q']['tone'] = $recommended_tone;
                $changes[] = array(
                    'param'    => 'q.tone',
                    'from'     => $old,
                    'to'       => $recommended_tone,
                    'priority' => 'low',
                    'reason'   => '【成功パターン】高スコア実績のあるトーン',
                );
            }
        }

        return array('settings' => $settings, 'changes' => $changes);
    }


    /**
     * 投稿IDから自動最適化を実行（一連の処理をまとめて実行）
     */
    public function optimize_from_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', '記事が見つかりません');
        }

        if (!class_exists('HRS_HQC_Analyzer')) {
            return new WP_Error('analyzer_missing', 'HQC Analyzerクラスが見つかりません');
        }

        $analyzer = new HRS_HQC_Analyzer();
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true) ?: $post->post_title;

        $analysis = $analyzer->analyze($post->post_content, array(
            'hotel_name' => $hotel_name
        ));

        // 現在の設定を取得（記事 > グローバル > デフォルト）
        $current_settings = get_post_meta($post_id, '_hrs_hqc_settings', true);
        if (empty($current_settings) || !is_array($current_settings)) {
            $current_settings = get_option('hrs_hqc_settings', array());
        }
        if (empty($current_settings)) {
            $current_settings = class_exists('HRS_Hqc_Data')
                ? HRS_Hqc_Data::get_default_settings()
                : array();
        }

        // 最適化実行
        $result = $this->optimize($current_settings, $analysis, array(
            'hotel_name'          => $hotel_name,
            'use_success_patterns' => true,
        ));

        // 変更があった場合のみ保存
        if ($result['change_count'] > 0) {
            update_post_meta($post_id, '_hrs_hqc_settings_optimized', $result['settings']);
            update_post_meta($post_id, '_hrs_hqc_optimization_log', array(
                'timestamp'     => current_time('mysql'),
                'changes'       => $result['changes'],
                'original_score' => $result['original_score'],
                'reasons'       => $result['reasons'],
            ));
        }

        return $result;
    }


    /**
     * 最適化結果を人間が読みやすい形式に整形
     */
    public static function format_optimization_summary($result) {
        if (empty($result['changes'])) {
            return '最適化の必要なし（現在のパラメータで十分な品質です）';
        }

        $lines = array();
        $lines[] = sprintf(
            '🔧 %d件のパラメータを自動調整（元スコア: %.1f点）',
            $result['change_count'],
            $result['original_score']
        );
        $lines[] = '';

        $priority_icons = array(
            'high'   => '🔴',
            'medium' => '🟡',
            'low'    => '🟢',
        );

        foreach ($result['changes'] as $change) {
            $icon = $priority_icons[$change['priority']] ?? '⚪';

            $from = is_array($change['from'])
                ? implode(', ', $change['from'])
                : ($change['from'] ?: '未設定');

            $to = is_array($change['to'])
                ? implode(', ', $change['to'])
                : $change['to'];

            $lines[] = sprintf(
                '%s %s:  %s → %s',
                $icon,
                $change['param'],
                $from,
                $to
            );

            if (!empty($change['reason'])) {
                $lines[] = '   └ ' . $change['reason'];
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

}