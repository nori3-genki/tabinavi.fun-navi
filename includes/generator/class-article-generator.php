<?php
/**
 * HRS_Article_Generator - WordPress専用版（統合版）
 * @package HRS
 * @version 5.0.2-debug
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('HRS_Article_Generator')) :

class HRS_Article_Generator {

    private $hqc_threshold;
    private $hqc_enabled;
    private $location_required;
    private $learning_enabled;

    public function __construct() {
        $this->hqc_threshold     = floatval(get_option('hrs_hqc_threshold', 50)) / 100;
        $this->hqc_enabled       = (bool) get_option('hrs_hqc_enabled', 1);
        $this->location_required = (bool) get_option('hrs_location_required', false);
        $this->learning_enabled  = (bool) get_option('hrs_hqc_learning_enabled', true);
    }

    public static function init() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public function render() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('記事を生成する権限がありません。', '5d-review-builder'));
        }

        $hqc_enabled       = $this->hqc_enabled;
        $location_required = $this->location_required;
        $regenerate_id     = isset($_GET['regenerate']) ? intval($_GET['regenerate']) : 0;
        $weak_points       = isset($_GET['weak_points']) ? json_decode(urldecode($_GET['weak_points']), true) : [];

        $post_title = $regenerate_id ? get_the_title($regenerate_id) : '';
        $hotel_name = $regenerate_id ? get_post_meta($regenerate_id, '_hrs_hotel_name', true) : '';
        $location   = $regenerate_id ? get_post_meta($regenerate_id, '_hrs_location', true) : '';

        $presets = [
            'story'  => __('物語形式', '5d-review-builder'),
            'review' => __('レビュー形式', '5d-review-builder'),
            'blog'   => __('ブログ形式', '5d-review-builder'),
        ];
        ?>
        <div class="wrap hrs-article-generator">
            <h1><span class="dashicons dashicons-welcome-write-blog"></span> 🚀 記事生成</h1>
            <p class="hrs-subtitle">AIで高品質なホテルレビュー記事を生成・保存します</p>

            <?php if ($regenerate_id): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>再生成モード：</strong>
                        <?php echo esc_html($hotel_name ?: $post_title); ?>
                        <?php if (!empty($weak_points)): ?>
                            （弱点補強中：<?php echo count($weak_points); ?>件）
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="hrs-container">
                <div class="hrs-card">
                    <h2><span class="dashicons dashicons-admin-settings"></span> 設定</h2>

                    <form id="article-gen-form">
                        <input type="hidden" name="regenerate_id" value="<?php echo esc_attr($regenerate_id); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('hrs_generator_nonce')); ?>">

                        <?php if (!empty($weak_points)): ?>
                            <input type="hidden" name="weak_points" value="<?php echo esc_attr(json_encode($weak_points)); ?>">
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="hotel_name">
                                <?php _e('ホテル名', '5d-review-builder'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="hotel_name" name="hotel_name"
                                   value="<?php echo esc_attr($hotel_name); ?>" required>
                        </div>

                        <div class="form-field">
                            <label for="location">
                                <?php _e('所在地', '5d-review-builder'); ?>
                                <?php if ($location_required): ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <input type="text" id="location" name="location"
                                   value="<?php echo esc_attr($location); ?>"
                                   <?php echo $location_required ? 'required' : ''; ?>>
                        </div>

                        <div class="form-field">
                            <label for="style"><?php _e('スタイル', '5d-review-builder'); ?></label>
                            <select id="style" name="style">
                                <?php foreach ($presets as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'story'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label><?php _e('弱点補強', '5d-review-builder'); ?></label>
                            <label>
                                <input type="checkbox" name="apply_boost" value="1"
                                    <?php checked(!empty($weak_points)); ?>>
                                <?php _e('弱点を補強して生成', '5d-review-builder'); ?>
                            </label>

                            <?php if (!empty($weak_points)): ?>
                                <p class="description">
                                    <?php _e('検出された弱点:', '5d-review-builder'); ?>
                                    <?php
                                    echo implode(', ', array_map(function ($wp) {
                                        return '<code>' . esc_html($wp['axis'] . '-' . $wp['category']) . '</code>';
                                    }, $weak_points));
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <button type="button" id="generate-btn"
                                class="button button-primary button-large">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('AIで記事を生成', '5d-review-builder'); ?>
                        </button>
                    </form>
                </div>

                <div class="hrs-card" id="preview-card" style="display:none;">
                    <h2><span class="dashicons dashicons-visibility"></span> プレビュー</h2>
                    <div id="preview-content" class="preview-content"></div>

                    <div class="preview-actions">
                        <button id="copy-btn" class="button">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php _e('コピー', '5d-review-builder'); ?>
                        </button>

                        <button id="save-btn" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('投稿として保存', '5d-review-builder'); ?>
                        </button>
                    </div>
                </div>

                <div id="loading" class="hrs-loading" style="display:none;">
                    <div class="spinner is-active"></div>
                    <p><?php _e('AIが記事を生成中...', '5d-review-builder'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .hrs-article-generator .hrs-subtitle { color:#666; margin:-10px 0 20px; }
            .hrs-container { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
            .hrs-card { padding:20px; background:#fff; border:1px solid #e2e4e7; }
            .form-field { margin-bottom:16px; }
            .required { color:#d63638; }
            .preview-content { background:#f9f9f9; padding:20px; min-height:300px; }
            @media (max-width:782px){ .hrs-container{grid-template-columns:1fr;} }
        </style>

        <script>
        jQuery(function($){
            const $form = $('#article-gen-form');
            const $btn  = $('#generate-btn');
            const $load = $('#loading');
            const $pre  = $('#preview-card');
            const $cnt  = $('#preview-content');

            $btn.on('click', function(){
                $btn.prop('disabled', true);
                $load.show();

                $.post(ajaxurl, $form.serialize() + '&action=hrs_generate_article')
                .done(function(res){
                    if(res.success){
                        $cnt.html(res.data.article);
                        $pre.show();
                    } else {
                        alert(res.data.message || '生成失敗');
                    }
                })
                .fail(function(){
                    alert('通信エラー');
                })
                .always(function(){
                    $btn.prop('disabled', false);
                    $load.hide();
                });
            });
        });
        </script>
        <?php
    }

    public static function render_page() {
        $instance = new self();
        $instance->render();
    }

    public function generate($hotel_name, $options = []) {
        // ★★★ デバッグ開始 ★★★
        $this->log("========== GENERATE START ==========");
        $this->log("[DEBUG] hotel_name: " . $hotel_name);
        $this->log("[DEBUG] options: " . print_r($options, true));
        
        try {
            $location = $options['location'] ?? '';
            $skip_hqc_check = $options['skip_hqc_check'] ?? false;

            $this->log("[DEBUG] location: " . $location);
            $this->log("[DEBUG] skip_hqc_check: " . ($skip_hqc_check ? 'true' : 'false'));
            $this->log("[DEBUG] hqc_enabled: " . ($this->hqc_enabled ? 'true' : 'false'));

            if (!$this->hqc_enabled) {
                $skip_hqc_check = true;
            }

            if ($this->location_required && empty($location) && !$skip_hqc_check) {
                $this->log("[DEBUG] FAILED: location_required");
                return ['success' => false, 'error_code' => 'location_required'];
            }

            $collector = $this->get_data_collector();
            $this->log("[DEBUG] Data Collector: " . ($collector ? get_class($collector) : 'NULL'));
            
            $hotel_data = $collector
                ? $collector->collect_hotel_data($hotel_name, $location)
                : ($options['mock_hotel_data'] ?? null);

            $this->log("[DEBUG] hotel_data collected: " . ($hotel_data ? 'YES' : 'NO'));

            if (isset($options['_hotel_data_override'])) {
                $hotel_data = $options['_hotel_data_override'];
                $this->log("[CONFIDENCE OVERRIDE] Injected hotel_data used");
            }

            if (!$hotel_data) {
                $this->log("[DEBUG] FAILED: collection_failed - no hotel_data");
                return ['success' => false, 'error_code' => 'collection_failed'];
            }

            $hqc_score = $hotel_data['hqc_score'] ?? null;
            $this->log("[DEBUG] hqc_score from data: " . var_export($hqc_score, true));

            if ($hqc_score === null) {
                $skip_hqc_check = true;
                $hqc_score = 0.8;
                $this->log("hqc_score not set, using default 0.8 and skipping check");
            } elseif ($hqc_score > 1) {
                $hqc_score /= 100;
            }

            if (!$skip_hqc_check && $hqc_score < $this->hqc_threshold) {
                $this->log("[DEBUG] FAILED: low_hqc_score ({$hqc_score} < {$this->hqc_threshold})");
                return [
                    'success' => false,
                    'error_code' => 'low_hqc_score',
                    'hqc_score' => $hqc_score,
                    'hotel_data' => $hotel_data,
                    'article' => $options['fallback_article'] ?? ''
                ];
            }

            // ★★★ プロンプト生成 ★★★
            $prompt = '';
            $prompt_engine = $this->get_prompt_engine();
            $this->log("[DEBUG] Prompt Engine: " . ($prompt_engine ? get_class($prompt_engine) : 'NULL'));
            
            if ($prompt_engine) {
                $this->log("[DEBUG] Calling generate_5d_prompt...");
                $prompt = $prompt_engine->generate_5d_prompt(
                    $hotel_data,
                    $options['style'] ?? 'story',
                    $options['persona'] ?? 'general',
                    $options['tone'] ?? 'casual',
                    $options['policy'] ?? 'standard',
                    $options['ai_model'] ?? 'chatgpt'
                );
                $this->log("[DEBUG] Prompt generated, length: " . strlen($prompt));
                $this->log("[DEBUG] Prompt preview (first 500 chars): " . substr($prompt, 0, 500));
                
                $prompt = apply_filters('hrs_before_generate_prompt', $prompt, $hotel_name, $options);
                $options['optimized_prompt'] = $prompt;
            } else {
                $this->log("[DEBUG] WARNING: No prompt engine available!");
            }

            $optimization_result = null;
            
            if ($this->learning_enabled && class_exists('HRS_HQC_Prompt_Optimizer')) {
                $optimizer = HRS_HQC_Prompt_Optimizer::get_instance();
                
                if (!empty($options['weak_points']) || !empty($options['force_patterns'])) {
                    $force_patterns = $options['force_patterns'] ?? [];
                    
                    if (!empty($options['weak_points'])) {
                        $force_patterns = array_merge(
                            $force_patterns,
                            $this->weak_points_to_patterns($options['weak_points'])
                        );
                    }
                    
                    $optimization_result = $optimizer->optimize($prompt, $hotel_name, [
                        'boost_level' => $options['boost_level'] ?? 'strong',
                        'force_patterns' => array_unique($force_patterns),
                    ]);
                    
                    $this->log("[WEAK_POINT_BOOST] Patterns applied: " . implode(', ', $force_patterns));
                    
                } else {
                    $optimization_result = $optimizer->optimize_for_80($prompt, $hotel_name);
                }
                
                if (!empty($optimization_result['prompt'])) {
                    $prompt = $optimization_result['prompt'];
                    $options['optimized_prompt'] = $prompt;
                }
                
                if (!empty($optimization_result['patterns_applied'])) {
                    $options['_hqc_boost'] = $optimization_result['patterns_applied'];
                }
                if (!empty($optimization_result['predicted_improvement'])) {
                    $options['_predicted_improvement'] = $optimization_result['predicted_improvement'];
                }
                
                $this->log("[PROMPT_OPTIMIZER] Boost level: " . ($optimization_result['boost_level'] ?? 'none'));
                $this->log("[PROMPT_OPTIMIZER] Patterns: " . implode(', ', $optimization_result['patterns_applied'] ?? []));
            }

            // ★★★ AI記事生成 ★★★
            $this->log("[DEBUG] ========== AI GENERATION START ==========");
            $this->log("[DEBUG] Final prompt length: " . strlen($prompt));
            $this->log("[DEBUG] Prompt empty?: " . (empty($prompt) ? 'YES' : 'NO'));
            
            if (!empty($prompt)) {
                $this->log("[DEBUG] Calling generate_article_from_prompt...");
                $article_content = $this->generate_article_from_prompt($prompt, $options);
                $this->log("[DEBUG] Article returned, length: " . strlen($article_content));
                $this->log("[DEBUG] Article preview (first 300 chars): " . substr($article_content, 0, 300));
            } else {
                $this->log("[DEBUG] SKIP: Prompt is empty, using fallback message");
                $article_content = "<p>プロンプト生成に失敗しました。</p>";
            }

            if (empty($article_content)) {
                $this->log("[DEBUG] WARNING: Article content is empty after generation");
                $article_content = "<p>AI記事生成に失敗しました。APIキーを確認してください。</p>";
            }

            $this->log("[DEBUG] ========== AI GENERATION END ==========");

            if (!empty($hotel_data['urls'])) {
                $options['urls'] = $hotel_data['urls'];
            }

            // ★★★ 投稿挿入 ★★★
            $this->log("[DEBUG] Calling insert_post_direct...");
            $post_id = $this->insert_post_direct($hotel_name, $article_content, $options);
            $this->log("[DEBUG] insert_post_direct returned: " . var_export($post_id, true));

            if (is_wp_error($post_id)) {
                $this->log("WP_Error: " . $post_id->get_error_message());
                return ['success' => false, 'error_code' => 'generation_error', 'message' => $post_id->get_error_message()];
            }

            if (!$post_id) {
                $this->log("[DEBUG] FAILED: post_id is false/0");
                return ['success' => false, 'error_code' => 'generation_error', 'message' => 'Failed to create post'];
            }

            $this->log("[DEBUG] Post created successfully, ID: " . $post_id);

            $this->ensure_price_section($post_id);

            update_post_meta($post_id, '_hrs_hqc_score', $hqc_score);
            
            if (isset($hotel_data['hqc_h_score'])) {
                update_post_meta($post_id, '_hrs_hqc_h_score', $hotel_data['hqc_h_score']);
            }
            if (isset($hotel_data['hqc_q_score'])) {
                update_post_meta($post_id, '_hrs_hqc_q_score', $hotel_data['hqc_q_score']);
            }
            if (isset($hotel_data['hqc_c_score'])) {
                update_post_meta($post_id, '_hrs_hqc_c_score', $hotel_data['hqc_c_score']);
            }
            
            $h_stored = get_post_meta($post_id, '_hrs_hqc_h_score', true);
            $q_stored = get_post_meta($post_id, '_hrs_hqc_q_score', true);
            $c_stored = get_post_meta($post_id, '_hrs_hqc_c_score', true);

            if (empty($h_stored) || empty($q_stored) || empty($c_stored)) {
                $this->log("[HQC_FIX] Individual scores missing, re-analyzing post_id: {$post_id}");
                
                $post = get_post($post_id);
                if ($post && !empty($post->post_content) && class_exists('HRS_HQC_Analyzer')) {
                    $analyzer = new HRS_HQC_Analyzer();
                    $analysis = $analyzer->analyze($post->post_content, ['hotel_name' => $hotel_name]);
                    
                    if (!empty($analysis)) {
                        update_post_meta($post_id, '_hrs_hqc_h_score', $analysis['h_score']);
                        update_post_meta($post_id, '_hrs_hqc_q_score', $analysis['q_score']);
                        update_post_meta($post_id, '_hrs_hqc_c_score', $analysis['c_score']);
                        update_post_meta($post_id, '_hrs_hqc_score', $analysis['total_score']);
                        
                        $this->log("[HQC_FIX] Saved H:{$analysis['h_score']} Q:{$analysis['q_score']} C:{$analysis['c_score']} Total:{$analysis['total_score']}");
                    }
                }
            }
            
            update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
            update_post_meta($post_id, '_hrs_location', $location);

            if (!empty($hotel_data['urls']) && is_array($hotel_data['urls'])) {
                update_post_meta($post_id, '_hrs_ota_urls', $hotel_data['urls']);

                $ota_key_map = [
                    'rakuten' => 'hrp_rakuten_travel_url',
                    'jalan'   => 'hrp_booking_jalan_url',
                    'ikyu'    => 'hrp_booking_ikyu_url',
                    'yahoo'   => 'hrp_booking_yahoo_url',
                    'jtb'     => 'hrp_booking_jtb_url',
                    'rurubu'  => 'hrp_booking_rurubu_url',
                    'relux'   => 'hrp_booking_relux_url',
                    'yukoyuko'=> 'hrp_booking_yukoyuko_url',
                    'booking' => 'hrp_booking_bookingcom_url',
                    'expedia' => 'hrp_booking_expedia_url',
                ];

                foreach ($ota_key_map as $source_key => $meta_key) {
                    $url = $hotel_data['urls'][$source_key] ?? '';
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        update_post_meta($post_id, $meta_key, esc_url_raw($url));
                        if (function_exists('update_field')) {
                            update_field($meta_key, $url, $post_id);
                        }
                    } else {
                        delete_post_meta($post_id, $meta_key);
                        if (function_exists('delete_field')) {
                            delete_field($meta_key, $post_id);
                        }
                    }
                }

                if (!empty($hotel_data['address'])) {
                    update_post_meta($post_id, '_hrs_hotel_address', sanitize_text_field($hotel_data['address']));
                }

                if (empty($location) && !empty($hotel_data['prefecture'])) {
                    $location = $hotel_data['prefecture'];
                    update_post_meta($post_id, '_hrs_location', sanitize_text_field($location));
                }
            } else {
                if (!empty($location)) {
                    update_post_meta($post_id, '_hrs_location', sanitize_text_field($location));
                }
                if (!empty($hotel_name)) {
                    update_post_meta($post_id, '_hrs_hotel_name', sanitize_text_field($hotel_name));
                }
            }

            if (!empty($options['_hqc_boost'])) {
                update_post_meta($post_id, '_hrs_hqc_boost_patterns', $options['_hqc_boost']);
            }
            if (!empty($options['_predicted_improvement'])) {
                update_post_meta($post_id, '_hrs_predicted_improvement', $options['_predicted_improvement']);
            }

            $this->set_location_categories($post_id, $location, $hotel_data);
            $this->set_persona_category($post_id, $options['persona'] ?? 'general');
            $this->auto_fetch_rakuten_price($post_id, $hotel_data, $hotel_name);

            $post = get_post($post_id);
            do_action('hrs_after_generate_article', $post_id, $post->post_content ?? '', $hotel_data, $options);

            $this->log("[DEBUG] ========== GENERATE SUCCESS ==========");
            $this->log("[DEBUG] Final article length: " . strlen($post->post_content ?? ''));

            return [
                'success' => true,
                'post_id' => $post_id,
                'hqc_score' => $hqc_score,
                'hotel_data' => $hotel_data,
                'article' => $post->post_content ?? '',
                'optimization' => $optimization_result,
                'learning' => [
                    'style' => $options['style'] ?? 'story',
                    'persona' => $options['persona'] ?? 'general',
                    'tone' => $options['tone'] ?? 'casual',
                ]
            ];

        } catch (Throwable $e) {
            $this->log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->log('Trace: ' . $e->getTraceAsString());
            return ['success' => false, 'error_code' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * プロンプトからAI記事を生成
     */
    private function generate_article_from_prompt($prompt, $options = []) {
        $this->log("[API_CALL] ========== generate_article_from_prompt START ==========");
        
        $api_key = get_option('hrs_chatgpt_api_key', '');
        $this->log("[API_CALL] API Key exists: " . (!empty($api_key) ? 'YES (length: ' . strlen($api_key) . ')' : 'NO'));
        
        if (empty($api_key)) {
            $this->log('[API_CALL] FAILED: ChatGPT API key not set');
            return '';
        }

        $this->log("[API_CALL] Sending request to OpenAI API...");
        $this->log("[API_CALL] Prompt length: " . strlen($prompt));

        $request_body = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ];
        
        $this->log("[API_CALL] Request model: gpt-4");
        $this->log("[API_CALL] Request max_tokens: 4000");

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode($request_body),
        ]);

        if (is_wp_error($response)) {
            $this->log('[API_CALL] WP_Error: ' . $response->get_error_message());
            return '';
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $this->log("[API_CALL] Response HTTP Code: " . $response_code);

        $body_raw = wp_remote_retrieve_body($response);
        $this->log("[API_CALL] Response body length: " . strlen($body_raw));
        
        $body = json_decode($body_raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("[API_CALL] JSON decode error: " . json_last_error_msg());
            $this->log("[API_CALL] Raw response (first 500): " . substr($body_raw, 0, 500));
            return '';
        }

        // エラーレスポンスのチェック
        if (isset($body['error'])) {
            $this->log("[API_CALL] API Error: " . print_r($body['error'], true));
            return '';
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $this->log("[API_CALL] Content extracted, length: " . strlen($content));

        if (empty($content)) {
            $this->log('[API_CALL] WARNING: Empty content from API');
            $this->log('[API_CALL] Full response: ' . print_r($body, true));
            return '';
        }

        $this->log("[API_CALL] Content preview (first 300): " . substr($content, 0, 300));
        $this->log("[API_CALL] ========== generate_article_from_prompt END ==========");

        return $content;
    }

    private function ensure_price_section($post_id) {
        $post = get_post($post_id);
        if (!$post) return;
        
        $content = $post->post_content;
        $shortcode = '[hrs_price_section]';
        
        if (strpos($content, $shortcode) !== false) return;
        
        $inserted = false;
        $patterns = array('/<h2[^>]*>.*?まとめ.*?<\/h2>/iu', '/<h2[^>]*>.*?おわりに.*?<\/h2>/iu');
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
                $content = substr($content, 0, $m[0][1]) . "\n\n" . $shortcode . "\n\n" . substr($content, $m[0][1]);
                $inserted = true;
                break;
            }
        }
        
        if (!$inserted && ($pos = strrpos($content, '<h2')) !== false) {
            $content = substr($content, 0, $pos) . "\n\n" . $shortcode . "\n\n" . substr($content, $pos);
            $inserted = true;
        }
        
        if (!$inserted) $content .= "\n\n" . $shortcode;
        
        wp_update_post(array('ID' => $post_id, 'post_content' => $content));
        $this->log("[PRICE_SECTION] Inserted in post_id: {$post_id}");
    }

    private function generate_slug_from_data($hotel_name, $options = []) {
        $urls = $options['urls'] ?? [];
        $official_url = $urls['official'] ?? '';
        
        if (!empty($official_url)) {
            $slug = $this->extract_slug_from_url($official_url);
            if (!empty($slug)) {
                return $this->ensure_unique_slug($slug);
            }
        }
        
        $rakuten_url = $urls['rakuten'] ?? '';
        if (!empty($rakuten_url) && preg_match('/\/([a-z0-9_-]+)\/?(?:\?|$)/i', parse_url($rakuten_url, PHP_URL_PATH), $m)) {
            $slug = sanitize_title($m[1]);
            if (!empty($slug) && strlen($slug) > 3) {
                return $this->ensure_unique_slug($slug);
            }
        }
        
        $slug = $this->hotel_name_to_slug($hotel_name);
        return $this->ensure_unique_slug($slug);
    }

    private function extract_slug_from_url($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) return '';
        
        $host = preg_replace('/^www\./', '', $host);
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            $slug = sanitize_title($parts[0]);
            if (!empty($slug) && strlen($slug) > 2) {
                return $slug;
            }
        }
        return '';
    }

    private function hotel_name_to_slug($hotel_name) {
        $map = array(
            'あ'=>'a','い'=>'i','う'=>'u','え'=>'e','お'=>'o',
            'か'=>'ka','き'=>'ki','く'=>'ku','け'=>'ke','こ'=>'ko',
            'さ'=>'sa','し'=>'shi','す'=>'su','せ'=>'se','そ'=>'so',
            'た'=>'ta','ち'=>'chi','つ'=>'tsu','て'=>'te','と'=>'to',
            'な'=>'na','に'=>'ni','ぬ'=>'nu','ね'=>'ne','の'=>'no',
            'は'=>'ha','ひ'=>'hi','ふ'=>'fu','へ'=>'he','ほ'=>'ho',
            'ま'=>'ma','み'=>'mi','む'=>'mu','め'=>'me','も'=>'mo',
            'や'=>'ya','ゆ'=>'yu','よ'=>'yo',
            'ら'=>'ra','り'=>'ri','る'=>'ru','れ'=>'re','ろ'=>'ro',
            'わ'=>'wa','を'=>'wo','ん'=>'n',
            'が'=>'ga','ぎ'=>'gi','ぐ'=>'gu','げ'=>'ge','ご'=>'go',
            'ざ'=>'za','じ'=>'ji','ず'=>'zu','ぜ'=>'ze','ぞ'=>'zo',
            'だ'=>'da','ぢ'=>'di','づ'=>'du','で'=>'de','ど'=>'do',
            'ば'=>'ba','び'=>'bi','ぶ'=>'bu','べ'=>'be','ぼ'=>'bo',
            'ぱ'=>'pa','ぴ'=>'pi','ぷ'=>'pu','ぺ'=>'pe','ぽ'=>'po',
            'ゃ'=>'ya','ゅ'=>'yu','ょ'=>'yo','っ'=>'',
            'ー'=>'-','　'=>'-',' '=>'-',
        );
        
        $name = mb_convert_kana($hotel_name, 'c');
        $slug = strtr($name, $map);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        
        return !empty($slug) ? $slug : 'hotel-' . time();
    }

    private function ensure_unique_slug($slug) {
        global $wpdb;
        $original = $slug;
        $counter = 1;
        
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'hotel-review' LIMIT 1",
            $slug
        ))) {
            $counter++;
            $slug = $original . '-' . $counter;
        }
        
        return $slug;
    }

    private function set_location_categories($post_id, $location, $hotel_data) {
        $prefecture = $hotel_data['prefecture'] ?? '';
        if (empty($prefecture) && !empty($location)) {
            $prefecture = $this->extract_prefecture_from_location($location);
        }
        if (empty($prefecture) && !empty($hotel_data['address'])) {
            $prefecture = $this->extract_prefecture_from_location($hotel_data['address']);
        }
        
        if (empty($prefecture)) {
            $this->log("[AUTO_CATEGORY] No prefecture found for post_id: {$post_id}");
            return;
        }
        
        $prefecture_to_area = array(
            '北海道' => 'hokkaido',
            '青森県' => 'tohoku', '岩手県' => 'tohoku', '宮城県' => 'tohoku', 
            '秋田県' => 'tohoku', '山形県' => 'tohoku', '福島県' => 'tohoku',
            '茨城県' => 'kanto', '栃木県' => 'kanto', '群馬県' => 'kanto', 
            '埼玉県' => 'kanto', '千葉県' => 'kanto', '東京都' => 'kanto', '神奈川県' => 'kanto',
            '新潟県' => 'chubu', '富山県' => 'chubu', '石川県' => 'chubu', 
            '福井県' => 'chubu', '山梨県' => 'chubu', '長野県' => 'chubu', 
            '岐阜県' => 'chubu', '静岡県' => 'chubu', '愛知県' => 'chubu',
            '三重県' => 'kinki', '滋賀県' => 'kinki', '京都府' => 'kinki', 
            '大阪府' => 'kinki', '兵庫県' => 'kinki', '奈良県' => 'kinki', '和歌山県' => 'kinki',
            '鳥取県' => 'chugoku', '島根県' => 'chugoku', '岡山県' => 'chugoku', 
            '広島県' => 'chugoku', '山口県' => 'chugoku',
            '徳島県' => 'shikoku', '香川県' => 'shikoku', '愛媛県' => 'shikoku', '高知県' => 'shikoku',
            '福岡県' => 'kyushu', '佐賀県' => 'kyushu', '長崎県' => 'kyushu', 
            '熊本県' => 'kyushu', '大分県' => 'kyushu', '宮崎県' => 'kyushu', 
            '鹿児島県' => 'kyushu', '沖縄県' => 'kyushu',
        );
        
        $prefecture_slugs = array(
            '北海道' => 'hokkaido',
            '青森県' => 'aomori', '岩手県' => 'iwate', '宮城県' => 'miyagi',
            '秋田県' => 'akita', '山形県' => 'yamagata', '福島県' => 'fukushima',
            '茨城県' => 'ibaraki', '栃木県' => 'tochigi', '群馬県' => 'gunma',
            '埼玉県' => 'saitama', '千葉県' => 'chiba', '東京都' => 'tokyo', '神奈川県' => 'kanagawa',
            '新潟県' => 'niigata', '富山県' => 'toyama', '石川県' => 'ishikawa',
            '福井県' => 'fukui', '山梨県' => 'yamanashi', '長野県' => 'nagano',
            '岐阜県' => 'gifu', '静岡県' => 'shizuoka', '愛知県' => 'aichi',
            '三重県' => 'mie', '滋賀県' => 'shiga', '京都府' => 'kyoto',
            '大阪府' => 'osaka', '兵庫県' => 'hyogo', '奈良県' => 'nara', '和歌山県' => 'wakayama',
            '鳥取県' => 'tottori', '島根県' => 'shimane', '岡山県' => 'okayama',
            '広島県' => 'hiroshima', '山口県' => 'yamaguchi',
            '徳島県' => 'tokushima', '香川県' => 'kagawa', '愛媛県' => 'ehime', '高知県' => 'kochi',
            '福岡県' => 'fukuoka', '佐賀県' => 'saga', '長崎県' => 'nagasaki',
            '熊本県' => 'kumamoto', '大分県' => 'oita', '宮崎県' => 'miyazaki',
            '鹿児島県' => 'kagoshima', '沖縄県' => 'okinawa',
        );
        
        $area_slug = $prefecture_to_area[$prefecture] ?? '';
        $pref_slug = $prefecture_slugs[$prefecture] ?? '';
        
        if (empty($area_slug) || empty($pref_slug)) {
            $this->log("[AUTO_CATEGORY] Unknown prefecture: {$prefecture}");
            return;
        }
        
        $taxonomy = 'category';
        $term_ids = array();
        
        $area_term = get_term_by('slug', $area_slug, $taxonomy);
        if ($area_term && !is_wp_error($area_term)) {
            $term_ids[] = $area_term->term_id;
        }
        
        $pref_term = get_term_by('slug', $pref_slug, $taxonomy);
        if ($pref_term && !is_wp_error($pref_term)) {
            $term_ids[] = $pref_term->term_id;
        }
        
        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
            $this->log("[AUTO_CATEGORY] Set categories for post_id {$post_id}: " . implode(', ', $term_ids));
        }
    }
    
    private function set_persona_category($post_id, $persona) {
        if (empty($persona)) {
            $persona = 'general';
        }
        
        $persona_slugs = array(
            'general' => 'general', 'solo' => 'solo', 'couple' => 'couple',
            'family' => 'family', 'senior' => 'senior', 'workation' => 'workation',
            'luxury' => 'luxury', 'budget' => 'budget',
        );
        
        $persona_name_to_slug = array(
            '一般・観光' => 'general',
            'カップル・夫婦' => 'couple',
            '一人旅' => 'solo',
            'ファミリー' => 'family',
            'シニア' => 'senior',
            'ワーケーション' => 'workation',
            'ラグジュアリー' => 'luxury',
            'コスパ重視' => 'budget',
        );
        
        $slug = $persona_slugs[$persona] ?? $persona_name_to_slug[$persona] ?? 'general';
        $taxonomy = 'hotel-category';
        
        if (!taxonomy_exists($taxonomy)) return;
        
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term && !is_wp_error($term)) {
            wp_set_object_terms($post_id, array($term->term_id), $taxonomy, false);
            $this->log("[PERSONA_CATEGORY] Set {$slug} for post_id: {$post_id}");
        }
    }

    private function extract_prefecture_from_location($location) {
        if (empty($location)) return '';
        
        $prefectures = array(
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
        );
        
        foreach ($prefectures as $pref) {
            if (mb_strpos($location, $pref) !== false) return $pref;
        }
        return '';
    }

    private function auto_fetch_rakuten_price($post_id, $hotel_data, $hotel_name) {
        $rakuten_hotel_no = $hotel_data['rakuten_hotel_no'] ?? null;
        
        if (empty($rakuten_hotel_no) && !empty($hotel_data['urls']['rakuten'])) {
            $rakuten_url = $hotel_data['urls']['rakuten'];
            if (preg_match('/hotel_no=(\d+)/', $rakuten_url, $matches)) {
                $rakuten_hotel_no = $matches[1];
            } elseif (preg_match('/\/HOTEL\/(\d+)\//', $rakuten_url, $matches)) {
                $rakuten_hotel_no = $matches[1];
            } elseif (preg_match('/f_no=(\d+)/', $rakuten_url, $matches)) {
                $rakuten_hotel_no = $matches[1];
            }
        }
        
        if (empty($rakuten_hotel_no)) return;
        
        update_post_meta($post_id, '_hrs_rakuten_hotel_no', $rakuten_hotel_no);
        
        if (function_exists('hrs_rakuten_price_updater')) {
            $updater = hrs_rakuten_price_updater();
            $updater->update_price_for_post($post_id);
        }
    }

    private function weak_points_to_patterns($weak_points) {
        $patterns = [];
        $pattern_map = [
            'H' => ['timeline' => 'timeline', 'emotion' => 'emotion', 'scene' => 'scene', 'first_person' => 'first_person', 'address' => 'first_person', 'dialogue' => 'emotion'],
            'Q' => ['five_senses' => 'five_senses', 'cuisine' => 'cuisine', 'facility' => 'facility', 'facilities' => 'facility', 'specificity' => 'five_senses'],
            'C' => ['headings' => 'timeline', 'keyphrase' => 'facility', 'structure' => 'timeline'],
        ];
        
        foreach ($weak_points as $wp) {
            $axis = $wp['axis'] ?? '';
            $category = $wp['category'] ?? '';
            if (isset($pattern_map[$axis][$category])) {
                $patterns[] = $pattern_map[$axis][$category];
            }
        }
        return array_unique($patterns);
    }

    public function insert_post_direct($hotel_name, $article, $options = []) {
        $this->log("[INSERT_POST] Starting insert_post_direct...");
        $this->log("[INSERT_POST] hotel_name: " . $hotel_name);
        $this->log("[INSERT_POST] article length: " . strlen($article));
        
        $slug = $this->generate_slug_from_data($hotel_name, $options);
        $this->log("[INSERT_POST] generated slug: " . $slug);
        
        $post_data = [
            'post_title'   => $hotel_name,
            'post_content' => $article,
            'post_status'  => 'draft',
            'post_type'    => 'hotel-review',
            'post_name'    => $slug,
        ];

        $this->log("[INSERT_POST] Calling wp_insert_post...");
        $post_id = wp_insert_post($post_data, true);
        $this->log("[INSERT_POST] wp_insert_post returned: " . (is_wp_error($post_id) ? 'WP_Error: ' . $post_id->get_error_message() : $post_id));

        if (!is_wp_error($post_id) && $post_id > 0) {
            $hqc_score = $options['hqc_score'] ?? 0.8;
            update_post_meta($post_id, '_hrs_hqc_score', $hqc_score);
            update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
            update_post_meta($post_id, '_hrs_location', $options['location'] ?? '');
            $this->log("[INSERT_POST] Meta saved successfully");
        }

        return is_wp_error($post_id) ? false : $post_id;
    }

    public function record_section_regeneration_success(array $data) {
        if (empty($data['hotel_name']) || empty($data['section_type'])) return false;
        if (!isset($data['before_score'], $data['after_score']) || $data['after_score'] <= $data['before_score']) return false;

        $handled = apply_filters('hrs_handle_section_learning', false, $data);
        if ($handled !== false) return true;

        if (class_exists('HRS_HQC_Learning_Module')) {
            $learning = HRS_HQC_Learning_Module::get_instance();
            $learning_data = [
                'hotel_name' => $data['hotel_name'],
                'section_type' => $data['section_type'],
                'before_score' => round($data['before_score'], 3),
                'after_score' => round($data['after_score'], 3),
                'improvement' => round($data['after_score'] - $data['before_score'], 3),
                'confidence' => floatval($data['confidence'] ?? 0),
                'content' => $data['content'] ?? '',
                'learned_at' => current_time('mysql'),
            ];
            if (method_exists($learning, 'record_section_learning')) {
                $learning->record_section_learning($learning_data);
            } else {
                do_action('hrs_section_learning_record', $learning_data);
            }
        }
        return true;
    }

    private function get_data_collector() {
        $this->log("[GET_DATA_COLLECTOR] Checking available classes...");
        if (class_exists('HRS_Data_Collector')) {
            $this->log("[GET_DATA_COLLECTOR] Found: HRS_Data_Collector");
            return new HRS_Data_Collector();
        }
        if (class_exists('HRS\\Core\\DataCollector')) {
            $this->log("[GET_DATA_COLLECTOR] Found: HRS\\Core\\DataCollector");
            return new \HRS\Core\DataCollector();
        }
        $this->log("[GET_DATA_COLLECTOR] WARNING: No data collector class found!");
        return null;
    }

    private function get_prompt_engine() {
        $this->log("[GET_PROMPT_ENGINE] Checking available classes...");
        if (class_exists('HRS_Prompt_Engine')) {
            $this->log("[GET_PROMPT_ENGINE] Found: HRS_Prompt_Engine");
            return new HRS_Prompt_Engine();
        }
        if (class_exists('HRS\\Core\\PromptEngine')) {
            $this->log("[GET_PROMPT_ENGINE] Found: HRS\\Core\\PromptEngine");
            return new \HRS\Core\PromptEngine();
        }
        $this->log("[GET_PROMPT_ENGINE] WARNING: No prompt engine class found!");
        return null;
    }

    private function log($msg) {
        // デバッグ版では常にログ出力
        error_log('[HRS_Article_Generator] ' . $msg);
    }

    public function ajax_generate_article() {
        $this->log("[AJAX] ========== ajax_generate_article START ==========");
        
        check_ajax_referer('hrs_generator_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            $this->log("[AJAX] FAILED: Permission denied");
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')]);
        }

        $hotel_name = sanitize_text_field($_POST['hotel_name'] ?? '');
        $location = sanitize_text_field($_POST['location'] ?? '');
        $style = sanitize_key($_POST['style'] ?? 'story');
        $apply_boost = !empty($_POST['apply_boost']);
        $regenerate_id = intval($_POST['regenerate_id'] ?? 0);

        $this->log("[AJAX] hotel_name: " . $hotel_name);
        $this->log("[AJAX] location: " . $location);
        $this->log("[AJAX] style: " . $style);
        $this->log("[AJAX] apply_boost: " . ($apply_boost ? 'true' : 'false'));
        $this->log("[AJAX] regenerate_id: " . $regenerate_id);

        if (empty($hotel_name)) {
            $this->log("[AJAX] FAILED: Empty hotel_name");
            wp_send_json_error(['message' => __('ホテル名は必須です。', '5d-review-builder')]);
        }

        $options = ['location' => $location, 'style' => $style];

        if ($regenerate_id > 0) {
            $weak_points = json_decode(wp_unslash($_POST['weak_points'] ?? '[]'), true);
            if (!empty($weak_points)) $options['weak_points'] = $weak_points;
            $options['skip_hqc_check'] = true;
        }

        if ($apply_boost && empty($options['weak_points'])) {
            $options['weak_points'] = [
                ['axis' => 'H', 'category' => 'emotion'],
                ['axis' => 'Q', 'category' => 'five_senses'],
            ];
        }

        $this->log("[AJAX] Calling generate() method...");
        $result = $this->generate($hotel_name, $options);
        $this->log("[AJAX] generate() returned: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        $this->log("[AJAX] Result: " . print_r($result, true));

        if ($result['success']) {
            $this->log("[AJAX] Sending success response");
            wp_send_json_success([
                'post_id' => $result['post_id'],
                'hqc_score' => $result['hqc_score'],
                'hotel_name' => $hotel_name,
                'article' => $result['article'],
            ]);
        } else {
            $this->log("[AJAX] Sending error response");
            wp_send_json_error($result);
        }
    }

    public function ajax_save_as_post() {
        check_ajax_referer('hrs_generator_nonce', 'nonce');
        if (!current_user_can('publish_posts')) {
            wp_send_json_error(['message' => __('投稿権限がありません。', '5d-review-builder')]);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? 'draft');

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => __('投稿が見つかりません。', '5d-review-builder')]);
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_status' => $status,
            'post_date' => current_time('mysql'),
            'post_date_gmt' => get_gmt_from_date(current_time('mysql')),
        ], true);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => $updated->get_error_message()]);
        }

        wp_send_json_success([
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
        ]);
    }
}

endif;