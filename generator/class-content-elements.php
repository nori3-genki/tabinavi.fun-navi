<?php
/**
 * C層コンテンツ要素設定（API連動対応版）
 * 
 * コンテンツ要素の定義とペルソナ別デフォルト設定
 * API連動要素とAI生成要素の分類
 * 
 * @package HRS
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Content_Elements {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * C層コンテンツ要素の定義（API連動対応版）
     * type: 'ai' = AI生成, 'api' = API自動挿入
     */
    private $c_content_items = array(
        // === AI生成要素 ===
        'cta' => array(
            'name' => 'CTA（行動喚起）',
            'type' => 'ai',
            'prompt' => '予約や詳細確認を促すCTA（行動喚起）を自然に配置してください。「詳細をチェック」「空室を確認」などの表現を適切な箇所に含めてください。',
        ),
        'affiliate_links' => array(
            'name' => '予約リンク',
            'type' => 'ai',
            'prompt' => '楽天トラベル・じゃらん等の予約サイトへのリンク挿入に適した箇所を設けてください。「予約はこちら」などのリンクテキストを想定した文脈を作ってください。',
        ),
        'faq' => array(
            'name' => 'FAQ',
            'type' => 'ai',
            'prompt' => '読者が疑問に思いそうな点をFAQ形式（Q&A）で3〜5個含めてください。「Q. チェックイン時間は？」「Q. 駐車場はありますか？」などの形式で。',
        ),
        'pros_cons' => array(
            'name' => 'メリット・デメリット',
            'type' => 'ai',
            'prompt' => 'このホテルのメリット（良い点）3〜5個とデメリット（注意点・残念な点）2〜3個を正直に記載してください。読者の信頼を得るために、良い点だけでなく改善点も含めてください。',
        ),
        'target_audience' => array(
            'name' => 'ターゲット明示',
            'type' => 'ai',
            'prompt' => '「こんな人におすすめ」というセクションを設け、具体的なターゲット層を3〜5パターン明示してください。「カップルの記念日旅行に」「子連れファミリーに」「一人旅でゆっくりしたい方に」など。',
        ),
        'seasonal_info' => array(
            'name' => '季節・時期情報',
            'type' => 'ai',
            'prompt' => 'おすすめの訪問時期、ベストシーズン、混雑状況、予約のコツを含めてください。「紅葉シーズンは予約困難」「平日がおすすめ」「GWは早めの予約を」など具体的に。',
        ),
        'access_info' => array(
            'name' => '周辺・アクセス',
            'type' => 'ai',
            'prompt' => '最寄り駅からのアクセス方法、周辺の観光スポット、便利な交通手段を詳しく記載してください。「駅から徒歩○分」「車で○分」「周辺には○○がある」など。',
        ),

        // === API連動要素（AIには書かせない） ===
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

    /**
     * ペルソナ別C層デフォルト設定
     */
    private $persona_c_defaults = array(
        'general' => array(
            'cta',
            'price_info',
            'access_info',
            'reviews',
        ),
        'solo' => array(
            'price_info',
            'access_info',
            'reviews',
            'target_audience',
        ),
        'couple' => array(
            'cta',
            'seasonal_info',
            'target_audience',
            'reviews',
        ),
        'family' => array(
            'faq',
            'price_info',
            'access_info',
            'pros_cons',
        ),
        'senior' => array(
            'faq',
            'access_info',
            'pros_cons',
            'reviews',
        ),
        'workation' => array(
            'access_info',
            'faq',
            'target_audience',
            'reviews',
        ),
        'luxury' => array(
            'comparison',
            'reviews',
            'target_audience',
            'cta',
        ),
        'budget' => array(
            'price_info',
            'comparison',
            'pros_cons',
            'reviews',
        ),
    );

    /**
     * C層コンテンツ要素の定義を取得
     */
    public function get_c_content_items() {
        return $this->c_content_items;
    }

    /**
     * ペルソナ別C層デフォルト項目を取得
     */
    public function get_persona_c_defaults($persona) {
        return $this->persona_c_defaults[$persona] ?? $this->persona_c_defaults['general'];
    }

    /**
     * ペルソナ別デフォルト設定のマッピングを取得
     */
    public function get_all_persona_c_defaults() {
        return $this->persona_c_defaults;
    }

    /**
     * API連動要素のみ取得
     */
    public function get_api_content_items() {
        return array_filter($this->c_content_items, function($item) {
            return ($item['type'] ?? 'ai') === 'api';
        });
    }

    /**
     * AI生成要素のみ取得
     */
    public function get_ai_content_items() {
        return array_filter($this->c_content_items, function($item) {
            return ($item['type'] ?? 'ai') === 'ai';
        });
    }

    /**
     * 要素がAPI連動かどうか判定
     */
    public function is_api_element($element_id) {
        return isset($this->c_content_items[$element_id]) 
            && ($this->c_content_items[$element_id]['type'] ?? 'ai') === 'api';
    }

    /**
     * C層コンテンツ要素をプロンプトに適用（API連動対応版）
     */
    public function apply_c_content_elements($selected_contents) {
        if (empty($selected_contents) || !is_array($selected_contents)) {
            return '';
        }

        $ai_includes = array();
        $api_excludes = array();

        // AI生成要素とAPI連動要素を分類
        foreach ($selected_contents as $content_id) {
            if (!isset($this->c_content_items[$content_id])) {
                continue;
            }
            
            $item = $this->c_content_items[$content_id];
            
            if (($item['type'] ?? 'ai') === 'api') {
                // API連動要素 → AIには書かせない
                $api_excludes[] = array(
                    'name' => $item['name'],
                    'exclude' => $item['prompt_exclude'],
                );
            } else {
                // AI生成要素 → AIに指示
                $ai_includes[] = array(
                    'name' => $item['name'],
                    'prompt' => $item['prompt'],
                );
            }
        }

        $prompt = '';

        // AI生成要素の指示
        if (!empty($ai_includes)) {
            $prompt .= "【必須コンテンツ要素】\n";
            $prompt .= "以下の要素を記事に必ず含めてください：\n\n";

            $count = 0;
            foreach ($ai_includes as $item) {
                $count++;
                $prompt .= "■ {$count}. {$item['name']}\n";
                $prompt .= "{$item['prompt']}\n\n";
            }

            $prompt .= "※上記の要素は記事の適切な箇所に自然に組み込んでください。\n\n";
        }

        // API連動要素の除外指示
        if (!empty($api_excludes)) {
            $prompt .= "【以下の内容は記載しないでください】\n";
            $prompt .= "※これらは別途APIから最新データを自動表示します\n\n";

            foreach ($api_excludes as $item) {
                $prompt .= "✗ {$item['name']}\n";
                $prompt .= "  → {$item['exclude']}\n\n";
            }
        }

        return $prompt;
    }
}