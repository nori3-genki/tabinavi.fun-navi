<?php
/**
 * 5D Review Builder - Admin Menu
 *
 * 管理画面メニュー管理クラス
 *
 * @package Hotel_Review_System
 * @version 7.2.4
 * @since 7.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu Class
 */
class HRS_Admin_Menu {

    /**
     * メニュースラッグ
     */
    const MENU_SLUG = '5d-review-builder';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * メニュー登録
     */
    public function register_menu() {

        // メインメニュー
        add_menu_page(
            __('5D Review Builder', '5d-review-builder'),
            __('5D Review', '5d-review-builder'),
            'edit_posts',
            self::MENU_SLUG,
            [$this, 'render_dashboard'],
            'dashicons-building',
            30
        );

        // ダッシュボード
        add_submenu_page(
            self::MENU_SLUG,
            __('ダッシュボード', '5d-review-builder'),
            __('📊 ダッシュボード', '5d-review-builder'),
            'edit_posts',
            self::MENU_SLUG,
            [$this, 'render_dashboard']
        );

        // 記事生成（HQC Generator）
        add_submenu_page(
            self::MENU_SLUG,
            __('記事生成', '5d-review-builder'),
            __('🚀 記事生成', '5d-review-builder'),
            'edit_posts',
            self::MENU_SLUG . '-generator',
            [$this, 'render_article_generator']
        );

        // 手動生成
        add_submenu_page(
            self::MENU_SLUG,
            __('手動生成', '5d-review-builder'),
            __('✍️ 手動生成', '5d-review-builder'),
            'edit_posts',
            self::MENU_SLUG . '-manual',
            [$this, 'render_manual']
        );

        // 記事育成
        add_submenu_page(
            self::MENU_SLUG,
            __('記事育成', '5d-review-builder'),
            __('🌱 記事育成', '5d-review-builder'),
            'edit_posts',
            self::MENU_SLUG . '-nurture',
            [$this, 'render_nurture']
        );

        // 学習ダッシュボード
        add_submenu_page(
            self::MENU_SLUG,
            __('学習ダッシュボード', '5d-review-builder'),
            __('📈 学習ダッシュボード', '5d-review-builder'),
            'edit_posts',
            'hrs-hqc-dashboard',
            [$this, 'render_learning_dashboard']
        );

        // 設定
        add_submenu_page(
            self::MENU_SLUG,
            __('設定', '5d-review-builder'),
            __('⚙️ 設定', '5d-review-builder'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings']
        );

        // カテゴリ移行
        add_submenu_page(
            self::MENU_SLUG,
            __('カテゴリ移行', '5d-review-builder'),
            __('🔄 カテゴリ移行', '5d-review-builder'),
            'manage_options',
            'hrs-category-migration',
            [$this, 'render_category_migration']
        );
    }

    /**
     * アセット読み込み
     */
    public function enqueue_assets($hook) {

        if (strpos($hook, self::MENU_SLUG) === false && strpos($hook, 'hrs-hqc') === false && strpos($hook, 'hrs-category') === false) {
            return;
        }

        $version = defined('HRS_VERSION') ? HRS_VERSION : '7.2.4';

        wp_enqueue_style(
            'hrs-admin-style',
            HRS_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            $version
        );

        wp_enqueue_script(
            'hrs-admin-script',
            HRS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            $version,
            true
        );

        wp_localize_script('hrs-admin-script', 'hrsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('hrs_ajax_nonce'),
        ]);
    }

    /**
     * ダッシュボード
     */
    public function render_dashboard() {
        if (class_exists('HRS_Dashboard')) {
            (new HRS_Dashboard())->render();
        } else {
            $this->render_fallback_page('ダッシュボード', 'HRS_Dashboard');
        }
    }

    /**
     * 記事生成（HQC Generator）
     */
    public function render_article_generator() {
        $file_path = HRS_PLUGIN_DIR . 'includes/admin/class-hqc-generator.php';

        if (file_exists($file_path)) {
            require_once $file_path;

            if (class_exists('HRS_HQC_Generator')) {
                HRS_HQC_Generator::render_page();
                return;
            }
        }

        $this->render_fallback_page('記事生成', 'HRS_HQC_Generator');
    }

    /**
     * 手動生成
     */
    public function render_manual() {
        $file_path = HRS_PLUGIN_DIR . 'includes/admin/class-generator-page.php';

        if (file_exists($file_path)) {
            require_once $file_path;
        }

        if (class_exists('HRS_Generator_Page')) {
            (new HRS_Generator_Page())->render();
        } else {
            $this->render_fallback_page('手動生成', 'HRS_Generator_Page');
        }
    }

    /**
     * 記事育成
     */
    public function render_nurture() {
        $file_path = HRS_PLUGIN_DIR . 'includes/admin/class-nurture-page.php';

        if (file_exists($file_path)) {
            require_once $file_path;
        }

        if (class_exists('HRS_Nurture_Page')) {
            (new HRS_Nurture_Page())->render();
        } else {
            $this->render_fallback_page('記事育成', 'HRS_Nurture_Page');
        }
    }

    /**
     * 学習ダッシュボード
     */
    public function render_learning_dashboard() {
        if (class_exists('HRS_HQC_Dashboard_Widget')) {
            (new HRS_HQC_Dashboard_Widget())->render_dashboard_page();
        } else {
            $this->render_fallback_page('学習ダッシュボード', 'HRS_HQC_Dashboard_Widget');
        }
    }

    /**
     * 設定
     */
    public function render_settings() {
        if (class_exists('HRS_Settings_Page')) {
            (new HRS_Settings_Page())->render();
        } else {
            $this->render_fallback_page('設定', 'HRS_Settings_Page');
        }
    }

    /**
     * カテゴリ移行
     */
    public function render_category_migration() {
        if (class_exists('HRS_Category_Migration')) {
            HRS_Category_Migration::render_migration_page();
        } else {
            $this->render_fallback_page('カテゴリ移行', 'HRS_Category_Migration');
        }
    }

    /**
     * フォールバック
     */
    private function render_fallback_page($page_name, $class_name) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($page_name); ?></h1>
            <div class="notice notice-error">
                <p>
                    クラス <code><?php echo esc_html($class_name); ?></code> が見つかりません。
                </p>
            </div>
        </div>
        <?php
    }
}