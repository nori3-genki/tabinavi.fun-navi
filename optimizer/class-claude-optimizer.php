<?php
/**
 * Claude向け最適化クラス（HQC完全対応版）
 * 
 * Claude APIに最適化されたプロンプト生成・レスポンス処理
 * HQC Framework（H-Layer, Q-Layer, C-Layer）統合
 * XML構造プロンプトでClaude最適化
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Claude_Optimizer {

    /**
     * APIエンドポイント
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * APIバージョン
     */
    const API_VERSION = '2023-06-01';

    /**
     * デフォルトモデル
     */
    const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';

    /**
     * モデル設定
     */
    private $model_configs = array(
        'claude-3-5-sonnet-20241022' => array('max_tokens' => 8192, 'temperature' => 0.7),
        'claude-3-opus-20240229' => array('max_tokens' => 4096, 'temperature' => 0.7),
        'claude-3-haiku-20240307' => array('max_tokens' => 4096, 'temperature' => 0.7),
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
     * プロンプト最適化（HQC対応・XML構造）
     */
    public function optimize_prompt($base_prompt, $options = array()) {
        $hotel_name = $options['hotel_name'] ?? '';
        $hotel_data = $options['hotel_data'] ?? array();
        $hqc = $options['hqc'] ?? $this->hqc_settings;
        
        return $this->build_xml_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc);
    }

    /**
     * XML構造HQCプロンプト構築（Claude最適化）
     */
    private function build_xml_hqc_prompt($hotel_name, $hotel_data, $base_prompt, $hqc) {
        $prompt = "<task>\nホテルレビュー記事の作成\n</task>\n\n";
        
        // ホテル情報
        $prompt .= "<hotel_info>\n";
        $prompt .= "<name>{$hotel_name}</name>\n";
        if (!empty($hotel_data['address'])) {
            $prompt .= "<address>{$hotel_data['address']}</address>\n";
        }
        if (!empty($hotel_data['features'])) {
            $prompt .= "<features>" . implode('、', array_slice($hotel_data['features'], 0, 8)) . "</features>\n";
        }
        if (!empty($hotel_data['emotions'])) {
            $prompt .= "<atmosphere>" . implode('、', array_slice($hotel_data['emotions'], 0, 5)) . "</atmosphere>\n";
        }
        $prompt .= "</hotel_info>\n\n";
        
        // H-Layer
        $prompt .= $this->build_h_layer_xml($hqc['h'] ?? array());
        
        // Q-Layer
        $prompt .= $this->build_q_layer_xml($hqc['q'] ?? array());
        
        // C-Layer
        $prompt .= $this->build_c_layer_xml($hqc['c'] ?? array());
        
        // 追加指示
        if (!empty($base_prompt)) {
            $prompt .= "<additional_instructions>\n{$base_prompt}\n</additional_instructions>\n\n";
        }
        
        // 出力ルール
        $prompt .= $this->build_output_rules_xml($hotel_name, $hqc);
        
        return $prompt;
    }

    /**
     * H-Layer XML
     */
    private function build_h_layer_xml($h_layer) {
        $persona = $h_layer['persona'] ?? 'general';
        $persona_info = $this->get_persona_info($persona);
        $depth = $h_layer['depth'] ?? 2;
        
        $xml = "<h_layer>\n";
        $xml .= "<persona>{$persona_info['name']}（{$persona_info['description']}）</persona>\n";
        
        $purposes = $h_layer['purpose'] ?? array();
        if (!empty($purposes)) {
            $purpose_names = array_map(array($this, 'get_purpose_name'), $purposes);
            $xml .= "<travel_purpose>" . implode('、', $purpose_names) . "</travel_purpose>\n";
        }
        
        $xml .= "<experience_depth>L{$depth}（{$this->get_depth_description($depth)}）</experience_depth>\n";
        $xml .= "</h_layer>\n\n";
        
        return $xml;
    }

    /**
     * Q-Layer XML
     */
    private function build_q_layer_xml($q_layer) {
        $tone = $q_layer['tone'] ?? 'casual';
        $tone_info = $this->get_tone_info($tone);
        $structure = $q_layer['structure'] ?? 'timeline';
        $structure_info = $this->get_structure_info($structure);
        $sensory = $q_layer['sensory'] ?? 2;
        $story = $q_layer['story'] ?? 2;
        $info = $q_layer['info'] ?? 2;
        
        $xml = "<q_layer>\n";
        $xml .= "<tone>{$tone_info['name']}：{$tone_info['instruction']}</tone>\n";
        $xml .= "<structure>{$structure_info['name']}：{$structure_info['instruction']}</structure>\n";
        $xml .= "<sensory_depth>G{$sensory}（{$this->get_sensory_description($sensory)}）</sensory_depth>\n";
        $xml .= "<story_strength>S{$story}（{$this->get_story_description($story)}）</story_strength>\n";
        $xml .= "<info_level>I{$info}（{$this->get_info_description($info)}）</info_level>\n";
        $xml .= "</q_layer>\n\n";
        
        return $xml;
    }

    /**
     * C-Layer XML
     */
    private function build_c_layer_xml($c_layer) {
        $commercial = $c_layer['commercial'] ?? 'seo';
        $experience = $c_layer['experience'] ?? 'record';
        
        $xml = "<c_layer>\n";
        $xml .= "<commercial_focus>{$this->get_commercial_description($commercial)}</commercial_focus>\n";
        $xml .= "<experience_type>{$this->get_experience_description($experience)}</experience_type>\n";
        $xml .= "</c_layer>\n\n";
        
        return $xml;
    }

    /**
     * 出力ルールXML
     */
    private function build_output_rules_xml($hotel_name, $hqc) {
        $sensory = $hqc['q']['sensory'] ?? 2;
        $story = $hqc['q']['story'] ?? 2;
        
        $xml = "<output_rules>\n";
        $xml .= "- 出力形式: HTMLのみ（Markdown禁止）\n";
        $xml .= "- 見出し: <h2>タグを6個以上使用\n";
        $xml .= "- キーフレーズ「{$hotel_name}」を3〜5回自然に含める\n";
        $xml .= "- 冒頭段落に必ずキーフレーズを含める\n";
        $xml .= "- 文字数: 2000〜3000文字\n";
        
        if ($sensory >= 3) {
            $xml .= "- 五感すべて＋第六感（言葉にできない特別な感覚）を豊かに描写\n";
        } elseif ($sensory >= 2) {
            $xml .= "- 視覚・聴覚・嗅覚を中心に五感描写を含める\n";
        }
        
        if ($story >= 3) {
            $xml .= "- 読者を主人公として引き込む没入感ある文章\n";
        }
        
        $xml .= "</output_rules>\n\n";
        
        $xml .= "<output_format>\n";
        $xml .= "HTMLタグのみで構成された記事本文を出力。\n";
        $xml .= "余計な説明・前置き・コードブロック不要。\n";
        $xml .= "</output_format>";
        
        return $xml;
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
        $api_key = get_option('hrs_claude_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Claude APIキーが設定されていません');
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
                'x-api-key' => $api_key,
                'anthropic-version' => self::API_VERSION,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'max_tokens' => $max_tokens,
                'system' => $this->get_system_prompt($hqc),
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
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
        
        $base = "あなたはプロのホテルレビューライターです。読者を旅の世界に引き込む文章を作成します。";
        
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
        $content = '';
        
        if (!empty($response['content'])) {
            foreach ($response['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $content = $this->clean_output($content);
        $usage = $response['usage'] ?? array();

        return array(
            'content' => $content,
            'usage' => array(
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
            ),
            'model' => $response['model'] ?? '',
            'stop_reason' => $response['stop_reason'] ?? '',
        );
    }

    /**
     * 出力クリーニング
     */
    private function clean_output($content) {
        // XMLタグ除去
        $content = preg_replace('/<\/?task>/', '', $content);
        $content = preg_replace('/<\/?output_rules>/', '', $content);
        $content = preg_replace('/<\/?output_format>/', '', $content);
        
        // コードブロック除去
        $content = preg_replace('/```html?\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        
        return trim($content);
    }

    /**
     * トークン数推定
     */
    public function estimate_tokens($text) {
        $jp_chars = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text);
        $en_words = str_word_count(preg_replace('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', ' ', $text));
        return (int)($jp_chars / 1.2 + $en_words);
    }

    /**
     * コスト推定
     */
    public function estimate_cost($input_tokens, $output_tokens, $model = null) {
        $costs = array(
            'claude-3-5-sonnet-20241022' => array('input' => 0.003, 'output' => 0.015),
            'claude-3-opus-20240229' => array('input' => 0.015, 'output' => 0.075),
            'claude-3-haiku-20240307' => array('input' => 0.00025, 'output' => 0.00125),
        );
        $rate = $costs[$model ?? self::DEFAULT_MODEL] ?? $costs['claude-3-5-sonnet-20241022'];
        return ($input_tokens / 1000) * $rate['input'] + ($output_tokens / 1000) * $rate['output'];
    }

    /**
     * 接続テスト
     */
    public function test_connection() {
        $api_key = get_option('hrs_claude_api_key', '');
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'APIキー未設定');
        }

        $result = $this->call_api('ping', self::DEFAULT_MODEL, array('max_tokens' => 10));
        if (is_wp_error($result)) {
            return array('success' => false, 'message' => $result->get_error_message());
        }
        return array('success' => true, 'message' => '✅ Claude API 接続成功', 'model' => $result['model']);
    }

    public function get_hqc_settings() { return $this->hqc_settings; }
    public function set_hqc_settings($settings) { $this->hqc_settings = $settings; }
}