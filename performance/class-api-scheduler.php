<?php
/**
 * API スケジューラー
 * 
 * @package HRS
 * @subpackage Performance
 * @version 1.0.2
 * 
 * 変更履歴:
 * - 1.0.2: on_activation/on_deactivationメソッド追加
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * HRS_API_Scheduler クラス
 * 
 * API呼び出しのスケジューリングと最適化を管理
 */
class HRS_API_Scheduler {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * スケジュール設定
     */
    private $schedules = array();

    /**
     * Cronフック名
     */
    const CRON_HOOK = 'hrs_api_scheduler_cron';

    /**
     * コンストラクタ（public に変更）
     */
    public function __construct() {
        // Cronフックを登録
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_tasks'));
    }

    /**
     * シングルトンインスタンス取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * プラグイン有効化時の処理
     */
    public static function on_activation() {
        // 日次Cronスケジュールを設定
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
        
        // オプション初期化
        if (get_option('hrs_api_scheduler_settings') === false) {
            update_option('hrs_api_scheduler_settings', array(
                'enabled' => true,
                'rate_limit' => 60, // 1分あたりの最大リクエスト数
                'retry_delay' => 30, // リトライ遅延（秒）
            ));
        }
    }

    /**
     * プラグイン無効化時の処理
     */
    public static function on_deactivation() {
        // Cronスケジュールを解除
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        
        // すべてのスケジュールをクリア
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * 初期化
     */
    public function init() {
        // Cronが登録されていなければ再登録
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * スケジュールされたタスクを実行
     */
    public function run_scheduled_tasks() {
        // 将来の実装: GA4/GSCデータの定期取得など
        do_action('hrs_api_scheduler_run');
    }

    /**
     * API呼び出しをスケジュール
     * 
     * @param string $api_type API種別
     * @param array $params パラメータ
     * @param int $delay 遅延秒数
     * @return bool
     */
    public function schedule_call($api_type, $params = array(), $delay = 0) {
        // 将来の実装用
        return true;
    }

    /**
     * レート制限チェック
     * 
     * @param string $api_type API種別
     * @return bool 呼び出し可能かどうか
     */
    public function can_call($api_type) {
        $settings = get_option('hrs_api_scheduler_settings', array());
        $rate_limit = $settings['rate_limit'] ?? 60;
        
        // 現在のリクエスト数を取得
        $current_count = get_transient('hrs_api_call_count_' . $api_type);
        
        if ($current_count === false) {
            // カウンターが存在しない場合は初期化
            set_transient('hrs_api_call_count_' . $api_type, 1, 60);
            return true;
        }
        
        if ($current_count >= $rate_limit) {
            return false;
        }
        
        // カウンターをインクリメント
        set_transient('hrs_api_call_count_' . $api_type, $current_count + 1, 60);
        return true;
    }

    /**
     * API呼び出しを記録
     * 
     * @param string $api_type API種別
     * @param bool $success 成功/失敗
     */
    public function log_call($api_type, $success = true) {
        $log = get_option('hrs_api_call_log', array());
        
        $log[] = array(
            'api_type' => $api_type,
            'success' => $success,
            'timestamp' => current_time('mysql'),
        );
        
        // 最新100件のみ保持
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        
        update_option('hrs_api_call_log', $log);
    }
}