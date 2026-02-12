<?php
/**
 * Nurture Scripts - JavaScript
 * @package Hotel_Review_System
 * @version 7.0.0 - 一括操作機能追加
 */
if (!defined('ABSPATH')) exit;

class HRS_Nurture_Scripts {
    public static function render() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('✅ Nurture Scripts Loaded - バージョン 7.0.0');
            
            // フィルター適用
            $('#apply-filters').on('click', function(e) {
                e.preventDefault();
                
                var scoreVal = $('#score-filter').val();
                var orderVal = $('#order-filter').val();
                var directionVal = $('#direction-filter').val();
                
                var url = new URL(window.location.href);
                url.searchParams.set('score', scoreVal);
                url.searchParams.set('order', orderVal);
                url.searchParams.set('direction', directionVal);
                url.searchParams.delete('paged');
                
                window.location.href = url.toString();
            });
            
            // 全選択チェックボックス
            $('#select-all-articles').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('.article-select').prop('checked', isChecked);
                updateBulkActionState();
            });
            
            // 個別チェックボックス変更時
            $('.article-select').on('change', function() {
                var total = $('.article-select').length;
                var checked = $('.article-select:checked').length;
                
                // 全選択チェックボックスの状態を更新
                $('#select-all-articles').prop('checked', total === checked);
                $('#select-all-articles').prop('indeterminate', checked > 0 && checked < total);
                
                updateBulkActionState();
            });
            
            // 一括操作ボタンの状態更新
            function updateBulkActionState() {
                var checked = $('.article-select:checked').length;
                var hasAction = $('#bulk-action-select').val() !== '';
                $('#bulk-action-apply').prop('disabled', checked === 0 || !hasAction);
                
                // 選択数を表示
                if (checked > 0) {
                    $('#selected-count').text(checked + '件選択中');
                } else {
                    $('#selected-count').text('');
                }
            }
            
            // 一括操作選択変更時
            $('#bulk-action-select').on('change', function() {
                updateBulkActionState();
            });
            
            // 一括操作実行
            $('#bulk-action-apply').on('click', function() {
                var action = $('#bulk-action-select').val();
                var selectedIds = [];
                
                $('.article-select:checked').each(function() {
                    selectedIds.push($(this).val());
                });
                
                if (selectedIds.length === 0) {
                    alert('記事を選択してください');
                    return;
                }
                
                if (action === '') {
                    alert('操作を選択してください');
                    return;
                }
                
                console.log('一括操作:', action, '対象:', selectedIds);
                
                switch (action) {
                    case 'analyze':
                        bulkAnalyze(selectedIds);
                        break;
                    case 'trash':
                        bulkTrash(selectedIds);
                        break;
                    case 'export':
                        bulkExport(selectedIds);
                        break;
                }
            });
            
            // 一括HQC再分析
            function bulkAnalyze(ids) {
                if (!confirm(ids.length + '件の記事をHQC再分析しますか？')) {
                    return;
                }
                
                var $btn = $('#bulk-action-apply');
                $btn.prop('disabled', true).text('分析中...');
                
                var completed = 0;
                var errors = [];
                
                ids.forEach(function(postId, index) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrs_analyze_article',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce("hrs_analyze_nonce"); ?>'
                        },
                        success: function(response) {
                            completed++;
                            if (!response.success) {
                                errors.push('ID ' + postId + ': ' + (response.data ? response.data.message : 'エラー'));
                            }
                            checkComplete();
                        },
                        error: function() {
                            completed++;
                            errors.push('ID ' + postId + ': 通信エラー');
                            checkComplete();
                        }
                    });
                });
                
                function checkComplete() {
                    $btn.text('分析中... (' + completed + '/' + ids.length + ')');
                    if (completed >= ids.length) {
                        $btn.prop('disabled', false).text('適用');
                        if (errors.length > 0) {
                            alert('完了（エラーあり）:\n' + errors.join('\n'));
                        } else {
                            alert(ids.length + '件の分析が完了しました');
                        }
                        location.reload();
                    }
                }
            }
            
            // 一括ゴミ箱移動
            function bulkTrash(ids) {
                if (!confirm(ids.length + '件の記事をゴミ箱へ移動しますか？\n\nこの操作は取り消せません。')) {
                    return;
                }
                
                var $btn = $('#bulk-action-apply');
                $btn.prop('disabled', true).text('削除中...');
                
                var completed = 0;
                var errors = [];
                
                ids.forEach(function(postId) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrs_trash_article',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce("hrs_trash_nonce"); ?>'
                        },
                        success: function(response) {
                            completed++;
                            if (!response.success) {
                                errors.push('ID ' + postId + ': ' + (response.data ? response.data.message : 'エラー'));
                            }
                            checkComplete();
                        },
                        error: function() {
                            completed++;
                            errors.push('ID ' + postId + ': 通信エラー');
                            checkComplete();
                        }
                    });
                });
                
                function checkComplete() {
                    $btn.text('削除中... (' + completed + '/' + ids.length + ')');
                    if (completed >= ids.length) {
                        $btn.prop('disabled', false).text('適用');
                        if (errors.length > 0) {
                            alert('完了（エラーあり）:\n' + errors.join('\n'));
                        } else {
                            alert(ids.length + '件をゴミ箱へ移動しました');
                        }
                        location.reload();
                    }
                }
            }
            
            // CSV出力
            function bulkExport(ids) {
                var articles = [];
                
                ids.forEach(function(postId) {
                    var $card = $('.hrs-article-card[data-post-id="' + postId + '"]');
                    var title = $card.find('.article-title a').text();
                    var score = $card.find('.score-number').text();
                    var breakdown = $card.find('.score-breakdown').text();
                    var date = $card.find('.meta-item:first').text().replace(/[^\d\/\s:]/g, '').trim();
                    var status = $card.find('.meta-item:last').text().trim();
                    
                    articles.push({
                        id: postId,
                        title: title,
                        score: score,
                        breakdown: breakdown,
                        date: date,
                        status: status
                    });
                });
                
                // CSV生成
                var csv = 'ID,タイトル,HQCスコア,内訳,作成日,ステータス\n';
                articles.forEach(function(a) {
                    csv += a.id + ',"' + a.title.replace(/"/g, '""') + '",' + a.score + ',"' + a.breakdown + '","' + a.date + '","' + a.status + '"\n';
                });
                
                // BOM付きUTF-8でダウンロード
                var bom = new Uint8Array([0xEF, 0xBB, 0xBF]);
                var blob = new Blob([bom, csv], { type: 'text/csv;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'hrs_articles_' + new Date().toISOString().slice(0,10) + '.csv';
                link.click();
                URL.revokeObjectURL(url);
                
                alert(ids.length + '件のデータをCSV出力しました');
            }
            
            // 個別削除
            $(document).on('click', '.delete-article', function() {
                var postId = $(this).data('post-id');
                var title = $(this).data('title');
                var $card = $(this).closest('.hrs-article-card');
                
                if (!confirm('「' + title + '」をゴミ箱へ移動しますか？')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrs_trash_article',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("hrs_trash_nonce"); ?>'
                    },
                    beforeSend: function() {
                        $card.css('opacity', '0.5');
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.slideUp(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('エラー: ' + (response.data ? response.data.message : '不明なエラー'));
                            $card.css('opacity', '1');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('通信エラー: ' + error);
                        $card.css('opacity', '1');
                    }
                });
            });
            
            // 再分析
            $('.analyze-article').on('click', function() {
                var postId = $(this).data('post-id');
                var $btn = $(this);
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 分析中...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrs_analyze_article',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("hrs_analyze_nonce"); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> 再分析');
                        
                        if (response.success) {
                            var msg = '分析完了！\n\n' +
                                'スコア: ' + response.data.score + ' 点\n' +
                                'H: ' + response.data.h_score + '\n' +
                                'Q: ' + response.data.q_score + '\n' +
                                'C: ' + response.data.c_score;
                            alert(msg);
                            location.reload();
                        } else {
                            alert('分析エラー: ' + (response.data ? response.data.message : '不明なエラー'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> 再分析');
                        alert('通信エラー: ' + error);
                    }
                });
            });
        });
        </script>
        
        <style>
        @keyframes spinning {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spinning {
            animation: spinning 1s linear infinite;
            display: inline-block;
        }
        </style>
        <?php
    }
}