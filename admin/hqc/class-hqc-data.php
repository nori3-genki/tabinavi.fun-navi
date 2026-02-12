<?php
/**
 * HQC Data - データ定義クラス
 * 
 * ペルソナ、目的、トーン、構造、コンテンツ要素などの静的データを管理
 * 
 * @package Hotel_Review_System
 * @subpackage HQC
 * @version 6.7.3-FIX
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Hqc_Data {

    /**
     * ペルソナ定義（8種類）
     */
    public static function get_personas() {
        return [
            'general'    => ['name' => '一般・観光', 'icon' => '🌍', 'desc' => '幅広い読者向け'],
            'solo'       => ['name' => '一人旅', 'icon' => '🚶', 'desc' => 'ソロトラベラー向け'],
            'couple'     => ['name' => 'カップル・夫婦', 'icon' => '💑', 'desc' => '二人旅向け'],
            'family'     => ['name' => 'ファミリー', 'icon' => '👨‍👩‍👧‍👦', 'desc' => '家族旅行向け'],
            'senior'     => ['name' => 'シニア', 'icon' => '👴', 'desc' => '60代以上向け'],
            'workation'  => ['name' => 'ワーケーション', 'icon' => '💻', 'desc' => 'リモートワーカー向け'],
            'luxury'     => ['name' => 'ラグジュアリー', 'icon' => '💎', 'desc' => '高級志向向け'],
            'budget'     => ['name' => 'コスパ重視', 'icon' => '💰', 'desc' => '予算重視向け'],
        ];
    }

    /**
     * 旅の目的定義
     */
    public static function get_purposes() {
        return [
            'sightseeing' => ['name' => '観光・周遊', 'icon' => '🏯'],
            'onsen'       => ['name' => '温泉', 'icon' => '♨️'],
            'gourmet'     => ['name' => 'グルメ', 'icon' => '🍽️'],
            'anniversary' => ['name' => '記念日', 'icon' => '🎂'],
            'workation'   => ['name' => 'ワーケーション', 'icon' => '💻'],
            'healing'     => ['name' => '癒し・リラックス', 'icon' => '🧘'],
            'family'      => ['name' => '家族旅行', 'icon' => '👨‍👩‍👧'],
            'budget'      => ['name' => '節約旅行', 'icon' => '💴'],
        ];
    }

    /**
     * トーン定義
     */
    public static function get_tones() {
        return [
            'casual'       => ['name' => 'カジュアル', 'desc' => '親しみやすい語り口'],
            'luxury'       => ['name' => 'ラグジュアリー', 'desc' => '上質で洗練された表現'],
            'emotional'    => ['name' => 'エモーショナル', 'desc' => '感情に訴える表現'],
            'cinematic'    => ['name' => 'シネマティック', 'desc' => '映画的な描写'],
            'journalistic' => ['name' => 'ジャーナリスティック', 'desc' => '客観的で情報重視'],
        ];
    }

    /**
     * 構造定義
     */
    public static function get_structures() {
        return [
            'timeline'     => ['name' => 'タイムライン', 'desc' => '時系列で体験を追う'],
            'hero_journey' => ['name' => 'ヒーローズジャーニー', 'desc' => '物語形式'],
            'five_sense'   => ['name' => '五感構成', 'desc' => '五感で体験を伝える'],
            'dialogue'     => ['name' => '対話形式', 'desc' => '会話を交えた構成'],
            'review'       => ['name' => 'レビュー形式', 'desc' => '評価ポイント別'],
        ];
    }

    /**
     * C層コンテンツ要素
     */
    public static function get_content_items() {
        return [
            'cta'             => ['name' => 'CTA'],
            'affiliate_links' => ['name' => '予約リンク'],
            'price_info'      => ['name' => '価格情報'],
            'comparison'      => ['name' => '比較'],
            'faq'             => ['name' => 'FAQ'],
            'pros_cons'       => ['name' => 'メリット・デメリット'],
            'target_audience' => ['name' => 'ターゲット明示'],
            'seasonal_info'   => ['name' => '季節情報'],
            'access_info'     => ['name' => 'アクセス'],
            'reviews'         => ['name' => '口コミ'],
        ];
    }

    public static function get_default_contents() {
        return ['cta', 'price_info', 'access_info'];
    }

    /**
     * ペルソナ別デフォルト（PHP内部用）
     */
    public static function get_persona_defaults() {
        return [
            'general'   => [
                'depth'       => 'L2',
                'tone'        => 'casual',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E2',
                'volume'      => 'V2',
                'target'      => 'T2',
                'seo'         => 'SEO2',
                'reliability' => 'R2',
            ],
            'solo'      => [
                'depth'       => 'L2',
                'tone'        => 'casual',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E2',
                'volume'      => 'V2',
                'target'      => 'T2',
                'seo'         => 'SEO2',
                'reliability' => 'R2',
            ],
            'couple'    => [
                'depth'       => 'L3',
                'tone'        => 'emotional',
                'sensory'     => 'G3',
                'story'       => 'S3',
                'info'        => 'I2',
                'expression'  => 'E3',
                'volume'      => 'V3',
                'target'      => 'T3',
                'seo'         => 'SEO3',
                'reliability' => 'R3',
            ],
            'family'    => [
                'depth'       => 'L2',
                'tone'        => 'casual',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E2',
                'volume'      => 'V2',
                'target'      => 'T2',
                'seo'         => 'SEO2',
                'reliability' => 'R2',
            ],
            'senior'    => [
                'depth'       => 'L2',
                'tone'        => 'casual',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E2',
                'volume'      => 'V2',
                'target'      => 'T2',
                'seo'         => 'SEO2',
                'reliability' => 'R2',
            ],
            'workation' => [
                'depth'       => 'L1',
                'tone'        => 'journalistic',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E1',
                'volume'      => 'V1',
                'target'      => 'T1',
                'seo'         => 'SEO1',
                'reliability' => 'R2',
            ],
            'luxury'    => [
                'depth'       => 'L3',
                'tone'        => 'luxury',
                'sensory'     => 'G3',
                'story'       => 'S3',
                'info'        => 'I2',
                'expression'  => 'E3',
                'volume'      => 'V3',
                'target'      => 'T3',
                'seo'         => 'SEO3',
                'reliability' => 'R3',
            ],
            'budget'    => [
                'depth'       => 'L1',
                'tone'        => 'casual',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E1',
                'volume'      => 'V1',
                'target'      => 'T1',
                'seo'         => 'SEO1',
                'reliability' => 'R1',
            ],
        ];
    }

    /**
     * ペルソナと目的のマッピング
     */
    public static function get_persona_purpose_map() {
        return [
            'general'   => ['sightseeing', 'onsen', 'gourmet'],
            'solo'      => ['healing', 'onsen', 'sightseeing'],
            'couple'    => ['anniversary', 'onsen', 'gourmet'],
            'family'    => ['family', 'sightseeing', 'gourmet'],
            'senior'    => ['onsen', 'healing', 'sightseeing'],
            'workation' => ['workation', 'healing'],
            'luxury'    => ['anniversary', 'gourmet', 'healing'],
            'budget'    => ['budget', 'sightseeing'],
        ];
    }

    /**
     * 全体デフォルト設定
     */
    public static function get_default_settings() {
        return [
            'h' => [
                'persona' => 'general',
                'purpose' => ['sightseeing'],
                'depth'   => 'L2',
            ],
            'q' => [
                'tone'        => 'casual',
                'structure'   => 'timeline',
                'sensory'     => 'G1',
                'story'       => 'S1',
                'info'        => 'I1',
                'expression'  => 'E2',
                'volume'      => 'V2',
                'target'      => 'T2',
                'seo'         => 'SEO2',
                'reliability' => 'R2',
            ],
            'c' => [
                'commercial' => 'none',
                'experience' => 'recommend',
                'contents'   => self::get_default_contents(),
            ],
        ];
    }

    /**
     * JS用 persona → Q層プリセット変換
     */
    public static function get_persona_q_layer_presets_for_js() {
        $raw = self::get_persona_defaults();
        $presets = [];

        foreach ($raw as $persona => $data) {
            $presets[$persona] = [
                'q_layer' => [
                    'tone'        => $data['tone'],
                    'sensory'     => intval(substr($data['sensory'], 1)),
                    'story'       => intval(substr($data['story'], 1)),
                    'info'        => intval(substr($data['info'], 1)),
                    'expression'  => intval(substr($data['expression'], 1)),
                    'volume'      => intval(substr($data['volume'], 1)),
                    'target'      => intval(substr($data['target'], 1)),
                    'seo'         => intval(substr($data['seo'], 3)), // "SEO2" → 2
                    'reliability' => intval(substr($data['reliability'], 1)),
                ],
            ];
        }

        return $presets;
    }

    /**
     * サンプル導入文（リアルタイムプレビュー用）
     * 
     * キー形式: {persona}_{tone}_{sensory}_{story}
     * sensory / story は数値 or Gx/Sx どちらでも受け付ける
     */
    public static function get_sample_text($persona, $tone, $sensory, $story) {

        // 正規化
        if (is_numeric($sensory)) {
            $sensory = 'G' . intval($sensory);
        }
        if (is_numeric($story)) {
            $story = 'S' . intval($story);
        }

        $samples = [
            'couple_emotional_G3_S3' => '夕陽が水平線に溶けていく瞬間、二人だけの特別な時間が始まる。窓の外に広がる絶景を眺めながら、心が静かに満たされていく...',
            'couple_luxury_G3_S3' => '上質なリネンの香りに包まれて目覚める朝。窓の外には穏やかな海が広がり、二人だけの特別な一日が始まる。',
            'family_casual_G2_S2' => '子どもたちの歓声が響くプールサイド。「パパ、見て見て！」という声に振り向けば、初めての飛び込みに挑戦する姿。',
            'solo_cinematic_G3_S3' => '静寂に包まれた早朝のロビー。コーヒーの香りが漂う中、窓の向こうに広がる山々が朝日に染まっていく。',
            'workation_journalistic_G1_S1' => 'Wi-Fi環境も整い、仕事に集中できる環境が整っている。午前中は仕事、午後からは周辺観光へ。',
            'senior_casual_G2_S2' => 'ゆったりとした時間が流れる温泉宿。長年連れ添った二人で、静かに湯船に浸かる幸せ。',
            'luxury_luxury_G3_S3' => '一歩足を踏み入れた瞬間、日常から切り離された特別な空間が広がる。洗練された空気が全身を包み込む。',
            'budget_casual_G1_S1' => 'コスパ抜群！この価格でこのクオリティは正直驚き。必要なものは全て揃っています。',
            'default' => '旅の始まりは、いつも期待と発見に満ちている。このホテルで過ごす時間が、きっと忘れられない思い出になる。'
        ];

        $key = "{$persona}_{$tone}_{$sensory}_{$story}";
        return $samples[$key] ?? $samples['default'];
    }
}