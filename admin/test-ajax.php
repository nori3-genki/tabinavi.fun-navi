<?php
require_once('../wp-load.php');

echo "<h2>AJAX Registry Test</h2>";

// プラグインが読み込まれているか
echo "<p>Plugin loaded: " . (defined('HRS_PLUGIN_DIR') ? 'YES' : 'NO') . "</p>";

// クラスが存在するか
echo "<p>HRS_Ajax_Registry: " . (class_exists('HRS_Ajax_Registry') ? 'YES' : 'NO') . "</p>";
echo "<p>HRS_Article_Generator: " . (class_exists('HRS_Article_Generator') ? 'YES' : 'NO') . "</p>";

// 登録されているアクションを確認
global $wp_filter;
$action_name = 'wp_ajax_hrs_generate_article';
echo "<p>Action registered: " . (isset($wp_filter[$action_name]) ? 'YES' : 'NO') . "</p>";

// 全ての hrs_ で始まるアクションを表示
echo "<h3>Registered HRS Actions:</h3><ul>";
foreach ($wp_filter as $tag => $callbacks) {
    if (strpos($tag, 'wp_ajax_hrs_') === 0) {
        echo "<li>" . esc_html($tag) . "</li>";
    }
}
echo "</ul>";