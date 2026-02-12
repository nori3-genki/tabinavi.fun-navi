<?php
/**
 * 学習データ活用クラス（編集長レイヤー）
 * 
 * 過去の記事から学習した弱点改善指示と成功パターンを提供
 * 
 * @package HRS
 * @version 6.2.0-EDITOR-IN-CHIEF
 * 
 * 設計思想:
 * - これは「プロンプト改善」ではなく「生成AIに対する編集長／赤入れ責任者」
 * - モデル非依存の知的編集レイヤー（OpenAI/Claude/Local LLM/人間 すべてに適用）
 * - 弱点 × 成功パターンの自己補正構造（when/how/why/example）
 * - ホテル固有文脈を「最初に」注入（文脈ベクトルのブレ防止）
 * 
 * 実装特徴:
 * ✅ 指示テンプレート × コンテキスト注入 → 疑似的な“創造的揺らぎ”
 * ✅ 再現性保証（同じ入力＝同じ指示）
 * ✅ 過剰最適化防止（文字数による条件分岐）
 * ✅ 実運用レベルの堅牢性（SQL不整合の完全排除）
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Learning_Boost {

    private static $instance = null;

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 弱点マッピング（テンプレート化＋再現性保証）
     * 
     * 各弱点は以下のように構成:
     * - template: 指示文のテンプレート（%s で変数注入）
     * - contexts: 具体例の配列（ホテル名をseedに再現性保証）
     */
    private $weakness_map = array(
        // H軸（Human）
        'H_five_senses' => [
            'template' => '五感描写が不足しています。【%s】の場面で、%sを感じられる描写を追加してください',
            'contexts' => [
                ['温泉入浴', '湯の温度・香り・肌触り'],
                ['夕食', '料理の香り・食感・味の広がり'],
                ['客室', 'ベッドの肌触り・窓からの景色・静けさ'],
                ['朝食', 'コーヒーの香り・パンのサクサク感・朝日'],
            ],
        ],
        'H_emotion' => [
            'template' => '感情表現が弱いです。【%s】の場面で「%s」といった感情の動きを明確に描写してください',
            'contexts' => [
                ['到着時', '感動・期待・わくわく'],
                ['温泉入浴', '癒し・解放感・至福'],
                ['夕食', '驚き・幸福感・満足感'],
                ['寝る前', '安らぎ・幸せ・明日への期待'],
                ['チェックアウト', '名残惜しさ・また来たい気持ち'],
            ],
        ],
        'H_scene' => [
            'template' => '情景描写が不足しています。【%s】の場面を、%sといった要素で具体的に描写してください',
            'contexts' => [
                ['露天風呂', '山々・星空・湯気・木々の香り'],
                ['客室窓から', '海・富士山・街並み・夕焼け'],
                ['レストラン', 'テーブルセッティング・料理の盛り付け・雰囲気'],
                ['ロビー', 'インテリア・香り・スタッフの笑顔'],
            ],
        ],
        'H_story' => [
            'template' => 'ストーリー性が弱いです。【%s】を起点に、%sといった流れで体験を時系列で描写してください',
            'contexts' => [
                ['到着', '到着→案内→客室→温泉→夕食→就寝'],
                ['朝', '目覚め→朝食→チェックアウト→帰路'],
                ['1日目', '午後到着→温泉→夕食→夜の館内探索'],
                ['2日目', '朝食→周辺散策→チェックアウト'],
            ],
        ],
        
        // Q軸（Quality）
        'Q_facilities' => [
            'template' => '施設情報が不足しています。【%s】について、%sといった具体的な情報を含めてください',
            'contexts' => [
                ['温泉', '源泉かけ流しかどうか・湯船の数・露天風呂の有無'],
                ['客室', '広さ・ベッドの種類・アメニティ・景色'],
                ['食事', '料理長のこだわり・地元食材・コース内容'],
                ['設備', '駐車場・送迎・スパ・プール'],
            ],
        ],
        'Q_accuracy' => [
            'template' => '情報の具体性が足りません。【%s】について、%sといった数値や固有名詞を含めてください',
            'contexts' => [
                ['アクセス', '駅からの距離（徒歩10分）・車での所要時間'],
                ['料金', '1泊2食15,000円〜・季節料金の差'],
                ['設備', '客室数50室・温泉2種類・駐車場80台'],
                ['食事', '会席料理10品・地ビール3種類'],
            ],
        ],
        'Q_originality' => [
            'template' => 'オリジナリティが不足しています。【%s】について、%sといった独自の視点や発見を含めてください',
            'contexts' => [
                ['温泉', '地元の人が教える隠れ湯・湯上がりの楽しみ方'],
                ['食事', 'シェフへのインタビュー・地元食材の物語'],
                ['周辺', 'スタッフおすすめの穴場スポット'],
                ['体験', '他の宿にはない特別なサービス'],
            ],
        ],
        
        // C軸（Content）
        'C_h2_count' => [
            'template' => 'H2見出しが少ないです。【%s】をテーマに、%sといった6個以上の見出しで構成してください',
            'contexts' => [
                ['全体構成', '到着→客室→温泉→夕食→夜→翌朝→まとめ'],
                ['温泉特集', '種類→効能→入浴方法→湯上がり→感想'],
                ['食事特集', '朝食→ランチ→夕食→デザート→感想'],
                ['周辺観光', 'アクセス→観光地→グルメ→ショッピング→まとめ'],
            ],
        ],
        'C_keyphrase' => [
            'template' => 'キーフレーズの配置が不適切です。【%s】の位置に、%sを必ず配置してください',
            'contexts' => [
                ['冒頭', 'ホテル名＋地域名＋主要キーワード'],
                ['H2見出し', '各セクションのテーマ＋キーワード'],
                ['本文冒頭', 'セクションの要点＋キーワード'],
                ['まとめ', '再訪の勧め＋キーワード'],
            ],
        ],
        'C_structure' => [
            'template' => '記事構成が弱いです。【%s】の流れを明確にし、%sといった要素を必ず含めてください',
            'contexts' => [
                ['導入→本文→まとめ', '結論先行→詳細展開→再確認'],
                ['問題→解決→効果', '課題提示→対応策→満足度'],
                ['期待→体験→感想', '事前イメージ→実際の体験→比較'],
            ],
        ],
        
        // SEO軸（パフォーマンス連携）
        'SEO_low_ctr' => [
            'template' => 'CTRが低いです。タイトルに【%s】といった具体的な数字や魅力的なフレーズを入れてください',
            'contexts' => [
                ['数字', '源泉かけ流し100%・露天風呂3種類・料理10品'],
                ['感情', '感動・至福・癒し・極上'],
                ['特典', '無料送迎・ウェルカムドリンク・記念品'],
                ['限定', '期間限定・数量限定・予約限定'],
            ],
        ],
        'SEO_high_bounce' => [
            'template' => '直帰率が高いです。冒頭【%s】で読者の興味を引き、%sといった要素を必ず含めてください',
            'contexts' => [
                ['結論先行', '最高の温泉体験・人生最高の食事'],
                ['共感', 'あなたも経験したこの感覚・誰もが求める癒し'],
                ['具体性', '湯船に浸かった瞬間の感動・料理が口の中でとろける感覚'],
            ],
        ],
        'SEO_short_session' => [
            'template' => '滞在時間が短いです。【%s】のセクションを充実させ、%sといった読み応えのある内容にしてください',
            'contexts' => [
                ['温泉体験', '入浴前の期待→湯船の感覚→湯上がりの爽快感'],
                ['食事体験', '料理の見た目→香り→味→余韻'],
                ['客室体験', '部屋に入った瞬間の感動→設備の使い心地→寝心地'],
            ],
        ],
        'SEO_low_ranking' => [
            'template' => '検索順位が低いです。【%s】の配置と、%sといったH2構成を最適化してください',
            'contexts' => [
                ['キーフレーズ', '冒頭・見出し・本文・まとめにバランスよく'],
                ['H2構成', '導入→客室→温泉→食事→周辺→まとめ'],
                ['内部リンク', '関連記事への自然な誘導'],
            ],
        ],
    );

    /**
     * 成功パターンのラベル
     */
    private $pattern_labels = array(
        'h_boost'   => '人間性・感情面',
        'q_boost'   => '品質・情報面',
        'c_boost'   => '構成・SEO面',
        'seo_boost' => 'SEOパフォーマンス',
    );

    /**
     * 学習データから改善指示を生成（編集長レイヤー）
     * 
     * @param array $hotel_data ホテルデータ
     * @param int $target_word_count 想定文字数（省略可・過剰最適化防止用）
     * @return string プロンプトに追加する学習指示
     */
    public function apply_learning_boost($hotel_data, $target_word_count = 2500) {
        // 学習モジュールが存在しない場合はスキップ
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return '';
        }
        
        $hotel_name = $hotel_data['hotel_name'] ?? '';
        if (empty($hotel_name)) {
            return '';
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        $prompt = '';
        
        // 1. ホテル別学習データを取得
        $hotel_learning = $learning->get_hotel_learning($hotel_name);
        
        if ($hotel_learning) {
            // 1-1. ホテル固有文脈を「最初に」注入（文脈ベクトルのブレ防止）
            if (!empty($hotel_learning['identity'])) {
                $prompt .= $this->build_hotel_identity_instructions($hotel_learning['identity'], $target_word_count);
            }
            
            // 1-2. 慢性的弱点がある場合
            $chronic_weak = $hotel_learning['chronic_weak_points'] ?? array();
            if (!empty($chronic_weak)) {
                $prompt .= $this->build_weakness_instructions($chronic_weak, $hotel_name);
            }
            
            // 1-3. 過去の平均スコアが低い場合（70未満）
            $avg_score = $hotel_learning['avg_score'] ?? 0;
            if ($avg_score > 0 && $avg_score < 70) {
                $prompt .= $this->build_quality_boost_instructions($avg_score);
            }
        }
        
        // 2. 成功パターンを取得して適用
        $prompt .= $this->build_success_pattern_instructions();
        
        return $prompt;
    }

    /**
     * ホテル固有文脈から指示を生成（過剰最適化防止）
     * 
     * @param array $identity ホテルのアイデンティティ情報
     * @param int $word_count 想定文字数
     * @return string 指示文
     */
    private function build_hotel_identity_instructions($identity, $word_count = 2500) {
        if (empty($identity)) {
            return '';
        }
        
        $prompt = "【このホテルの特徴を活かす必須指示】\n";
        
        // 強み
        if (!empty($identity['strengths'])) {
            $prompt .= '・強み（' . implode('・', $identity['strengths']) . '）は各セクションで必ず詳細描写';
            if ($word_count >= 2000) {
                $prompt .= '（1段落以上）';
            }
            $prompt .= "\n";
        }
        
        // 弱点（改善ポイント）
        if (!empty($identity['weaknesses'])) {
            $prompt .= '・弱点（' . implode('・', $identity['weaknesses']) . '）は特に丁寧に説明し、読者の不安を解消\n';
        }
        
        // 読者層
        if (!empty($identity['audience'])) {
            $prompt .= '・主要読者層（' . $identity['audience'] . '）向けに共感表現を多用';
            if ($word_count >= 1500) {
                $prompt .= '（例：「二人の特別な時間を...」）';
            }
            $prompt .= "\n";
        }
        
        // 固有価値（文字数による条件分岐）
        if (!empty($identity['unique_selling'])) {
            $mention_count = $word_count >= 2000 ? '3回' : ($word_count >= 1000 ? '2回' : '1回');
            $prompt .= '・固有価値（' . $identity['unique_selling'] . '）は';
            $prompt .= $word_count >= 2000 ? '冒頭・中盤・まとめで' : '冒頭とまとめで';
            $prompt .= $mention_count . '言及\n';
        }
        
        // 特別な指示
        if (!empty($identity['special_notes'])) {
            $prompt .= '・特別指示：' . $identity['special_notes'] . "\n";
        }
        
        return $prompt . "\n";
    }

    /**
     * 品質強化指示を生成
     * 
     * @param float $avg_score 平均スコア
     * @return string 指示文
     */
    private function build_quality_boost_instructions($avg_score) {
        $prompt = "【品質強化指示】\n";
        $prompt .= sprintf("このホテルの過去記事は品質スコアが低め（%.1f点）でした。以下を特に意識してください：\n", $avg_score);
        $prompt .= "・五感描写を各セクションに必ず含める（視覚・聴覚・嗅覚・味覚・触覚）\n";
        $prompt .= "・感情の動きを具体的に描写する（「良かった」ではなく「心が震えた」）\n";
        $prompt .= "・読者が追体験できる臨場感を出す（場面を細かく描写）\n";
        $prompt .= "・数値や固有名詞を積極的に使う（具体性の向上）\n";
        $prompt .= "\n";
        
        return $prompt;
    }

    /**
     * 慢性的弱点から改善指示を生成（再現性保証）
     * 
     * @param array $chronic_weak 慢性的弱点の配列
     * @param string $hotel_name ホテル名（再現性のためのseed）
     * @return string 指示文
     */
    public function build_weakness_instructions($chronic_weak, $hotel_name) {
        if (empty($chronic_weak)) {
            return '';
        }
        
        // 出現回数が多い順にソート
        uasort($chronic_weak, function($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });
        
        // 上位3つの弱点を取得
        $top_weak = array_slice($chronic_weak, 0, 3, true);
        
        $instructions = array();
        foreach ($top_weak as $key => $data) {
            $map = $this->weakness_map[$key] ?? null;
            if (!$map) {
                continue;
            }
            
            // 再現性保証：同じホテル+同じ弱点＝同じ指示
            $seed = crc32($hotel_name . $key);
            mt_srand($seed);
            
            // テンプレート形式の場合は変数注入
            if (is_array($map) && isset($map['template'], $map['contexts'])) {
                $context_index = mt_rand(0, count($map['contexts']) - 1);
                $context = $map['contexts'][$context_index];
                $instruction = vsprintf($map['template'], $context);
            } else {
                // 互換性：旧形式もサポート
                $instruction = is_string($map) ? $map : $map['template'];
            }
            
            $instructions[] = $instruction;
        }
        
        if (empty($instructions)) {
            return '';
        }
        
        $prompt = "【過去の弱点に基づく必須改善項目】\n";
        $prompt .= "このホテルの過去記事で繰り返し指摘された問題点です。必ず改善してください：\n";
        foreach ($instructions as $inst) {
            $prompt .= "★ {$inst}\n";
        }
        $prompt .= "\n";
        
        return $prompt;
    }

    /**
     * 成功パターンから推奨指示を生成（構造化対応＋SQL不整合修正）
     * 
     * @return string 指示文
     */
    public function build_success_pattern_instructions() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hrs_success_patterns';
        
        // テーブルが存在するか確認
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return '';
        }
        
        // ⚠️ 修正：usage_count を SELECT に追加（SQL不整合の完全排除）
        $patterns = $wpdb->get_results(
            "SELECT pattern_type, pattern_key, pattern_value, success_rate, avg_score_impact, usage_count 
             FROM {$table} 
             WHERE is_active = 1 
               AND success_rate >= 80 
               AND usage_count >= 3 
             ORDER BY avg_score_impact DESC 
             LIMIT 8",
            ARRAY_A
        );
        
        if (empty($patterns)) {
            return '';
        }
        
        // タイプ別にグループ化
        $grouped = array(
            'h_boost'   => array(),
            'q_boost'   => array(),
            'c_boost'   => array(),
            'seo_boost' => array(),
        );
        
        foreach ($patterns as $p) {
            $type = $p['pattern_type'];
            if (isset($grouped[$type]) && count($grouped[$type]) < 2) {
                $grouped[$type][] = $p;
            }
        }
        
        // 空でないグループがあるか確認
        $has_content = false;
        foreach ($grouped as $items) {
            if (!empty($items)) {
                $has_content = true;
                break;
            }
        }
        
        if (!$has_content) {
            return '';
        }
        
        $prompt = "【高成功率の表現パターン】\n";
        $prompt .= "以下は過去の高評価記事で効果的だった手法です。積極的に取り入れてください：\n\n";
        
        foreach ($grouped as $type => $items) {
            if (empty($items)) {
                continue;
            }
            
            $prompt .= "■ {$this->pattern_labels[$type]}\n";
            
            foreach ($items as $p) {
                $value = json_decode($p['pattern_value'], true);
                
                // 構造化データ（when/how/why/example）の場合
                if ($value && isset($value['when'], $value['how'])) {
                    $prompt .= "  【{$value['when']}】\n";
                    $prompt .= "    手法: {$value['how']}\n";
                    
                    if (!empty($value['why'])) {
                        $prompt .= "    効果: {$value['why']}\n";
                    }
                    if (!empty($value['example'])) {
                        $prompt .= "    例: {$value['example']}\n";
                    }
                } else {
                    // 互換性：旧形式（単純な文字列）もサポート
                    $prompt .= "  ・{$p['pattern_value']}\n";
                }
                
                // 件数や成功率を表示（信頼性向上）⚠️ 修正済み：usage_count が存在
                $usage = $p['usage_count'] ?? 0;
                $rate = $p['success_rate'] ?? 0;
                $prompt .= sprintf("    （使用%d回・成功率%.0f%%）\n", $usage, $rate);
            }
            
            $prompt .= "\n";
        }
        
        return $prompt;
    }

    /**
     * 弱点マップを取得
     * 
     * @return array 弱点マップ
     */
    public function get_weakness_map() {
        return $this->weakness_map;
    }

    /**
     * パターンラベルを取得
     * 
     * @return array パターンラベル
     */
    public function get_pattern_labels() {
        return $this->pattern_labels;
    }

    /**
     * 特定の弱点のテンプレートを取得
     * 
     * @param string $key 弱点キー
     * @return string|array テンプレート
     */
    public function get_weakness_template($key) {
        return $this->weakness_map[$key] ?? null;
    }

    /**
     * 再現性保証付きの弱点指示生成（テスト用）
     * 
     * @param string $key 弱点キー
     * @param string $hotel_name ホテル名（再現性のためのseed）
     * @return string 指示文
     */
    public function generate_deterministic_weakness_instruction($key, $hotel_name) {
        $map = $this->weakness_map[$key] ?? null;
        if (!$map || !is_array($map) || !isset($map['template'], $map['contexts'])) {
            return is_string($map) ? $map : '';
        }
        
        // 再現性保証
        $seed = crc32($hotel_name . $key);
        mt_srand($seed);
        
        $context_index = mt_rand(0, count($map['contexts']) - 1);
        $context = $map['contexts'][$context_index];
        return vsprintf($map['template'], $context);
    }
}