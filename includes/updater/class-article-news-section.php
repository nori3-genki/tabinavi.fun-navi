<?php
/**
 * HRS Article News Section
 * 
 * ホテル記事の末尾に最新ニュース・プラン情報を自動表示
 *
 * @package HRS
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Article_News_Section {

    private static $instance = null;

    /**
     * シングルトン
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
        // 記事コンテンツにフィルター追加
        add_filter('the_content', [$this, 'append_news_section'], 20);
    }

    /**
     * 記事末尾にニュースセクション追加
     */
    public function append_news_section($content) {
        // 管理画面・フィード・抜粋では実行しない
        if (is_admin() || is_feed() || !is_singular('hotel-review')) {
            return $content;
        }

        // メインクエリのみ
        if (!in_the_loop() || !is_main_query()) {
            return $content;
        }

        // 設定確認
        $show_in_article = get_option('hrs_news_show_in_article', 1);
        if (!$show_in_article) {
            return $content;
        }

        // 記事IDを取得
        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        // ニュース・プランセクションを生成
        $section = $this->render_section($post_id);

        if (empty($section)) {
            return $content;
        }

        // 挿入位置
        $position = get_option('hrs_news_article_position', 'bottom');

        if ($position === 'top') {
            return $section . $content;
        } else {
            return $content . $section;
        }
    }

    /**
     * セクションHTML生成
     */
    public function render_section($post_id) {
        $news_items = get_post_meta($post_id, '_hrs_news_items', true) ?: [];
        $plan_items = get_post_meta($post_id, '_hrs_plan_items', true) ?: [];

        // 両方空なら何も表示しない
        if (empty($news_items) && empty($plan_items)) {
            return '';
        }

        $max_items = (int) get_option('hrs_news_article_max_items', 5);
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        $last_updated = get_post_meta($post_id, '_hrs_news_updated', true);

        ob_start();
        ?>
        <div class="hrs-article-news-section">
            <h2 class="hrs-section-heading">📰 <?php echo esc_html($hotel_name); ?>の最新情報</h2>
            
            <?php if (!empty($news_items)): ?>
            <div class="hrs-news-block">
                <h3 class="hrs-block-title"><span class="icon">📢</span> ニュース・お知らせ</h3>
                <ul class="hrs-news-list">
                    <?php 
                    // 最新N件を取得（既に日付降順でソート済み）
                    $news_items = array_slice($news_items, 0, $max_items);
                    foreach ($news_items as $item): 
                        $date = !empty($item['date']) ? date('Y/m/d', strtotime($item['date'])) : '';
                    ?>
                    <li class="hrs-news-item">
                        <span class="hrs-item-date"><?php echo esc_html($date); ?></span>
                        <span class="hrs-item-source"><?php echo esc_html($item['source'] ?? ''); ?></span>
                        <a href="<?php echo esc_url($item['url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer" class="hrs-item-link">
                            <?php echo esc_html($item['title'] ?? ''); ?>
                            <span class="external-icon">↗</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($plan_items)): ?>
            <div class="hrs-plans-block">
                <h3 class="hrs-block-title"><span class="icon">🏷️</span> 新着プラン・キャンペーン</h3>
                <ul class="hrs-plans-list">
                    <?php 
                    // 最新N件を取得（既に日付降順でソート済み）
                    $plan_items = array_slice($plan_items, 0, $max_items);
                    foreach ($plan_items as $item): 
                        $date = !empty($item['date']) ? date('Y/m/d', strtotime($item['date'])) : '';
                    ?>
                    <li class="hrs-plan-item">
                        <span class="hrs-item-date"><?php echo esc_html($date); ?></span>
                        <span class="hrs-item-source hrs-plan-source"><?php echo esc_html($item['source'] ?? ''); ?></span>
                        <a href="<?php echo esc_url($item['url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer" class="hrs-item-link">
                            <?php echo esc_html($item['title'] ?? ''); ?>
                            <span class="external-icon">↗</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($last_updated): ?>
            <p class="hrs-update-info">
                <small>情報更新日: <?php echo esc_html(date('Y年n月j日', strtotime($last_updated))); ?></small>
            </p>
            <?php endif; ?>
        </div>

        <?php $this->render_styles(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * スタイル出力
     */
    private function render_styles() {
        static $styles_rendered = false;
        if ($styles_rendered) return;
        $styles_rendered = true;
        ?>
        <style>
        .hrs-article-news-section {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .hrs-section-heading {
            font-size: 1.5em;
            margin: 0 0 25px 0;
            padding-bottom: 15px;
            border-bottom: 3px solid #3b82f6;
            color: #1e293b;
        }
        .hrs-news-block,
        .hrs-plans-block {
            margin-bottom: 25px;
        }
        .hrs-news-block:last-of-type,
        .hrs-plans-block:last-of-type {
            margin-bottom: 0;
        }
        .hrs-block-title {
            font-size: 1.15em;
            margin: 0 0 15px 0;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .hrs-block-title .icon {
            font-size: 1.2em;
        }
        .hrs-news-list,
        .hrs-plans-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .hrs-news-item,
        .hrs-plan-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            padding: 14px 16px;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            gap: 10px;
        }
        .hrs-news-item:hover,
        .hrs-plan-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        .hrs-news-item:last-child,
        .hrs-plan-item:last-child {
            margin-bottom: 0;
        }
        .hrs-item-date {
            font-size: 0.9em;
            color: #64748b;
            min-width: 85px;
            font-weight: 600;
        }
        .hrs-item-source {
            display: inline-block;
            padding: 4px 12px;
            background: #e2e8f0;
            color: #475569;
            font-size: 0.8em;
            border-radius: 20px;
            font-weight: 600;
        }
        .hrs-plan-source {
            background: #fef3c7;
            color: #92400e;
        }
        .hrs-item-link {
            flex: 1;
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 200px;
        }
        .hrs-item-link:hover {
            color: #3b82f6;
            text-decoration: underline;
        }
        .hrs-item-link .external-icon {
            font-size: 0.85em;
            opacity: 0.7;
        }
        .hrs-update-info {
            text-align: right;
            margin: 20px 0 0 0;
            color: #94a3b8;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .hrs-article-news-section {
                padding: 20px;
                margin: 30px 0;
            }
            .hrs-section-heading {
                font-size: 1.3em;
            }
            .hrs-news-item,
            .hrs-plan-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px;
            }
            .hrs-item-date {
                min-width: auto;
            }
            .hrs-item-link {
                min-width: 100%;
            }
        }
        </style>
        <?php
    }

    /**
     * ショートコード: [hrs_hotel_news]
     * 特定記事のニュースを任意の場所に表示
     */
    public static function shortcode_hotel_news($atts) {
        $atts = shortcode_atts([
            'post_id' => get_the_ID(),
            'max' => 5,
        ], $atts);

        $instance = self::get_instance();
        
        // 一時的に最大件数を変更
        $original_max = get_option('hrs_news_article_max_items', 5);
        update_option('hrs_news_article_max_items', (int) $atts['max']);
        
        $output = $instance->render_section((int) $atts['post_id']);
        
        // 元に戻す
        update_option('hrs_news_article_max_items', $original_max);
        
        return $output;
    }
}

// ショートコード登録
add_shortcode('hrs_hotel_news', ['HRS_Article_News_Section', 'shortcode_hotel_news']);

// 初期化
add_action('init', function() {
    HRS_Article_News_Section::get_instance();
});