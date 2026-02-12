<?php
/**
 * Generator Page - メインクラス
 * 
 * 手動プロンプト生成ページ
 * 分割ファイルを読み込み、統合管理
 * 
 * @package Hotel_Review_System
 * @version 6.7.2-FIX
 * 
 * 分割構造:
 * - generator/class-generator-data.php    : プリセット定義
 * - generator/class-generator-styles.php  : CSS
 * - generator/class-generator-scripts.php : JavaScript
 * - generator/class-generator-ui.php      : HTML/UI
 * 
 * 変更履歴:
 * - 6.7.0: UI改善（HQC分析ガイド、プリセット説明、プロンプト状態表示）
 * - 6.7.1-FIX: HQCスコア0.0表示エラー修正（記事ID取得・meta key名対応）
 * - 6.7.2-FIX: 紫帯の重複表示を削除（HRS_Generator_UI::render_regenerate_alert()に統一）
 */
if (!defined('ABSPATH')) {
    exit;
}

// 分割ファイルを読み込み
require_once __DIR__ . '/generator/class-generator-data.php';
require_once __DIR__ . '/generator/class-generator-styles.php';
require_once __DIR__ . '/generator/class-generator-scripts.php';
require_once __DIR__ . '/generator/class-generator-ui.php';

// クラス重複チェック
if (class_exists('HRS_Generator_Page')) {
    return;
}

class HRS_Generator_Page {
    
    /**
     * レンダリング（インスタンスメソッド）
     */
    public function render() {
        // v6.7.2-FIX: 紫帯（render_hqc_analysis_guide）を削除
        // → HRS_Generator_UI::render() 内の render_regenerate_alert() に統一
        // $this->render_hqc_analysis_guide(); // 削除
        
        // メインUIをレンダリング
        HRS_Generator_UI::render();
    }
    
    /**
     * 静的呼び出し用ラッパー（後方互換性）
     */
    public static function render_page() {
        $instance = new self();
        $instance->render();
    }
    
    /**
     * プリセット取得（後方互換性）
     */
    private function get_presets() {
        return HRS_Generator_Data::get_presets();
    }
    
    /**
     * HQCプリセットの取得（説明付き）
     * 
     * @since 6.7.0
     * @return array
     */
    public static function get_presets_with_description() {
        return array(
            'balance' => array(
                'label' => '🎯 バランス型（推奨）',
                'tone' => '中立的・バランス',
                'focus' => 'H⭐⭐⭐ Q⭐⭐⭐ C⭐⭐⭐',
                'description' => '全軸を均等に強化'
            ),
            'seo' => array(
                'label' => '🔍 SEO最大化',
                'tone' => '情報的・説明的',
                'focus' => 'H⭐⭐ Q⭐⭐ C⭐⭐⭐⭐⭐',
                'description' => 'C層強化：見出し・KW・構造'
            ),
            'emotion' => array(
                'label' => '💕 感情訴求型',
                'tone' => 'カジュアル・親しみやすい',
                'focus' => 'H⭐⭐⭐⭐⭐ Q⭐⭐ C⭐⭐',
                'description' => 'H層強化：感情・体験談・ストーリー'
            ),
            'sensory' => array(
                'label' => '🍜 五感描写型',
                'tone' => '詳細描写・具体的',
                'focus' => 'H⭐⭐ Q⭐⭐⭐⭐⭐ C⭐⭐',
                'description' => 'Q層強化：視覚・味覚・触覚・嗅覚・聴覚'
            ),
            'family' => array(
                'label' => '👨‍👩‍👧‍👦 ファミリー向け',
                'tone' => '明るい・親しみやすい',
                'focus' => 'H⭐⭐⭐⭐ Q⭐⭐⭐ C⭐⭐',
                'description' => 'H+Q層：家族体験談・子供視点'
            ),
            'luxury' => array(
                'label' => '💎 高級・記念日',
                'tone' => 'フォーマル・洗練',
                'focus' => 'H⭐⭐⭐⭐ Q⭐⭐⭐⭐ C⭐⭐⭐',
                'description' => 'H+Q層：特別感・高級素材描写'
            ),
            'solo' => array(
                'label' => '🚶 一人旅向け',
                'tone' => '内省的・個人的',
                'focus' => 'H⭐⭐⭐⭐ Q⭐⭐⭐ C⭐⭐',
                'description' => 'H層強化：個人的内省・感情'
            ),
            'workation' => array(
                'label' => '💼 ワーケーション',
                'tone' => 'プロフェッショナル・実用的',
                'focus' => 'H⭐⭐⭐ Q⭐⭐⭐⭐ C⭐⭐⭐',
                'description' => 'Q+C層：設備・Wi-Fi・ビジネス'
            ),
        );
    }
}