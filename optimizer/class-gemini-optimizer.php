<?php
/**
 * Gemini向け最適化クラス（HQC完全対応版）
 * 
 * Google Gemini APIに最適化されたプロンプト生成・レスポンス処理
 * HQC Framework（H-Layer, Q-Layer, C-Layer）統合
 * マークダウン強調形式でGemini最適化
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Gemini_Optimizer {

    /**
     * APIエンドポイントベース
     */
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * デフォルトモデル
     */
    const DEFAULT_MODEL = 'gemini-1.5-flash';

    /**
     * モデル設定
     */
    private $model_configs = array(
        'gemini-1.5-flash' => array('max_output_tokens' => 8192, 'temperature' => 0.7, 'top_p' => 0.95),
        'gemini-1.5-pro' => array('max_output_tokens' => 8192, 'temperature' => 0.7, 'top_p' => 0.95),
        'gemini-pro' => array('max_output_tokens' => 4096, 'temperature' => 0.7, 'top_p' => 0.95),
    );

    /**
     * HQC設定
     */
    private $hqc_settings = null;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->load_hqc_settings();
    }

    /**
     * HQC設定読み込み
     */
    private function load_hqc_settings() {
        $this->hqc_settings = get_option('hrs_hqc_settings', array());
        
        if (empty($this->hqc_settings)) {
            $this->hqc_settings = array(
                'h' => array('persona' => 'general', 'purpose' => array('sightseeing'), 'depth' => 2),
                'q' => array('tone' => 'casual', 'structure' => 'timeline', 'sensory' => 2, 'story' => 2, 'info' => 2),
                'c' => array('commercial' => 'seo', 'experience' => 'record'),
            );
        }
    }

    /**
     * プロンプト最適化（HQC対応・マークダウン強調）
     */
    public function optimize_prompt($base_prompt, $options = array()) {
        $hotel_name = $options['hotel_name'] ?? '';
        $hotel_data = $options['hotel_data'] ?? array();
        $hqc = $options['hqc'] ?? $this->hqc_settings;
        
        return $this->build_markdown_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc);
    }

    /**
     * マークダウン強調HQCプロンプト構築（Gemini最適化）
     */
    private function build_markdown_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc) {
        $prompt = "# タスク: ホテルレビュー記事の作成\n\n";
        
        // ホテル情報
        $prompt .= "## ホテル情報\n";
        $prompt .= "- **ホテル名**: {$hotel_name}\n";
        if (!empty($hotel_data['address'])) {
            $prompt .= "- **所在地**: {$hotel_data['address']}\n";
        }
        if (!empty($hotel_data['features'])) {
            $prompt .= "- **特徴**: " . implode('、', array_slice($hotel_data['features'], 0, 8)) . "\n";
        }
        if (!empty($hotel_data['emotions'])) {
            $prompt .= "- **雰囲気**: " . implode('、', array_slice($hotel_data['emotions'], 0, 5)) . "\n";
        }
        $prompt .= "\n";
        
        // H-Layer
        $prompt .= $this->build_h_layer_markdown($hqc['h'] ?? array());
        
        // Q-Layer
        $prompt .= $this->build_q_layer_markdown($hqc['q'] ?? array());
        
        // C-Layer
        $prompt .= $this->build_c_layer_markdown($hqc['c'] ?? array());
        
        // 追加指示
        if (!empty($base_prompt)) {
            $prompt .= "## 追加指示\n{$base_prompt}\n\n";
        }
        
        // 重要な制約
        $prompt .= $this->build_constraints_markdown($hotel_name, $hqc);
        
        return $prompt;
    }

    /**
     * H-Layer マークダウン
     */
    private function build_h_layer_markdown($h_layer) {
        $persona = $h_layer['persona'] ?? 'general';
        $persona_info = $this->get_persona_info($persona);
        $depth = $h_layer['depth'] ?? 2;
        
        $md = "## H-Layer（読者ペルソナ設定）\n";
        $md .= "- **ターゲット読者**: {$persona_info['name']}（{$persona_info['description']}）\n";
        
        $purposes = $h_layer['purpose'] ?? array();
        if (!empty($purposes)) {
            $purpose_names = array_map(array($this, 'get_purpose_name'), $purposes);
            $md .= "- **旅の目的**: " . implode('、', $purpose_names) . "\n";
        }
        
        $md .= "- **体験深度**: L{$depth}（{$this->get_depth_description($depth)}）\n\n";
        
        return $md;
    }

    /**
     * Q-Layer マークダウン
     */
    private function build_q_layer_markdown($q_layer) {
        $tone = $q_layer['tone'] ?? 'casual';
        $tone_info = $this->get_tone_info($tone);
        $structure = $q_layer['structure'] ?? 'timeline';
        $structure_info = $this->get_structure_info($structure);
        $sensory = $q_layer['sensory'] ?? 2;
        $story = $q_layer['story'] ?? 2;
        $info = $q_layer['info'] ?? 2;
        
        $md = "## Q-Layer（品質・スタイル設定）\n";
        $md .= "- **文章トーン**: {$tone_info['name']}（{$tone_info['instruction']}）\n";
        $md .= "- **記事構造**: {$structure_info['name']}（{$structure_info['instruction']}）\n";
        $md .= "- **五感描写**: G{$sensory}（{$this->get_sensory_description($sensory)}）\n";
        $md .= "- **物語性**: S{$story}（{$this->get_story_description($story)}）\n";
        $md .= "- **情報量**: I{$info}（{$this->get_info_description($info)}）\n\n";
        
        return $md;
    }

    /**
     * C-Layer マークダウン
     */
    private function build_c_layer_markdown($c_layer) {
        $commercial = $c_layer['commercial'] ?? 'seo';
        $experience = $c_layer['experience'] ?? 'record';
        
        $md = "## C-Layer（コンテンツ方針）\n";
        $md .= "- **商業方針**: {$this->get_commercial_description($commercial)}\n";
        $md .= "- **体験タイプ**: {$this->get_experience_description($experience)}\n\n";
        
        return $md;
    }

    /**
     * 制約マークダウン
     */
    private function build_constraints_markdown($hotel_name, $hqc) {
        $sensory = $hqc['q']['sensory'] ?? 2;
        $story = $hqc['q']['story'] ?? 2;
        
        $md = "## ⚠️ 重要な制約（必ず守ること）\n";
        $md .= "1. **出力形式**: HTMLタグのみ使用（Markdownの#や*は絶対禁止）\n";
        $md .= "2. **見出し**: `<h2>`タグを6個以上使用\n";
        $md .= "3. **キーフレーズ**: 「{$hotel_name}」を3〜5回自然に含める\n";
        $md .= "4. **冒頭**: 最初の`<p>`にキーフレーズを含める\n";
        $md .= "5. **文字数**: 2000〜3000文字\n";
        $md .= "6. **HTML構造**: `<p>`, `<h2>`, `<h3>`, `<ul>`, `<li>`を適切に使用\n";
        
        if ($sensory >= 3) {
            $md .= "7. **五感描写**: 五感すべて＋第六感（言葉にできない特別な感覚）を豊かに描写\n";
        } elseif ($sensory >= 2) {
            $md .= "7. **五感描写**: 視覚・聴覚・嗅覚を中心に感覚描写を含める\n";
        }
        
        if ($story >= 3) {
            $md .= "8. **物語性**: 読者を主人公として引き込む没入感ある文章\n";
        }
        
        $md .= "\n## 出力\n";
        $md .= "HTMLタグで構成された記事本文のみを出力。説明・前置き・コードブロック不要。\n";
        
        return $md;
    }

    /**
     * ペルソナ情報
     */
    private function get_persona_info($persona) {
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
     * 目的名
     */
    private function get_purpose_name($purpose) {
        $purposes = array(
            'sightseeing' => '観光', 'onsen' => '温泉', 'gourmet' => 'グルメ',
            'anniversary' => '記念日', 'workation' => 'ワーケーション',
            'healing' => '癒し', 'family' => '家族旅行', 'budget' => '節約旅',
        );
        return $purposes[$purpose] ?? $purpose;
    }

    /**
     * 体験深度説明
     */
    private function get_depth_description($depth) {
        $desc = array(1 => '基本情報中心', 2 => '標準的な体験描写', 3 => '深い没入体験');
        return $desc[$depth] ?? $desc[2];
    }

    /**
     * トーン情報
     */
    private function get_tone_info($tone) {
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
     * 構造情報
     */
    private function get_structure_info($structure) {
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
     * 五感深度説明
     */
    private function get_sensory_description($level) {
        $desc = array(1 => '最小限', 2 => '主要感覚を適度に', 3 => '五感＋第六感フル活用');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 物語強度説明
     */
    private function get_story_description($level) {
        $desc = array(1 => '事実ベース', 2 => '適度なストーリー性', 3 => '強い物語性');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 情報量説明
     */
    private function get_info_description($level) {
        $desc = array(1 => '簡潔', 2 => '標準', 3 => '詳細');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 商業重点説明
     */
    private function get_commercial_description($commercial) {
        $desc = array(
            'none' => 'コンテンツ重視',
            'seo' => 'SEO最適化重視',
            'conversion' => '予約促進重視',
        );
        return $desc[$commercial] ?? $desc['seo'];
    }

    /**
     * 体験タイプ説明
     */
    private function get_experience_description($experience) {
        $desc = array(
            'record' => '記録型（客観的）',
            'immersive' => '没入型（体験引込み）',
            'drama' => 'ドラマ型（感動ストーリー）',
        );
        return $desc[$experience] ?? $desc['record'];
    }

    /**
     * API呼び出し
     */
    public function call_api($prompt, $model = null, $options = array()) {
        $api_key = get_option('hrs_gemini_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Gemini APIキーが設定されていません');
        }

        $model = $model ?? self::DEFAULT_MODEL;
        $config = $this->model_configs[$model] ?? $this->model_configs[self::DEFAULT_MODEL];

        $max_output_tokens = $options['max_output_tokens'] ?? $config['max_output_tokens'];
        $temperature = $options['temperature'] ?? $config['temperature'];

        $hqc = $options['hqc'] ?? $this->hqc_settings;
        if (($hqc['q']['story'] ?? 2) >= 3) {
            $temperature = min(0.9, $temperature + 0.1);
        }

        $endpoint = self::API_BASE . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post($endpoint, array(
            'timeout' => 120,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => $this->get_system_prompt($hqc) . "\n\n" . $prompt)
                        )
                    )
                ),
                'generationConfig' => array(
                    'maxOutputTokens' => $max_output_tokens,
                    'temperature' => $temperature,
                    'topP' => $config['top_p'],
                ),
                'safetySettings' => $this->get_safety_settings(),
            )),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error('api_error', "API Error ({$code}): " . ($body['error']['message'] ?? 'Unknown'));
        }

        return $this->process_response($body);
    }

    /**
     * システムプロンプト（HQC対応）
     */
    private function get_system_prompt($hqc) {
        $tone = $hqc['q']['tone'] ?? 'casual';
        $sensory = $hqc['q']['sensory'] ?? 2;
        
        $base = "あなたはプロのホテルレビューライターです。読者が行きたくなる魅力的な記事を書きます。";
        
        if ($sensory >= 3) {
            $base .= "五感＋第六感（言葉にできない特別な感覚）の豊かな描写を得意とします。";
        }
        
        $tone_additions = array(
            'luxury' => "上品で洗練された高級感ある表現を使います。",
            'emotional' => "読者の心に響く感情豊かな文章を紡ぎます。",
            'cinematic' => "映画のワンシーンのような描写を得意とします。",
        );
        
        if (isset($tone_additions[$tone])) {
            $base .= $tone_additions[$tone];
        }
        
        $base .= "出力は常にHTMLタグのみ。Markdownは絶対に使用しません。";
        
        return $base;
    }

    /**
     * 安全性設定
     */
    private function get_safety_settings() {
        return array(
            array('category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'),
            array('category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'),
            array('category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'),
            array('category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'),
        );
    }

    /**
     * レスポンス処理
     */
    private function process_response($response) {
        $content = '';
        
        if (!empty($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }

        $content = $this->clean_output($content);
        $usage = $response['usageMetadata'] ?? array();

        return array(
            'content' => $content,
            'usage' => array(
                'prompt_tokens' => $usage['promptTokenCount'] ?? 0,
                'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usage['totalTokenCount'] ?? 0,
            ),
            'model' => $response['modelVersion'] ?? '',
            'finish_reason' => $response['candidates'][0]['finishReason'] ?? '',
        );
    }

    /**
     * 出力クリーニング
     */
    private function clean_output($content) {
        $content = preg_replace('/```html?\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        return trim($content);
    }

    /**
     * トークン数推定
     */
    public function estimate_tokens($text) {
        return (int)(mb_strlen($text) * 0.8);
    }

    /**
     * コスト推定
     */
    public function estimate_cost($input_tokens, $output_tokens, $model = null) {
        $costs = array(
            'gemini-1.5-flash' => array('input' => 0.000075, 'output' => 0.0003),
            'gemini-1.5-pro' => array('input' => 0.00125, 'output' => 0.005),
            'gemini-pro' => array('input' => 0.000125, 'output' => 0.000375),
        );
        $rate = $costs[$model ?? self::DEFAULT_MODEL] ?? $costs['gemini-1.5-flash'];
        return ($input_tokens / 1000) * $rate['input'] + ($output_tokens / 1000) * $rate['output'];
    }

    /**
     * 接続テスト
     */
    public function test_connection() {
        $api_key = get_option('hrs_gemini_api_key', '');
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'APIキー未設定');
        }

        $result = $this->call_api('ping', self::DEFAULT_MODEL, array('max_output_tokens' => 10));
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        return array('success' => true, 'message' => '✅ Gemini API 接続成功', 'model' => $result['model']);
    }

    public function get_hqc_settings() { return $this->hqc_settings; }
    public function set_hqc_settings($settings) { $this->hqc_settings = $settings; }
}