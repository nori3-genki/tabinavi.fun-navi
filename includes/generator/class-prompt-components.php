<?php
/**
 * プロンプトコンポーネントクラス
 * 
 * スタイル、トーン、ポリシー、HQC詳細設定の適用
 * AIモデル別最適化
 * 
 * @package HRS
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Prompt_Components {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * OTA名称マッピング
     */
    private $ota_names = array(
        'rakuten'  => '楽天トラベル',
        'jalan'    => 'じゃらん',
        'ikyu'     => '一休.com',
        'relux'    => 'Relux',
        'booking'  => 'Booking.com',
        'yahoo'    => 'Yahoo!トラベル',
        'jtb'      => 'JTB',
        'rurubu'   => 'るるぶトラベル',
        'yukoyuko' => 'ゆこゆこ',
        'expedia'  => 'Expedia',
    );

    /**
     * スタイル適用
     */
    public function apply_style($style) {
        $styles = array(
            'story' => "【記事スタイル: ストーリー型】\n旅行記のように時系列で体験を描写し、読者が追体験できるような臨場感のある文章にしてください。\n\n",
            'guide' => "【記事スタイル: ガイド型】\n情報を整理して伝え、読者が予約判断しやすい実用的な内容にしてください。\n\n",
            'review' => "【記事スタイル: レビュー型】\n各項目を評価形式でまとめ、メリット・デメリットを客観的に伝えてください。\n\n",
            'emotional' => "【記事スタイル: エモーショナル型】\n感情に訴える表現を多用し、特別な体験として記憶に残る文章にしてください。\n\n",
            'five_sense' => "【記事スタイル: 五感描写型】\n視覚・聴覚・嗅覚・味覚・触覚の五感を使った臨場感ある描写を中心にしてください。\n\n",
        );

        return $styles[$style] ?? $styles['story'];
    }

    /**
     * トーン適用
     */
    public function apply_tone($tone) {
        $tones = array(
            'casual' => "【文体: カジュアル】\n親しみやすく、友人に話しかけるような軽やかな文体で書いてください。\n\n",
            'luxury' => "【文体: ラグジュアリー】\n上品で洗練された表現を使い、高級感のある文体で書いてください。\n\n",
            'emotional' => "【文体: エモーショナル】\n感情を揺さぶる表現を多用し、心に響く文体で書いてください。\n\n",
            'cinematic' => "【文体: シネマティック】\n映画のワンシーンのような描写で、視覚的にイメージが浮かぶ文体で書いてください。\n\n",
            'journalistic' => "【文体: ジャーナリスティック】\n客観的で信頼性のある、報道記事のような文体で書いてください。\n\n",
        );

        return $tones[$tone] ?? $tones['luxury'];
    }

    /**
     * ポリシー適用
     */
    public function apply_policy($policy) {
        $policies = array(
            'standard' => "【生成ポリシー: スタンダード】\n読みやすさと情報量のバランスを重視してください。\n\n",
            'seo' => "【生成ポリシー: SEO重視】\nキーワードを自然に盛り込み、検索エンジンに最適化された構成にしてください。見出しタグ(H2, H3)を適切に使用してください。\n\n",
            'conversion' => "【生成ポリシー: コンバージョン重視】\n予約や問い合わせに繋がるよう、魅力的なCTAを含め、行動を促す文章にしてください。\n\n",
            'viral' => "【生成ポリシー: バイラル重視】\nSNSでシェアされやすい、インパクトのある表現やキャッチーなフレーズを含めてください。\n\n",
            'balanced' => "【生成ポリシー: バランス重視】\n読みやすさ、SEO、コンバージョンのバランスを取った構成にしてください。\n\n",
        );

        return $policies[$policy] ?? $policies['standard'];
    }

    /**
     * スタイルレイヤー適用
     */
    public function apply_style_layers($layers) {
        if (empty($layers)) {
            return '';
        }

        $prompt = "【追加スタイルレイヤー】\n";
        
        $layer_descriptions = array(
            'seasonal'    => '季節感を演出する表現を追加',
            'regional'    => '地域の特色や文化を強調',
            'gourmet'     => '料理やグルメ情報を詳しく',
            'wellness'    => '癒しや健康への効果を強調',
            'adventure'   => 'アクティビティや冒険要素を追加',
            'historical'  => '歴史や伝統の深みを表現',
            'photogenic'  => 'フォトジェニックなスポットを紹介',
            'sustainable' => 'エコや持続可能性への取り組みを紹介',
        );

        foreach ($layers as $layer) {
            if (isset($layer_descriptions[$layer])) {
                $prompt .= "・{$layer_descriptions[$layer]}\n";
            }
        }

        $prompt .= "\n";
        return $prompt;
    }

    /**
     * HQC詳細設定適用（強化版）
     */
    public function apply_hqc_details($hqc) {
        $prompt = '';

        // 旅の目的
        if (!empty($hqc['h']['purpose'])) {
            $purposes = implode('、', $hqc['h']['purpose']);
            $prompt .= "【旅の目的】\n{$purposes}を目的とした旅行者向けの内容にしてください。\n\n";
        }

        // 体験深度（強化版）
        if (!empty($hqc['h']['depth'])) {
            $depth_map = array(
                'L1' => "基本的な情報を簡潔に伝える。事実ベースの記述を中心に、読者が素早く概要を把握できるようにする。",
                'L2' => "体験を時系列で追い、感情の動きを含めて描写する。「〜した」だけでなく「〜して○○を感じた」「〜した瞬間、○○という気持ちになった」まで踏み込む。具体的なシーンを3つ以上含める。",
                'L3' => "深い没入体験として詳細に描写。五感・感情・思考の変化を丁寧に追い、読者が完全に追体験できるレベルの臨場感を出す。",
            );
            $depth = $depth_map[$hqc['h']['depth']] ?? $depth_map['L2'];
            $prompt .= "【体験深度】\n{$depth}\n\n";
        }

        // ストーリー構成
        if (!empty($hqc['q']['structure'])) {
            $story_map = array(
                'timeline'     => "時系列に沿って、到着から出発までの流れを追う。各時間帯での体験を具体的に描写。",
                'hero_journey' => "主人公（読者）の変化の物語として構成。「日常→非日常→変容→帰還」の流れを意識。",
                'five_sense'   => "五感それぞれのセクションで構成。視覚・聴覚・嗅覚・味覚・触覚ごとに印象的な体験を描写。",
                'dialogue'     => "会話形式を多く取り入れ、スタッフや同行者とのやり取りを通じて魅力を伝える。",
            );
            if (isset($story_map[$hqc['q']['structure']])) {
                $prompt .= "【ストーリー構成】\n{$story_map[$hqc['q']['structure']]}\n\n";
            }
        }

        // 五感深度（強化版）
        if (!empty($hqc['q']['sensory'])) {
            $sensory_map = array(
                'G1' => "五感描写は最小限に抑え、事実情報を中心にする。",
                'G2' => "各セクションに最低1つの五感描写を含める。「○○の香りが」「○○の音が」「○○の手触りが」など具体的な感覚表現を使用。視覚だけでなく、聴覚・嗅覚・触覚・味覚もバランスよく含める。",
                'G3' => "五感をフルに使った臨場感ある描写を全編通じて展開。読者が実際にその場にいるかのような没入感を演出。各段落に複数の感覚描写を織り込む。",
            );
            $sensory = $sensory_map[$hqc['q']['sensory']] ?? $sensory_map['G2'];
            $prompt .= "【五感描写】\n{$sensory}\n\n";
        }

        // 情報量
        if (!empty($hqc['c']['info'])) {
            $info_map = array(
                'I1' => '簡潔で読みやすい短めの文章（1500〜2000文字目安）',
                'I2' => '標準的な情報量（2000〜3000文字目安）',
                'I3' => '詳細で網羅的な情報（3000〜4000文字目安）',
            );
            $info = $info_map[$hqc['c']['info']] ?? $info_map['I2'];
            $prompt .= "【情報量】\n{$info}にしてください。\n\n";
        }

        // 商業性レベル
        if (!empty($hqc['c']['commercial'])) {
            $commercial_map = array(
                'none'       => "商業的な表現は控えめに。純粋な体験記として書いてください。",
                'seo'        => "自然なCTAを含め、SEOを意識した構成にしてください。予約を促す表現は控えめに。",
                'conversion' => "予約や問い合わせに繋がるよう、積極的にCTAを含めてください。「予約はお早めに」「詳細をチェック」などの表現を使用。",
            );
            if (isset($commercial_map[$hqc['c']['commercial']])) {
                $prompt .= "【商業性】\n{$commercial_map[$hqc['c']['commercial']]}\n\n";
            }
        }

        // 体験表現
        if (!empty($hqc['c']['experience'])) {
            $experience_map = array(
                'record'    => "客観的な記録として、事実ベースで淡々と書いてください。",
                'recommend' => "おすすめとして紹介する形で、読者に薦めるニュアンスを含めてください。",
                'immersive' => "読者が実際に体験しているかのような没入感のある描写にしてください。「あなたは〜」という表現も可。",
            );
            if (isset($experience_map[$hqc['c']['experience']])) {
                $prompt .= "【体験表現】\n{$experience_map[$hqc['c']['experience']]}\n\n";
            }
        }

        return $prompt;
    }

    /**
     * AIモデル別最適化
     */
    public function optimize_for_model($prompt, $model) {
        switch ($model) {
            case 'claude':
                // Claude向け: XML構造を追加
                $prompt = "<instructions>\n" . $prompt . "</instructions>\n";
                $prompt .= "\n<output_rules>\n";
                $prompt .= "- HTMLタグで出力（Markdown禁止）\n";
                $prompt .= "- 見出しはH2, H3タグを使用\n";
                $prompt .= "- 段落はpタグで囲む\n";
                $prompt .= "</output_rules>\n";
                break;
                
            case 'gemini':
                // Gemini向け: マークダウン強調
                $prompt = "## 記事生成指示\n\n" . $prompt;
                $prompt .= "\n---\n";
                $prompt .= "### 重要な制約\n";
                $prompt .= "- 出力はHTMLタグのみ（Markdownは使用しない）\n";
                $prompt .= "- 見出しレベルを適切に使い分ける\n";
                break;
                
            case 'chatgpt':
            default:
                // ChatGPT向け: ルールブロックを追加
                $rules = "### ルール\n";
                $rules .= "1. 出力形式: HTML（Markdown禁止）\n";
                $rules .= "2. 見出し: <h2>, <h3>タグを使用（H2は最低6個）\n";
                $rules .= "3. 段落: <p>タグで囲む\n";
                $rules .= "4. リスト: <ul><li>または<ol><li>を使用\n";
                $rules .= "5. 強調: <strong>タグを使用（**ではない）\n";
                $rules .= "6. 改行: <br>タグまたは段落分けで対応\n";
                $rules .= "\n### 品質基準\n";
                $rules .= "- 読者の感情に訴える表現を使う\n";
                $rules .= "- 五感を刺激する描写を含める\n";
                $rules .= "- 具体的なシーンをイメージできる文章にする\n";
                $rules .= "\n---\n\n";
                $prompt = $rules . $prompt;
                break;
        }

        return $prompt;
    }

    /**
     * 出力指示
     */
    public function get_output_instructions($hotel_name = '') {
        $keyphrase_instruction = '';
        if (!empty($hotel_name)) {
            $keyphrase_instruction = "・キーフレーズ「{$hotel_name}」を自然に3〜5回含めてください\n";
        }
        
        return "【出力形式】\n" .
            "・文字数: 2000〜3000文字程度\n" .
            "・見出し: H2, H3タグを適切に使用（H2は6個以上）\n" .
            "・構成: 導入→本文（複数セクション）→まとめ\n" .
            "・HTMLタグを使用して出力してください（Markdown禁止）\n" .
            $keyphrase_instruction .
            "・冒頭の段落にもキーフレーズを含めてください\n" .
            "・予約リンクや公式サイトへのリンクは含めないでください（別途追加します）\n\n";
    }

    /**
     * 構造からスタイルへのマッピング
     */
    public function map_structure_to_style($structure) {
        $map = array(
            // 基本マッピング
            'timeline'     => 'story',
            'hero_journey' => 'emotional',
            'five_sense'   => 'five_sense',
            'dialogue'     => 'story',
            'review'       => 'review',
            
            // 拡張マッピング
            'guide'       => 'guide',
            'comparison'  => 'review',
            'ranking'     => 'guide',
            'qa'          => 'story',
            'interview'   => 'story',
            'documentary' => 'emotional',
            'cinematic'   => 'emotional',
            'seasonal'    => 'story',
            'experience'  => 'five_sense',
            'sensory'     => 'five_sense',
        );

        return $map[$structure] ?? 'story';
    }

    /**
     * OTA名称を取得
     */
    public function get_ota_name($ota_id) {
        return $this->ota_names[$ota_id] ?? $ota_id;
    }

    /**
     * 全OTA名称を取得
     */
    public function get_all_ota_names() {
        return $this->ota_names;
    }
}