<?php
/**
 * HQCスコアチャート
 * 
 * Chart.jsを使用してスコア推移を可視化
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.1.0 - スコア分布を円グラフ(doughnut)に改良
 *   - 中央に総記事数を表示
 *   - 下部に件数・割合付き凡例テーブル
 *   - ツールチップに割合表示
 *   - テーブル未存在時のフォールバック
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('HRS_HQC_Score_Chart')) {
    return;
}

class HRS_HQC_Score_Chart {

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_chart_scripts'));
    }

    /**
     * Chart.js読み込み
     */
    public function enqueue_chart_scripts($hook) {
        // 5d-review関連ページ OR hrs-hqc関連ページ
        if (strpos($hook, '5d-review') === false && strpos($hook, 'hrs-hqc') === false) {
            return;
        }

        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        wp_enqueue_script(
            'hrs-hqc-charts',
            HRS_PLUGIN_URL . 'assets/js/hqc-charts.js',
            array('chartjs', 'jquery'),
            HRS_VERSION,
            true
        );
    }

    /**
     * スコア推移チャートを生成
     */
    public function render_trend_chart($days = 30, $canvas_id = 'hqc-trend-chart') {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            echo '<p>学習モジュールが読み込まれていません。</p>';
            return;
        }

        $learning = HRS_HQC_Learning_Module::get_instance();
        $trend_data = $learning->get_score_trend($days);

        if (empty($trend_data)) {
            echo '<p>表示するデータがありません。</p>';
            return;
        }

        $labels = array();
        $total_scores = array();
        $h_scores = array();
        $q_scores = array();
        $c_scores = array();

        foreach ($trend_data as $row) {
            $labels[] = date('m/d', strtotime($row['date']));
            $total_scores[] = round((float)$row['avg_total'], 1);
            $h_scores[] = round((float)$row['avg_h'], 1);
            $q_scores[] = round((float)$row['avg_q'], 1);
            $c_scores[] = round((float)$row['avg_c'], 1);
        }

        $chart_data = array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => '総合スコア',
                    'data' => $total_scores,
                    'borderColor' => '#4CAF50',
                    'backgroundColor' => 'rgba(76, 175, 80, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ),
                array(
                    'label' => 'H軸（人間性）',
                    'data' => $h_scores,
                    'borderColor' => '#2196F3',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                    'borderDash' => array(5, 5),
                ),
                array(
                    'label' => 'Q軸（品質）',
                    'data' => $q_scores,
                    'borderColor' => '#FF9800',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                    'borderDash' => array(5, 5),
                ),
                array(
                    'label' => 'C軸（構造）',
                    'data' => $c_scores,
                    'borderColor' => '#9C27B0',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                    'borderDash' => array(5, 5),
                ),
            ),
        );

        echo '<div style="max-width: 800px; margin: 20px 0;">';
        echo '<canvas id="' . esc_attr($canvas_id) . '" width="800" height="400"></canvas>';
        echo '</div>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Chart !== "undefined") {
                    var ctx = document.getElementById("' . esc_js($canvas_id) . '").getContext("2d");
                    new Chart(ctx, {
                        type: "line",
                        data: ' . json_encode($chart_data) . ',
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: "HQCスコア推移（過去' . (int)$days . '日間）"
                                },
                                legend: {
                                    position: "bottom"
                                }
                            },
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100,
                                    title: {
                                        display: true,
                                        text: "スコア"
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>';
    }

    /**
     * 軸別バーチャートを生成
     */
    public function render_axis_chart($canvas_id = 'hqc-axis-chart') {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            return;
        }

        $learning = HRS_HQC_Learning_Module::get_instance();
        $stats = $learning->get_statistics();

        $chart_data = array(
            'labels' => array('H軸（人間性）', 'Q軸（品質）', 'C軸（構造）'),
            'datasets' => array(
                array(
                    'label' => '平均スコア',
                    'data' => array(
                        $stats['history']['avg_h'] ?? 0,
                        $stats['history']['avg_q'] ?? 0,
                        $stats['history']['avg_c'] ?? 0,
                    ),
                    'backgroundColor' => array(
                        'rgba(33, 150, 243, 0.7)',
                        'rgba(255, 152, 0, 0.7)',
                        'rgba(156, 39, 176, 0.7)',
                    ),
                    'borderColor' => array(
                        '#2196F3',
                        '#FF9800',
                        '#9C27B0',
                    ),
                    'borderWidth' => 2,
                ),
            ),
        );

        echo '<div style="max-width: 400px; margin: 20px 0;">';
        echo '<canvas id="' . esc_attr($canvas_id) . '" width="400" height="300"></canvas>';
        echo '</div>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Chart !== "undefined") {
                    var ctx = document.getElementById("' . esc_js($canvas_id) . '").getContext("2d");
                    new Chart(ctx, {
                        type: "bar",
                        data: ' . json_encode($chart_data) . ',
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: "軸別平均スコア"
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100
                                }
                            }
                        }
                    });
                }
            });
        </script>';
    }

    /**
     * ★ スコア分布チャートを生成（ドーナツ円グラフ改良版）
     *
     * v1.1.0 変更点:
     * - type: "bar" → "doughnut"
     * - 中央に総記事数テキスト表示（Chart.jsカスタムプラグイン）
     * - 下部に件数・割合付き凡例テーブル
     * - ツールチップに割合%表示
     * - ホバー時のオフセットアニメーション
     * - テーブル未存在時のフォールバック
     */
    public function render_distribution_chart($canvas_id = 'hqc-distribution-chart') {
        global $wpdb;

        $table = $wpdb->prefix . 'hrs_hqc_history';

        // テーブル存在チェック（フォールバック）
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $table = $wpdb->prefix . 'hrs_hqc_learning_history';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $this->render_distribution_fallback('学習データテーブルが見つかりません');
                return;
            }
        }

        // スコア分布を集計
        $ranges = array(
            '0-25'   => 0,
            '26-50'  => 0,
            '51-75'  => 0,
            '76-100' => 0,
        );

        $results = $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN total_score <= 25 THEN '0-25'
                    WHEN total_score <= 50 THEN '26-50'
                    WHEN total_score <= 75 THEN '51-75'
                    ELSE '76-100'
                END as score_range,
                COUNT(*) as count
            FROM {$table}
            GROUP BY score_range",
            ARRAY_A
        );

        foreach ($results as $row) {
            if (isset($ranges[$row['score_range']])) {
                $ranges[$row['score_range']] = (int)$row['count'];
            }
        }

        $total_count = array_sum($ranges);

        if ($total_count === 0) {
            $this->render_distribution_fallback('スコア分布データがありません', '記事を生成すると分布が表示されます');
            return;
        }

        $chart_values = array_values($ranges);

        // ===== ドーナツグラフ =====
        echo '<div style="position: relative; max-width: 260px; margin: 10px auto 0;">';
        echo '<canvas id="' . esc_attr($canvas_id) . '" width="260" height="260"></canvas>';
        echo '</div>';

        // ===== 凡例テーブル =====
        $legend = array(
            '0-25'   => array('label' => '低品質',  'color' => '#ef4444'),
            '26-50'  => array('label' => '改善余地', 'color' => '#f97316'),
            '51-75'  => array('label' => '良好',    'color' => '#eab308'),
            '76-100' => array('label' => '高品質',  'color' => '#22c55e'),
        );

        echo '<div style="margin-top: 16px; padding: 0 10px;">';
        foreach ($ranges as $range => $count) {
            $cfg = $legend[$range] ?? array('label' => $range, 'color' => '#999');
            $pct = $total_count > 0 ? round(($count / $total_count) * 100, 1) : 0;

            echo '<div style="display: flex; align-items: center; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px;">';
            echo '<span style="display: flex; align-items: center; gap: 8px;">';
            echo '<span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: ' . esc_attr($cfg['color']) . '; flex-shrink: 0;"></span>';
            echo '<span>' . esc_html($range) . '</span>';
            echo '<span style="color: #888; font-size: 11px;">(' . esc_html($cfg['label']) . ')</span>';
            echo '</span>';
            echo '<span style="font-weight: 600; white-space: nowrap;">';
            echo esc_html($count) . '件 ';
            echo '<span style="color: #888; font-weight: 400; font-size: 12px;">(' . esc_html($pct) . '%)</span>';
            echo '</span>';
            echo '</div>';
        }
        echo '</div>';

        // ===== Chart.js ドーナツ描画 =====
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Chart === "undefined") return;
                var ctx = document.getElementById("' . esc_js($canvas_id) . '");
                if (!ctx) return;
                
                var totalCount = ' . (int)$total_count . ';
                
                new Chart(ctx.getContext("2d"), {
                    type: "doughnut",
                    data: {
                        labels: ' . json_encode(array_keys($ranges)) . ',
                        datasets: [{
                            data: ' . json_encode($chart_values) . ',
                            backgroundColor: ["#ef4444", "#f97316", "#eab308", "#22c55e"],
                            borderColor: ["#dc2626", "#ea580c", "#ca8a04", "#16a34a"],
                            borderWidth: 2,
                            hoverBorderWidth: 3,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: "58%",
                        plugins: {
                            title: { display: false },
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var val = context.parsed || 0;
                                        var pct = totalCount > 0 ? Math.round((val / totalCount) * 100) : 0;
                                        return " " + context.label + ": " + val + "件 (" + pct + "%)";
                                    }
                                },
                                backgroundColor: "rgba(0,0,0,0.8)",
                                titleFont: { size: 13, weight: "bold" },
                                bodyFont: { size: 13 },
                                padding: 10,
                                cornerRadius: 8,
                                displayColors: true,
                                boxPadding: 4
                            }
                        },
                        animation: {
                            animateRotate: true,
                            duration: 800
                        }
                    },
                    plugins: [{
                        id: "centerText",
                        beforeDraw: function(chart) {
                            var w = chart.width;
                            var h = chart.height;
                            var c = chart.ctx;
                            c.save();
                            
                            c.font = "bold 28px -apple-system, BlinkMacSystemFont, sans-serif";
                            c.textBaseline = "middle";
                            c.textAlign = "center";
                            c.fillStyle = "#1f2937";
                            c.fillText(totalCount, w / 2, h / 2 - 8);
                            
                            c.font = "12px -apple-system, BlinkMacSystemFont, sans-serif";
                            c.fillStyle = "#6b7280";
                            c.fillText("\u7DCF\u8A18\u4E8B\u6570", w / 2, h / 2 + 16);
                            
                            c.restore();
                        }
                    }]
                });
            });
        </script>';
    }

    /**
     * スコア分布フォールバック表示
     */
    private function render_distribution_fallback($message, $hint = '') {
        echo '<div style="text-align: center; padding: 40px 20px; color: #666;">';
        echo '<div style="font-size: 32px; margin-bottom: 10px;">🥧</div>';
        echo '<p style="margin: 0; font-weight: 500;">' . esc_html($message) . '</p>';
        if (!empty($hint)) {
            echo '<p style="margin: 5px 0 0; font-size: 12px; color: #999;">' . esc_html($hint) . '</p>';
        }
        echo '</div>';
    }
}

// インスタンス化
new HRS_HQC_Score_Chart();