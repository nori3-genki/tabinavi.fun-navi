<?php
/**
 * HQC学習システムローダー
 * 
 * 学習システム関連のフック処理を行う
 * ファイルの読み込みはメインファイルのPhase 9で行う
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグイン有効化時のDB作成
 */
function hrs_hqc_activate() {
    if (class_exists('HRS_HQC_DB_Installer')) {
        HRS_HQC_DB_Installer::install();
    }
}

/**
 * プラグイン無効化時の処理
 */
function hrs_hqc_deactivate() {
    // テーブルは残す（データ保持）
}

/**
 * プラグイン削除時のDB削除
 */
function hrs_hqc_uninstall() {
    if (class_exists('HRS_HQC_DB_Installer')) {
        HRS_HQC_DB_Installer::uninstall();
    }
}

/**
 * DBバージョンチェック（アップグレード対応）
 */
add_action('admin_init', function() {
    if (class_exists('HRS_HQC_DB_Installer')) {
        HRS_HQC_DB_Installer::maybe_upgrade();
    }
});

/**
 * ============================================
 * 既存クラスへの統合フック
 * ============================================
 */

/**
 * 記事生成前にプロンプトを最適化
 */
add_filter('hrs_before_generate_prompt', function($prompt, $hotel_name, $options) {
    if (!class_exists('HRS_HQC_Prompt_Optimizer')) {
        return $prompt;
    }

    $learning_enabled = get_option('hrs_hqc_learning_enabled', true);
    if (!$learning_enabled) {
        return $prompt;
    }

    $optimizer = HRS_HQC_Prompt_Optimizer::get_instance();
    $result = $optimizer->optimize_for_80($prompt, $hotel_name);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[HRS HQC] Prompt optimized for: ' . $hotel_name);
        error_log('[HRS HQC] Boost applied: ' . ($result['boost_applied'] ? 'Yes' : 'No'));
    }

    return $result['prompt'];
}, 10, 3);

/**
 * 記事生成後にHQC分析＆学習データ保存
 */
add_action('hrs_after_generate_article', function($post_id, $content, $hotel_data, $options) {
    if (!class_exists('HRS_HQC_Analyzer') || !class_exists('HRS_HQC_Learning_Module')) {
        return;
    }

    $analyzer = new HRS_HQC_Analyzer();
    $learning = HRS_HQC_Learning_Module::get_instance();

    $analysis = $analyzer->analyze($content, $hotel_data);

    $learning->save_history(array(
        'post_id' => $post_id,
        'hotel_name' => $hotel_data['hotel_name'] ?? '',
        'location' => $hotel_data['location'] ?? '',
        'h_score' => $analysis['h_score'],
        'q_score' => $analysis['q_score'],
        'c_score' => $analysis['c_score'],
        'total_score' => $analysis['total_score'],
        'h_details' => $analysis['h_details'],
        'q_details' => $analysis['q_details'],
        'c_details' => $analysis['c_details'],
        'weak_points' => $analysis['weak_points'],
        'prompt_used' => $options['prompt'] ?? '',
        'model_used' => $options['model'] ?? 'gpt-4',
        'generation_params' => $options,
    ));

    // メタデータにスコアを保存
    update_post_meta($post_id, '_hrs_hqc_score', $analysis['total_score']);
    update_post_meta($post_id, '_hrs_h_score', $analysis['h_score']);
    update_post_meta($post_id, '_hrs_q_score', $analysis['q_score']);
    update_post_meta($post_id, '_hrs_c_score', $analysis['c_score']);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[HRS HQC] Article analyzed. Score: ' . $analysis['total_score'] . '%');
    }
}, 10, 4);

/**
 * 学習システム状態チェック
 */
add_action('admin_init', function() {
    if (!class_exists('HRS_HQC_DB_Installer')) {
        return;
    }

    if (!HRS_HQC_DB_Installer::tables_exist()) {
        HRS_HQC_DB_Installer::install();
    }
});

/**
 * REST API エンドポイント
 */
add_action('rest_api_init', function() {
    register_rest_route('hrs/v1', '/hqc/stats', array(
        'methods' => 'GET',
        'callback' => function() {
            if (!class_exists('HRS_HQC_Learning_Module')) {
                return new WP_Error('not_loaded', '学習モジュールが読み込まれていません');
            }
            $learning = HRS_HQC_Learning_Module::get_instance();
            return rest_ensure_response($learning->get_statistics());
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('hrs/v1', '/hqc/hotel/(?P<name>.+)', array(
        'methods' => 'GET',
        'callback' => function($request) {
            if (!class_exists('HRS_HQC_Learning_Module')) {
                return new WP_Error('not_loaded', '学習モジュールが読み込まれていません');
            }
            $learning = HRS_HQC_Learning_Module::get_instance();
            $data = $learning->get_hotel_learning($request['name']);
            if (!$data) {
                return new WP_Error('not_found', 'ホテルデータが見つかりません');
            }
            return rest_ensure_response($data);
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ));
});

// ログ
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[5DRB] HQC Learning System loader loaded');
}