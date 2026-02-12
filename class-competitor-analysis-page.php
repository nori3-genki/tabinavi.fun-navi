<?php
/**
 * 5D Review Builder - Competitor Analysis Page
 *
 * 競合ホテル自動提案・分析ページ
 *
 * @package Hotel_Review_System
 * @version 1.0.0
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Competitor Analysis Page Class
 */
class HRS_Competitor_Analysis_Page {

    /**
     * 楽天APIアプリID
     */
    private $rakuten_app_id;

    /**
     * Constructor
     */
    public function __construct() {
        $this->rakuten_app_id = get_option('hrs_rakuten_app_id', '');
        add_action('wp_ajax_hrs_search_competitors', [$this, 'ajax_search_competitors']);
        add_action('wp_ajax_hrs_get_hotel_competitors', [$this, 'ajax_get_hotel_competitors']);
    }

    /**
     * ページレンダリング
     */
    public function render() {
        ?>
        <div class="wrap hrs-competitor-analysis">
            <h1>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e('競合分析', '5d-review-builder'); ?>
            </h1>

            <?php $this->render_api_notice(); ?>

            <div class="hrs-competitor-container">
                <!-- 検索フォーム -->
                <div class="hrs-competitor-search-panel">
                    <h2><?php esc_html_e('競合ホテル検索', '5d-review-builder'); ?></h2>
                    
                    <div class="hrs-search-form">
                        <div class="hrs-form-row">
                            <label for="hrs-area-select"><?php esc_html_e('エリア', '5d-review-builder'); ?></label>
                            <select id="hrs-area-select" class="hrs-select">
                                <option value=""><?php esc_html_e('-- 都道府県を選択 --', '5d-review-builder'); ?></option>
                                <?php echo $this->get_prefecture_options(); ?>
                            </select>
                        </div>

                        <div class="hrs-form-row">
                            <label for="hrs-price-min"><?php esc_html_e('価格帯', '5d-review-builder'); ?></label>
                            <div class="hrs-price-range">
                                <input type="number" id="hrs-price-min" placeholder="下限" min="0" step="1000">
                                <span>〜</span>
                                <input type="number" id="hrs-price-max" placeholder="上限" min="0" step="1000">
                                <span>円</span>
                            </div>
                        </div>

                        <div class="hrs-form-row">
                            <label for="hrs-keyword"><?php esc_html_e('キーワード', '5d-review-builder'); ?></label>
                            <input type="text" id="hrs-keyword" placeholder="温泉、露天風呂など">
                        </div>

                        <div class="hrs-form-row">
                            <button type="button" id="hrs-search-btn" class="button button-primary">
                                <span class="dashicons dashicons-search"></span>
                                <?php esc_html_e('検索', '5d-review-builder'); ?>
                            </button>
                            <button type="button" id="hrs-reset-btn" class="button">
                                <?php esc_html_e('リセット', '5d-review-builder'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 既存記事からの競合提案 -->
                <div class="hrs-existing-articles-panel">
                    <h2><?php esc_html_e('既存記事の競合', '5d-review-builder'); ?></h2>
                    <?php $this->render_existing_articles_list(); ?>
                </div>
            </div>

            <!-- 検索結果 -->
            <div id="hrs-competitor-results" class="hrs-competitor-results" style="display:none;">
                <h2>
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('検索結果', '5d-review-builder'); ?>
                    <span id="hrs-result-count"></span>
                </h2>
                <div id="hrs-results-container"></div>
            </div>

            <!-- ローディング -->
            <div id="hrs-loading" class="hrs-loading" style="display:none;">
                <span class="spinner is-active"></span>
                <span><?php esc_html_e('検索中...', '5d-review-builder'); ?></span>
            </div>
        </div>

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    /**
     * API未設定通知
     */
    private function render_api_notice() {
        if (empty($this->rakuten_app_id)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('楽天APIが未設定です。', '5d-review-builder'); ?></strong>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=5d-review-builder-settings&tab=api')); ?>">
                        <?php esc_html_e('設定画面へ', '5d-review-builder'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * 都道府県オプション
     */
    private function get_prefecture_options() {
        $prefectures = [
            'hokkaido' => ['name' => '北海道', 'code' => '011000'],
            'aomori' => ['name' => '青森県', 'code' => '020100'],
            'iwate' => ['name' => '岩手県', 'code' => '030100'],
            'miyagi' => ['name' => '宮城県', 'code' => '040100'],
            'akita' => ['name' => '秋田県', 'code' => '050100'],
            'yamagata' => ['name' => '山形県', 'code' => '060100'],
            'fukushima' => ['name' => '福島県', 'code' => '070100'],
            'ibaraki' => ['name' => '茨城県', 'code' => '080100'],
            'tochigi' => ['name' => '栃木県', 'code' => '090100'],
            'gunma' => ['name' => '群馬県', 'code' => '100100'],
            'saitama' => ['name' => '埼玉県', 'code' => '110100'],
            'chiba' => ['name' => '千葉県', 'code' => '120100'],
            'tokyo' => ['name' => '東京都', 'code' => '130100'],
            'kanagawa' => ['name' => '神奈川県', 'code' => '140100'],
            'niigata' => ['name' => '新潟県', 'code' => '150100'],
            'toyama' => ['name' => '富山県', 'code' => '160100'],
            'ishikawa' => ['name' => '石川県', 'code' => '170100'],
            'fukui' => ['name' => '福井県', 'code' => '180100'],
            'yamanashi' => ['name' => '山梨県', 'code' => '190100'],
            'nagano' => ['name' => '長野県', 'code' => '200100'],
            'gifu' => ['name' => '岐阜県', 'code' => '210100'],
            'shizuoka' => ['name' => '静岡県', 'code' => '220100'],
            'aichi' => ['name' => '愛知県', 'code' => '230100'],
            'mie' => ['name' => '三重県', 'code' => '240100'],
            'shiga' => ['name' => '滋賀県', 'code' => '250100'],
            'kyoto' => ['name' => '京都府', 'code' => '260100'],
            'osaka' => ['name' => '大阪府', 'code' => '270100'],
            'hyogo' => ['name' => '兵庫県', 'code' => '280100'],
            'nara' => ['name' => '奈良県', 'code' => '290100'],
            'wakayama' => ['name' => '和歌山県', 'code' => '300100'],
            'tottori' => ['name' => '鳥取県', 'code' => '310100'],
            'shimane' => ['name' => '島根県', 'code' => '320100'],
            'okayama' => ['name' => '岡山県', 'code' => '330100'],
            'hiroshima' => ['name' => '広島県', 'code' => '340100'],
            'yamaguchi' => ['name' => '山口県', 'code' => '350100'],
            'tokushima' => ['name' => '徳島県', 'code' => '360100'],
            'kagawa' => ['name' => '香川県', 'code' => '370100'],
            'ehime' => ['name' => '愛媛県', 'code' => '380100'],
            'kochi' => ['name' => '高知県', 'code' => '390100'],
            'fukuoka' => ['name' => '福岡県', 'code' => '400100'],
            'saga' => ['name' => '佐賀県', 'code' => '410100'],
            'nagasaki' => ['name' => '長崎県', 'code' => '420100'],
            'kumamoto' => ['name' => '熊本県', 'code' => '430100'],
            'oita' => ['name' => '大分県', 'code' => '440100'],
            'miyazaki' => ['name' => '宮崎県', 'code' => '450100'],
            'kagoshima' => ['name' => '鹿児島県', 'code' => '460100'],
            'okinawa' => ['name' => '沖縄県', 'code' => '470100'],
        ];

        $options = '';
        foreach ($prefectures as $key => $pref) {
            $options .= sprintf(
                '<option value="%s" data-code="%s">%s</option>',
                esc_attr($key),
                esc_attr($pref['code']),
                esc_html($pref['name'])
            );
        }
        return $options;
    }

    /**
     * 既存記事一覧
     */
    private function render_existing_articles_list() {
        $args = [
            'post_type' => 'hotel-review',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
        ];
        $posts = get_posts($args);

        if (empty($posts)) {
            echo '<p class="hrs-no-articles">' . esc_html__('記事がありません', '5d-review-builder') . '</p>';
            return;
        }

        echo '<ul class="hrs-article-list">';
        foreach ($posts as $post) {
            $hotel_id = get_post_meta($post->ID, '_hrs_rakuten_hotel_id', true);
            $area = get_post_meta($post->ID, '_hrs_area', true);
            $prefecture = get_post_meta($post->ID, '_hrs_prefecture', true);
            
            printf(
                '<li class="hrs-article-item" data-post-id="%d" data-hotel-id="%s" data-area="%s">
                    <span class="hrs-article-title">%s</span>
                    <span class="hrs-article-meta">%s</span>
                    <button type="button" class="button button-small hrs-find-competitors" data-post-id="%d">
                        <span class="dashicons dashicons-search"></span> 競合を探す
                    </button>
                </li>',
                esc_attr($post->ID),
                esc_attr($hotel_id),
                esc_attr($area ?: $prefecture),
                esc_html($post->post_title),
                esc_html($prefecture),
                esc_attr($post->ID)
            );
        }
        echo '</ul>';
    }

    /**
     * AJAX: 競合検索
     */
    public function ajax_search_competitors() {
        check_ajax_referer('hrs_competitor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $area_code = sanitize_text_field($_POST['area_code'] ?? '');
        $price_min = intval($_POST['price_min'] ?? 0);
        $price_max = intval($_POST['price_max'] ?? 0);
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');

        if (empty($area_code) && empty($keyword)) {
            wp_send_json_error(['message' => 'エリアまたはキーワードを指定してください']);
        }

        $results = $this->search_rakuten_hotels($area_code, $price_min, $price_max, $keyword);

        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
        }

        // 既存記事との照合
        $results = $this->mark_existing_articles($results);

        wp_send_json_success([
            'hotels' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * AJAX: 特定ホテルの競合取得
     */
    public function ajax_get_hotel_competitors() {
        check_ajax_referer('hrs_competitor_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => '記事IDが必要です']);
        }

        $hotel_id = get_post_meta($post_id, '_hrs_rakuten_hotel_id', true);
        $area = get_post_meta($post_id, '_hrs_area', true);
        $prefecture = get_post_meta($post_id, '_hrs_prefecture', true);
        $price = get_post_meta($post_id, '_hrs_price_min', true);

        // エリアコードを取得
        $area_code = $this->get_area_code_from_prefecture($area ?: $prefecture);

        if (empty($area_code)) {
            wp_send_json_error(['message' => 'エリア情報が取得できません']);
        }

        // 価格帯の範囲を設定（±30%）
        $price_min = $price ? intval($price * 0.7) : 0;
        $price_max = $price ? intval($price * 1.3) : 0;

        $results = $this->search_rakuten_hotels($area_code, $price_min, $price_max, '');

        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
        }

        // 自身を除外
        $results = array_filter($results, function($hotel) use ($hotel_id) {
            return $hotel['hotelNo'] != $hotel_id;
        });

        // 既存記事との照合
        $results = $this->mark_existing_articles(array_values($results));

        wp_send_json_success([
            'hotels' => $results,
            'count' => count($results),
            'source_post' => get_the_title($post_id),
        ]);
    }

    /**
     * 楽天APIでホテル検索
     */
    private function search_rakuten_hotels($area_code, $price_min, $price_max, $keyword) {
        if (empty($this->rakuten_app_id)) {
            return new WP_Error('no_api', '楽天APIが設定されていません');
        }

        $params = [
            'applicationId' => $this->rakuten_app_id,
            'format' => 'json',
            'hits' => 30,
            'datumType' => 1,
        ];

        if (!empty($area_code)) {
            $params['middleClassCode'] = $area_code;
        }

        if (!empty($keyword)) {
            $params['keyword'] = $keyword;
        }

        if ($price_min > 0) {
            $params['minCharge'] = $price_min;
        }

        if ($price_max > 0) {
            $params['maxCharge'] = $price_max;
        }

        $url = 'https://app.rakuten.co.jp/services/api/Travel/SimpleHotelSearch/20170426?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['hotels'])) {
            return [];
        }

        $hotels = [];
        foreach ($data['hotels'] as $item) {
            $hotel = $item['hotel'][0]['hotelBasicInfo'];
            $hotels[] = [
                'hotelNo' => $hotel['hotelNo'],
                'hotelName' => $hotel['hotelName'],
                'hotelImageUrl' => $hotel['hotelImageUrl'] ?? '',
                'hotelMinCharge' => $hotel['hotelMinCharge'] ?? 0,
                'reviewAverage' => $hotel['reviewAverage'] ?? 0,
                'reviewCount' => $hotel['reviewCount'] ?? 0,
                'address1' => $hotel['address1'] ?? '',
                'address2' => $hotel['address2'] ?? '',
                'hotelInformationUrl' => $hotel['hotelInformationUrl'] ?? '',
            ];
        }

        return $hotels;
    }

    /**
     * 既存記事との照合
     */
    private function mark_existing_articles($hotels) {
        global $wpdb;

        if (empty($hotels)) {
            return $hotels;
        }

        $hotel_ids = array_column($hotels, 'hotelNo');
        $placeholders = implode(',', array_fill(0, count($hotel_ids), '%s'));

        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_hrs_rakuten_hotel_id' 
             AND meta_value IN ($placeholders)",
            $hotel_ids
        ), OBJECT_K);

        $existing_map = [];
        foreach ($existing as $row) {
            $existing_map[$row->meta_value] = $row->post_id;
        }

        foreach ($hotels as &$hotel) {
            $hotel['has_article'] = isset($existing_map[$hotel['hotelNo']]);
            $hotel['article_id'] = $existing_map[$hotel['hotelNo']] ?? null;
            if ($hotel['article_id']) {
                $hotel['article_url'] = get_edit_post_link($hotel['article_id'], 'raw');
            }
        }

        return $hotels;
    }

    /**
     * 都道府県名からエリアコード取得
     */
    private function get_area_code_from_prefecture($prefecture) {
        $map = [
            '北海道' => '011000',
            '青森' => '020100', '青森県' => '020100',
            '岩手' => '030100', '岩手県' => '030100',
            '宮城' => '040100', '宮城県' => '040100',
            '秋田' => '050100', '秋田県' => '050100',
            '山形' => '060100', '山形県' => '060100',
            '福島' => '070100', '福島県' => '070100',
            '茨城' => '080100', '茨城県' => '080100',
            '栃木' => '090100', '栃木県' => '090100',
            '群馬' => '100100', '群馬県' => '100100',
            '埼玉' => '110100', '埼玉県' => '110100',
            '千葉' => '120100', '千葉県' => '120100',
            '東京' => '130100', '東京都' => '130100',
            '神奈川' => '140100', '神奈川県' => '140100',
            '新潟' => '150100', '新潟県' => '150100',
            '富山' => '160100', '富山県' => '160100',
            '石川' => '170100', '石川県' => '170100',
            '福井' => '180100', '福井県' => '180100',
            '山梨' => '190100', '山梨県' => '190100',
            '長野' => '200100', '長野県' => '200100',
            '岐阜' => '210100', '岐阜県' => '210100',
            '静岡' => '220100', '静岡県' => '220100',
            '愛知' => '230100', '愛知県' => '230100',
            '三重' => '240100', '三重県' => '240100',
            '滋賀' => '250100', '滋賀県' => '250100',
            '京都' => '260100', '京都府' => '260100',
            '大阪' => '270100', '大阪府' => '270100',
            '兵庫' => '280100', '兵庫県' => '280100',
            '奈良' => '290100', '奈良県' => '290100',
            '和歌山' => '300100', '和歌山県' => '300100',
            '鳥取' => '310100', '鳥取県' => '310100',
            '島根' => '320100', '島根県' => '320100',
            '岡山' => '330100', '岡山県' => '330100',
            '広島' => '340100', '広島県' => '340100',
            '山口' => '350100', '山口県' => '350100',
            '徳島' => '360100', '徳島県' => '360100',
            '香川' => '370100', '香川県' => '370100',
            '愛媛' => '380100', '愛媛県' => '380100',
            '高知' => '390100', '高知県' => '390100',
            '福岡' => '400100', '福岡県' => '400100',
            '佐賀' => '410100', '佐賀県' => '410100',
            '長崎' => '420100', '長崎県' => '420100',
            '熊本' => '430100', '熊本県' => '430100',
            '大分' => '440100', '大分県' => '440100',
            '宮崎' => '450100', '宮崎県' => '450100',
            '鹿児島' => '460100', '鹿児島県' => '460100',
            '沖縄' => '470100', '沖縄県' => '470100',
        ];

        return $map[$prefecture] ?? '';
    }

    /**
     * スタイル出力
     */
    private function render_styles() {
        ?>
        <style>
        .hrs-competitor-analysis { max-width: 1400px; }
        .hrs-competitor-analysis h1 .dashicons { margin-right: 8px; vertical-align: middle; }
        
        .hrs-competitor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .hrs-competitor-search-panel,
        .hrs-existing-articles-panel {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .hrs-competitor-search-panel h2,
        .hrs-existing-articles-panel h2,
        .hrs-competitor-results h2 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 16px;
        }
        
        .hrs-form-row { margin-bottom: 15px; }
        .hrs-form-row label { display: block; font-weight: 600; margin-bottom: 5px; }
        .hrs-form-row .hrs-select,
        .hrs-form-row input[type="text"] { width: 100%; }
        
        .hrs-price-range {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hrs-price-range input { width: 100px; }
        
        .hrs-form-row .button { margin-right: 10px; }
        .hrs-form-row .button .dashicons { margin-right: 4px; vertical-align: middle; }
        
        .hrs-article-list {
            margin: 0;
            padding: 0;
            list-style: none;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .hrs-article-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            gap: 10px;
        }
        .hrs-article-item:hover { background: #f9f9f9; }
        .hrs-article-title { flex: 1; font-weight: 500; }
        .hrs-article-meta { color: #666; font-size: 12px; }
        
        .hrs-competitor-results {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        #hrs-result-count {
            font-size: 14px;
            font-weight: normal;
            color: #666;
            margin-left: 10px;
        }
        
        #hrs-results-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .hrs-hotel-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #fafafa;
            position: relative;
        }
        .hrs-hotel-card.has-article {
            border-color: #0073aa;
            background: #f0f6fc;
        }
        
        .hrs-hotel-card-header {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .hrs-hotel-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .hrs-hotel-info { flex: 1; }
        .hrs-hotel-name {
            font-weight: 600;
            font-size: 14px;
            margin: 0 0 5px 0;
            line-height: 1.3;
        }
        .hrs-hotel-address {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        
        .hrs-hotel-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .hrs-hotel-price { color: #d63638; font-weight: 600; }
        .hrs-hotel-rating { color: #dba617; }
        
        .hrs-hotel-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .hrs-article-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #0073aa;
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .hrs-loading {
            text-align: center;
            padding: 30px;
        }
        .hrs-loading .spinner { float: none; margin: 0 10px 0 0; }
        
        .hrs-no-articles { color: #666; font-style: italic; }
        
        @media (max-width: 1200px) {
            .hrs-competitor-container { grid-template-columns: 1fr; }
        }
        </style>
        <?php
    }

    /**
     * スクリプト出力
     */
    private function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('hrs_competitor_nonce'); ?>';
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // 検索ボタン
            $('#hrs-search-btn').on('click', function() {
                var areaCode = $('#hrs-area-select option:selected').data('code') || '';
                var priceMin = $('#hrs-price-min').val() || 0;
                var priceMax = $('#hrs-price-max').val() || 0;
                var keyword = $('#hrs-keyword').val() || '';
                
                searchCompetitors({
                    area_code: areaCode,
                    price_min: priceMin,
                    price_max: priceMax,
                    keyword: keyword
                });
            });
            
            // リセットボタン
            $('#hrs-reset-btn').on('click', function() {
                $('#hrs-area-select').val('');
                $('#hrs-price-min, #hrs-price-max, #hrs-keyword').val('');
                $('#hrs-competitor-results').hide();
            });
            
            // 既存記事から競合を探す
            $(document).on('click', '.hrs-find-competitors', function() {
                var postId = $(this).data('post-id');
                
                $('#hrs-loading').show();
                $('#hrs-competitor-results').hide();
                
                $.post(ajaxUrl, {
                    action: 'hrs_get_hotel_competitors',
                    nonce: nonce,
                    post_id: postId
                }, function(response) {
                    $('#hrs-loading').hide();
                    
                    if (response.success) {
                        renderResults(response.data.hotels, response.data.source_post);
                    } else {
                        alert(response.data.message || 'エラーが発生しました');
                    }
                });
            });
            
            // 検索実行
            function searchCompetitors(params) {
                $('#hrs-loading').show();
                $('#hrs-competitor-results').hide();
                
                $.post(ajaxUrl, {
                    action: 'hrs_search_competitors',
                    nonce: nonce,
                    area_code: params.area_code,
                    price_min: params.price_min,
                    price_max: params.price_max,
                    keyword: params.keyword
                }, function(response) {
                    $('#hrs-loading').hide();
                    
                    if (response.success) {
                        renderResults(response.data.hotels);
                    } else {
                        alert(response.data.message || 'エラーが発生しました');
                    }
                });
            }
            
            // 結果表示
            function renderResults(hotels, sourcePost) {
                var $container = $('#hrs-results-container');
                $container.empty();
                
                var title = sourcePost ? '「' + sourcePost + '」の競合ホテル' : '検索結果';
                $('#hrs-competitor-results h2').html(
                    '<span class="dashicons dashicons-list-view"></span> ' + title +
                    ' <span id="hrs-result-count">(' + hotels.length + '件)</span>'
                );
                
                if (hotels.length === 0) {
                    $container.html('<p>該当するホテルが見つかりませんでした。</p>');
                    $('#hrs-competitor-results').show();
                    return;
                }
                
                hotels.forEach(function(hotel) {
                    var cardClass = hotel.has_article ? 'hrs-hotel-card has-article' : 'hrs-hotel-card';
                    var badge = hotel.has_article ? '<span class="hrs-article-badge">記事あり</span>' : '';
                    var image = hotel.hotelImageUrl ? 
                        '<img src="' + hotel.hotelImageUrl + '" class="hrs-hotel-image" alt="">' : 
                        '<div class="hrs-hotel-image" style="background:#ddd;"></div>';
                    
                    var actions = '';
                    if (hotel.has_article) {
                        actions += '<a href="' + hotel.article_url + '" class="button button-small">記事を編集</a>';
                    } else {
                        actions += '<a href="<?php echo admin_url('admin.php?page=5d-review-builder-manual'); ?>&hotel_id=' + hotel.hotelNo + '" class="button button-primary button-small">記事を作成</a>';
                    }
                    actions += '<a href="' + hotel.hotelInformationUrl + '" target="_blank" class="button button-small">楽天で見る</a>';
                    
                    var html = '<div class="' + cardClass + '">' +
                        badge +
                        '<div class="hrs-hotel-card-header">' +
                            image +
                            '<div class="hrs-hotel-info">' +
                                '<h4 class="hrs-hotel-name">' + hotel.hotelName + '</h4>' +
                                '<p class="hrs-hotel-address">' + hotel.address1 + hotel.address2 + '</p>' +
                            '</div>' +
                        '</div>' +
                        '<div class="hrs-hotel-meta">' +
                            '<span class="hrs-hotel-price">¥' + Number(hotel.hotelMinCharge).toLocaleString() + '〜</span>' +
                            '<span class="hrs-hotel-rating">★ ' + (hotel.reviewAverage || '-') + ' (' + (hotel.reviewCount || 0) + '件)</span>' +
                        '</div>' +
                        '<div class="hrs-hotel-actions">' + actions + '</div>' +
                    '</div>';
                    
                    $container.append(html);
                });
                
                $('#hrs-competitor-results').show();
            }
        });
        </script>
        <?php
    }
}