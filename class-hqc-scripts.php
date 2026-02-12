<?php
/**
 * HQC Scripts - JavaScript管理クラス
 * 
 * インラインJavaScript定義（HQC Generator のフロントエンド制御）
 * 
 * @package Hotel_Review_System
 * @subpackage HQC
 * @version 6.7.2
 * 
 * 変更履歴:
 * - 6.7.0: C層コンテンツ要素（10項目）の保存・読み込み・プリセット対応
 * - 6.7.1: ペルソナ変更時、Q層（info/sensory/story）をリセットしない仕様に変更
 * - 6.7.2: 
 *     * F5リロード時、Q層（sensory/story/info）が復元されない不具合を修正
 *     * C層コンテンツ要素の復元を堅牢化
 *     * 生成結果表示をリッチ化（ローディング/成功/エラー）
 *     * 【追加】デバッグ用 console.log() を3か所に挿入（原因特定支援）
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Hqc_Scripts {

    /**
     * インラインJavaScriptを生成
     *
     * @param array $current 現在の設定（HRS_Hqc_UI::render_page() から渡される）
     * @return string
     */
    public static function get_inline_script($current) {
        // 依存データ取得
        $presets = HRS_Hqc_Presets::get_presets();
        $persona_purpose_map = HRS_Hqc_Data::get_persona_purpose_map();
        $persona_defaults = HRS_Hqc_Data::get_persona_defaults();
        
        // JSONエンコード（非エスケープで日本語可読性確保）
        $presets_json = wp_json_encode($presets['presets'], JSON_UNESCAPED_UNICODE);
        $current_json = wp_json_encode($current, JSON_UNESCAPED_UNICODE);
        $persona_purpose_map_json = wp_json_encode($persona_purpose_map, JSON_UNESCAPED_UNICODE);
        $persona_defaults_json = wp_json_encode($persona_defaults, JSON_UNESCAPED_UNICODE);
        $samples_json = wp_json_encode(self::get_sample_texts(), JSON_UNESCAPED_UNICODE);

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('hrs_hqc_nonce');

        return "
        jQuery(document).ready(function($) {
            // === 依存データ初期化 ===
            var presets = {$presets_json};
            var current = {$current_json};
            var personaPurposeMap = {$persona_purpose_map_json};
            var personaDefaults = {$persona_defaults_json};
            var samples = {$samples_json};
            var ajaxUrl = '{$ajax_url}';
            var nonce = '{$nonce}';

            // === 1. ペルソナ・プリセット選択 ===
            $('.hrs-persona-card').on('click', function() {
                var persona = $(this).data('persona');
                $('.hrs-persona-card').removeClass('active');
                $(this).addClass('active');
                updateRecommendedPurposes(persona);
                applyPersonaDefaults(persona); // ※ 6.7.1: Q層は維持
                updatePreview();
            });

            $('.hrs-preset-card').on('click', function() {
                var preset = $(this).data('preset');
                $('.hrs-preset-card').removeClass('active');
                $(this).addClass('active');
                if (presets[preset]) {
                    applyPreset(presets[preset]);
                    updatePreview();
                    hideWarning();
                }
            });

            // === 2. 入力ハンドラ（H/Q/C層） ===
            // H層：旅の目的
            $('.hrs-checkbox-group .hrs-checkbox-item').on('click', function(e) {
                e.preventDefault();
                $(this).toggleClass('checked');
                $(this).find('input').prop('checked', $(this).hasClass('checked'));
                checkConsistency();
                updatePreview();
            });

            // C層：コンテンツ要素
            $('.hrs-content-items .hrs-checkbox-item').on('click', function(e) {
                e.preventDefault();
                $(this).toggleClass('checked');
                $(this).find('input').prop('checked', $(this).hasClass('checked'));
                updatePreview();
            });

            // 深度・五感・物語・情報強度
            $(document).on('click', '.hrs-level-item', function() {
                var group = $(this).data('group');
                $('[data-group=\"' + group + '\"]').removeClass('checked');
                $(this).addClass('checked');
                $(this).find('input').prop('checked', true);
                updatePreview();
            });

            // セレクトボックス（トーン・構造・商業性など）
            $('select').on('change', function() {
                updatePreview();
            });

            // === 3. 内部ロジック ===
            /**
             * 推奨目的に★マークを付与
             */
            function updateRecommendedPurposes(persona) {
                var recommended = personaPurposeMap[persona] || [];
                $('.hrs-checkbox-group .hrs-checkbox-item').removeClass('recommended');
                recommended.forEach(function(id) {
                    $('.hrs-checkbox-group .hrs-checkbox-item[data-value=\"' + id + '\"]').addClass('recommended');
                });
            }

            /**
             * ペルソナ変更時のデフォルト適用（※ Q層値は維持：6.7.1仕様）
             */
            function applyPersonaDefaults(persona) {
                var defaults = personaDefaults[persona];
                if (!defaults) return;

                // H層：目的 → 推奨の最初を自動選択
                var recommended = personaPurposeMap[persona] || [];
                $('.hrs-checkbox-group .hrs-checkbox-item').removeClass('checked').find('input').prop('checked', false);
                if (recommended.length > 0) {
                    $('.hrs-checkbox-group .hrs-checkbox-item[data-value=\"' + recommended[0] + '\"]')
                        .addClass('checked').find('input').prop('checked', true);
                }

                // H層：深度
                $('[data-group=\"depth\"]').removeClass('checked');
                $('[data-group=\"depth\"][data-value=\"' + defaults.depth + '\"]').addClass('checked').find('input').prop('checked', true);

                // Q層：トーンのみ更新（sensory/story/infoは保持）
                $('#hrs-tone').val(defaults.tone);
            }

            /**
             * ペルソナと旅の目的の整合性チェック
             */
            function checkConsistency() {
                var persona = $('.hrs-persona-card.active').data('persona');
                var selected = $('.hrs-checkbox-group .hrs-checkbox-item.checked input').map(function(){return $(this).val();}).get();
                var inconsistent = (['workation', 'family', 'budget'].includes(persona) && selected.includes('anniversary'));
                if (inconsistent) {
                    showWarning('ペルソナと旅の目的の組み合わせが不自然です。推奨目的（★）を選択することをおすすめします。');
                } else {
                    hideWarning();
                }
            }

            function showWarning(msg) { 
                $('#hrs-warning-box').html('<span class=\"dashicons dashicons-warning\"></span>' + msg).addClass('show'); 
            }
            function hideWarning() { 
                $('#hrs-warning-box').removeClass('show'); 
            }

            /**
             * プリセット適用
             */
            function applyPreset(p) {
                if (p.h) {
                    $('.hrs-persona-card').removeClass('active');
                    $('.hrs-persona-card[data-persona=\"' + p.h.persona + '\"]').addClass('active');
                    updateRecommendedPurposes(p.h.persona);
                    
                    $('[data-group=\"depth\"]').removeClass('checked');
                    $('[data-group=\"depth\"][data-value=\"' + p.h.depth + '\"]').addClass('checked').find('input').prop('checked', true);
                    
                    $('.hrs-checkbox-group .hrs-checkbox-item').removeClass('checked').find('input').prop('checked', false);
                    if (Array.isArray(p.h.purpose)) {
                        p.h.purpose.forEach(function(v) {
                            $('.hrs-checkbox-group .hrs-checkbox-item[data-value=\"' + v + '\"]').addClass('checked').find('input').prop('checked', true);
                        });
                    }
                }
                if (p.q) {
                    $('#hrs-tone').val(p.q.tone);
                    $('#hrs-structure').val(p.q.structure);
                    ['sensory', 'story', 'info'].forEach(function(k) {
                        $('[data-group=\"' + k + '\"]').removeClass('checked');
                        $('[data-group=\"' + k + '\" ][data-value=\"' + p.q[k] + '\"]').addClass('checked').find('input').prop('checked', true);
                    });
                }
                if (p.c) {
                    $('#hrs-commercial').val(p.c.commercial);
                    $('#hrs-experience').val(p.c.experience);
                    
                    $('.hrs-content-items .hrs-checkbox-item').removeClass('checked').find('input').prop('checked', false);
                    if (Array.isArray(p.c.contents)) {
                        p.c.contents.forEach(function(v) {
                            $('.hrs-content-items .hrs-checkbox-item[data-value=\"' + v + '\"]').addClass('checked').find('input').prop('checked', true);
                        });
                    }
                }
            }

            /**
             * プレビュー更新
             */
            function updatePreview() {
                var s = collectSettings();
                
                // ✅ 【Debug】プレビュー更新時の設定を確認
                console.log('【Debug】updatePreview() → settings =', s);
                
                var summary = 'H[' + s.h.persona + '/' + s.h.depth + '] Q[' + 
                    s.q.tone + '/' + s.q.structure + '/' + s.q.sensory + '/' + s.q.story + '/' + s.q.info + 
                    '] C[' + s.c.commercial + '/' + s.c.experience + ']';
                $('#preview-summary').text(summary);
                
                var key = s.h.persona + '_' + s.q.tone + '_' + s.q.sensory + '_' + s.q.story;
                var sample = samples[key] || samples['default'];
                $('#preview-sample').html('<h4>📝 サンプル導入文:</h4><p>' + sample + '</p>');
            }

            /**
             * 現在の設定を収集
             */
            function collectSettings() {
                var settings = {
                    h: { 
                        persona: $('.hrs-persona-card.active').data('persona') || 'general', 
                        purpose: $('.hrs-checkbox-group .hrs-checkbox-item.checked input').map(function(){return $(this).val();}).get(),
                        depth: $('[data-group=\"depth\"].checked').data('value') || 'L2'
                    },
                    q: { 
                        tone: $('#hrs-tone').val(), 
                        structure: $('#hrs-structure').val(),
                        sensory: $('[data-group=\"sensory\"].checked').data('value') || 'G1',
                        story: $('[data-group=\"story\"].checked').data('value') || 'S1',
                        info: $('[data-group=\"info\"].checked').data('value') || 'I1'
                    },
                    c: { 
                        commercial: $('#hrs-commercial').val(), 
                        experience: $('#hrs-experience').val(),
                        contents: $('.hrs-content-items .hrs-checkbox-item.checked input').map(function(){return $(this).val();}).get()
                    }
                };

                // ✅ 【Debug】送信直前の設定を確認
                console.log('【Debug】collectSettings() →', settings);
                return settings;
            }

            // === 4. AJAX操作 ===
            // 設定保存
            $('#hrs-save').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<span class=\"dashicons dashicons-update spinning\"></span> 保存中...');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: { action: 'hrs_hqc_save_settings', nonce: nonce, settings: collectSettings() },
                    success: function(res) {
                        alert(res.success ? '設定を保存しました' : 'エラー: ' + (res.data.message || '保存に失敗しました'));
                    },
                    error: function() { alert('通信エラーが発生しました'); },
                    complete: function() {
                        btn.prop('disabled', false).html('<span class=\"dashicons dashicons-saved\"></span> 設定を保存');
                    }
                });
            });

            // リセット
            $('#hrs-reset').on('click', function() {
                if (confirm('設定をデフォルトにリセットしますか？')) {
                    applyPreset(presets['starter'] || Object.values(presets)[0]);
                    updatePreview();
                    hideWarning();
                    $('.hrs-preset-card').removeClass('active');
                    if (presets['starter']) {
                        $('.hrs-preset-card[data-preset=\"starter\"]').addClass('active');
                    }
                }
            });

            // 単一生成
            $('#hrs-generate-single').on('click', function() {
                var hotelName = $('#hrs-hotel-name').val().trim();
                if (!hotelName) return alert('ホテル名を入力してください');
                
                var btn = $(this);
                var result = $('#hrs-generation-result');
                btn.prop('disabled', true).html('<span class=\"dashicons dashicons-update spinning\"></span> 生成中...');
                result.removeClass('success error loading').addClass('loading')
                      .html('<span class=\"dashicons dashicons-update spinning\"></span> 記事を生成しています...').show();
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hrs_generate_article',
                        nonce: nonce,
                        hotel_name: hotelName,
                        location: $('#hrs-hotel-location').val().trim(),
                        settings: collectSettings()
                    },
                    success: function(res) {
                        if (res.success) {
                            result.removeClass('loading').addClass('success').html(
                                '<strong>✅ 生成完了!</strong><br>' +
                                'タイトル: ' + res.data.title + '<br>' +
                                '<a href=\"' + res.data.edit_url + '\" target=\"_blank\">編集する</a>'
                            );
                            $('#hrs-hotel-name').val('');
                            $('#hrs-hotel-location').val('');
                        } else {
                            result.removeClass('loading').addClass('error')
                                  .html('❌ ' + (res.data.message || '生成に失敗しました'));
                        }
                    },
                    error: function() {
                        result.removeClass('loading').addClass('error')
                              .html('❌ 通信エラーが発生しました');
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<span class=\"dashicons dashicons-media-document\"></span> 今すぐ生成');
                    }
                });
            });

            // キュー追加
            $('#hrs-add-to-queue').on('click', function() {
                var hotelName = $('#hrs-hotel-name').val().trim();
                if (!hotelName) return alert('ホテル名を入力してください');
                
                $.post(ajaxUrl, {
                    action: 'hrs_add_to_queue',
                    nonce: nonce,
                    hotel_name: hotelName,
                    location: $('#hrs-hotel-location').val().trim()
                }, function(res) {
                    if (res.success) window.location.reload();
                    else alert('エラー: ' + (res.data.message || '追加に失敗しました'));
                });
            });

            // キュー削除（動的要素 → delegate）
            $(document).on('click', '.hrs-remove-queue', function() {
                var hotelName = $(this).data('hotel');
                if (!confirm(hotelName + ' をキューから削除しますか？')) return;
                
                $.post(ajaxUrl, {
                    action: 'hrs_remove_from_queue',
                    nonce: nonce,
                    hotel_name: hotelName
                }, function(res) {
                    if (res.success) window.location.reload();
                    else alert('エラー: ' + (res.data.message || '削除に失敗しました'));
                });
            });

            // キュー一括処理
            $('#hrs-process-queue').on('click', function() {
                if (!confirm('キュー内の全ホテルの記事を生成しますか？')) return;
                
                var btn = $(this);
                var result = $('#hrs-generation-result');
                btn.prop('disabled', true).html('<span class=\"dashicons dashicons-update spinning\"></span> 処理中...');
                result.removeClass('success error loading').addClass('loading')
                      .html('<span class=\"dashicons dashicons-update spinning\"></span> キューを処理しています...').show();
                
                $.post(ajaxUrl, {
                    action: 'hrs_process_queue',
                    nonce: nonce,
                    settings: collectSettings()
                }, function(res) {
                    if (res.success) {
                        var msg = '✅ 処理完了!<br>成功: ' + res.data.success_count + '件 / 失敗: ' + res.data.error_count + '件';
                        if (res.data.remaining > 0) {
                            msg += ' / 未処理: ' + res.data.remaining + '件';
                        }
                        result.removeClass('loading').addClass('success').html(msg);
                        if (res.data.success_count > 0) setTimeout(function() { window.location.reload(); }, 2000);
                    } else {
                        result.removeClass('loading').addClass('error')
                              .html('❌ ' + (res.data.message || '処理に失敗しました'));
                    }
                });
            });

            // === 5. 初期化（F5リロード対応：Q層・C層を確実に復元） ===
            function initializeFromSaved() {
                // ✅ 【Debug】PHPから渡された current の内容を確認
                console.log('【Debug】initializeFromSaved() → current =', current);

                if (!current) return;

                // H層
                if (current.h) {
                    if (current.h.persona) {
                        $('.hrs-persona-card').removeClass('active');
                        $('.hrs-persona-card[data-persona=\"' + current.h.persona + '\"]').addClass('active');
                    }
                    if (current.h.depth) {
                        $('[data-group=\"depth\"]').removeClass('checked');
                        $('[data-group=\"depth\"][data-value=\"' + current.h.depth + '\"]').addClass('checked').find('input').prop('checked', true);
                    }
                    if (current.h.purpose && Array.isArray(current.h.purpose)) {
                        $('.hrs-checkbox-group .hrs-checkbox-item').removeClass('checked').find('input').prop('checked', false);
                        current.h.purpose.forEach(function(v) {
                            $('.hrs-checkbox-group .hrs-checkbox-item[data-value=\"' + v + '\"]').addClass('checked').find('input').prop('checked', true);
                        });
                    }
                }

                // Q層 ← ★★★★ 修正：sensory/story/info を明示的に復元 ★★★★
                if (current.q) {
                    if (current.q.tone) $('#hrs-tone').val(current.q.tone);
                    if (current.q.structure) $('#hrs-structure').val(current.q.structure);
                    
                    // ここで、sensory/story/info を確実に反映
                    ['sensory', 'story', 'info'].forEach(function(key) {
                        var value = current.q[key];
                        if (value) {
                            $('[data-group=\"' + key + '\"]').removeClass('checked');
                            $('[data-group=\"' + key + '\"][data-value=\"' + value + '\"]').addClass('checked').find('input').prop('checked', true);
                        }
                    });
                }

                // C層 ← 同様に堅牢化
                if (current.c) {
                    if (current.c.commercial) $('#hrs-commercial').val(current.c.commercial);
                    if (current.c.experience) $('#hrs-experience').val(current.c.experience);
                    if (current.c.contents && Array.isArray(current.c.contents)) {
                        $('.hrs-content-items .hrs-checkbox-item').removeClass('checked').find('input').prop('checked', false);
                        current.c.contents.forEach(function(v) {
                            $('.hrs-content-items .hrs-checkbox-item[data-value=\"' + v + '\"]').addClass('checked').find('input').prop('checked', true);
                        });
                    }
                }
            }

            // 初期化実行
            initializeFromSaved();
            var initialPersona = $('.hrs-persona-card.active').data('persona') || 'general';
            updateRecommendedPurposes(initialPersona);
            updatePreview();
        });
        ";
    }

    /**
     * サンプル導入文一覧（リアルタイムプレビュー用）
     */
    private static function get_sample_texts() {
        return [
            'couple_emotional_G3_S3' => '夕陽が水平線に溶けていく瞬間、二人だけの特別な時間が始まる。窓の外に広がる絶景を眺めながら、心が静かに満たされていく...',
            'couple_luxury_G3_S3' => '上質なリネンの香りに包まれて目覚める朝。窓の外には穏やかな海が広がり、二人だけの特別な一日が始まる。',
            'family_casual_G2_S2' => '子どもたちの歓声が響くプールサイド。「パパ、見て見て！」という声に振り向けば、初めての飛び込みに挑戦する姿。',
            'solo_cinematic_G3_S3' => '静寂に包まれた早朝のロビー。コーヒーの香りが漂う中、窓の向こうに広がる山々が朝日に染まっていく。',
            'workation_journalistic_G1_S1' => 'Wi-Fi環境も整い、仕事に集中できる環境が整っている。午前中は仕事、午後からは周辺観光へ。',
            'senior_casual_G2_S2' => 'ゆったりとした時間が流れる温泉宿。長年連れ添った二人で、静かに湯船に浸かる幸せ。',
            'luxury_luxury_G3_S3' => '一歩足を踏み入れた瞬間、日常から切り離された特別な空間が広がる。洗練された空気が全身を包み込む。',
            'budget_casual_G1_S1' => 'コスパ抜群！この価格でこのクオリティは正直驚き。必要なものは全て揃っています。',
            'default' => '旅の始まりは、いつも期待と発見に満ちている。このホテルで過ごす時間が、きっと忘れられない思い出になる。'
        ];
    }
}