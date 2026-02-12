<?php
/**
 * HRS Performance Admin Page
 * パフォーマンス管理画面
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Performance_Admin_Page {
    
    /** @var HRS_Performance_Tracker */
    private $tracker;
    
    /** @var HRS_CSV_Importer */
    private $importer;
    
    /** @var HRS_Performance_HQC_Bridge */
    private $hqc_bridge;
    
    /** @var string ページスラッグ */
    private $page_slug = 'hrs-performance';
    
    /** @var int 1ページあたりの表示件数 */
    private $per_page = 20;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_hrs_import_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_hrs_bulk_flag_update', array($this, 'handle_bulk_flag_update'));
        add_action('wp_ajax_hrs_bulk_rewrite_send', array($this, 'handle_bulk_rewrite_send'));
        add_action('wp_ajax_hrs_export_csv', array($this, 'handle_export_csv'));
        add_action('wp_ajax_hrs_send_to_rewrite', array($this, 'handle_send_to_rewrite'));
    }
    
    /**
     * クラスの初期化
     */
    private function init_classes() {
        if (!$this->tracker) {
            $this->tracker = new HRS_Performance_Tracker();
        }
        if (!$this->importer) {
            $this->importer = new HRS_CSV_Importer();
        }
        if (!$this->hqc_bridge) {
            $this->hqc_bridge = new HRS_Performance_HQC_Bridge();
        }
    }
    
    /**
     * 管理メニューに追加
     */
    public function add_menu_page() {
        add_submenu_page(
            'hrs-dashboard',
            '📊 パフォーマンス',
            '📊 パフォーマンス',
            'manage_options',
            $this->page_slug,
            array($this, 'render_page')
        );
    }
    
    /**
     * CSS/JS読み込み
     * 
     * @param string $hook 現在のページフック
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '4.4.1',
            true
        );
        
        // カスタムスタイル
        wp_add_inline_style('wp-admin', $this->get_inline_styles());
        
        // カスタムスクリプト
        wp_add_inline_script('chartjs', $this->get_inline_scripts(), 'after');
    }
    
    /**
     * メインページ描画
     */
    public function render_page() {
        $this->init_classes();
        
        // 現在のタブ
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        
        ?>
        <div class="wrap hrs-performance-wrap">
            <h1>📊 パフォーマンスダッシュボード</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->page_slug; ?>&tab=overview" 
                   class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    概要
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=articles" 
                   class="nav-tab <?php echo $current_tab === 'articles' ? 'nav-tab-active' : ''; ?>">
                    記事別データ
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=import" 
                   class="nav-tab <?php echo $current_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    CSVインポート
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=api" 
                   class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    🔗 API設定
                </a>
            </nav>
            
            <div class="hrs-performance-content">
                <?php
                switch ($current_tab) {
                    case 'articles':
                        $this->render_articles_tab();
                        break;
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'api':
                        $this->render_api_settings_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 概要タブ描画
     */
    private function render_overview_tab() {
        $summary = $this->tracker->get_summary();
        $flag_counts = $this->hqc_bridge->get_flag_counts();
        
        ?>
        <!-- サマリーカード -->
        <div class="hrs-summary-cards">
            <?php $this->render_summary_cards($summary); ?>
        </div>
        
        <!-- フラグ分布 -->
        <div class="hrs-section">
            <h2>📈 フラグ分布</h2>
            <div class="hrs-flag-distribution">
                <div class="hrs-flag-item excellent">
                    <span class="flag-count"><?php echo $flag_counts['excellent']; ?></span>
                    <span class="flag-label">優良</span>
                </div>
                <div class="hrs-flag-item normal">
                    <span class="flag-count"><?php echo $flag_counts['normal']; ?></span>
                    <span class="flag-label">普通</span>
                </div>
                <div class="hrs-flag-item poor">
                    <span class="flag-count"><?php echo $flag_counts['poor']; ?></span>
                    <span class="flag-label">要改善</span>
                </div>
            </div>
        </div>
        
        <!-- グラフ -->
        <div class="hrs-section">
            <h2>📊 推移グラフ</h2>
            <div class="hrs-chart-controls">
                <select id="hrs-chart-period">
                    <option value="7">7日間</option>
                    <option value="30" selected>30日間</option>
                    <option value="90">90日間</option>
                </select>
                <select id="hrs-chart-metric">
                    <option value="all">全指標</option>
                    <option value="avg_time_on_page">滞在時間</option>
                    <option value="bounce_rate">直帰率</option>
                    <option value="ctr">CTR</option>
                    <option value="avg_position">平均順位</option>
                </select>
            </div>
            <div class="hrs-chart-container">
                <canvas id="hrs-performance-chart"></canvas>
            </div>
        </div>
        
        <!-- HQCアクション -->
        <div class="hrs-section">
            <h2>🔧 HQCアクション</h2>
            <?php $this->render_action_buttons(); ?>
        </div>
        
        <script>
            var hrsTimeSeriesData = <?php echo json_encode($this->get_chart_data(30)); ?>;
        </script>
        <?php
    }
    
    /**
     * 記事別タブ描画
     */
    private function render_articles_tab() {
        // フィルタ取得
        $current_flag = isset($_GET['flag']) ? sanitize_text_field($_GET['flag']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'performance_score';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        
        $offset = ($paged - 1) * $this->per_page;
        
        // データ取得
        $data = $this->tracker->get_all_data(array(
            'flag'    => $current_flag,
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $this->per_page,
            'offset'  => $offset,
            'latest'  => true
        ));
        
        $total = $this->tracker->get_count($current_flag);
        $total_pages = ceil($total / $this->per_page);
        
        ?>
        <!-- フィルタ -->
        <div class="hrs-filters">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $this->page_slug; ?>">
                <input type="hidden" name="tab" value="articles">
                
                <select name="flag">
                    <option value="">全てのフラグ</option>
                    <option value="excellent" <?php selected($current_flag, 'excellent'); ?>>優良</option>
                    <option value="normal" <?php selected($current_flag, 'normal'); ?>>普通</option>
                    <option value="poor" <?php selected($current_flag, 'poor'); ?>>要改善</option>
                </select>
                
                <button type="submit" class="button">フィルタ</button>
            </form>
        </div>
        
        <!-- データテーブル -->
        <div class="hrs-data-table-wrap">
            <?php $this->render_data_table($data, $orderby, $order); ?>
        </div>
        
        <!-- ページネーション -->
        <?php if ($total_pages > 1) : ?>
        <div class="hrs-pagination">
            <?php
            $base_url = add_query_arg(array(
                'page'    => $this->page_slug,
                'tab'     => 'articles',
                'flag'    => $current_flag,
                'orderby' => $orderby,
                'order'   => $order
            ), admin_url('admin.php'));
            
            echo paginate_links(array(
                'base'      => $base_url . '&paged=%#%',
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '&laquo; 前へ',
                'next_text' => '次へ &raquo;'
            ));
            ?>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * インポートタブ描画
     */
    private function render_import_tab() {
        $import_logs = $this->importer->get_import_log(10);
        
        ?>
        <div class="hrs-import-section">
            <!-- GA4インポート -->
            <div class="hrs-import-box">
                <h3>📊 GA4データインポート</h3>
                <p>Google Analytics 4からエクスポートしたCSVをインポートします。</p>
                <p class="description">必須カラム: ページパス、平均セッション時間、直帰率</p>
                
                <form id="hrs-ga4-import-form" class="hrs-import-form">
                    <input type="hidden" name="action" value="hrs_import_csv">
                    <input type="hidden" name="type" value="ga4">
                    <?php wp_nonce_field('hrs_import_csv', 'hrs_import_nonce'); ?>
                    
                    <div class="hrs-form-row">
                        <label>CSVファイル:</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="hrs-form-row">
                        <label>データ集計日:</label>
                        <input type="date" name="data_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <button type="submit" class="button button-primary">インポート実行</button>
                </form>
                
                <div id="hrs-ga4-import-result" class="hrs-import-result"></div>
            </div>
            
            <!-- Search Consoleインポート -->
            <div class="hrs-import-box">
                <h3>🔍 Search Consoleデータインポート</h3>
                <p>Google Search Consoleからエクスポートしたページレポートをインポートします。</p>
                <p class="description">必須カラム: ページ、クリック数、表示回数、CTR、掲載順位</p>
                
                <form id="hrs-gsc-import-form" class="hrs-import-form">
                    <input type="hidden" name="action" value="hrs_import_csv">
                    <input type="hidden" name="type" value="gsc">
                    <?php wp_nonce_field('hrs_import_csv', 'hrs_import_nonce_gsc'); ?>
                    
                    <div class="hrs-form-row">
                        <label>CSVファイル:</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="hrs-form-row">
                        <label>データ集計日:</label>
                        <input type="date" name="data_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <button type="submit" class="button button-primary">インポート実行</button>
                </form>
                
                <div id="hrs-gsc-import-result" class="hrs-import-result"></div>
            </div>
        </div>
        
        <!-- インポートログ -->
        <div class="hrs-section">
            <h3>📋 インポート履歴</h3>
            <?php if (empty($import_logs)) : ?>
                <p>インポート履歴はありません。</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>日時</th>
                            <th>種類</th>
                            <th>ファイル名</th>
                            <th>成功</th>
                            <th>スキップ</th>
                            <th>エラー</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log['imported_at']); ?></td>
                            <td><?php echo strtoupper(esc_html($log['type'])); ?></td>
                            <td><?php echo esc_html($log['filename']); ?></td>
                            <td class="success-count"><?php echo intval($log['success']); ?></td>
                            <td class="skip-count"><?php echo intval($log['skip']); ?></td>
                            <td class="error-count"><?php echo intval($log['error']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * サマリーカード描画
     * 
     * @param array $summary サマリーデータ
     */
    private function render_summary_cards($summary) {
        $metrics = array(
            'avg_time_on_page' => array('label' => '滞在時間', 'unit' => '秒', 'icon' => '⏱️'),
            'bounce_rate'      => array('label' => '直帰率', 'unit' => '%', 'icon' => '↩️'),
            'ctr'              => array('label' => 'CTR', 'unit' => '%', 'icon' => '👆'),
            'avg_position'     => array('label' => '平均順位', 'unit' => '位', 'icon' => '📍')
        );
        
        foreach ($metrics as $key => $info) {
            $data = $summary['metrics'][$key] ?? array();
            $current = $data['current'] ?? 0;
            $change = $data['change'] ?? null;
            $trend = $data['trend'] ?? 'stable';
            
            $trend_class = '';
            $trend_icon = '';
            if ($trend === 'up') {
                $trend_class = 'trend-up';
                $trend_icon = '↑';
            } elseif ($trend === 'down') {
                $trend_class = 'trend-down';
                $trend_icon = '↓';
            }
            
            ?>
            <div class="hrs-summary-card">
                <div class="card-icon"><?php echo $info['icon']; ?></div>
                <div class="card-content">
                    <div class="card-label"><?php echo esc_html($info['label']); ?></div>
                    <div class="card-value"><?php echo esc_html($current); ?><span class="card-unit"><?php echo esc_html($info['unit']); ?></span></div>
                    <?php if ($change !== null) : ?>
                    <div class="card-change <?php echo $trend_class; ?>">
                        <?php echo $trend_icon; ?> <?php echo ($change >= 0 ? '+' : '') . $change . $info['unit']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * データテーブル描画
     * 
     * @param array $data データ配列
     * @param string $current_orderby 現在のソートカラム
     * @param string $current_order 現在のソート順
     */
    private function render_data_table($data, $current_orderby, $current_order) {
        $columns = array(
            'title'            => '記事タイトル',
            'avg_time_on_page' => '滞在時間',
            'bounce_rate'      => '直帰率',
            'ctr'              => 'CTR',
            'avg_position'     => '平均順位',
            'performance_score'=> 'スコア',
            'flag'             => 'フラグ',
            'actions'          => '操作'
        );
        
        $sortable = array('avg_time_on_page', 'bounce_rate', 'ctr', 'avg_position', 'performance_score');
        
        ?>
        <table class="widefat striped hrs-data-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $key => $label) : ?>
                    <th class="column-<?php echo $key; ?>">
                        <?php if (in_array($key, $sortable)) : 
                            $new_order = ($current_orderby === $key && $current_order === 'ASC') ? 'DESC' : 'ASC';
                            $sort_url = add_query_arg(array('orderby' => $key, 'order' => $new_order));
                        ?>
                        <a href="<?php echo esc_url($sort_url); ?>">
                            <?php echo esc_html($label); ?>
                            <?php if ($current_orderby === $key) : ?>
                                <span class="sorting-indicator <?php echo strtolower($current_order); ?>"></span>
                            <?php endif; ?>
                        </a>
                        <?php else : ?>
                            <?php echo esc_html($label); ?>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)) : ?>
                <tr>
                    <td colspan="<?php echo count($columns); ?>">データがありません</td>
                </tr>
                <?php else : ?>
                    <?php foreach ($data as $row) : 
                        $post = get_post($row->post_id);
                        if (!$post) continue;
                        
                        $flag_status = $this->hqc_bridge->get_flag_status($row->post_id);
                    ?>
                    <tr>
                        <td class="column-title">
                            <a href="<?php echo get_edit_post_link($row->post_id); ?>" target="_blank">
                                <?php echo esc_html(wp_trim_words($post->post_title, 10)); ?>
                            </a>
                        </td>
                        <td class="column-avg_time_on_page"><?php echo round($row->avg_time_on_page); ?>秒</td>
                        <td class="column-bounce_rate"><?php echo round($row->bounce_rate, 1); ?>%</td>
                        <td class="column-ctr"><?php echo round($row->ctr, 2); ?>%</td>
                        <td class="column-avg_position"><?php echo round($row->avg_position, 1); ?>位</td>
                        <td class="column-performance_score">
                            <span class="score-badge <?php echo $flag_status['flag']; ?>">
                                <?php echo round($row->performance_score, 1); ?>
                            </span>
                        </td>
                        <td class="column-flag">
                            <span class="flag-badge <?php echo $flag_status['flag']; ?>">
                                <?php echo esc_html($flag_status['flag_label']); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <?php if ($flag_status['flag'] === 'poor') : ?>
                            <button type="button" 
                                    class="button button-small hrs-send-rewrite" 
                                    data-post-id="<?php echo $row->post_id; ?>">
                                リライト候補へ
                            </button>
                            <?php endif; ?>
                            <a href="<?php echo get_permalink($row->post_id); ?>" 
                               target="_blank" 
                               class="button button-small">表示</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * HQCアクションボタン描画
     */
    private function render_action_buttons() {
        ?>
        <div class="hrs-action-buttons">
            <button type="button" id="hrs-bulk-flag-update" class="button button-primary">
                🔄 全記事フラグ更新
            </button>
            
            <button type="button" id="hrs-bulk-rewrite-send" class="button">
                📝 要改善記事を一括リライト候補へ
            </button>
            
            <button type="button" id="hrs-export-csv" class="button">
                📥 CSVエクスポート
            </button>
        </div>
        
        <div id="hrs-action-result" class="hrs-action-result"></div>
        <?php
    }
    
    /**
     * グラフ用データ取得
     * 
     * @param int $days 日数
     * @return array グラフデータ
     */
    private function get_chart_data($days) {
        $time_series = $this->tracker->get_time_series(null, $days);
        
        $labels = array();
        $datasets = array(
            'avg_time_on_page' => array(),
            'bounce_rate'      => array(),
            'ctr'              => array(),
            'avg_position'     => array()
        );
        
        foreach ($time_series as $row) {
            $labels[] = $row->data_date;
            $datasets['avg_time_on_page'][] = round($row->avg_time_on_page, 1);
            $datasets['bounce_rate'][] = round($row->bounce_rate, 1);
            $datasets['ctr'][] = round($row->ctr, 2);
            $datasets['avg_position'][] = round($row->avg_position, 1);
        }
        
        return array(
            'labels'   => $labels,
            'datasets' => $datasets
        );
    }
    
    /**
     * CSVアップロード処理（AJAX）
     */
    public function handle_csv_upload() {
        // nonceチェック
        if (!check_ajax_referer('hrs_import_csv', 'hrs_import_nonce', false) &&
            !check_ajax_referer('hrs_import_csv', 'hrs_import_nonce_gsc', false)) {
            wp_send_json_error(array('message' => '認証エラー'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $this->init_classes();
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $data_date = sanitize_text_field($_POST['data_date'] ?? date('Y-m-d'));
        
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(array('message' => 'ファイルがアップロードされていません'));
        }
        
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'ファイルアップロードエラー'));
        }
        
        // 一時ファイルパス
        $tmp_path = $file['tmp_name'];
        
        // インポート実行
        if ($type === 'ga4') {
            $result = $this->importer->import_ga4_csv($tmp_path, $data_date);
        } elseif ($type === 'gsc') {
            $result = $this->importer->import_gsc_csv($tmp_path, $data_date);
        } else {
            wp_send_json_error(array('message' => '不明なインポートタイプ'));
        }
        
        if ($result['success']) {
            // フラグも更新
            $this->hqc_bridge->check_and_flag();
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'インポート完了: 成功 %d件 / スキップ %d件 / エラー %d件',
                    $result['success_count'],
                    $result['skip_count'],
                    $result['error_count']
                ),
                'result' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
                'result'  => $result
            ));
        }
    }
    
    /**
     * 一括フラグ更新処理（AJAX）
     */
    public function handle_bulk_flag_update() {
        check_ajax_referer('hrs_admin_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $this->init_classes();
        
        $result = $this->hqc_bridge->check_and_flag();
        
        wp_send_json_success(array(
            'message' => sprintf(
                'フラグ更新完了: 優良 %d件 / 普通 %d件 / 要改善 %d件',
                $result['excellent'],
                $result['normal'],
                $result['poor']
            ),
            'result' => $result
        ));
    }
    
    /**
     * 一括リライト候補送り処理（AJAX）
     */
    public function handle_bulk_rewrite_send() {
        check_ajax_referer('hrs_admin_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $this->init_classes();
        
        $result = $this->hqc_bridge->bulk_send_low_performers();
        
        wp_send_json_success(array(
            'message' => sprintf(
                'リライト候補送り完了: 送信 %d件 / スキップ %d件 / 失敗 %d件',
                $result['sent'],
                $result['skipped'],
                $result['failed']
            ),
            'result' => $result
        ));
    }
    
    /**
     * CSVエクスポート処理（AJAX）
     */
    public function handle_export_csv() {
        check_ajax_referer('hrs_admin_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $this->init_classes();
        
        $data = $this->tracker->get_all_data(array(
            'latest' => true,
            'limit'  => 9999
        ));
        
        $csv_lines = array();
        $csv_lines[] = array('記事ID', '記事タイトル', '滞在時間(秒)', '直帰率(%)', 'CTR(%)', '平均順位', 'スコア', 'フラグ', 'データ日付');
        
        foreach ($data as $row) {
            $post = get_post($row->post_id);
            $flag_status = $this->hqc_bridge->get_flag_status($row->post_id);
            
            $csv_lines[] = array(
                $row->post_id,
                $post ? $post->post_title : '(削除済み)',
                round($row->avg_time_on_page, 1),
                round($row->bounce_rate, 1),
                round($row->ctr, 2),
                round($row->avg_position, 1),
                round($row->performance_score, 1),
                $flag_status['flag_label'],
                $row->data_date
            );
        }
        
        // CSV生成
        $output = '';
        foreach ($csv_lines as $line) {
            $output .= '"' . implode('","', array_map('esc_html', $line)) . '"' . "\n";
        }
        
        wp_send_json_success(array(
            'csv'      => $output,
            'filename' => 'hrs_performance_' . date('Y-m-d') . '.csv'
        ));
    }
    
    /**
     * 個別リライト候補送り処理（AJAX）
     */
    public function handle_send_to_rewrite() {
        check_ajax_referer('hrs_admin_action', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(array('message' => '記事IDが不正です'));
        }
        
        $this->init_classes();
        
        $result = $this->hqc_bridge->send_to_rewrite_planner($post_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'リライト候補に追加しました'));
        } else {
            wp_send_json_error(array('message' => '追加に失敗しました'));
        }
    }
    
    /**
     * インラインスタイル
     * 
     * @return string CSS
     */
    private function get_inline_styles() {
        return '
        .hrs-performance-wrap { max-width: 1400px; }
        .hrs-performance-content { margin-top: 20px; }
        
        /* サマリーカード */
        .hrs-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .hrs-summary-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; display: flex; align-items: center; }
        .hrs-summary-card .card-icon { font-size: 32px; margin-right: 15px; }
        .hrs-summary-card .card-label { color: #666; font-size: 12px; text-transform: uppercase; }
        .hrs-summary-card .card-value { font-size: 28px; font-weight: bold; }
        .hrs-summary-card .card-unit { font-size: 14px; color: #666; margin-left: 2px; }
        .hrs-summary-card .card-change { font-size: 12px; margin-top: 5px; }
        .hrs-summary-card .card-change.trend-up { color: #28a745; }
        .hrs-summary-card .card-change.trend-down { color: #dc3545; }
        
        /* フラグ分布 */
        .hrs-flag-distribution { display: flex; gap: 20px; }
        .hrs-flag-item { text-align: center; padding: 15px 30px; border-radius: 8px; }
        .hrs-flag-item.excellent { background: #d4edda; color: #155724; }
        .hrs-flag-item.normal { background: #fff3cd; color: #856404; }
        .hrs-flag-item.poor { background: #f8d7da; color: #721c24; }
        .hrs-flag-item .flag-count { display: block; font-size: 32px; font-weight: bold; }
        .hrs-flag-item .flag-label { font-size: 14px; }
        
        /* セクション */
        .hrs-section { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .hrs-section h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        /* チャート */
        .hrs-chart-controls { margin-bottom: 15px; }
        .hrs-chart-controls select { margin-right: 10px; }
        .hrs-chart-container { height: 300px; }
        
        /* テーブル */
        .hrs-data-table .score-badge, .hrs-data-table .flag-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .hrs-data-table .score-badge.excellent, .hrs-data-table .flag-badge.excellent { background: #d4edda; color: #155724; }
        .hrs-data-table .score-badge.normal, .hrs-data-table .flag-badge.normal { background: #fff3cd; color: #856404; }
        .hrs-data-table .score-badge.poor, .hrs-data-table .flag-badge.poor { background: #f8d7da; color: #721c24; }
        .sorting-indicator { margin-left: 5px; }
        .sorting-indicator.asc::after { content: "▲"; }
        .sorting-indicator.desc::after { content: "▼"; }
        
        /* インポート */
        .hrs-import-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .hrs-import-box { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
        .hrs-import-box h3 { margin-top: 0; }
        .hrs-form-row { margin-bottom: 15px; }
        .hrs-form-row label { display: block; margin-bottom: 5px; font-weight: bold; }
        .hrs-import-result { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
        .hrs-import-result.success { background: #d4edda; color: #155724; display: block; }
        .hrs-import-result.error { background: #f8d7da; color: #721c24; display: block; }
        
        /* アクションボタン */
        .hrs-action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .hrs-action-result { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
        .hrs-action-result.success { background: #d4edda; color: #155724; display: block; }
        .hrs-action-result.error { background: #f8d7da; color: #721c24; display: block; }
        
        /* フィルタ */
        .hrs-filters { margin-bottom: 20px; }
        .hrs-filters select { margin-right: 10px; }
        
        /* ページネーション */
        .hrs-pagination { margin-top: 20px; text-align: center; }
        
        /* インポートログ */
        .success-count { color: #28a745; font-weight: bold; }
        .skip-count { color: #ffc107; }
        .error-count { color: #dc3545; font-weight: bold; }
        ';
    }
    
    /**
     * インラインスクリプト
     * 
     * @return string JavaScript
     */
    private function get_inline_scripts() {
        return "
        document.addEventListener('DOMContentLoaded', function() {
            // グラフ初期化
            var ctx = document.getElementById('hrs-performance-chart');
            if (ctx && typeof hrsTimeSeriesData !== 'undefined') {
                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: hrsTimeSeriesData.labels,
                        datasets: [
                            {
                                label: '滞在時間(秒)',
                                data: hrsTimeSeriesData.datasets.avg_time_on_page,
                                borderColor: '#007bff',
                                tension: 0.1,
                                yAxisID: 'y'
                            },
                            {
                                label: '直帰率(%)',
                                data: hrsTimeSeriesData.datasets.bounce_rate,
                                borderColor: '#dc3545',
                                tension: 0.1,
                                yAxisID: 'y1'
                            },
                            {
                                label: 'CTR(%)',
                                data: hrsTimeSeriesData.datasets.ctr,
                                borderColor: '#28a745',
                                tension: 0.1,
                                yAxisID: 'y1'
                            },
                            {
                                label: '平均順位',
                                data: hrsTimeSeriesData.datasets.avg_position,
                                borderColor: '#ffc107',
                                tension: 0.1,
                                yAxisID: 'y2'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { position: 'left', title: { display: true, text: '秒' } },
                            y1: { position: 'right', title: { display: true, text: '%' }, grid: { drawOnChartArea: false } },
                            y2: { position: 'right', reverse: true, title: { display: true, text: '順位' }, grid: { drawOnChartArea: false } }
                        }
                    }
                });
            }
            
            // CSVインポートフォーム
            document.querySelectorAll('.hrs-import-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(form);
                    var resultDiv = form.nextElementSibling;
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        resultDiv.textContent = data.data.message;
                        resultDiv.className = 'hrs-import-result ' + (data.success ? 'success' : 'error');
                        if (data.success) {
                            setTimeout(function() { location.reload(); }, 2000);
                        }
                    })
                    .catch(function(error) {
                        resultDiv.textContent = 'エラーが発生しました';
                        resultDiv.className = 'hrs-import-result error';
                    });
                });
            });
            
            // アクションボタン
            var actionNonce = '" . wp_create_nonce('hrs_admin_action') . "';
            
            document.getElementById('hrs-bulk-flag-update')?.addEventListener('click', function() {
                if (!confirm('全記事のフラグを更新しますか？')) return;
                executeAction('hrs_bulk_flag_update');
            });
            
            document.getElementById('hrs-bulk-rewrite-send')?.addEventListener('click', function() {
                if (!confirm('要改善記事を一括でリライト候補に送りますか？')) return;
                executeAction('hrs_bulk_rewrite_send');
            });
            
            document.getElementById('hrs-export-csv')?.addEventListener('click', function() {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=hrs_export_csv&nonce=' + actionNonce
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        var blob = new Blob([data.data.csv], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = data.data.filename;
                        link.click();
                    }
                });
            });
            
            // 個別リライト送り
            document.querySelectorAll('.hrs-send-rewrite').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var postId = this.dataset.postId;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=hrs_send_to_rewrite&nonce=' + actionNonce + '&post_id=' + postId
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        alert(data.data.message);
                        if (data.success) {
                            btn.disabled = true;
                            btn.textContent = '追加済み';
                        }
                    });
                });
            });
            
            function executeAction(action) {
                var resultDiv = document.getElementById('hrs-action-result');
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=' + action + '&nonce=' + actionNonce
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    resultDiv.textContent = data.data.message;
                    resultDiv.className = 'hrs-action-result ' + (data.success ? 'success' : 'error');
                })
                .catch(function(error) {
                    resultDiv.textContent = 'エラーが発生しました';
                    resultDiv.className = 'hrs-action-result error';
                });
            }
        });
        ";
    }
    
    /**
     * API設定タブ描画
     */
    private function render_api_settings_tab() {
        if (class_exists('HRS_API_Settings_Extension')) {
            HRS_API_Settings_Extension::render_api_settings_tab();
        } else {
            echo '<div class="notice notice-error"><p>API設定機能が利用できません。class-api-settings-extension.php を確認してください。</p></div>';
        }
    }
}