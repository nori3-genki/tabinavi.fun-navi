<?php
/**
 * HQC Ajax - AJAXハンドラークラス
 * 
 * HQC Generator の非同期処理（保存・生成・キュー・プレビュー等）を管理
 * 
 * @package Hotel_Review_System
 * @subpackage HQC
 * @version 6.8.0
 * 
 * 変更履歴:
 * - 6.8.0: ホテルごと個別HQCパラメータ対応
 *          ajax_add_to_queue() で settings を受け取り保存
 *          ajax_process_queue() で個別settings優先
 *          HRS_Auto_Generator::add_to_queue() に委譲（summary自動生成）
 * - 6.7.2-FIX: 重複メソッド削除、構文エラー修正
 * - 6.7.1: 設定保存を「安全保存」に強化
 * - 6.7.0: キュー処理・プリセット適用を追加
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Hqc_Ajax {

    /**
     * AJAXハンドラーを登録
     */
    public static function init() {
        // 共通操作
        add_action('wp_ajax_hrs_hqc_autosuggest',      [__CLASS__, 'ajax_autosuggest']);
        add_action('wp_ajax_hrs_hqc_apply_preset',     [__CLASS__, 'ajax_apply_preset']);
        add_action('wp_ajax_hrs_hqc_save_settings',    [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_hrs_hqc_preview',          [__CLASS__, 'ajax_preview']);
        
        // 育成ホテル生成系
        add_action('wp_ajax_hrs_generate_article',     [__CLASS__, 'ajax_generate_article']);
        add_action('wp_ajax_hrs_add_to_queue',         [__CLASS__, 'ajax_add_to_queue']);
        add_action('wp_ajax_hrs_remove_from_queue',    [__CLASS__, 'ajax_remove_from_queue']);
        add_action('wp_ajax_hrs_process_queue',        [__CLASS__, 'ajax_process_queue']);
    }

    /**
     * ホテル名オートサジェスト
     */
    public static function ajax_autosuggest() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }
        wp_send_json_success([]);
    }

    /**
     * プリセット適用
     */
    public static function ajax_apply_preset() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $preset_name = isset($_POST['preset']) ? sanitize_key(wp_unslash($_POST['preset'])) : '';
        $presets_data = HRS_Hqc_Presets::get_presets();
        $presets = $presets_data['presets'] ?? [];

        if (!$preset_name || !isset($presets[$preset_name])) {
            wp_send_json_error(['message' => __('プリセットが見つかりません。', '5d-review-builder')], 404);
        }

        wp_send_json_success($presets[$preset_name]);
    }

    /**
     * 設定保存
     */
    public static function ajax_save_settings() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        if (!is_array($raw)) {
            wp_send_json_error(['message' => __('無効なデータ形式です。', '5d-review-builder')], 400);
        }

        $sanitized = HRS_Hqc_Presets::sanitize_and_validate_settings($raw);
        update_option('hrs_hqc_settings', $sanitized);

        wp_send_json_success([
            'message' => __('設定を保存しました。', '5d-review-builder'),
            'saved'   => $sanitized,
        ]);
    }

    /**
     * リアルタイムプレビューテキスト生成
     */
    public static function ajax_preview() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        
        $h = $settings['h'] ?? [];
        $q = $settings['q'] ?? [];

        $persona   = sanitize_key($h['persona'] ?? 'general');
        $tone      = sanitize_key($q['tone'] ?? 'casual');
        $sensory   = sanitize_key($q['sensory'] ?? 'G1');
        $story     = sanitize_key($q['story'] ?? 'S1');
        $depth     = sanitize_key($h['depth'] ?? 'L2');
        $structure = sanitize_key($q['structure'] ?? 'timeline');
        $commercial = sanitize_key($settings['c']['commercial'] ?? 'none');
        $experience = sanitize_key($settings['c']['experience'] ?? 'recommend');

        $summary = sprintf(
            'H[%s/%s] Q[%s/%s/%s/%s/%s] C[%s/%s]',
            $persona, $depth,
            $tone, $structure, $sensory, $story, $q['info'] ?? 'I1',
            $commercial, $experience
        );

        $sample_text = HRS_Hqc_Data::get_sample_text($persona, $tone, $sensory, $story);

        wp_send_json_success([
            'summary' => $summary,
            'sample'  => $sample_text,
        ]);
    }

    /**
     * 単一ホテルの記事生成
     */
    public static function ajax_generate_article() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $hotel_name = isset($_POST['hotel_name']) ? sanitize_text_field(wp_unslash($_POST['hotel_name'])) : '';
        $location   = isset($_POST['location'])   ? sanitize_text_field(wp_unslash($_POST['location']))   : '';
        $settings   = isset($_POST['settings'])   ? wp_unslash($_POST['settings'])   : [];

        if (empty($hotel_name)) {
            wp_send_json_error(['message' => __('ホテル名を入力してください。', '5d-review-builder')], 400);
        }

        // HRS_Auto_Generator の読み込み
        self::ensure_auto_generator();

        if (!class_exists('HRS_Auto_Generator')) {
            wp_send_json_error([
                'message' => __('HRS_Auto_Generator クラスが見つかりません。', '5d-review-builder')
            ], 500);
        }

        $generator = HRS_Auto_Generator::get_instance();
        $options = [
            'location'      => $location,
            'settings'      => $settings,
            'hqc_settings'  => $settings, // 後方互換
        ];

        $result = $generator->generate_single($hotel_name, $options);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ], 500);
        }

        if (empty($result['post_id'])) {
            $msg = $result['message'] ?? __('記事生成に失敗しました。', '5d-review-builder');
            wp_send_json_error(['message' => $msg], 500);
        }

        wp_send_json_success([
            'post_id'   => (int) $result['post_id'],
            'title'     => get_the_title($result['post_id']),
            'edit_url'  => get_edit_post_link($result['post_id'], 'raw'),
        ]);
    }

    /**
     * キューにホテルを追加
     * 
     * @version 6.8.0 HQC settings を個別保存対応
     */
    public static function ajax_add_to_queue() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $hotel_name = isset($_POST['hotel_name']) ? sanitize_text_field(wp_unslash($_POST['hotel_name'])) : '';
        $location   = isset($_POST['location'])   ? sanitize_text_field(wp_unslash($_POST['location']))   : '';

        if (empty($hotel_name)) {
            wp_send_json_error(['message' => __('ホテル名を入力してください。', '5d-review-builder')], 400);
        }

        // ★【v6.8.0】JS側から送信されたHQC settingsを受け取る
        $settings = null;
        if (isset($_POST['settings'])) {
            $raw_settings = wp_unslash($_POST['settings']);
            // JSON文字列の場合はデコード
            if (is_string($raw_settings)) {
                $decoded = json_decode($raw_settings, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $settings = $decoded;
                }
            } elseif (is_array($raw_settings)) {
                $settings = $raw_settings;
            }
        }

        // HRS_Auto_Generator に委譲（summary自動生成・重複チェック含む）
        self::ensure_auto_generator();

        if (class_exists('HRS_Auto_Generator')) {
            $generator = HRS_Auto_Generator::get_instance();
            $options = [
                'location' => $location,
            ];

            // ★【v6.8.0】settingsがある場合はoptionsに含める
            if (!empty($settings)) {
                $options['settings'] = $settings;
            }

            $result = $generator->add_to_queue($hotel_name, $options);

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message()
                ], 400);
            }

            $queue = $generator->get_queue();
            wp_send_json_success([
                'message'     => sprintf(__('「%s」をキューに追加しました。', '5d-review-builder'), $hotel_name),
                'queue_count' => count($queue),
            ]);
        } else {
            // フォールバック: HRS_Auto_Generator がない場合は直接保存
            $queue = get_option('hrs_generation_queue', []);

            foreach ($queue as $item) {
                if (isset($item['hotel_name']) && $item['hotel_name'] === $hotel_name) {
                    wp_send_json_error([
                        'message' => sprintf(__('「%s」は既にキューに存在します。', '5d-review-builder'), $hotel_name)
                    ], 400);
                }
            }

            $queue_item = [
                'hotel_name' => $hotel_name,
                'options'    => [
                    'location' => $location,
                ],
                'added_at'   => current_time('mysql'),
                'status'     => 'pending',
            ];

            // ★【v6.8.0】settingsをフォールバック時にも保存
            if (!empty($settings)) {
                $queue_item['options']['settings'] = $settings;
                $queue_item['summary'] = self::build_summary_fallback($settings);
            } else {
                $queue_item['summary'] = 'デフォルト設定';
            }

            $queue[] = $queue_item;
            update_option('hrs_generation_queue', $queue);

            wp_send_json_success([
                'message'     => sprintf(__('「%s」をキューに追加しました。', '5d-review-builder'), $hotel_name),
                'queue_count' => count($queue),
            ]);
        }
    }

    /**
     * キューからホテルを削除
     */
    public static function ajax_remove_from_queue() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        $hotel_name = isset($_POST['hotel_name']) ? sanitize_text_field(wp_unslash($_POST['hotel_name'])) : '';

        if (empty($hotel_name)) {
            wp_send_json_error(['message' => __('ホテル名が指定されていません。', '5d-review-builder')], 400);
        }

        // HRS_Auto_Generator に委譲（ログ記録含む）
        self::ensure_auto_generator();

        if (class_exists('HRS_Auto_Generator')) {
            $generator = HRS_Auto_Generator::get_instance();
            $generator->remove_from_queue($hotel_name);
        } else {
            $queue = get_option('hrs_generation_queue', []);
            $new_queue = array_filter($queue, function($item) use ($hotel_name) {
                return ($item['hotel_name'] ?? '') !== $hotel_name;
            });
            update_option('hrs_generation_queue', array_values($new_queue));
        }

        wp_send_json_success([
            'message' => sprintf(__('「%s」をキューから削除しました。', '5d-review-builder'), $hotel_name),
        ]);
    }

    /**
     * キューのバッチ処理
     * 
     * @version 6.8.0 ホテルごとの個別settings優先
     */
    public static function ajax_process_queue() {
        check_ajax_referer('hrs_hqc_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('権限がありません。', '5d-review-builder')], 403);
        }

        // JS側から送信されたグローバルsettings（フォールバック用）
        $global_settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $queue = get_option('hrs_generation_queue', []);

        if (empty($queue)) {
            wp_send_json_error(['message' => __('処理対象のキューが空です。', '5d-review-builder')], 400);
        }

        self::ensure_auto_generator();

        if (!class_exists('HRS_Auto_Generator')) {
            wp_send_json_error([
                'message' => __('HRS_Auto_Generator クラスが見つかりません。', '5d-review-builder')
            ], 500);
        }

        $generator = HRS_Auto_Generator::get_instance();
        $success_count = 0;
        $error_count   = 0;
        $remaining_queue = [];

        foreach ($queue as $item) {
            if (!isset($item['hotel_name'])) {
                $error_count++;
                continue;
            }

            // ★【v6.8.0】個別settingsを優先、なければグローバルsettingsをフォールバック
            $item_settings = $item['options']['settings'] ?? null;
            if (empty($item_settings)) {
                $item_settings = $global_settings;
            }

            $options = array_merge(
                $item['options'] ?? [],
                [
                    'settings'     => $item_settings,
                    'hqc_settings' => $item_settings, // 後方互換
                ]
            );

            $result = $generator->generate_single($item['hotel_name'], $options);

            if (is_wp_error($result) || empty($result['post_id'])) {
                $error_count++;
                // 失敗したものはステータスを更新して残す
                $item['status'] = 'failed';
                $item['error'] = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? '不明なエラー');
                $remaining_queue[] = $item;
            } else {
                $success_count++;
            }
        }

        update_option('hrs_generation_queue', $remaining_queue);

        wp_send_json_success([
            'success_count' => $success_count,
            'error_count'   => $error_count,
            'remaining'     => count($remaining_queue),
            'message'       => sprintf(
                __('成功: %d件, 失敗: %d件, 残り: %d件', '5d-review-builder'),
                $success_count, $error_count, count($remaining_queue)
            ),
        ]);
    }

    /**
     * HRS_Auto_Generator の読み込みを保証
     * 
     * @since 6.8.0
     */
    private static function ensure_auto_generator() {
        if (class_exists('HRS_Auto_Generator')) {
            return;
        }

        // パスの候補を試行
        $paths = [
            plugin_dir_path(dirname(dirname(__FILE__))) . 'generator/class-auto-generator.php',
            dirname(dirname(__FILE__)) . '/generator/class-auto-generator.php',
            dirname(__DIR__) . '/class-auto-generator.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS] WARNING: HRS_Auto_Generator not found in any expected path');
        }
    }

    /**
     * サマリー文字列を生成（フォールバック用）
     * 
     * @since 6.8.0
     */
    private static function build_summary_fallback($settings) {
        if (class_exists('HRS_Auto_Generator')) {
            return HRS_Auto_Generator::build_settings_summary($settings);
        }

        $h = $settings['h'] ?? [];
        $q = $settings['q'] ?? [];
        $persona = $h['persona'] ?? 'general';
        $tone = $q['tone'] ?? 'casual';
        $depth = $h['depth'] ?? 'L2';

        return "{$persona}/{$tone}/{$depth}";
    }
}