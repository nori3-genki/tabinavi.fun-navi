<?php
/**
 * Generator AJAX Handler (Manual Prompt)
 *
 * @package Hotel_Review_System
 * @version 1.0.1-NONCE-FIX
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_Generator_Ajax {

    public function __construct() {
        add_action(
            'wp_ajax_hrs_generate_manual_prompt',
            [$this, 'generate_manual_prompt']
        );
    }

    /**
     * Manual Prompt AJAX
     */
    public function generate_manual_prompt() {
        // nonce チェック（hrs_manual_generator_nonce に統一）
        check_ajax_referer('hrs_manual_generator_nonce', 'nonce');

        // ---- 入力取得 ----
        $hotel_name  = sanitize_text_field($_POST['hotel_name'] ?? '');
        $location    = sanitize_text_field($_POST['location'] ?? '');
        $preset      = sanitize_text_field($_POST['preset'] ?? '');
        $words       = intval($_POST['words'] ?? 0);
        $ai_model    = sanitize_text_field($_POST['ai_model'] ?? 'chatgpt');
        $layers      = array_map('sanitize_text_field', $_POST['layers'] ?? []);
        $post_id     = intval($_POST['regenerate'] ?? 0);

        $weak_points = [];
        if (!empty($_POST['weak_points'])) {
            $decoded = json_decode(wp_unslash($_POST['weak_points']), true);
            if (is_array($decoded)) {
                $weak_points = $decoded;
            }
        }

        // ---- 必須チェック ----
        if ($hotel_name === '') {
            wp_send_json_error([
                'message' => 'ホテル名が指定されていません'
            ]);
        }

        // ---- プロンプト生成 ----
        if (!class_exists('HRS_Manual_Prompt_Builder')) {
            wp_send_json_error([
                'message' => 'HRS_Manual_Prompt_Builder クラスが見つかりません'
            ]);
        }

        $prompt = HRS_Manual_Prompt_Builder::build([
            'hotel_name'  => $hotel_name,
            'location'    => $location,
            'preset'      => $preset,
            'words'       => $words,
            'ai_model'    => $ai_model,
            'layers'      => $layers,
            'post_id'     => $post_id,
            'weak_points' => $weak_points,
        ]);

        if (is_wp_error($prompt)) {
            wp_send_json_error([
                'message' => $prompt->get_error_message()
            ]);
        }

        // ---- 正常終了 ----
        wp_send_json_success([
            'prompt' => $prompt
        ]);
    }
}