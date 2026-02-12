<?php
/**
 * Nurture Page - AJAX Handlers
 * 記事育成ページのAJAX処理を担当
 *
 * @package Hotel_Review_System
 * @version 2.0.0-CLASS
 *
 * 変更履歴:
 * - 1.1.0: 初版（関数形式）
 * - 2.0.0: クラス形式に変更（HRS_Ajax_Registry 対応）
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_Nurture_Ajax_Handlers {

    /**
     * 個別記事削除（ゴミ箱へ移動）
     */
    public static function ajax_delete_article() {
        $post_id = isset($_POST['post_id']) ? (int) wp_unslash($_POST['post_id']) : 0;
        
        if ($post_id <= 0) {
            wp_send_json_error(['message' => __('無効な記事IDです', '5d-review-builder')], 400);
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== HRS_POST_TYPE) {
            wp_send_json_error(['message' => __('無効な記事です', '5d-review-builder')], 404);
        }
        
        if (wp_trash_post($post_id)) {
            wp_send_json_success([
                'message' => __('ゴミ箱へ移動しました', '5d-review-builder'),
                'post_id' => $post_id,
            ]);
        }
        
        wp_send_json_error(['message' => __('ゴミ箱への移動に失敗しました', '5d-review-builder')], 500);
    }

    /**
     * 記事再分析
     */
    public static function ajax_analyze_article() {
        $post_id = isset($_POST['post_id']) ? (int) wp_unslash($_POST['post_id']) : 0;
        
        if ($post_id <= 0) {
            wp_send_json_error(['message' => __('無効な記事IDです', '5d-review-builder')], 400);
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== HRS_POST_TYPE) {
            wp_send_json_error(['message' => __('記事が見つかりません', '5d-review-builder')], 404);
        }
        
        if (!class_exists('HRS_HQC_Analyzer')) {
            wp_send_json_error(['message' => __('HQC Analyzer が見つかりません', '5d-review-builder')], 500);
        }
        
        $analyzer   = new HRS_HQC_Analyzer();
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true) ?: $post->post_title;
        $result     = $analyzer->analyze($post->post_content, ['hotel_name' => $hotel_name]);
        
        $h_score = (float) ($result['h_score'] ?? 0);
        $q_score = (float) ($result['q_score'] ?? 0);
        $c_score = (float) ($result['c_score'] ?? 0);
        $hqc     = (float) ($result['total_score'] ?? 0);
        
        update_post_meta($post_id, '_hrs_hqc_h_score', $h_score);
        update_post_meta($post_id, '_hrs_hqc_q_score', $q_score);
        update_post_meta($post_id, '_hrs_hqc_c_score', $c_score);
        update_post_meta($post_id, '_hrs_hqc_score',   $hqc);
        
        $seo_score = (int) get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
        
        $weak_points = [];
        if ($h_score < 50) {
            $weak_points[] = ['axis' => 'H', 'score' => $h_score];
        }
        if ($q_score < 50) {
            $weak_points[] = ['axis' => 'Q', 'score' => $q_score];
        }
        if ($c_score < 50) {
            $weak_points[] = ['axis' => 'C', 'score' => $c_score];
        }
        
        wp_send_json_success([
            'score'        => round($hqc, 1),
            'seo_score'    => $seo_score,
            'h_score'      => round($h_score, 1),
            'q_score'      => round($q_score, 1),
            'c_score'      => round($c_score, 1),
            'weak_points'  => $weak_points,
            'message'      => __('再分析が完了しました', '5d-review-builder'),
        ]);
    }

    /**
     * 一括分析
     */
    public static function ajax_bulk_analyze() {
        $post_ids = isset($_POST['post_ids'])
            ? array_map('intval', wp_unslash($_POST['post_ids']))
            : [];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('記事が選択されていません', '5d-review-builder')], 400);
        }
        
        if (!class_exists('HRS_HQC_Analyzer')) {
            wp_send_json_error(['message' => __('HQC Analyzer が見つかりません', '5d-review-builder')], 500);
        }
        
        $analyzer = new HRS_HQC_Analyzer();
        $success = 0;
        $failed = 0;
        $results = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== HRS_POST_TYPE) {
                $failed++;
                continue;
            }
            
            $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true) ?: $post->post_title;
            $result = $analyzer->analyze($post->post_content, ['hotel_name' => $hotel_name]);
            
            $h_score = (float) ($result['h_score'] ?? 0);
            $q_score = (float) ($result['q_score'] ?? 0);
            $c_score = (float) ($result['c_score'] ?? 0);
            $hqc     = (float) ($result['total_score'] ?? 0);
            
            update_post_meta($post_id, '_hrs_hqc_h_score', $h_score);
            update_post_meta($post_id, '_hrs_hqc_q_score', $q_score);
            update_post_meta($post_id, '_hrs_hqc_c_score', $c_score);
            update_post_meta($post_id, '_hrs_hqc_score',   $hqc);
            
            $results[$post_id] = [
                'score' => round($hqc, 1),
                'h_score' => round($h_score, 1),
                'q_score' => round($q_score, 1),
                'c_score' => round($c_score, 1),
            ];
            
            $success++;
        }
        
        wp_send_json_success([
            'success' => $success,
            'failed'  => $failed,
            'results' => $results,
            'message' => sprintf(
                __('%d件の分析が完了しました', '5d-review-builder'),
                $success
            ),
        ]);
    }

    /**
     * 一括削除（ゴミ箱へ移動）
     */
    public static function ajax_bulk_delete() {
        $post_ids = isset($_POST['post_ids'])
            ? array_map('intval', wp_unslash($_POST['post_ids']))
            : [];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('記事が選択されていません', '5d-review-builder')], 400);
        }
        
        $success = 0;
        $failed  = 0;
        $errors  = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== HRS_POST_TYPE) {
                $failed++;
                $errors[] = "ID {$post_id}";
                continue;
            }
            
            if (wp_trash_post($post_id)) {
                $success++;
            } else {
                $failed++;
                $errors[] = "ID {$post_id}";
            }
        }
        
        wp_send_json_success([
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
            'message' => sprintf(
                __('%d件をゴミ箱へ移動しました', '5d-review-builder'),
                $success
            ),
        ]);
    }
}