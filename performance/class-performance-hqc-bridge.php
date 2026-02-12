<?php
/**
 * HRS Performance HQC Bridge
 * パフォーマンスデータとHQCシステムの連携
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Performance_HQC_Bridge {
    
    /** @var HRS_Performance_Tracker */
    private $tracker;
    
    /** @var string フラグ保存用メタキー */
    private $flag_meta_key = '_hrs_performance_flag';
    
    /** @var string 優先度保存用メタキー */
    private $priority_meta_key = '_hrs_improvement_priority';
    
    /** @var int リライト候補送りの最低公開日数 */
    private $min_days_published = 30;
    
    /** @var array 優先度ラベル */
    private $priority_labels = array(
        'high'   => '高（すぐ改善）',
        'medium' => '中（計画的に）',
        'low'    => '低（余裕があれば）'
    );
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->tracker = new HRS_Performance_Tracker();
    }
    
    /**
     * 全記事をチェックし自動フラグ付与
     * 
     * @return array 処理結果
     */
    public function check_and_flag() {
        $results = array(
            'excellent' => 0,
            'normal'    => 0,
            'poor'      => 0,
            'skipped'   => 0,
            'total'     => 0
        );
        
        // パフォーマンスデータのある全記事を取得
        $data_list = $this->tracker->get_all_data(array(
            'latest' => true,
            'limit'  => 9999
        ));
        
        foreach ($data_list as $data) {
            $post_id = $data->post_id;
            $score = floatval($data->performance_score);
            
            // 記事が存在するか確認
            if (!get_post($post_id)) {
                $results['skipped']++;
                continue;
            }
            
            // フラグ判定
            $thresholds = $this->tracker->get_thresholds();
            
            if ($score >= $thresholds['excellent']) {
                $flag = 'excellent';
                $results['excellent']++;
            } elseif ($score >= $thresholds['normal']) {
                $flag = 'normal';
                $results['normal']++;
            } else {
                $flag = 'poor';
                $results['poor']++;
            }
            
            // フラグを保存
            update_post_meta($post_id, $this->flag_meta_key, $flag);
            
            // 優先度も計算して保存
            $priority = $this->calculate_improvement_priority($post_id, $score, $data->impressions);
            update_post_meta($post_id, $this->priority_meta_key, $priority);
            
            $results['total']++;
        }
        
        return $results;
    }
    
    /**
     * 特定記事のフラグ状態を取得
     * 
     * @param int $post_id 記事ID
     * @return array フラグ情報
     */
    public function get_flag_status($post_id) {
        $flag = get_post_meta($post_id, $this->flag_meta_key, true);
        $priority = get_post_meta($post_id, $this->priority_meta_key, true);
        
        if (empty($flag)) {
            // フラグがない場合はTrackerから取得して設定
            $flag = $this->tracker->get_flag_for_post($post_id);
            if ($flag !== 'none') {
                update_post_meta($post_id, $this->flag_meta_key, $flag);
            }
        }
        
        return array(
            'flag'           => $flag ?: 'none',
            'flag_label'     => $this->get_flag_label($flag),
            'flag_color'     => $this->get_flag_color($flag),
            'priority'       => $priority ?: '',
            'priority_label' => $this->priority_labels[$priority] ?? ''
        );
    }
    
    /**
     * 低パフォーマンス記事をリライトプランナーに送る
     * 
     * @param int $post_id 記事ID
     * @param string $reason 理由（省略時は自動生成）
     * @return bool 成功/失敗
     */
    public function send_to_rewrite_planner($post_id, $reason = '') {
        // 記事存在確認
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // パフォーマンスデータ取得
        $perf_data = $this->tracker->get_data_by_post($post_id);
        if (!$perf_data) {
            return false;
        }
        
        // 公開日チェック
        $published_date = strtotime($post->post_date);
        $days_since_publish = (time() - $published_date) / DAY_IN_SECONDS;
        
        if ($days_since_publish < $this->min_days_published) {
            return false; // まだ公開から日が浅い
        }
        
        // リライトプランナーが存在するか確認
        if (!class_exists('HRS_Rewrite_Planner')) {
            // リライトプランナーがない場合はメタデータとして保存
            return $this->save_rewrite_candidate($post_id, $perf_data, $reason);
        }
        
        // リライトプランナーに送信
        $planner = new HRS_Rewrite_Planner();
        
        $priority = get_post_meta($post_id, $this->priority_meta_key, true) ?: 'medium';
        
        if (empty($reason)) {
            $reason = $this->generate_rewrite_reason($perf_data);
        }
        
        $rewrite_data = array(
            'post_id'  => $post_id,
            'reason'   => $reason,
            'priority' => $priority,
            'source'   => 'performance',
            'metrics'  => array(
                'avg_time_on_page' => $perf_data->avg_time_on_page,
                'bounce_rate'      => $perf_data->bounce_rate,
                'ctr'              => $perf_data->ctr,
                'avg_position'     => $perf_data->avg_position,
                'score'            => $perf_data->performance_score
            )
        );
        
        return $planner->add_candidate($rewrite_data);
    }
    
    /**
     * 閾値以下の全記事を一括でリライト候補に
     * 
     * @return array 処理結果
     */
    public function bulk_send_low_performers() {
        $results = array(
            'sent'    => 0,
            'skipped' => 0,
            'failed'  => 0,
            'total'   => 0
        );
        
        // 低パフォーマンス記事を取得
        $low_performers = $this->tracker->get_low_performers();
        
        foreach ($low_performers as $data) {
            $post_id = $data->post_id;
            $results['total']++;
            
            // 既にリライト候補に入っているかチェック
            if ($this->is_already_candidate($post_id)) {
                $results['skipped']++;
                continue;
            }
            
            $sent = $this->send_to_rewrite_planner($post_id);
            
            if ($sent) {
                $results['sent']++;
            } else {
                // 公開日が浅い等でスキップされた場合
                $post = get_post($post_id);
                if ($post) {
                    $published_date = strtotime($post->post_date);
                    $days = (time() - $published_date) / DAY_IN_SECONDS;
                    if ($days < $this->min_days_published) {
                        $results['skipped']++;
                        continue;
                    }
                }
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * HQCアナライザー用にデータ整形して返す
     * 
     * @param int $post_id 記事ID
     * @return array|null HQC用データ
     */
    public function get_performance_for_hqc($post_id) {
        $perf_data = $this->tracker->get_data_by_post($post_id);
        
        if (!$perf_data) {
            return null;
        }
        
        $flag_status = $this->get_flag_status($post_id);
        
        return array(
            'has_data'         => true,
            'score'            => floatval($perf_data->performance_score),
            'flag'             => $flag_status['flag'],
            'flag_label'       => $flag_status['flag_label'],
            'priority'         => $flag_status['priority'],
            'metrics'          => array(
                'avg_time_on_page' => array(
                    'value' => floatval($perf_data->avg_time_on_page),
                    'unit'  => '秒',
                    'label' => '滞在時間'
                ),
                'bounce_rate' => array(
                    'value' => floatval($perf_data->bounce_rate),
                    'unit'  => '%',
                    'label' => '直帰率'
                ),
                'ctr' => array(
                    'value' => floatval($perf_data->ctr),
                    'unit'  => '%',
                    'label' => 'CTR'
                ),
                'avg_position' => array(
                    'value' => floatval($perf_data->avg_position),
                    'unit'  => '位',
                    'label' => '平均順位'
                )
            ),
            'data_date'        => $perf_data->data_date,
            'improvement_suggestions' => $this->generate_improvement_suggestions($perf_data)
        );
    }
    
    /**
     * 改善優先度を算出
     * 
     * @param int $post_id 記事ID
     * @param float $score スコア
     * @param int $impressions 表示回数
     * @return string 優先度（high/medium/low）
     */
    public function calculate_improvement_priority($post_id, $score = null, $impressions = null) {
        // データがない場合は取得
        if ($score === null || $impressions === null) {
            $data = $this->tracker->get_data_by_post($post_id);
            if (!$data) {
                return 'low';
            }
            $score = floatval($data->performance_score);
            $impressions = intval($data->impressions);
        }
        
        // 優先度スコア = (100 - パフォーマンススコア) × log(表示回数 + 1)
        $priority_score = (100 - $score) * log($impressions + 1);
        
        // 全記事の優先度スコアを取得して相対評価
        $all_scores = $this->get_all_priority_scores();
        
        if (empty($all_scores)) {
            // 単独評価
            if ($priority_score > 200) {
                return 'high';
            } elseif ($priority_score > 100) {
                return 'medium';
            } else {
                return 'low';
            }
        }
        
        // パーセンタイル計算
        $percentile = $this->calculate_percentile($priority_score, $all_scores);
        
        if ($percentile >= 80) {
            return 'high';  // 上位20%
        } elseif ($percentile >= 30) {
            return 'medium'; // 中間50%
        } else {
            return 'low';   // 下位30%
        }
    }
    
    /**
     * 特定記事のフラグを更新
     * 
     * @param int $post_id 記事ID
     * @return bool 成功/失敗
     */
    public function update_flag($post_id) {
        $data = $this->tracker->get_data_by_post($post_id);
        
        if (!$data) {
            delete_post_meta($post_id, $this->flag_meta_key);
            delete_post_meta($post_id, $this->priority_meta_key);
            return false;
        }
        
        $flag = $this->tracker->get_flag_for_post($post_id);
        update_post_meta($post_id, $this->flag_meta_key, $flag);
        
        $priority = $this->calculate_improvement_priority($post_id, $data->performance_score, $data->impressions);
        update_post_meta($post_id, $this->priority_meta_key, $priority);
        
        return true;
    }
    
    /**
     * フラグごとの記事数を取得
     * 
     * @return array フラグ別カウント
     */
    public function get_flag_counts() {
        return array(
            'excellent' => $this->tracker->get_count('excellent'),
            'normal'    => $this->tracker->get_count('normal'),
            'poor'      => $this->tracker->get_count('poor'),
            'total'     => $this->tracker->get_count()
        );
    }
    
    /**
     * フラグラベルを取得
     * 
     * @param string $flag フラグ
     * @return string ラベル
     */
    private function get_flag_label($flag) {
        $labels = array(
            'excellent' => '優良',
            'normal'    => '普通',
            'poor'      => '要改善',
            'none'      => '未計測'
        );
        return $labels[$flag] ?? '不明';
    }
    
    /**
     * フラグ色を取得
     * 
     * @param string $flag フラグ
     * @return string カラーコード
     */
    private function get_flag_color($flag) {
        $colors = array(
            'excellent' => '#28a745', // 緑
            'normal'    => '#ffc107', // 黄
            'poor'      => '#dc3545', // 赤
            'none'      => '#6c757d'  // グレー
        );
        return $colors[$flag] ?? '#6c757d';
    }
    
    /**
     * リライト理由を生成
     * 
     * @param object $perf_data パフォーマンスデータ
     * @return string 理由文
     */
    private function generate_rewrite_reason($perf_data) {
        $reasons = array();
        $thresholds = $this->tracker->get_thresholds();
        
        if ($perf_data->avg_time_on_page < 60) {
            $reasons[] = '滞在時間が短い（' . round($perf_data->avg_time_on_page) . '秒）';
        }
        
        if ($perf_data->bounce_rate > 70) {
            $reasons[] = '直帰率が高い（' . round($perf_data->bounce_rate, 1) . '%）';
        }
        
        if ($perf_data->ctr < 2) {
            $reasons[] = 'CTRが低い（' . round($perf_data->ctr, 2) . '%）';
        }
        
        if ($perf_data->avg_position > 20) {
            $reasons[] = '検索順位が低い（' . round($perf_data->avg_position, 1) . '位）';
        }
        
        if (empty($reasons)) {
            $reasons[] = '総合スコアが低下（' . round($perf_data->performance_score, 1) . '点）';
        }
        
        return 'パフォーマンス低下: ' . implode('、', $reasons);
    }
    
    /**
     * 改善提案を生成
     * 
     * @param object $perf_data パフォーマンスデータ
     * @return array 改善提案
     */
    private function generate_improvement_suggestions($perf_data) {
        $suggestions = array();
        
        if ($perf_data->avg_time_on_page < 60) {
            $suggestions[] = array(
                'metric' => '滞在時間',
                'issue'  => '滞在時間が短い',
                'action' => 'コンテンツの充実、読みやすさの向上、内部リンクの追加'
            );
        }
        
        if ($perf_data->bounce_rate > 70) {
            $suggestions[] = array(
                'metric' => '直帰率',
                'issue'  => '直帰率が高い',
                'action' => 'ファーストビューの改善、CTAの見直し、関連記事の表示'
            );
        }
        
        if ($perf_data->ctr < 2) {
            $suggestions[] = array(
                'metric' => 'CTR',
                'issue'  => 'クリック率が低い',
                'action' => 'タイトルの改善、メタディスクリプションの最適化'
            );
        }
        
        if ($perf_data->avg_position > 20) {
            $suggestions[] = array(
                'metric' => '検索順位',
                'issue'  => '検索順位が低い',
                'action' => 'コンテンツの更新、キーワード最適化、被リンク獲得'
            );
        }
        
        return $suggestions;
    }
    
    /**
     * 既にリライト候補に入っているかチェック
     * 
     * @param int $post_id 記事ID
     * @return bool 候補に入っている場合true
     */
    private function is_already_candidate($post_id) {
        // リライトプランナーが存在する場合
        if (class_exists('HRS_Rewrite_Planner')) {
            $planner = new HRS_Rewrite_Planner();
            return $planner->is_candidate($post_id);
        }
        
        // メタデータでチェック
        $candidate_flag = get_post_meta($post_id, '_hrs_rewrite_candidate', true);
        return !empty($candidate_flag);
    }
    
    /**
     * リライト候補としてメタデータに保存（プランナーがない場合のフォールバック）
     * 
     * @param int $post_id 記事ID
     * @param object $perf_data パフォーマンスデータ
     * @param string $reason 理由
     * @return bool 成功/失敗
     */
    private function save_rewrite_candidate($post_id, $perf_data, $reason) {
        if (empty($reason)) {
            $reason = $this->generate_rewrite_reason($perf_data);
        }
        
        $priority = get_post_meta($post_id, $this->priority_meta_key, true) ?: 'medium';
        
        $candidate_data = array(
            'added_at' => current_time('mysql'),
            'reason'   => $reason,
            'priority' => $priority,
            'source'   => 'performance',
            'score'    => $perf_data->performance_score
        );
        
        update_post_meta($post_id, '_hrs_rewrite_candidate', $candidate_data);
        
        return true;
    }
    
    /**
     * 全記事の優先度スコアを取得
     * 
     * @return array スコア配列
     */
    private function get_all_priority_scores() {
        $data_list = $this->tracker->get_all_data(array(
            'latest' => true,
            'limit'  => 9999
        ));
        
        $scores = array();
        foreach ($data_list as $data) {
            $score = (100 - floatval($data->performance_score)) * log(intval($data->impressions) + 1);
            $scores[] = $score;
        }
        
        sort($scores);
        return $scores;
    }
    
    /**
     * パーセンタイルを計算
     * 
     * @param float $value 対象値
     * @param array $sorted_array ソート済み配列
     * @return float パーセンタイル（0-100）
     */
    private function calculate_percentile($value, $sorted_array) {
        $count = count($sorted_array);
        if ($count === 0) {
            return 50;
        }
        
        $below = 0;
        foreach ($sorted_array as $v) {
            if ($v < $value) {
                $below++;
            }
        }
        
        return ($below / $count) * 100;
    }
    
    /**
     * メタキーを取得（外部参照用）
     * 
     * @return array メタキー
     */
    public function get_meta_keys() {
        return array(
            'flag'     => $this->flag_meta_key,
            'priority' => $this->priority_meta_key
        );
    }

/**
     * ★ パフォーマンスデータを学習システムに同期
     * 
     * @return array 処理結果
     */
    public function sync_to_learning_system() {
        $results = array(
            'success_patterns' => 0,
            'weakness_recorded' => 0,
            'skipped' => 0,
            'total' => 0
        );
        
        // 学習モジュールが存在しない場合はスキップ
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return $results;
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        
        // パフォーマンスデータを取得
        $data_list = $this->tracker->get_all_data(array(
            'latest' => true,
            'limit' => 500
        ));
        
        foreach ($data_list as $data) {
            $results['total']++;
            
            $post_id = $data->post_id;
            $post = get_post($post_id);
            
            if (!$post) {
                $results['skipped']++;
                continue;
            }
            
            // ホテル名を取得
            $hotel_name = $this->extract_hotel_name($post);
            if (empty($hotel_name)) {
                $results['skipped']++;
                continue;
            }
            
            $score = floatval($data->performance_score);
            
            // 高パフォーマンス（70以上）→ 成功パターン抽出
            if ($score >= 70) {
                $this->extract_seo_success_pattern($data, $post);
                $results['success_patterns']++;
            }
            
            // 低パフォーマンス（50未満）→ 弱点として記録
            if ($score < 50) {
                $this->record_seo_weakness($data, $hotel_name, $learning);
                $results['weakness_recorded']++;
            }
        }
        
        return $results;
    }
    
    /**
     * ★ SEO成功パターンを抽出
     * 
     * @param object $perf_data パフォーマンスデータ
     * @param WP_Post $post 記事
     */
    private function extract_seo_success_pattern($perf_data, $post) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hrs_success_patterns';
        
        // 高CTR（3%以上）のパターン
        if ($perf_data->ctr >= 3) {
            $this->update_seo_pattern($table, 'seo_boost', 'high_ctr', 
                'CTR3%以上を達成。タイトルに具体的な数字や感情を揺さぶる表現を含める',
                $perf_data->performance_score
            );
        }
        
        // 低直帰率（50%未満）のパターン
        if ($perf_data->bounce_rate < 50) {
            $this->update_seo_pattern($table, 'seo_boost', 'low_bounce',
                '直帰率50%未満を達成。冒頭で読者の興味を引き、内部リンクを適切に配置',
                $perf_data->performance_score
            );
        }
        
        // 長滞在時間（120秒以上）のパターン
        if ($perf_data->avg_time_on_page >= 120) {
            $this->update_seo_pattern($table, 'seo_boost', 'long_session',
                '滞在時間2分以上を達成。読み応えのあるコンテンツと適切な文章量',
                $perf_data->performance_score
            );
        }
        
        // 高順位（10位以内）のパターン
        if ($perf_data->avg_position <= 10) {
            $this->update_seo_pattern($table, 'seo_boost', 'top_ranking',
                '検索10位以内を達成。キーフレーズの適切な配置とH2構成の最適化',
                $perf_data->performance_score
            );
        }
    }
    
    /**
     * ★ SEOパターンを更新または追加
     * 
     * @param string $table テーブル名
     * @param string $type パターンタイプ
     * @param string $key パターンキー
     * @param string $value パターン説明
     * @param float $score スコア
     */
    private function update_seo_pattern($table, $type, $key, $value, $score) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE pattern_type = %s AND pattern_key = %s",
            $type, $key
        ), ARRAY_A);
        
        if ($existing) {
            // 既存パターンを更新
            $new_count = (int)$existing['usage_count'] + 1;
            $old_avg = (float)$existing['avg_score_impact'];
            $old_count = (int)$existing['usage_count'];
            $new_avg = $old_count > 0 ? (($old_avg * $old_count) + $score) / $new_count : $score;
            
            $wpdb->update(
                $table,
                array(
                    'usage_count' => $new_count,
                    'avg_score_impact' => round($new_avg, 2),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing['id'])
            );
        } else {
            // 新規追加
            $wpdb->insert($table, array(
                'pattern_type' => $type,
                'pattern_key' => $key,
                'pattern_value' => $value,
                'usage_count' => 1,
                'avg_score_impact' => round($score, 2),
                'success_rate' => 85.00,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ));
        }
    }
    
    /**
     * ★ SEO弱点を学習データに記録
     * 
     * @param object $perf_data パフォーマンスデータ
     * @param string $hotel_name ホテル名
     * @param HRS_HQC_Learning_Module $learning 学習モジュール
     */
    private function record_seo_weakness($perf_data, $hotel_name, $learning) {
        // ホテル学習データを取得
        $hotel_learning = $learning->get_hotel_learning($hotel_name);
        
        if (!$hotel_learning) {
            return;
        }
        
        // 既存の弱点データを取得
        $chronic_weak = $hotel_learning['chronic_weak_points'] ?? array();
        
        // SEO弱点を追加
        $seo_weaknesses = array();
        
        if ($perf_data->ctr < 1.5) {
            $seo_weaknesses['SEO_low_ctr'] = array(
                'axis' => 'SEO',
                'category' => 'low_ctr',
                'count' => 1
            );
        }
        
        if ($perf_data->bounce_rate > 80) {
            $seo_weaknesses['SEO_high_bounce'] = array(
                'axis' => 'SEO',
                'category' => 'high_bounce',
                'count' => 1
            );
        }
        
        if ($perf_data->avg_time_on_page < 30) {
            $seo_weaknesses['SEO_short_session'] = array(
                'axis' => 'SEO',
                'category' => 'short_session',
                'count' => 1
            );
        }
        
        if ($perf_data->avg_position > 50) {
            $seo_weaknesses['SEO_low_ranking'] = array(
                'axis' => 'SEO',
                'category' => 'low_ranking',
                'count' => 1
            );
        }
        
        // 弱点をマージ
        foreach ($seo_weaknesses as $key => $weak) {
            if (isset($chronic_weak[$key])) {
                $chronic_weak[$key]['count']++;
            } else {
                $chronic_weak[$key] = $weak;
            }
        }
        
        // 学習データを更新
        global $wpdb;
        $table = $wpdb->prefix . 'hrs_hotel_learning';
        
        $wpdb->update(
            $table,
            array(
                'chronic_weak_points' => json_encode($chronic_weak, JSON_UNESCAPED_UNICODE),
                'updated_at' => current_time('mysql'),
            ),
            array('hotel_name' => $hotel_name)
        );
    }
    
    /**
     * ★ 記事からホテル名を抽出
     * 
     * @param WP_Post $post 記事
     * @return string ホテル名
     */
    private function extract_hotel_name($post) {
        // カスタムフィールドから取得
        $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
        
        if (!empty($hotel_name)) {
            return $hotel_name;
        }
        
        // タイトルから抽出（「ホテル名」レビュー等のパターン）
        $title = $post->post_title;
        
        // 「〇〇 レビュー」「〇〇 口コミ」パターン
        if (preg_match('/^(.+?)[\s　]*(レビュー|口コミ|宿泊記|体験記)/u', $title, $matches)) {
            return trim($matches[1]);
        }
        
        // 「【〇〇】」パターン
        if (preg_match('/【(.+?)】/u', $title, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
}