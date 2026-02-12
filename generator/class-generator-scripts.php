<?php
/**
 * Generator Scripts - JavaScript管理クラス
 *
 * 役割:
 * - 管理画面用 JS の enqueue のみを担当
 * - AJAX 登録は HRS_Ajax_Registry に完全委譲
 *
 * @package Hotel_Review_System
 * @subpackage Generator
 * @version 6.8.1
 *
 * 変更履歴:
 * - 6.7.2-FINAL : 初版
 * - 6.8.0       : AJAX 登録を Ajax Registry に委譲
 * - 6.8.1       : Bootstrap 呼び出し前提で安定化
 *
 * 注意:
 * - このクラス内で add_action('wp_ajax_*') は行わない
 * - init() は Bootstrap（plugins_loaded）から 1回だけ呼ぶ
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Generator_Scripts {

    /**
     * 初期化
     *
     * ※ AJAX 登録は行わない
     * ※ JS enqueue のみを登録
     */
    public static function init() {

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[5DRB] HRS_Generator_Scripts::init() registered');
        }
    }

    /**
     * 管理画面用 JS 読み込み
     *
     * @param string $hook 現在の管理画面フック
     */
    public static function enqueue_scripts($hook) {

        // Generator 画面以外では読み込まない
        if (strpos($hook, '5d-review-builder-generator') === false) {
            return;
        }

        wp_enqueue_script(
            'hrs-generator-manual',
            plugins_url('assets/js/hrs-generator-manual.js', dirname(__FILE__, 2)),
            ['jquery'],
            '6.8.1',
            true
        );

        wp_localize_script(
            'hrs-generator-manual',
            'HRS_Generator',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('hrs_manual_generator_nonce'),
                'ai_names' => [
                    'claude'  => 'Claude',
                    'gemini'  => 'Gemini',
                    'chatgpt' => 'ChatGPT',
                ],
            ]
        );
    }

    /**
     * AJAX: プロンプト生成
     *
     * ※ add_action は HRS_Ajax_Registry が担当
     */
    public static function ajax_generate_prompt() {

        check_ajax_referer('hrs_manual_generator_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        if (empty($_POST['hotel_name'])) {
            wp_send_json_error(['message' => '不正なリクエスト']);
        }

        $hotel_name = sanitize_text_field($_POST['hotel_name']);
        $location   = sanitize_text_field($_POST['location'] ?? '');
        $preset_id  = sanitize_text_field($_POST['preset'] ?? 'balanced');
        $words      = intval($_POST['words'] ?? 2000);
        $ai_model   = sanitize_text_field($_POST['ai_model'] ?? 'chatgpt');

        $layers = isset($_POST['layers'])
            ? array_map('sanitize_text_field', (array) $_POST['layers'])
            : [];

        if (!class_exists('HRS_Prompt_Engine')) {
            wp_send_json_error(['message' => 'プロンプトエンジンが見つかりません']);
        }

        $presets = HRS_Generator_Data::get_presets();
        $preset  = $presets[$preset_id] ?? $presets['balanced'];

        $hotel_data = [
            'hotel_name'  => $hotel_name,
            'address'     => $location,
            'description' => '',
            'features'    => [],
        ];

        // UI レイヤー → Prompt Engine 用マッピング
        $layer_map = [
            'seasonal' => 'seasonal',
            'local'    => 'regional',
            'luxury'   => 'wellness',
            'family'   => 'adventure',
        ];

        $mapped_layers = [];
        foreach ($layers as $layer) {
            if (isset($layer_map[$layer])) {
                $mapped_layers[] = $layer_map[$layer];
            }
        }

        $engine = new HRS_Prompt_Engine();

        $prompt = $engine->generate_5d_prompt(
            $hotel_data,
            $preset['style'],
            $preset['persona'],
            $preset['tone'],
            $preset['policy'],
            $ai_model,
            $mapped_layers
        );

        $prompt = self::add_word_count_instruction($prompt, $words);

        wp_send_json_success([
            'prompt' => $prompt,
            'length' => mb_strlen($prompt),
            'ai'     => $ai_model,
            'preset' => $preset_id,
        ]);
    }

    /**
     * 文字数指示を付与
     */
    private static function add_word_count_instruction($prompt, $words) {

        $instruction = "【目標文字数】\n記事本文は{$words}文字程度で作成してください。\n\n";

        if (strpos($prompt, '<instructions>') !== false) {
            return str_replace(
                '<instructions>',
                "<instructions>\n{$instruction}",
                $prompt
            );
        }

        if (strpos($prompt, '## 記事生成指示') !== false) {
            return str_replace(
                '## 記事生成指示',
                "## 記事生成指示\n\n{$instruction}",
                $prompt
            );
        }

        return $instruction . $prompt;
    }
}