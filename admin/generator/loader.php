<?php
/**
 * HRS Article Generator - ローダー（完全修正版・堅牢版）
 *
 * 修正内容:
 *  - 初期化を admin_init → plugins_loaded に変更（AJAX対応）
 *  - ファイル末尾の早期returnを完全削除
 *  - 権限を edit_posts に統一
 *  - スラッグ修正ツールの読み込みを堅牢化（2025-2026年基準）
 *
 * @package HRS\Admin\Generator
 * @version 5.0.2-ROBUST
 */
if (!defined('ABSPATH')) {
    exit;
}

// ── 定数定義 ───────────────────────────────────────────────
if (!defined('HRS_GENERATOR_PATH')) {
    define('HRS_GENERATOR_PATH', __DIR__);
}

// プラグイン全体のルートディレクトリを可能な限り安全に決定
if (!defined('HRS_PLUGIN_DIR')) {
    // HRS_PLUGIN_DIR が定義されていない場合のフォールバック
    // generator/ が plugins/直下にあることを前提としない
    $possible_plugin_root = dirname(HRS_GENERATOR_PATH);
    
    // より確からしい判定（wp-content/plugins/ 配下であることを確認）
    if (strpos($possible_plugin_root, WP_PLUGIN_DIR) === 0) {
        define('HRS_PLUGIN_DIR', trailingslashit($possible_plugin_root));
    } else {
        // 最終フォールバック：致命的エラーにならないよう空文字列
        define('HRS_PLUGIN_DIR', '');
    }
}

// ── クラス読み込み（依存順） ─────────────────────────────────
require_once HRS_GENERATOR_PATH . '/class-article-helpers.php';
require_once HRS_GENERATOR_PATH . '/class-article-post-handler.php';
require_once HRS_GENERATOR_PATH . '/class-article-generator-ui.php';
require_once HRS_GENERATOR_PATH . '/class-article-generator-ajax.php';
require_once HRS_GENERATOR_PATH . '/class-article-generator.php';

// ── スラッグ一括修正ツール（オプション・堅牢読み込み） ────────
$slug_fixer_path = HRS_PLUGIN_DIR . 'tools/class-slug-fixer.php';

if ($slug_fixer_path && file_exists($slug_fixer_path)) {
    require_once $slug_fixer_path;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[HRS Generator] Slug Fixer tool loaded: ' . $slug_fixer_path);
    }
} elseif (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[HRS Generator] Slug Fixer tool not found at: ' . $slug_fixer_path);
}

// ── 初期化（plugins_loaded で確実に実行） ─────────────────────
add_action('plugins_loaded', function () {
    // 管理画面 または AJAX のときだけ初期化
    if (!is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (class_exists('HRS_Article_Generator')) {
        HRS_Article_Generator::init();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Generator] Initialized on plugins_loaded');
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Generator] ERROR: HRS_Article_Generator class not found');
        }
    }
}, 20);

// ── 管理メニュー登録 ──────────────────────────────────────────
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=hotel-review',
        __('AI記事生成', '5d-review-builder'),
        __('AI記事生成', '5d-review-builder'),
        'edit_posts',           // 編集者以上で表示
        'hrs-article-generator',
        ['HRS_Article_Generator', 'render_page']
    );
});

// ── 後方互換関数（メソッド名を generate_article に統一） ───────
if (!function_exists('hrs_generate_article')) {
    function hrs_generate_article($hotel_name, $location = '', $options = []) {
        if (!class_exists('HRS_Article_Generator')) {
            return new WP_Error('generator_not_loaded', 'HRS Article Generator is not loaded.');
        }
        return HRS_Article_Generator::init()
            ->generate($hotel_name, array_merge(['location' => $location], $options));
    }
}

if (!function_exists('hrs_insert_post_direct')) {
    function hrs_insert_post_direct($hotel_name, $article, $options = []) {
        if (!class_exists('HRS_Article_Generator')) {
            return new WP_Error('generator_not_loaded', 'HRS Article Generator is not loaded.');
        }
        return HRS_Article_Generator::init()
            ->insert_post_direct($hotel_name, $article, $options);
    }
}