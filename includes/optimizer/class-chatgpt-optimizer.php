<?php
/**
 * ChatGPT向け最適化クラス（HQC完全対応版）
 * 
 * ChatGPT APIに最適化されたプロンプト生成・レスポンス処理
 * HQC Framework（H-Layer, Q-Layer, C-Layer）統合
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_ChatGPT_Optimizer {

    /**
     * APIエンドポイント
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * デフォルトモデル
     */
    const DEFAULT_MODEL = 'gpt-4o-mini';

    /**
     * モデル設定
     */
    private $model_configs = array(
        'gpt-4o-mini' => array(
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'top_p' => 1.0,
        ),
        'gpt-4o' => array(
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'top_p' => 1.0,
        ),
        'gpt-4-turbo' => array(
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'top_p' => 1.0,
        ),
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
            $this->hqc_settings = $this->get_default_hqc_settings();
        }
    }

    /**
     * デフォルトHQC設定
     */
    private function get_default_hqc_settings() {
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
     * プロンプト最適化（HQC対応）
     */
    public function optimize_prompt($base_prompt, $options = array()) {
        $hotel_name = $options['hotel_name'] ?? '';
        $hotel_data = $options['hotel_data'] ?? array();
        $hqc = $options['hqc'] ?? $this->hqc_settings;
        
        return $this->build_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc);
    }

    /**
     * HQC対応プロンプト構築
     */
    private function build_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc) {
        $prompt = "";
        
        $prompt .= $this->build_hotel_info_section($hotel_name, $hotel_data);
        $prompt .= $this->build_h_layer_section($hqc['h'] ?? array());
        $prompt .= $this->build_q_layer_section($hqc['q'] ?? array());
        $prompt .= $this->build_c_layer_section($hqc['c'] ?? array());
        
        if (!empty($base_prompt)) {
            $prompt .= "【追加指示】\n{$base_prompt}\n\n";
        }
        
        $prompt .= $this->build_rules_block($hotel_name);
        $prompt .= $this->build_quality_block($hqc);
        
        return $prompt;
    }

    /**
     * ホテル情報セクション
     */
    private function build_hotel_info_section($hotel_name, $hotel_data) {
        $section = "【ホテル情報】\n";
        $section .= "ホテル名: {$hotel_name}\n";
        
        if (!empty($hotel_data['address'])) {
            $section .= "所在地: {$hotel_data['address']}\n";
        }
        
        if (!empty($hotel_data['features'])) {
            $features = implode('、', array_slice($hotel_data['features'], 0, 8));
            $section .= "特徴: {$features}\n";
        }
        
        if (!empty($hotel_data['emotions'])) {
            $emotions = implode('、', array_slice($hotel_data['emotions'], 0, 5));
            $section .= "雰囲気: {$emotions}\n";
        }
        
        $section .= "\n";
        return $section;
    }

    /**
     * H-Layer（Human Layer）セクション
     */
    private function build_h_layer_section($h_layer) {
        $section = "【H-Layer: 読者ペルソナ設定】\n";
        
        $persona = $h_layer['persona'] ?? 'general';
        $persona_info = $this->get_persona_info($persona);
        $section .= "ターゲット読者: {$persona_info['name']}（{$persona_info['description']}）\n";
        
        $purposes = $h_layer['purpose'] ?? array();
        if (!empty($purposes)) {
            $purpose_names = array_map(array($this, 'get_purpose_name'), $purposes);
            $section .= "想定する旅の目的: " . implode('、', $purpose_names) . "\n";
        }
        
        $depth = $h_layer['depth'] ?? 2;
        $depth_desc = $this->get_depth_description($depth);
        $section .= "体験深度: L{$depth}（{$depth_desc}）\n\n";
        
        return $section;
    }

    /**
     * Q-Layer（Quality Layer）セクション
     */
    private function build_q_layer_section($q_layer) {
        $section = "【Q-Layer: 品質・スタイル設定】\n";
        
        $tone = $q_layer['tone'] ?? 'casual';
        $tone_info = $this->get_tone_info($tone);
        $section .= "文章トーン: {$tone_info['name']}（{$tone_info['instruction']}）\n";
        
        $structure = $q_layer['structure'] ?? 'timeline';
        $structure_info = $this->get_structure_info($structure);
        $section .= "記事構造: {$structure_info['name']}（{$structure_info['instruction']}）\n";
        
        $sensory = $q_layer['sensory'] ?? 2;
        $section .= "五感描写: G{$sensory}（{$this->get_sensory_description($sensory)}）\n";
        
        $story = $q_layer['story'] ?? 2;
        $section .= "物語性: S{$story}（{$this->get_story_description($story)}）\n";
        
        $info = $q_layer['info'] ?? 2;
        $section .= "情報量: I{$info}（{$this->get_info_description($info)}）\n\n";
        
        return $section;
    }

    /**
     * C-Layer（Content Layer）セクション
     */
    private function build_c_layer_section($c_layer) {
        $section = "【C-Layer: コンテンツ方針】\n";
        
        $commercial = $c_layer['commercial'] ?? 'seo';
        $section .= "商業方針: {$this->get_commercial_description($commercial)}\n";
        
        $experience = $c_layer['experience'] ?? 'record';
        $section .= "体験タイプ: {$this->get_experience_description($experience)}\n\n";
        
        return $section;
    }

    /**
     * ルールブロック
     */
    private function build_rules_block($hotel_name) {
        $rules = "【絶対遵守ルール】\n";
        $rules .= "1. 出力形式: HTMLのみ（Markdownの#や*は絶対禁止）\n";
        $rules .= "2. 見出し: <h2>タグを6個以上使用\n";
        $rules .= "3. キーフレーズ: 「{$hotel_name}」を3〜5回自然に含める\n";
        $rules .= "4. 冒頭: 最初の<p>にキーフレーズを含める\n";
        $rules .= "5. 文字数: 2000〜3000文字\n";
        $rules .= "6. コードブロック（```）禁止\n\n";
        
        return $rules;
    }

    /**
     * 品質基準ブロック（HQC対応）
     */
    private function build_quality_block($hqc) {
        $quality = "【品質基準】\n";
        
        $sensory = $hqc['q']['sensory'] ?? 2;
        $story = $hqc['q']['story'] ?? 2;
        
        if ($sensory >= 3) {
            $quality .= "- 五感すべて＋第六感（言葉にできない特別な感覚）を豊かに描写\n";
        } elseif ($sensory >= 2) {
            $quality .= "- 視覚・聴覚・嗅覚を中心に五感描写を含める\n";
        }
        
        if ($story >= 3) {
            $quality .= "- 読者を主人公として引き込む没入感ある文章\n";
        } elseif ($story >= 2) {
            $quality .= "- 適度なストーリー性を持たせる\n";
        }
        
        $quality .= "- 読者が訪れたくなる魅力的な文章\n";
        
        return $quality;
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
        $desc = array(1 => '最小限の感覚描写', 2 => '主要な感覚を適度に描写', 3 => '五感＋第六感フル活用');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 物語強度説明
     */
    private function get_story_description($level) {
        $desc = array(1 => '事実ベース簡潔', 2 => '適度なストーリー性', 3 => '強い物語性と感情の起伏');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 情報量説明
     */
    private function get_info_description($level) {
        $desc = array(1 => '簡潔・要点のみ', 2 => '標準的な情報量', 3 => '詳細・網羅的');
        return $desc[$level] ?? $desc[2];
    }

    /**
     * 商業重点説明
     */
    private function get_commercial_description($commercial) {
        $desc = array(
            'none' => '商業性なし（コンテンツ重視）',
            'seo' => 'SEO重視（検索最適化）',
            'conversion' => 'コンバージョン重視（予約促進）',
        );
        return $desc[$commercial] ?? $desc['seo'];
    }

    /**
     * 体験タイプ説明
     */
    private function get_experience_description($experience) {
        $desc = array(
            'record' => '記録型（客観的情報提供）',
            'immersive' => '没入型（体験に引き込む）',
            'drama' => 'ドラマ型（感動的ストーリー）',
        );
        return $desc[$experience] ?? $desc['record'];
    }

    /**
     * API呼び出し
     */
    public function call_api($prompt, $model = null, $options = array()) {
        $api_key = get_option('hrs_chatgpt_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'ChatGPT APIキーが設定されていません');
        }

        $model = $model ?? self::DEFAULT_MODEL;
        $config = $this->model_configs[$model] ?? $this->model_configs[self::DEFAULT_MODEL];

        $max_tokens = $options['max_tokens'] ?? $config['max_tokens'];
        $temperature = $options['temperature'] ?? $config['temperature'];

        $hqc = $options['hqc'] ?? $this->hqc_settings;
        if (($hqc['q']['story'] ?? 2) >= 3) {
            $temperature = min(0.9, $temperature + 0.1);
        }

        $response = wp_remote_post(self::API_ENDPOINT, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => $this->get_system_prompt($hqc)),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
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
        
        $base = "あなたはプロのホテルレビューライターです。";
        
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
        
        $base .= "出力は常にHTMLタグのみ。Markdown禁止。";
        
        return $base;
    }

    /**
     * レスポンス処理
     */
    private function process_response($response) {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $content = $this->clean_markdown($content);
        $usage = $response['usage'] ?? array();

        return array(
            'content' => $content,
            'usage' => array(
                'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ),
            'model' => $response['model'] ?? '',
        );
    }

    /**
     * Markdownクリーニング
     */
    private function clean_markdown($content) {
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
        $jp_chars = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text);
        $en_words = str_word_count(preg_replace('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', ' ', $text));
        return (int)($jp_chars / 1.5 + $en_words);
    }

    /**
     * コスト推定
     */
    public function estimate_cost($tokens, $model = null) {
        $costs = array('gpt-4o-mini' => 0.00015, 'gpt-4o' => 0.005, 'gpt-4-turbo' => 0.01);
        return ($tokens / 1000) * ($costs[$model ?? self::DEFAULT_MODEL] ?? 0.00015);
    }

    /**
     * 接続テスト
     */
    public function test_connection() {
        $result = $this->call_api('ping', self::DEFAULT_MODEL, array('max_tokens' => 5));
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        return array('success' => true, 'message' => '✅ ChatGPT API 接続成功', 'model' => $result['model']);
    }

    public function get_hqc_settings() { return $this->hqc_settings; }
    public function set_hqc_settings($settings) { $this->hqc_settings = $settings; }
}