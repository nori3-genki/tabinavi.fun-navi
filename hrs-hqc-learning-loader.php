<?php
/**
 * カスタム投稿タイプ登録クラス
 * 
 * hotel-review カスタム投稿タイプ
 * - レビュー一覧
 * - 新規追加
 * - カテゴリ（タクソノミー）
 * 
 * @package HRS
 * @version 4.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'HRS_Custom_Post_Type' ) ) {

    class HRS_Custom_Post_Type {

        /**
         * 投稿タイプ名
         */
        private $post_type = 'hotel-review';

        /**
         * タクソノミー名
         */
        private $taxonomy = 'hotel-category';

        /**
         * コンストラクタ
         */
        public function __construct() {
            add_action('init', array($this, 'register_post_type'));
            add_action('init', array($this, 'register_taxonomy'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post_' . $this->post_type, array($this, 'save_meta_boxes'), 10, 2);
            
            // 管理画面カラム
            add_filter('manage_' . $this->post_type . '_posts_columns', array($this, 'add_admin_columns'));
            add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
            add_filter('manage_edit-' . $this->post_type . '_sortable_columns', array($this, 'sortable_columns'));
        }

        /**
         * カスタム投稿タイプを登録
         */
        public function register_post_type() {
            $labels = array(
                'name'                  => 'ホテルレビュー',
                'singular_name'         => 'ホテルレビュー',
                'menu_name'             => 'レビュー一覧',
                'name_admin_bar'        => 'ホテルレビュー',
                'add_new'               => '新規追加',
                'add_new_item'          => '新規レビューを追加',
                'new_item'              => '新規レビュー',
                'edit_item'             => 'レビューを編集',
                'view_item'             => 'レビューを表示',
                'all_items'             => 'レビュー一覧',
                'search_items'          => 'レビューを検索',
                'parent_item_colon'     => '親レビュー:',
                'not_found'             => 'レビューが見つかりません',
                'not_found_in_trash'    => 'ゴミ箱にレビューはありません',
                'featured_image'        => 'アイキャッチ画像',
                'set_featured_image'    => 'アイキャッチ画像を設定',
                'remove_featured_image' => 'アイキャッチ画像を削除',
                'use_featured_image'    => 'アイキャッチ画像として使用',
                'archives'              => 'レビューアーカイブ',
                'insert_into_item'      => 'レビューに挿入',
                'uploaded_to_this_item' => 'このレビューにアップロード',
                'filter_items_list'     => 'レビューリストを絞り込む',
                'items_list_navigation' => 'レビューリストナビゲーション',
                'items_list'            => 'レビューリスト',
            );

            $args = array(
                'labels'              => $labels,
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => '5d-review-builder',
                'query_var'           => true,
                'rewrite'             => array('slug' => 'hotel-review', 'with_front' => false),
                'capability_type'     => 'post',
                'has_archive'         => true,
                'hierarchical'        => false,
                'menu_position'       => null,
                'menu_icon'           => 'dashicons-building',
                'supports'            => array(
                    'title',
                    'editor',
                    'author',
                    'thumbnail',
                    'excerpt',
                    'comments',
                    'revisions',
                    'custom-fields',
                ),
                'show_in_rest'        => true,
                'rest_base'           => 'hotel-reviews',
            );

            register_post_type($this->post_type, $args);
        }

        /**
         * タクソノミー（カテゴリ）を登録
         */
        public function register_taxonomy() {
            $labels = array(
                'name'                       => 'ホテルカテゴリ',
                'singular_name'              => 'ホテルカテゴリ',
                'search_items'               => 'カテゴリを検索',
                'popular_items'              => '人気のカテゴリ',
                'all_items'                  => 'すべてのカテゴリ',
                'parent_item'                => '親カテゴリ',
                'parent_item_colon'          => '親カテゴリ:',
                'edit_item'                  => 'カテゴリを編集',
                'update_item'                => 'カテゴリを更新',
                'add_new_item'               => '新規カテゴリを追加',
                'new_item_name'              => '新しいカテゴリ名',
                'separate_items_with_commas' => 'カンマで区切って入力',
                'add_or_remove_items'        => 'カテゴリを追加または削除',
                'choose_from_most_used'      => 'よく使うカテゴリから選択',
                'not_found'                  => 'カテゴリが見つかりません',
                'menu_name'                  => 'カテゴリ',
            );

            $args = array(
                'hierarchical'          => true,
                'labels'                => $labels,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'show_in_menu'          => true,
                'query_var'             => true,
                'rewrite'               => array('slug' => 'hotel-category'),
                'show_in_rest'          => true,
            );

            register_taxonomy($this->taxonomy, array($this->post_type), $args);

            // デフォルトカテゴリを追加
            $this->add_default_categories();
        }

        /**
         * デフォルトカテゴリを追加
         */
        private function add_default_categories() {
            $default_categories = array(
                'onsen'      => '温泉旅館',
                'city'       => 'シティホテル',
                'resort'     => 'リゾートホテル',
                'ryokan'     => '旅館',
                'business'   => 'ビジネスホテル',
                'pension'    => 'ペンション・民宿',
            );

            foreach ($default_categories as $slug => $name) {
                if (!term_exists($slug, $this->taxonomy)) {
                    wp_insert_term($name, $this->taxonomy, array('slug' => $slug));
                }
            }
        }

        /**
         * メタボックスを追加
         */
        public function add_meta_boxes() {
            add_meta_box(
                'hrs_hotel_info',
                'ホテル情報',
                array($this, 'render_hotel_info_metabox'),
                $this->post_type,
                'normal',
                'high'
            );

            add_meta_box(
                'hrs_seo_info',
                'SEO情報',
                array($this, 'render_seo_info_metabox'),
                $this->post_type,
                'side',
                'default'
            );

            add_meta_box(
                'hrs_hqc_info',
                'HQCスコア',
                array($this, 'render_hqc_info_metabox'),
                $this->post_type,
                'side',
                'default'
            );
        }

        /**
         * ホテル情報メタボックスを表示
         */
        public function render_hotel_info_metabox($post) {
            wp_nonce_field('hrs_hotel_info_nonce', 'hrs_hotel_info_nonce_field');

            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
            $hotel_address = get_post_meta($post->ID, '_hrs_hotel_address', true);
            $ota_urls = get_post_meta($post->ID, '_hrs_ota_urls', true);
            
            if (!is_array($ota_urls)) {
                $ota_urls = array();
            }
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="hrs_hotel_name">ホテル名</label></th>
                    <td>
                        <input type="text" id="hrs_hotel_name" name="hrs_hotel_name" 
                               value="<?php echo esc_attr($hotel_name); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="hrs_hotel_address">住所</label></th>
                    <td>
                        <input type="text" id="hrs_hotel_address" name="hrs_hotel_address" 
                               value="<?php echo esc_attr($hotel_address); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th>OTAリンク</th>
                    <td>
                        <p>
                            <label>楽天トラベル:</label><br>
                            <input type="url" name="hrs_ota_urls[rakuten]" 
                                   value="<?php echo esc_url($ota_urls['rakuten'] ?? ''); ?>" class="large-text">
                        </p>
                        <p>
                            <label>じゃらん:</label><br>
                            <input type="url" name="hrs_ota_urls[jalan]" 
                                   value="<?php echo esc_url($ota_urls['jalan'] ?? ''); ?>" class="large-text">
                        </p>
                        <p>
                            <label>一休.com:</label><br>
                            <input type="url" name="hrs_ota_urls[ikyu]" 
                                   value="<?php echo esc_url($ota_urls['ikyu'] ?? ''); ?>" class="large-text">
                        </p>
                        <p>
                            <label>Booking.com:</label><br>
                            <input type="url" name="hrs_ota_urls[booking]" 
                                   value="<?php echo esc_url($ota_urls['booking'] ?? ''); ?>" class="large-text">
                        </p>
                        <p>
                            <label>公式サイト:</label><br>
                            <input type="url" name="hrs_ota_urls[official]" 
                                   value="<?php echo esc_url($ota_urls['official'] ?? ''); ?>" class="large-text">
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }

        /**
         * SEO情報メタボックスを表示
         */
        public function render_seo_info_metabox($post) {
            $keyphrase = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            ?>
            <p>
                <strong>フォーカスキーフレーズ:</strong><br>
                <?php echo esc_html($keyphrase ?: '未設定'); ?>
            </p>
            <p>
                <strong>メタディスクリプション:</strong><br>
                <?php echo esc_html($meta_desc ? mb_substr($meta_desc, 0, 50) . '...' : '未設定'); ?>
            </p>
            <p>
                <strong>文字数:</strong><br>
                <?php echo esc_html(mb_strlen(wp_strip_all_tags($post->post_content))); ?> 文字
            </p>
            <?php
        }

        /**
         * HQCスコアメタボックスを表示
         */
        public function render_hqc_info_metabox($post) {
            $hqc_score = get_post_meta($post->ID, '_hrs_hqc_score', true);
            $hqc_label = $this->get_hqc_label($hqc_score);
            
            $label_colors = array(
                'excellent' => '#22c55e',
                'good'      => '#3b82f6',
                'fair'      => '#f59e0b',
                'poor'      => '#ef4444',
            );
            
            $color = $label_colors[$hqc_label] ?? '#6b7280';
            ?>
            <div style="text-align: center; padding: 10px;">
                <?php if ($hqc_score): ?>
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo esc_attr($color); ?>;">
                        <?php echo esc_html(number_format($hqc_score * 100, 1)); ?>
                    </div>
                    <div style="font-size: 14px; color: <?php echo esc_attr($color); ?>; text-transform: uppercase;">
                        <?php echo esc_html($hqc_label); ?>
                    </div>
                <?php else: ?>
                    <div style="color: #6b7280;">未計算</div>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * HQCラベル取得
         */
        private function get_hqc_label($score) {
            if (empty($score)) return '';
            if ($score >= 0.85) return 'excellent';
            if ($score >= 0.70) return 'good';
            if ($score >= 0.50) return 'fair';
            return 'poor';
        }

        /**
         * メタボックスを保存
         */
        public function save_meta_boxes($post_id, $post) {
            if (!isset($_POST['hrs_hotel_info_nonce_field']) || 
                !wp_verify_nonce($_POST['hrs_hotel_info_nonce_field'], 'hrs_hotel_info_nonce')) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            if (isset($_POST['hrs_hotel_name'])) {
                update_post_meta($post_id, '_hrs_hotel_name', sanitize_text_field($_POST['hrs_hotel_name']));
            }

            if (isset($_POST['hrs_hotel_address'])) {
                update_post_meta($post_id, '_hrs_hotel_address', sanitize_text_field($_POST['hrs_hotel_address']));
            }

            if (isset($_POST['hrs_ota_urls']) && is_array($_POST['hrs_ota_urls'])) {
                $ota_urls = array();
                foreach ($_POST['hrs_ota_urls'] as $key => $url) {
                    $ota_urls[sanitize_key($key)] = esc_url_raw($url);
                }
                update_post_meta($post_id, '_hrs_ota_urls', $ota_urls);
            }
        }

        /**
         * 管理画面カラムを追加
         */
        public function add_admin_columns($columns) {
            $new_columns = array();
            
            foreach ($columns as $key => $value) {
                $new_columns[$key] = $value;
                
                if ($key === 'title') {
                    $new_columns['hotel_name'] = 'ホテル名';
                    $new_columns['hqc_score']  = 'HQCスコア';
                    $new_columns['h_score']    = 'H';
                    $new_columns['q_score']    = 'Q';
                    $new_columns['c_score']    = 'C';
                }
            }
            
            return $new_columns;
        }

        /**
         * 管理画面カラムを表示
         */
        public function render_admin_columns($column, $post_id) {
            switch ($column) {
                case 'hotel_name':
                    $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
                    echo esc_html($hotel_name ?: '—');
                    break;
                    
                case 'hqc_score':
                    $hqc_score = get_post_meta($post_id, '_hrs_hqc_score', true);
                    if ($hqc_score) {
                        $label = $this->get_hqc_label($hqc_score);
                        $colors = array(
                            'excellent' => '#22c55e',
                            'good'      => '#3b82f6',
                            'fair'      => '#f59e0b',
                            'poor'      => '#ef4444',
                        );
                        $color = $colors[$label] ?? '#6b7280';
                        echo '<span style="color:' . esc_attr($color) . '; font-weight: bold;">';
                        echo esc_html(number_format($hqc_score * 100, 1));
                        echo '</span>';
                    } else {
                        echo '—';
                    }
                    break;

                case 'h_score':
                    $h_score = get_post_meta($post_id, '_hrs_h_score', true);
                    echo $h_score !== '' ? esc_html(number_format($h_score * 100, 1)) : '—';
                    break;

                case 'q_score':
                    $q_score = get_post_meta($post_id, '_hrs_q_score', true);
                    echo $q_score !== '' ? esc_html(number_format($q_score * 100, 1)) : '—';
                    break;

                case 'c_score':
                    $c_score = get_post_meta($post_id, '_hrs_c_score', true);
                    echo $c_score !== '' ? esc_html(number_format($c_score * 100, 1)) : '—';
                    break;
            }
        }

        /**
         * ソート可能カラム
         */
        public function sortable_columns($columns) {
            $columns['hqc_score'] = 'hqc_score';
            $columns['h_score']   = 'h_score';
            $columns['q_score']   = 'q_score';
            $columns['c_score']   = 'c_score';
            return $columns;
        }
    }

    new HRS_Custom_Post_Type();
}