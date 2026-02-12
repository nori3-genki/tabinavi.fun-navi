<?php
/**
 * HQC A/Bテスト - スクリプト
 * 
 * @package HRS
 * @subpackage Learning
 * @version 2.1.1
 * 
 * 変更履歴:
 * - 2.1.1: ajaxurl未定義エラー修正（グローバル変数定義追加）
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_AB_Test_Scripts {

    /**
     * インラインJS取得
     * 
     * @param string $nonce nonceトークン
     * @return string
     */
    public static function get_inline_script($nonce) {
        $escaped_nonce = esc_js($nonce);
        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        
        return <<<JS
(function($) {
    'use strict';
    
    // ajaxurl グローバル変数定義（未定義エラー防止）
    var ajaxurl = '{$ajax_url}';
    
    $(document).ready(function() {
        // タブ切り替え
        $('.ab-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.ab-tab').removeClass('active');
            $(this).addClass('active');
            $('.ab-tab-panel').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });
        
        // 記事選択時に現在のタイトルを取得
        $('select[name="post_id"]').on('change', function() {
            var postId = $(this).val();
            if (postId) {
                var title = $(this).find('option:selected').text();
                $('#variant_a_title').val(title);
            }
        });

        // テスト作成
        $('.ab-test-form').on('submit', function(e) {
            e.preventDefault();
            var \$form = $(this);
            var formData = \$form.serialize();
            
            $.post(ajaxurl, {
                action: 'hrs_create_ab_test',
                _wpnonce: '{$escaped_nonce}',
                data: formData
            }, function(response) {
                if (response.success) {
                    alert('テストを作成しました');
                    location.reload();
                } else {
                    alert('エラー: ' + (response.data || 'テストの作成に失敗しました'));
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('通信エラーが発生しました。ページをリロードしてください。');
            });
        });

        // テスト実行
        $(document).on('click', '.run-test', function() {
            var id = $(this).data('id');
            var \$btn = $(this);
            
            if (!confirm('テストを実行しますか?\\n\\n2つの記事が生成されます（下書き保存）。')) return;
            
            \$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 実行中...');
            
            $.post(ajaxurl, {
                action: 'hrs_run_ab_test',
                _wpnonce: '{$escaped_nonce}',
                test_id: id
            }, function(response) {
                if (response.success) {
                    if (response.data.status === 'failed') {
                        alert('テスト失敗\\n\\n' + (response.data.error || '記事の生成に失敗しました。API設定を確認してください。'));
                    } else {
                        var msg = 'テスト完了！\\n\\n';
                        msg += 'バリアントA: ' + response.data.score_a + '%\\n';
                        msg += 'バリアントB: ' + response.data.score_b + '%\\n\\n';
                        msg += '勝者: ' + (response.data.winner === 'TIE' ? '引分' : 'バリアント ' + response.data.winner);
                        alert(msg);
                    }
                    location.reload();
                } else {
                    alert('エラー: ' + (response.data || '実行に失敗しました'));
                    \$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> 実行');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('通信エラーが発生しました。ページをリロードしてください。');
                \$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> 実行');
            });
        });

        // 再実行
        $(document).on('click', '.retry-test', function() {
            var id = $(this).data('id');
            var \$btn = $(this);
            
            if (!confirm('テストを再実行しますか?\\n\\n前回は生成に失敗しています。API設定を確認してから実行してください。')) return;
            
            \$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 実行中...');
            
            $.post(ajaxurl, {
                action: 'hrs_run_ab_test',
                _wpnonce: '{$escaped_nonce}',
                test_id: id
            }, function(response) {
                if (response.success) {
                    if (response.data.status === 'failed') {
                        alert('再実行も失敗しました\\n\\n' + (response.data.error || 'API設定を確認してください。'));
                    } else {
                        var msg = 'テスト完了！\\n\\n';
                        msg += 'バリアントA: ' + response.data.score_a + '%\\n';
                        msg += 'バリアントB: ' + response.data.score_b + '%\\n\\n';
                        msg += '勝者: ' + (response.data.winner === 'TIE' ? '引分' : 'バリアント ' + response.data.winner);
                        alert(msg);
                    }
                    location.reload();
                } else {
                    alert('エラー: ' + (response.data || '再実行に失敗しました'));
                    \$btn.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> 再実行');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('通信エラーが発生しました。');
                \$btn.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> 再実行');
            });
        });

        // 勝者適用
        $(document).on('click', '.apply-winner', function() {
            if (!confirm('勝者の設定をデフォルトとして保存しますか?')) return;
            
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'hrs_apply_winner',
                _wpnonce: '{$escaped_nonce}',
                test_id: id
            }, function(response) {
                if (response.success) {
                    alert('設定を保存しました');
                } else {
                    alert('エラー: ' + (response.data || '保存に失敗しました'));
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('通信エラーが発生しました。');
            });
        });

        // テスト削除
        $(document).on('click', '.delete-test', function() {
            if (!confirm('このテストを削除しますか?')) return;
            
            var id = $(this).data('id');
            $.post(ajaxurl, {
                action: 'hrs_delete_ab_test',
                _wpnonce: '{$escaped_nonce}',
                test_id: id
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + (response.data || '削除に失敗しました'));
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('通信エラーが発生しました。');
            });
        });
    });
})(jQuery);
JS;
    }
}