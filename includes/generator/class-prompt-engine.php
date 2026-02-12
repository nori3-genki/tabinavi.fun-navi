<?php
/**
 * プロンプトエンジン（5D Framework統合版）
 * 
 * ★ API連動コンテンツ要素対応（価格・比較・口コミ）
 * ★ [hrs_price_section] 自動挿入対応
 * ★ C層AI型要素プロンプト強化版
 * ★ 非線形構造・感覚軸構造指示強化
 * ★ SEO必須要件強化（Yoast対応）
 * 
 * @package HRS
 * @version 6.4.0
 * @change 6.4.0: SEO必須要件（冒頭キーフレーズ、密度、H2配置、内部リンクポイント）追加
 * @change 6.3.0: 非線形構造(hero_journey)・感覚軸(five_sense)のプロンプト指示を大幅強化
 * @change 6.2.0: C層AI型要素のプロンプト指示を具体化・強化
 * @change 6.1.1: 手動生成画面対応（generate_prompt引数拡張）
 * @change 6.1.0: [hrs_price_section] プロンプト指示追加
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Prompt_Engine {

    private $hqc_option = 'hrs_hqc_settings';

    private $ota_names = array(
        'rakuten' => '楽天トラベル', 'jalan' => 'じゃらん', 'ikyu' => '一休.com',
        'relux' => 'Relux', 'booking' => 'Booking.com', 'yahoo' => 'Yahoo!トラベル',
        'jtb' => 'JTB', 'rurubu' => 'るるぶトラベル', 'yukoyuko' => 'ゆこゆこ', 'expedia' => 'Expedia',
    );

    /**
     * ★ C層コンテンツ要素（API連動対応版）
     * type: 'ai' = AI生成, 'api' = API自動挿入
     */
    private $c_content_items = array(
        'cta' => array(
            'name' => 'CTA（行動喚起）', 
            'type' => 'ai',
            'prompt' => '予約や詳細確認を促すCTA（行動喚起）を記事内に自然に配置してください。
・記事冒頭（導入部の最後）に軽めのCTA：「気になる方はぜひチェックしてみてください」等
・記事中盤（魅力を伝えた後）に中程度のCTA：「この体験、ぜひあなたも」等
・記事末尾（まとめ前）に強めのCTA：「次の記念日・特別な日に、ぜひ訪れてみてください」等
・押しつけがましくなく、読者の背中を優しく押す表現を心がけてください',
        ),
        'affiliate_links' => array(
            'name' => '予約リンク', 
            'type' => 'ai',
            'prompt' => '予約サイトへのリンク挿入に適した箇所を設けてください。
※実際のリンクは後から挿入するため、文章の流れとして自然な位置を意識してください',
        ),
        'faq' => array(
            'name' => 'FAQ', 
            'type' => 'ai',
            'prompt' => '読者が気になる質問をFAQ形式（Q&A）で3〜5個含めてください。
■ 必須の質問テーマ：
・予約に関すること（キャンセルポリシー、予約のコツ、早割など）
・アクセス・駐車場に関すること
・子連れ/カップル/一人など対象者別の疑問
・食事に関すること（アレルギー対応、食事時間など）
・温泉・お風呂に関すること（貸切、入浴時間など）
■ 形式：
Q: 質問文
A: 具体的で実用的な回答（50〜100文字程度）',
        ),
        'pros_cons' => array(
            'name' => 'メリット・デメリット', 
            'type' => 'ai',
            'prompt' => 'このホテルのメリット3〜5個とデメリット（注意点）2〜3個を正直に記載してください。
■ メリットの観点例：
・立地/アクセスの良さ
・コストパフォーマンス
・料理/食事の質
・温泉/お風呂の魅力
・サービス/おもてなし
・客室の快適さ
・周辺観光との相性
■ デメリット（注意点）の観点例：
・アクセスの不便さ（車必須など）
・施設の古さ/設備面
・価格帯の高さ
・混雑時期の予約難易度
・周辺に店が少ない等
■ 書き方：
・デメリットは「〜が気になる方もいるかも」「〜は事前に確認を」など柔らかい表現で
・メリットで打ち消せる場合は補足を添える',
        ),
        'target_audience' => array(
            'name' => 'ターゲット明示', 
            'type' => 'ai',
            'prompt' => '「こんな人におすすめ」を3〜5パターン、具体的なシーン付きで明示してください。
■ 記載形式：
・〇〇な人 → 理由・具体的なシーン
■ 例：
・記念日を特別な場所で過ごしたいカップル → 夜景を眺めながらのディナーが忘れられない思い出に
・子連れでも安心して温泉を楽しみたいファミリー → 貸切風呂とキッズメニューで家族全員満足
・仕事を忘れてリフレッシュしたい一人旅の方 → 静かな客室と露天風呂で自分だけの時間を
■ ペルソナに応じて最も響くターゲット像を優先してください',
        ),
        'seasonal_info' => array(
            'name' => '季節・時期情報', 
            'type' => 'ai',
            'prompt' => 'ベストシーズン、混雑状況、予約のコツを具体的に含めてください。
■ 必須項目：
【ベストシーズン】
・おすすめの季節とその理由（景色、気候、イベント等）
・季節ごとの魅力の違い（春：桜、夏：避暑、秋：紅葉、冬：雪景色など）
【混雑情報】
・繁忙期（GW、お盆、年末年始、紅葉シーズン等）の目安
・比較的空いている穴場時期
・平日vs週末の違い
【予約のコツ】
・何ヶ月前から予約すべきか
・早割・直前割などお得なタイミング
・人気の客室/プランは早めに埋まる旨の注意喚起',
        ),
        'access_info' => array(
            'name' => '周辺・アクセス', 
            'type' => 'ai',
            'prompt' => 'アクセス方法と周辺観光スポットを詳しく記載してください。
■ アクセス情報（必須）：
【電車の場合】
・最寄り駅と路線名
・駅からの所要時間と移動手段（送迎バス、タクシー、徒歩等）
・主要都市（東京、大阪、名古屋等）からの所要時間
【車の場合】
・最寄りICと所要時間
・駐車場の有無、料金、予約要否
・主要都市からの所要時間目安
【送迎】
・送迎サービスの有無
・要予約かどうか、時間帯
■ 周辺観光スポット（2〜3箇所）：
・スポット名と概要
・ホテルからの距離/所要時間
・おすすめの組み合わせプラン',
        ),
        'price_info' => array(
            'name' => '価格・プラン情報', 
            'type' => 'api',
            'api_class' => 'HRS_Rakuten_Price_Updater',
            'prompt_exclude' => '価格・料金・プラン情報は記載しないでください。最新の料金情報は別途APIから自動表示されます。',
        ),
        'comparison' => array(
            'name' => '比較・おすすめ', 
            'type' => 'api',
            'api_class' => 'HRS_Rakuten_Ranking',
            'prompt_exclude' => '他のホテルとの比較やランキング情報は記載しないでください。楽天トラベルのランキングデータが別途自動表示されます。',
        ),
        'reviews' => array(
            'name' => '口コミ・評価', 
            'type' => 'api',
            'api_class' => 'HRS_Rakuten_Ranking',
            'prompt_exclude' => '口コミ・評価・レビュー点数は記載しないでください。楽天トラベルの実際の評価データが別途自動表示されます。',
        ),
    );

    private $persona_c_defaults = array(
        'general' => array('cta', 'price_info', 'access_info', 'reviews'),
        'solo' => array('price_info', 'access_info', 'reviews', 'target_audience'),
        'couple' => array('cta', 'seasonal_info', 'target_audience', 'reviews'),
        'family' => array('faq', 'price_info', 'access_info', 'pros_cons'),
        'senior' => array('faq', 'access_info', 'pros_cons', 'reviews'),
        'workation' => array('access_info', 'faq', 'target_audience', 'reviews'),
        'luxury' => array('comparison', 'reviews', 'target_audience', 'cta'),
        'budget' => array('price_info', 'comparison', 'pros_cons', 'reviews'),
    );

    public function get_persona_c_defaults($persona) {
        return $this->persona_c_defaults[$persona] ?? $this->persona_c_defaults['general'];
    }

    public function get_c_content_items() {
        return $this->c_content_items;
    }

    public function get_api_content_items() {
        return array_filter($this->c_content_items, function($item) {
            return ($item['type'] ?? 'ai') === 'api';
        });
    }

    public function get_ai_content_items() {
        return array_filter($this->c_content_items, function($item) {
            return ($item['type'] ?? 'ai') === 'ai';
        });
    }

    public function is_api_element($element_id) {
        return isset($this->c_content_items[$element_id]) 
            && ($this->c_content_items[$element_id]['type'] ?? 'ai') === 'api';
    }

    public function get_all_persona_c_defaults() {
        return $this->persona_c_defaults;
    }

    public function generate_5d_prompt($hotel_data, $style = 'story', $persona = 'couple', $tone = 'luxury', $policy = 'standard', $ai_model = 'chatgpt', $style_layers = array()) {
        
        $hqc = $this->get_hqc_settings();
        if (!empty($hqc)) {
            if ($persona === 'general' && !empty($hqc['h']['persona'])) $persona = $hqc['h']['persona'];
            if ($tone === 'luxury' && !empty($hqc['q']['tone'])) $tone = $hqc['q']['tone'];
            if ($style === 'story' && !empty($hqc['q']['structure'])) $style = $this->map_structure_to_style($hqc['q']['structure']);
        }

        $hotel_name = $this->safe_string($hotel_data['hotel_name'] ?? '');
        $address = $this->safe_string($hotel_data['address'] ?? '');
        $description = $this->safe_string($hotel_data['description'] ?? '');
        $features = $this->safe_features($hotel_data['features'] ?? array());

        $prompt = $this->build_base_prompt($hotel_name, $address, $description, $features);
        $prompt .= $this->apply_style($style);
        $prompt .= $this->apply_persona($persona);
        $prompt .= $this->apply_tone($tone);
        $prompt .= $this->apply_policy($policy);

        if (!empty($style_layers)) $prompt .= $this->apply_style_layers($style_layers);
        $prompt .= $this->apply_learning_boost($hotel_data);
        if (!empty($hqc)) $prompt .= $this->apply_hqc_details($hqc);

        $c_contents = $this->resolve_c_contents($hqc, $persona);
        if (!empty($c_contents)) $prompt .= $this->apply_c_content_elements_v2($c_contents);
        if (!in_array('reviews', $c_contents)) $prompt .= $this->apply_reviews($hotel_data, $persona);

        $prompt = $this->optimize_for_model($prompt, $ai_model);
        $prompt .= $this->get_output_instructions($hotel_name);
        
        $prompt .= $this->get_shortcode_instructions();

        return $prompt;
    }

    /**
     * 手動生成画面用のシンプルなインターフェース
     */
    public function generate_prompt($prompt_data) {
        $hotel_data = array(
            'hotel_name' => $prompt_data['hotel_name'] ?? '',
            'address' => $prompt_data['location'] ?? '',
            'description' => '',
            'features' => array(),
        );
        
        $preset = $prompt_data['preset'] ?? 'balanced';
        $presets = HRS_Generator_Data::get_presets();
        $preset_config = $presets[$preset] ?? $presets['balanced'];
        
        $style = $preset_config['style'] ?? 'story';
        $persona = $preset_config['persona'] ?? 'couple';
        $tone = $preset_config['tone'] ?? 'luxury';
        $policy = $preset_config['policy'] ?? 'standard';
        $ai_model = $prompt_data['ai_model'] ?? 'claude';
        $style_layers = $prompt_data['style_layers'] ?? array();
        
        if (isset($prompt_data['target_words'])) {
            $hotel_data['target_words'] = intval($prompt_data['target_words']);
        }
        
        return $this->generate_5d_prompt(
            $hotel_data,
            $style,
            $persona,
            $tone,
            $policy,
            $ai_model,
            $style_layers
        );
    }

    /**
     * ショートコード挿入指示
     */
    private function get_shortcode_instructions() {
        $instructions = "\n【必須ショートコード】\n";
        $instructions .= "記事の最後（まとめセクションの直前）に、以下のショートコードを必ず挿入してください：\n\n";
        $instructions .= "[hrs_price_section]\n\n";
        $instructions .= "※このショートコードは楽天トラベルの最新料金・空室情報を自動表示します。\n";
        $instructions .= "※ショートコードはそのまま記述し、HTMLタグで囲まないでください。\n\n";
        
        return $instructions;
    }

    private function resolve_c_contents($hqc, $persona) {
        if (!empty($hqc['c']['contents']) && is_array($hqc['c']['contents'])) return $hqc['c']['contents'];
        return $this->get_persona_c_defaults($persona);
    }

    private function apply_c_content_elements_v2($selected_contents) {
        if (empty($selected_contents) || !is_array($selected_contents)) return '';

        $ai_includes = array();
        $api_excludes = array();

        foreach ($selected_contents as $content_id) {
            if (!isset($this->c_content_items[$content_id])) continue;
            $item = $this->c_content_items[$content_id];
            
            if (($item['type'] ?? 'ai') === 'api') {
                $api_excludes[] = array('name' => $item['name'], 'exclude' => $item['prompt_exclude']);
            } else {
                $ai_includes[] = array('name' => $item['name'], 'prompt' => $item['prompt']);
            }
        }

        $prompt = '';

        if (!empty($ai_includes)) {
            $prompt .= "\n【必須コンテンツ要素】\n以下の要素を記事に必ず含めてください：\n\n";
            $count = 0;
            foreach ($ai_includes as $item) {
                $count++;
                $prompt .= "■ {$count}. {$item['name']}\n{$item['prompt']}\n\n";
            }
            $prompt .= "※上記の要素は記事の適切な箇所に自然に組み込んでください。\n\n";
        }

        if (!empty($api_excludes)) {
            $prompt .= "【以下の内容は記載しないでください】\n※これらは別途APIから最新データを自動表示します\n\n";
            foreach ($api_excludes as $item) {
                $prompt .= "✗ {$item['name']}\n  → {$item['exclude']}\n\n";
            }
        }

        return $prompt;
    }

    private function apply_reviews($hotel_data, $persona) {
        if (empty($hotel_data['reviews']) && empty($hotel_data['review_summary'])) return '';
        $reviews = $hotel_data['reviews'] ?? array();
        $summary = $hotel_data['review_summary'] ?? array();

        $prompt = "\n【参考口コミ情報】\n実際の宿泊者の声を参考に、リアリティのある描写を心がけてください。\n\n";

        $persona_reviews = $this->get_reviews_by_persona($reviews, $persona, 3);
        if (!empty($persona_reviews)) {
            $persona_labels = array('family' => 'ファミリー層', 'couple' => 'カップル', 'solo' => '一人旅', 'senior' => 'シニア層', 'luxury' => 'ラグジュアリー志向', 'budget' => 'コスパ重視層', 'general' => '一般', 'workation' => 'ワーケーション層');
            $label = $persona_labels[$persona] ?? '一般';
            $prompt .= "■ {$label}の声\n";
            foreach ($persona_reviews as $review) {
                $text = $this->truncate_text($review['text'], 100);
                $prompt .= "・「{$text}」\n";
            }
            $prompt .= "\n";
        }

        if (!empty($summary['top_keywords'])) {
            $keywords = array_slice($summary['top_keywords'], 0, 7);
            $prompt .= "■ よく言及されるポイント\n・" . implode('、', $keywords) . "\n\n";
        }

        $prompt .= "※上記の口コミを直接引用せず、これらの情報を元に自分の言葉で描写してください。\n\n";
        return $prompt;
    }

    private function get_reviews_by_persona($reviews, $persona, $limit = 3) {
        $filtered = array_filter($reviews, function($r) use ($persona) {
            return isset($r['persona']) && $r['persona'] === $persona && isset($r['sentiment']) && $r['sentiment'] === 'positive';
        });
        return array_slice(array_values($filtered), 0, $limit);
    }

    private function truncate_text($text, $max_length = 100) {
        if (mb_strlen($text) <= $max_length) return $text;
        return mb_substr($text, 0, $max_length) . '...';
    }

    private function get_hqc_settings() {
        $settings = get_option($this->hqc_option, array());
        return is_array($settings) ? $settings : array();
    }

    private function build_base_prompt($hotel_name, $address, $description, $features) {
        $prompt = "あなたは旅行ライターとして、以下のホテルについて魅力的なレビュー記事を書いてください。\n\n【ホテル情報】\n・ホテル名: {$hotel_name}\n";
        if (!empty($address)) $prompt .= "・所在地: {$address}\n";
        if (!empty($description)) $prompt .= "・概要: {$description}\n";
        if (!empty($features)) $prompt .= "・特徴: " . implode('、', $features) . "\n";
        $prompt .= "\n";
        return $prompt;
    }

    private function apply_style($style) {
        $styles = array(
            'story' => "【記事スタイル: ストーリー型】\n旅行記のように時系列で体験を描写し、読者が追体験できるような臨場感のある文章にしてください。\n\n",
            'guide' => "【記事スタイル: ガイド型】\n情報を整理して伝え、読者が予約判断しやすい実用的な内容にしてください。\n\n",
            'review' => "【記事スタイル: レビュー型】\n各項目を評価形式でまとめ、メリット・デメリットを客観的に伝えてください。\n\n",
            'emotional' => "【記事スタイル: エモーショナル型】\n感情に訴える表現を多用し、特別な体験として記憶に残る文章にしてください。\n\n",
            'five_sense' => "【記事スタイル: 五感描写型】\n視覚・聴覚・嗅覚・味覚・触覚の五感を使った臨場感ある描写を中心にしてください。\n\n",
        );
        return $styles[$style] ?? $styles['story'];
    }

    private function apply_persona($persona) {
        $personas = array(
            'general' => "【想定読者: 一般・観光】\n幅広い読者向けに、初めて訪れる人の視点でバランスの取れた情報を提供してください。\n\n",
            'solo' => "【想定読者: 一人旅】\n自由な時間を楽しみたい一人旅の読者向けに、静かに過ごせる空間やセルフケアの魅力を描写してください。\n\n",
            'couple' => "【想定読者: カップル・夫婦】\n二人の時間を大切にしたいカップル向けに、ロマンチックな雰囲気や記念日に最適なポイントを描写してください。\n\n",
            'family' => "【想定読者: ファミリー】\n子連れ家族向けに、子どもの反応と親の安心感を軸に描写してください。\n\n",
            'senior' => "【想定読者: シニア世代】\n落ち着いた大人旅を好むシニア向けに、安心感と上質な時間の流れを描写してください。\n\n",
            'workation' => "【想定読者: ワーケーション】\n仕事と休暇を両立したいビジネスパーソン向けに、ON/OFFの切り替えを描写してください。\n\n",
            'luxury' => "【想定読者: ラグジュアリー】\n最高級の体験を求める読者向けに、細部へのこだわりと特別扱いの実感を描写してください。\n\n",
            'budget' => "【想定読者: コスパ重視】\n賢くお得に旅したい読者向けに、「価格以上の価値」の発見を描写してください。\n\n",
        );
        return $personas[$persona] ?? $personas['general'];
    }

    private function apply_tone($tone) {
        $tones = array(
            'casual' => "【文体: カジュアル】\n親しみやすく、友人に話しかけるような軽やかな文体で書いてください。\n\n",
            'luxury' => "【文体: ラグジュアリー】\n上品で洗練された表現を使い、高級感のある文体で書いてください。\n\n",
            'emotional' => "【文体: エモーショナル】\n感情を揺さぶる表現を多用し、心に響く文体で書いてください。\n\n",
            'cinematic' => "【文体: シネマティック】\n映画のワンシーンのような描写で、視覚的にイメージが浮かぶ文体で書いてください。\n\n",
            'journalistic' => "【文体: ジャーナリスティック】\n客観的で信頼性のある、報道記事のような文体で書いてください。\n\n",
        );
        return $tones[$tone] ?? $tones['luxury'];
    }

    private function apply_policy($policy) {
        $policies = array(
            'standard' => "【生成ポリシー: スタンダード】\n読みやすさと情報量のバランスを重視してください。\n\n",
            'seo' => "【生成ポリシー: SEO重視】\nキーワードを自然に盛り込み、検索エンジンに最適化された構成にしてください。\n\n",
            'conversion' => "【生成ポリシー: コンバージョン重視】\n予約や問い合わせに繋がるよう、魅力的なCTAを含めてください。\n\n",
            'viral' => "【生成ポリシー: バイラル重視】\nSNSでシェアされやすい、インパクトのある表現を含めてください。\n\n",
            'balanced' => "【生成ポリシー: バランス重視】\n読みやすさ、SEO、コンバージョンのバランスを取った構成にしてください。\n\n",
        );
        return $policies[$policy] ?? $policies['standard'];
    }

    private function apply_style_layers($layers) {
        if (empty($layers)) return '';
        $prompt = "\n【追加スタイルレイヤー】\n";
        $layer_descriptions = array(
            'seasonal' => '季節感を演出する表現を追加', 'regional' => '地域の特色や文化を強調',
            'gourmet' => '料理やグルメ情報を詳しく', 'wellness' => '癒しや健康への効果を強調',
            'adventure' => 'アクティビティや冒険要素を追加', 'historical' => '歴史や伝統の深みを表現',
            'photogenic' => 'フォトジェニックなスポットを紹介', 'sustainable' => 'エコや持続可能性への取り組みを紹介',
        );
        foreach ($layers as $layer) {
            if (isset($layer_descriptions[$layer])) $prompt .= "・{$layer_descriptions[$layer]}\n";
        }
        $prompt .= "\n";
        return $prompt;
    }

    /**
     * ★ HQC詳細設定適用（v6.3.0 強化版）
     * 
     * 非線形構造・感覚軸構造のプロンプト指示を大幅強化
     */
    private function apply_hqc_details($hqc) {
        $prompt = '';
        
        // 旅の目的
        if (!empty($hqc['h']['purpose'])) {
            $prompt .= "\n【旅の目的】\n" . implode('、', $hqc['h']['purpose']) . "を目的とした旅行者向けの内容にしてください。\n\n";
        }
        
        // 体験深度（H層）- 強化版
        if (!empty($hqc['h']['depth'])) {
            $depth_map = array(
                'L1' => "基本的な情報を簡潔に伝える。事実ベースの記述を中心に。",
                'L2' => "体験を時系列で追い、感情の動きを含めて描写する。「〜した」だけでなく「〜して○○を感じた」まで踏み込む。具体的なシーンを3つ以上含める。",
                'L3' => "深い没入体験として詳細に描写。五感・感情・思考の変化を丁寧に追い、読者が完全に追体験できるレベルの臨場感を出す。内面の変化や気づきも言語化する。"
            );
            $prompt .= "【体験深度】\n" . ($depth_map[$hqc['h']['depth']] ?? $depth_map['L2']) . "\n\n";
        }
        
        // ★ ストーリー構成（Q層）- 大幅強化版
        if (!empty($hqc['q']['structure'])) {
            $story_map = array(
                'timeline' => "【時系列構造】
到着→チェックイン→客室→温泉→夕食→就寝→朝食→出発の流れで追う。
・各シーンに時刻や時間帯を添える（「14時、ロビーに足を踏み入れると〜」）
・読者が自分の旅程をイメージできる具体性を持たせる
・時間経過とともに期待→満足→名残惜しさの感情変化を織り込む",

                'hero_journey' => "【非線形・変容構造】
時系列に縛られず、感情の起伏を軸に構成してください。
■ 構成の流れ：
1. 導入（共感）: 日常の疲れや悩みを抱えた状態から始める。読者が「わかる」と思える描写
2. 転換点: ホテルに到着し「空気が変わる」瞬間を印象的に。五感で捉えた変化を描写
3. 深化: 体験を通じて心身が解きほぐれていく過程。時間軸を飛ばして核心へ進んでOK
4. クライマックス: 最も感動的な瞬間（夕景、料理、温泉など）を丁寧に描写
5. 帰還・余韻: 「また来たい」「自分が少し変わった」という気づきで締める
■ テクニック：
・回想や期待を織り交ぜてOK（「あの時のスタッフの笑顔を思い出す」）
・内面の独白を入れる（「この一口のために、ここに来たのかもしれない」）
・結末から始めて過去を振り返る構成も可",

                'five_sense' => "【感覚軸構造】
記事を五感セクションで構成してください。
■ 必須セクション（この順序で）：
1. 視覚: 景色、建築、照明、色彩、空間の広がり
2. 聴覚: 静寂、水音、虫の声、BGM、足音
3. 嗅覚: 温泉の硫黄、畳、檜、料理の香り
4. 味覚: 料理の味わい、地酒、朝食、お茶菓子
5. 触覚: 湯の温度、布団の肌触り、風、床の感触
■ 各セクションで：
・最も印象的だった瞬間を1つ深掘り
・具体的な表現を使う（「ぬるめの湯」ではなく「39度のやわらかな湯」）
・その感覚が呼び起こした感情も添える",

                'dialogue' => "【会話形式構造】
スタッフや同行者との会話を軸に展開してください。
■ 会話の種類：
・スタッフとの会話: 「おすすめは？」「実は〜なんですよ」
・同行者との会話: 「これすごくない？」「また来たいね」
・自分の心の声: 「〜と思った」「〜かもしれない」
■ バランス：
・会話7割、地の文3割を目安に
・会話から情報を自然に伝える（「お風呂は夜通し入れますよ」→24時間利用可能と伝わる）
・会話の合間に情景描写を挟む"
            );
            if (isset($story_map[$hqc['q']['structure']])) {
                $prompt .= "【ストーリー構成】\n{$story_map[$hqc['q']['structure']]}\n\n";
            }
        }
        
        // ★ 五感深度（Q層）- 強化版
        if (!empty($hqc['q']['sensory'])) {
            $sensory_map = array(
                'G1' => "五感描写は最小限に抑え、事実情報を中心にする。形容詞は控えめに。",
                'G2' => "各セクションに最低1つの五感描写を含める。
・視覚だけでなく、聴覚・嗅覚・触覚・味覚もバランスよく
・具体的な感覚表現を使用（「いい香り」ではなく「檜と湯気が混じった、どこか懐かしい香り」）
・その感覚が引き起こした感情も一文添える",
                'G3' => "五感をフルに使った臨場感ある描写を全編通じて展開。
・各段落に複数の感覚描写を織り込む
・感覚の重なりを表現（「湯の温もりと窓から差し込む夕日が、体の芯まで染みていく」）
・読者が実際にその場にいるかのような没入感を演出
・比喩や擬音語も積極的に活用"
            );
            $prompt .= "【五感描写】\n" . ($sensory_map[$hqc['q']['sensory']] ?? $sensory_map['G2']) . "\n\n";
        }
        
        // 情報量（C層）
        if (!empty($hqc['c']['info'])) {
            $info_map = array(
                'I1' => '簡潔で読みやすい短めの文章（1500〜2000文字）',
                'I2' => '標準的な情報量（2000〜3000文字）',
                'I3' => '詳細で網羅的な情報（3000〜4000文字）'
            );
            $prompt .= "【情報量】\n" . ($info_map[$hqc['c']['info']] ?? $info_map['I2']) . "にしてください。\n\n";
        }
        
        // 商業性（C層）
        if (!empty($hqc['c']['commercial'])) {
            $commercial_map = array(
                'none' => "商業的な表現は控えめに。純粋な体験記として書いてください。",
                'seo' => "自然なCTAを含め、SEOを意識した構成にしてください。",
                'conversion' => "予約や問い合わせに繋がるよう、積極的にCTAを含めてください。"
            );
            if (isset($commercial_map[$hqc['c']['commercial']])) {
                $prompt .= "【商業性】\n{$commercial_map[$hqc['c']['commercial']]}\n\n";
            }
        }
        
        // 体験表現（C層）
        if (!empty($hqc['c']['experience'])) {
            $experience_map = array(
                'record' => "客観的な記録として、事実ベースで淡々と書いてください。",
                'recommend' => "おすすめとして紹介する形で書いてください。",
                'immersive' => "読者が実際に体験しているかのような没入感のある描写にしてください。"
            );
            if (isset($experience_map[$hqc['c']['experience']])) {
                $prompt .= "【体験表現】\n{$experience_map[$hqc['c']['experience']]}\n\n";
            }
        }
        
        return $prompt;
    }

    private function optimize_for_model($prompt, $model) {
        switch ($model) {
            case 'claude':
                $prompt = "<instructions>\n" . $prompt . "</instructions>\n<output_rules>\n- HTMLタグで出力（Markdown禁止）\n- 見出しはH2, H3タグを使用\n</output_rules>\n";
                break;
            case 'gemini':
                $prompt = "## 記事生成指示\n\n" . $prompt . "\n---\n### 重要な制約\n- 出力はHTMLタグのみ\n";
                break;
            default:
                $rules = "### ルール\n1. 出力形式: HTML（Markdown禁止）\n2. 見出し: <h2>, <h3>タグを使用（H2は最低6個）\n3. 段落: <p>タグで囲む\n\n---\n\n";
                $prompt = $rules . $prompt;
        }
        return $prompt;
    }

    /**
     * ★ 出力形式の指示を生成（v6.4.0 SEO強化版）
     * 
     * Yoast SEO対応：
     * - 冒頭キーフレーズ配置
     * - キーフレーズ密度
     * - H2見出しへのキーフレーズ配置
     * - 内部リンク挿入ポイント
     */
    private function get_output_instructions($hotel_name = '') {
        $default_words = get_option('hrs_default_words', '2000');
        $ranges = array(
            '1500' => '1500〜2000文字程度',
            '2000' => '2000〜2500文字程度',
            '2500' => '2500〜3000文字程度',
            '3000' => '3000〜3500文字程度',
        );
        $word_range = $ranges[$default_words] ?? '2000〜3000文字程度';
        
        $output = "\n【出力形式】\n";
        $output .= "・文字数: {$word_range}\n";
        $output .= "・構成: 導入→本文→まとめ\n";
        $output .= "・HTMLタグを使用（Markdown禁止）\n";
        $output .= "・予約リンクは含めないでください（別途追加します）\n\n";
        
        // ★ SEO必須要件（v6.4.0追加）
        if (!empty($hotel_name)) {
            $output .= "【SEO必須要件】※検索上位表示のため必ず守ってください\n";
            $output .= "■ キーフレーズ: 「{$hotel_name}」\n\n";
            
            $output .= "1. 冒頭配置（最重要）:\n";
            $output .= "   記事の第一段落（最初の<p>タグ内）に必ずキーフレーズを含める\n";
            $output .= "   例: 「{$hotel_name}は、〜で知られる人気の宿です。」\n\n";
            
            $output .= "2. キーフレーズ密度:\n";
            $output .= "   本文全体で自然に5〜7回配置する\n";
            $output .= "   ※不自然な繰り返しや詰め込みは避け、文脈に沿って配置\n\n";
            
            $output .= "3. H2見出し配置:\n";
            $output .= "   H2見出しは6個以上作成し、うち最低2つにキーフレーズを含める\n";
            $output .= "   例:\n";
            $output .= "   ・<h2>{$hotel_name}の客室と設備</h2>\n";
            $output .= "   ・<h2>{$hotel_name}の温泉・お風呂</h2>\n";
            $output .= "   ・<h2>{$hotel_name}へのアクセス</h2>\n";
            $output .= "   ※残りのH2は「料理の魅力」「周辺観光」等でOK\n\n";
            
            $output .= "4. 内部リンク挿入ポイント:\n";
            $output .= "   記事内の適切な箇所に以下のHTMLコメントを2〜3個入れる\n";
            $output .= "   <!-- INTERNAL_LINK_POINT -->\n";
            $output .= "   ※後から関連記事リンクを自動挿入する目印として使用\n";
            $output .= "   ※「周辺観光」「似たエリアの宿」の文脈で配置すると自然\n\n";
        }
        
        return $output;
    }

    private function map_structure_to_style($structure) {
        $map = array('timeline' => 'story', 'hero_journey' => 'emotional', 'five_sense' => 'five_sense', 'dialogue' => 'story', 'review' => 'review');
        return $map[$structure] ?? 'story';
    }

    private function safe_string($value) {
        if (is_array($value)) return '';
        return sanitize_text_field((string) $value);
    }

    private function safe_features($features) {
        if (!is_array($features)) return array();
        return array_map('sanitize_text_field', $features);
    }

    private function apply_learning_boost($hotel_data) {
        if (!class_exists('HRS_HQC_Learning_Module')) return '';
        $hotel_name = $hotel_data['hotel_name'] ?? '';
        if (empty($hotel_name)) return '';
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        $prompt = '';
        $hotel_learning = $learning->get_hotel_learning($hotel_name);
        
        if ($hotel_learning) {
            $chronic_weak = $hotel_learning['chronic_weak_points'] ?? array();
            if (!empty($chronic_weak)) $prompt .= $this->build_weakness_instructions($chronic_weak);
            $avg_score = $hotel_learning['avg_score'] ?? 0;
            if ($avg_score > 0 && $avg_score < 70) {
                $prompt .= "\n【品質強化指示】\nこのホテルの過去記事は品質スコアが低めでした。五感描写と感情の動きを意識してください。\n\n";
            }
        }
        $prompt .= $this->build_success_pattern_instructions();
        return $prompt;
    }

    private function build_weakness_instructions($chronic_weak) {
        if (empty($chronic_weak)) return '';
        uasort($chronic_weak, function($a, $b) { return ($b['count'] ?? 0) - ($a['count'] ?? 0); });
        $top_weak = array_slice($chronic_weak, 0, 3, true);
        $weakness_map = array(
            'H_five_senses' => '五感描写が不足しています', 'H_emotion' => '感情表現が弱いです',
            'H_scene' => '情景描写が不足しています', 'Q_facilities' => '施設情報が不足しています',
            'C_h2_count' => 'H2見出しが少ないです。6個以上で構成してください', 'C_keyphrase' => 'キーフレーズの配置が不適切です',
        );
        $instructions = array();
        foreach ($top_weak as $key => $data) {
            if (isset($weakness_map[$key])) $instructions[] = $weakness_map[$key];
        }
        if (empty($instructions)) return '';
        $prompt = "\n【過去の弱点に基づく必須改善項目】\n";
        foreach ($instructions as $inst) $prompt .= "★ {$inst}\n";
        $prompt .= "\n";
        return $prompt;
    }

    private function build_success_pattern_instructions() {
        global $wpdb;
        $table = $wpdb->prefix . 'hrs_success_patterns';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) return '';
        $patterns = $wpdb->get_results("SELECT pattern_type, pattern_value FROM {$table} WHERE is_active = 1 AND success_rate >= 80 AND usage_count >= 3 ORDER BY avg_score_impact DESC LIMIT 6", ARRAY_A);
        if (empty($patterns)) return '';
        $prompt = "\n【高成功率の表現パターン】\n";
        foreach ($patterns as $p) $prompt .= "・{$p['pattern_value']}\n";
        $prompt .= "\n";
        return $prompt;
    }
}

if (!function_exists('hrs_generate_prompt')) {
    function hrs_generate_prompt($hotel_data, $style = 'story', $persona = 'couple', $tone = 'luxury', $policy = 'standard') {
        $engine = new HRS_Prompt_Engine();
        return $engine->generate_5d_prompt($hotel_data, $style, $persona, $tone, $policy);
    }
}