<?php
/**
 * Settings Scripts - JavaScript管理
 * 
 * @package Hotel_Review_System
 * @subpackage Settings
 * @version 6.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Settings_Scripts {

    public static function render() {
        $nonce = wp_create_nonce('hrs_test_api_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            // ChatGPT API Test
            $('#test-chatgpt').on('click', function() {
                var $btn = $(this), $result = $('#api-test-result');
                var apiKey = $('#hrs_chatgpt_api_key').val();
                
                if (!apiKey) {
                    $result.removeClass('success').addClass('error').html('❌ APIキーを入力してください').show();
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> テスト中...');
                $result.hide();
                
                $.post(ajaxurl, {
                    action: 'hrs_test_api', api_type: 'chatgpt', api_key: apiKey, nonce: '<?php echo $nonce; ?>'
                }, function(res) {
                    if (res.success) $result.removeClass('error').addClass('success').html('✅ ' + res.data.message).show();
                    else $result.removeClass('success').addClass('error').html('❌ ' + res.data.message).show();
                }).fail(function() {
                    $result.removeClass('success').addClass('error').html('❌ 接続テストに失敗しました').show();
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ChatGPT API をテスト');
                });
            });
            
            // Google CSE Test
            $('#test-google-cse').on('click', function() {
                var $btn = $(this), $result = $('#google-test-result');
                var apiKey = $('#hrs_google_cse_api_key').val();
                var cseId = $('#hrs_google_cse_id').val();
                
                if (!apiKey || !cseId) {
                    $result.removeClass('success').addClass('error').html('❌ APIキーと検索エンジンIDを入力してください').show();
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> テスト中...');
                $result.hide();
                
                $.post(ajaxurl, {
                    action: 'hrs_test_api', api_type: 'google_cse', api_key: apiKey, cse_id: cseId, nonce: '<?php echo $nonce; ?>'
                }, function(res) {
                    if (res.success) $result.removeClass('error').addClass('success').html('✅ ' + res.data.message).show();
                    else $result.removeClass('success').addClass('error').html('❌ ' + res.data.message).show();
                }).fail(function() {
                    $result.removeClass('success').addClass('error').html('❌ 接続テストに失敗しました').show();
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Google CSE をテスト');
                });
            });
            
            // Rakuten Test
            $('#test-rakuten').on('click', function() {
                var $btn = $(this), $result = $('#rakuten-test-result');
                var appId = $('#hrs_rakuten_app_id').val();
                
                if (!appId) {
                    $result.removeClass('success').addClass('error').html('❌ アプリケーションIDを入力してください').show();
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> テスト中...');
                $result.hide();
                
                $.post(ajaxurl, {
                    action: 'hrs_test_api', api_type: 'rakuten', app_id: appId, nonce: '<?php echo $nonce; ?>'
                }, function(res) {
                    if (res.success) $result.removeClass('error').addClass('success').html('✅ ' + res.data.message).show();
                    else $result.removeClass('success').addClass('error').html('❌ ' + res.data.message).show();
                }).fail(function() {
                    $result.removeClass('success').addClass('error').html('❌ 接続テストに失敗しました').show();
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> 楽天トラベル をテスト');
                });
            });
        });
        </script>
        <?php
    }
}