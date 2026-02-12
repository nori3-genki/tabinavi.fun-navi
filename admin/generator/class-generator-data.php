<?php
/**
 * Generator Data - プリセット定義クラス
 * 
 * @package Hotel_Review_System
 * @subpackage Generator
 * @version 6.8.1-ENGINE-LOAD
 * 
 * 変更履歴:
 * - 6.8.1: プロンプトエンジン読み込み追加
 */
if (!defined('ABSPATH')) {
    exit;
}

// プロンプトエンジンを読み込み
$prompt_engine_file = HRS_PLUGIN_DIR . 'includes/generator/class-prompt-engine.php';
if (file_exists($prompt_engine_file)) {
    require_once $prompt_engine_file;
}

class HRS_Generator_Data {
    /**
     * HQCプリセット定義
     * HRS_Prompt_Engine と連携するパラメータを含む
     * 
     * hqc_scores: 各軸の目標スコア（合計100点満点）
     *   - H: Human（人間味・体験談）
     *   - Q: Quality（品質・具体性）
     *   - C: Content（構成・SEO）
     */
    public static function get_presets() {
        return array(
            'balanced' => array(
                'name' => '🎯 バランス型（推奨）',
                'description' => 'SEOと読みやすさのバランスを重視',
                'hqc_scores' => array('H' => 33, 'Q' => 34, 'C' => 33),
                'style' => 'story',
                'persona' => 'couple',
                'tone' => 'luxury',
                'policy' => 'seo',
            ),
            'seo_max' => array(
                'name' => '🔍 SEO最大化',
                'description' => '検索順位を最優先',
                'hqc_scores' => array('H' => 20, 'Q' => 30, 'C' => 50),
                'style' => 'guide',
                'persona' => 'couple',
                'tone' => 'journalistic',
                'policy' => 'seo',
            ),
            'emotional' => array(
                'name' => '💕 感情訴求型',
                'description' => '読者の心に響く感動的な記事',
                'hqc_scores' => array('H' => 50, 'Q' => 30, 'C' => 20),
                'style' => 'emotional',
                'persona' => 'couple',
                'tone' => 'emotional',
                'policy' => 'conversion',
            ),
            'five_sense' => array(
                'name' => '🌸 五感描写型',
                'description' => '五感を使った臨場感ある記事',
                'hqc_scores' => array('H' => 35, 'Q' => 45, 'C' => 20),
                'style' => 'five_sense',
                'persona' => 'couple',
                'tone' => 'cinematic',
                'policy' => 'standard',
            ),
            'family' => array(
                'name' => '👨‍👩‍👧‍👦 ファミリー向け',
                'description' => '子連れ旅行に最適な情報',
                'hqc_scores' => array('H' => 30, 'Q' => 45, 'C' => 25),
                'style' => 'guide',
                'persona' => 'family',
                'tone' => 'casual',
                'policy' => 'standard',
            ),
            'luxury' => array(
                'name' => '💎 高級・記念日',
                'description' => '特別な日のための上質な記事',
                'hqc_scores' => array('H' => 40, 'Q' => 40, 'C' => 20),
                'style' => 'emotional',
                'persona' => 'couple',
                'tone' => 'luxury',
                'policy' => 'conversion',
            ),
            'solo' => array(
                'name' => '🧳 一人旅向け',
                'description' => '自分時間を楽しむ旅',
                'hqc_scores' => array('H' => 45, 'Q' => 30, 'C' => 25),
                'style' => 'story',
                'persona' => 'solo',
                'tone' => 'casual',
                'policy' => 'standard',
            ),
            'workation' => array(
                'name' => '💻 ワーケーション',
                'description' => '仕事と休暇を両立',
                'hqc_scores' => array('H' => 25, 'Q' => 40, 'C' => 35),
                'style' => 'guide',
                'persona' => 'workation',
                'tone' => 'journalistic',
                'policy' => 'standard',
            ),
        );
    }
    
    /**
     * プロンプトエンジンのインスタンスを取得
     */
    public static function get_prompt_engine() {
        if (!class_exists('HRS_Prompt_Engine')) {
            return null;
        }
        return new HRS_Prompt_Engine();
    }
}