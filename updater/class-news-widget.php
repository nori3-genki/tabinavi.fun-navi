<?php
/**
 * HRS News & Plan Widget
 * 
 * トップページ（人気ランキング下）と投稿ページのサイドバーに最新ニュース・プラン情報を表示
 *
 * @package HRS
 * @version 2.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_News_Widget extends WP_Widget {

    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct(
            'hrs_news_widget',
            '🏨 ホテルニュース・プラン',
            [
                'description' => 'ホテルの最新ニュース・新プラン情報を表示します（トップページ・投稿ページ用）',
                'classname' => 'hrs-news-widget',
            ]
        );
        
        // トップページの人気ランキング下に表示
        add_action('wp_footer', [$this, 'inject_to_top_page'], 5);
    }

    /**
     * トップページの人気ランキング下に表示
     */
    public function inject_to_top_page() {
        // トップページ以外は実行しない
        if (!is_front_page() && !is_home()) {
            return;
        }
        
        // ウィジェット設定取得
        $widget_options = get_option('widget_hrs_news_widget', []);
        $instance = $widget_options[1] ?? [
            'show_news' => true,
            'show_plans' => true,
            'news_count' => 5,
            'plan_count' => 5,
        ];
        
        // HTMLを生成
        $html = $this->render_news_section($instance, false);
        
        if (empty($html)) {
            return;
        }
        
        // JavaScriptで人気ランキングの下に挿入
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Cocoonの人気ランキングセクションを探す
            var rankingWidget = document.querySelector('.hrs-ranking-section');
            
            if (rankingWidget) {
                // ニュースHTMLを作成
                var newsDiv = document.createElement('div');
                newsDiv.className = 'hrs-news-top-page widget';
                newsDiv.innerHTML = <?php echo json_encode($html); ?>;
                
                // 人気ランキングの後に挿入
                rankingWidget.parentNode.insertBefore(newsDiv, rankingWidget.nextSibling);
                
                console.log('[HRS] ニュースウィジェットをトップページに追加しました');
            } else {
                console.warn('[HRS] 人気ランキングセクションが見つかりません');
            }
        });
        </script>
        <style>
        .hrs-news-top-page {
            margin-top: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        </style>
        <?php
    }

    /**
     * ウィジェット表示
     */
    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo $this->render_news_section($instance, false);
        echo $args['after_widget'];
    }

    /**
     * ニュースセクションHTML生成（共通）
     */
    private function render_news_section($instance, $include_title = false) {
        $show_news = $instance['show_news'] ?? true;
        $show_plans = $instance['show_plans'] ?? true;
        $news_count = (int) ($instance['news_count'] ?? 5);
        $plan_count = (int) ($instance['plan_count'] ?? 5);

        ob_start();
        
        echo '<div class="hrs-news-widget-content">';

        // ニュース表示
        if ($show_news) {
            $news = HRS_News_Plan_Updater::get_latest_news($news_count);
            if (!empty($news)) {
                echo '<div class="hrs-news-section">';
                echo '<h4 class="hrs-section-title"><span class="dashicons dashicons-megaphone"></span> 最新ニュース</h4>';
                echo '<ul class="hrs-news-list">';
                foreach ($news as $item) {
                    $this->render_item($item, 'news');
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        // 新プラン表示
        if ($show_plans) {
            $plans = HRS_News_Plan_Updater::get_latest_plans($plan_count);
            if (!empty($plans)) {
                echo '<div class="hrs-plans-section">';
                echo '<h4 class="hrs-section-title"><span class="dashicons dashicons-tag"></span> 新着プラン・キャンペーン</h4>';
                echo '<ul class="hrs-plans-list">';
                foreach ($plans as $item) {
                    $this->render_item($item, 'plan');
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        // データがない場合のメッセージ
        if ((empty($news) || !$show_news) && (empty($plans) || !$show_plans)) {
            echo '<p class="hrs-no-data">最新情報はまだありません</p>';
        }

        // 最終更新日時
        $last_updated = get_option('hrs_news_last_updated');
        if ($last_updated) {
            echo '<p class="hrs-last-updated">最終更新: ' . esc_html(date('n/j H:i', strtotime($last_updated))) . '</p>';
        }

        echo '</div>';
        
        // スタイル出力
        $this->render_styles();
        
        return ob_get_clean();
    }

    /**
     * アイテム表示
     */
    private function render_item($item, $type) {
        $date = !empty($item['date']) ? date('n/j', strtotime($item['date'])) : '';
        $source = esc_html($item['source'] ?? '');
        $hotel = esc_html($item['hotel_name'] ?? '');
        $title = esc_html($item['title'] ?? '');
        
        // リンク先: 楽天はMOSHIMO経由、それ以外は直リンク
        $url = esc_url($item['url'] ?? '#');
        
        echo '<li class="hrs-news-item hrs-' . esc_attr($type) . '-item">';
        echo '<span class="hrs-item-date">' . $date . '</span>';
        echo '<span class="hrs-item-source">' . $source . '</span>';
        echo '<div class="hrs-item-content">';
        echo '<a href="' . $url . '" class="hrs-hotel-link" target="_blank" rel="noopener noreferrer">' . $hotel . '</a>';
        echo '<a href="' . $url . '" class="hrs-item-title" target="_blank" rel="noopener noreferrer">' . $title . ' <span class="external-icon">↗</span></a>';
        echo '</div>';
        echo '</li>';
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
        .hrs-news-widget-content {
            padding: 10px 0;
        }
        .hrs-section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 15px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0073aa;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .hrs-section-title:first-child {
            margin-top: 0;
        }
        .hrs-section-title .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        .hrs-news-list,
        .hrs-plans-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .hrs-news-item {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            gap: 5px;
        }
        .hrs-news-item:last-child {
            border-bottom: none;
        }
        .hrs-item-date {
            color: #666;
            min-width: 35px;
            font-size: 11px;
            font-weight: 500;
        }
        .hrs-item-source {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            color: #555;
        }
        .hrs-plan-item .hrs-item-source {
            background: #fff3cd;
            color: #856404;
        }
        .hrs-item-content {
            flex: 1;
            min-width: 180px;
        }
        .hrs-hotel-link {
            display: block;
            font-weight: bold;
            color: #0073aa;
            text-decoration: none;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .hrs-hotel-link:hover {
            text-decoration: underline;
        }
        .hrs-item-title {
            display: block;
            color: #333;
            text-decoration: none;
            line-height: 1.5;
            font-size: 12px;
        }
        .hrs-item-title:hover {
            color: #0073aa;
        }
        .hrs-item-title .external-icon {
            font-size: 10px;
            opacity: 0.6;
            margin-left: 2px;
        }
        .hrs-no-data {
            color: #999;
            font-size: 13px;
            text-align: center;
            padding: 20px 10px;
        }
        .hrs-last-updated {
            font-size: 11px;
            color: #999;
            text-align: right;
            margin: 10px 0 0 0;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 600px) {
            .hrs-item-content {
                min-width: 100%;
            }
        }
        </style>
        <?php
    }

    /**
     * ウィジェット設定フォーム
     */
    public function form($instance) {
        $show_news = isset($instance['show_news']) ? (bool) $instance['show_news'] : true;
        $show_plans = isset($instance['show_plans']) ? (bool) $instance['show_plans'] : true;
        $news_count = (int) ($instance['news_count'] ?? 5);
        $plan_count = (int) ($instance['plan_count'] ?? 5);
        ?>
        <p>
            <strong>表示内容</strong>
        </p>
        <p>
            <input type="checkbox" id="<?php echo $this->get_field_id('show_news'); ?>" 
                   name="<?php echo $this->get_field_name('show_news'); ?>" value="1" 
                   <?php checked($show_news); ?>>
            <label for="<?php echo $this->get_field_id('show_news'); ?>">ニュースを表示</label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('news_count'); ?>">ニュース表示件数:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('news_count'); ?>" 
                   name="<?php echo $this->get_field_name('news_count'); ?>" type="number" 
                   min="1" max="20" value="<?php echo esc_attr($news_count); ?>">
        </p>
        <p style="border-top: 1px solid #eee; padding-top: 10px;">
            <input type="checkbox" id="<?php echo $this->get_field_id('show_plans'); ?>" 
                   name="<?php echo $this->get_field_name('show_plans'); ?>" value="1" 
                   <?php checked($show_plans); ?>>
            <label for="<?php echo $this->get_field_id('show_plans'); ?>">新プランを表示</label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('plan_count'); ?>">プラン表示件数:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('plan_count'); ?>" 
                   name="<?php echo $this->get_field_name('plan_count'); ?>" type="number" 
                   min="1" max="20" value="<?php echo esc_attr($plan_count); ?>">
        </p>
        <p style="background: #f0f6fc; padding: 10px; border-left: 3px solid #0073aa; margin-top: 10px;">
            <strong>📍 表示場所</strong><br>
            <small>• トップページ: 人気ランキングの下<br>• 投稿ページ: サイドバー</small>
        </p>
        <?php
    }

    /**
     * ウィジェット設定保存
     */
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['show_news'] = !empty($new_instance['show_news']);
        $instance['show_plans'] = !empty($new_instance['show_plans']);
        $instance['news_count'] = absint($new_instance['news_count'] ?? 5);
        $instance['plan_count'] = absint($new_instance['plan_count'] ?? 5);
        return $instance;
    }
}

/**
 * ショートコード: [hrs_latest_news]
 */
function hrs_latest_news_shortcode($atts) {
    $atts = shortcode_atts([
        'type' => 'both', // news, plans, both
        'count' => 5,
    ], $atts);

    ob_start();
    
    echo '<div class="hrs-news-shortcode">';
    
    if ($atts['type'] === 'news' || $atts['type'] === 'both') {
        $news = HRS_News_Plan_Updater::get_latest_news((int) $atts['count']);
        if (!empty($news)) {
            echo '<div class="hrs-news-section">';
            echo '<h3>📰 最新ニュース</h3>';
            echo '<ul class="hrs-news-list">';
            foreach ($news as $item) {
                $date = !empty($item['date']) ? date('n/j', strtotime($item['date'])) : '';
                $url = esc_url($item['url'] ?? '#');
                echo '<li>';
                echo '<span class="date">' . esc_html($date) . '</span> ';
                echo '<span class="source">[' . esc_html($item['source'] ?? '') . ']</span> ';
                echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($item['hotel_name'] ?? '') . '</a>: ';
                echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($item['title'] ?? '') . ' ↗</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    if ($atts['type'] === 'plans' || $atts['type'] === 'both') {
        $plans = HRS_News_Plan_Updater::get_latest_plans((int) $atts['count']);
        if (!empty($plans)) {
            echo '<div class="hrs-plans-section">';
            echo '<h3>🏷️ 新着プラン</h3>';
            echo '<ul class="hrs-plans-list">';
            foreach ($plans as $item) {
                $date = !empty($item['date']) ? date('n/j', strtotime($item['date'])) : '';
                $url = esc_url($item['url'] ?? '#');
                echo '<li>';
                echo '<span class="date">' . esc_html($date) . '</span> ';
                echo '<span class="source">[' . esc_html($item['source'] ?? '') . ']</span> ';
                echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($item['hotel_name'] ?? '') . '</a>: ';
                echo '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($item['title'] ?? '') . ' ↗</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }
    
    echo '</div>';
    
    echo '<style>
    .hrs-news-shortcode { margin: 20px 0; }
    .hrs-news-shortcode h3 { border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
    .hrs-news-shortcode ul { list-style: none; padding: 0; }
    .hrs-news-shortcode li { padding: 8px 0; border-bottom: 1px solid #eee; }
    .hrs-news-shortcode .date { color: #666; font-size: 0.9em; }
    .hrs-news-shortcode .source { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.85em; }
    </style>';
    
    return ob_get_clean();
}
add_shortcode('hrs_latest_news', 'hrs_latest_news_shortcode');

/**
 * ウィジェット登録
 */
add_action('widgets_init', function() {
    register_widget('HRS_News_Widget');
});