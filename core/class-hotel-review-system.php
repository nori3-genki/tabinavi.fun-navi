<?php
/**
 * 5D Review Builder メインクラス（統合版）
 *
 * @package HRS
 * @version 5.0.2-PERSONA-8
 *
 * 修正: hotel-category → category (標準カテゴリ)
 * 修正: ペルソナマッピング 6個 → 8個
 * 修正: クラス二重定義防止処理の追加
 */

if (!defined('ABSPATH')) exit;

// エラー回避: 既にクラスが存在する場合は定義をスキップする
if (!class_exists('HRS_Hotel_Review_System')) {

    class HRS_Hotel_Review_System {

        private static $instance = null;
        public $version = '5.0.2-PERSONA-8';

        // コンポーネント
        public $data_collector = null;
        public $prompt_engine = null;
        public $auto_generator = null;
        public $ota_fetcher = null;
        public $content_validator = null;
        public $duplicate_checker = null;
        public $error_recovery = null;
        public $image_optimizer = null;
        public $metadata_manager = null;

        private static $is_optimizing = false;

        public static function get_instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            if (defined('FIVE_DRB_VERSION')) {
                $this->version = FIVE_DRB_VERSION;
            }
            $this->init_components();
            $this->init_hooks();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[5DRB] Hotel Review System initialized (v' . $this->version . ')');
            }
        }

        private function init_components() {
            if (class_exists('HRS_Post_Enhancer')) {
                try { \HRS_Post_Enhancer::get_instance(); } catch (Exception $e) { $this->log_error('Post Enhancer', $e); }
            }
            if (class_exists('HRS_Data_Collector')) {
                try { $this->data_collector = new \HRS_Data_Collector(); } catch (Exception $e) { $this->log_error('Data Collector', $e); }
            }
            if (class_exists('HRS_Prompt_Engine')) {
                try { $this->prompt_engine = new \HRS_Prompt_Engine(); } catch (Exception $e) { $this->log_error('Prompt Engine', $e); }
            }
            if (class_exists('HRS_Auto_Generator')) {
                try { $this->auto_generator = new \HRS_Auto_Generator(); } catch (Exception $e) { $this->log_error('Auto Generator', $e); }
            }
            if (class_exists('HRS_OTA_URL_Fetcher')) {
                try { $this->ota_fetcher = new \HRS_OTA_URL_Fetcher(); } catch (Exception $e) { $this->log_error('OTA Fetcher', $e); }
            }
            if (class_exists('HRS_Content_Validator')) {
                try { $this->content_validator = new \HRS_Content_Validator(); } catch (Exception $e) { $this->log_error('Content Validator', $e); }
            }
            if (class_exists('HRS_Duplicate_Checker')) {
                try { $this->duplicate_checker = new \HRS_Duplicate_Checker(); } catch (Exception $e) { $this->log_error('Duplicate Checker', $e); }
            }
            if (class_exists('HRS_Error_Recovery')) {
                try { $this->error_recovery = new \HRS_Error_Recovery(); } catch (Exception $e) { $this->log_error('Error Recovery', $e); }
            }
            if (class_exists('HRS_Image_Optimizer')) {
                try { $this->image_optimizer = new \HRS_Image_Optimizer(); } catch (Exception $e) { $this->log_error('Image Optimizer', $e); }
            }
            if (class_exists('HRS_Metadata_Manager')) {
                try { $this->metadata_manager = new \HRS_Metadata_Manager(); } catch (Exception $e) { $this->log_error('Metadata Manager', $e); }
            }
        }

        private function log_error($component, Exception $e) {
            error_log("[5DRB] {$component} init error: " . $e->getMessage());
        }

        private function init_hooks() {
            add_action('save_post_hotel-review', array($this, 'on_post_save'), 50, 2);
        }

        public function on_post_save($post_id, $post) {
            if (self::$is_optimizing) return;
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (wp_is_post_revision($post_id)) return;
            if ($post->post_status === 'trash') return;
            if (!current_user_can('edit_post', $post_id)) return;

            $allowed = array('publish', 'draft', 'pending', 'future');
            if (!in_array($post->post_status, $allowed, true)) return;

            self::$is_optimizing = true;
            try {
                $this->run_seo_optimizations($post_id, $post);
            } catch (Exception $e) {
                error_log('[5DRB] SEO error: ' . $e->getMessage());
            } finally {
                self::$is_optimizing = false;
            }
        }

        private function run_seo_optimizations($post_id, $post) {
            if (class_exists('HRS_Yoast_SEO_Optimizer')) {
                try {
                    $optimizer = new \HRS_Yoast_SEO_Optimizer();
                    if (method_exists($optimizer, 'optimize_yoast_seo')) {
                        $optimizer->optimize_yoast_seo($post_id, $post);
                    }
                } catch (Exception $e) {
                    error_log('[5DRB] Yoast error: ' . $e->getMessage());
                }
            }

            if ($post->post_status === 'publish') {
                if (!has_post_thumbnail($post_id) && class_exists('HRS_Rakuten_Image_Fetcher')) {
                    try {
                        $fetcher = new \HRS_Rakuten_Image_Fetcher();
                        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
                        if (!empty($hotel_name) && method_exists($fetcher, 'set_featured_image')) {
                            $fetcher->set_featured_image($post_id, $hotel_name);
                        }
                    } catch (Exception $e) {
                        error_log('[5DRB] Image fetch error: ' . $e->getMessage());
                    }
                }

                if ($this->image_optimizer && has_post_thumbnail($post_id)) {
                    try {
                        $thumbnail_id = get_post_thumbnail_id($post_id);
                        $this->image_optimizer->optimize_featured_image($thumbnail_id);
                    } catch (Exception $e) {
                        error_log('[5DRB] Image optimize error: ' . $e->getMessage());
                    }
                }
            }
        }

        public function generate_article($hotel_name, $options = array()) {
            $defaults = array(
                'location' => '',
                'ai_model' => 'gpt-4o-mini',
                'post_status' => 'draft',
                'style' => 'story',
                'persona' => 'general',
                'tone' => 'casual',
                'optimized_prompt' => '',
                'skip_duplicate_check' => false,
                'auto_regenerate' => true,
                'max_regeneration_attempts' => 3,
            );
            $options = wp_parse_args($options, $defaults);

            $generation_start = microtime(true);

            try {
                if (!$options['skip_duplicate_check'] && $this->duplicate_checker) {
                    $duplicate_result = $this->duplicate_checker->check($hotel_name, $options['location']);
                    
                    if ($duplicate_result['has_duplicate'] && $duplicate_result['exact_match']) {
                        $existing_id = $duplicate_result['exact_match']['id'];
                        
                        if (!empty($options['update_existing'])) {
                            return $this->regenerate_article($existing_id, $options);
                        }

                        return new \WP_Error(
                            'duplicate_found',
                            $duplicate_result['message'],
                            array(
                                'existing_post_id' => $existing_id,
                                'edit_url' => $duplicate_result['exact_match']['edit_url'],
                            )
                        );
                    }
                }

                $hotel_data = $this->collect_data($hotel_name, $options['location']);

                if (!empty($options['optimized_prompt'])) {
                    $prompt = $options['optimized_prompt'];
                } else {
                    $prompt = $this->generate_prompt($hotel_data, $options);
                    $prompt = apply_filters('hrs_before_generate_prompt', $prompt, $hotel_name, $options);
                }

                $api_result = $this->call_ai_api_with_recovery($prompt, $options['ai_model']);
                
                if (is_wp_error($api_result)) {
                    return $api_result;
                }

                $content = $api_result['content'];
                $api_response = $api_result;

                $validation_result = null;
                if ($this->content_validator) {
                    $validation_result = $this->content_validator->validate($content, array(
                        'hotel_name' => $hotel_name,
                        'persona' => $options['persona'],
                    ));

                    if ($options['auto_regenerate'] && $this->content_validator->requires_regeneration()) {
                        $regeneration_attempts = 0;
                        
                        while ($regeneration_attempts < $options['max_regeneration_attempts']) {
                            $regeneration_attempts++;
                            
                            $improvement = $this->content_validator->get_improvement_prompt();
                            $retry_prompt = $prompt . "\n\n【重要】" . $improvement;
                            
                            $retry_result = $this->call_ai_api_with_recovery($retry_prompt, $options['ai_model']);
                            
                            if (is_wp_error($retry_result)) {
                                break;
                            }

                            $content = $retry_result['content'];
                            $api_response = $retry_result;
                            
                            $validation_result = $this->content_validator->validate($content, array(
                                'hotel_name' => $hotel_name,
                            ));

                            if (!$this->content_validator->requires_regeneration()) {
                                break;
                            }
                        }
                    }
                }

                $content = $this->sanitize_ai_content($content);

                $post_id = $this->create_post($hotel_name, $content, $hotel_data, $options);

                if (is_wp_error($post_id)) {
                    return $post_id;
                }

                $generation_end = microtime(true);

                if ($this->metadata_manager) {
                    $this->metadata_manager->record_generation_metadata($post_id, array(
                        'hotel_name' => $hotel_name,
                        'location' => $options['location'],
                        'ai_model' => $options['ai_model'],
                        'persona' => $options['persona'],
                        'prompt' => $prompt,
                        'content' => $content,
                        'api_response' => $api_response,
                        'generation_start' => $generation_start,
                        'generation_end' => $generation_end,
                    ));

                    if ($validation_result) {
                        $this->metadata_manager->record_validation_score($post_id, $validation_result);
                    }
                }

                do_action('hrs_after_generate_article', $post_id, $content, $hotel_data, $options);

                return $post_id;

            } catch (Exception $e) {
                return new \WP_Error('generation_failed', $e->getMessage());
            }
        }

        public function regenerate_article($post_id, $options = array()) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'hotel-review') {
                return new \WP_Error('invalid_post', '指定された記事が見つかりません');
            }

            $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
            if (empty($hotel_name)) {
                $hotel_name = $post->post_title;
            }

            $options['skip_duplicate_check'] = true;
            $options['update_existing'] = false;

            $hotel_data = $this->collect_data($hotel_name, $options['location'] ?? '');
            $prompt = $this->generate_prompt($hotel_data, $options);
            
            $api_result = $this->call_ai_api_with_recovery($prompt, $options['ai_model'] ?? 'gpt-4o-mini');
            
            if (is_wp_error($api_result)) {
                return $api_result;
            }

            $content = $this->sanitize_ai_content($api_result['content']);

            $ota_section = $this->generate_ota_section($hotel_name, $hotel_data);
            if (!empty($ota_section)) {
                $content = rtrim($content) . "\n\n" . $ota_section;
            }

            if ($this->duplicate_checker) {
                $result = $this->duplicate_checker->update_existing_post($post_id, $content, array(
                    'note' => '自動再生成',
                ));

                if (is_wp_error($result)) {
                    return $result;
                }
            } else {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $content,
                ));
            }

            if ($this->metadata_manager) {
                $this->metadata_manager->add_to_history($post_id, array(
                    'type' => 'regeneration',
                    'model' => $options['ai_model'] ?? 'gpt-4o-mini',
                    'tokens' => $api_result['usage']['total_tokens'] ?? 0,
                ));
            }

            return $post_id;
        }

        private function collect_data($hotel_name, $location = '') {
            $data = array(
                'hotel_name' => $hotel_name,
                'address' => $location,
                'description' => '',
                'features' => array(),
                'urls' => array(),
                'hqc_score' => 0.5,
            );

            if ($this->data_collector && method_exists($this->data_collector, 'collect_hotel_data')) {
                try {
                    $collected = $this->data_collector->collect_hotel_data($hotel_name, $location);
                    if ($collected) {
                        $data = array_merge($data, $collected);
                    }
                } catch (Exception $e) {
                    error_log('[5DRB] DataCollector error: ' . $e->getMessage());
                }
            }

            if ($this->ota_fetcher && (empty($data['urls']) || count($data['urls']) < 3)) {
                try {
                    $ota_urls = $this->ota_fetcher->fetch_urls($hotel_name, $location);
                    if (!empty($ota_urls)) {
                        $data['urls'] = array_merge($data['urls'] ?? array(), $ota_urls);
                    }
                } catch (Exception $e) {
                    error_log('[5DRB] OTA Fetcher error: ' . $e->getMessage());
                }
            }

            return $data;
        }

        private function generate_prompt($hotel_data, $options) {
            if ($this->prompt_engine && method_exists($this->prompt_engine, 'generate_5d_prompt')) {
                try {
                    return $this->prompt_engine->generate_5d_prompt(
                        $hotel_data,
                        $options['style'],
                        $options['persona'],
                        $options['tone'],
                        'balanced',
                        $options['ai_model'],
                        array()
                    );
                } catch (Exception $e) {
                    error_log('[5DRB] PromptEngine error: ' . $e->getMessage());
                }
            }

            $hotel_name = isset($hotel_data['hotel_name']) ? $hotel_data['hotel_name'] : '';
            return "# {$hotel_name} の紹介記事\n\n## 要件\n- H2見出し6個以上\n- 2000-3000文字\n- HTML形式\n";
        }

        private function call_ai_api_with_recovery($prompt, $model) {
            if ($this->error_recovery) {
                return $this->error_recovery->openai_call_with_retry($prompt, $model, array(
                    'operation_name' => 'Article Generation',
                    'max_retries' => 3,
                ));
            }

            return $this->call_ai_api_direct($prompt, $model);
        }

        private function call_ai_api_direct($prompt, $model) {
            $api_key = get_option('hrs_chatgpt_api_key', '');
            if (empty($api_key)) {
                return new \WP_Error('no_api_key', 'ChatGPT APIキーが設定されていません');
            }

            $body = array(
                'model' => $model,
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'max_tokens' => 4000,
                'temperature' => 0.7,
            );

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'timeout' => 120,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($body),
            ));

            if (is_wp_error($response)) {
                return new \WP_Error('api_error', 'API接続エラー: ' . $response->get_error_message());
            }

            $resp_body = wp_remote_retrieve_body($response);
            $decoded = json_decode($resp_body, true);

            if (isset($decoded['error'])) {
                $msg = is_array($decoded['error']) ? $decoded['error']['message'] : $decoded['error'];
                return new \WP_Error('api_error', 'APIエラー: ' . $msg);
            }

            if (!isset($decoded['choices'][0]['message']['content'])) {
                return new \WP_Error('api_error', 'APIからの応答が不正です');
            }

            return array(
                'content' => $decoded['choices'][0]['message']['content'],
                'usage' => $decoded['usage'] ?? null,
                'model' => $decoded['model'] ?? $model,
            );
        }

        private function sanitize_ai_content($content) {
            if (!is_string($content)) return '';
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
            return $content;
        }

        private function create_post($hotel_name, $content, $hotel_data, $options) {
            $title = $this->generate_title($hotel_name, $content, $hotel_data);
            $excerpt = $this->generate_excerpt($hotel_name, $content);
            $categories = $this->generate_categories($hotel_data, $options);
            $tags = $this->generate_tags($hotel_name, $content);
            $status = isset($options['post_status']) ? $options['post_status'] : 'draft';

            $ota_section = $this->generate_ota_section($hotel_name, $hotel_data);
            if (!empty($ota_section)) {
                $content = rtrim($content) . "\n\n" . $ota_section;
            }

            $post_arr = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status' => $status,
                'post_type' => 'hotel-review',
                'post_author' => get_current_user_id(),
            );

            $post_id = wp_insert_post($post_arr);

            if (is_wp_error($post_id)) {
                return $post_id;
            }

            // 標準カテゴリに変更
            if (!empty($categories)) {
                wp_set_object_terms($post_id, $categories, 'category', false);
            }

            if (!empty($tags)) {
                wp_set_object_terms($post_id, $tags, 'post_tag', false);
            }

            update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
            update_post_meta($post_id, '_hrs_hotel_address', $hotel_data['address'] ?? '');
            update_post_meta($post_id, '_hrs_location', $options['location'] ?? '');
            update_post_meta($post_id, '_hrs_ota_urls', $hotel_data['urls'] ?? array());
            update_post_meta($post_id, '_hrs_hqc_score', $hotel_data['hqc_score'] ?? 0);
            update_post_meta($post_id, '_hrs_generated_at', current_time('mysql'));
            update_post_meta($post_id, '_hrs_ai_model', $options['ai_model'] ?? '');
            update_post_meta($post_id, '_hrs_persona', $options['persona'] ?? '');

            return $post_id;
        }

        private function generate_title($hotel_name, $content, $hotel_data) {
            $priority_keywords = array(
                '源泉かけ流し' => '源泉かけ流しの贅沢',
                '露天風呂付き客室' => '客室露天風呂の至福',
                '露天風呂' => '絶景露天風呂',
                '貸切風呂' => 'プライベート温泉',
                '大浴場' => '癒しの大浴場',
                '天然温泉' => '天然温泉の恵み',
                'オーシャンビュー' => '海を望む絶景',
                '夜景' => '煌めく夜景',
                '富士山' => '富士山を望む',
                '懐石料理' => '極上の懐石',
                'スパ' => '極上スパ体験',
            );

            $plain = wp_strip_all_tags($content);

            foreach ($priority_keywords as $kw => $feat) {
                if (mb_stripos($plain, $kw, 0, 'UTF-8') !== false) {
                    $title = "{$hotel_name}の魅力 - {$feat}";
                    return mb_strlen($title, 'UTF-8') > 60 ? mb_substr($title, 0, 57, 'UTF-8') . '...' : $title;
                }
            }

            if (!empty($hotel_data['features']) && is_array($hotel_data['features'])) {
                foreach ($hotel_data['features'] as $f) {
                    $f = trim((string) $f);
                    if ($f === '') continue;
                    if (mb_strlen($f, 'UTF-8') >= 2 && mb_strlen($f, 'UTF-8') <= 20) {
                        $title = "{$hotel_name}の魅力 - {$f}";
                        return mb_strlen($title, 'UTF-8') > 60 ? mb_substr($title, 0, 57, 'UTF-8') . '...' : $title;
                    }
                }
            }

            return "{$hotel_name}の魅力 - 特別な滞在";
        }

        private function generate_excerpt($hotel_name, $content) {
            $plain = wp_strip_all_tags($content);
            $plain = preg_replace('/\s+/', ' ', trim($plain));
            if ($plain === '') {
                return $hotel_name . 'の魅力・設備・アクセスを徹底レビュー。';
            }

            $excerpt = mb_substr($plain, 0, 155, 'UTF-8');
            $pos = mb_strrpos($excerpt, '。', 0, 'UTF-8');
            if ($pos !== false && $pos > 80) {
                return mb_substr($excerpt, 0, $pos + 1, 'UTF-8');
            }
            return rtrim($excerpt) . '...';
        }

        private function generate_categories($hotel_data, $options = array()) {
            $categories = array();
            $persona = isset($options['persona']) ? $options['persona'] : '';
            
            $persona_map = array(
                'general'   => array('name' => '一般・観光',       'slug' => 'general'),
                'solo'      => array('name' => '一人旅',           'slug' => 'solo'),
                'couple'    => array('name' => 'カップル・夫婦',   'slug' => 'couple'),
                'family'    => array('name' => 'ファミリー',       'slug' => 'family'),
                'senior'    => array('name' => 'シニア',           'slug' => 'senior'),
                'workation' => array('name' => 'ワーケーション',   'slug' => 'workation'),
                'luxury'    => array('name' => 'ラグジュアリー',   'slug' => 'luxury'),
                'budget'    => array('name' => 'コスパ重視',       'slug' => 'budget'),
            );

            if (!empty($persona) && isset($persona_map[$persona])) {
                $cat = $persona_map[$persona];
                $id = $this->ensure_category_exists($cat['name'], $cat['slug']);
                if ($id) $categories[] = $id;
            }

            $address = isset($hotel_data['address']) ? $hotel_data['address'] : '';
            $location = isset($options['location']) ? $options['location'] : '';
            $search = $address . ' ' . $location;

            $prefs = array(
                '北海道'=>'hokkaido','青森'=>'aomori','岩手'=>'iwate','宮城'=>'miyagi','秋田'=>'akita',
                '山形'=>'yamagata','福島'=>'fukushima','茨城'=>'ibaraki','栃木'=>'tochigi','群馬'=>'gunma',
                '埼玉'=>'saitama','千葉'=>'chiba','東京'=>'tokyo','神奈川'=>'kanagawa','新潟'=>'niigata',
                '富山'=>'toyama','石川'=>'ishikawa','福井'=>'fukui','山梨'=>'yamanashi','長野'=>'nagano',
                '岐阜'=>'gifu','静岡'=>'shizuoka','愛知'=>'aichi','三重'=>'mie','滋賀'=>'shiga',
                '京都'=>'kyoto','大阪'=>'osaka','兵庫'=>'hyogo','奈良'=>'nara','和歌山'=>'wakayama',
                '鳥取'=>'tottori','島根'=>'shimane','岡山'=>'okayama','広島'=>'hiroshima','山口'=>'yamaguchi',
                '徳島'=>'tokushima','香川'=>'kagawa','愛媛'=>'ehime','高知'=>'kochi','福岡'=>'fukuoka',
                '佐賀'=>'saga','長崎'=>'nagasaki','熊本'=>'kumamoto','大分'=>'oita','宮崎'=>'miyazaki',
                '鹿児島'=>'kagoshima','沖縄'=>'okinawa',
            );

            foreach ($prefs as $name => $slug) {
                if ($name !== '' && mb_strpos($search, $name) !== false) {
                    $id = $this->ensure_category_exists($name, $slug);
                    if ($id) $categories[] = $id;
                    break;
                }
            }

            return $categories;
        }

        private function ensure_category_exists($name, $slug) {
            $tax = 'category';
            $term = get_term_by('name', $name, $tax);
            if ($term && !is_wp_error($term)) return $term->term_id;

            $term = get_term_by('slug', $slug, $tax);
            if ($term && !is_wp_error($term)) return $term->term_id;

            $result = wp_insert_term($name, $tax, array('slug' => $slug));
            if (is_wp_error($result)) return false;
            return $result['term_id'];
        }

        private function generate_ota_section($hotel_name, $hotel_data) {
            $urls = isset($hotel_data['urls']) && is_array($hotel_data['urls']) ? $hotel_data['urls'] : array();
            if (empty($urls)) return '';

            $tier1_raw = get_option('hrs_ota_tier_1', array());
            $tier1 = array();

            if (empty($tier1_raw)) {
                $tier1 = array('rakuten', 'jalan', 'ikyu', 'booking', 'yahoo');
            } else {
                if (is_array($tier1_raw)) {
                    foreach ($tier1_raw as $k => $v) {
                        if ($v === 1 || $v === '1' || $v === true) {
                            $tier1[] = (string) $k;
                        } elseif (is_string($v) && !empty($v) && is_numeric($k)) {
                            $tier1[] = $v;
                        }
                    }
                }
            }

            $moshimo = get_option('hrs_moshimo_affiliate_id', '');
            $names = array(
                'rakuten'=>'楽天トラベル','jalan'=>'じゃらん','ikyu'=>'一休.com','relux'=>'Relux',
                'booking'=>'Booking.com','yahoo'=>'Yahoo!トラベル','jtb'=>'JTB',
                'rurubu'=>'るるぶトラベル','yukoyuko'=>'ゆこゆこ','expedia'=>'Expedia',
            );

            $available = array();
            foreach ($tier1 as $ota) {
                if (!empty($urls[$ota]) && filter_var($urls[$ota], FILTER_VALIDATE_URL)) {
                    $available[$ota] = $urls[$ota];
                }
            }

            if (empty($available)) {
                foreach ($urls as $ota => $url) {
                    if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                        $available[$ota] = $url;
                    }
                }
            }

            if (empty($available)) return '';

            $html  = '<div class="hrs-booking-links">';
            $html .= '<h2>' . esc_html($hotel_name) . ' の予約・詳細</h2>';
            $html .= '<ul class="hrs-ota-list">';

            foreach ($available as $ota => $url) {
                $display = isset($names[$ota]) ? $names[$ota] : esc_html($ota);
                
                if ($ota === 'rakuten' && !empty($moshimo)) {
                    $aff = "//af.moshimo.com/af/c/click?a_id=" . rawurlencode($moshimo) . "&p_id=55&pc_id=55&pl_id=624&url=" . rawurlencode($url);
                    $html .= '<li class="hrs-ota-item"><a href="' . esc_url($aff) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html($display) . 'で予約する</a></li>';
                    continue;
                }

                $html .= '<li class="hrs-ota-item"><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($display) . 'で予約する</a></li>';
            }

            $html .= '</ul></div>';
            return $html;
        }

        private function generate_tags($hotel_name, $content) {
            $tags = array();
            if (mb_stripos($hotel_name, '温泉', 0, 'UTF-8') !== false) $tags[] = '温泉';
            $keywords = array('露天風呂','貸切風呂','大浴場','オーシャンビュー','ビュッフェ','スパ','源泉かけ流し');
            foreach ($keywords as $kw) {
                if (mb_stripos($content, $kw, 0, 'UTF-8') !== false) $tags[] = $kw;
            }
            return array_unique($tags);
        }

        public function is_api_configured() {
            return !empty(get_option('hrs_chatgpt_api_key')) 
                && !empty(get_option('hrs_google_cse_api_key')) 
                && !empty(get_option('hrs_google_cse_id'));
        }
    }
} // class_exists チェック終了

if (!function_exists('hrs')) {
    function hrs() {
        return \HRS_Hotel_Review_System::get_instance();
    }
}