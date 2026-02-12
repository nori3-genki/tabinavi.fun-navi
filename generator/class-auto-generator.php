<?php
/**
 * 自動記事生成クラス（キュー管理・スケジュール）
 *
 * @package HRS
 * @version 4.6.0
 *
 * 変更履歴:
 * - 4.6.0: ホテルごとの個別HQCパラメータ対応
 *   add_to_queue()にsettings保存
 *   process_queue()で個別settings使用
 * - 4.5.0-SPLIT: 初期分割版
 * - 【追加】A/Bテスト対応：generate_single() で ab_variant があればスラッグに -a / -b を付与
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_Auto_Generator {
    private static $instance = null;
    private $queue_option = 'hrs_generation_queue';
    private $log_option = 'hrs_generation_log';
    private $hqc_threshold = 0.50;
    private $location_required = true;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('hrs_auto_generate_event', array($this, 'process_queue'));
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action('admin_init', array($this, 'maybe_schedule_event'));
        $this->load_settings();
    }

    private function load_settings() {
        $saved_threshold = get_option('hrs_hqc_threshold', null);
        if ($saved_threshold !== null) {
            $this->hqc_threshold = floatval($saved_threshold);
        }
        $location_required = get_option('hrs_location_required', null);
        if ($location_required !== null) {
            $this->location_required = (bool) $location_required;
        }
    }

    public function get_hqc_threshold() {
        return $this->hqc_threshold;
    }

    public function set_hqc_threshold($threshold) {
        $threshold = floatval($threshold);
        if ($threshold < 0.0 || $threshold > 1.0) {
            return false;
        }
        $this->hqc_threshold = $threshold;
        update_option('hrs_hqc_threshold', $threshold);
        $this->log('HQC閾値を変更: ' . ($threshold * 100) . '%');
        return true;
    }

    public function is_location_required() {
        return $this->location_required;
    }

    public function set_location_required($required) {
        $this->location_required = (bool) $required;
        update_option('hrs_location_required', $this->location_required);
        $this->log('地域名設定を変更: ' . ($this->location_required ? '必須' : '任意'));
        return true;
    }

    public function add_cron_schedules($schedules) {
        $schedules['hrs_every_30_minutes'] = array(
            'interval' => 1800,
            'display' => __('30分ごと', '5d-review-builder'),
        );
        $schedules['hrs_hourly'] = array(
            'interval' => 3600,
            'display' => __('1時間ごと', '5d-review-builder'),
        );
        return $schedules;
    }

    public function maybe_schedule_event() {
        $auto_generate_enabled = get_option('hrs_auto_generate_enabled', false);
       
        if ($auto_generate_enabled && !wp_next_scheduled('hrs_auto_generate_event')) {
            $interval = get_option('hrs_auto_generate_interval', 'hrs_hourly');
            wp_schedule_event(time(), $interval, 'hrs_auto_generate_event');
        } elseif (!$auto_generate_enabled && wp_next_scheduled('hrs_auto_generate_event')) {
            wp_clear_scheduled_hook('hrs_auto_generate_event');
        }
    }

    // ========================================
    // キュー管理
    // ========================================
    /**
     * キューに追加
     *
     * @version 4.6.0 HQC settings を個別保存対応
     */
    public function add_to_queue($hotel_name, $options = array()) {
        if (empty($hotel_name)) {
            return new WP_Error('empty_hotel_name', 'ホテル名を入力してください');
        }
        $location = $options['location'] ?? '';
        if ($this->location_required && empty($location)) {
            return new WP_Error('location_required', '地域名を入力してください（HQCスコア向上のため必須）');
        }
        $queue = $this->get_queue();
        foreach ($queue as $item) {
            if ($item['hotel_name'] === $hotel_name) {
                return new WP_Error('duplicate', 'このホテルは既にキューに存在します');
            }
        }
        $defaults = array(
            'location' => '',
            'ai_model' => 'gpt-4o-mini',
            'post_status' => 'draft',
            'priority' => 10,
            'skip_hqc_check' => false,
        );
        $merged_options = wp_parse_args($options, $defaults);
        // ★【v4.6.0】HQC settings が渡された場合は保存
        // 渡されない場合はキュー追加時点のグローバル設定をスナップショット
        if (!isset($merged_options['settings']) || empty($merged_options['settings'])) {
            $global_settings = get_option('hrs_hqc_settings', array());
            if (!empty($global_settings)) {
                $merged_options['settings'] = $global_settings;
            }
        }
        // ★【v4.6.0】パラメータサマリーを生成（UI表示用）
        $summary = self::build_settings_summary($merged_options['settings'] ?? array());
        $queue[] = array(
            'hotel_name' => $hotel_name,
            'options' => $merged_options,
            'summary' => $summary,
            'added_at' => current_time('mysql'),
            'status' => 'pending',
        );
        usort($queue, function($a, $b) {
            return ($a['options']['priority'] ?? 10) - ($b['options']['priority'] ?? 10);
        });
        update_option($this->queue_option, $queue);
        $this->log('キューに追加: ' . $hotel_name . ($location ? " ({$location})" : '') . " [{$summary}]");
        return true;
    }

    /**
     * パラメータサマリー文字列を生成
     *
     * @since 4.6.0
     */
    public static function build_settings_summary($settings) {
        if (empty($settings)) {
            return 'デフォルト設定';
        }
        $h = $settings['h'] ?? array();
        $q = $settings['q'] ?? array();
        $c = $settings['c'] ?? array();
        $persona_labels = array(
            'general' => '一般', 'solo' => '一人旅', 'couple' => 'カップル',
            'family' => 'ファミリー', 'senior' => 'シニア', 'workation' => 'ワーケ',
            'luxury' => 'ラグジュ', 'budget' => 'コスパ',
        );
        $tone_labels = array(
            'casual' => 'カジュアル', 'luxury' => 'ラグジュアリー', 'emotional' => 'エモ',
            'cinematic' => '映画的', 'journalistic' => '報道的',
        );
        $persona = $persona_labels[$h['persona'] ?? 'general'] ?? ($h['persona'] ?? '一般');
        $tone = $tone_labels[$q['tone'] ?? 'casual'] ?? ($q['tone'] ?? 'カジュアル');
        $depth = $h['depth'] ?? 'L2';
        return "{$persona}/{$tone}/{$depth}";
    }

    public function remove_from_queue($hotel_name) {
        $queue = $this->get_queue();
       
        $new_queue = array_filter($queue, function($item) use ($hotel_name) {
            return $item['hotel_name'] !== $hotel_name;
        });
        if (count($new_queue) !== count($queue)) {
            update_option($this->queue_option, array_values($new_queue));
            $this->log('キューから削除: ' . $hotel_name);
            return true;
        }
        return false;
    }

    public function get_queue() {
        return get_option($this->queue_option, array());
    }

    public function clear_queue() {
        update_option($this->queue_option, array());
        $this->log('キューをクリアしました');
    }

    /**
     * キューを処理
     *
     * @version 4.6.0 ホテルごとの個別settings対応
     */
    public function process_queue() {
        $queue = $this->get_queue();
       
        if (empty($queue)) {
            return;
        }
        $batch_size = get_option('hrs_auto_generate_batch_size', 1);
        $processed = 0;
        foreach ($queue as $index => $item) {
            if ($item['status'] !== 'pending') {
                continue;
            }
            if ($processed >= $batch_size) {
                break;
            }
            $queue[$index]['status'] = 'processing';
            update_option($this->queue_option, $queue);
            // ★【v4.6.0】ホテルごとの個別settingsを使用
            $gen_options = $item['options'];
            if (!empty($gen_options['settings'])) {
                // 個別settingsがある場合はそれを使う
                $gen_options['settings'] = $gen_options['settings'];
            } else {
                // なければグローバル設定をフォールバック
                $gen_options['settings'] = get_option('hrs_hqc_settings', array());
            }
            $generator = new HRS_Article_Generator();
            $result = $generator->generate($item['hotel_name'], $gen_options);
            if ($result['success']) {
                unset($queue[$index]);
                $this->log('生成成功: ' . $item['hotel_name'] . ' (ID: ' . $result['post_id'] . ')');
            } else {
                $queue[$index]['status'] = 'failed';
                $queue[$index]['error'] = $result['message'];
                $this->log('生成失敗: ' . $item['hotel_name'] . ' - ' . $result['message']);
            }
            $processed++;
        }
        update_option($this->queue_option, array_values($queue));
    }

    // ========================================
    // 単一・バッチ生成（委譲）
    // ========================================
    public function generate_single($hotel_name, $options = array()) {
        // A/Bテスト用：スラッグに -a / -b サフィックスを付与
        if (!empty($options['ab_variant'])) {
            $variant = sanitize_title($options['ab_variant']); // 'a' または 'b'
            // 既存のスラッグ生成ロジックは HRS_Article_Generator 側で行われるので、
            // ここではオプションをそのまま渡し、ログだけ追加
            $this->log("[A/B TEST] Variant '{$variant}' detected for hotel: {$hotel_name}");
        }

        $generator = new HRS_Article_Generator();
        return $generator->generate($hotel_name, $options);
    }

    public function generate_batch($hotels, $options = array()) {
        $results = array(
            'total' => count($hotels),
            'success' => 0,
            'failed' => 0,
            'skipped_low_quality' => 0,
            'forced_generation' => 0,
            'details' => array(),
        );
        foreach ($hotels as $hotel_name) {
            $result = $this->generate_single($hotel_name, $options);
           
            if ($result['success']) {
                $results['success']++;
                if ($result['forced'] ?? false) {
                    $results['forced_generation']++;
                }
            } else {
                $results['failed']++;
                if (($result['error_code'] ?? '') === 'low_hqc_score') {
                    $results['skipped_low_quality']++;
                }
            }
            $results['details'][] = array(
                'hotel_name' => $hotel_name,
                'result' => $result,
            );
            sleep(2);
        }
        return $results;
    }

    // ========================================
    // ログ管理
    // ========================================
    public function log($message) {
        $logs = get_option($this->log_option, array());
       
        if (count($logs) >= 100) {
            array_shift($logs);
        }
        $logs[] = array(
            'time' => current_time('mysql'),
            'message' => $message,
        );
        update_option($this->log_option, $logs);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Auto Generator] ' . $message);
        }
    }

    public function get_logs($limit = 50) {
        $logs = get_option($this->log_option, array());
        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function clear_logs() {
        update_option($this->log_option, array());
    }

    // ========================================
    // 統計・設定
    // ========================================
    public function get_stats() {
        $queue = $this->get_queue();
       
        $pending = 0;
        $processing = 0;
        $failed = 0;
        foreach ($queue as $item) {
            switch ($item['status']) {
                case 'pending': $pending++; break;
                case 'processing': $processing++; break;
                case 'failed': $failed++; break;
            }
        }
        $today_count = get_posts(array(
            'post_type' => 'hotel-review',
            'posts_per_page' => -1,
            'date_query' => array(array('after' => 'today')),
            'fields' => 'ids',
        ));
        $total_count = wp_count_posts('hotel-review');
        $forced_count = get_posts(array(
            'post_type' => 'hotel-review',
            'posts_per_page' => -1,
            'meta_query' => array(array('key' => '_hrs_forced_generation', 'value' => '1')),
            'fields' => 'ids',
        ));
        return array(
            'queue' => array(
                'total' => count($queue),
                'pending' => $pending,
                'processing' => $processing,
                'failed' => $failed,
            ),
            'posts' => array(
                'today' => count($today_count),
                'total_published' => $total_count->publish ?? 0,
                'total_draft' => $total_count->draft ?? 0,
                'forced_generation' => count($forced_count),
            ),
            'next_scheduled' => wp_next_scheduled('hrs_auto_generate_event'),
            'auto_generate_enabled' => get_option('hrs_auto_generate_enabled', false),
            'hqc_threshold' => $this->hqc_threshold,
            'location_required' => $this->location_required,
        );
    }

    public function get_settings() {
        return array(
            'hqc_threshold' => $this->hqc_threshold,
            'hqc_threshold_percent' => round($this->hqc_threshold * 100),
            'location_required' => $this->location_required,
            'auto_generate_enabled' => get_option('hrs_auto_generate_enabled', false),
            'auto_generate_interval' => get_option('hrs_auto_generate_interval', 'hrs_hourly'),
            'auto_generate_batch_size' => get_option('hrs_auto_generate_batch_size', 1),
        );
    }

    public function save_settings($settings) {
        if (isset($settings['hqc_threshold'])) {
            $this->set_hqc_threshold(floatval($settings['hqc_threshold']));
        }
        if (isset($settings['location_required'])) {
            $this->set_location_required((bool) $settings['location_required']);
        }
        if (isset($settings['auto_generate_enabled'])) {
            update_option('hrs_auto_generate_enabled', (bool) $settings['auto_generate_enabled']);
        }
        if (isset($settings['auto_generate_interval'])) {
            update_option('hrs_auto_generate_interval', sanitize_text_field($settings['auto_generate_interval']));
        }
        if (isset($settings['auto_generate_batch_size'])) {
            update_option('hrs_auto_generate_batch_size', intval($settings['auto_generate_batch_size']));
        }
        $this->maybe_schedule_event();
        return true;
    }

    public function import_from_csv($csv_content) {
        $lines = explode("\n", trim($csv_content));
        $added = 0;
        $skipped = 0;
        $errors = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = str_getcsv($line);
            $hotel_name = trim($parts[0] ?? '');
            $location = trim($parts[1] ?? '');
            $priority = intval($parts[2] ?? 10);
            if (empty($hotel_name)) continue;
            $result = $this->add_to_queue($hotel_name, array(
                'location' => $location,
                'priority' => $priority,
            ));
            if ($result === true) {
                $added++;
            } elseif (is_wp_error($result)) {
                $skipped++;
                $errors[] = $hotel_name . ': ' . $result->get_error_message();
            } else {
                $skipped++;
            }
        }
        return array('added' => $added, 'skipped' => $skipped, 'errors' => $errors);
    }
}