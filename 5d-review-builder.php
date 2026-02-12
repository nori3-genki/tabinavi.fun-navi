<?php
/**
 * Plugin Name: 5D Review Builder
 * Plugin URI: https://tabinavi.fun
 * Description: AI-powered 5D hotel review article generator with HQC Framework (Human/Quality/Content)
 * Version: 8.1.2
 * Author: アインシュタイン
 * Author URI: https://tabinavi.fun
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 5d-review-builder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Hotel_Review_System
 * @version 8.1.2
 *
 * CHANGELOG v8.1.2 (2026-02-10):
 * - フォルダ内未登録ファイル12件をPhase定義に追加
 *   Phase 1: includes/data/seasonal-events-data.php
 *   Phase 8: includes/utils/color-utils.php
 *   Phase 9: includes/admin/class-competitor-analysis-page.php
 *            includes/admin/dashboard/class-dashboard-data.php
 *            includes/admin/dashboard/class-dashboard-page.php
 *            includes/admin/dashboard/class-dashboard-styles.php
 *            includes/admin/settings/class-settings-scripts.php
 *            includes/admin/settings/class-settings-styles.php
 *            includes/admin/settings/class-settings-tabs.php
 *            includes/admin/tools/class-slug-fixer.php
 * - test-ajax.php は開発用のため意図的に除外
 *
 * CHANGELOG v8.1.1 (2026-02-07):
 * - class-auto-generator.php を Phase 9 に追加（A/Bテストエラー修正）
 *
 * CHANGELOG v8.1.0 (2026-02-07):
 * - Phase 9: HQC styles/scripts ファイル追加
 * - Phase 10: A/Bテスト styles/scripts ファイル追加
 * - register_taxonomy() 呼び出し削除（Fatal error修正）
 *
 * CHANGELOG v8.0.9 (2025-01-20):
 * - AJAX Registry方式完全導入
 * - class-ajax-handler.php 削除、class-ajax-registry.php に統一
 * - Phase 6 SEO重複削除
 * - HQC関連ファイル統合
 * - HQC パス修正: includes/hqc/core → includes/admin/hqc
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========================================
// 定数定義
// ========================================
if (!defined('HRS_VERSION'))        define('HRS_VERSION', '8.1.2');
if (!defined('HRS_PLUGIN_FILE'))    define('HRS_PLUGIN_FILE', __FILE__);
if (!defined('HRS_PLUGIN_DIR'))     define('HRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('HRS_PLUGIN_URL'))     define('HRS_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('HRS_PLUGIN_BASENAME')) define('HRS_PLUGIN_BASENAME', plugin_basename(__FILE__));
if (!defined('HRS_POST_TYPE'))      define('HRS_POST_TYPE', 'hotel-review');

// ========================================
// PHP / WP バージョンチェック
// ========================================
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s %s</p></div>',
            esc_html__('5D Review Builder:', '5d-review-builder'),
            esc_html__('このプラグインは PHP 7.4 以上が必要です。現在のバージョン:', '5d-review-builder'),
            esc_html(PHP_VERSION)
        );
    });
    return;
}

if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
    add_action('admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
            esc_html__('5D Review Builder:', '5d-review-builder'),
            esc_html__('このプラグインは WordPress 5.8 以上が必要です。', '5d-review-builder')
        );
    });
    return;
}

// ========================================
// コアファイル読み込み(最優先)
// ========================================
$hrs_core_files = [
    'includes/core/functions.php',
    'includes/core/class-plugin-loader.php',
    'includes/core/class-plugin-upgrader.php',
    'includes/core/class-rest-endpoints.php',
    'includes/core/class-custom-post-type.php',
    'includes/core/class-ajax-registry.php',
    'includes/core/class-admin-diagnostics.php',
    'includes/core/class-hotel-review-system.php',
];

foreach ($hrs_core_files as $file) {
    $full_path = HRS_PLUGIN_DIR . $file;
    if (file_exists($full_path)) {
        require_once $full_path;
    }
}

// ========================================
// フェーズ別ファイル読み込み
// ========================================
$phase_files = [
    // Phase 1: Config + Data
    'Phase 1: Config' => [
        'includes/config/class-5d-config.php',
        'includes/config/class-5d-architecture.php',
        'includes/config/class-hqc-traits.php',
        'includes/data/seasonal-events-data.php',      // ★ v8.1.2 追加
    ],

    // Phase 2: Optimizer Base
    'Phase 2: Optimizer Base' => [
        'includes/optimizer/class-base-optimizer.php',
    ],

    // Phase 3: Optimizers
    'Phase 3: Optimizers' => [
        'includes/optimizer/class-chatgpt-optimizer.php',
        'includes/optimizer/class-claude-optimizer.php',
        'includes/optimizer/class-gemini-optimizer.php',
    ],

    // Phase 4: Data Collector
    'Phase 4: Data Collector' => [
        'includes/data-collector/class-ota-id-mapper.php',
        'includes/data-collector/class-text-extractor.php',
        'includes/data-collector/class-ota-search-engine.php',
        'includes/data-collector/class-review-collector.php',
        'includes/data-collector/class-data-collector.php',
        'includes/data-collector/class-mapping-manager.php',
    ],

    // Phase 6: SEO
    'Phase 6: SEO' => [
        'includes/seo/class-keyphrase-injector.php',
        'includes/seo/class-heading-optimizer.php',
        'includes/seo/class-internal-link-generator.php',
        'includes/seo/class-related-post-linker.php',
        'includes/seo/class-yoast-seo-optimizer.php',
        'includes/seo/class-post-enhancer.php',
    ],

    // Phase 7: OTA
    'Phase 7: OTA' => [
        'includes/ota/class-ota-selector.php',
        'includes/ota/class-ota-persona-mapper.php',
        'includes/ota/class-linkswitch-integration.php',
        'includes/ota/class-rakuten-api-test-endpoint.php',
        'includes/ota/class-rakuten-image-fetcher.php',
        'includes/ota/class-rakuten-price-updater.php',
        'includes/ota/class-rakuten-ranking.php',
        'includes/ota/rakuten-price-bridge.php',
    ],

    // Phase 8: Utility
    'Phase 8: Utility' => [
        'includes/utility/class-category-tag-manager.php',
        'includes/utility/class-image-optimizer.php',
        'includes/utility/class-hrs-hybrid-master.php',
        'includes/utility/fix-prefecture-names.php',
        'includes/utility/fix-existing-posts.php',
        'includes/utils/color-utils.php',              // ★ v8.1.2 追加
    ],

    // Phase 9: Admin + HQC
    'Phase 9: Admin' => [
        'includes/admin/class-hqc-generator.php',
        'includes/admin/class-settings-page.php',
        'includes/admin/class-admin-menu.php',
        'includes/admin/class-dashboard.php',
        'includes/admin/class-api-tester.php',
        'includes/admin/class-generator-page.php',
        'includes/admin/generator/class-article-generator.php',
        'includes/generator/class-auto-generator.php',
        'includes/admin/class-nurture-page.php',
        'includes/admin/nurture/class-nurture-ajax-handlers.php',
        'includes/admin/class-article-nurturing-page.php',
        'includes/admin/class-mapping-ui.php',
        'includes/admin/generator/class-generator-data.php',
        'includes/admin/generator/class-generator-scripts.php',
        'includes/admin/generator/class-generator-styles.php',
        'includes/admin/class-ota-meta-box.php',
        'includes/admin/class-api-preview-page.php',
        'includes/admin/class-api-settings-page.php',
        'includes/admin/class-api-meta-box.php',
        'includes/admin/class-content-elements-ui.php',
        'includes/admin/class-competitor-analysis-page.php', // ★ v8.1.2 追加
        'includes/admin/class-schedule-settings.php',
        'includes/admin/class-news-settings-page.php',
        // Dashboard関連
        'includes/admin/dashboard/class-dashboard-data.php',    // ★ v8.1.2 追加
        'includes/admin/dashboard/class-dashboard-page.php',    // ★ v8.1.2 追加
        'includes/admin/dashboard/class-dashboard-styles.php',  // ★ v8.1.2 追加
        // Settings関連
        'includes/admin/settings/class-settings-scripts.php',   // ★ v8.1.2 追加
        'includes/admin/settings/class-settings-styles.php',    // ★ v8.1.2 追加
        'includes/admin/settings/class-settings-tabs.php',      // ★ v8.1.2 追加
        // Tools関連
        'includes/admin/tools/class-slug-fixer.php',            // ★ v8.1.2 追加
        // HQC関連
        'includes/admin/hqc/class-hqc-ajax.php',
        'includes/admin/hqc/class-hqc-presets.php',
        'includes/admin/hqc/class-hqc-data.php',
        'includes/admin/hqc/class-hqc-styles.php',
        'includes/admin/hqc/class-hqc-scripts.php',
        'includes/admin/hqc/class-hqc-ui.php',
    ],

    // Phase 10: Learning
    'Phase 10: Learning' => [
        'includes/learning/class-hqc-db-installer.php',
        'includes/learning/class-hqc-analyzer.php',
        'includes/learning/class-hqc-learning-module.php',
        'includes/learning/class-hqc-prompt-optimizer.php',
        'includes/learning/class-hqc-score-chart.php',
        'includes/learning/class-hqc-dashboard-widget.php',
        'includes/learning/class-hqc-ab-test.php',
        'includes/learning/class-hqc-ab-test-styles.php',
        'includes/learning/class-hqc-ab-test-scripts.php',
        'includes/learning/class-hqc-auto-optimizer.php',
    ],

    // Phase 11: Performance
    'Phase 11: Performance' => [
        'includes/performance/class-performance-tracker.php',
        'includes/performance/class-csv-importer.php',
        'includes/performance/class-performance-hqc-bridge.php',
        'includes/performance/class-ga4-api-client.php',
        'includes/performance/class-gsc-api-client.php',
        'includes/performance/class-api-scheduler.php',
        'includes/performance/class-api-settings-extension.php',
        'includes/admin/class-performance-admin-page.php',
    ],

    // Phase 12: Display
    'Phase 12: Display' => [
        'includes/display/class-display-templates.php',
        'includes/display/class-competitor-widget.php',
    ],

    // Phase 13: Updater
    'Phase 13: Updater' => [
        'includes/updater/class-news-plan-updater.php',
        'includes/updater/class-article-news-section.php',
        'includes/updater/class-price-updater.php',
    ],

    // Phase 14: Widgets
    'Phase 14: Widgets' => [
        'includes/widgets/class-recent-reviews-widget.php',
        'includes/widgets/class-hrs-news-widget.php',
    ],

    // Phase 15: Migration
    'Phase 15: Migration' => [
        'includes/migration-hotel-category-to-category.php',
    ],
];

// 読み込み状態トラッキング
$hrs_loaded_files = [];
$hrs_skipped_files = [];
$hrs_load_errors = [];

foreach ($phase_files as $phase_name => $files) {
    foreach ($files as $file) {
        $full_path = HRS_PLUGIN_DIR . $file;
        if (file_exists($full_path)) {
            try {
                require_once $full_path;
                $hrs_loaded_files[] = $file;
            } catch (Throwable $t) {
                $hrs_load_errors[] = $file . ': ' . $t->getMessage();
            }
        } else {
            $hrs_skipped_files[] = $file;
        }
    }
}

// HQC学習システムローダー
$hqc_loader = HRS_PLUGIN_DIR . 'hrs-hqc-learning-loader.php';
if (file_exists($hqc_loader)) {
    try {
        require_once $hqc_loader;
        $hrs_loaded_files[] = 'hrs-hqc-learning-loader.php';
    } catch (Throwable $t) {
        $hrs_load_errors[] = 'hrs-hqc-learning-loader.php: ' . $t->getMessage();
    }
}

// グローバル変数に保存
$GLOBALS['hrs_loaded_files'] = $hrs_loaded_files;
$GLOBALS['hrs_skipped_files'] = $hrs_skipped_files;
$GLOBALS['hrs_load_errors']  = $hrs_load_errors;

// デバッグログ
if (defined('WP_DEBUG') && WP_DEBUG) {
    if (function_exists('hrs_log')) {
        hrs_log('Files loaded: ' . count($hrs_loaded_files), 'info');
        if (!empty($hrs_skipped_files)) {
            hrs_log('Skipped: ' . implode(', ', $hrs_skipped_files), 'warning');
        }
        if (!empty($hrs_load_errors)) {
            hrs_log('Errors: ' . implode(', ', $hrs_load_errors), 'error');
        }
    }
}

// ========================================
// 必須クラスチェック
// ========================================
if (!class_exists('HRS_Settings_Page')) {
    add_action('admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
            esc_html__('5D Review Builder:', '5d-review-builder'),
            esc_html__('重要: class-settings-page.php が見つかりません。', '5d-review-builder')
        );
    });
}

// ========================================
// 有効化・無効化フック
// ========================================
register_activation_hook(HRS_PLUGIN_FILE, 'hrs_plugin_activate');
register_deactivation_hook(HRS_PLUGIN_FILE, 'hrs_plugin_deactivate');

function hrs_plugin_activate()
{
    if (class_exists('HRS_Custom_Post_Type')) {
        $cpt = new HRS_Custom_Post_Type();
        $cpt->register_post_type();
    }

    if (class_exists('HRS_HQC_DB_Installer')) {
        try {
            HRS_HQC_DB_Installer::install();
        } catch (Throwable $t) {
            error_log('[5DRB] HQC DB install error: ' . $t->getMessage());
        }
    }

    if (class_exists('HRS_Performance_Tracker')) {
        try {
            $tracker = new HRS_Performance_Tracker();
            $tracker->create_table();
        } catch (Throwable $t) {
            error_log('[5DRB] Performance DB install error: ' . $t->getMessage());
        }
    }

    if (class_exists('HRS_API_Scheduler')) {
        HRS_API_Scheduler::on_activation();
    }

    if (get_option('hrs_hqc_learning_enabled') === false) {
        update_option('hrs_hqc_learning_enabled', true);
    }

    if (function_exists('hrs_create_default_categories')) {
        hrs_create_default_categories();
    }

    flush_rewrite_rules();

    if (function_exists('hrs_log')) {
        hrs_log('Plugin activated', 'info');
    }
}

function hrs_plugin_deactivate()
{
    if (class_exists('HRS_API_Scheduler')) {
        HRS_API_Scheduler::on_deactivation();
    }

    flush_rewrite_rules();

    if (function_exists('hrs_log')) {
        hrs_log('Plugin deactivated', 'info');
    }
}

// ========================================
// プラグイン初期化
// ========================================
add_action('plugins_loaded', 'hrs_plugin_init', 10);
add_action('init', 'hrs_load_textdomain', 1);

function hrs_plugin_init()
{
    if (class_exists('HRS_Ajax_Registry')) {
        HRS_Ajax_Registry::init();
    }

    if (class_exists('HRS_Plugin_Loader')) {
        HRS_Plugin_Loader::init();
    }

    if (function_exists('hrs_log')) {
        hrs_log('Plugin initialized (v' . HRS_VERSION . ')', 'info');
    }
}

function hrs_load_textdomain()
{
    load_plugin_textdomain('5d-review-builder', false, dirname(HRS_PLUGIN_BASENAME) . '/languages');
}

// ========================================
// 管理画面初期化
// ========================================
add_action('admin_init', 'hrs_admin_init', 5);

function hrs_admin_init()
{
    if (class_exists('HRS_Plugin_Upgrader')) {
        HRS_Plugin_Upgrader::maybe_upgrade();
    }

    if (class_exists('HRS_HQC_DB_Installer') && method_exists('HRS_HQC_DB_Installer', 'maybe_upgrade')) {
        HRS_HQC_DB_Installer::maybe_upgrade();
    }

    if (class_exists('HRS_Performance_Tracker') && method_exists('HRS_Performance_Tracker', 'maybe_upgrade')) {
        HRS_Performance_Tracker::maybe_upgrade();
    }
}

// ========================================
// REST API初期化
// ========================================
add_action('rest_api_init', 'hrs_rest_api_init');

function hrs_rest_api_init()
{
    if (class_exists('HRS_REST_Endpoints')) {
        HRS_REST_Endpoints::register();
    }

    if (class_exists('HRS_Rakuten_API_Test') && method_exists('HRS_Rakuten_API_Test', 'register_routes')) {
        try {
            $test = new HRS_Rakuten_API_Test();
            $test->register_routes();
        } catch (Throwable $t) {
            if (function_exists('hrs_log')) {
                hrs_log('Rakuten test route registration failed: ' . $t->getMessage(), 'error');
            }
        }
    }
}

// ========================================
// 管理画面アセット
// ========================================
add_action('admin_enqueue_scripts', 'hrs_admin_enqueue_scripts');

function hrs_admin_enqueue_scripts($hook)
{
    if (!is_string($hook) || stripos($hook, '5d-review-builder') === false) {
        return;
    }

    $css_file = HRS_PLUGIN_DIR . 'assets/css/admin-style.css';
    if (file_exists($css_file)) {
        wp_enqueue_style('hrs-admin-style', HRS_PLUGIN_URL . 'assets/css/admin-style.css', [], HRS_VERSION);
    }

    $js_file = HRS_PLUGIN_DIR . 'assets/js/admin.js';
    if (file_exists($js_file)) {
        wp_enqueue_script('hrs-admin-js', HRS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], HRS_VERSION, true);

        wp_localize_script('hrs-admin-js', 'hrsBuilder', [
            'rest' => [
                'base'  => rest_url('hrs/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'ajax' => [
                'url'   => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hrs_admin_nonce'),
            ],
            'api_test' => [
                'rest_url'  => rest_url('hrs/v1'),
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'ajax_url'  => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('hrs_api_test_nonce'),
            ],
            'i18n' => [
                'confirmDelete' => __('本当に削除しますか？', '5d-review-builder'),
                'testing'       => __('テスト中...', '5d-review-builder'),
                'success'       => __('成功', '5d-review-builder'),
                'error'         => __('エラー', '5d-review-builder'),
            ],
            'apiKeySet'      => !empty(get_option('hrs_chatgpt_api_key')),
            'learningEnabled' => (bool) get_option('hrs_hqc_learning_enabled', true),
        ]);
    }

    if (strpos($hook, '5d-review-builder-manual') !== false || strpos($hook, 'builder-manual') !== false) {
        $manual_js = HRS_PLUGIN_DIR . 'assets/js/hrs-generator-manual.js';
        if (file_exists($manual_js)) {
            wp_enqueue_script(
                'hrs-manual-generator',
                HRS_PLUGIN_URL . 'assets/js/hrs-generator-manual.js',
                ['jquery'],
                HRS_VERSION,
                true
            );

            wp_localize_script('hrs-manual-generator', 'HRS_Generator', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('hrs_manual_generator_nonce'),
            ]);
        }
    }
}

// ========================================
// フロントエンドアセット
// ========================================
add_action('wp_enqueue_scripts', function () {
    if (is_singular(HRS_POST_TYPE)) {
        $css_file = HRS_PLUGIN_DIR . 'assets/css/hrs-booking-links.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('hrs-booking-links', HRS_PLUGIN_URL . 'assets/css/hrs-booking-links.css', [], HRS_VERSION);
        }

        $enhancement_css = HRS_PLUGIN_DIR . 'assets/css/hotel-review-style-enhancement.css';
        if (file_exists($enhancement_css)) {
            wp_enqueue_style(
                'hrs-style-enhancement',
                HRS_PLUGIN_URL . 'assets/css/hotel-review-style-enhancement.css',
                [],
                HRS_VERSION
            );
        }
    }
});

// ========================================
// 管理画面通知
// ========================================
add_action('admin_notices', 'hrs_admin_notices');

function hrs_admin_notices()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if ($screen === null || stripos($screen->id, '5d-review-builder') === false) {
        return;
    }

    $chatgpt_api_key = get_option('hrs_chatgpt_api_key', '');
    $google_cse_key  = get_option('hrs_google_cse_api_key', '');
    $google_cse_id   = get_option('hrs_google_cse_id', '');

    $missing_apis = [];
    if (empty($chatgpt_api_key)) {
        $missing_apis[] = '<a href="' . esc_url(admin_url('admin.php?page=5d-review-builder-settings&tab=api')) . '">ChatGPT API</a>';
    }
    if (empty($google_cse_key) || empty($google_cse_id)) {
        $missing_apis[] = '<a href="' . esc_url(admin_url('admin.php?page=5d-review-builder-settings&tab=google')) . '">Google CSE</a>';
    }

    if (!empty($missing_apis)) {
        $notice_class = count($missing_apis) >= 2 ? 'notice-error' : 'notice-warning';
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible hrs-api-notice">
            <p>
                <strong>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <?php esc_html_e('5D Review Builder:', '5d-review-builder'); ?>
                </strong>
                <?php printf(esc_html__('以下のAPI設定が未完了です: %s', '5d-review-builder'), implode(', ', $missing_apis)); ?>
            </p>
            <p style="margin: 8px 0 0 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-settings')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings" style="margin-top: 3px;"></span>
                    <?php esc_html_e('設定を完了する', '5d-review-builder'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}

if (function_exists('hrs_log')) {
    hrs_log('Plugin main file loaded (v' . HRS_VERSION . ')', 'info');
}