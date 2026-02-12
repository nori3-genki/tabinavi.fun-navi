<?php
/**
 * AI Optimizer 基底クラス（HQC対応版）
 * 
 * ChatGPT/Claude/Gemini Optimizer の共通基底クラス
 * HQC Framework 共通処理を提供
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class HRS_Base_Optimizer {

    /**
     * HQC設定
     */
    protected $hqc_settings = null;

    /**
     * デフォルトHQC設定
     */
    protected $default_hqc = array(
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

    /**
     * ペルソナ定義
     */
    protected $personas = array(
        'general' => array('name' => '一般旅行者', 'description' => '幅広い読者層'),
        'solo' => array('name' => '一人旅', 'description' => '自由な旅を求める人'),
        'couple' => array('name' => 'カップル・夫婦', 'description' => '二人の特別な時間'),
        'family' => array('name' => 'ファミリー', 'description' => '子連れ家族'),
        'senior' => array('name' => 'シニア', 'description' => 'ゆったり快適な滞在'),
        'workation' => array('name' => 'ワーケーション', 'description' => '仕事と休暇の両立'),
        'luxury' => array('name' => 'ラグジュアリー', 'description' => '最高のおもてなし'),
        'budget' => array('name' => '節約志向', 'description' => 'コスパ重視'),
    );

    /**
     * 旅の目的定義
     */
    protected $purposes = array(
        'sightseeing' => '観光',
        'onsen' => '温泉',
        'gourmet' => 'グルメ',
        'anniversary' => '記念日',
        'workation' => 'ワーケーション',
        'healing' => '癒し',
        'family' => '家族旅行',
        'budget' => '節約旅',
    );

    /**
     * トーン定義
     */
    protected $tones = array(
        'casual' => array('name' => 'カジュアル', 'instruction' => '親しみやすい温かみのある文体'),
        'luxury' => array('name' => 'ラグジュアリー', 'instruction' => '上品で洗練された高級感ある文体'),
        'emotional' => array('name' => 'エモーショナル', 'instruction' => '心に響く情緒的な文体'),
        'cinematic' => array('name' => '映画的', 'instruction' => '映像が浮かぶドラマチックな描写'),
        'journalistic' => array('name' => '報道的', 'instruction' => '客観的で信頼性の高い文体'),
    );

    /**
     * 構造定義
     */
    protected $structures = array(
        'timeline' => array('name' => '時系列', 'instruction' => 'チェックインから時間順に紹介'),
        'hero_journey' => array('name' => '物語構造', 'instruction' => '出発→体験→感動→帰還の物語形式'),
        'five_sense' => array('name' => '五感描写', 'instruction' => '視覚・聴覚・嗅覚・味覚・触覚で構成'),
        'dialogue' => array('name' => '対話形式', 'instruction' => '会話を交えた親しみやすい構成'),
        'review' => array('name' => 'レビュー', 'instruction' => '評価ポイントごとに整理'),
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->load_hqc_settings();
    }

    /**
     * HQC設定読み込み
     */
    protected function load_hqc_settings() {
        $this->hqc_settings = get_option('hrs_hqc_settings', array());
        
        if (empty($this->hqc_settings)) {
            $this->hqc_settings = $this->default_hqc;
        }
    }

    /**
     * プロンプト最適化（サブクラスで実装）
     * 
     * @param string $base_prompt 基本プロンプト
     * @param array $options オプション
     * @return string 最適化されたプロンプト
     */
    abstract public function optimize_prompt($base_prompt, $options = array());

    /**
     * API呼び出し（サブクラスで実装）
     * 
     * @param string $prompt プロンプト
     * @param string $model モデル名
     * @param array $options 追加オプション
     * @return array|WP_Error
     */
    abstract public function call_api($prompt, $model = null, $options = array());

    /**
     * 接続テスト（サブクラスで実装）
     * 
     * @return array
     */
    abstract public function test_connection();

    /**
     * ペルソナ情報を取得
     * 
     * @param string $persona
     * @return array
     */
    protected function get_persona_info($persona) {
        return $this->personas[$persona] ?? $this->personas['general'];
    }

    /**
     * 目的名を取得
     * 
     * @param string $purpose
     * @return string
     */
    protected function get_purpose_name($purpose) {
        return $this->purposes[$purpose] ?? $purpose;
    }

    /**
     * トーン情報を取得
     * 
     * @param string $tone
     * @return array
     */
    protected function get_tone_info($tone) {
        return $this->tones[$tone] ?? $this->tones['casual'];
    }

    /**
     * 構造情報を取得
     * 
     * @param string $structure
     * @return array
     */
    protected function get_structure_info($structure) {
        return $this->structures[$structure] ?? $this->structures['timeline'];
    }

    /**
     * 体験深度説明を取得
     * 
     * @param int $depth
     * @return string
     */
    protected function get_depth_description($depth) {
        $descriptions = array(
            1 => '基本情報中心',
            2 => '標準的な体験描写',
            3 => '深い没入体験',
        );
        return $descriptions[$depth] ?? $descriptions[2];
    }

    /**
     * 五感深度説明を取得
     * 
     * @param int $level
     * @return string
     */
    protected function get_sensory_description($level) {
        $descriptions = array(
            1 => '最小限の感覚描写',
            2 => '主要な感覚を適度に描写',
            3 => '五感＋第六感フル活用',
        );
        return $descriptions[$level] ?? $descriptions[2];
    }

    /**
     * 物語強度説明を取得
     * 
     * @param int $level
     * @return string
     */
    protected function get_story_description($level) {
        $descriptions = array(
            1 => '事実ベース簡潔',
            2 => '適度なストーリー性',
            3 => '強い物語性と感情の起伏',
        );
        return $descriptions[$level] ?? $descriptions[2];
    }

    /**
     * 情報量説明を取得
     * 
     * @param int $level
     * @return string
     */
    protected function get_info_description($level) {
        $descriptions = array(
            1 => '簡潔・要点のみ',
            2 => '標準的な情報量',
            3 => '詳細・網羅的',
        );
        return $descriptions[$level] ?? $descriptions[2];
    }

    /**
     * 商業重点説明を取得
     * 
     * @param string $commercial
     * @return string
     */
    protected function get_commercial_description($commercial) {
        $descriptions = array(
            'none' => 'コンテンツ重視',
            'seo' => 'SEO最適化重視',
            'conversion' => '予約促進重視',
        );
        return $descriptions[$commercial] ?? $descriptions['seo'];
    }

    /**
     * 体験タイプ説明を取得
     * 
     * @param string $experience
     * @return string
     */
    protected function get_experience_description($experience) {
        $descriptions = array(
            'record' => '記録型（客観的）',
            'immersive' => '没入型（体験引込み）',
            'drama' => 'ドラマ型（感動ストーリー）',
        );
        return $descriptions[$experience] ?? $descriptions['record'];
    }

    /**
     * システムプロンプトを生成（HQC対応）
     * 
     * @param array $hqc HQC設定
     * @return string
     */
    protected function generate_system_prompt($hqc) {
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
        
        $base .= "出力は常にHTMLタグのみ。Markdown禁止。";
        
        return $base;
    }

    /**
     * 品質基準ブロックを生成
     * 
     * @param array $hqc HQC設定
     * @return string
     */
    protected function generate_quality_block($hqc) {
        $quality = "";
        
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
     * Markdownをクリーニング
     * 
     * @param string $content
     * @return string
     */
    protected function clean_markdown($content) {
        $content = preg_replace('/```html?\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        return trim($content);
    }

    /**
     * temperature調整（物語強度に応じて）
     * 
     * @param float $base_temperature
     * @param array $hqc
     * @return float
     */
    protected function adjust_temperature($base_temperature, $hqc) {
        $story = $hqc['q']['story'] ?? 2;
        if ($story >= 3) {
            return min(0.9, $base_temperature + 0.1);
        }
        return $base_temperature;
    }

    /**
     * HQC設定を取得
     * 
     * @return array
     */
    public function get_hqc_settings() {
        return $this->hqc_settings;
    }

    /**
     * HQC設定を更新
     * 
     * @param array $settings
     */
    public function set_hqc_settings($settings) {
        $this->hqc_settings = $settings;
    }

    /**
     * デフォルトHQC設定を取得
     * 
     * @return array
     */
    public function get_default_hqc() {
        return $this->default_hqc;
    }

    /**
     * トークン数を推定（日本語対応）
     * 
     * @param string $text
     * @param float $ratio 日本語文字あたりのトークン数
     * @return int
     */
    protected function estimate_tokens($text, $ratio = 1.5) {
        $jp_chars = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text);
        $en_words = str_word_count(preg_replace('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', ' ', $text));
        return (int)($jp_chars / $ratio + $en_words);
    }

    /**
     * 利用可能なペルソナ一覧を取得
     * 
     * @return array
     */
    public function get_available_personas() {
        return $this->personas;
    }

    /**
     * 利用可能なトーン一覧を取得
     * 
     * @return array
     */
    public function get_available_tones() {
        return $this->tones;
    }

    /**
     * 利用可能な構造一覧を取得
     * 
     * @return array
     */
    public function get_available_structures() {
        return $this->structures;
    }

    /**
     * 利用可能な目的一覧を取得
     * 
     * @return array
     */
    public function get_available_purposes() {
        return $this->purposes;
    }
}