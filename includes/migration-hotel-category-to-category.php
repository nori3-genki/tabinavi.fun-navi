<?php

/**
 * hotel-category → category 移行スクリプト
 *
 * 1. hotel-review に category を関連付け
 * 2. 県タームを category に作成
 * 3. 既存記事の県タームを category にコピー
 *
 * @package HRS
 * @version 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Category_Migration
{

    /**
     * 都道府県リスト
     */
    private static $prefectures = array(
        'hokkaido' => '北海道',
        'aomori' => '青森県',
        'iwate' => '岩手県',
        'miyagi' => '宮城県',
        'akita' => '秋田県',
        'yamagata' => '山形県',
        'fukushima' => '福島県',
        'ibaraki' => '茨城県',
        'tochigi' => '栃木県',
        'gunma' => '群馬県',
        'saitama' => '埼玉県',
        'chiba' => '千葉県',
        'tokyo' => '東京都',
        'kanagawa' => '神奈川県',
        'niigata' => '新潟県',
        'toyama' => '富山県',
        'ishikawa' => '石川県',
        'fukui' => '福井県',
        'yamanashi' => '山梨県',
        'nagano' => '長野県',
        'gifu' => '岐阜県',
        'shizuoka' => '静岡県',
        'aichi' => '愛知県',
        'mie' => '三重県',
        'shiga' => '滋賀県',
        'kyoto' => '京都府',
        'osaka' => '大阪府',
        'hyogo' => '兵庫県',
        'nara' => '奈良県',
        'wakayama' => '和歌山県',
        'tottori' => '鳥取県',
        'shimane' => '島根県',
        'okayama' => '岡山県',
        'hiroshima' => '広島県',
        'yamaguchi' => '山口県',
        'tokushima' => '徳島県',
        'kagawa' => '香川県',
        'ehime' => '愛媛県',
        'kochi' => '高知県',
        'fukuoka' => '福岡県',
        'saga' => '佐賀県',
        'nagasaki' => '長崎県',
        'kumamoto' => '熊本県',
        'oita' => '大分県',
        'miyazaki' => '宮崎県',
        'kagoshima' => '鹿児島県',
        'okinawa' => '沖縄県',
    );

    /**
     * 地方グループ（親カテゴリ用）
     */
    private static $regions = array(
        'hokkaido-region' => array(
            'name' => '北海道',
            'prefectures' => array('hokkaido'),
        ),
        'tohoku' => array(
            'name' => '東北',
            'prefectures' => array('aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima'),
        ),
        'kanto' => array(
            'name' => '関東',
            'prefectures' => array('ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa'),
        ),
        'chubu' => array(
            'name' => '中部',
            'prefectures' => array('niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano', 'gifu', 'shizuoka', 'aichi'),
        ),
        'kinki' => array(
            'name' => '関西',
            'prefectures' => array('mie', 'shiga', 'kyoto', 'osaka', 'hyogo', 'nara', 'wakayama'),
        ),
        'chugoku' => array(
            'name' => '中国',
            'prefectures' => array('tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi'),
        ),
        'shikoku' => array(
            'name' => '四国',
            'prefectures' => array('tokushima', 'kagawa', 'ehime', 'kochi'),
        ),
        'kyushu' => array(
            'name' => '九州・沖縄',
            'prefectures' => array('fukuoka', 'saga', 'nagasaki', 'kumamoto', 'oita', 'miyazaki', 'kagoshima', 'okinawa'),
        ),
    );

    /**
     * 初期化
     */
    public static function init()
    {
        // hotel-review に category を関連付け
        add_action('init', array(__CLASS__, 'register_category_for_hotel_review'), 20);

        // Ajax ハンドラー
        add_action('wp_ajax_hrs_run_category_migration', array(__CLASS__, 'ajax_run_migration'));
    }

    /**
     * hotel-review に category を関連付け
     */
    public static function register_category_for_hotel_review()
    {
        register_taxonomy_for_object_type('category', 'hotel-review');
    }

    /**
     * 移行ページ表示
     */
    public static function render_migration_page()
    {
?>
        <div class="wrap">
            <h1>🔄 hotel-category → category 移行ツール</h1>

            <div class="notice notice-warning">
                <p><strong>注意:</strong> この操作は既存データを変更します。実行前にバックアップを取ることを推奨します。</p>
            </div>

            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>移行内容</h2>
                <ol>
                    <li><strong>地方カテゴリ作成:</strong> 北海道、東北、関東、中部、関西、中国、四国、九州・沖縄</li>
                    <li><strong>県カテゴリ作成:</strong> 47都道府県を各地方の子カテゴリとして作成</li>
                    <li><strong>記事の移行:</strong> hotel-category の県タームを category にコピー（地方も含む）</li>
                </ol>

                <h3>現在の状況</h3>
                <?php
                $hotel_reviews = wp_count_posts('hotel-review');
                $total = $hotel_reviews->publish + $hotel_reviews->draft;

                $hotel_cat_terms = get_terms(array(
                    'taxonomy' => 'hotel-category',
                    'hide_empty' => false,
                ));
                $pref_count = 0;
                foreach ($hotel_cat_terms as $term) {
                    if (in_array($term->name, self::$prefectures) || in_array($term->slug, array_keys(self::$prefectures))) {
                        $pref_count++;
                    }
                }
                ?>
                <table class="widefat" style="max-width: 400px;">
                    <tr>
                        <th>hotel-review 記事数</th>
                        <td><?php echo $total; ?> 件</td>
                    </tr>
                    <tr>
                        <th>hotel-category 県ターム数</th>
                        <td><?php echo $pref_count; ?> 件</td>
                    </tr>
                </table>

                <p style="margin-top: 20px;">
                    <button type="button" id="run-migration" class="button button-primary button-hero">
                        🚀 移行を実行
                    </button>
                </p>

                <div id="migration-progress" style="display: none; margin-top: 20px;">
                    <h3>進捗</h3>
                    <div style="background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden;">
                        <div id="progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="progress-text">準備中...</p>
                </div>

                <div id="migration-result" style="display: none; margin-top: 20px;"></div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#run-migration').on('click', function() {
                    if (!confirm('カテゴリ移行を実行しますか？\n\nこの操作は既存データを変更します。')) {
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).text('実行中...');
                    $('#migration-progress').show();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrs_run_category_migration',
                            nonce: '<?php echo wp_create_nonce('hrs_migration_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#progress-bar').css('width', '100%');

                            if (response.success) {
                                $('#progress-text').text('完了！');
                                $('#migration-result').html(
                                    '<div class="notice notice-success"><p>' +
                                    '<strong>✅ 移行完了</strong><br>' +
                                    '作成した地方カテゴリ: ' + response.data.regions_created + ' 件<br>' +
                                    '更新した県カテゴリ: ' + response.data.prefectures_updated + ' 件<br>' +
                                    '移行した hotel-review: ' + response.data.posts_migrated + ' 件<br>' +
                                    '更新した post: ' + response.data.posts_updated + ' 件' +
                                    '</p></div>'
                                ).show();
                            } else {
                                $('#progress-text').text('エラー');
                                $('#migration-result').html(
                                    '<div class="notice notice-error"><p>エラー: ' + response.data.message + '</p></div>'
                                ).show();
                            }
                        },
                        error: function() {
                            $('#progress-text').text('通信エラー');
                            $('#migration-result').html(
                                '<div class="notice notice-error"><p>通信エラーが発生しました</p></div>'
                            ).show();
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('🚀 移行を実行');
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * 移行実行 (Ajax)
     */
    public static function ajax_run_migration()
    {
        check_ajax_referer('hrs_migration_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }

        $regions_created = 0;
        $prefectures_updated = 0;
        $posts_migrated = 0;

        // Step 1: 地方カテゴリを作成
        $region_ids = array();
        foreach (self::$regions as $slug => $data) {
            $term = term_exists($data['name'], 'category');
            if (!$term) {
                $term = wp_insert_term($data['name'], 'category', array('slug' => $slug));
                if (!is_wp_error($term)) {
                    $regions_created++;
                }
            }
            if (!is_wp_error($term)) {
                $region_ids[$slug] = is_array($term) ? $term['term_id'] : $term;
            }
        }

        // Step 2: 既存の県カテゴリに親（地方）を設定
        $pref_ids = array();
        foreach (self::$regions as $region_slug => $region_data) {
            $parent_id = $region_ids[$region_slug] ?? 0;

            foreach ($region_data['prefectures'] as $pref_slug) {
                $pref_name = self::$prefectures[$pref_slug] ?? null;
                if (!$pref_name) continue;

                $term = get_term_by('name', $pref_name, 'category');
                if (!$term) {
                    $term = get_term_by('slug', $pref_slug, 'category');
                }

                if ($term) {
                    if ((int)$term->parent !== (int)$parent_id) {
                        wp_update_term($term->term_id, 'category', array(
                            'parent' => $parent_id,
                        ));
                        $prefectures_updated++;
                    }
                    $pref_ids[$pref_name] = $term->term_id;
                    $pref_ids[$pref_slug] = $term->term_id;
                } else {
                    $new_term = wp_insert_term($pref_name, 'category', array(
                        'slug'   => $pref_slug,
                        'parent' => $parent_id,
                    ));
                    if (!is_wp_error($new_term)) {
                        $prefectures_updated++;
                        $pref_ids[$pref_name] = $new_term['term_id'];
                        $pref_ids[$pref_slug] = $new_term['term_id'];
                    }
                }
            }
        }

        // Step 3: 既存 hotel-review の hotel-category 県タームを category にコピー（地方も含む）
        $hotel_reviews = get_posts(array(
            'post_type' => 'hotel-review',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'private'),
        ));

        foreach ($hotel_reviews as $post) {
            $hotel_cats = get_the_terms($post->ID, 'hotel-category');
            if (!$hotel_cats || is_wp_error($hotel_cats)) continue;

            $category_ids = array();
            foreach ($hotel_cats as $term) {
                if (isset($pref_ids[$term->name])) {
                    $category_ids[] = $pref_ids[$term->name];
                } elseif (isset($pref_ids[$term->slug])) {
                    $category_ids[] = $pref_ids[$term->slug];
                }
            }

            if (!empty($category_ids)) {
                // 親カテゴリ（地方）も追加
                foreach ($category_ids as $cat_id) {
                    $term = get_term($cat_id, 'category');
                    if ($term && $term->parent > 0) {
                        $category_ids[] = $term->parent;
                    }
                }
                $category_ids = array_unique($category_ids);
                wp_set_post_categories($post->ID, $category_ids, true);
                $posts_migrated++;
            }
        }

        // Step 4: 既存 post のタイトル・本文から県を検出して category に付与（地方も含む）
        $posts_updated = 0;
        $all_posts = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'private'),
        ));

        foreach ($all_posts as $post) {
            $content = $post->post_title . ' ' . $post->post_content;
            $detected_ids = array();

            foreach (self::$prefectures as $slug => $name) {
                if (mb_strpos($content, $name) !== false) {
                    if (isset($pref_ids[$name])) {
                        $detected_ids[] = $pref_ids[$name];
                    }
                }
            }

            if (!empty($detected_ids)) {
                // 親カテゴリ（地方）も追加
                foreach ($detected_ids as $cat_id) {
                    $term = get_term($cat_id, 'category');
                    if ($term && $term->parent > 0) {
                        $detected_ids[] = $term->parent;
                    }
                }
                $detected_ids = array_unique($detected_ids);
                $existing = wp_get_post_categories($post->ID);
                $new_cats = array_unique(array_merge($existing, $detected_ids));
                wp_set_post_categories($post->ID, $new_cats);
                $posts_updated++;
            }
        }

        wp_send_json_success(array(
            'regions_created' => $regions_created,
            'prefectures_updated' => $prefectures_updated,
            'posts_migrated' => $posts_migrated,
            'posts_updated' => $posts_updated,
        ));
    }
}

// 初期化
HRS_Category_Migration::init();