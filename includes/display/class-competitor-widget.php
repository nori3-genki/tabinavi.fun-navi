<?php
/**
 * HRS Competitor Comparison Widget
 * 
 * サイドバーに同エリアの競合ホテル比較テーブルを表示
 *
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Competitor_Widget extends WP_Widget {

    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct(
            'hrs_competitor_widget',
            '🏨 エリア内ホテル比較',
            [
                'description' => '同エリアの競合ホテルを価格・評価で比較表示',
                'classname' => 'hrs-competitor-widget',
            ]
        );
    }

    /**
     * ウィジェット表示
     */
    public function widget($args, $instance) {
        // ホテルレビュー記事ページのみ表示
        if (!is_singular('hotel-review')) {
            return;
        }

        $post_id = get_the_ID();
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        
        if (empty($hotel_name)) {
            return;
        }

        // エリア（都道府県）取得
        $area = $this->get_hotel_area($post_id);
        if (empty($area)) {
            return;
        }

        // 競合ホテル取得
        $max_hotels = (int) ($instance['max_hotels'] ?? 5);
        $competitors = $this->get_competitors($post_id, $area, $max_hotels);

        if (empty($competitors)) {
            return;
        }

        $title = apply_filters('widget_title', $instance['title'] ?? $area . 'の人気ホテル');
        $show_price = !empty($instance['show_price']);
        $show_rating = !empty($instance['show_rating']);

        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $this->render_comparison_table($competitors, $post_id, $show_price, $show_rating);
        $this->render_styles();

        echo $args['after_widget'];
    }

    /**
     * ホテルのエリア（都道府県）取得
     */
    private function get_hotel_area($post_id) {
        // まず _hrs_prefecture を確認
        $prefecture = get_post_meta($post_id, '_hrs_prefecture', true);
        if (!empty($prefecture)) {
            return $prefecture;
        }

        // _hrs_location から抽出
        $location = get_post_meta($post_id, '_hrs_location', true);
        if (!empty($location)) {
            return $this->extract_prefecture($location);
        }

        // タクソノミーから取得
        $terms = wp_get_object_terms($post_id, ['hotel-area', 'hotel_area', 'area'], ['fields' => 'names']);
        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0];
        }

        return '';
    }

    /**
     * 都道府県抽出
     */
    private function extract_prefecture($location) {
        $prefectures = [
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
        ];

        foreach ($prefectures as $pref) {
            if (mb_strpos($location, $pref) !== false) {
                return $pref;
            }
        }

        return '';
    }

    /**
     * 同エリアの競合ホテル取得
     */
    private function get_competitors($current_post_id, $area, $limit = 5) {
        global $wpdb;

        // 同エリアのホテル記事を取得
        $posts = get_posts([
            'post_type' => 'hotel-review',
            'post_status' => 'publish',
            'posts_per_page' => $limit + 1, // 自分を除くため+1
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_hrs_prefecture',
                    'value' => $area,
                    'compare' => '=',
                ],
                [
                    'key' => '_hrs_location',
                    'value' => $area,
                    'compare' => 'LIKE',
                ],
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => '_hrs_hqc_score',
            'order' => 'DESC',
        ]);

        $competitors = [];
        foreach ($posts as $post) {
            // 自分自身は除外しない（比較表示のため）
            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
            $min_price = get_post_meta($post->ID, '_hrs_min_price', true);
            $rating = get_post_meta($post->ID, '_hrs_rakuten_rating', true);
            $hqc_score = get_post_meta($post->ID, '_hrs_hqc_score', true);

            // 価格がない場合は楽天APIから取得を試行
            if (empty($min_price)) {
                $min_price = $this->fetch_price_from_rakuten($hotel_name);
                if ($min_price) {
                    update_post_meta($post->ID, '_hrs_min_price', $min_price);
                }
            }

            // 評価がない場合
            if (empty($rating)) {
                $rating = $this->fetch_rating_from_rakuten($hotel_name);
                if ($rating) {
                    update_post_meta($post->ID, '_hrs_rakuten_rating', $rating);
                }
            }

            $competitors[] = [
                'post_id' => $post->ID,
                'hotel_name' => $hotel_name ?: $post->post_title,
                'url' => get_permalink($post->ID),
                'min_price' => (int) $min_price,
                'rating' => (float) $rating,
                'hqc_score' => (float) $hqc_score,
                'is_current' => ($post->ID === $current_post_id),
            ];
        }

        // 価格でソート（安い順）
        usort($competitors, function($a, $b) {
            if ($a['min_price'] === 0) return 1;
            if ($b['min_price'] === 0) return -1;
            return $a['min_price'] - $b['min_price'];
        });

        return array_slice($competitors, 0, $limit);
    }

    /**
     * 楽天APIから価格取得（簡易版）
     */
    private function fetch_price_from_rakuten($hotel_name) {
        if (!class_exists('HRS_Rakuten_API_Test_Endpoint')) {
            return 0;
        }

        try {
            $api = new HRS_Rakuten_API_Test_Endpoint();
            $result = $api->search_hotel($hotel_name, 1);
            
            if (!empty($result['success']) && !empty($result['hotels'][0]['min_charge'])) {
                return (int) $result['hotels'][0]['min_charge'];
            }
        } catch (Exception $e) {
            error_log('[HRS Competitor] Price fetch error: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * 楽天APIから評価取得（簡易版）
     */
    private function fetch_rating_from_rakuten($hotel_name) {
        if (!class_exists('HRS_Rakuten_API_Test_Endpoint')) {
            return 0;
        }

        try {
            $api = new HRS_Rakuten_API_Test_Endpoint();
            $result = $api->search_hotel($hotel_name, 1);
            
            if (!empty($result['success']) && !empty($result['hotels'][0]['review_average'])) {
                return (float) $result['hotels'][0]['review_average'];
            }
        } catch (Exception $e) {
            error_log('[HRS Competitor] Rating fetch error: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * 比較テーブル表示
     */
    private function render_comparison_table($competitors, $current_post_id, $show_price, $show_rating) {
        ?>
        <div class="hrs-competitor-table-wrap">
            <table class="hrs-competitor-table">
                <thead>
                    <tr>
                        <th class="col-hotel">ホテル</th>
                        <?php if ($show_price): ?>
                        <th class="col-price">価格</th>
                        <?php endif; ?>
                        <?php if ($show_rating): ?>
                        <th class="col-rating">評価</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($competitors as $hotel): 
                        $is_current = $hotel['is_current'];
                        $row_class = $is_current ? 'current-hotel' : '';
                    ?>
                    <tr class="<?php echo esc_attr($row_class); ?>">
                        <td class="col-hotel">
                            <?php if ($is_current): ?>
                                <span class="current-badge">👀</span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($hotel['url']); ?>" class="hotel-link">
                                <?php echo esc_html($this->truncate_name($hotel['hotel_name'], 15)); ?>
                            </a>
                        </td>
                        <?php if ($show_price): ?>
                        <td class="col-price">
                            <?php if ($hotel['min_price'] > 0): ?>
                                <span class="price">¥<?php echo number_format($hotel['min_price']); ?>〜</span>
                            <?php else: ?>
                                <span class="no-data">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if ($show_rating): ?>
                        <td class="col-rating">
                            <?php if ($hotel['rating'] > 0): ?>
                                <span class="rating">
                                    <span class="star">★</span>
                                    <?php echo number_format($hotel['rating'], 1); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-data">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $last_updated = get_option('hrs_price_last_updated', '');
            $update_date = $last_updated ? date('n/j', strtotime($last_updated)) : '';
            ?>
            <p class="hrs-competitor-note">
                <small>※価格は楽天トラベル参考<?php echo $update_date ? "（{$update_date}時点）" : ''; ?></small>
            </p>
        </div>
        <?php
    }

    /**
     * ホテル名を短縮
     */
    private function truncate_name($name, $length) {
        if (mb_strlen($name) <= $length) {
            return $name;
        }
        return mb_substr($name, 0, $length) . '…';
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
        .hrs-competitor-widget {
            padding: 0 !important;
        }
        .hrs-competitor-table-wrap {
            overflow-x: auto;
        }
        .hrs-competitor-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin: 0;
        }
        .hrs-competitor-table th {
            background: #f8f9fa;
            padding: 8px 6px;
            text-align: left;
            font-size: 11px;
            color: #666;
            border-bottom: 2px solid #dee2e6;
        }
        .hrs-competitor-table td {
            padding: 10px 6px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .hrs-competitor-table tr:last-child td {
            border-bottom: none;
        }
        .hrs-competitor-table tr.current-hotel {
            background: #fff8e6;
        }
        .hrs-competitor-table tr.current-hotel td {
            font-weight: bold;
        }
        .hrs-competitor-table .current-badge {
            font-size: 12px;
            margin-right: 3px;
        }
        .hrs-competitor-table .hotel-link {
            color: #0073aa;
            text-decoration: none;
            display: block;
            line-height: 1.3;
        }
        .hrs-competitor-table .hotel-link:hover {
            text-decoration: underline;
            color: #005177;
        }
        .hrs-competitor-table .col-price {
            text-align: right;
            white-space: nowrap;
        }
        .hrs-competitor-table .price {
            color: #d63638;
            font-weight: bold;
            font-size: 12px;
        }
        .hrs-competitor-table .col-rating {
            text-align: center;
            white-space: nowrap;
        }
        .hrs-competitor-table .rating {
            color: #f59e0b;
            font-weight: bold;
        }
        .hrs-competitor-table .rating .star {
            color: #f59e0b;
        }
        .hrs-competitor-table .no-data {
            color: #ccc;
        }
        .hrs-competitor-note {
            text-align: right;
            margin: 8px 0 0 0;
            color: #999;
        }
        
        /* ホバーエフェクト */
        .hrs-competitor-table tr:not(.current-hotel):hover {
            background: #f8f9fa;
        }
        </style>
        <?php
    }

    /**
     * ウィジェット設定フォーム
     */
    public function form($instance) {
        $title = $instance['title'] ?? 'エリア内人気ホテル';
        $max_hotels = (int) ($instance['max_hotels'] ?? 5);
        $show_price = isset($instance['show_price']) ? (bool) $instance['show_price'] : true;
        $show_rating = isset($instance['show_rating']) ? (bool) $instance['show_rating'] : true;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">タイトル:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>">
            <small>※空欄の場合「〇〇県の人気ホテル」と自動表示</small>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('max_hotels'); ?>">表示件数:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('max_hotels'); ?>" 
                   name="<?php echo $this->get_field_name('max_hotels'); ?>" type="number" 
                   min="3" max="10" value="<?php echo esc_attr($max_hotels); ?>">
            件
        </p>
        <p>
            <input type="checkbox" id="<?php echo $this->get_field_id('show_price'); ?>" 
                   name="<?php echo $this->get_field_name('show_price'); ?>" value="1" 
                   <?php checked($show_price); ?>>
            <label for="<?php echo $this->get_field_id('show_price'); ?>">価格を表示</label>
        </p>
        <p>
            <input type="checkbox" id="<?php echo $this->get_field_id('show_rating'); ?>" 
                   name="<?php echo $this->get_field_name('show_rating'); ?>" value="1" 
                   <?php checked($show_rating); ?>>
            <label for="<?php echo $this->get_field_id('show_rating'); ?>">評価を表示</label>
        </p>
        <?php
    }

    /**
     * ウィジェット設定保存
     */
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['max_hotels'] = absint($new_instance['max_hotels'] ?? 5);
        $instance['show_price'] = !empty($new_instance['show_price']);
        $instance['show_rating'] = !empty($new_instance['show_rating']);
        return $instance;
    }
}

/**
 * ウィジェット登録
 */
add_action('widgets_init', function() {
    register_widget('HRS_Competitor_Widget');
});

/**
 * ショートコード: [hrs_competitors]
 */
function hrs_competitors_shortcode($atts) {
    if (!is_singular('hotel-review')) {
        return '';
    }

    $atts = shortcode_atts([
        'max' => 5,
        'show_price' => 'yes',
        'show_rating' => 'yes',
    ], $atts);

    // ウィジェットインスタンスを作成して表示
    $widget = new HRS_Competitor_Widget();
    
    ob_start();
    $widget->widget(
        ['before_widget' => '<div class="hrs-competitor-shortcode">', 'after_widget' => '</div>', 'before_title' => '<h3>', 'after_title' => '</h3>'],
        [
            'title' => '',
            'max_hotels' => (int) $atts['max'],
            'show_price' => $atts['show_price'] === 'yes',
            'show_rating' => $atts['show_rating'] === 'yes',
        ]
    );
    return ob_get_clean();
}
add_shortcode('hrs_competitors', 'hrs_competitors_shortcode');