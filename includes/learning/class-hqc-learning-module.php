<?php
/**
 * HQC学習モジュール
 * 
 * 記事生成履歴の保存、成功パターンの抽出、
 * ホテル別学習データの管理を行う
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.0.1
 * @modified 2025-01-06 - created_at → first_generated_at 修正
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Learning_Module {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * テーブル名
     */
    private $table_history;
    private $table_learning;
    private $table_patterns;

    /**
     * 高スコア閾値
     */
    private $high_score_threshold = 75;

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        
        $this->table_history = $wpdb->prefix . 'hrs_hqc_history';
        $this->table_learning = $wpdb->prefix . 'hrs_hotel_learning';
        $this->table_patterns = $wpdb->prefix . 'hrs_success_patterns';
    }

    /**
     * 生成履歴を保存
     * 
     * @param array $data 履歴データ
     * @return int|false 挿入ID or false
     */
    public function save_history($data) {
        global $wpdb;

        $insert_data = array(
            'post_id' => $data['post_id'] ?? null,
            'hotel_name' => $data['hotel_name'] ?? '',
            'location' => $data['location'] ?? '',
            'h_score' => $data['h_score'] ?? 0,
            'q_score' => $data['q_score'] ?? 0,
            'c_score' => $data['c_score'] ?? 0,
            'total_score' => $data['total_score'] ?? 0,
            'h_details' => json_encode($data['h_details'] ?? array(), JSON_UNESCAPED_UNICODE),
            'q_details' => json_encode($data['q_details'] ?? array(), JSON_UNESCAPED_UNICODE),
            'c_details' => json_encode($data['c_details'] ?? array(), JSON_UNESCAPED_UNICODE),
            'weak_points' => json_encode($data['weak_points'] ?? array(), JSON_UNESCAPED_UNICODE),
            'prompt_used' => $data['prompt_used'] ?? '',
            'model_used' => $data['model_used'] ?? 'gpt-4',
            'generation_params' => json_encode($data['generation_params'] ?? array(), JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($this->table_history, $insert_data);

        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HRS HQC] History save failed: ' . $wpdb->last_error);
            }
            return false;
        }

        $history_id = $wpdb->insert_id;

        // ホテル別学習データを更新
        $this->update_hotel_learning($data);

        // 高スコアの場合、成功パターンを抽出
        if (($data['total_score'] ?? 0) >= $this->high_score_threshold) {
            $this->extract_success_pattern($data);
        }

        return $history_id;
    }

    /**
     * ホテル別学習データを更新
     * 
     * @modified 2025-01-06 - INSERT時 created_at → first_generated_at
     */
    private function update_hotel_learning($data) {
        global $wpdb;

        $hotel_name = $data['hotel_name'] ?? '';
        if (empty($hotel_name)) {
            return;
        }

        // 既存データを取得
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_learning} WHERE hotel_name = %s",
            $hotel_name
        ), ARRAY_A);

        $total_score = $data['total_score'] ?? 0;

        if ($existing) {
            // 更新
            $new_count = $existing['generation_count'] + 1;
            $new_avg = (($existing['avg_score'] * $existing['generation_count']) + $total_score) / $new_count;
            $new_best = max($existing['best_score'], $total_score);

            // 慢性的弱点を更新
            $chronic_weak = json_decode($existing['chronic_weak_points'], true) ?: array();
            $new_weak = $data['weak_points'] ?? array();
            foreach ($new_weak as $wp) {
                $key = $wp['axis'] . '_' . $wp['category'];
                if (!isset($chronic_weak[$key])) {
                    $chronic_weak[$key] = array('count' => 0, 'axis' => $wp['axis'], 'category' => $wp['category']);
                }
                $chronic_weak[$key]['count']++;
            }

            $wpdb->update(
                $this->table_learning,
                array(
                    'generation_count' => $new_count,
                    'avg_score' => $new_avg,
                    'best_score' => $new_best,
                    'last_score' => $total_score,
                    'chronic_weak_points' => json_encode($chronic_weak, JSON_UNESCAPED_UNICODE),
                    'last_generated_at' => current_time('mysql'),
                ),
                array('hotel_name' => $hotel_name)
            );
        } else {
            // 新規作成 - created_at → first_generated_at に修正
            $chronic_weak = array();
            foreach (($data['weak_points'] ?? array()) as $wp) {
                $key = $wp['axis'] . '_' . $wp['category'];
                $chronic_weak[$key] = array('count' => 1, 'axis' => $wp['axis'], 'category' => $wp['category']);
            }

            $wpdb->insert($this->table_learning, array(
                'hotel_name' => $hotel_name,
                'location' => $data['location'] ?? '',
                'generation_count' => 1,
                'avg_score' => $total_score,
                'best_score' => $total_score,
                'last_score' => $total_score,
                'chronic_weak_points' => json_encode($chronic_weak, JSON_UNESCAPED_UNICODE),
                'best_params' => json_encode(array(), JSON_UNESCAPED_UNICODE),
                'first_generated_at' => current_time('mysql'),
                'last_generated_at' => current_time('mysql'),
            ));
        }
    }

    /**
     * 成功パターンを抽出（修正版）
     * 
     * 高スコア記事からH/Q/C各軸の成功項目を抽出し、
     * 既存パターンの usage_count / success_rate を更新
     */
    private function extract_success_pattern($data) {
        global $wpdb;
        
        $total_score = $data['total_score'] ?? 0;
        
        // H軸の詳細から高評価項目を抽出
        $h_details = $data['h_details'] ?? array();
        if (is_string($h_details)) {
            $h_details = json_decode($h_details, true) ?: array();
        }
        foreach ($h_details as $key => $detail) {
            if ($this->is_high_score($detail)) {
                $this->increment_pattern_success('h_boost', $key, $total_score);
            }
        }
        
        // Q軸の詳細から高評価項目を抽出
        $q_details = $data['q_details'] ?? array();
        if (is_string($q_details)) {
            $q_details = json_decode($q_details, true) ?: array();
        }
        foreach ($q_details as $key => $detail) {
            if ($this->is_high_score($detail)) {
                $this->increment_pattern_success('q_boost', $key, $total_score);
            }
        }
        
        // C軸の詳細から高評価項目を抽出
        $c_details = $data['c_details'] ?? array();
        if (is_string($c_details)) {
            $c_details = json_decode($c_details, true) ?: array();
        }
        foreach ($c_details as $key => $detail) {
            if ($this->is_high_score($detail)) {
                $this->increment_pattern_success('c_boost', $key, $total_score);
            }
        }
        
        // スタイル×ペルソナ×トーンの組み合わせを記録
        $combo_key = sprintf(
            '%s_%s_%s',
            $data['style'] ?? 'timeline',
            $data['persona'] ?? 'solo',
            $data['tone'] ?? 'casual'
        );
        $this->increment_pattern_success('combo', $combo_key, $total_score);
    }

    /**
     * 高スコア判定（maxの80%以上）
     */
    private function is_high_score($detail) {
        if (!is_array($detail)) {
            return false;
        }
        
        $max = $detail['max'] ?? 0;
        $score = $detail['score'] ?? 0;
        
        if ($max <= 0) {
            return false;
        }
        
        return ($score / $max) >= 0.8;
    }

    /**
     * パターン成功率を更新
     */
    private function increment_pattern_success($type, $key, $score) {
        global $wpdb;
        
        // 既存パターンを検索
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_patterns} WHERE pattern_type = %s AND pattern_key = %s",
            $type,
            $key
        ), ARRAY_A);
        
        if ($existing) {
            // 既存パターンを更新
            $new_count = (int)$existing['usage_count'] + 1;
            $old_avg = (float)$existing['avg_score_impact'];
            $old_count = (int)$existing['usage_count'];
            
            $new_avg = $old_count > 0 
                ? (($old_avg * $old_count) + $score) / $new_count 
                : $score;
            
            $wpdb->update(
                $this->table_patterns,
                array(
                    'usage_count' => $new_count,
                    'avg_score_impact' => round($new_avg, 2),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing['id'])
            );
        } elseif ($type === 'combo') {
            // comboのみ新規追加を許可
            $wpdb->insert(
                $this->table_patterns,
                array(
                    'pattern_type' => 'combo',
                    'pattern_key' => $key,
                    'pattern_value' => "組み合わせ: {$key}",
                    'usage_count' => 1,
                    'avg_score_impact' => round($score, 2),
                    'success_rate' => 85.00,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                )
            );
        }
    }

    /**
     * ホテル別学習データを取得
     */
    public function get_hotel_learning($hotel_name) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_learning} WHERE hotel_name = %s",
            $hotel_name
        ), ARRAY_A);

        if ($result) {
            $result['chronic_weak_points'] = json_decode($result['chronic_weak_points'], true) ?: array();
            $result['best_params'] = json_decode($result['best_params'], true) ?: array();
        }

        return $result;
    }

    /**
     * ホテルの履歴を取得
     */
    public function get_history_by_hotel($hotel_name, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_history} WHERE hotel_name = %s ORDER BY created_at DESC LIMIT %d",
            $hotel_name,
            $limit
        ), ARRAY_A);
    }

    /**
     * 最近の履歴を取得
     */
    public function get_recent_history($limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_history} ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * 統計情報を取得
     */
    public function get_statistics() {
        global $wpdb;

        // 履歴統計
        $history_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_count,
                AVG(total_score) as avg_score,
                MAX(total_score) as max_score,
                MIN(total_score) as min_score,
                AVG(h_score) as avg_h,
                AVG(q_score) as avg_q,
                AVG(c_score) as avg_c,
                SUM(CASE WHEN total_score >= 75 THEN 1 ELSE 0 END) as high_quality_count
            FROM {$this->table_history}",
            ARRAY_A
        );

        // ホテル数
        $hotel_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_learning}");

        // 成功パターン数
        $pattern_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_patterns}");

        return array(
            'history' => array(
                'total_count' => (int)($history_stats['total_count'] ?? 0),
                'avg_score' => round((float)($history_stats['avg_score'] ?? 0), 1),
                'max_score' => round((float)($history_stats['max_score'] ?? 0), 1),
                'min_score' => round((float)($history_stats['min_score'] ?? 0), 1),
                'avg_h' => round((float)($history_stats['avg_h'] ?? 0), 1),
                'avg_q' => round((float)($history_stats['avg_q'] ?? 0), 1),
                'avg_c' => round((float)($history_stats['avg_c'] ?? 0), 1),
                'high_quality_count' => (int)($history_stats['high_quality_count'] ?? 0),
            ),
            'hotels' => array(
                'count' => (int)$hotel_count,
            ),
            'patterns' => array(
                'count' => (int)$pattern_count,
            ),
        );
    }

    /**
     * 弱点の慢性度を取得
     */
    public function get_chronic_weak_points($min_count = 3) {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT chronic_weak_points FROM {$this->table_learning}",
            ARRAY_A
        );

        $aggregated = array();
        foreach ($results as $row) {
            $weak_points = json_decode($row['chronic_weak_points'], true) ?: array();
            foreach ($weak_points as $key => $wp) {
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = array(
                        'axis' => $wp['axis'],
                        'category' => $wp['category'],
                        'total_count' => 0,
                        'hotel_count' => 0,
                    );
                }
                $aggregated[$key]['total_count'] += $wp['count'];
                $aggregated[$key]['hotel_count']++;
            }
        }

        // フィルタリング
        $filtered = array_filter($aggregated, function($wp) use ($min_count) {
            return $wp['hotel_count'] >= $min_count;
        });

        // ソート
        uasort($filtered, function($a, $b) {
            return $b['hotel_count'] - $a['hotel_count'];
        });

        return $filtered;
    }

    /**
     * スコア推移を取得（グラフ用）
     */
    public function get_score_trend($days = 30) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                AVG(total_score) as avg_total,
                AVG(h_score) as avg_h,
                AVG(q_score) as avg_q,
                AVG(c_score) as avg_c,
                COUNT(*) as count
            FROM {$this->table_history}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $days
        ), ARRAY_A);

        return $results;
    }
}