<?php
/**
 * Nurture Page - メインクラス（ページコントローラ＋UI）
 * 
 * HQCスコアに基づき、既存記事を分析・改善・再生成する管理画面
 *
 * @package Hotel_Review_System
 * @version 7.1.0 - 自動最適化ボタン追加
 * 
 * 変更履歴:
 * - 7.1.0: 自動最適化ボタン追加（HQC Auto Optimizer連携）
 * - 7.0.1: 全選択機能追加
 */

if (!defined('ABSPATH')) {
    exit;
}

// ★ 必須依存を安全に読み込み（重複防止）
if (!class_exists('HRS_Nurture_Styles')) {
    require_once __DIR__ . '/nurture/class-nurture-styles.php';
}
if (!class_exists('HRS_Nurture_Scripts')) {
    require_once __DIR__ . '/nurture/class-nurture-scripts.php';
}
if (!class_exists('HRS_Nurture_Data')) {
    require_once __DIR__ . '/nurture/class-nurture-data.php';
}

// ★ クラス未定義時のみ定義（安全ロード）
if (!class_exists('HRS_Nurture_Page')) {

    class HRS_Nurture_Page {

        public function render() {
            if (!current_user_can('edit_posts')) {
                wp_die(__('このページにアクセスする権限がありません。', '5d-review-builder'));
            }
            
            $score_filter = isset($_GET['score']) ? sanitize_key($_GET['score']) : 'all';
            $order_filter = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'date';
            $direction_filter = isset($_GET['direction']) ? sanitize_key($_GET['direction']) : 'desc';
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            
            $result = HRS_Nurture_Data::get_articles($score_filter, $order_filter, $direction_filter, $paged);
            $articles = $result['articles'];
            $pagination = [
                'total'       => $result['total'],
                'total_pages' => $result['total_pages'],
                'current'     => $result['current'],
                'per_page'    => $result['per_page'],
            ];
            $stats = HRS_Nurture_Data::get_statistics();
            ?>
            <div class="wrap hrs-nurture-wrap">
                <?php $this->render_header(); ?>
                <?php $this->render_stats($stats); ?>
                <?php $this->render_filters($score_filter, $order_filter, $direction_filter); ?>
                <?php $this->render_tips(); ?>
                <?php $this->render_articles($articles, $pagination); ?>
            </div>
            
            <!-- ★【v7.1.0】自動最適化モーダル -->
            <div id="hrs-optimize-modal" class="hrs-modal" style="display:none;">
                <div class="hrs-modal-content">
                    <div class="hrs-modal-header">
                        <h3><span class="dashicons dashicons-admin-generic"></span> 自動最適化結果</h3>
                        <button type="button" class="hrs-modal-close">&times;</button>
                    </div>
                    <div class="hrs-modal-body" id="hrs-optimize-result">
                        <!-- 結果がここに入る -->
                    </div>
                    <div class="hrs-modal-footer">
                        <button type="button" class="hrs-button hrs-button-primary" id="hrs-optimize-apply" style="display:none;">
                            <span class="dashicons dashicons-yes"></span> この設定でキューに追加
                        </button>
                        <button type="button" class="hrs-button hrs-modal-close-btn">閉じる</button>
                    </div>
                </div>
            </div>
            
            <?php
            HRS_Nurture_Styles::render();
            HRS_Nurture_Scripts::render();
            $this->render_optimize_styles();
            $this->render_optimize_scripts();
        }

        private function render_header() {
            ?>
            <div class="hrs-page-header">
                <h1><span class="dashicons dashicons-chart-line"></span> 記事育成</h1>
                <p class="hrs-page-subtitle">HQCスコアを改善して記事品質を向上させましょう</p>
            </div>
            <?php
        }

        private function render_stats($stats) {
            $cards = [
                ['class' => 'excellent', 'icon' => '🎯', 'key' => 'excellent', 'label' => '優良（80+）'],
                ['class' => 'good', 'icon' => '✨', 'key' => 'good', 'label' => '良好（60-79）'],
                ['class' => 'needs-work', 'icon' => '⚠️', 'key' => 'needs_work', 'label' => '要改善（40-59）'],
                ['class' => 'poor', 'icon' => '❌', 'key' => 'poor', 'label' => '低品質（0-39）'],
            ];
            ?>
            <div class="hrs-stats-cards">
                <?php foreach ($cards as $c): ?>
                <div class="hrs-stat-card <?php echo $c['class']; ?>">
                    <div class="stat-icon"><?php echo $c['icon']; ?></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo esc_html($stats[$c['key']]); ?></div>
                        <div class="stat-label"><?php echo $c['label']; ?></div>
                    </div>
                    <div class="stat-percent"><?php echo esc_html($stats[$c['key'] . '_percent']); ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php
        }

        private function render_filters($score_filter, $order_filter, $direction_filter) {
            ?>
            <div class="hrs-filters-card">
                <div class="hrs-filters-row">
                    <div class="filter-group">
                        <label>HQCスコア</label>
                        <select id="score-filter" class="hrs-select">
                            <option value="all" <?php selected($score_filter, 'all'); ?>>すべて</option>
                            <option value="excellent" <?php selected($score_filter, 'excellent'); ?>>🎯 優良（80+）</option>
                            <option value="good" <?php selected($score_filter, 'good'); ?>>✨ 良好（60-79）</option>
                            <option value="needs_work" <?php selected($score_filter, 'needs_work'); ?>>⚠️ 要改善（40-59）</option>
                            <option value="poor" <?php selected($score_filter, 'poor'); ?>>❌ 低品質（0-39）</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>並び順</label>
                        <select id="order-filter" class="hrs-select">
                            <option value="date" <?php selected($order_filter, 'date'); ?>>作成日時</option>
                            <option value="score" <?php selected($order_filter, 'score'); ?>>HQCスコア</option>
                            <option value="title" <?php selected($order_filter, 'title'); ?>>タイトル</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>順序</label>
                        <select id="direction-filter" class="hrs-select">
                            <option value="desc" <?php selected($direction_filter, 'desc'); ?>>降順</option>
                            <option value="asc" <?php selected($direction_filter, 'asc'); ?>>昇順</option>
                        </select>
                    </div>
                    <button type="button" id="apply-filters" class="hrs-button hrs-button-primary">
                        <span class="dashicons dashicons-filter"></span> フィルター適用
                    </button>
                </div>
            </div>
            <?php
        }

        private function render_tips() {
            $tips = [
                ['icon' => '❤️', 'title' => 'H層強化', 'desc' => '感情表現・体験談・ストーリー性を追加'],
                ['icon' => '✨', 'title' => 'Q層強化', 'desc' => '五感描写・具体的な情報・データを追加'],
                ['icon' => '📊', 'title' => 'C層強化', 'desc' => '見出し構造・内部リンク・CTA改善'],
                ['icon' => '🔧', 'title' => '自動最適化', 'desc' => '再分析結果からパラメータを自動調整して再生成'],
            ];
            ?>
            <div class="hrs-tips-card">
                <h3><span class="dashicons dashicons-lightbulb"></span> HQCスコア改善のヒント</h3>
                <div class="hrs-tips-grid">
                    <?php foreach ($tips as $t): ?>
                    <div class="tip-item">
                        <strong><?php echo $t['icon'] . ' ' . $t['title']; ?></strong>
                        <p><?php echo $t['desc']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }

        private function render_articles($articles, $pagination) {
            $start = ($pagination['current'] - 1) * $pagination['per_page'] + 1;
            $end = min($pagination['current'] * $pagination['per_page'], $pagination['total']);
            ?>
            <div class="hrs-articles-section">
                <div class="hrs-section-header">
                    <h2>
                        <span class="dashicons dashicons-media-document"></span> 
                        記事一覧（<?php echo $pagination['total']; ?>件中 <?php echo $start; ?>〜<?php echo $end; ?>件表示）
                    </h2>
                    <div class="bulk-actions">
                        <label class="select-all-label">
                            <input type="checkbox" id="select-all-articles">
                            <span>全選択</span>
                        </label>
                        <select id="bulk-action-select" class="hrs-select">
                            <option value="">一括操作を選択</option>
                            <option value="analyze">HQC再分析</option>
                            <option value="optimize">🔧 一括自動最適化</option>
                            <option value="trash">🗑️ ゴミ箱へ移動</option>
                            <option value="export">CSV出力</option>
                        </select>
                        <button type="button" id="bulk-action-apply" class="hrs-button" disabled>適用</button>
                        <span id="selected-count" class="selected-count"></span>
                    </div>
                </div>
                
                <?php if (!empty($articles)): ?>
                <div class="hrs-articles-grid">
                    <?php foreach ($articles as $a): ?>
                    <div class="hrs-article-card" data-post-id="<?php echo esc_attr($a['id']); ?>">
                        <div class="article-header">
                            <input type="checkbox" class="article-select" value="<?php echo esc_attr($a['id']); ?>">
                            <div class="article-score-badge score-<?php echo esc_attr($a['score_class']); ?>">
                                <div class="score-number"><?php echo esc_html($a['score']); ?></div>
                                <div class="score-label"><?php echo esc_html($a['score_label']); ?></div>
                                <div class="score-breakdown">H:<?php echo esc_html($a['h_score']); ?> Q:<?php echo esc_html($a['q_score']); ?> C:<?php echo esc_html($a['c_score']); ?></div>
                            </div>
                        </div>
                        <div class="article-body">
                            <h3 class="article-title"><a href="<?php echo get_edit_post_link($a['id']); ?>"><?php echo esc_html($a['title']); ?></a></h3>
                            <div class="article-meta">
                                <span class="meta-item"><span class="dashicons dashicons-calendar"></span> <?php echo esc_html($a['date']); ?></span>
                                <span class="meta-item"><span class="dashicons dashicons-visibility"></span> <?php echo esc_html($a['status_label']); ?></span>
                            </div>
                            <div class="seo-progress">
                                <div class="progress-bar-container"><div class="progress-bar score-<?php echo esc_attr($a['score_class']); ?>" style="width:<?php echo esc_attr(min($a['score'], 100)); ?>%"></div></div>
                                <div class="progress-percentage"><?php echo esc_html($a['score']); ?>%</div>
                            </div>
                            <?php if (!empty($a['issues'])): ?>
                            <div class="article-issues">
                                <div class="issues-label"><span class="dashicons dashicons-warning"></span> 要改善項目</div>
                                <ul class="issues-list"><?php foreach (array_slice($a['issues'], 0, 3) as $i): ?><li><?php echo esc_html($i); ?></li><?php endforeach; ?></ul>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="article-footer">
                            <a href="<?php echo get_edit_post_link($a['id']); ?>" class="hrs-button hrs-button-small hrs-button-primary"><span class="dashicons dashicons-edit"></span> 編集</a>
                            <?php if ($a['status'] === 'publish'): ?><a href="<?php echo get_permalink($a['id']); ?>" class="hrs-button hrs-button-small" target="_blank"><span class="dashicons dashicons-visibility"></span> 表示</a><?php endif; ?>
                            <button type="button" class="hrs-button hrs-button-small analyze-article" data-post-id="<?php echo esc_attr($a['id']); ?>"><span class="dashicons dashicons-chart-bar"></span> 再分析</button>
                            <!-- ★【v7.1.0】自動最適化ボタン -->
                            <button type="button" class="hrs-button hrs-button-small hrs-button-optimize optimize-article" data-post-id="<?php echo esc_attr($a['id']); ?>" data-hotel="<?php echo esc_attr(get_post_meta($a['id'], '_hrs_hotel_name', true) ?: $a['title']); ?>"><span class="dashicons dashicons-admin-generic"></span> 自動最適化</button>
                            <a href="<?php echo esc_url($this->get_regenerate_url($a['id'], $a)); ?>" class="hrs-button hrs-button-small hrs-button-regenerate"><span class="dashicons dashicons-update"></span> 再生成</a>
                            <button type="button" class="hrs-button hrs-button-small hrs-button-danger delete-article" data-post-id="<?php echo esc_attr($a['id']); ?>" data-title="<?php echo esc_attr($a['title']); ?>"><span class="dashicons dashicons-trash"></span> 削除</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php $this->render_pagination($pagination); ?>
                
                <?php else: ?>
                <div class="hrs-empty-state"><p>記事がありません</p></div>
                <?php endif; ?>
            </div>
            
            <style>
            .hrs-button-regenerate {
                background: #f59e0b !important;
                color: #fff !important;
                border-color: #f59e0b !important;
            }
            .hrs-button-regenerate:hover {
                background: #d97706 !important;
                border-color: #d97706 !important;
            }
            .bulk-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: nowrap;
            }
            .select-all-label {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                cursor: pointer;
                font-weight: 500;
                padding: 6px 12px;
                background: #f0f0f1;
                border-radius: 4px;
                transition: background 0.2s;
                white-space: nowrap;
            }
            .select-all-label:hover {
                background: #e0e0e1;
            }
            .select-all-label input[type="checkbox"] {
                width: 16px;
                height: 16px;
                margin: 0;
                cursor: pointer;
            }
            .selected-count {
                font-size: 13px;
                color: #50575e;
                font-weight: 500;
                white-space: nowrap;
            }
            .selected-count:not(:empty) {
                padding: 4px 8px;
                background: #ddd;
                border-radius: 4px;
            }
            .hrs-section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 15px;
            }
            </style>
            <?php
        }

        private function render_pagination($pagination) {
            if ($pagination['total_pages'] <= 1) return;
            
            $base_url = remove_query_arg('paged');
            ?>
            <div class="hrs-pagination">
                <div class="pagination-info">
                    <?php echo $pagination['total']; ?>件中 <?php echo $pagination['per_page']; ?>件表示 / 
                    ページ <?php echo $pagination['current']; ?> / <?php echo $pagination['total_pages']; ?>
                </div>
                <div class="pagination-links">
                    <?php if ($pagination['current'] > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>" class="pagination-link first">« 最初</a>
                    <a href="<?php echo esc_url(add_query_arg('paged', $pagination['current'] - 1, $base_url)); ?>" class="pagination-link prev">‹ 前へ</a>
                    <?php endif; ?>
                    
                    <?php
                    $range = 2;
                    $start_page = max(1, $pagination['current'] - $range);
                    $end_page = min($pagination['total_pages'], $pagination['current'] + $range);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>" class="pagination-link <?php echo $i === $pagination['current'] ? 'current' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['current'] < $pagination['total_pages']): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $pagination['current'] + 1, $base_url)); ?>" class="pagination-link next">次へ ›</a>
                    <a href="<?php echo esc_url(add_query_arg('paged', $pagination['total_pages'], $base_url)); ?>" class="pagination-link last">最後 »</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        /**
         * 再生成URL取得
         */
        private function get_regenerate_url($post_id, $article_data) {
            $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
            if (empty($hotel_name)) {
                $hotel_name = $article_data['title'] ?? '';
            }
            
            $weak_points = array();
            if (($article_data['h_score'] ?? 0) < 50) {
                $weak_points[] = array('axis' => 'H', 'category' => 'general');
            }
            if (($article_data['q_score'] ?? 0) < 50) {
                $weak_points[] = array('axis' => 'Q', 'category' => 'general');
            }
            if (($article_data['c_score'] ?? 0) < 50) {
                $weak_points[] = array('axis' => 'C', 'category' => 'general');
            }
            
            $url = admin_url('admin.php?page=5d-review-builder-manual');
            $url .= '&regenerate=' . $post_id;
            $url .= '&hotel=' . urlencode($hotel_name);
            if (!empty($weak_points)) {
                $url .= '&weak_points=' . urlencode(json_encode($weak_points));
            }
            
            return $url;
        }

        /**
         * ★【v7.1.0】自動最適化のCSS
         */
        private function render_optimize_styles() {
            ?>
            <style>
            /* 自動最適化ボタン */
            .hrs-button-optimize {
                background: #8b5cf6 !important;
                color: #fff !important;
                border-color: #8b5cf6 !important;
            }
            .hrs-button-optimize:hover {
                background: #7c3aed !important;
                border-color: #7c3aed !important;
            }
            /* モーダル */
            .hrs-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .hrs-modal-content {
                background: #fff;
                border-radius: 12px;
                width: 90%;
                max-width: 600px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .hrs-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #e5e7eb;
            }
            .hrs-modal-header h3 {
                margin: 0;
                font-size: 16px;
            }
            .hrs-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #6b7280;
                padding: 0 4px;
            }
            .hrs-modal-close:hover { color: #111; }
            .hrs-modal-body {
                padding: 20px;
                overflow-y: auto;
                flex: 1;
            }
            .hrs-modal-footer {
                padding: 12px 20px;
                border-top: 1px solid #e5e7eb;
                display: flex;
                gap: 8px;
                justify-content: flex-end;
            }
            /* 最適化結果の表示 */
            .optimize-change-item {
                padding: 8px 12px;
                margin-bottom: 6px;
                border-radius: 6px;
                font-size: 13px;
                border-left: 4px solid;
            }
            .optimize-change-item.high { background: #fef2f2; border-color: #ef4444; }
            .optimize-change-item.medium { background: #fffbeb; border-color: #f59e0b; }
            .optimize-change-item.low { background: #f0fdf4; border-color: #22c55e; }
            .optimize-change-param { font-weight: 600; }
            .optimize-change-arrow { color: #6b7280; margin: 0 4px; }
            .optimize-change-reason { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }
            .optimize-no-changes {
                text-align: center;
                padding: 30px;
                color: #6b7280;
            }
            .optimize-no-changes .dashicons {
                font-size: 40px;
                width: 40px;
                height: 40px;
                color: #22c55e;
            }
            .optimize-score-info {
                background: #f8fafc;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 12px;
                font-size: 14px;
            }
            </style>
            <?php
        }

        /**
         * ★【v7.1.0】自動最適化のJS
         */
        private function render_optimize_scripts() {
            $nonce = wp_create_nonce('hrs_hqc_nonce');
            ?>
            <script>
            jQuery(document).ready(function($) {
                var optimizeNonce = '<?php echo $nonce; ?>';
                var currentOptimizedSettings = null;
                var currentOptimizePostId = null;

                // 自動最適化ボタン
                $(document).on('click', '.optimize-article', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var postId = btn.data('post-id');
                    var hotelName = btn.data('hotel');

                    currentOptimizePostId = postId;
                    btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 分析中...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrs_hqc_auto_optimize',
                            nonce: optimizeNonce,
                            post_id: postId
                        },
                        success: function(res) {
                            if (res.success && res.data) {
                                currentOptimizedSettings = res.data.settings;
                                showOptimizeModal(res.data, hotelName);
                            } else {
                                alert('エラー: ' + (res.data?.message || '最適化に失敗しました'));
                            }
                        },
                        error: function(xhr) {
                            alert('通信エラー: ' + xhr.status);
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> 自動最適化');
                        }
                    });
                });

                function showOptimizeModal(data, hotelName) {
                    var html = '';

                    // スコア情報
                    html += '<div class="optimize-score-info">';
                    html += '📊 現在のスコア: <strong>' + data.original_score + '点</strong>';
                    html += ' → <strong>' + data.change_count + '件</strong>のパラメータを調整';
                    html += '</div>';

                    if (data.changes && data.changes.length > 0) {
                        var priorityIcons = { high: '🔴', medium: '🟡', low: '🟢' };

                        data.changes.forEach(function(c) {
                            var icon = priorityIcons[c.priority] || '⚪';
                            var fromStr = Array.isArray(c.from) ? c.from.join(', ') : (c.from || '未設定');
                            var toStr = Array.isArray(c.to) ? c.to.join(', ') : c.to;

                            html += '<div class="optimize-change-item ' + c.priority + '">';
                            html += icon + ' <span class="optimize-change-param">' + c.param + '</span>';
                            html += '<span class="optimize-change-arrow">→</span>';
                            html += '<strong>' + fromStr + '</strong> → <strong>' + toStr + '</strong>';
                            if (c.reason) {
                                html += '<span class="optimize-change-reason">' + c.reason + '</span>';
                            }
                            html += '</div>';
                        });

                        $('#hrs-optimize-apply').show().data('hotel', hotelName);
                    } else {
                        html += '<div class="optimize-no-changes">';
                        html += '<span class="dashicons dashicons-yes-alt"></span>';
                        html += '<p>最適化の必要はありません。<br>現在のパラメータで十分なスコアです。</p>';
                        html += '</div>';
                        $('#hrs-optimize-apply').hide();
                    }

                    $('#hrs-optimize-result').html(html);
                    $('#hrs-optimize-modal').fadeIn(200);
                }

                // モーダル閉じる
                $(document).on('click', '.hrs-modal-close, .hrs-modal-close-btn', function() {
                    $('#hrs-optimize-modal').fadeOut(200);
                });
                $(document).on('click', '#hrs-optimize-modal', function(e) {
                    if (e.target === this) $(this).fadeOut(200);
                });

                // 最適化設定でキューに追加
                $(document).on('click', '#hrs-optimize-apply', function() {
                    var btn = $(this);
                    var hotelName = btn.data('hotel');

                    if (!currentOptimizedSettings || !hotelName) {
                        alert('最適化データがありません');
                        return;
                    }

                    btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 追加中...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrs_add_to_queue',
                            nonce: optimizeNonce,
                            hotel_name: hotelName,
                            settings: JSON.stringify(currentOptimizedSettings)
                        },
                        success: function(res) {
                            if (res.success) {
                                alert('✅ 「' + hotelName + '」を最適化パラメータでキューに追加しました');
                                $('#hrs-optimize-modal').fadeOut(200);
                            } else {
                                alert('エラー: ' + (res.data?.message || 'キュー追加に失敗'));
                            }
                        },
                        error: function(xhr) {
                            alert('通信エラー: ' + xhr.status);
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> この設定でキューに追加');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

}