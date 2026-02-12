<?php
/**
 * HRS News & Plan Widget
 * 
 * トップページ（人気ランキング下）と投稿ページのサイドバーに最新ニュース・プラン情報を表示
 *
 * @package HRS
 * @version 2.5.0
 * 変更点:
 * - ✅ トップページ重複表示を修正（inject_to_top_page削除、front-page.phpで直接出力）
 * - ✅ サイドバーウィジェットはトップページ以外でのみ表示
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
            '📰 ホテルニュース・プラン',
            [
                'description' => 'ホテルの最新ニュース・新プラン情報を表示します（サイドバー用）',
                'classname' => 'hrs-news-widget',
            ]
        );
        // ✅ 削除: inject_to_top_page（重複の原因）
    }

    /**
     * ウィジェット表示（サイドバー用）
     * トップページでは表示しない
     */
    public function widget($args, $instance) {
        // ✅ トップページではウィジェットを表示しない（front-page.phpで直接出力するため）
        if (is_front_page() || is_home()) {
            return;
        }
        
        echo $args['before_widget'];
        echo $this->render_news_section($instance, false);
        echo $args['after_widget'];
    }

    /**
     * ニュースセクションHTML生成（共通）
     * 外部からも呼び出し可能（front-page.php用）
     */
    public function render_news_section($instance, $include_title = false) {
        $show_news = $instance['show_news'] ?? true;
        $show_plans = $instance['show_plans'] ?? true;
        $news_count = (int) ($instance['news_count'] ?? 5);
        $plan_count = (int) ($instance['plan_count'] ?? 5);

        // HRS_News_Plan_Updater が存在するか確認
        if (!class_exists('HRS_News_Plan_Updater')) {
            return '<p class="hrs-no-data">ニュース機能が有効化されていません</p>';
        }

        ob_start();
        
        echo '<div class="hrs-news-widget-content">';

        $has_content = false;

        // ニュース表示
        if ($show_news) {
            $news = HRS_News_Plan_Updater::get_latest_news($news_count);
            if (!empty($news)) {
                $has_content = true;
                echo '<div class="hrs-news-section">';
                echo '<h4 class="hrs-news-title"><span class="hrs-news-icon">📰</span> 最新ニュース</h4>';
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
                $has_content = true;
                echo '<div class="hrs-plans-section">';
                echo '<h4 class="hrs-news-title"><span class="hrs-news-icon">🏷️</span> 新着プラン・キャンペーン</h4>';
                echo '<ul class="hrs-plans-list">';
                foreach ($plans as $item) {
                    $this->render_item($item, 'plan');
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        // データがない場合のメッセージ
        if (!$has_content) {
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
        $title = esc_html($item['title'] ?? '');
        $url = esc_url($item['url'] ?? '#');
        
        echo '<li class="hrs-news-item hrs-' . esc_attr($type) . '-item">';
        echo '<span class="hrs-item-date">' . $date . '</span>';
        echo '<span class="hrs-item-source">' . $source . '</span>';
        echo '<div class="hrs-item-content">';
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
        .hrs-news-title {
            font-size: 14px;
            font-weight: bold;
            margin: 15px 0 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #4a7c59;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .hrs-news-title:first-child {
            margin-top: 0;
        }
        .hrs-news-icon {
            font-size: 16px;
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
        .hrs-item-title {
            display: block;
            color: #333;
            text-decoration: none;
            line-height: 1.5;
            font-size: 12px;
            font-weight: bold;
        }
        .hrs-item-title:hover {
            color: #4a7c59;
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
        
        /* トップページ用スタイル */
        .hrs-news-top-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 30px;
        }
        .hrs-news-top-section .hrs-news-title {
            font-size: 16px;
        }
        
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
        <p style="background: #f0f6fc; padding: 10px; border-left: 3px solid #4a7c59; margin-top: 10px;">
            <strong>📍 表示場所</strong><br>
            <small>• トップページ: 人気ランキングの下（テンプレートで出力）<br>• 投稿ページ: サイドバー</small>
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
 * トップページ用ニュースセクション出力関数
 * front-page.phpから呼び出す
 */
function hrs_render_news_section_for_front_page() {
    if (!class_exists('HRS_News_Plan_Updater')) {
        return '';
    }
    
    $widget = new HRS_News_Widget();
    $instance = [
        'show_news' => true,
        'show_plans' => true,
        'news_count' => 5,
        'plan_count' => 5,
    ];
    
    // ウィジェット設定があれば使用
    $widget_options = get_option('widget_hrs_news_widget', []);
    if (!empty($widget_options)) {
        foreach ($widget_options as $key => $opt) {
            if (is_array($opt) && isset($opt['show_news'])) {
                $instance = $opt;
                break;
            }
        }
    }
    
    return '<div class="hrs-news-top-section">' . $widget->render_news_section($instance, false) . '</div>';
}

/**
 * ショートコード: [hrs_latest_news]
 */
function hrs_latest_news_shortcode($atts) {
    $atts = shortcode_atts([
        'type' => 'both',
        'count' => 5,
    ], $atts);

    if (!class_exists('HRS_News_Plan_Updater')) {
        return '<p>ニュース機能が有効化されていません</p>';
    }

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
    .hrs-news-shortcode h3 { border-bottom: 2px solid #4a7c59; padding-bottom: 10px; }
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