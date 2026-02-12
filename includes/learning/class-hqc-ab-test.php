<?php
/**
 * HQC A/Bテスト機能
 *
 * 生成パラメータの最適化のためのA/Bテスト
 * - プロンプトA/Bテスト
 * - AIモデル比較
 * - 記事A/Bテスト（CTR/PV比較）
 *
 * @package HRS
 * @subpackage Learning
 * @version 2.3.1
 *
 * 変更履歴:
 * - 2.3.1: class-auto-generator.php パス修正 (includes/admin/generator/)
 * - 2.3.0: HRS_Auto_Generator依存解決（generate_with_params修正）
 * - 2.2.0: 依存ファイル自己読み込み追加（読み込み忘れ防止）
 * - 2.1.0: 生成失敗時のエラーハンドリング改善、CSS/JS分離
 * - 2.0.0: 初期リリース
 */
if (!defined('ABSPATH')) {
    exit;
}

// ★ 依存ファイル自己読み込み（追加忘れ防止）
$_hrs_ab_dir = plugin_dir_path(__FILE__);
foreach (['class-hqc-ab-test-styles.php', 'class-hqc-ab-test-scripts.php'] as $_f) {
    if (file_exists($_hrs_ab_dir . $_f)) {
        require_once $_hrs_ab_dir . $_f;
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[HRS] WARNING: Missing A/B Test dependency: ' . $_f);
    }
}
unset($_hrs_ab_dir, $_f);

class HRS_HQC_AB_Test {
    /**
     * テーブル名
     */
    private $table_name;

    /**
     * A/Bテストページかどうか
     */
    private static $is_ab_page = false;

