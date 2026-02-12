<?php
/**
 * HRS Manual Prompt Builder
 * 手動プロンプト生成用クラス
 *
 * @package Hotel_Review_System
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Manual_Prompt_Builder {

    /**
     * プロンプトを生成
     *
     * @param array $args 生成パラメータ
     * @return string|WP_Error
     */
    public static function build($args) {
        $defaults = [
            'hotel_name'  => '',
            'location'    => '',
            'preset'      => 'balanced',
            'words'       => 2000,
            'ai_model'    => 'chatgpt',
            'layers'      => [],
            'post_id'     => 0,
            'weak_points' => [],
        ];

        $args = wp_parse_args($args, $defaults);

        if (empty($args['hotel_name'])) {
            return new WP_Error('missing_hotel', 'ホテル名が指定されていません');
        }

        // プリセット設定
        $preset_configs = self::get_preset_configs();
        $preset = isset($preset_configs[$args['preset']]) ? $preset_configs[$args['preset']] : $preset_configs['balanced'];

        // AIモデル別の調整
        $ai_instructions = self::get_ai_instructions($args['ai_model']);

        // 基本プロンプト構築
        $prompt = self::build_base_prompt($args, $preset, $ai_instructions);

        // スタイルレイヤー適用
        if (!empty($args['layers'])) {
            $prompt = self::apply_style_layers($prompt, $args['layers']);
        }

        // 弱点補強適用
        if (!empty($args['weak_points'])) {
            $prompt = self::apply_weak_point_boost($prompt, $args['weak_points']);
        }

        return $prompt;
    }

    /**
     * プリセット設定を取得
     */
    private static function get_preset_configs() {
        return [
            'creative' => [
                'name' => 'クリエイティブ型',
                'h_weight' => 50,
                'q_weight' => 25,
                'c_weight' => 25,
                'tone' => '感情豊かで物語性のある',
                'focus' => '体験談や感動ポイント',
            ],
            'balanced' => [
                'name' => 'バランス型',
                'h_weight' => 33,
                'q_weight' => 34,
                'c_weight' => 33,
                'tone' => 'バランスの取れた',
                'focus' => '総合的な情報',
            ],
            'seo' => [
                'name' => 'SEO特化型',
                'h_weight' => 20,
                'q_weight' => 30,
                'c_weight' => 50,
                'tone' => '検索エンジンに最適化された',
                'focus' => 'キーワードと構造化',
            ],
            'detail' => [
                'name' => '詳細重視型',
                'h_weight' => 25,
                'q_weight' => 50,
                'c_weight' => 25,
                'tone' => '詳細で具体的な',
                'focus' => '設備やサービスの詳細',
            ],
        ];
    }

    /**
     * AIモデル別の指示を取得
     */
    private static function get_ai_instructions($ai_model) {
        $instructions = [
            'chatgpt' => [
                'format' => 'Markdown形式で出力してください。',
                'style' => '自然で読みやすい文章を心がけてください。',
            ],
            'claude' => [
                'format' => 'Markdown形式で出力してください。',
                'style' => '論理的かつ詳細な分析を含めてください。',
            ],
            'gemini' => [
                'format' => 'Markdown形式で出力してください。',
                'style' => '構造化された分かりやすい形式で記述してください。',
            ],
        ];

        return isset($instructions[$ai_model]) ? $instructions[$ai_model] : $instructions['chatgpt'];
    }

    /**
     * 基本プロンプトを構築
     */
    private static function build_base_prompt($args, $preset, $ai_instructions) {
        $hotel_name = esc_html($args['hotel_name']);
        $location = !empty($args['location']) ? esc_html($args['location']) : '';
        $words = intval($args['words']) > 0 ? intval($args['words']) : 2000;

        $location_text = $location ? "（所在地: {$location}）" : '';

        $prompt = <<<PROMPT
# ホテルレビュー記事作成依頼

## 対象ホテル
**{$hotel_name}**{$location_text}

## 記事の要件

### 基本設定
- **文字数**: 約{$words}文字
- **スタイル**: {$preset['tone']}文章
- **重点**: {$preset['focus']}

### HQCスコア配分（参考）
- Human（体験・感情）: {$preset['h_weight']}%
- Quality（詳細・具体性）: {$preset['q_weight']}%
- Content（SEO・構成）: {$preset['c_weight']}%

### 必須セクション
1. **導入部**: ホテルの第一印象と魅力の概要
2. **客室**: 部屋のタイプ、設備、快適さ
3. **料理・食事**: 朝食、夕食、レストランの特徴
4. **温泉・風呂**: 大浴場、露天風呂、泉質（該当する場合）
5. **サービス**: スタッフの対応、おもてなし
6. **周辺情報**: アクセス、観光スポット
7. **まとめ**: 総合評価とおすすめポイント

### 出力形式
{$ai_instructions['format']}
{$ai_instructions['style']}

### 注意事項
- 実際に宿泊したかのような臨場感のある文章
- 具体的な描写と感想を織り交ぜる
- 読者が予約を検討する際の参考になる情報を含める
- 過度な誇張や虚偽の情報は避ける

PROMPT;

        return $prompt;
    }

    /**
     * スタイルレイヤーを適用
     */
    private static function apply_style_layers($prompt, $layers) {
        $layer_instructions = [];

        foreach ($layers as $layer) {
            switch ($layer) {
                case 'seasonal':
                    $layer_instructions[] = '- 季節感のある表現（現在の季節に合わせた描写）を含める';
                    break;
                case 'regional':
                    $layer_instructions[] = '- 地域色（地元の文化、名物、方言など）を反映させる';
                    break;
                case 'luxury':
                    $layer_instructions[] = '- 高級感のある表現（上質なサービス、洗練された雰囲気）を強調';
                    break;
                case 'family':
                    $layer_instructions[] = '- ファミリー向け情報（子供連れに適した設備、サービス）を含める';
                    break;
            }
        }

        if (!empty($layer_instructions)) {
            $prompt .= "\n### スタイル追加指示\n" . implode("\n", $layer_instructions) . "\n";
        }

        return $prompt;
    }

    /**
     * 弱点補強を適用
     */
    private static function apply_weak_point_boost($prompt, $weak_points) {
        if (empty($weak_points)) {
            return $prompt;
        }

        $boost_instructions = [];

        foreach ($weak_points as $wp) {
            $axis = isset($wp['axis']) ? $wp['axis'] : '';
            $category = isset($wp['category']) ? $wp['category'] : '';

            switch ($axis) {
                case 'H':
                    $boost_instructions[] = "- Human軸（{$category}）: より感情的・体験的な表現を強化";
                    break;
                case 'Q':
                    $boost_instructions[] = "- Quality軸（{$category}）: より具体的・詳細な情報を追加";
                    break;
                case 'C':
                    $boost_instructions[] = "- Content軸（{$category}）: SEO・構造面を改善";
                    break;
            }
        }

        if (!empty($boost_instructions)) {
            $prompt .= "\n### 弱点補強指示（重点的に改善）\n" . implode("\n", $boost_instructions) . "\n";
        }

        return $prompt;
    }
}