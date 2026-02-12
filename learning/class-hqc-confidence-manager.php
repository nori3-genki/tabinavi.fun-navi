<?php
/**
 * HQC信頼度マネージャー
 * 
 * HQCスコアの信頼性と閾値を管理する
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Confidence_Manager {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * 信頼度設定
     */
    private $config = array(
        'min_samples' => 5,
        'high_confidence_samples' => 20,
        'score_variance_threshold' => 15,
        'trend_window' => 10,
    );

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
    private function __construct() {
        // 設定をオプションから読み込み
        $saved_config = get_option('hrs_hqc_confidence_config', array());
        $this->config = array_merge($this->config, $saved_config);
    }

    /**
     * 信頼度を計算
     * 
     * @param array $scores スコアの配列
     * @return array 信頼度情報
     */
    public function calculate_confidence($scores) {
        if (empty($scores)) {
            return array(
                'level' => 'none',
                'percentage' => 0,
                'reason' => 'データなし',
            );
        }

        $count = count($scores);
        $avg = array_sum($scores) / $count;
        
        // 分散計算
        $variance = 0;
        foreach ($scores as $score) {
            $variance += pow($score - $avg, 2);
        }
        $variance = $variance / $count;
        $std_dev = sqrt($variance);

        // 信頼度レベル判定
        if ($count < $this->config['min_samples']) {
            $level = 'low';
            $percentage = min(50, ($count / $this->config['min_samples']) * 50);
            $reason = "サンプル数不足（{$count}/{$this->config['min_samples']}）";
        } elseif ($count >= $this->config['high_confidence_samples'] && $std_dev < $this->config['score_variance_threshold']) {
            $level = 'high';
            $percentage = min(100, 80 + ($count - $this->config['high_confidence_samples']) / 2);
            $reason = "十分なサンプル数と安定したスコア";
        } elseif ($std_dev < $this->config['score_variance_threshold']) {
            $level = 'medium';
            $percentage = 50 + min(30, ($count / $this->config['high_confidence_samples']) * 30);
            $reason = "安定したスコア、サンプル数増加で向上";
        } else {
            $level = 'unstable';
            $percentage = max(30, 60 - $std_dev);
            $reason = "スコアのばらつきが大きい（標準偏差: " . round($std_dev, 1) . "）";
        }

        return array(
            'level' => $level,
            'percentage' => round($percentage, 1),
            'reason' => $reason,
            'sample_count' => $count,
            'average' => round($avg, 1),
            'std_dev' => round($std_dev, 1),
        );
    }

    /**
     * トレンドを分析
     * 
     * @param array $scores 時系列順のスコア配列
     * @return array トレンド情報
     */
    public function analyze_trend($scores) {
        if (count($scores) < 3) {
            return array(
                'direction' => 'unknown',
                'strength' => 0,
                'message' => 'データ不足',
            );
        }

        // 直近のウィンドウを取得
        $window = array_slice($scores, -$this->config['trend_window']);
        $n = count($window);
        
        // 線形回帰で傾きを計算
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_x += $i;
            $sum_y += $window[$i];
            $sum_xy += $i * $window[$i];
            $sum_x2 += $i * $i;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        
        // トレンド判定
        if ($slope > 1) {
            $direction = 'improving';
            $message = '品質向上中';
        } elseif ($slope < -1) {
            $direction = 'declining';
            $message = '品質低下傾向';
        } else {
            $direction = 'stable';
            $message = '安定';
        }

        return array(
            'direction' => $direction,
            'strength' => round(abs($slope), 2),
            'message' => $message,
            'slope' => round($slope, 2),
            'window_size' => $n,
        );
    }

    /**
     * 推奨アクションを取得
     * 
     * @param array $confidence 信頼度情報
     * @param array $trend トレンド情報
     * @return array 推奨アクション
     */
    public function get_recommendations($confidence, $trend) {
        $recommendations = array();

        // 信頼度ベースの推奨
        if ($confidence['level'] === 'low') {
            $recommendations[] = array(
                'type' => 'data_collection',
                'priority' => 'high',
                'message' => 'より多くの記事を生成してデータを蓄積',
            );
        }

        if ($confidence['level'] === 'unstable') {
            $recommendations[] = array(
                'type' => 'consistency',
                'priority' => 'medium',
                'message' => 'プロンプトの標準化でスコアを安定化',
            );
        }

        // トレンドベースの推奨
        if ($trend['direction'] === 'declining') {
            $recommendations[] = array(
                'type' => 'quality_check',
                'priority' => 'high',
                'message' => '品質低下の原因を調査',
            );
        }

        if ($trend['direction'] === 'improving' && $confidence['average'] < 75) {
            $recommendations[] = array(
                'type' => 'continue',
                'priority' => 'low',
                'message' => '現在の方針を継続',
            );
        }

        return $recommendations;
    }

    /**
     * ホテル別の信頼度を取得
     * 
     * @param string $hotel_name ホテル名
     * @return array 信頼度情報
     */
    public function get_hotel_confidence($hotel_name) {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return $this->calculate_confidence(array());
        }

        $learning = HRS_HQC_Learning_Module::get_instance();
        $history = $learning->get_history_by_hotel($hotel_name);
        
        if (empty($history)) {
            return $this->calculate_confidence(array());
        }

        $scores = array_column($history, 'total_score');
        return $this->calculate_confidence($scores);
    }

    /**
     * 全体の信頼度サマリーを取得
     * 
     * @return array サマリー情報
     */
    public function get_overall_summary() {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return array(
                'confidence' => $this->calculate_confidence(array()),
                'trend' => array('direction' => 'unknown'),
                'recommendations' => array(),
            );
        }

        $learning = HRS_HQC_Learning_Module::get_instance();
        $stats = $learning->get_statistics();
        
        // 最近のスコアを取得
        $recent_history = $learning->get_recent_history(50);
        $scores = array_column($recent_history, 'total_score');
        
        $confidence = $this->calculate_confidence($scores);
        $trend = $this->analyze_trend($scores);
        $recommendations = $this->get_recommendations($confidence, $trend);

        return array(
            'confidence' => $confidence,
            'trend' => $trend,
            'recommendations' => $recommendations,
            'statistics' => $stats,
        );
    }

    /**
     * 閾値を更新
     * 
     * @param string $key 設定キー
     * @param mixed $value 値
     * @return bool
     */
    public function update_config($key, $value) {
        if (!array_key_exists($key, $this->config)) {
            return false;
        }

        $this->config[$key] = $value;
        update_option('hrs_hqc_confidence_config', $this->config);
        return true;
    }

    /**
     * 設定を取得
     * 
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
}