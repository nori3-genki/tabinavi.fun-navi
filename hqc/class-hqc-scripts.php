<?php

/**
 * 【最新・完全修正】HQC Scripts
 *
 * ✅ 修正①: collectSettings() キャッシュの無効化機構（invalidateSettings）
 * ✅ 修正②: イベントバインドをすべて delegated event に統一
 * ✅ 修正③: showWarning() のXSS脆弱性を修正
 * ✅ 修正④: applyPreset() などすべてのUI変更箇所で invalidateSettings() 呼び出し
 * ✅ 改善⑤: イベントハンドラを関数化（重複排除）
 * ✅ 修正⑥: 記事生成ボタンにevent.preventDefault()追加（ページ遷移防止）
 * ✅ 修正⑦: generateArticleWithRetry() 構文エラー修正（v2.4.2）
 *
 * 使用場所: /wp-content/plugins/5d-review-builder/includes/admin/hqc/class-hqc-scripts.php
 * @version 2.4.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// ★ 依存クラスチェック（デバッグ警告）
if (defined('WP_DEBUG') && WP_DEBUG) {
    $required_classes = ['HRS_Hqc_Presets', 'HRS_Hqc_Data'];
    foreach ($required_classes as $_cls) {
        if (!class_exists($_cls, false)) {
            error_log('[HRS] WARNING: class-hqc-scripts.php loaded but dependency missing: ' . $_cls);
        }
    }
    unset($required_classes, $_cls);
}

class HRS_Hqc_Scripts
{

    public static function get_inline_script($current)
    {
        if (!class_exists('HRS_Hqc_Presets') || !class_exists('HRS_Hqc_Data')) {
            return '/* [HRS ERROR] Required classes not loaded: HRS_Hqc_Presets or HRS_Hqc_Data */';
        }

        $presets = HRS_Hqc_Presets::get_presets();
        $persona_purpose_map = HRS_Hqc_Data::get_persona_purpose_map();
        $persona_defaults = HRS_Hqc_Data::get_persona_defaults();

        $presets_json = wp_json_encode($presets['presets'], JSON_UNESCAPED_UNICODE);
        $current_json = wp_json_encode($current, JSON_UNESCAPED_UNICODE);
        $persona_purpose_map_json = wp_json_encode($persona_purpose_map, JSON_UNESCAPED_UNICODE);
        $persona_defaults_json = wp_json_encode($persona_defaults, JSON_UNESCAPED_UNICODE);
        $samples_json = wp_json_encode(self::get_sample_texts(), JSON_UNESCAPED_UNICODE);

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('hrs_hqc_nonce');

        return <<<JAVASCRIPT
