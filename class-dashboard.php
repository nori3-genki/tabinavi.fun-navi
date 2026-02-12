<?php
/**
 * Dashboard - メインクラス（ローダー）
 * 
 * 新しいダッシュボードクラスを読み込み・呼び出す
 * 
 * @package Hotel_Review_System
 * @version 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ダッシュボード関連ファイルを読み込み
$dashboard_dir = __DIR__ . '/dashboard/';

if (file_exists($dashboard_dir . 'class-dashboard-page.php')) {
    require_once $dashboard_dir . 'class-dashboard-page.php';
}

if (file_exists($dashboard_dir . 'class-dashboard-data.php')) {
    require_once $dashboard_dir . 'class-dashboard-data.php';
}

if (file_exists($dashboard_dir . 'class-dashboard-styles.php')) {
    require_once $dashboard_dir . 'class-dashboard-styles.php';
}

/**
 * ダッシュボードクラス
 * 
 * 後方互換性のために HRS_Dashboard クラス名を維持
 * 内部では HRS_Dashboard_Page に処理を委譲
 */
class HRS_Dashboard {

    /**
     * ダッシュボードページインスタンス
     * 
     * @var HRS_Dashboard_Page|null
     */
    private $page = null;

    /**
     * コンストラクタ
     */
    public function __construct() {
        if (class_exists('HRS_Dashboard_Page')) {
            $this->page = new HRS_Dashboard_Page();
        }
    }

    /**
     * ダッシュボードを表示
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', '5d-review-builder'));
        }

        // 新しいダッシュボードページクラスがあれば使用
        if ($this->page && method_exists($this->page, 'render')) {
            $this->page->render();
            return;
        }

        // フォールバック: 基本的なダッシュボードを表示
        $this->render_fallback();
    }

    /**
     * フォールバック表示
     * 
     * HRS_Dashboard_Page が利用できない場合の簡易表示
     */
    private function render_fallback() {
        $stats = $this->get_basic_stats();
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-dashboard"></span> 5D Review Builder</h1>
            <p>AI powered Hotel Review System - v<?php echo defined('HRS_VERSION') ? HRS_VERSION : '7.1.0'; ?></p>
            
            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2>統計情報</h2>
                <table class="widefat" style="margin-top: 10px;">
                    <tr><td>総記事数</td><td><strong><?php echo esc_html($stats['total']); ?></strong></td></tr>
                    <tr><td>公開済み</td><td><strong><?php echo esc_html($stats['published']); ?></strong></td></tr>
                    <tr><td>下書き</td><td><strong><?php echo esc_html($stats['draft']); ?></strong></td></tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2>クイックアクション</h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-generator')); ?>" class="button button-primary">🚀 記事生成</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-settings')); ?>" class="button">⚙️ 設定</a>
                </p>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2>システム情報</h2>
                <p>WordPress: <?php echo esc_html(get_bloginfo('version')); ?></p>
                <p>PHP: <?php echo esc_html(PHP_VERSION); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * 基本統計情報を取得
     * 
     * @return array
     */
    private function get_basic_stats() {
        // HRS_Dashboard_Data があれば使用
        if (class_exists('HRS_Dashboard_Data') && method_exists('HRS_Dashboard_Data', 'get_statistics')) {
            return HRS_Dashboard_Data::get_statistics();
        }

        // フォールバック: 直接取得
        $counts = wp_count_posts('hotel-review');
        
        return array(
            'total'     => isset($counts->publish) ? (int) $counts->publish + (int) ($counts->draft ?? 0) : 0,
            'published' => isset($counts->publish) ? (int) $counts->publish : 0,
            'draft'     => isset($counts->draft) ? (int) $counts->draft : 0,
            'today'     => 0,
        );
    }
}