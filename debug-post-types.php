<?php
/**
 * デバッグ用: 投稿タイプと記事を確認
 */
require_once('../../../wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>投稿タイプデバッグ</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }
        h2 { color: #0073aa; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #0073aa; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>

<h1>🔍 投稿タイプとメタデータのデバッグ</h1>

<div class="box">
    <h2>1. 全投稿タイプ一覧</h2>
    <?php
    $post_types = get_post_types(['public' => true], 'objects');
    echo '<table>';
    echo '<tr><th>投稿タイプ名</th><th>ラベル</th><th>記事数</th></tr>';
    foreach ($post_types as $post_type) {
        $count = wp_count_posts($post_type->name);
        $total = 0;
        foreach ($count as $status => $num) {
            $total += $num;
        }
        echo '<tr>';
        echo '<td><strong>' . esc_html($post_type->name) . '</strong></td>';
        echo '<td>' . esc_html($post_type->label) . '</td>';
        echo '<td>' . $total . '件</td>';
        echo '</tr>';
    }
    echo '</table>';
    ?>
</div>

<div class="box">
    <h2>2. hotel-review タイプの記事</h2>
    <?php
    $args = [
        'post_type' => 'hotel-review',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending'],
    ];
    $query = new WP_Query($args);
    
    echo '<p>検索結果: <span class="' . ($query->found_posts > 0 ? 'success' : 'error') . '">' . $query->found_posts . '件</span></p>';
    
    if ($query->have_posts()) {
        echo '<table>';
        echo '<tr><th>ID</th><th>タイトル</th><th>ステータス</th><th>SEOスコア</th><th>H</th><th>Q</th><th>C</th></tr>';
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $seo_score = get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
            $h_score = get_post_meta($post_id, '_hrs_h_score', true);
            $q_score = get_post_meta($post_id, '_hrs_q_score', true);
            $c_score = get_post_meta($post_id, '_hrs_c_score', true);
            
            echo '<tr>';
            echo '<td>' . $post_id . '</td>';
            echo '<td>' . esc_html(get_the_title()) . '</td>';
            echo '<td>' . get_post_status() . '</td>';
            echo '<td>' . ($seo_score ? $seo_score : '<span class="error">未設定</span>') . '</td>';
            echo '<td>' . ($h_score ? $h_score : '<span class="error">0</span>') . '</td>';
            echo '<td>' . ($q_score ? $q_score : '<span class="error">0</span>') . '</td>';
            echo '<td>' . ($c_score ? $c_score : '<span class="error">0</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="error">❌ hotel-review タイプの記事が見つかりません</p>';
    }
    wp_reset_postdata();
    ?>
</div>

<div class="box">
    <h2>3. 全記事のメタデータ確認（最新5件）</h2>
    <?php
    $args = [
        'post_type' => 'any',
        'posts_per_page' => 5,
        'post_status' => ['publish', 'draft'],
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            echo '<h3>記事 #' . $post_id . ': ' . esc_html(get_the_title()) . '</h3>';
            echo '<p><strong>投稿タイプ:</strong> ' . get_post_type() . '</p>';
            
            // 全メタデータを取得
            $all_meta = get_post_meta($post_id);
            echo '<pre style="background:#f0f0f0; padding:10px; overflow:auto; max-height:300px;">';
            foreach ($all_meta as $key => $value) {
                if (strpos($key, '_hrs') === 0 || strpos($key, '_yoast') === 0) {
                    echo esc_html($key) . ' = ' . esc_html(is_array($value) ? print_r($value, true) : $value[0]) . "\n";
                }
            }
            echo '</pre>';
        }
    }
    wp_reset_postdata();
    ?>
</div>

<div class="box">
    <p><strong>デバッグ完了</strong></p>
    <p><a href="<?php echo admin_url('admin.php?page=5d-review-builder-nurture'); ?>">← 記事育成ページに戻る</a></p>
</div>

</body>
</html>