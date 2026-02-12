<?php
/**
 * HQC Dashboard Widget
 *
 * @package    Hotel_Review_System
 * @subpackage HQC_Generator
 * @since      1.0.0
 * @version    1.3.0
 *
 * 変更履歴:
 * - 1.3.0: スコア推移とスコア分布を横並びレイアウトに変更
 * - 1.2.0: スコア分布を横棒グラフからSVG円グラフ（パイチャート）に変更
 * - 1.1.0: post_type を hotel-review に修正、メタキーを _hrs_hqc_* に統一
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Dashboard_Widget {
    
    const POST_TYPE = 'hotel-review';

    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'hqc_dashboard_widget',
            'HQC Generator ステータス',
            array($this, 'render_dashboard_widget')
        );
    }

    public function render_dashboard_widget() {
        $stats = $this->get_hqc_stats();
        ?>
        <div class="hrs-hqc-dashboard-widget">
            <div class="hrs-hqc-stats-grid">
                <div class="hrs-hqc-stat-item">
                    <span class="hrs-hqc-stat-label">Total Articles</span>
                    <span class="hrs-hqc-stat-value"><?php echo esc_html($stats['total_articles']); ?></span>
                </div>
                <div class="hrs-hqc-stat-item">
                    <span class="hrs-hqc-stat-label">Average H Score</span>
                    <span class="hrs-hqc-stat-value"><?php echo esc_html(number_format($stats['avg_h_score'], 1)); ?></span>
                </div>
                <div class="hrs-hqc-stat-item">
                    <span class="hrs-hqc-stat-label">Average Q Score</span>
                    <span class="hrs-hqc-stat-value"><?php echo esc_html(number_format($stats['avg_q_score'], 1)); ?></span>
                </div>
                <div class="hrs-hqc-stat-item">
                    <span class="hrs-hqc-stat-label">Average C Score</span>
                    <span class="hrs-hqc-stat-value"><?php echo esc_html(number_format($stats['avg_c_score'], 1)); ?></span>
                </div>
            </div>
            <div class="hrs-hqc-recent-articles">
                <h4>Recent HQC Articles</h4>
                <?php if (!empty($stats['recent_articles'])): ?>
                    <ul>
                        <?php foreach ($stats['recent_articles'] as $article): ?>
                            <li>
                                <a href="<?php echo esc_url(get_edit_post_link($article['id'])); ?>">
                                    <?php echo esc_html($article['title']); ?>
                                </a>
                                <span class="hrs-hqc-scores">
                                    H: <?php echo esc_html($article['h_score']); ?> |
                                    Q: <?php echo esc_html($article['q_score']); ?> |
                                    C: <?php echo esc_html($article['c_score']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No HQC articles generated yet.</p>
                <?php endif; ?>
            </div>
            <div class="hrs-hqc-quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-generator')); ?>" class="button button-primary">
                    Go to HQC Generator
                </a>
            </div>
            <style>
                .hrs-hqc-dashboard-widget { padding: 12px; }
                .hrs-hqc-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px; }
                .hrs-hqc-stat-item { background: #f0f0f1; padding: 16px; border-radius: 4px; text-align: center; }
                .hrs-hqc-stat-label { display: block; font-size: 12px; color: #646970; margin-bottom: 8px; }
                .hrs-hqc-stat-value { display: block; font-size: 24px; font-weight: 600; color: #1d2327; }
                .hrs-hqc-recent-articles h4 { margin-top: 0; margin-bottom: 12px; font-size: 14px; }
                .hrs-hqc-recent-articles ul { margin: 0; padding: 0; list-style: none; }
                .hrs-hqc-recent-articles li { padding: 8px 0; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; }
                .hrs-hqc-scores { font-size: 12px; color: #646970; white-space: nowrap; }
                .hrs-hqc-quick-actions { text-align: center; margin-top: 16px; }
            </style>
        </div>
        <?php
    }

    /**
     * 管理画面の専用ページ「HQC学習ダッシュボード」
     */
    public function render_dashboard_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('このページにアクセスする権限がありません。'));
        }

        $stats = $this->get_hqc_stats();
        $trend_data = $this->get_score_trend_data();
        $distribution = $this->get_score_distribution();
        ?>
        <div class="wrap hrs-hqc-learning-dashboard">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-bar"></span> HQC学習ダッシュボード
            </h1>
            
            <p class="description">
                HQC Generator で生成された記事の統計情報と学習履歴を表示します。
                <a href="<?php echo esc_url(add_query_arg('refresh', '1')); ?>" class="button button-small" style="margin-left: 10px;">
                    <span class="dashicons dashicons-update"></span> データを更新
                </a>
            </p>

            <?php if (isset($_GET['refresh'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>✓ 最新データを取得しました</p>
                </div>
            <?php endif; ?>

            <!-- 統計カード -->
            <div class="hrs-hqc-stats-section">
                <div class="hrs-hqc-stats-grid-4">
                    <div class="hrs-hqc-stat-card hrs-hqc-stat-card--gradient-purple">
                        <div class="hrs-hqc-stat-value"><?php echo esc_html($stats['total_articles']); ?></div>
                        <div class="hrs-hqc-stat-label">総生成数</div>
                    </div>
                    <div class="hrs-hqc-stat-card hrs-hqc-stat-card--gradient-pink">
                        <div class="hrs-hqc-stat-value"><?php echo $stats['avg_h_score'] > 0 ? esc_html(number_format($stats['avg_h_score'], 1)) : '--'; ?></div>
                        <div class="hrs-hqc-stat-label">平均Hスコア<?php echo $stats['avg_h_score'] == 0 ? ' (未学習)' : ''; ?></div>
                    </div>
                    <div class="hrs-hqc-stat-card hrs-hqc-stat-card--gradient-blue">
                        <div class="hrs-hqc-stat-value"><?php echo $stats['avg_q_score'] > 0 ? esc_html(number_format($stats['avg_q_score'], 1)) : '--'; ?></div>
                        <div class="hrs-hqc-stat-label">平均Qスコア<?php echo $stats['avg_q_score'] == 0 ? ' (未学習)' : ''; ?></div>
                    </div>
                    <div class="hrs-hqc-stat-card hrs-hqc-stat-card--gradient-green">
                        <div class="hrs-hqc-stat-value"><?php echo $stats['avg_c_score'] > 0 ? esc_html(number_format($stats['avg_c_score'], 1)) : '--'; ?></div>
                        <div class="hrs-hqc-stat-label">平均Cスコア<?php echo $stats['avg_c_score'] == 0 ? ' (未学習)' : ''; ?></div>
                    </div>
                </div>
            </div>

            <!-- ★ スコア推移 + スコア分布 横並び -->
            <div class="hrs-hqc-charts-row">
                <div class="hrs-hqc-chart-col hrs-hqc-chart-col--trend">
                    <h2>スコア推移</h2>
                    <div class="hrs-hqc-card hrs-hqc-card--white">
                        <?php if (!empty($trend_data)): ?>
                            <?php echo self::render_trend_svg($trend_data); ?>
                        <?php else: ?>
                            <p style="text-align: center; padding: 40px; color: #666;">表示するデータがありません。記事を生成するとスコア推移が表示されます。</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hrs-hqc-chart-col hrs-hqc-chart-col--dist">
                    <h2>スコア分布</h2>
                    <div class="hrs-hqc-card hrs-hqc-card--white">
                        <?php if ($stats['total_articles'] > 0): ?>
                            <?php echo self::render_distribution_pie($distribution, $stats['total_articles']); ?>
                        <?php else: ?>
                            <p style="text-align: center; padding: 40px 0; color: #666;">スコア分布データがありません。記事を生成すると表示されます。</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 最近の生成履歴 -->
            <div class="hrs-hqc-section">
                <h2>最近の生成履歴</h2>
                <div class="hrs-hqc-card hrs-hqc-card--white">
                    <?php if (!empty($stats['recent_articles'])): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>記事タイトル</th>
                                    <th style="width: 80px; text-align: center;">総合</th>
                                    <th style="width: 80px; text-align: center;">Hスコア</th>
                                    <th style="width: 80px; text-align: center;">Qスコア</th>
                                    <th style="width: 80px; text-align: center;">Cスコア</th>
                                    <th style="width: 120px;">生成日時</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_articles'] as $article): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($article['id'])); ?>">
                                                <?php echo esc_html($article['title']); ?>
                                            </a>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="hrs-score-badge hrs-score-badge--total"><?php echo esc_html($article['total_score']); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="hrs-score-badge hrs-score-badge--h"><?php echo esc_html($article['h_score']); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="hrs-score-badge hrs-score-badge--q"><?php echo esc_html($article['q_score']); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="hrs-score-badge hrs-score-badge--c"><?php echo esc_html($article['c_score']); ?></span>
                                        </td>
                                        <td><?php echo esc_html(get_the_date('Y/m/d H:i', $article['id'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px; color: #666;">履歴がありません。HQC Generatorで記事を生成すると表示されます。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .hrs-hqc-learning-dashboard { margin: 20px 20px 20px 0; }
            .hrs-hqc-stats-section { margin: 30px 0; }
            .hrs-hqc-stats-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
            .hrs-hqc-stat-card { padding: 30px 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; color: white; }
            .hrs-hqc-stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 12px rgba(0,0,0,0.15); }
            .hrs-hqc-stat-card--gradient-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
            .hrs-hqc-stat-card--gradient-pink { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
            .hrs-hqc-stat-card--gradient-blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
            .hrs-hqc-stat-card--gradient-green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
            .hrs-hqc-stat-value { font-size: 48px; font-weight: 700; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .hrs-hqc-stat-label { font-size: 14px; opacity: 0.95; font-weight: 500; }

            /* ★ 横並びレイアウト */
            .hrs-hqc-charts-row { display: grid; grid-template-columns: 3fr 2fr; gap: 20px; margin: 30px 0; }
            .hrs-hqc-chart-col h2 { font-size: 20px; font-weight: 600; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #2271b1; }
            .hrs-hqc-chart-col .hrs-hqc-card { height: 100%; display: flex; align-items: center; justify-content: center; }
            .hrs-hqc-chart-col--trend .hrs-hqc-card { display: block; }

            .hrs-hqc-section { margin: 30px 0; }
            .hrs-hqc-section h2 { font-size: 20px; font-weight: 600; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #2271b1; }
            .hrs-hqc-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
            .hrs-hqc-card--white { background: white; }
            .hrs-score-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 13px; }
            .hrs-score-badge--total { background: #e8e5ff; color: #5b21b6; }
            .hrs-score-badge--h { background: #ffe5e5; color: #d63638; }
            .hrs-score-badge--q { background: #e5f5ff; color: #0073aa; }
            .hrs-score-badge--c { background: #e5ffe5; color: #00a32a; }
            .hrs-trend-wrapper { padding: 10px 0; }
            .hrs-trend-legend { display: flex; gap: 16px; justify-content: center; margin-bottom: 12px; }
            .hrs-trend-legend-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; }
            .hrs-trend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
            .hrs-trend-svg { width: 100%; height: auto; max-height: 300px; }
            /* 円グラフ */
            .hrs-pie-wrapper { display: flex; align-items: center; justify-content: center; gap: 30px; padding: 20px 0; flex-wrap: wrap; }
            .hrs-pie-svg-container { flex-shrink: 0; }
            .hrs-pie-svg { width: 200px; height: 200px; }
            .hrs-pie-legend { display: flex; flex-direction: column; gap: 12px; }
            .hrs-pie-legend-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #374151; }
            .hrs-pie-legend-color { width: 16px; height: 16px; border-radius: 4px; flex-shrink: 0; }
            .hrs-pie-legend-count { font-weight: 700; font-size: 15px; min-width: 30px; }
            .hrs-pie-legend-range { font-weight: 500; }
            .hrs-pie-legend-pct { color: #9ca3af; font-size: 12px; }

            @media (max-width: 1280px) {
                .hrs-hqc-stats-grid-4 { grid-template-columns: repeat(2, 1fr); }
                .hrs-hqc-charts-row { grid-template-columns: 1fr; }
            }
            @media (max-width: 782px) {
                .hrs-hqc-stats-grid-4 { grid-template-columns: 1fr; }
                .hrs-pie-wrapper { flex-direction: column; gap: 20px; }
            }
        </style>
        <?php
    }

    /**
     * SVGラインチャート
     */
    private static function render_trend_svg($trend_data) {
        $count = count($trend_data);
        if ($count === 0) return '';

        $w = 800;
        $h = 280;
        $pad_l = 40;
        $pad_r = 20;
        $pad_t = 30;
        $pad_b = 40;
        $chart_w = $w - $pad_l - $pad_r;
        $chart_h = $h - $pad_t - $pad_b;

        $series = [
            'total' => ['color' => '#6366f1', 'label' => '総合'],
            'h'     => ['color' => '#f5576c', 'label' => 'H'],
            'q'     => ['color' => '#4facfe', 'label' => 'Q'],
            'c'     => ['color' => '#43e97b', 'label' => 'C'],
        ];

        $svg = '<div class="hrs-trend-wrapper">';
        $svg .= '<div class="hrs-trend-legend">';
        foreach ($series as $key => $s) {
            $svg .= '<span class="hrs-trend-legend-item"><span class="hrs-trend-dot" style="background:' . $s['color'] . ';"></span>' . esc_html($s['label']) . '</span>';
        }
        $svg .= '</div>';

        $svg .= '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="hrs-trend-svg">';

        for ($v = 0; $v <= 100; $v += 20) {
            $y = $pad_t + $chart_h - ($v / 100 * $chart_h);
            $svg .= '<line x1="' . $pad_l . '" y1="' . $y . '" x2="' . ($w - $pad_r) . '" y2="' . $y . '" stroke="#e5e7eb" stroke-width="1"/>';
            $svg .= '<text x="' . ($pad_l - 6) . '" y="' . ($y + 4) . '" text-anchor="end" fill="#9ca3af" font-size="11">' . $v . '</text>';
        }

        foreach ($series as $key => $s) {
            $points = [];
            foreach ($trend_data as $i => $d) {
                $x = $pad_l + ($count > 1 ? $i / ($count - 1) * $chart_w : $chart_w / 2);
                $y = $pad_t + $chart_h - ($d[$key] / 100 * $chart_h);
                $points[] = round($x, 1) . ',' . round($y, 1);
            }
            $stroke_dash = $key === 'total' ? '' : ' stroke-dasharray="6,4"';
            $stroke_w = $key === 'total' ? '2.5' : '1.5';
            $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $s['color'] . '" stroke-width="' . $stroke_w . '"' . $stroke_dash . ' stroke-linejoin="round" stroke-linecap="round"/>';

            foreach ($trend_data as $i => $d) {
                $x = $pad_l + ($count > 1 ? $i / ($count - 1) * $chart_w : $chart_w / 2);
                $y = $pad_t + $chart_h - ($d[$key] / 100 * $chart_h);
                $r = $key === 'total' ? '4' : '3';
                $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="' . $r . '" fill="' . $s['color'] . '"/>';
            }
        }

        $label_step = max(1, intval($count / 10));
        foreach ($trend_data as $i => $d) {
            if ($i % $label_step === 0 || $i === $count - 1) {
                $x = $pad_l + ($count > 1 ? $i / ($count - 1) * $chart_w : $chart_w / 2);
                $svg .= '<text x="' . round($x, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" fill="#9ca3af" font-size="11">' . esc_html($d['label']) . '</text>';
            }
        }

        $svg .= '</svg></div>';
        return $svg;
    }

    /**
     * SVG円グラフ
     */
    private static function render_distribution_pie($distribution, $total) {
        $segments = [
            '0-25'   => ['color' => '#ef4444', 'label' => '0〜25点'],
            '26-50'  => ['color' => '#f59e0b', 'label' => '26〜50点'],
            '51-75'  => ['color' => '#eab308', 'label' => '51〜75点'],
            '76-100' => ['color' => '#22c55e', 'label' => '76〜100点'],
        ];

        $sum = array_sum($distribution);
        if ($sum === 0) {
            return '<p style="text-align: center; padding: 40px 0; color: #666;">スコア分布データがありません。</p>';
        }

        $cx = 100;
        $cy = 100;
        $r = 80;
        $circumference = 2 * M_PI * $r;

        $html = '<div class="hrs-pie-wrapper">';
        $html .= '<div class="hrs-pie-svg-container">';
        $html .= '<svg viewBox="0 0 200 200" class="hrs-pie-svg">';

        $offset = 0;
        $delay = 0;
        foreach ($segments as $range => $seg) {
            $count = $distribution[$range];
            if ($count === 0) continue;

            $pct = $count / $sum;
            $dash = $pct * $circumference;
            $gap = $circumference - $dash;

            $html .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" '
                    . 'fill="none" '
                    . 'stroke="' . $seg['color'] . '" '
                    . 'stroke-width="50" '
                    . 'stroke-dasharray="' . round($dash, 2) . ' ' . round($gap, 2) . '" '
                    . 'stroke-dashoffset="' . round(-$offset, 2) . '" '
                    . 'transform="rotate(-90 ' . $cx . ' ' . $cy . ')" '
                    . 'style="transition: stroke-dashoffset 0.6s ease ' . $delay . 's;"'
                    . '/>';

            $offset += $dash;
            $delay += 0.15;
        }

        $html .= '</svg>';
        $html .= '</div>';

        $html .= '<div class="hrs-pie-legend">';
        foreach ($segments as $range => $seg) {
            $count = $distribution[$range];
            $pct = $sum > 0 ? round($count / $sum * 100, 1) : 0;
            $html .= '<div class="hrs-pie-legend-item">';
            $html .= '<span class="hrs-pie-legend-color" style="background:' . $seg['color'] . ';"></span>';
            $html .= '<span class="hrs-pie-legend-count">' . esc_html($count) . '件</span>';
            $html .= '<span class="hrs-pie-legend-range">' . esc_html($seg['label']) . '</span>';
            $html .= '<span class="hrs-pie-legend-pct">(' . esc_html($pct) . '%)</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function get_hqc_stats() {
        $stats = array(
            'total_articles'  => 0,
            'avg_h_score'     => 0,
            'avg_q_score'     => 0,
            'avg_c_score'     => 0,
            'recent_articles' => array()
        );

        $query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'draft', 'pending'),
            'meta_query'     => array(array('key' => '_hrs_hqc_score', 'compare' => 'EXISTS')),
            'fields'         => 'ids',
        ));

        $post_ids = $query->posts;
        $stats['total_articles'] = count($post_ids);

        if ($stats['total_articles'] > 0) {
            $total_h = 0; $total_q = 0; $total_c = 0; $scored_count = 0;

            foreach ($post_ids as $post_id) {
                $h = floatval(get_post_meta($post_id, '_hrs_hqc_h_score', true));
                $q = floatval(get_post_meta($post_id, '_hrs_hqc_q_score', true));
                $c = floatval(get_post_meta($post_id, '_hrs_hqc_c_score', true));
                if ($h > 0 && $h <= 1) $h *= 100;
                if ($q > 0 && $q <= 1) $q *= 100;
                if ($c > 0 && $c <= 1) $c *= 100;
                if ($h > 0 || $q > 0 || $c > 0) {
                    $total_h += $h; $total_q += $q; $total_c += $c; $scored_count++;
                }
            }

            if ($scored_count > 0) {
                $stats['avg_h_score'] = $total_h / $scored_count;
                $stats['avg_q_score'] = $total_q / $scored_count;
                $stats['avg_c_score'] = $total_c / $scored_count;
            }

            $recent_query = new WP_Query(array(
                'post_type'      => self::POST_TYPE,
                'posts_per_page' => 10,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => array('publish', 'draft', 'pending'),
                'meta_query'     => array(array('key' => '_hrs_hqc_score', 'compare' => 'EXISTS')),
            ));

            foreach ($recent_query->posts as $post) {
                $total = floatval(get_post_meta($post->ID, '_hrs_hqc_score', true));
                $h = floatval(get_post_meta($post->ID, '_hrs_hqc_h_score', true));
                $q = floatval(get_post_meta($post->ID, '_hrs_hqc_q_score', true));
                $c = floatval(get_post_meta($post->ID, '_hrs_hqc_c_score', true));
                if ($total > 0 && $total <= 1) $total *= 100;
                if ($h > 0 && $h <= 1) $h *= 100;
                if ($q > 0 && $q <= 1) $q *= 100;
                if ($c > 0 && $c <= 1) $c *= 100;

                $stats['recent_articles'][] = array(
                    'id' => $post->ID, 'title' => $post->post_title,
                    'total_score' => round($total, 1), 'h_score' => round($h, 1),
                    'q_score' => round($q, 1), 'c_score' => round($c, 1),
                );
            }
            wp_reset_postdata();
        }
        return $stats;
    }

    private function get_score_trend_data() {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE, 'posts_per_page' => 30,
            'orderby' => 'date', 'order' => 'ASC',
            'post_status' => array('publish', 'draft', 'pending'),
            'meta_query' => array(array('key' => '_hrs_hqc_score', 'compare' => 'EXISTS')),
        ));
        $data = array();
        foreach ($query->posts as $post) {
            $total = floatval(get_post_meta($post->ID, '_hrs_hqc_score', true));
            $h = floatval(get_post_meta($post->ID, '_hrs_hqc_h_score', true));
            $q = floatval(get_post_meta($post->ID, '_hrs_hqc_q_score', true));
            $c = floatval(get_post_meta($post->ID, '_hrs_hqc_c_score', true));
            if ($total > 0 && $total <= 1) $total *= 100;
            if ($h > 0 && $h <= 1) $h *= 100;
            if ($q > 0 && $q <= 1) $q *= 100;
            if ($c > 0 && $c <= 1) $c *= 100;
            $data[] = array('label' => get_the_date('m/d', $post->ID), 'total' => round($total, 1), 'h' => round($h, 1), 'q' => round($q, 1), 'c' => round($c, 1));
        }
        wp_reset_postdata();
        return $data;
    }

    private function get_score_distribution() {
        $dist = array('0-25' => 0, '26-50' => 0, '51-75' => 0, '76-100' => 0);
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE, 'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending'),
            'meta_query' => array(array('key' => '_hrs_hqc_score', 'compare' => 'EXISTS')),
            'fields' => 'ids',
        ));
        foreach ($query->posts as $post_id) {
            $score = floatval(get_post_meta($post_id, '_hrs_hqc_score', true));
            if ($score > 0 && $score <= 1) $score *= 100;
            if ($score <= 25) $dist['0-25']++;
            elseif ($score <= 50) $dist['26-50']++;
            elseif ($score <= 75) $dist['51-75']++;
            else $dist['76-100']++;
        }
        wp_reset_postdata();
        return $dist;
    }
}

new HRS_HQC_Dashboard_Widget();