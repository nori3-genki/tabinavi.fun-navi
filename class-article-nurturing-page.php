<?php
/**
 * 記事育成ページクラス（HQC Analyzer統合版・AJAX Registry対応）
 * 
 * 記事のHQCスコア詳細分析と改善提案
 * 弱点項目の詳細表示、再生成への連携
 * 
 * @package HRS
 * @version 5.1.1-AJAX-REGISTRY
 * 
 * 変更履歴:
 * - 5.0.0: HQC Analyzer統合
 * - 5.1.0: weak_pointsをURLに追加、fair記事にも再生成ボタン
 * - 5.1.1: AJAX Registry対応、wp_ajax ハンドラーを削除
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Article_Nurturing_Page {

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * ページスラッグ
     */
    private $page_slug = 'hrs-article-nurturing';

    /**
     * 分析結果キャッシュ
     */
    private $analysis_cache = array();

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        // ❌ 削除: add_action('wp_ajax_hrs_analyze_article', ...)
        // ❌ 削除: add_action('wp_ajax_hrs_optimize_article', ...)
        // ❌ 削除: add_action('wp_ajax_hrs_bulk_analyze', ...)
        // AJAX ハンドラーは HRS_Ajax_Registry が管理する
    }

    /**
     * メニューページ追加
     */
    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=' . $this->post_type,
            '記事育成',
            '📈 記事育成',
            'edit_posts',
            $this->page_slug,
            array($this, 'render_page')
        );
    }

    /**
     * アセット読み込み
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        wp_enqueue_script('jquery');
    }

    /**
     * ページ描画
     */
    public function render_page() {
        $articles = $this->get_articles_with_scores();
        $stats = $this->get_overall_stats($articles);
        ?>
        <div class="wrap hrs-nurturing-page">
            <h1>📈 記事育成ダッシュボード</h1>
            
            <style>
                .hrs-nurturing-page { max-width: 1400px; }
                .hrs-stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin: 20px 0; }
                .hrs-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
                .hrs-stat-value { font-size: 32px; font-weight: bold; color: #667eea; }
                .hrs-stat-label { color: #666; margin-top: 5px; font-size: 13px; }
                .hrs-filter-bar { background: #fff; padding: 15px 20px; border-radius: 8px; margin: 20px 0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
                .hrs-filter-bar select, .hrs-filter-bar input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
                .hrs-articles-table { width: 100%; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-collapse: collapse; }
                .hrs-articles-table th, .hrs-articles-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
                .hrs-articles-table th { background: #f8f9fa; font-weight: 600; }
                .hrs-articles-table tr:hover { background: #f8f9fa; }
                .hrs-score { display: inline-block; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 13px; }
                .hrs-score-excellent { background: #dcfce7; color: #166534; }
                .hrs-score-good { background: #fef9c3; color: #854d0e; }
                .hrs-score-fair { background: #fed7aa; color: #9a3412; }
                .hrs-score-poor { background: #fecaca; color: #991b1b; }
                .hrs-axis-scores { display: flex; gap: 8px; flex-wrap: wrap; }
                .hrs-axis-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
                .hrs-axis-h { background: #dbeafe; color: #1e40af; }
                .hrs-axis-q { background: #fce7f3; color: #9d174d; }
                .hrs-axis-c { background: #ffedd5; color: #9a3412; }
                .hrs-issues-list { margin: 0; padding: 0; list-style: none; }
                .hrs-issues-list li { padding: 2px 0; font-size: 11px; color: #666; }
                .hrs-issues-list li.issue-h { color: #1e40af; }
                .hrs-issues-list li.issue-q { color: #9d174d; }
                .hrs-issues-list li.issue-c { color: #9a3412; }
                .hrs-action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin: 2px; text-decoration: none; display: inline-block; }
                .hrs-action-btn-primary { background: #667eea; color: #fff; }
                .hrs-action-btn-primary:hover { background: #5a6fd6; }
                .hrs-action-btn-warning { background: #f59e0b; color: #fff; }
                .hrs-action-btn-warning:hover { background: #d97706; color: #fff; }
                .hrs-action-btn-danger { background: #ef4444; color: #fff; }
                .hrs-action-btn-danger:hover { background: #dc2626; color: #fff; }
                .hrs-bulk-actions { margin: 20px 0; display: flex; gap: 10px; }
                .hrs-yoast-score { display: inline-flex; align-items: center; gap: 5px; }
                .hrs-yoast-dot { width: 12px; height: 12px; border-radius: 50%; }
                .hrs-yoast-green { background: #7ad03a; }
                .hrs-yoast-orange { background: #ee7c1b; }
                .hrs-yoast-red { background: #dc3232; }
                .hrs-yoast-gray { background: #ccc; }
                .hrs-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; }
                .hrs-modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); background: #fff; padding: 30px; border-radius: 12px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; }
                .hrs-modal-close { margin-top: 20px; padding: 10px 20px; background: #667eea; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
                .hrs-detail-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 15px 0; }
                .hrs-detail-card { padding: 15px; border-radius: 8px; }
                .hrs-detail-card h4 { margin: 0 0 10px; font-size: 14px; }
                .hrs-detail-card-h { background: #dbeafe; }
                .hrs-detail-card-q { background: #fce7f3; }
                .hrs-detail-card-c { background: #ffedd5; }
                .hrs-detail-item { font-size: 12px; padding: 3px 0; display: flex; justify-content: space-between; }
                .hrs-detail-item.weak { color: #dc2626; font-weight: bold; }
                .hrs-fix-plan { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin-top: 15px; }
                .hrs-fix-plan h4 { margin: 0 0 10px; color: #991b1b; }
                .hrs-fix-plan ul { margin: 0; padding-left: 20px; }
                .hrs-fix-plan li { padding: 3px 0; font-size: 13px; }
                .hrs-regenerate-btn { display: inline-block; margin-top: 15px; padding: 12px 24px; background: #ef4444; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
                .hrs-regenerate-btn:hover { background: #dc2626; color: #fff; }
            </style>

            <!-- 統計カード -->
            <div class="hrs-stats-grid">
                <div class="hrs-stat-card">
                    <div class="hrs-stat-value"><?php echo esc_html($stats['total']); ?></div>
                    <div class="hrs-stat-label">総記事数</div>
                </div>
                <div class="hrs-stat-card">
                    <div class="hrs-stat-value" style="color: #22c55e;"><?php echo esc_html($stats['excellent']); ?></div>
                    <div class="hrs-stat-label">優秀（75点↑）</div>
                </div>
                <div class="hrs-stat-card">
                    <div class="hrs-stat-value" style="color: #eab308;"><?php echo esc_html($stats['good']); ?></div>
                    <div class="hrs-stat-label">良好（50-74点）</div>
                </div>
                <div class="hrs-stat-card">
                    <div class="hrs-stat-value" style="color: #ef4444;"><?php echo esc_html($stats['needs_improvement']); ?></div>
                    <div class="hrs-stat-label">要改善（50点↓）</div>
                </div>
                <div class="hrs-stat-card">
                    <div class="hrs-stat-value" style="color: #667eea;"><?php echo esc_html(number_format($stats['avg_hqc'], 1)); ?></div>
                    <div class="hrs-stat-label">平均スコア</div>
                </div>
            </div>

            <!-- フィルター -->
            <div class="hrs-filter-bar">
                <label>
                    <strong>HQCスコア:</strong>
                    <select id="filter-hqc">
                        <option value="">すべて</option>
                        <option value="excellent">🌟 優秀（75点↑）</option>
                        <option value="good">✅ 良好（50-74点）</option>
                        <option value="poor">❌ 要改善（50点↓）</option>
                    </select>
                </label>
                <label>
                    <strong>弱点軸:</strong>
                    <select id="filter-weak">
                        <option value="">すべて</option>
                        <option value="h">H層が弱い</option>
                        <option value="q">Q層が弱い</option>
                        <option value="c">C層が弱い</option>
                    </select>
                </label>
                <label>
                    <strong>検索:</strong>
                    <input type="text" id="filter-search" placeholder="ホテル名で検索">
                </label>
                <button class="hrs-action-btn hrs-action-btn-primary" onclick="location.reload()">🔄 更新</button>
            </div>

            <!-- 一括アクション -->
            <div class="hrs-bulk-actions">
                <button class="hrs-action-btn hrs-action-btn-primary" id="btn-bulk-analyze">📊 選択記事を再分析</button>
            </div>

            <!-- 記事一覧 -->
            <table class="hrs-articles-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>記事タイトル</th>
                        <th>総合</th>
                        <th>H / Q / C</th>
                        <th>弱点項目</th>
                        <th>更新日</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                    <tr data-post-id="<?php echo esc_attr($article['id']); ?>" 
                        data-hqc-level="<?php echo esc_attr($article['hqc_level']); ?>"
                        data-weak-axis="<?php echo esc_attr($article['weak_axis']); ?>">
                        <td><input type="checkbox" class="article-select" value="<?php echo esc_attr($article['id']); ?>"></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($article['id'])); ?>" target="_blank">
                                <?php echo esc_html(mb_substr($article['title'], 0, 30)); ?><?php echo mb_strlen($article['title']) > 30 ? '...' : ''; ?>
                            </a>
                        </td>
                        <td>
                            <span class="hrs-score hrs-score-<?php echo esc_attr($article['hqc_level']); ?>">
                                <?php echo esc_html(number_format($article['total_score'], 1)); ?>
                            </span>
                        </td>
                        <td>
                            <div class="hrs-axis-scores">
                                <span class="hrs-axis-badge hrs-axis-h">H:<?php echo esc_html(number_format($article['h_score'], 0)); ?></span>
                                <span class="hrs-axis-badge hrs-axis-q">Q:<?php echo esc_html(number_format($article['q_score'], 0)); ?></span>
                                <span class="hrs-axis-badge hrs-axis-c">C:<?php echo esc_html(number_format($article['c_score'], 0)); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($article['issues'])): ?>
                            <ul class="hrs-issues-list">
                                <?php foreach (array_slice($article['issues'], 0, 3) as $issue): 
                                    $issue_class = '';
                                    if (strpos($issue, 'H層') === 0) $issue_class = 'issue-h';
                                    elseif (strpos($issue, 'Q層') === 0) $issue_class = 'issue-q';
                                    elseif (strpos($issue, 'C層') === 0) $issue_class = 'issue-c';
                                ?>
                                <li class="<?php echo esc_attr($issue_class); ?>"><?php echo esc_html($issue); ?></li>
                                <?php endforeach; ?>
                                <?php if (count($article['issues']) > 3): ?>
                                <li style="color:#999;">他<?php echo count($article['issues']) - 3; ?>件...</li>
                                <?php endif; ?>
                            </ul>
                            <?php else: ?>
                            <span style="color: #22c55e;">✓ 問題なし</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($article['date']); ?></td>
                        <td>
                            <button class="hrs-action-btn hrs-action-btn-primary btn-analyze" data-post-id="<?php echo esc_attr($article['id']); ?>">詳細</button>
                            <?php if ($article['hqc_level'] === 'poor'): ?>
                            <a href="<?php echo esc_url($article['regenerate_url']); ?>" class="hrs-action-btn hrs-action-btn-danger">再生成</a>
                            <?php elseif ($article['hqc_level'] === 'good' && $article['total_score'] < 70): ?>
                            <a href="<?php echo esc_url($article['regenerate_url']); ?>" class="hrs-action-btn hrs-action-btn-warning">改善</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 分析モーダル -->
            <div id="analysis-modal" class="hrs-modal">
                <div class="hrs-modal-content">
                    <h2 id="modal-title">📊 記事分析結果</h2>
                    <div id="modal-content"></div>
                    <button class="hrs-modal-close" onclick="document.getElementById('analysis-modal').style.display='none'">閉じる</button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // フィルター: HQCスコア
            $('#filter-hqc').on('change', function() {
                filterTable();
            });

            // フィルター: 弱点軸
            $('#filter-weak').on('change', function() {
                filterTable();
            });

            // 検索
            $('#filter-search').on('input', function() {
                filterTable();
            });

            function filterTable() {
                var hqcFilter = $('#filter-hqc').val();
                var weakFilter = $('#filter-weak').val();
                var search = $('#filter-search').val().toLowerCase();
                
                $('.hrs-articles-table tbody tr').each(function() {
                    var show = true;
                    var $row = $(this);
                    
                    if (hqcFilter && $row.data('hqc-level') !== hqcFilter) show = false;
                    if (weakFilter && $row.data('weak-axis').indexOf(weakFilter) === -1) show = false;
                    if (search) {
                        var title = $row.find('td:eq(1)').text().toLowerCase();
                        if (title.indexOf(search) === -1) show = false;
                    }
                    
                    $row.toggle(show);
                });
            }

            // 全選択
            $('#select-all').on('change', function() {
                $('.article-select:visible').prop('checked', $(this).is(':checked'));
            });

            // 詳細分析
            $('.btn-analyze').on('click', function() {
                var postId = $(this).data('post-id');
                showAnalysis(postId);
            });

            function showAnalysis(postId) {
                $('#modal-content').html('<p>分析中...</p>');
                $('#analysis-modal').show();
                
                $.post(ajaxurl, {
                    action: 'hrs_analyze_article',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('hrs_analyze_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var d = response.data;
                        var html = '';
                        
                        // 総合スコア
                        html += '<div style="text-align:center; padding:20px; background:#f8f9fa; border-radius:8px; margin-bottom:20px;">';
                        html += '<div style="font-size:48px; font-weight:bold; color:#667eea;">' + d.total_score.toFixed(1) + '</div>';
                        html += '<div style="color:#666;">総合スコア</div>';
                        if (d.ai_penalty > 0) {
                            html += '<div style="color:#ef4444; font-size:12px; margin-top:5px;">AI表現ペナルティ: -' + d.ai_penalty.toFixed(1) + '</div>';
                        }
                        html += '</div>';
                        
                        // 軸別スコア
                        html += '<div class="hrs-detail-grid">';
                        
                        // H層
                        html += '<div class="hrs-detail-card hrs-detail-card-h">';
                        html += '<h4>H層: ' + d.h_score.toFixed(1) + '点</h4>';
                        if (d.h_details) {
                            for (var key in d.h_details) {
                                var item = d.h_details[key];
                                var ratio = item.score / item.max;
                                var cls = ratio < 0.5 ? 'weak' : '';
                                html += '<div class="hrs-detail-item ' + cls + '">';
                                html += '<span>' + getLabel('h', key) + '</span>';
                                html += '<span>' + item.paragraph_count + '/' + item.target + '</span>';
                                html += '</div>';
                            }
                        }
                        html += '</div>';
                        
                        // Q層
                        html += '<div class="hrs-detail-card hrs-detail-card-q">';
                        html += '<h4>Q層: ' + d.q_score.toFixed(1) + '点</h4>';
                        if (d.q_details) {
                            for (var key in d.q_details) {
                                var item = d.q_details[key];
                                var ratio = item.score / item.max;
                                var cls = ratio < 0.5 ? 'weak' : '';
                                var count = item.paragraph_count || item.found || 0;
                                var target = item.target || item.min_categories || 0;
                                html += '<div class="hrs-detail-item ' + cls + '">';
                                html += '<span>' + getLabel('q', key) + '</span>';
                                html += '<span>' + count + '/' + target + '</span>';
                                html += '</div>';
                            }
                        }
                        html += '</div>';
                        
                        // C層
                        html += '<div class="hrs-detail-card hrs-detail-card-c">';
                        html += '<h4>C層: ' + d.c_score.toFixed(1) + '点</h4>';
                        if (d.c_details) {
                            for (var key in d.c_details) {
                                var item = d.c_details[key];
                                var ratio = item.score / item.max;
                                var cls = ratio < 0.5 ? 'weak' : '';
                                var count = item.count !== undefined ? item.count : (item.found ? '✓' : '✗');
                                var target = item.target || '';
                                html += '<div class="hrs-detail-item ' + cls + '">';
                                html += '<span>' + getLabel('c', key) + '</span>';
                                html += '<span>' + count + (target ? '/' + target : '') + '</span>';
                                html += '</div>';
                            }
                        }
                        html += '</div>';
                        
                        html += '</div>';
                        
                        // 改善計画
                        if (d.recommendations && d.recommendations.length > 0) {
                            html += '<div class="hrs-fix-plan">';
                            html += '<h4>📝 改善計画</h4>';
                            html += '<ul>';
                            d.recommendations.forEach(function(rec) {
                                var priority = rec.priority === 'high' ? '🔴' : '🟡';
                                html += '<li>' + priority + ' ' + rec.message + '</li>';
                            });
                            html += '</ul>';
                            html += '<a href="' + d.regenerate_url + '" class="hrs-regenerate-btn">🔄 この改善計画で再生成</a>';
                            html += '</div>';
                        }
                        
                        $('#modal-content').html(html);
                    } else {
                        $('#modal-content').html('<p style="color:red;">分析に失敗しました</p>');
                    }
                });
            }
            
            function getLabel(axis, key) {
                var labels = {
                    h: {
                        timeline: '時系列',
                        emotion: '感情',
                        purpose: '目的',
                        scene: 'シーン',
                        first_person: '一人称'
                    },
                    q: {
                        objective_data: '客観データ',
                        five_senses: '五感',
                        cuisine: '料理',
                        facility: '施設'
                    },
                    c: {
                        h2_headings: 'H2見出し',
                        h3_headings: 'H3見出し',
                        keyphrase_density: 'KW密度',
                        keyphrase_intro: '冒頭KW',
                        word_count: '文字数',
                        cta: 'CTA',
                        affiliate_links: '予約リンク',
                        price_info: '価格情報',
                        comparison: '比較',
                        faq: 'FAQ',
                        pros_cons: 'メリデメ',
                        target_audience: 'ターゲット',
                        seasonal_info: '季節情報',
                        access_info: 'アクセス',
                        reviews: '口コミ'
                    }
                };
                return labels[axis] && labels[axis][key] ? labels[axis][key] : key;
            }

            // 一括分析
            $('#btn-bulk-analyze').on('click', function() {
                var ids = [];
                $('.article-select:checked').each(function() {
                    ids.push($(this).val());
                });
                if (ids.length === 0) {
                    alert('記事を選択してください');
                    return;
                }
                alert('選択した' + ids.length + '件の記事を分析します（機能実装中）');
            });
        });
        </script>
        <?php
    }

    /**
     * 再生成URL取得（弱点データ付き）
     */
    private function get_regenerate_url($post_id) {
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            $post = get_post($post_id);
            $hotel_name = $post ? $post->post_title : '';
        }
        
        // 分析結果から弱点を取得
        $weak_points = array();
        if (isset($this->analysis_cache[$post_id]['weak_points'])) {
            $weak_points = $this->analysis_cache[$post_id]['weak_points'];
        } else {
            // キャッシュにない場合は再分析
            $post = get_post($post_id);
            if ($post && class_exists('HRS_HQC_Analyzer')) {
                $analyzer = new HRS_HQC_Analyzer();
                $result = $analyzer->analyze($post->post_content, array('hotel_name' => $hotel_name));
                $weak_points = $result['weak_points'] ?? array();
            }
        }
        
        $url = admin_url('admin.php?page=5d-review-builder-manual&regenerate=' . $post_id . '&hotel=' . urlencode($hotel_name));
        
        if (!empty($weak_points)) {
            $url .= '&weak_points=' . urlencode(json_encode($weak_points));
        }
        
        return $url;
    }

    /**
     * スコア付き記事一覧取得
     */
    private function get_articles_with_scores() {
        $posts = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
        ));

        $articles = array();
        foreach ($posts as $post) {
            $analysis = $this->analyze_post($post);
            $issues = $this->extract_issues($analysis);
            $weak_axis = $this->detect_weak_axis($analysis);

            $articles[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'total_score' => $analysis['total_score'],
                'h_score' => $analysis['h_score'],
                'q_score' => $analysis['q_score'],
                'c_score' => $analysis['c_score'],
                'hqc_level' => $this->get_hqc_level($analysis['total_score']),
                'weak_axis' => $weak_axis,
                'issues' => $issues,
                'date' => get_the_modified_date('Y/m/d', $post->ID),
                'regenerate_url' => $this->get_regenerate_url($post->ID),
            );
        }

        return $articles;
    }

    /**
     * 記事を分析
     */
    private function analyze_post($post) {
        if (class_exists('HRS_HQC_Analyzer')) {
            $analyzer = new HRS_HQC_Analyzer();
            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true) ?: $post->post_title;
            $result = $analyzer->analyze($post->post_content, array('hotel_name' => $hotel_name));
            $this->analysis_cache[$post->ID] = $result;
            return $result;
        }
        
        // フォールバック
        return array(
            'total_score' => 50,
            'h_score' => 50,
            'q_score' => 50,
            'c_score' => 50,
            'h_details' => array(),
            'q_details' => array(),
            'c_details' => array(),
            'weak_points' => array(),
            'recommendations' => array(),
        );
    }

    /**
     * 弱点軸を検出
     */
    private function detect_weak_axis($analysis) {
        $axes = array();
        if ($analysis['h_score'] < 50) $axes[] = 'h';
        if ($analysis['q_score'] < 50) $axes[] = 'q';
        if ($analysis['c_score'] < 50) $axes[] = 'c';
        return implode(',', $axes);
    }

    /**
     * 問題点抽出
     */
    private function extract_issues($analysis) {
        $issues = array();
        
        // H層
        if (isset($analysis['h_details'])) {
            $labels = array(
                'timeline' => '時系列',
                'emotion' => '感情',
                'purpose' => '目的',
                'scene' => 'シーン',
                'first_person' => '一人称',
            );
            foreach ($analysis['h_details'] as $key => $detail) {
                if (isset($detail['score'], $detail['max']) && $detail['max'] > 0) {
                    if ($detail['score'] / $detail['max'] < 0.5) {
                        $label = $labels[$key] ?? $key;
                        $issues[] = "H層: {$label} ({$detail['paragraph_count']}/{$detail['target']})";
                    }
                }
            }
        }
        
        // Q層
        if (isset($analysis['q_details'])) {
            $labels = array(
                'objective_data' => '客観データ',
                'five_senses' => '五感',
                'cuisine' => '料理',
                'facility' => '施設',
            );
            foreach ($analysis['q_details'] as $key => $detail) {
                if (isset($detail['score'], $detail['max']) && $detail['max'] > 0) {
                    if ($detail['score'] / $detail['max'] < 0.5) {
                        $label = $labels[$key] ?? $key;
                        $count = $detail['paragraph_count'] ?? $detail['found'] ?? 0;
                        $target = $detail['target'] ?? 0;
                        $issues[] = "Q層: {$label} ({$count}/{$target})";
                    }
                }
            }
        }
        
        // C層
        if (isset($analysis['c_details'])) {
            $labels = array(
                'cta' => 'CTA',
                'faq' => 'FAQ',
                'pros_cons' => 'メリデメ',
                'target_audience' => 'ターゲット',
                'price_info' => '価格',
                'access_info' => 'アクセス',
            );
            foreach ($analysis['c_details'] as $key => $detail) {
                if (isset($detail['score'], $detail['max']) && $detail['max'] > 0) {
                    if ($detail['score'] / $detail['max'] < 0.5) {
                        $label = $labels[$key] ?? $key;
                        $count = $detail['count'] ?? 0;
                        $target = $detail['target'] ?? 0;
                        $issues[] = "C層: {$label} ({$count}/{$target})";
                    }
                }
            }
        }
        
        return $issues;
    }

    /**
     * HQCレベル判定
     */
    private function get_hqc_level($score) {
        if ($score >= 75) return 'excellent';
        if ($score >= 50) return 'good';
        return 'poor';
    }

    /**
     * 全体統計
     */
    private function get_overall_stats($articles) {
        $total = count($articles);
        $excellent = 0;
        $good = 0;
        $needs_improvement = 0;
        $total_hqc = 0;

        foreach ($articles as $article) {
            $total_hqc += $article['total_score'];
            if ($article['hqc_level'] === 'excellent') {
                $excellent++;
            } elseif ($article['hqc_level'] === 'good') {
                $good++;
            } else {
                $needs_improvement++;
            }
        }

        return array(
            'total' => $total,
            'excellent' => $excellent,
            'good' => $good,
            'needs_improvement' => $needs_improvement,
            'avg_hqc' => $total > 0 ? $total_hqc / $total : 0,
        );
    }

    /**
     * AJAX: 記事分析
     * 注意: このメソッドは HRS_Ajax_Registry から呼ばれる
     */
    public function ajax_analyze_article() {
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => '記事が見つかりません'));
        }

        $analysis = $this->analyze_post($post);
        $analysis['regenerate_url'] = $this->get_regenerate_url($post_id);

        wp_send_json_success($analysis);
    }

    /**
     * AJAX: 記事最適化
     * 注意: このメソッドは HRS_Ajax_Registry から呼ばれる
     */
    public function ajax_optimize_article() {
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => '記事が見つかりません'));
        }

        $optimized = false;

        if (class_exists('HRS_Yoast_SEO_Optimizer')) {
            $optimizer = new HRS_Yoast_SEO_Optimizer();
            $optimizer->optimize_yoast_seo($post_id, $post);
            $optimized = true;
        }

        if (class_exists('HRS_Heading_Optimizer')) {
            $heading_optimizer = new HRS_Heading_Optimizer();
            $heading_optimizer->optimize($post_id, $post);
            $optimized = true;
        }

        if ($optimized) {
            wp_send_json_success(array('message' => '最適化が完了しました'));
        } else {
            wp_send_json_error(array('message' => '最適化クラスが見つかりません'));
        }
    }

    /**
     * AJAX: 一括分析
     * 注意: このメソッドは HRS_Ajax_Registry から呼ばれる
     */
    public function ajax_bulk_analyze() {
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        
        if (empty($post_ids)) {
            wp_send_json_error(array('message' => '記事が選択されていません'));
        }

        $results = array();
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $analysis = $this->analyze_post($post);
                $results[$post_id] = array(
                    'title' => $post->post_title,
                    'total_score' => $analysis['total_score'],
                );
            }
        }

        wp_send_json_success(array('results' => $results));
    }
}

// 初期化
new HRS_Article_Nurturing_Page();