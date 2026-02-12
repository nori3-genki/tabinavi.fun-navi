<?php
namespace HRS\Core;

if (!defined('ABSPATH')) exit;

/**
 * 5D Review Builder メインクラス
 * 
 * HRS\Core\HotelReviewSystem
 * 
 * @package HRS
 * @version 4.5.1-GPT4O-MINI
 * 
 * 修正内容:
 * - gpt-4o-mini をデフォルトモデルに設定
 * - API URL 末尾の余分なスペースを削除（致命的バグ修正）
 * - HTTPステータスコードのエラーチェックを強化
 * - タイトル生成ロジック改善（不適切な特徴ワード除外）
 * - 本文から魅力的なキーワードを抽出
 * - ごみ箱移動時のタイムアウト問題を解決
 * - 再帰的save_post呼び出しを防止
 * - SEO最適化の重複実行を防止
 */
class HotelReviewSystem {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * バージョン（コンストラクタで設定）
     */
    public $version = '4.5.1-GPT4O-MINI';

    /**
     * コンポーネント
     */
    public $data_collector = null;
    public $prompt_engine = null;
    public $auto_generator = null;

    /**
     * 再帰防止フラグ
     */
    private static $is_optimizing = false;

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        // バージョンを定数から設定（定義されていれば）
        if (defined('FIVE_DRB_VERSION')) {
            $this->version = FIVE_DRB_VERSION;
        }
        
