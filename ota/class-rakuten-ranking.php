<?php
/**
 * 楽天ランキング・評価データ取得クラス
 *
 * 機能:
 * - 楽天トラベルランキングAPIからランキング情報を取得
 * - 施設検索APIから評価・口コミ数を取得
 * - 記事内に「比較・おすすめ」セクションを表示
 * - 楽天にない宿は代替表示
 *
 * @package Hotel_Review_System
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Rakuten_Ranking {

    /**
     * API URLs
     */
    private $ranking_api_url = 'https://app.rakuten.co.jp/services/api/Travel/HotelRanking/20170426';
    private $hotel_api_url = 'https://app.rakuten.co.jp/services/api/Travel/SimpleHotelSearch/20170426';

    /**
     * 楽天API設定
     */
    private $application_id;
    private $affiliate_id;

    /**
     * キャッシュ有効期限（秒）
     */
    private $cache_expiry = 86400; // 24時間

    /**
     * ランキングジャンル
     */
    private $genres = array(
        'all' => '総合',
        'onsen' => '温泉宿',
        'luxury' => '高級ホテル・旅館',
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->application_id = get_option('hrs_rakuten_app_id', '');
        $this->affiliate_id = get_option('hrs_rakuten_affiliate_id', '');

        // フック登録
        add_filter('the_content', array($this, 'inject_ranking_section'), 25);
        add_action('hrs_daily_ranking_update', array($this, 'run_daily_update'));

        // Cron スケジュール登録
        if (!wp_next_scheduled('hrs_daily_ranking_update')) {
            wp_schedule_event(time(), 'daily', 'hrs_daily_ranking_update');
        }
    }

    /**
     * ホテルの評価・ランキング情報を取得
     *
     * @param int $hotel_no 楽天施設番号
     * @return array|null
     */
    public function get_hotel_ranking_data($hotel_no) {
        if (empty($this->application_id) || empty($hotel_no)) {
            return null;
        }

        // キャッシュチェック
        $cache_key = 'hrs_rakuten_ranking_' . $hotel_no;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // 施設情報を取得（評価・口コミ数含む）
        $hotel_data = $this->get_hotel_detail($hotel_no);
        
        if (empty($hotel_data)) {
            return null;
        }

        // エリアランキングでの順位を取得
        $area_rank = $this->get_area_ranking_position($hotel_no, $hotel_data);

        $result = array(
            'hotel_no' => $hotel_no,
            'hotel_name' => $hotel_data['hotelName'] ?? '',
            'review_average' => $hotel_data['reviewAverage'] ?? null,
            'review_count' => $hotel_data['reviewCount'] ?? 0,
            'user_review' => $hotel_data['userReview'] ?? '',
            'area_name' => $hotel_data['areaName'] ?? '',
            'area_rank' => $area_rank,
            'hotel_special' => $hotel_data['hotelSpecial'] ?? '',
            'updated_at' => current_time('mysql'),
        );

        // キャッシュに保存
        set_transient($cache_key, $result, $this->cache_expiry);

        return $result;
    }

    /**
     * 施設詳細情報を取得
     *
     * @param int $hotel_no 施設番号
     * @return array|null
     */
    private function get_hotel_detail($hotel_no) {
        $params = array(
            'format' => 'json',
            'applicationId' => $this->application_id,
            'hotelNo' => $hotel_no,
        );

        if (!empty($this->affiliate_id)) {
            $params['affiliateId'] = $this->affiliate_id;
        }

        $url = $this->hotel_api_url . '?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['hotels'][0]['hotel'])) {
            return null;
        }

        // ホテル情報を抽出
        $hotel_info = array();
        foreach ($body['hotels'][0]['hotel'] as $info) {
            if (isset($info['hotelBasicInfo'])) {
                $hotel_info = array_merge($hotel_info, $info['hotelBasicInfo']);
            }
            if (isset($info['hotelRatingInfo'])) {
                $hotel_info = array_merge($hotel_info, $info['hotelRatingInfo']);
            }
        }

        return $hotel_info;
    }

    /**
     * エリア内でのランキング順位を取得
     *
     * @param int $hotel_no 施設番号
     * @param array $hotel_data ホテルデータ
     * @return int|null 順位（取得できない場合はnull）
     */
    private function get_area_ranking_position($hotel_no, $hotel_data) {
        // ランキングAPIからデータ取得
        $params = array(
            'format' => 'json',
            'applicationId' => $this->application_id,
            'genre' => 'all',
        );

        $url = $this->ranking_api_url . '?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['Rankings'])) {
            return null;
        }

        // ランキング内での順位を検索
        $rank = 1;
        foreach ($body['Rankings'] as $ranking) {
            if (isset($ranking['Ranking']['hotels'])) {
                foreach ($ranking['Ranking']['hotels'] as $hotel) {
                    if (isset($hotel['hotel']['hotelNo']) && $hotel['hotel']['hotelNo'] == $hotel_no) {
                        return $rank;
                    }
                    $rank++;
                }
            }
        }

        return null; // ランキング外
    }

    /**
     * 記事にランキングセクションを挿入
     *
     * @param string $content 記事本文
     * @return string
     */
    public function inject_ranking_section($content) {
        if (!is_singular('hotel-review')) {
            return $content;
        }

        $post_id = get_the_ID();
        
        // コンテンツ要素で「比較・おすすめ」がONかチェック
        $content_elements = get_post_meta($post_id, '_hrs_content_elements', true);
        if (!is_array($content_elements) || !in_array('comparison', $content_elements)) {
            return $content;
        }

        $ranking_html = $this->get_ranking_section_html($post_id);

        if (empty($ranking_html)) {
            return $content;
        }

        // 既存のセクションを置換、または適切な位置に挿入
        if (strpos($content, '<!-- hrs-ranking-section -->') !== false) {
            $content = preg_replace(
                '/<!-- hrs-ranking-section -->.*?<!-- \/hrs-ranking-section -->/s',
                $ranking_html,
                $content
            );
        } else {
            // 料金セクションの後に挿入
            $price_section_end = strpos($content, '<!-- /hrs-price-section -->');
            if ($price_section_end !== false) {
                $content = substr_replace($content, $ranking_html, $price_section_end + 27, 0);
            } else {
                // 記事末尾に追加
                $content .= $ranking_html;
            }
        }

        return $content;
    }

    /**
     * ランキングセクションHTMLを生成
     *
     * @param int $post_id 記事ID
     * @return string
     */
    public function get_ranking_section_html($post_id) {
        $rakuten_hotel_no = get_post_meta($post_id, '_hrs_rakuten_hotel_no', true);
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        $prefecture = get_post_meta($post_id, '_hrs_prefecture', true);
        $area = get_post_meta($post_id, '_hrs_area', true);

        // 楽天データを取得
        $ranking_data = null;
        if (!empty($rakuten_hotel_no)) {
            $ranking_data = $this->get_hotel_ranking_data($rakuten_hotel_no);
        }

        ob_start();
        ?>
        <!-- hrs-ranking-section -->
        <div class="hrs-ranking-section">
            <h3>📊 ランキング・評価</h3>
            
            <?php if ($ranking_data && ($ranking_data['review_average'] || $ranking_data['area_rank'])): ?>
            <!-- 楽天データあり -->
            <div class="hrs-ranking-card hrs-ranking-has-data">
                
                <?php if ($ranking_data['area_rank']): ?>
                <div class="hrs-ranking-badge">
                    <span class="hrs-rank-icon">🏆</span>
                    <span class="hrs-rank-position"><?php echo esc_html($ranking_data['area_rank']); ?>位</span>
                    <span class="hrs-rank-label">楽天トラベル人気ランキング</span>
                </div>
                <?php endif; ?>
                
                <?php if ($ranking_data['review_average']): ?>
                <div class="hrs-review-score">
                    <div class="hrs-score-stars">
                        <?php echo $this->render_stars($ranking_data['review_average']); ?>
                    </div>
                    <div class="hrs-score-number">
                        <span class="hrs-score-value"><?php echo esc_html(number_format($ranking_data['review_average'], 1)); ?></span>
                        <span class="hrs-score-max">/ 5.0</span>
                    </div>
                    <?php if ($ranking_data['review_count']): ?>
                    <div class="hrs-review-count">
                        （<?php echo esc_html(number_format($ranking_data['review_count'])); ?>件の口コミ）
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($ranking_data['hotel_special'])): ?>
                <div class="hrs-hotel-special">
                    <p><?php echo esc_html($ranking_data['hotel_special']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="hrs-ranking-summary">
                    <?php
                    $summary = $this->generate_ranking_summary($ranking_data, $hotel_name, $area);
                    echo '<p>' . esc_html($summary) . '</p>';
                    ?>
                </div>
                
                <p class="hrs-ranking-source">
                    出典: 楽天トラベル（<?php echo esc_html(date('Y/m/d', strtotime($ranking_data['updated_at']))); ?>時点）
                </p>
            </div>
            
            <?php else: ?>
            <!-- 楽天データなし -->
            <div class="hrs-ranking-card hrs-ranking-no-data">
                <div class="hrs-ranking-alternative">
                    <span class="hrs-alt-icon">✨</span>
                    <h4>この宿の特徴</h4>
                </div>
                
                <div class="hrs-ranking-no-data-content">
                    <?php
                    $alt_text = $this->generate_alternative_text($hotel_name, $prefecture, $area);
                    echo '<p>' . esc_html($alt_text) . '</p>';
                    ?>
                </div>
                
                <p class="hrs-ranking-note">
                    ※大手OTAに依存しない独自の魅力を持つ宿です
                </p>
            </div>
            <?php endif; ?>
            
        </div>
        <!-- /hrs-ranking-section -->
        <?php
        return ob_get_clean();
    }

    /**
     * 星評価をHTMLで表示
     *
     * @param float $rating 評価値（0-5）
     * @return string
     */
    private function render_stars($rating) {
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

        $html = '';
        
        // 満点の星
        for ($i = 0; $i < $full_stars; $i++) {
            $html .= '<span class="hrs-star hrs-star-full">★</span>';
        }
        
        // 半分の星
        if ($half_star) {
            $html .= '<span class="hrs-star hrs-star-half">★</span>';
        }
        
        // 空の星
        for ($i = 0; $i < $empty_stars; $i++) {
            $html .= '<span class="hrs-star hrs-star-empty">☆</span>';
        }

        return $html;
    }

    /**
     * ランキングサマリーを生成
     *
     * @param array $ranking_data ランキングデータ
     * @param string $hotel_name ホテル名
     * @param string $area エリア名
     * @return string
     */
    private function generate_ranking_summary($ranking_data, $hotel_name, $area) {
        $summaries = array();

        // ランキングに基づくコメント
        if ($ranking_data['area_rank']) {
            $rank = $ranking_data['area_rank'];
            if ($rank <= 3) {
                $summaries[] = "楽天トラベルで常に上位にランクインする人気宿です";
            } elseif ($rank <= 10) {
                $summaries[] = "楽天トラベルでトップ10に入る評価の高い宿です";
            } elseif ($rank <= 30) {
                $summaries[] = "多くの利用者から支持されている宿です";
            }
        }

        // 評価に基づくコメント
        if ($ranking_data['review_average']) {
            $avg = $ranking_data['review_average'];
            if ($avg >= 4.5) {
                $summaries[] = "口コミ評価が非常に高く、満足度の高い滞在が期待できます";
            } elseif ($avg >= 4.0) {
                $summaries[] = "口コミ評価が高く、多くの宿泊者から好評を得ています";
            } elseif ($avg >= 3.5) {
                $summaries[] = "安定した評価を得ている宿です";
            }
        }

        // 口コミ数に基づくコメント
        if ($ranking_data['review_count'] >= 500) {
            $summaries[] = "500件以上の口コミがあり、実績豊富な宿です";
        } elseif ($ranking_data['review_count'] >= 100) {
            $summaries[] = "多くの宿泊者からのレビューが寄せられています";
        }

        if (empty($summaries)) {
            return "{$hotel_name}は{$area}エリアで注目される宿の一つです。";
        }

        return implode('。', array_slice($summaries, 0, 2)) . '。';
    }

    /**
     * 楽天データなし時の代替テキストを生成
     *
     * @param string $hotel_name ホテル名
     * @param string $prefecture 都道府県
     * @param string $area エリア名
     * @return string
     */
    private function generate_alternative_text($hotel_name, $prefecture, $area) {
        $templates = array(
            "{$hotel_name}は、{$area}エリアで独自の魅力を持つ宿です。大手予約サイトに頼らない、知る人ぞ知る隠れ家的な存在として、リピーターからの支持を集めています。",
            "{$area}の{$hotel_name}は、独自の路線で運営される個性的な宿です。公式サイトでの予約や、直接の問い合わせがおすすめです。",
            "{$hotel_name}は、{$prefecture}の{$area}に位置する特別な宿。大手OTAのランキングには現れない、本物の価値を提供しています。",
        );

        // ランダムに選択（ただしpost_idベースで固定）
        $index = crc32($hotel_name) % count($templates);
        return $templates[$index];
    }

    /**
     * 毎日のランキング更新処理
     */
    public function run_daily_update() {
        $args = array(
            'post_type' => 'hotel-review',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => array(
                array(
                    'key' => '_hrs_rakuten_hotel_no',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby' => 'modified',
            'order' => 'ASC',
        );

        $posts = get_posts($args);

        foreach ($posts as $post) {
            $hotel_no = get_post_meta($post->ID, '_hrs_rakuten_hotel_no', true);
            if (empty($hotel_no)) continue;

            // キャッシュをクリアして最新データを取得
            $cache_key = 'hrs_rakuten_ranking_' . $hotel_no;
            delete_transient($cache_key);

            $ranking_data = $this->get_hotel_ranking_data($hotel_no);

            if ($ranking_data) {
                // メタデータに保存
                update_post_meta($post->ID, '_hrs_rakuten_review_average', $ranking_data['review_average']);
                update_post_meta($post->ID, '_hrs_rakuten_review_count', $ranking_data['review_count']);
                update_post_meta($post->ID, '_hrs_rakuten_area_rank', $ranking_data['area_rank']);
                update_post_meta($post->ID, '_hrs_ranking_updated', current_time('mysql'));
            }

            // API制限対策
            sleep(1);
        }
    }

    /**
     * プラグイン無効化時のクリーンアップ
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('hrs_daily_ranking_update');
    }
}

// シングルトンインスタンス
function hrs_rakuten_ranking() {
    static $instance = null;
    if ($instance === null) {
        $instance = new HRS_Rakuten_Ranking();
    }
    return $instance;
}

// 初期化
add_action('init', 'hrs_rakuten_ranking');