<?php
/**
 * HRS_Article_Generator_AJAX - AJAXハンドラー
 * @package HRS\Admin\Generator
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('HRS_Article_Generator_AJAX')) :

class HRS_Article_Generator_AJAX {
    private $generator;

    public function __construct($generator) {
        $this->generator = $generator;
    }

    // ========================================
    // ajax_generate_article
    // ========================================
    public function ajax_generate_article() {
        $nonce = $_POST['nonce'] ?? '';
        $allowed_nonces = [
            'hrs_generator_nonce',
            'hrs_hqc_nonce',
            'hrs_admin_nonce',
        ];
        $nonce_valid = false;
        foreach ($allowed_nonces as $nonce_name) {
            if (wp_verify_nonce($nonce, $nonce_name)) {
                $nonce_valid = true;
                break;
            }
        }

        if (!$nonce_valid) {
            wp_send_json_error([
                'message' => __('セキュリティトークンが無効です。ページを更新して再度お試しください。', '5d-review-builder')
            ]);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error([
                'message' => __('記事を生成する権限がありません。', '5d-review-builder')
            ]);
        }

        $hotel_name    = sanitize_text_field($_POST['hotel_name'] ?? '');
        $location      = sanitize_text_field($_POST['location'] ?? '');
        $style         = sanitize_key($_POST['style'] ?? 'story');
        $apply_boost   = !empty($_POST['apply_boost']);
        $regenerate_id = intval($_POST['regenerate_id'] ?? 0);

        if (empty($hotel_name)) {
            wp_send_json_error([
                'message' => __('ホテル名は必須です。', '5d-review-builder')
            ]);
        }

        if ($regenerate_id > 0) {
            $target_post = get_post($regenerate_id);
            if (!$target_post || $target_post->post_type !== 'hotel-review') {
                wp_send_json_error([
                    'message' => __('再生成対象の投稿が見つからないか、正しい投稿タイプではありません。', '5d-review-builder')
                ]);
            }
        }

        $options = [
            'location' => $location,
            'style'    => $style,
        ];

        if ($regenerate_id > 0) {
            $weak_points_json = wp_unslash($_POST['weak_points'] ?? '[]');
            $weak_points = json_decode($weak_points_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($weak_points) && !empty($weak_points)) {
                $options['weak_points'] = $weak_points;
            } elseif (json_last_error() !== JSON_ERROR_NONE && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HRS AJAX] Invalid weak_points JSON: ' . substr($weak_points_json, 0, 200));
            }
            $options['skip_hqc_check'] = true;
        }

        if ($apply_boost && empty($options['weak_points'] ?? [])) {
            $options['weak_points'] = [
                ['axis' => 'H', 'category' => 'emotion'],
                ['axis' => 'Q', 'category' => 'five_senses'],
            ];
        }

        $result = $this->generator->generate($hotel_name, $options);

        if ($result['success']) {
            wp_send_json_success([
                'post_id'    => $result['post_id'],
                'hqc_score'  => $result['hqc_score'],
                'hotel_name' => $hotel_name,
                'article'    => $result['article'],
            ]);
        } else {
            wp_send_json_error($result);
        }
    }

    // ========================================
    // ajax_save_as_post
    // ========================================
    public function ajax_save_as_post() {
        check_ajax_referer('hrs_generator_nonce', 'nonce');

        if (!current_user_can('publish_posts')) {
            wp_send_json_error(['message' => __('投稿権限がありません。', '5d-review-builder')]);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $status  = sanitize_key($_POST['status'] ?? 'draft');

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => __('投稿が見つかりません。', '5d-review-builder')]);
        }

        $updated = wp_update_post([
            'ID'            => $post_id,
            'post_status'   => $status,
            'post_date'     => current_time('mysql'),
            'post_date_gmt' => get_gmt_from_date(current_time('mysql')),
        ], true);

        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => $updated->get_error_message()]);
        }

        wp_send_json_success([
            'post_id'   => $post_id,
            'edit_url'  => get_edit_post_link($post_id, 'raw'),
            'view_url'  => get_permalink($post_id),
        ]);
    }
}

endif;