<?php
/**
 * 既存記事修正スクリプト (強制表示版)
 *
 * 機能:
 * - 既存記事にOTAリンクセクションを追加
 * - アイキャッチ画像が未設定の記事に画像を設定
 * - カテゴリ重複修正・都道府県カテゴリ追加
 *
 * @package HRS
 * @version 1.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// クラスの二重定義防止
if (!class_exists('HRS_Fix_Existing_Posts')) {

    class HRS_Fix_Existing_Posts {

        /**
         * 初期化
         */
        public static function init() {
            add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 999); // 優先度を上げて実行
            add_action('admin_init', array(__CLASS__, 'handle_fix_request'));
            add_action('admin_init', array(__CLASS__, 'fix_404_redirect'));
            add_action('wp_ajax_hrs_fix_single_post', array(__CLASS__, 'ajax_fix_single_post'));
            add_action('wp_ajax_hrs_fix_categories', array(__CLASS__, 'ajax_fix_categories'));
            
            // 読み込み確認用メッセージ（確認後削除可）
            add_action('admin_notices', function() {
                if (current_user_can('manage_options')) {
                    $screen = get_current_screen();
                    // 設定画面以外でも、読み込まれていることを通知
                    if ($screen && $screen->id === 'dashboard') {
                        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ HRS修正ツールが正常に読み込まれています。「ダッシュボード」の下を確認してください。</strong></p></div>';
                    }
                }
            });
        }

        /**
         * 404エラー回避用リダイレクト
         */
        public static function fix_404_redirect() {
            if (is_admin() && !isset($_GET['page'])) {
                $uri = $_SERVER['REQUEST_URI'];
                if (strpos($uri, 'hrs-fix-posts') !== false && strpos($uri, 'admin.php') === false) {
                    wp_safe_redirect(admin_url('admin.php?page=hrs-fix-posts'));
                    exit;
                }
            }
        }

        /**
         * 管理メニュー追加
         * ★一番目立つ場所（ダッシュボード直下）に配置
         */
        public static function add_admin_menu() {
            add_menu_page(
                '記事修正ツール',         // ページタイトル
                'HRS修正(緊急)',      // メニュー名
                'manage_options',        // 権限
                'hrs-fix-posts',         // スラッグ
                array(__CLASS__, 'render_page'),
                'dashicons-hammer',      // アイコン（ハンマー）
                2                        // 表示位置：ダッシュボード(2)の直下
            );
        }

        /**
         * 修正リクエスト処理
         */
        public static function handle_fix_request() {
            if (!isset($_GET['hrs_fix_posts'])) {
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_die('権限がありません');
            }

            if (!isset($_GET['hrs_nonce']) || !wp_verify_nonce($_GET['hrs_nonce'], 'hrs_fix_posts')) {
                wp_die('セキュリティチェックに失敗しました');
            }

            $results = self::fix_all_posts();
            set_transient('hrs_fix_results', $results, 60);

            wp_safe_redirect(admin_url('admin.php?page=hrs-fix-posts&done=1'));
            exit;
        }

        /**
         * 全記事を修正
         */
        public static function fix_all_posts() {
            $posts = get_posts(array(
                'post_type' => 'hotel-review',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending'),
            ));

            $results = array(
                'total' => count($posts),
                'links_added' => 0,
                'links_skipped' => 0,
                'images_added' => 0,
                'images_skipped' => 0,
                'errors' => array(),
            );

            $link_generator = class_exists('HRS_Internal_Link_Generator')
                ? HRS_Internal_Link_Generator::get_instance()
                : null;

            foreach ($posts as $post) {
                // 1. リンク追加
                if ($link_generator && strpos($post->post_content, 'hrs-booking-links') === false) {
                    $success = $link_generator->add_links_to_post($post->ID);
                    if ($success) {
                        $results['links_added']++;
                    } else {
                        $results['errors'][] = "投稿ID {$post->ID}: リンク追加失敗";
                    }
                } else {
                    $results['links_skipped']++;
                }

                // 2. アイキャッチ画像設定
                if (!has_post_thumbnail($post->ID)) {
                    $image_url = get_post_meta($post->ID, '_hrs_thumbnail_url', true);
                    if (empty($image_url)) {
                        $cse_data = get_post_meta($post->ID, '_hrs_cse_data', true);
                        if (!empty($cse_data['images'][0])) {
                            $image_url = $cse_data['images'][0];
                        }
                    }

                    if (!empty($image_url) && $link_generator) {
                        $success = $link_generator->set_featured_image_from_url($post->ID, $image_url);
                        if ($success) {
                            $results['images_added']++;
                        } else {
                            $results['errors'][] = "投稿ID {$post->ID}: 画像設定失敗";
                        }
                    } else {
                        $results['images_skipped']++;
                    }
                } else {
                    $results['images_skipped']++;
                }

                usleep(200000);
            }

            return $results;
        }

        /**
         * カテゴリ一括修正AJAX
         */
        public static function ajax_fix_categories() {
            check_ajax_referer('hrs_fix_categories', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
            }

            $results = self::fix_all_categories();
            wp_send_json_success($results);
        }

        /**
         * 全記事のカテゴリを修正
         */
        public static function fix_all_categories() {
            $posts = get_posts(array(
                'post_type' => 'hotel-review',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending'),
            ));

            $results = array(
                'total' => count($posts),
                'fixed' => 0,
                'skipped' => 0,
                'details' => array(),
            );

            $taxonomy = 'category';
            $persona_map = array(
                'general'   => array('name' => '一般・観光',       'slug' => 'general'),
                'solo'      => array('name' => '一人旅',            'slug' => 'solo'),
                'couple'    => array('name' => 'カップル・夫婦',   'slug' => 'couple'),
                'family'    => array('name' => 'ファミリー',       'slug' => 'family'),
                'senior'    => array('name' => 'シニア',           'slug' => 'senior'),
                'workation' => array('name' => 'ワーケーション',   'slug' => 'workation'),
                'luxury'    => array('name' => 'ラグジュアリー',   'slug' => 'luxury'),
                'budget'    => array('name' => 'コスパ重視',       'slug' => 'budget'),
            );

            $persona_slugs = array_column($persona_map, 'slug');
            $persona_names = array_column($persona_map, 'name');

            foreach ($posts as $post) {
                $post_id = $post->ID;
                $fixed = false;
                $terms_to_set = array();

                $current_terms = wp_get_object_terms($post_id, $taxonomy);
                $persona_found = false;
                $other_terms = array();

                foreach ($current_terms as $term) {
                    if (in_array($term->slug, $persona_slugs) || in_array($term->name, $persona_names)) {
                        if (!$persona_found) {
                            $terms_to_set[] = $term->term_id;
                            $persona_found = true;
                        }
                    } else {
                        $other_terms[] = $term->term_id;
                    }
                }

                $location = get_post_meta($post_id, '_hrs_location', true);
                if (empty($location)) {
                    $location = get_post_meta($post_id, '_hrs_hotel_address', true);
                }

                $prefecture_found = false;
                foreach ($other_terms as $tid) {
                    $t = get_term($tid, $taxonomy);
                    if ($t && self::is_prefecture($t->name)) {
                        $prefecture_found = true;
                        $terms_to_set[] = $tid;
                    } else {
                        $terms_to_set[] = $tid;
                    }
                }

                if (!$prefecture_found && !empty($location)) {
                    $prefecture = self::extract_prefecture($location);
                    if (!empty($prefecture)) {
                        $pref_term = get_term_by('name', $prefecture, $taxonomy);
                        if (!$pref_term) {
                            $pref_slug = self::get_prefecture_slug($prefecture);
                            $pref_term = get_term_by('slug', $pref_slug, $taxonomy);
                        }
                        if ($pref_term) {
                            $terms_to_set[] = $pref_term->term_id;
                            $fixed = true;
                        }
                    }
                }

                $original_count = count($current_terms);
                $new_count = count(array_unique($terms_to_set));

                if ($original_count !== $new_count || $fixed) {
                    $terms_to_set = array_unique($terms_to_set);
                    wp_set_object_terms($post_id, $terms_to_set, $taxonomy, false);
                    $results['fixed']++;
                } else {
                    $results['skipped']++;
                }
            }

            return $results;
        }

        private static function is_prefecture($name) {
            $prefectures = array(
                '北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島',
                '茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川',
                '新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜',
                '静岡', '愛知', '三重', '滋賀', '京都', '大阪', '兵庫',
                '奈良', '和歌山', '鳥取', '島根', '岡山', '広島', '山口',
                '徳島', '香川', '愛媛', '高知', '福岡', '佐賀', '長崎',
                '熊本', '大分', '宮崎', '鹿児島', '沖縄'
            );
            return in_array($name, $prefectures);
        }

        private static function extract_prefecture($address) {
            $prefectures = array(
                '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
            );
            foreach ($prefectures as $pref) {
                if (mb_strpos($address, $pref) !== false) {
                    return preg_replace('/(県|府|都)$/', '', $pref);
                }
            }
            return '';
        }

        private static function get_prefecture_slug($prefecture) {
            $slug_map = array(
                '北海道' => 'hokkaido', '青森' => 'aomori', '岩手' => 'iwate',
                '宮城' => 'miyagi', '秋田' => 'akita', '山形' => 'yamagata',
                '福島' => 'fukushima', '茨城' => 'ibaraki', '栃木' => 'tochigi',
                '群馬' => 'gunma', '埼玉' => 'saitama', '千葉' => 'chiba',
                '東京' => 'tokyo', '神奈川' => 'kanagawa', '新潟' => 'niigata',
                '富山' => 'toyama', '石川' => 'ishikawa', '福井' => 'fukui',
                '山梨' => 'yamanashi', '長野' => 'nagano', '岐阜' => 'gifu',
                '静岡' => 'shizuoka', '愛知' => 'aichi', '三重' => 'mie',
                '滋賀' => 'shiga', '京都' => 'kyoto', '大阪' => 'osaka',
                '兵庫' => 'hyogo', '奈良' => 'nara', '和歌山' => 'wakayama',
                '鳥取' => 'tottori', '島根' => 'shimane', '岡山' => 'okayama',
                '広島' => 'hiroshima', '山口' => 'yamaguchi', '徳島' => 'tokushima',
                '香川' => 'kagawa', '愛媛' => 'ehime', '高知' => 'kochi',
                '福岡' => 'fukuoka', '佐賀' => 'saga', '長崎' => 'nagasaki',
                '熊本' => 'kumamoto', '大分' => 'oita', '宮崎' => 'miyazaki',
                '鹿児島' => 'kagoshima', '沖縄' => 'okinawa'
            );
            return isset($slug_map[$prefecture]) ? $slug_map[$prefecture] : sanitize_title($prefecture);
        }

        public static function ajax_fix_single_post() {
            check_ajax_referer('hrs_fix_single_post', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
            }
            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error('投稿IDが必要です');
            }
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'hotel-review') {
                wp_send_json_error('記事が見つかりません');
            }
            $link_generator = class_exists('HRS_Internal_Link_Generator') ? HRS_Internal_Link_Generator::get_instance() : null;
            $results = array('links' => false, 'image' => false);
            if ($link_generator) {
                if (strpos($post->post_content, 'hrs-booking-links') === false) {
                    $results['links'] = $link_generator->add_links_to_post($post_id);
                } else {
                    $results['links'] = 'already_exists';
                }
                if (!has_post_thumbnail($post_id)) {
                    $image_url = get_post_meta($post_id, '_hrs_thumbnail_url', true);
                    if (!empty($image_url)) {
                        $results['image'] = $link_generator->set_featured_image_from_url($post_id, $image_url);
                    } else {
                        $results['image'] = 'no_image_url';
                    }
                } else {
                    $results['image'] = 'already_exists';
                }
            }
            wp_send_json_success($results);
        }

        public static function render_page() {
            $all_posts = get_posts(array(
                'post_type' => 'hotel-review',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft'),
            ));

            $needs_links = 0;
            $needs_image = 0;
            $needs_category_fix = 0;
            $persona_names = array('一般・観光', '一人旅', 'カップル・夫婦', 'ファミリー', 'シニア', 'ワーケーション', 'ラグジュアリー', 'コスパ重視');

            foreach ($all_posts as $post) {
                if (strpos($post->post_content, 'hrs-booking-links') === false) $needs_links++;
                if (!has_post_thumbnail($post->ID)) $needs_image++;
                $terms = wp_get_object_terms($post->ID, 'category', array('fields' => 'names'));
                $persona_count = 0;
                foreach ($terms as $term_name) {
                    if (in_array($term_name, $persona_names)) $persona_count++;
                }
                if ($persona_count > 1) $needs_category_fix++;
            }

            $results = get_transient('hrs_fix_results');
            delete_transient('hrs_fix_results');
            $nonce = wp_create_nonce('hrs_fix_posts');
            $cat_nonce = wp_create_nonce('hrs_fix_categories');
            ?>
            <div class="wrap">
                <h1>🔧 既存記事の修正ツール</h1>

                <?php if ($results): ?>
                <div class="notice notice-success">
                    <p><strong>修正完了！</strong></p>
                    <ul>
                        <li>処理記事数: <?php echo $results['total']; ?>件</li>
                        <li>リンク追加: <?php echo $results['links_added']; ?>件</li>
                        <li>画像追加: <?php echo $results['images_added']; ?>件</li>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="card" style="max-width:600px; padding:20px; margin:20px 0;">
                    <h2>📊 現在の状態</h2>
                    <table class="widefat" style="margin-top:10px;">
                        <tr><td><strong>総記事数</strong></td><td><?php echo count($all_posts); ?>件</td></tr>
                        <tr><td><strong>リンクが必要な記事</strong></td><td><?php echo $needs_links; ?>件</td></tr>
                        <tr><td><strong>アイキャッチが必要な記事</strong></td><td><?php echo $needs_image; ?>件</td></tr>
                        <tr><td><strong>カテゴリ重複がある記事</strong></td><td><?php echo $needs_category_fix; ?>件</td></tr>
                    </table>
                </div>

                <div class="card" style="max-width:600px; padding:20px; margin:20px 0;">
                    <h2>🏷️ カテゴリ一括修正</h2>
                    <p>処理内容：ペルソナ重複解消 ＆ 都道府県自動追加</p>
                    <button type="button" id="hrs-fix-categories-btn" class="button button-primary">🏷️ カテゴリを一括修正</button>
                    <span id="hrs-fix-categories-status" style="margin-left:10px;"></span>
                </div>

                <script>
                jQuery(function($) {
                    $('#hrs-fix-categories-btn').on('click', function() {
                        var $btn = $(this);
                        var $status = $('#hrs-fix-categories-status');
                        $btn.prop('disabled', true).text('処理中...');
                        $.post(ajaxurl, {
                            action: 'hrs_fix_categories',
                            nonce: '<?php echo $cat_nonce; ?>'
                        }, function(response) {
                            $btn.prop('disabled', false).text('🏷️ カテゴリを一括修正');
                            if (response.success) {
                                $status.html('<span style="color:green;">✅ ' + response.data.fixed + '件修正</span>');
                                setTimeout(function(){ location.reload(); }, 1500);
                            } else {
                                $status.html('<span style="color:red;">❌ ' + response.data + '</span>');
                            }
                        }).fail(function(){
                            $btn.prop('disabled', false).text('🏷️ カテゴリを一括修正');
                            $status.html('<span style="color:red;">❌ 通信エラー</span>');
                        });
                    });
                });
                </script>

                <div class="card" style="max-width:600px; padding:20px; margin:20px 0;">
                    <h2>🚀 リンク・画像一括修正</h2>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=hrs-fix-posts&hrs_fix_posts=1&hrs_nonce=' . $nonce); ?>"
                           class="button button-primary button-large"
                           onclick="return confirm('全記事を修正しますか？');">🔧 全記事を一括修正</a>
                    </p>
                </div>
            </div>
            <?php
        }
    }

    HRS_Fix_Existing_Posts::init();
}