        $this->init_components();
        $this->init_hooks();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[5DRB] Hotel Review System initialized (v' . $this->version . ')');
        }
    }

    /**
     * コンポーネント初期化
     */
    private function init_components() {
        // Post Enhancer（手動投稿時のOTAリンク・アイキャッチ自動追加）
       if (class_exists('\\HRS_Post_Enhancer')) {
    \HRS_Post_Enhancer::get_instance();
}

        // Backwards-compatible checks for both namespaced and legacy class names
        if (class_exists('\\HRS\\Core\\DataCollector')) {
            $this->data_collector = new \HRS\Core\DataCollector();
        } elseif (class_exists('HRS_Data_Collector')) {
            $this->data_collector = new \HRS_Data_Collector();
        }

        if (class_exists('\\HRS\\Core\\PromptEngine') || class_exists('\\HRS\\Core\\Prompt_Engine')) {
            // try multiple possible names
            if (class_exists('\\HRS\\Core\\PromptEngine')) {
                $this->prompt_engine = new \HRS\Core\PromptEngine();
            } elseif (class_exists('\\HRS\\Core\\Prompt_Engine')) {
                $this->prompt_engine = new \HRS\Core\Prompt_Engine();
            } elseif (class_exists('HRS_Prompt_Engine')) {
                $this->prompt_engine = new \HRS_Prompt_Engine();
            }
        } else {
            if (class_exists('HRS_Prompt_Engine')) {
                $this->prompt_engine = new \HRS_Prompt_Engine();
            }
        }

        if (class_exists('\\HRS\\Core\\AutoGenerator') || class_exists('\\HRS\\Core\\Auto_Generator')) {
            if (class_exists('\\HRS\\Core\\AutoGenerator')) {
                $this->auto_generator = new \HRS\Core\AutoGenerator();
            } elseif (class_exists('\\HRS\\Core\\Auto_Generator')) {
                $this->auto_generator = new \HRS\Core\Auto_Generator();
            }
        } elseif (class_exists('HRS_Auto_Generator')) {
            $this->auto_generator = new \HRS_Auto_Generator();
        }
    }

    /**
     * フック初期化
     */
    private function init_hooks() {
        // SEO最適化クラスの自動実行（記事保存時）
        add_action('save_post_hotel-review', array($this, 'on_post_save'), 50, 2);
    }

    /**
     * 記事保存時の処理
     */
    public function on_post_save($post_id, $post) {
        // ========== 早期リターン条件 ==========
        
        // 再帰防止（最重要）
        if (self::$is_optimizing) {
            return;
        }

        // 自動保存はスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // リビジョンはスキップ
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // ★ ごみ箱移動・削除時はスキップ（タイムアウト防止）
        if ($post->post_status === 'trash') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[5DRB] Skipping optimization for trashed post: ' . $post_id);
            }
            return;
        }

        // ★ 一括操作中はスキップ
        if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] === 'trash') {
            return;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ★ 公開・下書き状態のみ最適化（ごみ箱・非公開等は除外）
        $allowed_statuses = array('publish', 'draft', 'pending', 'future');
        if (!in_array($post->post_status, $allowed_statuses, true)) {
            return;
        }

        // ========== SEO最適化実行 ==========
        self::$is_optimizing = true;
        
        try {
            $this->run_seo_optimizations($post_id, $post);
        } catch (\Exception $e) {
            error_log('[5DRB] SEO optimization error: ' . $e->getMessage());
        }
        
        self::$is_optimizing = false;
    }

    /**
     * SEO最適化を実行
     */
    private function run_seo_optimizations($post_id, $post) {
        // Yoast SEO最適化（フックなしクラスのみ手動実行）
        if (class_exists('\\HRS\\Core\\YoastSEOOptimizer') || class_exists('HRS_Yoast_SEO_Optimizer')) {
            try {
                if (class_exists('\\HRS\\Core\\YoastSEOOptimizer')) {
                    $optimizer = new \HRS\Core\YoastSEOOptimizer();
                } else {
                    $optimizer = new \HRS_Yoast_SEO_Optimizer();
                }
                if (method_exists($optimizer, 'optimize_yoast_seo')) {
                    $optimizer->optimize_yoast_seo($post_id, $post);
                }
            } catch (\Exception $e) {
                error_log('[5DRB] Yoast SEO Optimizer error: ' . $e->getMessage());
            }
        }

        // アイキャッチ画像取得（公開時のみ、かつ未設定時のみ）
        if ($post->post_status === 'publish' && !has_post_thumbnail($post_id)) {
            if (class_exists('\\HRS\\Core\\RakutenImageFetcher') || class_exists('HRS_Rakuten_Image_Fetcher')) {
                try {
                    if (class_exists('\\HRS\\Core\\RakutenImageFetcher')) {
                        $fetcher = new \HRS\Core\RakutenImageFetcher();
                    } else {
                        $fetcher = new \HRS_Rakuten_Image_Fetcher();
                    }
                    
                    $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
                    if (!empty($hotel_name) && method_exists($fetcher, 'set_featured_image')) {
                        $fetcher->set_featured_image($post_id, $hotel_name);
                    }
                } catch (\Exception $e) {
                    error_log('[5DRB] Rakuten Image Fetcher error: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * 記事生成（統合メソッド）
     */
    public function generate_article($hotel_name, $options = array()) {
        $defaults = array(
            'location' => '',
            'ai_model' => 'gpt-4o-mini',
            'post_status' => 'draft',
            'style' => 'story',
            'persona' => 'general',
            'tone' => 'casual',
        );
        $options = wp_parse_args($options, $defaults);

        try {
            // Step 1: データ収集
            $hotel_data = $this->collect_data($hotel_name, $options['location']);

            // Step 2: プロンプト生成
            $prompt = $this->generate_prompt($hotel_data, $options);

            // Step 3: AI記事生成
            $content = $this->call_ai_api($prompt, $options['ai_model']);

            // Step 4: 投稿作成
            $post_id = $this->create_post($hotel_name, $content, $hotel_data, $options);

            return $post_id;

        } catch (\Exception $e) {
            return new \WP_Error('generation_failed', $e->getMessage());
        }
    }

    /**
     * データ収集
     */
    private function collect_data($hotel_name, $location = '') {
        if ($this->data_collector) {
            if (method_exists($this->data_collector, 'collect_hotel_data')) {
                $data = $this->data_collector->collect_hotel_data($hotel_name, $location);
            } elseif (method_exists($this->data_collector, 'fetch_hotel_data')) {
                $data = $this->data_collector->fetch_hotel_data($hotel_name, $location);
            } else {
                $data = null;
            }

            if ($data) {
                return $data;
            }
        }

        // フォールバック
        return array(
            'hotel_name' => $hotel_name,
            'address' => $location,
            'description' => '',
            'features' => array(),
            'emotions' => array(),
            'urls' => array(),
            'sources' => array(),
            'hqc_score' => 0.5,
        );
    }

    /**
     * プロンプト生成
     */
    private function generate_prompt($hotel_data, $options) {
        if ($this->prompt_engine) {
            if (method_exists($this->prompt_engine, 'generate_5d_prompt')) {
                return $this->prompt_engine->generate_5d_prompt(
                    $hotel_data,
                    $options['style'],
                    $options['persona'],
                    $options['tone'],
                    'balanced',
                    $options['ai_model'],
                    array()
                );
            } elseif (method_exists($this->prompt_engine, 'build_prompt')) {
                return $this->prompt_engine->build_prompt($hotel_data, $options);
            }
        }

        // フォールバック
        $hotel_name = $hotel_data['hotel_name'];
        return "# {$hotel_name} の紹介記事を作成してください\n\n" .
               "## 要件\n- H2見出し6個以上\n- 2000-3000文字\n- HTML形式\n";
    }

    /**
     * AI API呼び出し（gpt-4o-mini 対応）
     * 
     * @param string $prompt プロンプト
     * @param string $model モデル名（デフォルト: gpt-4o-mini）
     * @return string 生成されたテキスト
     * @throws \Exception APIエラー時
     */
    private function call_ai_api($prompt, $model = 'gpt-4o-mini') {
        $api_key = get_option('hrs_chatgpt_api_key', '');

        if (empty($api_key)) {
            throw new \Exception('ChatGPT APIキーが設定されていません');
        }

        // モデルが空なら gpt-4o-mini を使用
        if (empty($model)) {
            $model = 'gpt-4o-mini';
        }

        // ✅ 重要な修正: URL末尾の余分なスペースを削除（'  ' → ''）
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $model,  // gpt-4o-mini で完全動作
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'max_tokens' => 4000,
                'temperature' => 0.7,
            )),
        ));

        if (is_wp_error($response)) {
            throw new \Exception('API接続エラー: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = !empty($body['error']['message']) 
                ? $body['error']['message'] 
                : 'HTTP ' . $status_code;
            throw new \Exception('API エラー (' . $status_code . '): ' . $error_msg);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('API エラー: ' . ($body['error']['message'] ?? 'unknown'));
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            throw new \Exception('APIからの応答が不正です: ' . print_r($body, true));
        }

        return $body['choices'][0]['message']['content'];
    }

    /**
     * 投稿作成
     * 
     * @param string $hotel_name ホテル名
     * @param string $content 記事本文
     * @param array $hotel_data ホテルデータ
     * @param array $options オプション
     * @return int 投稿ID
     */
    private function create_post($hotel_name, $content, $hotel_data, $options) {
        // ★ タイトル生成（改善版）
        $title = $this->generate_title($hotel_name, $content, $hotel_data);

        // 抜粋を生成
        $excerpt = $this->generate_excerpt($hotel_name, $content);

        // カテゴリを生成
        $categories = $this->generate_categories($hotel_data, $options);

        // タグを生成
        $tags = $this->generate_tags($hotel_name, $content);

        // 投稿ステータス
        $status = is_array($options) ? ($options['post_status'] ?? 'draft') : $options;

        // 投稿作成
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'hotel-review',
            'post_author' => get_current_user_id(),
        ));

        if (is_wp_error($post_id)) {
            throw new \Exception('投稿作成に失敗: ' . $post_id->get_error_message());
        }

        // カテゴリ設定
        if (!empty($categories)) {
            wp_set_object_terms($post_id, $categories, 'category', false);
        }

        // タグ設定
        if (!empty($tags)) {
            wp_set_object_terms($post_id, $tags, 'post_tag', false);
        }

        // メタデータ保存
        update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
        update_post_meta($post_id, '_hrs_hotel_address', isset($hotel_data['address']) ? $hotel_data['address'] : '');
        update_post_meta($post_id, '_hrs_ota_urls', isset($hotel_data['urls']) ? $hotel_data['urls'] : array());
        update_post_meta($post_id, '_hrs_hqc_score', isset($hotel_data['hqc_score']) ? $hotel_data['hqc_score'] : 0);
        update_post_meta($post_id, '_hrs_generated_at', current_time('mysql'));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[5DRB] Post created: ID=' . $post_id . ', Title=' . $title);
        }

        return $post_id;
    }

    /**
     * タイトル生成（改善版）
     * 
     * パターン: {ホテル名}の魅力 - {特徴キーワード}
     * 
     * @param string $hotel_name ホテル名
     * @param string $content 記事本文
     * @param array $hotel_data ホテルデータ
     * @return string タイトル
     */
    private function generate_title($hotel_name, $content, $hotel_data) {
        // 特徴キーワードを抽出
        $feature = $this->extract_title_feature($hotel_name, $content, $hotel_data);
        
        // タイトル生成
        $title = "{$hotel_name}の魅力 - {$feature}";
        
        // 60文字以内に収める
        if (mb_strlen($title, 'UTF-8') > 60) {
            $title = mb_substr($title, 0, 57, 'UTF-8') . '...';
        }
        
        return $title;
    }

    /**
     * タイトル用の特徴キーワードを抽出
     * 
     * @param string $hotel_name ホテル名
     * @param string $content 記事本文
     * @param array $hotel_data ホテルデータ
     * @return string 特徴キーワード
     */
    private function extract_title_feature($hotel_name, $content, $hotel_data) {
        // ========== 除外ワード（タイトルに不適切） ==========
        $excluded_words = array(
            // 一般的すぎる設備
            'レストラン併設', 'レストラン', '駐車場', '駐車場あり', '駐車場無料',
            'Wi-Fi', 'WiFi', 'wifi', '無料Wi-Fi', 'インターネット',
            'エレベーター', 'フロント', 'ロビー', '客室',
            '宿泊予約', '予約', '楽天トラベル', 'じゃらん', '一休',
            // 曖昧な表現
            '魅力', '特徴', 'おすすめ', '人気', '口コミ', 'クチコミ',
            '情報', '詳細', '紹介', 'ガイド', 'まとめ',
            // OTA関連
            '宿泊プラン', 'プラン', '料金', '価格', '格安', '最安値',
        );
        
        // ========== 優先キーワード（魅力的な特徴） ==========
        $priority_keywords = array(
            // 温泉・風呂系（最優先）
            '源泉かけ流し' => '源泉かけ流しの贅沢',
            '露天風呂付き客室' => '客室露天風呂の至福',
            '露天風呂' => '絶景露天風呂',
            '貸切風呂' => 'プライベート温泉',
            '大浴場' => '癒しの大浴場',
            '天然温泉' => '天然温泉の恵み',
            '温泉' => '温泉の癒し',
            
            // 景観系
            'オーシャンビュー' => '海を望む絶景',
            '海の見える' => '海の絶景',
            '海一望' => '海一望の贅沢',
            '夜景' => '煌めく夜景',
            '富士山' => '富士山を望む',
            '山の景色' => '山々の絶景',
            '絶景' => '息をのむ絶景',
            '眺望' => '美しい眺望',
            
            // 食事系
            '懐石料理' => '極上の懐石',
            '会席料理' => '本格会席の味わい',
            '部屋食' => 'プライベートな食事',
            'ビュッフェ' => '豊富なビュッフェ',
            '和食' => '繊細な和食',
            '創作料理' => 'シェフの創作料理',
            '地産地消' => '地元の恵み',
            '美食' => '美食の饗宴',
            
            // 施設・サービス系
            'スパ' => '極上スパ体験',
            'エステ' => '癒しのエステ',
            'プール' => 'リゾートプール',
            'ラウンジ' => '寛ぎのラウンジ',
            'クラブラウンジ' => 'クラブラウンジの特権',
            'バー' => '大人のバータイム',
            
            // 雰囲気系
            '隠れ家' => '隠れ家リゾート',
            '静寂' => '静寂の贅沢',
            '癒し' => '心身の癒し',
            'くつろぎ' => '至福のくつろぎ',
            'リゾート' => 'リゾートステイ',
            'ラグジュアリー' => 'ラグジュアリーな時間',
            '高級' => '上質な滞在',
            'おもてなし' => '心尽くしのおもてなし',
            
            // 特別感
            'スイート' => 'スイートルームの贅沢',
            '特別室' => '特別な空間',
            'デザイナーズ' => 'デザイナーズ空間',
            '伝統' => '伝統の趣',
            '歴史' => '歴史ある佇まい',
            'モダン' => 'モダンな洗練',
        );
        
        // HTMLタグを除去
        $plain_content = wp_strip_all_tags($content);
        
        // ========== Step 1: 優先キーワードを本文から検索 ==========
        foreach ($priority_keywords as $keyword => $feature_text) {
            if (mb_strpos($plain_content, $keyword) !== false) {
                return $feature_text;
            }
        }
        
        // ========== Step 2: hotel_dataのfeaturesから有効なものを探す ==========
        $features = isset($hotel_data['features']) ? $hotel_data['features'] : array();
        foreach ($features as $feature) {
            $feature = trim($feature);
            if (empty($feature)) continue;
            
            // 除外ワードに含まれていないかチェック
            $is_excluded = false;
            foreach ($excluded_words as $excluded) {
                if (mb_strpos($feature, $excluded) !== false) {
                    $is_excluded = true;
                    break;
                }
            }
            
            if (!$is_excluded && mb_strlen($feature, 'UTF-8') >= 2 && mb_strlen($feature, 'UTF-8') <= 15) {
                return $feature;
            }
        }
        
        // ========== Step 3: ホテル名から特徴を推測 ==========
        if (mb_strpos($hotel_name, '温泉') !== false) {
            return '温泉の癒し';
        }
        if (preg_match('/(リゾート|resort)/iu', $hotel_name)) {
            return 'リゾートステイ';
        }
        if (preg_match('/(旅館|ryokan)/iu', $hotel_name)) {
            return '和の趣';
        }
        if (preg_match('/(スパ|spa)/iu', $hotel_name)) {
            return 'スパ体験';
        }
        
        // ========== Step 4: 地域名があれば使用 ==========
        $address = isset($hotel_data['address']) ? $hotel_data['address'] : '';
        $areas = array(
            '箱根' => '箱根の癒し',
            '熱海' => '熱海の寛ぎ',
            '伊豆' => '伊豆の魅力',
            '軽井沢' => '軽井沢の上質',
            '京都' => '京都の風情',
            '沖縄' => '沖縄の楽園',
            '北海道' => '北海道の大自然',
        );
        foreach ($areas as $area => $area_feature) {
            if (mb_strpos($hotel_name . $address, $area) !== false) {
                return $area_feature;
            }
        }
        
        // ========== フォールバック ==========
        return '特別な滞在';
    }

    /**
     * 抜粋を生成
     * 
     * @param string $hotel_name ホテル名
     * @param string $content 記事本文
     * @return string 抜粋（160文字以内）
     */
    private function generate_excerpt($hotel_name, $content) {
        // HTMLタグを除去
        $plain = wp_strip_all_tags($content);
        
        // 改行・余分なスペースを整理
        $plain = preg_replace('/\s+/', ' ', trim($plain));

        // 本文が空の場合はテンプレートから生成
        if (empty($plain)) {
            return $hotel_name . 'の魅力・設備・アクセスを徹底レビュー。実際の宿泊体験に基づいた詳細情報をお届けします。';
        }

        // キーフレーズ（ホテル名）を含む部分を探す
        $pos = mb_strpos($plain, $hotel_name, 0, 'UTF-8');
        
        if ($pos !== false && $pos < 100) {
            // ホテル名が冒頭付近にある場合、その位置から抽出
            $start = max(0, $pos - 10);
            $excerpt = mb_substr($plain, $start, 155, 'UTF-8');
        } else {
            // 冒頭から抽出
            $excerpt = mb_substr($plain, 0, 155, 'UTF-8');
        }

        // 文の途中で切れた場合は句読点まで調整
        $last_period = mb_strrpos($excerpt, '。', 0, 'UTF-8');
        if ($last_period !== false && $last_period > 80) {
            $excerpt = mb_substr($excerpt, 0, $last_period + 1, 'UTF-8');
        } else {
            // 句読点がない場合は「...」を追加
            $excerpt = rtrim($excerpt) . '...';
        }

        return $excerpt;
    }

    /**
     * カテゴリを生成
     * 
     * @param array $hotel_data ホテルデータ
     * @param array $options オプション
     * @return array カテゴリ名の配列
     */
    private function generate_categories($hotel_data, $options = array()) {
        $categories = array();

        // ペルソナからカテゴリ
        $persona = is_array($options) ? ($options['persona'] ?? '') : '';
        $persona_map = array(
            'couple' => 'カップル',
            'family' => 'ファミリー',
            'solo' => 'ソロ',
            'senior' => 'シニア',
            'workation' => 'ワーケーション',
            'luxury' => 'ラグジュアリー',
            'budget' => 'リーズナブル',
        );
        if (!empty($persona) && isset($persona_map[$persona])) {
            $categories[] = $persona_map[$persona];
        }

        // 都道府県からカテゴリ
        $address = isset($hotel_data['address']) ? $hotel_data['address'] : '';
        $prefectures = '北海道|青森|岩手|宮城|秋田|山形|福島|茨城|栃木|群馬|埼玉|千葉|東京|神奈川|新潟|富山|石川|福井|山梨|長野|岐阜|静岡|愛知|三重|滋賀|京都|大阪|兵庫|奈良|和歌山|鳥取|島根|岡山|広島|山口|徳島|香川|愛媛|高知|福岡|佐賀|長崎|熊本|大分|宮崎|鹿児島|沖縄';
        
        if (preg_match('/(' . $prefectures . ')/u', $address, $m)) {
            $categories[] = $m[1];
        }

        return $categories;
    }

    /**
     * タグを生成
     * 
     * @param string $hotel_name ホテル名
     * @param string $content 記事本文
     * @return array タグ名の配列
     */
    private function generate_tags($hotel_name, $content) {
        $tags = array();

        // ホテル名から特徴を抽出
        if (mb_strpos($hotel_name, '温泉') !== false) {
            $tags[] = '温泉';
        }
        if (preg_match('/(リゾート|resort)/iu', $hotel_name)) {
            $tags[] = 'リゾート';
        }
        if (preg_match('/(旅館|ryokan)/iu', $hotel_name)) {
            $tags[] = '旅館';
        }
        if (preg_match('/(ホテル|hotel)/iu', $hotel_name)) {
            $tags[] = 'ホテル';
        }

        // コンテンツから特徴を抽出
        $keywords = array(
            '露天風呂', '貸切風呂', '大浴場', '源泉かけ流し',
            'オーシャンビュー', '夜景', '絶景',
            'ビュッフェ', '懐石', '部屋食', '和食', '洋食',
            'スパ', 'エステ', 'プール',
            'ペット可', '子連れ', 'バリアフリー',
            '駅近', '送迎あり', '駐車場無料',
        );
        
        foreach ($keywords as $kw) {
            if (mb_strpos($content, $kw) !== false) {
                $tags[] = $kw;
            }
        }

        // 温泉地名を抽出
        $onsen_areas = array(
            '箱根', '熱海', '伊豆', '草津', '別府', '由布院', '道後',
            '城崎', '有馬', '白浜', '鬼怒川', '那須', '軽井沢',
            '登別', '定山渓', '洞爺湖', '層雲峡', '阿寒',
            '銀山', '蔵王', '秋保', '鳴子', '花巻',
            '越後湯沢', '苗場', '月岡', '瀬波',
            '山代', '山中', '和倉', '加賀',
            '下呂', '飛騨高山', '奥飛騨',
            '修善寺', '伊東', '稲取', '堂ヶ島',
            '南紀', '勝浦', '串本',
            '皆生', '玉造', '三朝',
            '指宿', '霧島', '黒川',
        );
        
        foreach ($onsen_areas as $area) {
            if (mb_strpos($hotel_name . $content, $area) !== false) {
                $tags[] = $area;
                break; // 1つだけ追加
            }
        }

        return array_unique($tags);
    }

    /**
     * API設定チェック
     */
    public function is_api_configured() {
        $chatgpt = get_option('hrs_chatgpt_api_key', '');
        $google_key = get_option('hrs_google_cse_api_key', '');
        $google_id = get_option('hrs_google_cse_id', '');

        return !empty($chatgpt) && !empty($google_key) && !empty($google_id);
    }

    /**
     * システム情報取得
     */
    public function get_system_info() {
        return array(
            'version' => $this->version,
            'api_configured' => $this->is_api_configured(),
            'data_collector' => $this->data_collector !== null,
            'prompt_engine' => $this->prompt_engine !== null,
            'auto_generator' => $this->auto_generator !== null,
            'post_type_exists' => post_type_exists('hotel-review'),
        );
    }
}

// Backwards compatibility: provide legacy global class alias and legacy function if not already defined
if (!class_exists('HRS_Hotel_Review_System')) {
    class_alias('HRS\\Core\\HotelReviewSystem', 'HRS_Hotel_Review_System');
}

if (!function_exists('hrs')) {
    function hrs() {
        return \HRS\Core\HotelReviewSystem::get_instance();
    }
}