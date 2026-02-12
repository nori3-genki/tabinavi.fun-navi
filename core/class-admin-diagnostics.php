<?php
/**
 * HRS Admin Diagnostics
 * 管理画面 診断ページ
 * 
 * @package Hotel_Review_System
 * @version 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Admin_Diagnostics {
    
    /**
     * 初期化
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu'), 99);
    }
    
    /**
     * メニュー追加
     */
    public static function add_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            '5d-review-builder',
            __('System Diagnostics', '5d-review-builder'),
            __('Diagnostics', '5d-review-builder'),
            'manage_options',
            '5d-review-builder-diagnostics',
            array(__CLASS__, 'render_page')
        );
    }
    
    /**
     * ページ描画
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $loaded = isset($GLOBALS['hrs_loaded_files']) ? $GLOBALS['hrs_loaded_files'] : array();
        $skipped = isset($GLOBALS['hrs_skipped_files']) ? $GLOBALS['hrs_skipped_files'] : array();
        $errors = isset($GLOBALS['hrs_load_errors']) ? $GLOBALS['hrs_load_errors'] : array();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('5D Review Builder Diagnostics', '5d-review-builder'); ?></h1>
            
            <!-- システム情報 -->
            <h2>System Info</h2>
            <table class="widefat" style="max-width:600px;">
                <tr><td>Plugin Version</td><td><strong><?php echo esc_html(HRS_VERSION); ?></strong></td></tr>
                <tr><td>PHP Version</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><td>WordPress Version</td><td><?php echo esc_html($GLOBALS['wp_version']); ?></td></tr>
                <tr><td>HQC Generator</td><td><?php echo class_exists('HRS_HQC_Generator') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Custom Post Type</td><td><?php echo class_exists('HRS_Custom_Post_Type') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Learning System</td><td><?php echo class_exists('HRS_HQC_Learning_Module') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Learning Tables</td><td><?php echo self::check_learning_tables(); ?></td></tr>
                <tr><td>Learning Enabled</td><td><?php echo get_option('hrs_hqc_learning_enabled', true) ? '✅ Yes' : '❌ No'; ?></td></tr>
                <tr><td>Mapping Manager</td><td><?php echo class_exists('HRS_Mapping_Manager') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Mapping UI</td><td><?php echo class_exists('HRS_Mapping_UI') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Performance Tracker</td><td><?php echo class_exists('HRS_Performance_Tracker') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>Performance Admin</td><td><?php echo class_exists('HRS_Performance_Admin_Page') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>CSV Importer</td><td><?php echo class_exists('HRS_CSV_Importer') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>HQC Bridge</td><td><?php echo class_exists('HRS_Performance_HQC_Bridge') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                <tr><td>API Scheduler</td><td><?php echo class_exists('HRS_API_Scheduler') ? '✅ Active' : '❌ Inactive'; ?></td></tr>
            </table>
            
            <!-- 学習統計 -->
            <?php self::render_learning_stats(); ?>
            
            <!-- パフォーマンス統計 -->
            <?php self::render_performance_stats(); ?>
            
            <!-- 読み込みファイル -->
            <h2><?php esc_html_e('Loaded files', '5d-review-builder'); ?> (<?php echo count($loaded); ?>)</h2>
            <ul style="background:#f0f0f0;padding:15px;max-width:600px;">
                <?php foreach ($loaded as $f) : ?>
                    <li style="color:green;">✅ <?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <!-- スキップファイル -->
            <h2><?php esc_html_e('Skipped files (not found)', '5d-review-builder'); ?></h2>
            <?php if (empty($skipped)) : ?>
                <p style="color:green;">✅ <?php esc_html_e('All files loaded successfully', '5d-review-builder'); ?></p>
            <?php else : ?>
                <ul style="background:#fff0f0;padding:15px;max-width:600px;">
                    <?php foreach ($skipped as $f) : ?>
                        <li style="color:red;">❌ <?php echo esc_html($f); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <!-- エラー -->
            <h2><?php esc_html_e('Load errors', '5d-review-builder'); ?></h2>
            <?php if (empty($errors)) : ?>
                <p style="color:green;">✅ <?php esc_html_e('No errors', '5d-review-builder'); ?></p>
            <?php else : ?>
                <ul style="background:#fff0f0;padding:15px;max-width:600px;">
                    <?php foreach ($errors as $e) : ?>
                        <li style="color:red;">❌ <?php echo esc_html($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * 学習テーブル存在チェック
     * 
     * @return string
     */
    private static function check_learning_tables() {
        if (!class_exists('HRS_HQC_DB_Installer') || !method_exists('HRS_HQC_DB_Installer', 'tables_exist')) {
            return '❌ Missing';
        }
        
        try {
            return HRS_HQC_DB_Installer::tables_exist() ? '✅ Exist' : '❌ Missing';
        } catch (Throwable $e) {
            return '⚠️ Check Error';
        }
    }
    
    /**
     * 学習統計描画
     */
    private static function render_learning_stats() {
        if (!class_exists('HRS_HQC_Learning_Module') || !method_exists('HRS_HQC_Learning_Module', 'get_instance')) {
            return;
        }
        
        try {
            $learning = HRS_HQC_Learning_Module::get_instance();
            if (!method_exists($learning, 'get_statistics')) {
                return;
            }
            
            $stats = $learning->get_statistics();
            ?>
            <h2>Learning Statistics</h2>
            <table class="widefat" style="max-width:600px;">
                <?php if (isset($stats['history'])) : ?>
                    <tr><td>Total Generations</td><td><?php echo esc_html($stats['history']['total_count'] ?? 0); ?></td></tr>
                    <tr><td>Average Score</td><td><?php echo esc_html(round($stats['history']['avg_score'] ?? 0, 1)); ?>%</td></tr>
                    <tr><td>High Quality (80+)</td><td><?php echo esc_html($stats['history']['high_quality_count'] ?? 0); ?></td></tr>
                    <tr><td>Low Quality (&lt;50)</td><td><?php echo esc_html($stats['history']['low_quality_count'] ?? 0); ?></td></tr>
                <?php endif; ?>
                <?php if (isset($stats['hotels'])) : ?>
                    <tr><td>Hotels Learned</td><td><?php echo esc_html($stats['hotels']['count'] ?? 0); ?></td></tr>
                <?php endif; ?>
            </table>
            <?php
        } catch (Throwable $e) {
            echo '<p style="color:orange;">⚠️ Learning statistics unavailable: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * パフォーマンス統計描画
     */
    private static function render_performance_stats() {
        if (!class_exists('HRS_Performance_Tracker')) {
            return;
        }
        
        try {
            $tracker = new HRS_Performance_Tracker();
            $summary = $tracker->get_summary();
            ?>
            <h2>Performance Statistics</h2>
            <table class="widefat" style="max-width:600px;">
                <tr><td>Total Articles Tracked</td><td><?php echo esc_html($summary['total_articles'] ?? 0); ?></td></tr>
                <?php if (isset($summary['metrics'])) : ?>
                    <tr><td>Avg Time on Page</td><td><?php echo esc_html($summary['metrics']['avg_time_on_page']['current'] ?? 0); ?> sec</td></tr>
                    <tr><td>Avg Bounce Rate</td><td><?php echo esc_html($summary['metrics']['bounce_rate']['current'] ?? 0); ?>%</td></tr>
                    <tr><td>Avg CTR</td><td><?php echo esc_html($summary['metrics']['ctr']['current'] ?? 0); ?>%</td></tr>
                    <tr><td>Avg Position</td><td><?php echo esc_html($summary['metrics']['avg_position']['current'] ?? 0); ?></td></tr>
                <?php endif; ?>
            </table>
            <?php
        } catch (Throwable $e) {
            echo '<p style="color:orange;">⚠️ Performance statistics unavailable: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
}

// 自動初期化
HRS_Admin_Diagnostics::init();