    /**
     * コンストラクタ
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hrs_ab_tests';

        add_action('admin_menu', array($this, 'add_menu'), 99);
        add_action('admin_init', array($this, 'check_page'));
        add_action('admin_head', array($this, 'output_inline_styles'));
        add_action('admin_footer', array($this, 'output_inline_scripts'));
        add_action('wp_ajax_hrs_create_ab_test', array($this, 'ajax_create_test'));
        add_action('wp_ajax_hrs_run_ab_test', array($this, 'ajax_run_test'));
        add_action('wp_ajax_hrs_get_ab_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_hrs_apply_winner', array($this, 'ajax_apply_winner'));
        add_action('wp_ajax_hrs_delete_ab_test', array($this, 'ajax_delete_test'));
    }

    /**
     * ページ判定
     */
    public function check_page() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === 'hrs-ab-test') {
            self::$is_ab_page = true;
        }
    }

    /**
     * headでインラインCSS出力
     */
    public function output_inline_styles() {
        if (!self::$is_ab_page) {
            return;
        }
        if (class_exists('HRS_HQC_AB_Test_Styles')) {
            echo '<style type="text/css" id="hrs-ab-test-styles">' . "\n";
            echo HRS_HQC_AB_Test_Styles::get_inline_styles();
            echo "\n" . '</style>' . "\n";
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS] WARNING: HRS_HQC_AB_Test_Styles class not available on A/B test page');
        }
    }

    /**
     * フッターでインラインJS出力
     */
    public function output_inline_scripts() {
        if (!self::$is_ab_page) {
            return;
        }
        if (class_exists('HRS_HQC_AB_Test_Scripts')) {
            $nonce = wp_create_nonce('hrs_ab_test');
            echo '<script type="text/javascript" id="hrs-ab-test-scripts">' . "\n";
            echo HRS_HQC_AB_Test_Scripts::get_inline_script($nonce);
            echo "\n" . '</script>' . "\n";
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS] WARNING: HRS_HQC_AB_Test_Scripts class not available on A/B test page');
        }
    }

    /**
     * メニュー追加
     */
    public function add_menu() {
        add_submenu_page(
            '5d-review-builder',
            'A/Bテスト',
            '🔬 A/Bテスト',
            'manage_options',
            'hrs-ab-test',
            array($this, 'render_page')
        );
    }

    /**
     * ページを描画
     */
    public function render_page() {
        ?>
        <div class="wrap hrs-ab-wrap">
            <h1><span class="dashicons dashicons-randomize"></span> A/Bテスト - パラメータ最適化</h1>
           
            <?php $this->render_statistics(); ?>
            <?php $this->render_tabs(); ?>
           
            <div id="ab-test-content">
                <div id="tab-prompt" class="ab-tab-panel active">
                    <?php $this->render_create_form('prompt'); ?>
                </div>
                <div id="tab-model" class="ab-tab-panel">
                    <?php $this->render_create_form('model'); ?>
                </div>
                <div id="tab-article" class="ab-tab-panel">
                    <?php $this->render_article_test_form(); ?>
                </div>
            </div>
           
            <?php $this->render_test_list(); ?>
        </div>
        <?php
    }

    /**
     * タブを表示
     */
    private function render_tabs() {
        ?>
        <div class="ab-tabs">
            <button class="ab-tab active" data-tab="prompt">
                <span class="dashicons dashicons-edit"></span> プロンプトA/B
            </button>
            <button class="ab-tab" data-tab="model">
                <span class="dashicons dashicons-desktop"></span> AIモデル比較
            </button>
            <button class="ab-tab" data-tab="article">
                <span class="dashicons dashicons-media-document"></span> 記事A/B
            </button>
        </div>
        <?php
    }

    /**
     * 統計を表示
     */
    private function render_statistics() {
        global $wpdb;
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}") ?: 0;
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'") ?: 0;
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'") ?: 0;
        $winner_a = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE winner = 'A'") ?: 0;
        $winner_b = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE winner = 'B'") ?: 0;
        ?>
        <div class="ab-stats-grid">
            <div class="ab-stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo esc_html($total); ?></div>
                <div class="stat-label">総テスト数</div>
            </div>
            <div class="ab-stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo esc_html($completed); ?></div>
                <div class="stat-label">完了</div>
            </div>
            <div class="ab-stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?php echo esc_html($failed); ?></div>
                <div class="stat-label">失敗</div>
            </div>
            <div class="ab-stat-card variant-a">
                <div class="stat-icon">🅰️</div>
                <div class="stat-value"><?php echo esc_html($winner_a); ?></div>
                <div class="stat-label">バリアントA勝利</div>
            </div>
            <div class="ab-stat-card variant-b">
                <div class="stat-icon">🅱️</div>
                <div class="stat-value"><?php echo esc_html($winner_b); ?></div>
                <div class="stat-label">バリアントB勝利</div>
            </div>
        </div>
        <?php
    }

    /**
     * 作成フォームを表示
     */
    private function render_create_form($type = 'prompt') {
        $personas = array();
        if (class_exists('HRS_5D_Config') && defined('HRS_5D_Config::PERSONAS')) {
            foreach (HRS_5D_Config::PERSONAS as $key => $data) {
                $personas[$key] = $data['name'];
            }
        } else {
            $personas = array(
                'general' => '一般',
                'solo' => '一人旅',
                'couple' => 'カップル・夫婦',
                'family' => 'ファミリー',
                'senior' => 'シニア',
                'workation' => 'ワーケーション',
                'luxury' => 'ラグジュアリー',
                'budget' => '節約志向',
            );
        }

        $styles = array();
        if (class_exists('HRS_5D_Config') && defined('HRS_5D_Config::STRUCTURES')) {
            foreach (HRS_5D_Config::STRUCTURES as $key => $data) {
                $styles[$key] = $data['name'];
            }
        } else {
            $styles = array(
                'timeline' => '時系列',
                'hero_journey' => '物語構造',
                'five_sense' => '五感描写',
                'dialogue' => '対話形式',
                'review' => 'レビュー',
            );
        }

        $tones = array();
        if (class_exists('HRS_5D_Config') && defined('HRS_5D_Config::TONES')) {
            foreach (HRS_5D_Config::TONES as $key => $data) {
                $tones[$key] = $data['name'];
            }
        } else {
            $tones = array(
                'casual' => 'カジュアル',
                'luxury' => 'ラグジュアリー',
                'emotional' => 'エモーショナル',
                'cinematic' => '映画的',
                'journalistic' => '報道的',
            );
        }

        $models = array(
            'gpt-4' => 'GPT-4',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'claude-3-opus' => 'Claude 3 Opus',
            'claude-3-sonnet' => 'Claude 3 Sonnet',
            'gemini-pro' => 'Gemini Pro',
        );

        $word_counts = array(
            '1500' => '1500文字（標準）',
            '2000' => '2000文字（推奨）',
            '2500' => '2500文字（詳細）',
            '3000' => '3000文字（超詳細）',
        );
        ?>
        <div class="ab-form-card">
            <h2>
                <?php if ($type === 'prompt'): ?>
                    <span class="dashicons dashicons-edit"></span> プロンプトA/Bテスト
                <?php else: ?>
                    <span class="dashicons dashicons-desktop"></span> AIモデル比較テスト
                <?php endif; ?>
            </h2>
           
            <form class="ab-test-form" data-type="<?php echo esc_attr($type); ?>">
                <input type="hidden" name="test_type" value="<?php echo esc_attr($type); ?>">
               
                <div class="ab-form-row">
                    <div class="ab-form-group full-width">
                        <label>テスト名 <span class="required">*</span></label>
                        <input type="text" name="test_name" required placeholder="例：<?php echo $type === 'model' ? 'GPT-4 vs Claude比較' : 'カジュアル vs ラグジュアリー比較'; ?>">
                    </div>
                </div>
               
                <div class="ab-form-row">
                    <div class="ab-form-group full-width">
                        <label>テスト対象ホテル <span class="required">*</span></label>
                        <input type="text" name="hotel_name" required placeholder="例：ローズホテル横浜">
                    </div>
                </div>

                <div class="ab-variants-grid">
                    <div class="ab-variant variant-a">
                        <h3>🅰️ バリアントA</h3>
                       
                        <?php if ($type === 'model'): ?>
                        <div class="ab-form-group">
                            <label>AIモデル</label>
                            <select name="variant_a_model">
                                <?php foreach ($models as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                       
                        <div class="ab-form-group">
                            <label>ペルソナ</label>
                            <select name="variant_a_persona">
                                <?php foreach ($personas as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>スタイル</label>
                            <select name="variant_a_style">
                                <?php foreach ($styles as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>トーン</label>
                            <select name="variant_a_tone">
                                <?php foreach ($tones as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>文字数</label>
                            <select name="variant_a_words">
                                <?php foreach ($word_counts as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, '2000'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="ab-variant variant-b">
                        <h3>🅱️ バリアントB</h3>
                       
                        <?php if ($type === 'model'): ?>
                        <div class="ab-form-group">
                            <label>AIモデル</label>
                            <select name="variant_b_model">
                                <?php foreach ($models as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'claude-3-sonnet'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                       
                        <div class="ab-form-group">
                            <label>ペルソナ</label>
                            <select name="variant_b_persona">
                                <?php foreach ($personas as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'couple'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>スタイル</label>
                            <select name="variant_b_style">
                                <?php foreach ($styles as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'five_sense'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>トーン</label>
                            <select name="variant_b_tone">
                                <?php foreach ($tones as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'luxury'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ab-form-group">
                            <label>文字数</label>
                            <select name="variant_b_words">
                                <?php foreach ($word_counts as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, '2500'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
               
                <div class="ab-form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt"></span> テストを作成
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * 記事A/Bテストフォーム
     */
    private function render_article_test_form() {
        $posts = get_posts(array(
            'post_type' => 'hotel-review',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        ?>
        <div class="ab-form-card">
            <h2><span class="dashicons dashicons-media-document"></span> 記事A/Bテスト（CTR/PV比較）</h2>
           
            <form class="ab-test-form" data-type="article">
                <input type="hidden" name="test_type" value="article">
               
                <div class="ab-form-row">
                    <div class="ab-form-group full-width">
                        <label>テスト名 <span class="required">*</span></label>
                        <input type="text" name="test_name" required placeholder="例：タイトル変更テスト">
                    </div>
                </div>
               
                <div class="ab-form-row">
                    <div class="ab-form-group full-width">
                        <label>テスト対象記事 <span class="required">*</span></label>
                        <select name="post_id" required>
                            <option value="">記事を選択...</option>
                            <?php foreach ($posts as $post): ?>
                            <option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ab-variants-grid">
                    <div class="ab-variant variant-a">
                        <h3>🅰️ バリアントA（現在）</h3>
                        <div class="ab-form-group">
                            <label>タイトル</label>
                            <input type="text" name="variant_a_title" id="variant_a_title" placeholder="現在のタイトルが自動入力されます" readonly>
                        </div>
                        <div class="ab-form-group">
                            <label>メタディスクリプション</label>
                            <textarea name="variant_a_meta" id="variant_a_meta" rows="3" placeholder="現在のメタディスクリプション" readonly></textarea>
                        </div>
                    </div>

                    <div class="ab-variant variant-b">
                        <h3>🅱️ バリアントB（テスト版）</h3>
                        <div class="ab-form-group">
                            <label>タイトル</label>
                            <input type="text" name="variant_b_title" placeholder="新しいタイトルを入力">
                        </div>
                        <div class="ab-form-group">
                            <label>メタディスクリプション</label>
                            <textarea name="variant_b_meta" rows="3" placeholder="新しいメタディスクリプションを入力"></textarea>
                        </div>
                    </div>
                </div>
               
                <div class="ab-form-row">
                    <div class="ab-form-group">
                        <label>テスト期間</label>
                        <select name="test_duration">
                            <option value="7">7日間</option>
                            <option value="14" selected>14日間</option>
                            <option value="30">30日間</option>
                        </select>
                    </div>
                    <div class="ab-form-group">
                        <label>トラフィック配分</label>
                        <select name="traffic_split">
                            <option value="50">50% / 50%</option>
                            <option value="70">70% / 30%</option>
                            <option value="80">80% / 20%</option>
                        </select>
                    </div>
                </div>
               
                <div class="ab-form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt"></span> 記事テストを開始
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * テスト一覧を表示
     */
    private function render_test_list() {
        global $wpdb;
        $tests = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT 20",
            ARRAY_A
        );
        ?>
        <div class="ab-list-card">
            <h2><span class="dashicons dashicons-list-view"></span> テスト一覧</h2>
            <?php if (empty($tests)): ?>
            <div class="ab-empty-state">
                <span class="dashicons dashicons-info-outline"></span>
                <p>テストがありません。上のフォームから新規テストを作成してください。</p>
            </div>
            <?php else: ?>
            <table class="ab-test-table">
                <thead>
                    <tr>
                        <th>テスト名</th>
                        <th>タイプ</th>
                        <th>対象</th>
                        <th>ステータス</th>
                        <th>Aスコア</th>
                        <th>Bスコア</th>
                        <th>勝者</th>
                        <th>作成日</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test):
                        $status_labels = array(
                            'pending' => '<span class="status-pending">待機中</span>',
                            'running' => '<span class="status-running">実行中</span>',
                            'completed' => '<span class="status-completed">完了</span>',
                            'failed' => '<span class="status-failed">生成失敗</span>',
                        );
                        $type_labels = array(
                            'prompt' => 'プロンプト',
                            'model' => 'AIモデル',
                            'article' => '記事',
                        );
                        $is_failed = ($test['status'] === 'failed');
                        $is_zero_score = ($test['status'] === 'completed'
                            && floatval($test['variant_a_score']) == 0
                            && floatval($test['variant_b_score']) == 0);
                    ?>
                    <tr<?php echo ($is_failed || $is_zero_score) ? ' class="row-failed"' : ''; ?>>
                        <td><strong><?php echo esc_html($test['test_name']); ?></strong></td>
                        <td><?php echo esc_html($type_labels[$test['test_type'] ?? 'prompt'] ?? 'プロンプト'); ?></td>
                        <td><?php echo esc_html($test['hotel_name']); ?></td>
                        <td>
                            <?php if ($is_zero_score && !$is_failed): ?>
                                <span class="status-failed">生成失敗</span>
                            <?php else: ?>
                                <?php echo $status_labels[$test['status']] ?? esc_html($test['status']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_failed || $is_zero_score): ?>
                                <span class="score-failed">-</span>
                            <?php elseif ($test['variant_a_score'] !== null): ?>
                                <?php echo esc_html(round($test['variant_a_score'], 1)) . '%'; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_failed || $is_zero_score): ?>
                                <span class="score-failed">-</span>
                            <?php elseif ($test['variant_b_score'] !== null): ?>
                                <?php echo esc_html(round($test['variant_b_score'], 1)) . '%'; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_failed || $is_zero_score): ?>
                                <span class="winner-badge winner-failed">失敗</span>
                            <?php elseif ($test['winner']): ?>
                                <span class="winner-badge winner-<?php echo strtolower($test['winner']); ?>">
                                    <?php echo $test['winner'] === 'TIE' ? '引分' : 'バリアント ' . esc_html($test['winner']); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('Y/m/d', strtotime($test['created_at']))); ?></td>
                        <td class="actions">
                            <?php if ($test['status'] === 'pending'): ?>
                            <button class="button button-small run-test" data-id="<?php echo esc_attr($test['id']); ?>">
                                <span class="dashicons dashicons-controls-play"></span> 実行
                            </button>
                            <?php endif; ?>
                            <?php if ($is_failed || $is_zero_score): ?>
                            <button class="button button-small retry-test" data-id="<?php echo esc_attr($test['id']); ?>">
                                <span class="dashicons dashicons-image-rotate"></span> 再実行
                            </button>
                            <?php endif; ?>
                            <?php if ($test['status'] === 'completed' && $test['winner'] && $test['winner'] !== 'TIE' && !$is_zero_score): ?>
                            <button class="button button-small button-primary apply-winner" data-id="<?php echo esc_attr($test['id']); ?>">
                                <span class="dashicons dashicons-yes"></span> 適用
                            </button>
                            <?php endif; ?>
                            <button class="button button-small delete-test" data-id="<?php echo esc_attr($test['id']); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: テスト作成
     */
    public function ajax_create_test() {
        check_ajax_referer('hrs_ab_test');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }

        parse_str($_POST['data'], $data);

        global $wpdb;

        $test_type = sanitize_text_field($data['test_type'] ?? 'prompt');

        $variant_a = array(
            'model'   => sanitize_text_field($data['variant_a_model'] ?? 'gpt-4o-mini'),
            'persona' => sanitize_text_field($data['variant_a_persona'] ?? 'solo'),
            'style'   => sanitize_text_field($data['variant_a_style'] ?? 'timeline'),
            'tone'    => sanitize_text_field($data['variant_a_tone'] ?? 'casual'),
            'words'   => intval($data['variant_a_words'] ?? 2000),
        );

        $variant_b = array(
            'model'   => sanitize_text_field($data['variant_b_model'] ?? 'gpt-4o-mini'),
            'persona' => sanitize_text_field($data['variant_b_persona'] ?? 'couple'),
            'style'   => sanitize_text_field($data['variant_b_style'] ?? 'five_sense'),
            'tone'    => sanitize_text_field($data['variant_b_tone'] ?? 'luxury'),
            'words'   => intval($data['variant_b_words'] ?? 2500),
        );

        if ($test_type === 'article') {
            $variant_a['post_id'] = intval($data['post_id'] ?? 0);
            $variant_a['title']   = sanitize_text_field($data['variant_a_title'] ?? '');
            $variant_a['meta']    = sanitize_textarea_field($data['variant_a_meta'] ?? '');

            $variant_b['title'] = sanitize_text_field($data['variant_b_title'] ?? '');
            $variant_b['meta']  = sanitize_textarea_field($data['variant_b_meta'] ?? '');
        }

        $result = $wpdb->insert($this->table_name, array(
            'test_name'        => sanitize_text_field($data['test_name'] ?? ''),
            'test_type'        => $test_type,
            'hotel_name'       => sanitize_text_field($data['hotel_name'] ?? $data['post_id'] ?? ''),
            'status'           => 'pending',
            'variant_a_config' => json_encode($variant_a),
            'variant_b_config' => json_encode($variant_b),
            'created_at'       => current_time('mysql'),
        ));

        if ($result) {
            wp_send_json_success(array('id' => $wpdb->insert_id));
        } else {
            wp_send_json_error('保存に失敗しました');
        }
    }

    /**
     * AJAX: テスト実行
     */
    public function ajax_run_test() {
        check_ajax_referer('hrs_ab_test');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }

        $test_id = intval($_POST['test_id']);

        global $wpdb;

        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $test_id
        ), ARRAY_A);

        if (!$test) {
            wp_send_json_error('テストが見つかりません');
        }

        $wpdb->update($this->table_name, array('status' => 'running'), array('id' => $test_id));

        $variant_a_config = json_decode($test['variant_a_config'], true);
        $variant_b_config = json_decode($test['variant_b_config'], true);

        // A/Bバリアント識別子を追加
        $variant_a_config['ab_variant'] = 'a';
        $variant_b_config['ab_variant'] = 'b';

        $result_a = $this->generate_with_params($test['hotel_name'], $variant_a_config);
        $result_b = $this->generate_with_params($test['hotel_name'], $variant_b_config);

        // 両方失敗
        if ($result_a['error'] && $result_b['error']) {
            $error_msg = 'A: ' . $result_a['error'] . ' / B: ' . $result_b['error'];
            $wpdb->update($this->table_name, array(
                'status'           => 'failed',
                'variant_a_score'  => null,
                'variant_b_score'  => null,
                'winner'           => null,
                'completed_at'     => current_time('mysql'),
            ), array('id' => $test_id));

            wp_send_json_success(array(
                'status' => 'failed',
                'error'  => $error_msg,
            ));
            return;
        }

        // Aだけ失敗
        if ($result_a['error'] && !$result_b['error']) {
            $wpdb->update($this->table_name, array(
                'status'           => 'completed',
                'variant_a_score'  => null,
                'variant_b_score'  => $result_b['score'],
                'variant_a_post_id'=> null,
                'variant_b_post_id'=> $result_b['post_id'],
                'winner'           => 'B',
                'completed_at'     => current_time('mysql'),
            ), array('id' => $test_id));

            wp_send_json_success(array(
                'status'  => 'partial',
                'winner'  => 'B',
                'score_a' => '生成失敗',
                'score_b' => $result_b['score'],
                'error'   => 'バリアントA生成失敗: ' . $result_a['error'],
            ));
            return;
        }

        // Bだけ失敗
        if (!$result_a['error'] && $result_b['error']) {
            $wpdb->update($this->table_name, array(
                'status'           => 'completed',
                'variant_a_score'  => $result_a['score'],
                'variant_b_score'  => null,
                'variant_a_post_id'=> $result_a['post_id'],
                'variant_b_post_id'=> null,
                'winner'           => 'A',
                'completed_at'     => current_time('mysql'),
            ), array('id' => $test_id));

            wp_send_json_success(array(
                'status'  => 'partial',
                'winner'  => 'A',
                'score_a' => $result_a['score'],
                'score_b' => '生成失敗',
                'error'   => 'バリアントB生成失敗: ' . $result_b['error'],
            ));
            return;
        }

        // 両方成功
        $winner = null;
        if ($result_a['score'] > $result_b['score']) {
            $winner = 'A';
        } elseif ($result_b['score'] > $result_a['score']) {
            $winner = 'B';
        } else {
            $winner = 'TIE';
        }

        $wpdb->update($this->table_name, array(
            'status'           => 'completed',
            'variant_a_score'  => $result_a['score'],
            'variant_b_score'  => $result_b['score'],
            'variant_a_post_id'=> $result_a['post_id'],
            'variant_b_post_id'=> $result_b['post_id'],
            'winner'           => $winner,
            'completed_at'     => current_time('mysql'),
        ), array('id' => $test_id));

        wp_send_json_success(array(
            'status'  => 'completed',
            'winner'  => $winner,
            'score_a' => $result_a['score'],
            'score_b' => $result_b['score'],
        ));
    }

    /**
     * パラメータで生成
     *
     * @version 2.3.1 class-auto-generator.phpパス修正
     */
    private function generate_with_params($hotel_name, $params) {
        // ★【v2.3.3修正】HRS_Auto_Generator を明示的に読み込み
        // includes/learning/ から includes/generator/ へのパス
        if (!class_exists('HRS_Auto_Generator')) {
            $auto_gen_file = plugin_dir_path(dirname(__FILE__)) . 'generator/class-auto-generator.php';
            if (file_exists($auto_gen_file)) {
                require_once $auto_gen_file;
            }
        }

        if (!class_exists('HRS_Auto_Generator')) {
            return array(
                'score'   => 0,
                'post_id' => null,
                'error'   => 'HRS_Auto_Generator クラスが見つかりません (パス: ' . ($auto_gen_file ?? 'unknown') . ')',
            );
        }

        try {
            $generator = HRS_Auto_Generator::get_instance();

            $result = $generator->generate_single($hotel_name, array(
                'persona'        => $params['persona'] ?? 'solo',
                'style'          => $params['style'] ?? 'timeline',
                'tone'           => $params['tone'] ?? 'casual',
                'target_words'   => $params['words'] ?? 2000,
                'skip_hqc_check' => true,
            ));

            if ($result['success'] && isset($result['post_id'])) {
                $hqc_score = get_post_meta($result['post_id'], '_hrs_hqc_score', true);
                $score = floatval($hqc_score);

                if (empty($hqc_score) && $hqc_score !== '0') {
                    error_log('[HRS AB Test] HQCスコア未設定: post_id=' . $result['post_id']);
                }

                return array(
                    'score'   => $score,
                    'post_id' => $result['post_id'],
                    'error'   => null,
                );
            }

            $error_msg = $result['message'] ?? $result['error'] ?? '記事生成に失敗しました';
            return array(
                'score'   => 0,
                'post_id' => null,
                'error'   => $error_msg,
            );
        } catch (Exception $e) {
            error_log('[HRS AB Test] Generation error: ' . $e->getMessage());
            return array(
                'score'   => 0,
                'post_id' => null,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * AJAX: 勝者適用
     */
    public function ajax_apply_winner() {
        check_ajax_referer('hrs_ab_test');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }

        $test_id = intval($_POST['test_id']);

        global $wpdb;

        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $test_id
        ), ARRAY_A);

        if (!$test || !$test['winner'] || $test['winner'] === 'TIE') {
            wp_send_json_error('テストまたは勝者が見つかりません');
        }

        $winner_params = $test['winner'] === 'A'
            ? json_decode($test['variant_a_config'], true)
            : json_decode($test['variant_b_config'], true);

        $hqc_settings = get_option('hrs_hqc_settings', array());

        $hqc_settings['h']['persona']   = $winner_params['persona'] ?? 'solo';
        $hqc_settings['q']['tone']      = $winner_params['tone'] ?? 'casual';
        $hqc_settings['q']['structure'] = $winner_params['style'] ?? 'timeline';

        update_option('hrs_hqc_settings', $hqc_settings);
        update_option('hrs_default_persona', $winner_params['persona'] ?? 'solo');
        update_option('hrs_default_style',   $winner_params['style'] ?? 'timeline');
        update_option('hrs_default_tone',    $winner_params['tone'] ?? 'casual');
        update_option('hrs_default_words',   $winner_params['words'] ?? 2000);

        wp_send_json_success();
    }

    /**
     * AJAX: テスト削除
     */
    public function ajax_delete_test() {
        check_ajax_referer('hrs_ab_test');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }

        $test_id = intval($_POST['test_id']);

        global $wpdb;

        $result = $wpdb->delete($this->table_name, array('id' => $test_id));

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('削除に失敗しました');
        }
    }

    /**
     * AJAX: 結果取得
     */
    public function ajax_get_results() {
        check_ajax_referer('hrs_ab_test');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }

        $test_id = intval($_POST['test_id']);

        global $wpdb;

        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $test_id
        ), ARRAY_A);

        if (!$test) {
            wp_send_json_error('テストが見つかりません');
        }

        wp_send_json_success($test);
    }
}

// インスタンス化
new HRS_HQC_AB_Test();