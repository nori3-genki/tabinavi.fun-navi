<?php
/**
 * 最新ホテルレビューウィジェット
 * 
 * サイドバーに最新のホテルレビュー記事を表示
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Recent_Reviews_Widget extends WP_Widget {

    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct(
            'hrs_recent_reviews',
            '📝 最新ホテルレビュー',
            array(
                'description' => '最新のホテルレビュー記事を表示します',
                'classname'   => 'hrs-recent-reviews-widget',
            )
        );
    }

    /**
     * フロントエンド表示
     */
    public function widget($args, $instance) {
        $title      = !empty($instance['title']) ? $instance['title'] : '新着ホテルレビュー';
        $max_posts  = !empty($instance['max_posts']) ? absint($instance['max_posts']) : 5;
        $show_thumb = isset($instance['show_thumb']) ? (bool) $instance['show_thumb'] : true;
        $show_date  = isset($instance['show_date']) ? (bool) $instance['show_date'] : true;

        // 最新記事を取得
        $query_args = array(
            'post_type'      => 'hotel-review',
            'posts_per_page' => $max_posts,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts = get_posts($query_args);

        if (empty($posts)) {
            return;
        }

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        echo '<ul class="hrs-recent-reviews-list">';

        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            $post_title = get_the_title($post->ID);
            $post_date = get_the_date('Y.m.d', $post->ID);
            
            // 都道府県を取得
            $prefecture = '';
            $terms = get_the_terms($post->ID, 'hotel-category');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($term->parent === 0) {
                        $prefecture = $term->name;
                        break;
                    }
                }
            }

            echo '<li class="hrs-recent-review-item">';
            echo '<a href="' . esc_url($permalink) . '">';

            // サムネイル
            if ($show_thumb) {
                echo '<div class="hrs-review-thumb">';
                if (has_post_thumbnail($post->ID)) {
                    echo get_the_post_thumbnail($post->ID, 'thumbnail', array('loading' => 'lazy'));
                } else {
                    echo '<div class="hrs-no-thumb">🏨</div>';
                }
                echo '</div>';
            }

            echo '<div class="hrs-review-info">';
            echo '<span class="hrs-review-title">' . esc_html($post_title) . '</span>';
            
            if ($prefecture || $show_date) {
                echo '<span class="hrs-review-meta">';
                if ($prefecture) {
                    echo '<span class="hrs-review-pref">📍' . esc_html($prefecture) . '</span>';
                }
                if ($show_date) {
                    echo '<span class="hrs-review-date">' . esc_html($post_date) . '</span>';
                }
                echo '</span>';
            }
            
            echo '</div>';
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';

        echo $args['after_widget'];
    }

    /**
     * 管理画面フォーム
     */
    public function form($instance) {
        $title      = !empty($instance['title']) ? $instance['title'] : '新着ホテルレビュー';
        $max_posts  = !empty($instance['max_posts']) ? absint($instance['max_posts']) : 5;
        $show_thumb = isset($instance['show_thumb']) ? (bool) $instance['show_thumb'] : true;
        $show_date  = isset($instance['show_date']) ? (bool) $instance['show_date'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">タイトル:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('max_posts')); ?>">表示件数:</label>
            <input class="tiny-text" 
                   id="<?php echo esc_attr($this->get_field_id('max_posts')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('max_posts')); ?>" 
                   type="number" 
                   min="1" 
                   max="20" 
                   value="<?php echo esc_attr($max_posts); ?>">
        </p>
        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_thumb')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_thumb')); ?>" 
                   <?php checked($show_thumb); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_thumb')); ?>">サムネイルを表示</label>
        </p>
        <p>
            <input class="checkbox" 
                   type="checkbox" 
                   id="<?php echo esc_attr($this->get_field_id('show_date')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_date')); ?>" 
                   <?php checked($show_date); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_date')); ?>">日付を表示</label>
        </p>
        <?php
    }

    /**
     * 設定保存
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title']      = sanitize_text_field($new_instance['title'] ?? '');
        $instance['max_posts']  = absint($new_instance['max_posts'] ?? 5);
        $instance['show_thumb'] = !empty($new_instance['show_thumb']);
        $instance['show_date']  = !empty($new_instance['show_date']);
        return $instance;
    }
}

/**
 * ウィジェット登録
 */
add_action('widgets_init', function() {
    register_widget('HRS_Recent_Reviews_Widget');
});

/**
 * スタイル
 */
add_action('wp_head', function() {
    if (!is_active_widget(false, false, 'hrs_recent_reviews', true)) {
        return;
    }
    ?>
    <style>
    .hrs-recent-reviews-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .hrs-recent-review-item {
        margin-bottom: 0;
        border-bottom: 1px solid #eee;
    }
    .hrs-recent-review-item:last-child {
        border-bottom: none;
    }
    .hrs-recent-review-item a {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 5px;
        text-decoration: none;
        color: #333;
        transition: background 0.2s;
    }
    .hrs-recent-review-item a:hover {
        background: #f9f9f9;
    }
    .hrs-review-thumb {
        flex-shrink: 0;
        width: 60px;
        height: 60px;
        border-radius: 6px;
        overflow: hidden;
        background: #f5f5f5;
    }
    .hrs-review-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .hrs-no-thumb {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        background: #e8f4f8;
    }
    .hrs-review-info {
        flex: 1;
        min-width: 0;
    }
    .hrs-review-title {
        display: block;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.4;
        color: #333;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .hrs-review-meta {
        display: flex;
        gap: 8px;
        margin-top: 4px;
        font-size: 11px;
        color: #888;
    }
    .hrs-review-pref {
        color: #666;
    }
    </style>
    <?php
});