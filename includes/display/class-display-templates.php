<?php
/**
 * API連動セクション 表示テンプレート（1コンテナ統合版）
 *
 * 価格・ランキング・口コミ・予約CTAを
 * 1つの意味的コンテナ（hrs-api-section）として統合出力する
 *
 * @package HRS
 * @version 6.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Display_Templates {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function enqueue_styles() {
        if (is_singular('post') || is_singular('hotel-review')) {
            wp_enqueue_style('hrs-price-section', HRS_PLUGIN_URL . 'assets/css/hrs-price-section.css', array(), HRS_VERSION);
            wp_enqueue_style('hrs-ranking-section', HRS_PLUGIN_URL . 'assets/css/hrs-ranking-section.css', array(), HRS_VERSION);
            wp_enqueue_style('hrs-display-templates', HRS_PLUGIN_URL . 'assets/css/hrs-display-templates.css', array(), HRS_VERSION);
        }
    }

    /**
     * ============================
     * 統合APIセクション（1コンテナ）
     * ============================
     */
    public function render_api_section(array $args) {

        if (empty($args)) return '';

        $hotel_name = esc_attr($args['hotel_name'] ?? '');

        ob_start();
        ?>
        <section class="hrs-api-section"
            data-hotel="<?php echo $hotel_name; ?>"
            data-source="ota-api">

            <?php
            echo $this->render_price_section($args['price'] ?? []);
            echo $this->render_ranking_section($args['ranking'] ?? []);
            echo $this->render_reviews_section($args['reviews'] ?? []);
            echo $this->render_booking_buttons($args['booking'] ?? []);
            ?>

        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * 価格セクション
     */
    private function render_price_section($data) {
        if (empty($data)) return '';

        $hotel_name   = esc_html($data['hotel_name'] ?? '');
        $min_price    = isset($data['min_price']) ? number_format($data['min_price']) : '-';
        $popular_price= isset($data['popular_price']) ? number_format($data['popular_price']) : '-';
        $suite_price  = isset($data['suite_price']) ? number_format($data['suite_price']) : '-';
        $rakuten_url  = esc_url($data['rakuten_url'] ?? '#');
        $last_updated = esc_html($data['last_updated'] ?? '');
        $plans        = $data['plans'] ?? array();

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
                <a href="<?php echo $rakuten_url; ?>" target="_blank" rel="nofollow noopener">
                    楽天トラベルで空室を確認する →
                </a>
            </div>

            <div class="hrs-price-update">
                最終更新: <?php echo $last_updated; ?> ｜ 楽天トラベルより自動取得
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * ランキングセクション
     */
    private function render_ranking_section($data) {
        if (empty($data) || empty($data['hotels'])) return '';

        $area_name = esc_html($data['area_name'] ?? 'このエリア');
        $hotels    = $data['hotels'];

        ob_start();
        ?>
        <div class="hrs-ranking-section">
            <h3><?php echo $area_name; ?> 人気ホテルランキング</h3>

            <ul class="hrs-ranking-list">
                <?php foreach ($hotels as $hotel) :
                    $is_current = !empty($hotel['is_current']);
                    $rank_class = 'hrs-rank-' . min((int)$hotel['rank'], 5);
                ?>
                <li class="hrs-ranking-item <?php echo $is_current ? 'current' : ''; ?>">
                    <div class="hrs-rank-badge <?php echo $rank_class; ?>">
                        <?php echo esc_html($hotel['rank']); ?>
                    </div>
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
     * 口コミセクション
     */
    private function render_reviews_section($data) {
        if (empty($data)) return '';

        $overall_score = (float)($data['overall_score'] ?? 0);
        $review_count = (int)($data['review_count'] ?? 0);
        $breakdown    = $data['breakdown'] ?? array();
        $reviews      = $data['reviews'] ?? array();

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
                        <div class="bar-bg">
                            <div class="bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                        </div>
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
                        <div class="hrs-review-rating">
                            <?php echo $rating_stars; ?> <?php echo number_format($review['rating'], 1); ?>
                        </div>
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
     * 予約ボタンセクション
     */
    private function render_booking_buttons($data) {
        if (empty($data)) return '';

        $hotel_name = esc_html($data['hotel_name'] ?? '');
        $rakuten_url = esc_url($data['rakuten_url'] ?? '');
        $jalan_url   = esc_url($data['jalan_url'] ?? '');
        $ikyu_url    = esc_url($data['ikyu_url'] ?? '');
        $jtb_url     = esc_url($data['jtb_url'] ?? '');

        ob_start();
        ?>
        <div class="hrs-booking-section">
            <h3>🏨 料金・予約情報</h3>
            
            <div class="hrs-price-display">
                <div class="hrs-price-label">最安値</div>
                <div class="hrs-price-value">
                    <span class="hrs-price-amount">17,600円〜</span>
                    <span class="hrs-price-unit">/ 1泊</span>
                </div>
            </div>

            <div class="hrs-booking-buttons">
                <?php if ($rakuten_url) : ?>
                <a href="<?php echo $rakuten_url; ?>" class="hrs-booking-btn rakuten rakuten-full" target="_blank" rel="nofollow noopener">
                    <span class="btn-icon">🏨</span>
                    <span class="btn-text">楽天トラベル</span>
                </a>
                <?php endif; ?>

                <?php if ($jalan_url) : ?>
                <a href="<?php echo $jalan_url; ?>" class="hrs-booking-btn jalan" target="_blank" rel="nofollow noopener">
                    <span class="btn-icon">🏨</span>
                    <span class="btn-text">じゃらん</span>
                </a>
                <?php endif; ?>

                <?php if ($ikyu_url) : ?>
                <a href="<?php echo $ikyu_url; ?>" class="hrs-booking-btn ikyu" target="_blank" rel="nofollow noopener">
                    <span class="btn-icon">✨</span>
                    <span class="btn-text">一休.com</span>
                </a>
                <?php endif; ?>

                <?php if ($jtb_url) : ?>
                <a href="<?php echo $jtb_url; ?>" class="hrs-booking-btn jtb" target="_blank" rel="nofollow noopener">
                    <span class="btn-icon">🎫</span>
                    <span class="btn-text">JTB</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="hrs-booking-notice">
                ※料金は目安です。最新の空室状況と正確な料金は各サイトでご確認ください。<br>
                ※リンクから各プランの詳細が確認できない場合があります。
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

HRS_Display_Templates::get_instance();