jQuery(document).ready(function($) {
    // ========================================
    // 状態管理
    // ========================================
    var state = {
        personaSaving: false,
        generating: false,
        previousPersona: null,
        \$cache: {},
        settings: null,
        ajaxTimeout: {
            default: 30000,
            generate: 300000,
            processQueue: 600000
        }
    };

    // ========================================
    // Ajax Wrapper
    // ========================================
    function hrsAjax(action, data, options) {
        var defaults = {
            timeout: state.ajaxTimeout.default,
            success: function() {},
            error: function() {},
            complete: function() {}
        };

        var settings = $.extend({}, defaults, options || {});

        return $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: settings.timeout,
            data: $.extend({
                action: action,
                nonce: nonce
            }, data),
            success: function(res) {
                if (!res.success && window.console && console.warn) {
                    console.warn('[HQC Ajax] Error response:', res.data?.message || 'Unknown error');
                }
                settings.success(res);
            },
            error: function(xhr, status, error) {
                var msg = 'Network error';
                if (status === 'timeout') msg = 'Request timeout';
                if (status === 'abort') msg = 'Request aborted';

                if (window.console && console.error) {
                    console.error('[HQC Ajax] ' + action + ': ' + msg, error);
                }

                settings.error({ status: status, message: msg });
            },
            complete: settings.complete
        });
    }

    // ========================================
    // settingsキャッシュの無効化
    // ========================================
    function invalidateSettings() {
        state.settings = null;
    }

    // ========================================
    // DOMキャッシュ
    // ========================================
    function cacheDOM() {
        state.\$cache = {
            personaCards: $('.hrs-persona-card'),
            presetCards: $('.hrs-preset-card'),
            purposeItems: $('.hrs-checkbox-group .hrs-checkbox-item'),
            contentItems: $('.hrs-content-items .hrs-checkbox-item'),
            levelItems: $('.hrs-level-item'),
            warningBox: $('#hrs-warning-box'),
            previewSummary: $('#preview-summary'),
            previewSample: $('#preview-sample'),
            hotelName: $('#hrs-hotel-name'),
            hotelLocation: $('#hrs-hotel-location'),
            selectTone: $('#hrs-tone'),
            selectStructure: $('#hrs-structure'),
            selectCommercial: $('#hrs-commercial'),
            selectExperience: $('#hrs-experience'),
            groups: {
                sensory: $('[data-group="sensory"]'),
                story: $('[data-group="story"]'),
                info: $('[data-group="info"]'),
                expression: $('[data-group="expression"]'),
                volume: $('[data-group="volume"]'),
                target: $('[data-group="target"]'),
                seo: $('[data-group="seo"]'),
                reliability: $('[data-group="reliability"]'),
                depth: $('[data-group="depth"]')
            }
        };
    }

    var presets = {$presets_json};
    var current = {$current_json};
    var personaPurposeMap = {$persona_purpose_map_json};
    var personaDefaults = {$persona_defaults_json};
    var samples = {$samples_json};
    var ajaxUrl = '{$ajax_url}';
    var nonce = '{$nonce}';

    // 初期化
    cacheDOM();

    // ========================================
    // ページ離脱防止
    // ========================================
    $(window).on('beforeunload', function(e) {
        if (state.generating) {
            var msg = '記事を生成中です。ページを離れると処理がキャンセルされる可能性があります。';
            e.preventDefault();
            e.returnValue = msg;
            return msg;
        }
    });

    // ========================================
    // ペルソナ選択
    // ========================================
    $(document).on('click', '.hrs-persona-card', function() {
        var oldPersona = state.\$cache.personaCards.filter('.active').data('persona') || 'general';
        state.previousPersona = oldPersona;

        var persona = $(this).data('persona');
        state.\$cache.personaCards.removeClass('active');
        $(this).addClass('active');
        updateRecommendedPurposes(persona);
        applyPersonaDefaults(persona);
        updatePreview();
        invalidateSettings();
    });

    // ========================================
    // プリセット選択
    // ========================================
    $(document).on('click', '.hrs-preset-card', function() {
        var preset = $(this).data('preset');
        state.\$cache.presetCards.removeClass('active');
        $(this).addClass('active');
        if (presets[preset]) {
            applyPreset(presets[preset]);
            updatePreview();
            hideWarning();
        }
    });

    // ========================================
    // 旅の目的（チェックボックス）
    // ========================================
    $(document).on('click', '.hrs-checkbox-group .hrs-checkbox-item', function(e) {
        e.preventDefault();
        $(this).toggleClass('checked');
        $(this).find('input').prop('checked', $(this).hasClass('checked'));
        checkConsistency();
        updatePreview();
        invalidateSettings();
    });

    // ========================================
    // コンテンツ要素（C層）
    // ========================================
    $(document).on('click', '.hrs-content-items .hrs-checkbox-item', function(e) {
        e.preventDefault();
        $(this).toggleClass('checked');
        $(this).find('input').prop('checked', $(this).hasClass('checked'));
        updatePreview();
        invalidateSettings();
    });

    // ========================================
    // レベル選択
    // ========================================
    $(document).on('click', '.hrs-level-item', function() {
        var group = $(this).data('group');
        state.\$cache.levelItems.filter('[data-group="' + group + '"]').removeClass('checked');
        $(this).addClass('checked');
        $(this).find('input').prop('checked', true);
        updatePreview();
        invalidateSettings();
    });

    // ========================================
    // セレクト変更
    // ========================================
    $(document).on('change', 'select', function() {
        updatePreview();
        invalidateSettings();
    });

    // ========================================
    // 推奨目的の更新
    // ========================================
    function updateRecommendedPurposes(persona) {
        var recommended = personaPurposeMap[persona] || [];
        state.\$cache.purposeItems.removeClass('recommended');
        recommended.forEach(function(id) {
            state.\$cache.purposeItems.filter('[data-value="' + id + '"]').addClass('recommended');
        });
    }

    // ========================================
    // ペルソナデフォルト適用
    // ========================================
    function applyPersonaDefaults(persona) {
        var defaults = personaDefaults[persona];
        if (!defaults) return;
        var recommended = personaPurposeMap[persona] || [];

        state.\$cache.purposeItems.removeClass('checked').find('input').prop('checked', false);
        if (recommended.length > 0) {
            state.\$cache.purposeItems.filter('[data-value="' + recommended[0] + '"]')
                .addClass('checked').find('input').prop('checked', true);
        }

        state.\$cache.groups.depth.removeClass('checked');
        state.\$cache.groups.depth.filter('[data-value="' + defaults.depth + '"]').addClass('checked').find('input').prop('checked', true);

        state.\$cache.selectTone.val(defaults.tone);

        ['sensory', 'story', 'info', 'expression', 'volume', 'target', 'seo', 'reliability'].forEach(function(key) {
            var value = defaults[key];
            if (value) {
                state.\$cache.groups[key].removeClass('checked');
                state.\$cache.groups[key].filter('[data-value="' + value + '"]').addClass('checked').find('input').prop('checked', true);
            }
        });

        invalidateSettings();
    }

    // ========================================
    // 整合性チェック
    // ========================================
    function checkConsistency() {
        var persona = state.\$cache.personaCards.filter('.active').data('persona');
        var selected = state.\$cache.purposeItems.filter('.checked input').map(function(){return $(this).val();}).get();
        var inconsistent = (['workation', 'family', 'budget'].includes(persona) && selected.includes('anniversary'));
        if (inconsistent) {
            showWarning('ペルソナと旅の目的の組み合わせが不自然です。推奨目的（★）を選択することをおすすめします。');
        } else {
            hideWarning();
        }
    }

    function showWarning(msg) {
        var warningSpan = $('<span class="dashicons dashicons-warning"></span>');
        state.\$cache.warningBox.empty().append(warningSpan).append(' ' + msg).addClass('show').show();
    }

    function hideWarning() {
        state.\$cache.warningBox.removeClass('show').hide();
    }

    // ========================================
    // プリセット適用
    // ========================================
    function applyPreset(p) {
        if (p.h) {
            state.\$cache.personaCards.removeClass('active');
            state.\$cache.personaCards.filter('[data-persona="' + p.h.persona + '"]').addClass('active');
            updateRecommendedPurposes(p.h.persona);

            state.\$cache.groups.depth.removeClass('checked');
            state.\$cache.groups.depth.filter('[data-group="depth"][data-value="' + p.h.depth + '"]').addClass('checked').find('input').prop('checked', true);

            state.\$cache.purposeItems.removeClass('checked').find('input').prop('checked', false);
            if (Array.isArray(p.h.purpose)) {
                p.h.purpose.forEach(function(v) {
                    state.\$cache.purposeItems.filter('[data-value="' + v + '"]').addClass('checked').find('input').prop('checked', true);
                });
            }
        }
        if (p.q) {
            state.\$cache.selectTone.val(p.q.tone);
            state.\$cache.selectStructure.val(p.q.structure);
            ['sensory', 'story', 'info', 'expression', 'volume', 'target', 'seo', 'reliability'].forEach(function(k) {
                state.\$cache.groups[k].removeClass('checked');
                state.\$cache.groups[k].filter('[data-value="' + p.q[k] + '"]').addClass('checked').find('input').prop('checked', true);
            });
        }
        if (p.c) {
            state.\$cache.selectCommercial.val(p.c.commercial);
            state.\$cache.selectExperience.val(p.c.experience);
            state.\$cache.contentItems.removeClass('checked').find('input').prop('checked', false);
            if (Array.isArray(p.c.contents)) {
                p.c.contents.forEach(function(v) {
                    state.\$cache.contentItems.filter('[data-value="' + v + '"]').addClass('checked').find('input').prop('checked', true);
                });
            }
        }
        invalidateSettings();
    }

    // ========================================
    // プレビュー更新
    // ========================================
    function updatePreview() {
        var s = collectSettings();
        var summary = 'H[' + s.h.persona + '/' + s.h.depth + '] Q[' +
            s.q.tone + '/' + s.q.structure + '/' + s.q.sensory + '/' + s.q.story + '/' + s.q.info +
            '] C[' + s.c.commercial + '/' + s.c.experience + ']';

        var key = s.h.persona + '_' + s.q.tone + '_' + s.q.sensory + '_' + s.q.story;
        var sample = samples[key] || samples['default'];

        state.\$cache.previewSummary.text(summary);
        state.\$cache.previewSample.html('<h4>📝 サンプル導入文:</h4><p>' + sample + '</p>');
        state.settings = s;
    }

    // ========================================
    // collectSettings()
    // ========================================
    function collectSettings() {
        if (state.settings) {
            return state.settings;
        }

        return {
            h: {
                persona: state.\$cache.personaCards.filter('.active').data('persona') || 'general',
                purpose: state.\$cache.purposeItems.filter('.checked input').map(function(){return $(this).val();}).get(),
                depth: state.\$cache.groups.depth.filter('.checked').data('value') || 'L2'
            },
            q: {
                tone: state.\$cache.selectTone.val() || 'casual',
                structure: state.\$cache.selectStructure.val() || 'timeline',
                sensory: state.\$cache.groups.sensory.filter('.checked').data('value') || 'G1',
                story: state.\$cache.groups.story.filter('.checked').data('value') || 'S1',
                info: state.\$cache.groups.info.filter('.checked').data('value') || 'I1',
                expression: state.\$cache.groups.expression.filter('.checked').data('value') || 'E1',
                volume: state.\$cache.groups.volume.filter('.checked').data('value') || 'V1',
                target: state.\$cache.groups.target.filter('.checked').data('value') || 'T1',
                seo: state.\$cache.groups.seo.filter('.checked').data('value') || 'SEO1',
                reliability: state.\$cache.groups.reliability.filter('.checked').data('value') || 'R1'
            },
            c: {
                commercial: state.\$cache.selectCommercial.val() || 'none',
                experience: state.\$cache.selectExperience.val() || 'recommend',
                contents: state.\$cache.contentItems.filter('.checked input').map(function(){return $(this).val();}).get()
            }
        };
    }

    // ========================================
    // キュー表示を部分更新
    // ========================================
    function refreshQueueList() {
        hrsAjax('hrs_get_queue_list', {}, {
            success: function(res) {
                if (res.success && res.data.html) {
                    $('#hrs-queue-list').html(res.data.html);
                    cacheDOM();
                }
            },
            error: function(err) {
                if (window.console && console.error) {
                    console.error('[HQC] Queue refresh failed:', err.message);
                }
            }
        });
    }

    // ========================================
    // 設定保存
    // ========================================
    $(document).on('click', '#hrs-save', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 保存中...');

        var settings = collectSettings();

        hrsAjax('hrs_hqc_save_settings', { settings: settings }, {
            success: function(res) {
                if (res.success) {
                    showNotice('設定を保存しました', 'success');
                } else {
                    showNotice('エラー: ' + (res.data.message || '保存に失敗しました'), 'error');
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> 設定を保存');
            }
        });
    });

    // ========================================
    // リセット
    // ========================================
    $(document).on('click', '#hrs-reset', function() {
        if (confirm('設定をデフォルトにリセットしますか？')) {
            applyPreset(presets['starter'] || Object.values(presets)[0]);
            updatePreview();
            hideWarning();
            state.\$cache.presetCards.removeClass('active');
            showNotice('設定をリセットしました', 'success');
        }
    });

    // ========================================
    // 記事生成
    // ========================================
    $(document).on('click', '#hrs-generate-single', function(e) {
        e.preventDefault();

        var hotelName = state.\$cache.hotelName.val().trim();
        if (!hotelName) {
            showNotice('ホテル名を入力してください', 'error');
            state.\$cache.hotelName.focus();
            return;
        }

        generateArticleWithRetry(hotelName, state.\$cache.hotelLocation.val().trim(), 0);
    });

    // ========================================
    // ★【修正⑦】リトライ機能付き記事生成（構文エラー修正済み）
    // ========================================
    function generateArticleWithRetry(hotelName, location, retryCount) {
        var maxRetries = 3;
        var btn = $('#hrs-generate-single');
        var result = $('#hrs-generation-result');

        if (retryCount === 0) {
            state.generating = true;
            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 生成中...');
            result.removeClass('success error loading').addClass('loading')
                  .html('<span class="dashicons dashicons-update spinning"></span> 記事を生成しています...').show();
        } else {
            result.html('<span class="dashicons dashicons-update spinning"></span> 再試行中... (' + retryCount + '/' + maxRetries + ')');
        }

        var extendedTimeout = 600000;

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: extendedTimeout,
            data: {
                action: 'hrs_generate_article',
                nonce: nonce,
                hotel_name: hotelName,
                location: location,
                settings: collectSettings()
            },
            success: function(res) {
                if (res.success) {
                    var d = res.data;
                    var editUrl = d.edit_url || (ajaxUrl.replace('admin-ajax.php', 'post.php') + '?post=' + d.post_id + '&action=edit');
                    result.removeClass('loading').addClass('success').html(
                        '<strong>✅ 記事を生成しました！</strong>' +
                        '<p>「' + (d.title || hotelName) + '」</p>' +
                        '<p><a href="' + editUrl + '" class="button button-primary" target="_blank">編集する</a></p>'
                    );
                    showNotice('記事を生成しました', 'success');
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : '生成に失敗しました';
                    result.removeClass('loading').addClass('error').html(
                        '<strong>❌ エラー</strong><p>' + msg + '</p>'
                    );
                    showNotice(msg, 'error');
                }
            },
            error: function(xhr, status) {
                if (status === 'timeout' && retryCount < maxRetries) {
                    generateArticleWithRetry(hotelName, location, retryCount + 1);
                    return;
                }
                result.removeClass('loading').addClass('error').html(
                    '<strong>❌ 通信エラー</strong><p>サーバーとの接続に失敗しました。</p>'
                );
                showNotice('通信エラーが発生しました', 'error');
            },
            complete: function() {
                state.generating = false;
                btn.prop('disabled', false).html('<span class="dashicons dashicons-media-document"></span> 今すぐ生成');
            }
        });
    }

    // ========================================
    // キューに追加
    // ========================================
    $(document).on('click', '#hrs-add-to-queue', function(e) {
        e.preventDefault();

        var hotelName = state.\$cache.hotelName.val().trim();
        if (!hotelName) {
            showNotice('ホテル名を入力してください', 'error');
            state.\$cache.hotelName.focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true);

        hrsAjax('hrs_add_to_queue', {
            hotel_name: hotelName,
            location: state.\$cache.hotelLocation.val().trim(),
            settings: JSON.stringify(collectSettings())
        }, {
            success: function(res) {
                if (res.success) {
                    showNotice(res.data.message, 'success');
                    state.\$cache.hotelName.val('');
                    state.\$cache.hotelLocation.val('');
                    refreshQueueList();
                } else {
                    showNotice(res.data.message || 'キューへの追加に失敗しました', 'error');
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました', 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // ========================================
    // キューから削除
    // ========================================
    $(document).on('click', '.hrs-remove-queue', function(e) {
        e.preventDefault();

        var hotelName = $(this).data('hotel');
        if (!confirm('「' + hotelName + '」をキューから削除しますか？')) return;

        var \$btn = $(this);
        \$btn.prop('disabled', true).html('削除中...');

        hrsAjax('hrs_remove_from_queue', { hotel_name: hotelName }, {
            success: function(res) {
                if (res.success) {
                    showNotice(res.data.message, 'success');
                    refreshQueueList();
                } else {
                    showNotice(res.data.message || '削除に失敗しました', 'error');
                    \$btn.prop('disabled', false).html('削除');
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました', 'error');
                \$btn.prop('disabled', false).html('削除');
            }
        });
    });

    // ========================================
    // キュー処理
    // ========================================
    $(document).on('click', '#hrs-process-queue', function(e) {
        e.preventDefault();

        if (!confirm('キュー内のホテルを一括生成しますか？')) return;

        var btn = $(this);
        state.generating = true;
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> 処理中...');

        hrsAjax('hrs_process_queue', { settings: collectSettings() }, {
            timeout: state.ajaxTimeout.processQueue,
            success: function(res) {
                if (res.success) {
                    showNotice(res.data.message, 'success');
                    refreshQueueList();
                } else {
                    showNotice(res.data.message || '処理に失敗しました', 'error');
                }
            },
            error: function() {
                showNotice('通信エラーが発生しました', 'error');
            },
            complete: function() {
                state.generating = false;
                btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> キューを処理');
            }
        });
    });

    // ========================================
    // showNotice()
    // ========================================
    function showNotice(message, type) {
        var notice = $('<div class="hrs-notice hrs-notice-' + type + '"></div>').text(message);
        $('body').append(notice);
        notice.fadeIn(200);
        setTimeout(function() {
            notice.fadeOut(200, function() { $(this).remove(); });
        }, 3000);
    }

    // ========================================
    // 保存済み設定の初期化
    // ========================================
    function initializeFromSaved() {
        if (!current) return;

        if (current.h) {
            if (current.h.persona) {
                state.\$cache.personaCards.removeClass('active');
                state.\$cache.personaCards.filter('[data-persona="' + current.h.persona + '"]').addClass('active');
            }
            if (current.h.depth) {
                state.\$cache.groups.depth.removeClass('checked');
                state.\$cache.groups.depth.filter('[data-value="' + current.h.depth + '"]').addClass('checked').find('input').prop('checked', true);
            }
            if (current.h.purpose && Array.isArray(current.h.purpose)) {
                state.\$cache.purposeItems.removeClass('checked').find('input').prop('checked', false);
                current.h.purpose.forEach(function(v) {
                    state.\$cache.purposeItems.filter('[data-value="' + v + '"]').addClass('checked').find('input').prop('checked', true);
                });
            }
        }

        if (current.q) {
            if (current.q.tone) state.\$cache.selectTone.val(current.q.tone);
            if (current.q.structure) state.\$cache.selectStructure.val(current.q.structure);
            ['sensory', 'story', 'info', 'expression', 'volume', 'target', 'seo', 'reliability'].forEach(function(key) {
                var value = current.q[key];
                if (value) {
                    state.\$cache.groups[key].removeClass('checked');
                    state.\$cache.groups[key].filter('[data-value="' + value + '"]').addClass('checked').find('input').prop('checked', true);
                }
            });
        }

        if (current.c) {
            if (current.c.commercial) state.\$cache.selectCommercial.val(current.c.commercial);
            if (current.c.experience) state.\$cache.selectExperience.val(current.c.experience);
            if (current.c.contents && Array.isArray(current.c.contents)) {
                state.\$cache.contentItems.removeClass('checked').find('input').prop('checked', false);
                current.c.contents.forEach(function(v) {
                    state.\$cache.contentItems.filter('[data-value="' + v + '"]').addClass('checked').find('input').prop('checked', true);
                });
            }
        }
    }

    // ========================================
    // 初期化実行
    // ========================================
    initializeFromSaved();
    var initialPersona = state.\$cache.personaCards.filter('.active').data('persona') || 'general';
    updateRecommendedPurposes(initialPersona);
    updatePreview();

    if (!$('#hrs-notice-style').length) {
        $('head').append(
            '<style id="hrs-notice-style">' +
            '.hrs-notice { position:fixed; top:40px; right:20px; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:500; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); }' +
            '.hrs-notice-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }' +
            '.hrs-notice-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }' +
            '.spinning { animation: spin 1s linear infinite; }' +
            '@keyframes spin { 100% { transform:rotate(360deg); } }' +
            '</style>'
        );
    }

    console.log('[HRS HQC] Initialized v2.4.2');
});
JAVASCRIPT;
    }

    private static function get_sample_texts()
    {
        return [
            'couple_emotional_G3_S3' => '夕陽が水平線に溶けていく瞬間、二人だけの特別な時間が始まる...',
            'couple_luxury_G3_S3' => '上質なリネンの香りに包まれて目覚める朝。窓の外には穏やかな海が広がり、二人だけの特別な一日が始まる。',
            'family_casual_G2_S2' => '子どもたちの歓声が響くプールサイド。「パパ、見て見て!」という声に振り向けば、初めての飛び込みに挑戦する姿。',
            'solo_cinematic_G3_S3' => '静寂に包まれた早朝のロビー。コーヒーの香りが漂う中、窓の向こうに広がる山々が朝日に染まっていく。',
            'workation_journalistic_G1_S1' => 'Wi-Fi環境も整い、仕事に集中できる環境が整っている。午前中は仕事、午後からは周辺観光へ。',
            'senior_casual_G2_S2' => 'ゆったりとした時間が流れる温泉宿。長年連れ添った二人で、静かに湯船に浸かる幸せ。',
            'luxury_luxury_G3_S3' => '一歩足を踏み入れた瞬間、日常から切り離された特別な空間が広がる。洗練された空気が全身を包み込む。',
            'budget_casual_G1_S1' => 'コスパ抜群！この価格でこのクオリティは正直驚き。必要なものは全て揃っています。',
            'default' => '旅の始まりは、いつも期待と発見に満ちている。このホテルで過ごす時間が、きっと忘れられない思い出になる。'
        ];
    }
}