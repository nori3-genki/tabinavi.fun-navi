<?php
/**
 * HRS Performance Tracker
 * パフォーマンスデータ管理・スコア算出
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Performance_Tracker {
    
    /** @var string テーブル名 */
    private $table_name;
    
    /** @var array スコア算出の重み配分 */
    private $score_weights = array(
        'avg_time_on_page' => 0.25,
        'bounce_rate'      => 0.25,
        'ctr'              => 0.25,
        'avg_position'     => 0.25
    );
    
    /** @var array 閾値設定 */
    private $thresholds = array(
        'excellent' => 80,  // 80以上 = 優良
        'normal'    => 50   // 50以上 = 普通、未満 = 要改善
    );
    
    /** @var array 指標の基準値（正規化用） */
    private $benchmarks = array(
        'avg_time_on_page' => array('min' => 0, 'max' => 300, 'direction' => 'higher'),    // 秒
        'bounce_rate'      => array('min' => 0, 'max' => 100, 'direction' => 'lower'),     // %
        'ctr'              => array('min' => 0, 'max' => 10,  'direction' => 'higher'),    // %
        'avg_position'     => array('min' => 1, 'max' => 100, 'direction' => 'lower')      // 順位
    );
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hrs_performance_data';
    }
    
    /**
     * DBテーブル作成
     * プラグイン有効化時に呼び出し
     * 
     * @return bool 成功/失敗
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            avg_time_on_page FLOAT DEFAULT 0,
            bounce_rate FLOAT DEFAULT 0,
            ctr FLOAT DEFAULT 0,
            avg_position FLOAT DEFAULT 0,
            impressions INT(11) DEFAULT 0,
            performance_score FLOAT DEFAULT 0,
            data_date DATE NOT NULL,
            source VARCHAR(20) DEFAULT 'csv',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY data_date (data_date),
            KEY performance_score (performance_score),
            UNIQUE KEY post_date_unique (post_id, data_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // テーブル存在確認
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)
        );
        
        return ($table_exists === $this->table_name);
    }
    
    /**
     * パフォーマンスデータを保存
     * 
     * @param array $data データ配列
     * @return int|false 挿入/更新されたID または false
     */
    public function save_data($data) {
        global $wpdb;
        
        // 必須項目チェック
        if (empty($data['post_id']) || empty($data['data_date'])) {
            return false;
        }
        
        // スコア算出
        $score = $this->calculate_score($data);
        
        $insert_data = array(
            'post_id'          => intval($data['post_id']),
            'avg_time_on_page' => floatval($data['avg_time_on_page'] ?? 0),
            'bounce_rate'      => floatval($data['bounce_rate'] ?? 0),
            'ctr'              => floatval($data['ctr'] ?? 0),
            'avg_position'     => floatval($data['avg_position'] ?? 0),
            'impressions'      => intval($data['impressions'] ?? 0),
            'performance_score'=> $score,
            'data_date'        => sanitize_text_field($data['data_date']),
            'source'           => sanitize_text_field($data['source'] ?? 'csv')
        );
        
        $format = array('%d', '%f', '%f', '%f', '%f', '%d', '%f', '%s', '%s');
        
        // 既存データチェック（同じ記事・同じ日付）
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE post_id = %d AND data_date = %s",
            $insert_data['post_id'],
            $insert_data['data_date']
        ));
        
        if ($existing) {
            // 更新
            $result = $wpdb->update(
                $this->table_name,
                $insert_data,
                array('id' => $existing),
                $format,
                array('%d')
            );
            return $result !== false ? $existing : false;
        } else {
            // 新規挿入
            $result = $wpdb->insert($this->table_name, $insert_data, $format);
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * 特定記事のデータ取得
     * 
     * @param int $post_id 記事ID
     * @param string $date 特定日付（省略時は最新）
     * @return object|null データオブジェクト
     */
    public function get_data_by_post($post_id, $date = null) {
        global $wpdb;
        
        if ($date) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE post_id = %d AND data_date = %s",
                $post_id,
                $date
            ));
        } else {
            // 最新データを取得
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY data_date DESC LIMIT 1",
                $post_id
            ));
        }
        
        return $result;
    }
    
    /**
     * 全記事のデータ取得
     * 
     * @param array $args オプション引数
     * @return array データ配列
     */
    public function get_all_data($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby'  => 'performance_score',
            'order'    => 'ASC',
            'limit'    => 100,
            'offset'   => 0,
            'flag'     => '',      // excellent, normal, poor
            'date'     => '',      // 特定日付
            'latest'   => true     // 各記事の最新データのみ
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // 許可されたカラムのみ
        $allowed_orderby = array('post_id', 'avg_time_on_page', 'bounce_rate', 'ctr', 'avg_position', 'performance_score', 'data_date');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'performance_score';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        // フラグフィルタ
        if (!empty($args['flag'])) {
            switch ($args['flag']) {
                case 'excellent':
                    $where_clauses[] = 'performance_score >= %f';
                    $where_values[] = $this->thresholds['excellent'];
                    break;
                case 'normal':
                    $where_clauses[] = 'performance_score >= %f AND performance_score < %f';
                    $where_values[] = $this->thresholds['normal'];
                    $where_values[] = $this->thresholds['excellent'];
                    break;
                case 'poor':
                    $where_clauses[] = 'performance_score < %f';
                    $where_values[] = $this->thresholds['normal'];
                    break;
            }
        }
        
        // 日付フィルタ
        if (!empty($args['date'])) {
            $where_clauses[] = 'data_date = %s';
            $where_values[] = $args['date'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        if ($args['latest'] && empty($args['date'])) {
            // 各記事の最新データのみ取得
            $sql = "SELECT t1.* FROM {$this->table_name} t1
                    INNER JOIN (
                        SELECT post_id, MAX(data_date) as max_date
                        FROM {$this->table_name}
                        GROUP BY post_id
                    ) t2 ON t1.post_id = t2.post_id AND t1.data_date = t2.max_date
                    WHERE {$where_sql}
                    ORDER BY {$orderby} {$order}
                    LIMIT %d OFFSET %d";
        } else {
            $sql = "SELECT * FROM {$this->table_name}
                    WHERE {$where_sql}
                    ORDER BY {$orderby} {$order}
                    LIMIT %d OFFSET %d";
        }
        
        $where_values[] = intval($args['limit']);
        $where_values[] = intval($args['offset']);
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * 全体サマリーを算出
     * 
     * @param string $current_date 現在のデータ日付
     * @param string $previous_date 比較用の前回日付
     * @return array サマリーデータ
     */
    public function get_summary($current_date = '', $previous_date = '') {
        global $wpdb;
        
        // 最新データの集計
        if (empty($current_date)) {
            $current_date = $wpdb->get_var(
                "SELECT MAX(data_date) FROM {$this->table_name}"
            );
        }
        
        if (empty($current_date)) {
            return $this->get_empty_summary();
        }
        
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(avg_time_on_page) as avg_time,
                AVG(bounce_rate) as avg_bounce,
                AVG(ctr) as avg_ctr,
                AVG(avg_position) as avg_position,
                AVG(performance_score) as avg_score,
                COUNT(*) as total_articles
            FROM {$this->table_name}
            WHERE data_date = %s",
            $current_date
        ));
        
        // 前回データの集計（比較用）
        $previous = null;
        if (!empty($previous_date)) {
            $previous = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    AVG(avg_time_on_page) as avg_time,
                    AVG(bounce_rate) as avg_bounce,
                    AVG(ctr) as avg_ctr,
                    AVG(avg_position) as avg_position,
                    AVG(performance_score) as avg_score
                FROM {$this->table_name}
                WHERE data_date = %s",
                $previous_date
            ));
        }
        
        return array(
            'current_date' => $current_date,
            'total_articles' => intval($current->total_articles),
            'metrics' => array(
                'avg_time_on_page' => array(
                    'current'  => round(floatval($current->avg_time), 1),
                    'previous' => $previous ? round(floatval($previous->avg_time), 1) : null,
                    'change'   => $previous ? round(floatval($current->avg_time) - floatval($previous->avg_time), 1) : null,
                    'trend'    => $this->get_trend($current->avg_time, $previous ? $previous->avg_time : null, 'higher')
                ),
                'bounce_rate' => array(
                    'current'  => round(floatval($current->avg_bounce), 1),
                    'previous' => $previous ? round(floatval($previous->avg_bounce), 1) : null,
                    'change'   => $previous ? round(floatval($current->avg_bounce) - floatval($previous->avg_bounce), 1) : null,
                    'trend'    => $this->get_trend($current->avg_bounce, $previous ? $previous->avg_bounce : null, 'lower')
                ),
                'ctr' => array(
                    'current'  => round(floatval($current->avg_ctr), 2),
                    'previous' => $previous ? round(floatval($previous->avg_ctr), 2) : null,
                    'change'   => $previous ? round(floatval($current->avg_ctr) - floatval($previous->avg_ctr), 2) : null,
                    'trend'    => $this->get_trend($current->avg_ctr, $previous ? $previous->avg_ctr : null, 'higher')
                ),
                'avg_position' => array(
                    'current'  => round(floatval($current->avg_position), 1),
                    'previous' => $previous ? round(floatval($previous->avg_position), 1) : null,
                    'change'   => $previous ? round(floatval($current->avg_position) - floatval($previous->avg_position), 1) : null,
                    'trend'    => $this->get_trend($current->avg_position, $previous ? $previous->avg_position : null, 'lower')
                ),
                'performance_score' => array(
                    'current'  => round(floatval($current->avg_score), 1),
                    'previous' => $previous ? round(floatval($previous->avg_score), 1) : null,
                    'change'   => $previous ? round(floatval($current->avg_score) - floatval($previous->avg_score), 1) : null,
                    'trend'    => $this->get_trend($current->avg_score, $previous ? $previous->avg_score : null, 'higher')
                )
            )
        );
    }
    
    /**
     * 4指標から総合スコアを算出
     * 
     * @param array $data 指標データ
     * @return float スコア（0-100）
     */
    public function calculate_score($data) {
        $normalized_scores = array();
        
        foreach ($this->benchmarks as $metric => $config) {
            $value = floatval($data[$metric] ?? 0);
            $min = $config['min'];
            $max = $config['max'];
            
            // 値を範囲内に制限
            $value = max($min, min($max, $value));
            
            // 正規化（0-100）
            if ($config['direction'] === 'higher') {
                // 高いほど良い（滞在時間、CTR）
                $normalized = (($value - $min) / ($max - $min)) * 100;
            } else {
                // 低いほど良い（直帰率、平均順位）
                $normalized = ((($max - $value) / ($max - $min))) * 100;
            }
            
            $normalized_scores[$metric] = $normalized;
        }
        
        // 加重平均でスコア算出
        $total_score = 0;
        foreach ($this->score_weights as $metric => $weight) {
            $total_score += ($normalized_scores[$metric] ?? 0) * $weight;
        }
        
        return round($total_score, 1);
    }
    
    /**
     * 閾値以下の記事一覧を取得
     * 
     * @param float $threshold 閾値（省略時は設定値）
     * @return array 低パフォーマンス記事一覧
     */
    public function get_low_performers($threshold = null) {
        if ($threshold === null) {
            $threshold = $this->thresholds['normal'];
        }
        
        return $this->get_all_data(array(
            'flag'    => 'poor',
            'orderby' => 'performance_score',
            'order'   => 'ASC',
            'latest'  => true
        ));
    }
    
    /**
     * 古いデータのクリーンアップ
     * 
     * @param int $days 保持日数（デフォルト365日）
     * @return int 削除件数
     */
    public function delete_old_data($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE data_date < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * 記事のフラグ状態を取得
     * 
     * @param int $post_id 記事ID
     * @return string フラグ（excellent/normal/poor/none）
     */
    public function get_flag_for_post($post_id) {
        $data = $this->get_data_by_post($post_id);
        
        if (!$data) {
            return 'none';
        }
        
        $score = floatval($data->performance_score);
        
        if ($score >= $this->thresholds['excellent']) {
            return 'excellent';
        } elseif ($score >= $this->thresholds['normal']) {
            return 'normal';
        } else {
            return 'poor';
        }
    }
    
    /**
     * 時系列データ取得（グラフ用）
     * 
     * @param int $post_id 記事ID（省略時は全体平均）
     * @param int $days 取得日数
     * @return array 時系列データ
     */
    public function get_time_series($post_id = null, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        if ($post_id) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT data_date, avg_time_on_page, bounce_rate, ctr, avg_position, performance_score
                FROM {$this->table_name}
                WHERE post_id = %d AND data_date >= %s
                ORDER BY data_date ASC",
                $post_id,
                $start_date
            ));
        } else {
            // 全体平均
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT data_date,
                    AVG(avg_time_on_page) as avg_time_on_page,
                    AVG(bounce_rate) as bounce_rate,
                    AVG(ctr) as ctr,
                    AVG(avg_position) as avg_position,
                    AVG(performance_score) as performance_score
                FROM {$this->table_name}
                WHERE data_date >= %s
                GROUP BY data_date
                ORDER BY data_date ASC",
                $start_date
            ));
        }
        
        return $results;
    }
    
    /**
     * データ件数を取得
     * 
     * @param string $flag フィルタ（省略時は全件）
     * @return int 件数
     */
    public function get_count($flag = '') {
        global $wpdb;
        
        $where = '1=1';
        $values = array();
        
        switch ($flag) {
            case 'excellent':
                $where = 'performance_score >= %f';
                $values[] = $this->thresholds['excellent'];
                break;
            case 'normal':
                $where = 'performance_score >= %f AND performance_score < %f';
                $values[] = $this->thresholds['normal'];
                $values[] = $this->thresholds['excellent'];
                break;
            case 'poor':
                $where = 'performance_score < %f';
                $values[] = $this->thresholds['normal'];
                break;
        }
        
        // 各記事の最新データのみカウント
        $sql = "SELECT COUNT(DISTINCT post_id) FROM {$this->table_name} t1
                INNER JOIN (
                    SELECT post_id, MAX(data_date) as max_date
                    FROM {$this->table_name}
                    GROUP BY post_id
                ) t2 ON t1.post_id = t2.post_id AND t1.data_date = t2.max_date
                WHERE {$where}";
        
        if (!empty($values)) {
            return intval($wpdb->get_var($wpdb->prepare($sql, $values)));
        }
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * トレンド判定
     * 
     * @param float $current 現在値
     * @param float $previous 前回値
     * @param string $direction 良い方向（higher/lower）
     * @return string トレンド（up/down/stable）
     */
    private function get_trend($current, $previous, $direction) {
        if ($previous === null) {
            return 'stable';
        }
        
        $diff = floatval($current) - floatval($previous);
        $threshold = 0.01; // 微小変化は安定とみなす
        
        if (abs($diff) < $threshold) {
            return 'stable';
        }
        
        if ($direction === 'higher') {
            return $diff > 0 ? 'up' : 'down';
        } else {
            return $diff < 0 ? 'up' : 'down';
        }
    }
    
    /**
     * 空のサマリーデータを返す
     * 
     * @return array 空のサマリー
     */
    private function get_empty_summary() {
        return array(
            'current_date' => null,
            'total_articles' => 0,
            'metrics' => array(
                'avg_time_on_page' => array('current' => 0, 'previous' => null, 'change' => null, 'trend' => 'stable'),
                'bounce_rate'      => array('current' => 0, 'previous' => null, 'change' => null, 'trend' => 'stable'),
                'ctr'              => array('current' => 0, 'previous' => null, 'change' => null, 'trend' => 'stable'),
                'avg_position'     => array('current' => 0, 'previous' => null, 'change' => null, 'trend' => 'stable'),
                'performance_score'=> array('current' => 0, 'previous' => null, 'change' => null, 'trend' => 'stable')
            )
        );
    }
    
    /**
     * 閾値を取得
     * 
     * @return array 閾値設定
     */
    public function get_thresholds() {
        return $this->thresholds;
    }
    
    /**
     * テーブル名を取得
     * 
     * @return string テーブル名
     */
    public function get_table_name() {
        return $this->table_name;
    }
}