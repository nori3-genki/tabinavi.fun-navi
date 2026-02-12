<?php
/**
 * API連動セクション プレビューページ
 * 
 * 管理画面で表示デザインを確認できるページ
 * 
 * @package HRS
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_API_Preview_Page {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_preview_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function add_preview_page() {
        add_menu_page(
            '表示プレビュー',
            '表示プレビュー',
            'manage_options',
            'hrs-api-preview',
            array($this, 'render_preview_page'),
            'dashicons-visibility',
            30
        );
    }

    public function enqueue_styles($hook) {
        if ($hook !== 'toplevel_page_hrs-api-preview') {
            return;
        }
        wp_enqueue_style('hrs-display-templates', HRS_PLUGIN_URL . 'assets/css/hrs-display-templates.css', array(), HRS_VERSION);
    }

    public function render_preview_page() {
        ?>
        <div class="wrap">
            <h1>🎨 API連動セクション プレビュー</h1>
            <p>実際の記事に表示されるデザインのプレビューです。</p>
            
            <div style="max-width: 800px; margin: 20px 0;">
                
                <!-- 価格セクション -->
                <h2>💰 価格セクション</h2>
                <?php echo $this->render_price_section($this->get_sample_price_data()); ?>
                
                <hr style="margin: 40px 0;">
                
                <!-- ランキングセクション -->
                <h2>🏆 ランキングセクション</h2>
                <?php echo $this->render_ranking_section($this->get_sample_ranking_data()); ?>
                
                <hr style="margin: 40px 0;">
                
                <!-- 口コミセクション -->
                <h2>⭐ 口コミセクション</h2>
                <?php echo $this->render_reviews_section($this->get_sample_reviews_data()); ?>
                
                <hr style="margin: 40px 0;">
                
                <!-- 予約ボタン -->
                <h2>🔗 予約ボタン</h2>
                <?php echo $this->render_booking_buttons($this->get_sample_booking_data()); ?>
                
            </div>
        </div>
        <?php
    }

    private function get_sample_price_data() {
        return array(
            'hotel_name' => '湯本館',
            'min_price' => 12800,
            'popular_price' => 18500,
            'suite_price' => 35000,
            'plans' => array(
                array(
                    'name' => '【早割30】1泊2食付き スタンダードプラン',
                    'detail' => '30日前までの予約で10%OFF・露天風呂付客室',
                    'price' => 12800,
                ),
                array(
                    'name' => '【カップル限定】記念日プラン',
                    'detail' => 'ケーキ＆スパークリングワイン付・レイトアウト無料',
                    'price' => 22000,
                ),
            ),
            'rakuten_url' => 'https://travel.rakuten.co.jp/',
            'last_updated' => date('Y年n月j日 H:i'),
        );
    }

    private function get_sample_ranking_data() {
        return array(
            'area_name' => '箱根エリア',
            'current_hotel' => '湯本館',
            'hotels' => array(
                array('rank' => 1, 'name' => '強羅花壇', 'area' => '箱根・強羅', 'score' => 4.8),
                array('rank' => 2, 'name' => '湯本館', 'area' => '箱根・湯本', 'score' => 4.6, 'is_current' => true),
                array('rank' => 3, 'name' => '箱根吟遊', 'area' => '箱根・宮ノ下', 'score' => 4.5),
                array('rank' => 4, 'name' => 'ハイアットリージェンシー箱根', 'area' => '箱根・強羅', 'score' => 4.4),
                array('rank' => 5, 'name' => '翠松園', 'area' => '箱根・小涌谷', 'score' => 4.3),
            ),
        );
    }

    private function get_sample_reviews_data() {
        return array(
            'overall_score' => 4.6,
            'review_count' => 128,
            'breakdown' => array(
                '部屋' => 4.6,
                '食事' => 4.8,
                '風呂' => 4.7,
                'サービス' => 4.5,
                '清潔感' => 4.4,
            ),
            'reviews' => array(
                array(
                    'user_initial' => '田',
                    'user_name' => '田中さん',
                    'date' => '2025年12月 カップルで利用',
                    'rating' => 5.0,
                    'text' => '結婚記念日で利用しました。部屋の露天風呂から見える紅葉が最高でした！夕食の懐石料理も一品一品丁寧に作られていて、特に金目鯛の煮付けは絶品。スタッフの方の心遣いも素晴らしく、また必ず訪れたいと思います。',
                    'tags' => array('露天風呂', '料理が美味しい', '記念日'),
                ),
                array(
                    'user_initial' => '鈴',
                    'user_name' => '鈴木さん',
                    'date' => '2025年11月 家族で利用',
                    'rating' => 4.0,
                    'text' => '子供連れでしたが、キッズメニューも充実していて助かりました。貸切風呂も広くて家族4人でゆったり入れました。ただ、館内の階段が多いのでベビーカーは少し大変かも。',
                    'tags' => array('子連れ歓迎', '貸切風呂'),
                ),
            ),
        );
    }

    private function get_sample_booking_data() {
        return array(
            'hotel_name' => '湯本館',
            'rakuten_url' => 'https://travel.rakuten.co.jp/',
            'jalan_url' => 'https://www.jalan.net/',
            'ikyu_url' => 'https://www.ikyu.com/',
        );
    }

    /**
     * 価格セクションを出力
     */
    private function render_price_section($data) {
        if (empty($data)) return '';
        
        $hotel_name = esc_html($data['hotel_name'] ?? '');
        $min_price = isset($data['min_price']) ? number_format($data['min_price']) : '-';
        $popular_price = isset($data['popular_price']) ? number_format($data['popular_price']) : '-';
        $suite_price = isset($data['suite_price']) ? number_format($data['suite_price']) : '-';
        $rakuten_url = esc_url($data['rakuten_url'] ?? '#');
        $last_updated = esc_html($data['last_updated'] ?? '');
        $plans = $data['plans'] ?? array();

        ob_start();
        ?>
        <div class="hrs-price-section">
            <h3>最新の料金・プラン情報</h3>
            
            <div class="hrs-price-grid">
                <div class="hrs-price-card highlight">
                    <div class="label">最安料金（税込）</div>
                    <div class="price">¥<?php echo $min_price; ?><small>/人</small></div>
                </div>
                <div class="hrs-price-card">
                    <div class="label">人気プラン</div>
                    <div class="price">¥<?php echo $popular_price; ?><small>/人</small></div>
                </div>
                <div class="hrs-price-card">
                    <div class="label">スイートルーム</div>
                    <div class="price">¥<?php echo $suite_price; ?><small>/人</small></div>
                </div>
            </div>

            <?php if (!empty($plans)) : ?>
            <div class="hrs-price-plans">
                <h4>📋 おすすめプラン</h4>
                <?php foreach ($plans as $plan) : ?>
                <div class="hrs-plan-item">
                    <div>
                        <div class="plan-name"><?php echo esc_html($plan['name']); ?></div>
                        <div class="plan-detail"><?php echo esc_html($plan['detail']); ?></div>
                    </div>
                    <div class="plan-price">¥<?php echo number_format($plan['price']); ?>〜</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="hrs-price-cta">
                <a href="<?php echo $rakuten_url; ?>" target="_blank" rel="nofollow noopener">楽天トラベルで空室を確認する →</a>
            </div>

            <div class="hrs-price-update">
                最終更新: <?php echo $last_updated; ?> ｜ 楽天トラベルより自動取得
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * ランキングセクションを出力
     */
    private function render_ranking_section($data) {
        if (empty($data) || empty($data['hotels'])) return '';
        
        $area_name = esc_html($data['area_name'] ?? 'このエリア');
        $hotels = $data['hotels'] ?? array();

        ob_start();
        ?>
        <div class="hrs-ranking-section">
            <h3><?php echo $area_name; ?> 人気ホテルランキング</h3>
            
            <ul class="hrs-ranking-list">
                <?php foreach ($hotels as $hotel) : 
                    $is_current = !empty($hotel['is_current']);
                    $rank_class = 'hrs-rank-' . min($hotel['rank'], 5);
                ?>
                <li class="hrs-ranking-item <?php echo $is_current ? 'current' : ''; ?>">
                    <div class="hrs-rank-badge <?php echo $rank_class; ?>"><?php echo esc_html($hotel['rank']); ?></div>
                    <div class="hrs-ranking-info">
                        <div class="hotel-name">
                            <?php if ($is_current) echo '🏨 '; ?>
                            <?php echo esc_html($hotel['name']); ?>
                            <?php if ($is_current) echo '（この記事）'; ?>
                        </div>
                        <div class="hotel-area"><?php echo esc_html($hotel['area']); ?></div>
                    </div>
                    <div class="hrs-ranking-score">
                        <div class="score"><?php echo esc_html($hotel['score']); ?></div>
                        <div class="label">評価</div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 口コミセクションを出力
     */
    private function render_reviews_section($data) {
        if (empty($data)) return '';
        
        $overall_score = $data['overall_score'] ?? 0;
        $review_count = $data['review_count'] ?? 0;
        $breakdown = $data['breakdown'] ?? array();
        $reviews = $data['reviews'] ?? array();
        
        $stars = str_repeat('★', floor($overall_score)) . str_repeat('☆', 5 - floor($overall_score));

        ob_start();
        ?>
        <div class="hrs-reviews-section">
            <h3>宿泊者の口コミ・評価</h3>
            
            <div class="hrs-review-summary">
                <div class="hrs-review-score-big">
                    <div class="score"><?php echo number_format($overall_score, 1); ?></div>
                    <div class="stars"><?php echo $stars; ?></div>
                    <div class="count"><?php echo number_format($review_count); ?>件の口コミ</div>
                </div>
                <div class="hrs-review-breakdown">
                    <?php foreach ($breakdown as $label => $score) : 
                        $percentage = ($score / 5) * 100;
                    ?>
                    <div class="hrs-review-bar">
                        <span class="label"><?php echo esc_html($label); ?></span>
                        <div class="bar-bg"><div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div></div>
                        <span class="value"><?php echo number_format($score, 1); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($reviews)) : ?>
            <div class="hrs-review-list">
                <h4>💬 最新の口コミ</h4>
                
                <?php foreach ($reviews as $review) : 
                    $rating_stars = str_repeat('★', floor($review['rating'])) . str_repeat('☆', 5 - floor($review['rating']));
                ?>
                <div class="hrs-review-item">
                    <div class="hrs-review-header">
                        <div class="hrs-review-user">
                            <div class="avatar"><?php echo esc_html($review['user_initial']); ?></div>
                            <div>
                                <div class="name"><?php echo esc_html($review['user_name']); ?></div>
                                <div class="date"><?php echo esc_html($review['date']); ?></div>
                            </div>
                        </div>
                        <div class="hrs-review-rating"><?php echo $rating_stars; ?> <?php echo number_format($review['rating'], 1); ?></div>
                    </div>
                    <div class="hrs-review-text"><?php echo esc_html($review['text']); ?></div>
                    <?php if (!empty($review['tags'])) : ?>
                    <div class="hrs-review-tags">
                        <?php foreach ($review['tags'] as $tag) : ?>
                        <span class="hrs-review-tag"><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 予約ボタンセクションを出力
     */
    private function render_booking_buttons($data) {
        if (empty($data)) return '';
        
        $hotel_name = esc_html($data['hotel_name'] ?? '');
        $rakuten_url = esc_url($data['rakuten_url'] ?? '');
        $jalan_url = esc_url($data['jalan_url'] ?? '');
        $ikyu_url = esc_url($data['ikyu_url'] ?? '');

        ob_start();
        ?>
        <div class="hrs-booking-section">
            <h3>🏨 <?php echo $hotel_name; ?> を予約する</h3>
            <p>各予約サイトで最新の空室状況・料金をチェック</p>
            <div class="hrs-booking-buttons">
                <?php if ($rakuten_url) : ?>
                <a href="<?php echo $rakuten_url; ?>" class="hrs-booking-btn rakuten" target="_blank" rel="nofollow noopener">楽天トラベル</a>
                <?php endif; ?>
                <?php if ($jalan_url) : ?>
                <a href="<?php echo $jalan_url; ?>" class="hrs-booking-btn jalan" target="_blank" rel="nofollow noopener">じゃらん</a>
                <?php endif; ?>
                <?php if ($ikyu_url) : ?>
                <a href="<?php echo $ikyu_url; ?>" class="hrs-booking-btn ikyu" target="_blank" rel="nofollow noopener">一休.com</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

HRS_API_Preview_Page::get_instance();