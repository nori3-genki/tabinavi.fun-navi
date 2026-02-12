/**
 * 5D Review Builder - HQC Generator JavaScript
 * HQC Generator管理画面のインタラクティブ機能
 * @package Hotel_Review_System
 * @version 7.0.4-FIX
 * 
 * 変更履歴:
 * - 7.0.4-FIX: UI側のセレクタ（class-hqc-ui.php v7.3.0）と完全同期
 *   - 初期化条件: .hrs-hqc-wrap に変更
 *   - セレクタ修正: #hrs-tone, #hrs-structure, #hrs-commercial, #hrs-experience
 *   - プリセット: .hrs-preset-card クリックに変更
 *   - 生成ボタン: #hrs-generate-single, #hrs-add-to-queue に対応
 *   - 目的チェックボックス: .hrs-checkbox-group に変更
 *   - HRS.Ajax 依存を削除、直接 $.ajax 使用（依存軽減）
 */
(function($) {
    'use strict';

    window.HRS = window.HRS || {};

    HRS.HQC = {

        currentSettings: {},
        nonce: '',
        ajaxurl: '',

        /**
         * 初期化
         */
        init: function() {
            // グローバル変数から初期設定を取得
            if (typeof HRS_HQC !== 'undefined') {
                this.currentSettings = HRS_HQC.current || {};
                this.nonce = HRS_HQC.nonce || '';
                this.ajaxurl = HRS_HQC.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            } else if (typeof hqcData !== 'undefined') {
                this.currentSettings = hqcData.current || {};
                this.nonce = hqcData.nonce || '';
                this.ajaxurl = hqcData.ajaxurl || '/wp-admin/admin-ajax.php';
            } else {
                console.error('[HRS HQC] HRS_HQC / hqcData が見つかりません');
                return;
            }

            this.bindEvents();
            this.updatePreview();

            console.log('[HRS HQC] Initialized v7.0.4-FIX');
        },

        /**
         * AJAX送信ヘルパー
         */
        ajaxPost: function(action, data) {
            data.action = action;
            data.nonce = this.nonce;

            return $.ajax({
                url: this.ajaxurl,
                type: 'POST',
                data: data,
                dataType: 'json'
            });
        },

        /**
         * イベントバインド（class-hqc-ui.php v7.3.0 のセレクタに合わせる）
         */
        bindEvents: function() {
            var self = this;

            // ===========================
            // ペルソナカード選択
            // ===========================
            $(document).on('click', '.hrs-persona-card', function(e) {
                e.preventDefault();
                $('.hrs-persona-card').removeClass('active');
                $(this).addClass('active');
                self.updatePreview();
            });

            // ===========================
            // レベルアイテム（深度、五感強度、物語強度、等）
            // ===========================
            $(document).on('click', '.hrs-level-item', function(e) {
                e.preventDefault();
                var group = $(this).data('group');
                if (!group) return;
                $('.hrs-level-item[data-group="' + group + '"]').removeClass('checked');
                $(this).addClass('checked');
                $(this).find('input[type="radio"]').prop('checked', true);
                self.updatePreview();
            });

            // ===========================
            // チェックボックスアイテム（旅の目的、C層コンテンツ要素）
            // ===========================
            $(document).on('click', '.hrs-checkbox-item', function(e) {
                // input自体のクリックはバブリング防止
                if ($(e.target).is('input[type="checkbox"]')) {
                    e.stopPropagation();
                    var isChecked = $(e.target).prop('checked');
                    $(this).toggleClass('checked', isChecked);
                    self.updatePreview();
                    return;
                }
                e.preventDefault();
                var $cb = $(this).find('input[type="checkbox"]');
                var newState = !$cb.prop('checked');
                $cb.prop('checked', newState);
                $(this).toggleClass('checked', newState);
                self.updatePreview();
            });

            // ===========================
            // セレクト変更（トーン、構造、商業性、体験表現）
            // ===========================
            $(document).on('change', '#hrs-tone, #hrs-structure, #hrs-commercial, #hrs-experience', function() {
                self.updatePreview();
            });

            // ===========================
            // プリセットカード（新UI: .hrs-preset-card）
            // ===========================
            $(document).on('click', '.hrs-preset-card', function(e) {
                e.preventDefault();
                var preset = $(this).data('preset');
                if (preset) {
                    self.applyPreset(preset);
                }
            });

            // ===========================
            // 設定保存 (#hrs-save)
            // ===========================
            $(document).on('click', '#hrs-save', function(e) {
                e.preventDefault();
                self.saveSettings();
            });

            // ===========================
            // リセット (#hrs-reset)
            // ===========================
            $(document).on('click', '#hrs-reset', function(e) {
                e.preventDefault();
                if (confirm('設定をデフォルトに戻しますか？')) {
                    self.resetSettings();
                }
            });

            // ===========================
            // 今すぐ生成 (#hrs-generate-single)
            // ===========================
            $(document).on('click', '#hrs-generate-single', function(e) {
                e.preventDefault();
                self.generateArticle();
            });

            // ===========================
            // キューに追加 (#hrs-add-to-queue)
            // ===========================
            $(document).on('click', '#hrs-add-to-queue', function(e) {
                e.preventDefault();
                self.addToQueue();
            });

            // ===========================
            // キューから削除
            // ===========================
            $(document).on('click', '.hrs-remove-queue', function(e) {
                e.preventDefault();
                var hotelName = $(this).data('hotel');
                if (hotelName) {
                    self.removeFromQueue(hotelName);
                }
            });

            // ===========================
            // キュー処理 (#hrs-process-queue)
            // ===========================
            $(document).on('click', '#hrs-process-queue', function(e) {
                e.preventDefault();
                self.processQueue();
            });

            // ===========================
            // 強制生成（動的に生成される）
            // ===========================
            $(document).on('click', '#hrs-force-generate', function(e) {
                e.preventDefault();
                self.generateArticle(true);
            });
        },

        // ===========================
        // プリセット適用
        // ===========================
        applyPreset: function(presetName) {
            var self = this;
            self.showLoading('プリセットを適用中...');

            self.ajaxPost('hrs_hqc_apply_preset', {
                preset: presetName
            }).done(function(response) {
                if (response.success && response.data) {
                    self.reflectToUI(response.data);
                    self.updatePreview();
                    self.showNotice('success', 'プリセット「' + presetName + '」を適用しました');
                } else {
                    self.showNotice('error', response.data?.message || 'プリセット適用に失敗しました');
                }
            }).fail(function(xhr) {
                self.showNotice('error', '通信エラー: ' + xhr.status);
            }).always(function() {
                self.hideLoading();
            });
        },

        /**
         * UIにデータを反映
         */
        reflectToUI: function(data) {
            // H層
            if (data.h) {
                // ペルソナ
                $('.hrs-persona-card').removeClass('active');
                $('.hrs-persona-card[data-persona="' + data.h.persona + '"]').addClass('active');

                // 情報深度
                $('.hrs-level-item[data-group="depth"]').removeClass('checked');
                $('.hrs-level-item[data-group="depth"][data-value="' + data.h.depth + '"]').addClass('checked');

                // 旅の目的
                if (data.h.purpose && Array.isArray(data.h.purpose)) {
                    // 目的はUIの .hrs-checkbox-group 内
                    $('.hrs-checkbox-group .hrs-checkbox-item').each(function() {
                        var val = $(this).data('value') || $(this).find('input').val();
                        var shouldCheck = data.h.purpose.indexOf(val) !== -1;
                        $(this).toggleClass('checked', shouldCheck);
                        $(this).find('input[type="checkbox"]').prop('checked', shouldCheck);
                    });
                }
            }

            // Q層
            if (data.q) {
                // セレクト（新UIのID）
                if (data.q.tone) $('#hrs-tone').val(data.q.tone);
                if (data.q.structure) $('#hrs-structure').val(data.q.structure);

                // レベルグループ
                var levelGroups = ['sensory', 'story', 'info', 'expression', 'volume', 'target', 'seo', 'reliability'];
                levelGroups.forEach(function(group) {
                    if (data.q[group]) {
                        $('.hrs-level-item[data-group="' + group + '"]').removeClass('checked');
                        $('.hrs-level-item[data-group="' + group + '"][data-value="' + data.q[group] + '"]').addClass('checked');
                        // ラジオボタン同期
                        $('.hrs-level-item[data-group="' + group + '"][data-value="' + data.q[group] + '"] input[type="radio"]').prop('checked', true);
                    }
                });
            }

            // C層
            if (data.c) {
                if (data.c.commercial) $('#hrs-commercial').val(data.c.commercial);
                if (data.c.experience) $('#hrs-experience').val(data.c.experience);

                // コンテンツ要素
                if (data.c.contents && Array.isArray(data.c.contents)) {
                    $('.hrs-content-items .hrs-checkbox-item').each(function() {
                        var val = $(this).data('value') || $(this).find('input').val();
                        var shouldCheck = data.c.contents.indexOf(val) !== -1;
                        $(this).toggleClass('checked', shouldCheck);
                        $(this).find('input[type="checkbox"]').prop('checked', shouldCheck);
                    });
                }
            }
        },

        // ===========================
        // 設定収集（UIから現在の状態を取得）
        // ===========================
        collectSettings: function() {
            // 旅の目的
            var purposes = [];
            $('.hrs-checkbox-group .hrs-checkbox-item.checked').each(function() {
                var val = $(this).data('value') || $(this).find('input').val();
                if (val) purposes.push(val);
            });

            // C層コンテンツ要素
            var contents = [];
            $('.hrs-content-items .hrs-checkbox-item.checked').each(function() {
                var val = $(this).data('value') || $(this).find('input').val();
                if (val) contents.push(val);
            });

            return {
                h: {
                    persona: $('.hrs-persona-card.active').data('persona') || 'general',
                    purpose: purposes,
                    depth: $('.hrs-level-item[data-group="depth"].checked').data('value') || 'L2'
                },
                q: {
                    tone: $('#hrs-tone').val() || 'casual',
                    structure: $('#hrs-structure').val() || 'timeline',
                    sensory: $('.hrs-level-item[data-group="sensory"].checked').data('value') || 'G1',
                    story: $('.hrs-level-item[data-group="story"].checked').data('value') || 'S1',
                    info: $('.hrs-level-item[data-group="info"].checked').data('value') || 'I1',
                    expression: $('.hrs-level-item[data-group="expression"].checked').data('value') || 'E1',
                    volume: $('.hrs-level-item[data-group="volume"].checked').data('value') || 'V1',
                    target: $('.hrs-level-item[data-group="target"].checked').data('value') || 'T1',
                    seo: $('.hrs-level-item[data-group="seo"].checked').data('value') || 'SEO1',
                    reliability: $('.hrs-level-item[data-group="reliability"].checked').data('value') || 'R1'
                },
                c: {
                    commercial: $('#hrs-commercial').val() || 'none',
                    experience: $('#hrs-experience').val() || 'recommend',
                    contents: contents
                }
            };
        },

        // ===========================
        // リアルタイムプレビュー更新
        // ===========================
        updatePreview: function() {
            var settings = this.collectSettings();

            this.ajaxPost('hrs_hqc_preview', {
                settings: settings
            }).done(function(response) {
                if (response.success && response.data) {
                    if (response.data.summary) {
                        $('#preview-summary').text(response.data.summary);
                    }
                    if (response.data.sample) {
                        $('#preview-sample p').text(response.data.sample);
                    }
                }
            });
        },

        // ===========================
        // 設定保存
        // ===========================
        saveSettings: function() {
            var self = this;
            var settings = this.collectSettings();
            self.showLoading('設定を保存中...');

            self.ajaxPost('hrs_hqc_save_settings', {
                settings: settings
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', '設定を保存しました');
                } else {
                    self.showNotice('error', '保存に失敗: ' + (response.data?.message || '不明なエラー'));
                }
            }).fail(function(xhr) {
                self.showNotice('error', '通信エラー: ' + xhr.status);
            }).always(function() {
                self.hideLoading();
            });
        },

        // ===========================
        // リセット
        // ===========================
        resetSettings: function() {
            // デフォルト値に戻す
            var defaults = {
                h: { persona: 'general', purpose: ['sightseeing'], depth: 'L2' },
                q: { tone: 'casual', structure: 'timeline', sensory: 'G1', story: 'S1', info: 'I1', expression: 'E1', volume: 'V1', target: 'T1', seo: 'SEO1', reliability: 'R1' },
                c: { commercial: 'none', experience: 'recommend', contents: ['cta', 'price_info', 'access_info'] }
            };
            this.reflectToUI(defaults);
            this.updatePreview();
            this.showNotice('info', 'デフォルト設定に戻しました');
        },

        // ===========================
        // 記事生成
        // ===========================
        generateArticle: function(forceGenerate) {
            var self = this;
            var hotelName = $('#hrs-hotel-name').val();
            var location = $('#hrs-hotel-location').val();

            if (!hotelName) {
                self.showNotice('error', 'ホテル名を入力してください');
                return;
            }

            var settings = this.collectSettings();
            var $result = $('#hrs-generation-result');
            $result.hide().empty();

            self.showLoading('記事を生成中...');

            self.ajaxPost('hrs_generate_article', {
                hotel_name: hotelName,
                location: location,
                settings: settings,
                skip_hqc_check: forceGenerate ? 1 : 0
            }).done(function(response) {
                if (response.success && response.data) {
                    var d = response.data;
                    var editUrl = d.edit_url || (self.ajaxurl.replace('admin-ajax.php', 'post.php') + '?post=' + d.post_id + '&action=edit');
                    var html = '<div class="hrs-success-box" style="padding:12px; background:#d1fae5; border:1px solid #6ee7b7; border-radius:8px; margin-top:12px;">';
                    html += '<strong>✅ 記事を生成しました！</strong>';
                    html += '<p>「' + (d.title || hotelName) + '」</p>';
                    html += '<p><a href="' + editUrl + '" class="button button-primary" target="_blank">編集する</a></p>';
                    html += '</div>';
                    $result.html(html).fadeIn(300);
                    self.showNotice('success', '記事を生成しました');
                } else {
                    var msg = response.data?.message || '生成に失敗しました';
                    var html = '<div class="hrs-error-box" style="padding:12px; background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; margin-top:12px;">';
                    html += '<strong>❌ エラー</strong><p>' + msg + '</p>';
                    if (!forceGenerate) {
                        html += '<p><button type="button" id="hrs-force-generate" class="button">強制生成</button></p>';
                    }
                    html += '</div>';
                    $result.html(html).fadeIn(300);
                    self.showNotice('error', msg);
                }
            }).fail(function(xhr) {
                var msg = '通信エラー: ' + xhr.status;
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.data?.message) msg = resp.data.message;
                } catch(e) {}
                $result.html('<div style="padding:12px; background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; margin-top:12px;">❌ ' + msg + '</div>').fadeIn(300);
                self.showNotice('error', msg);
            }).always(function() {
                self.hideLoading();
            });
        },

        // ===========================
        // キューに追加
        // ===========================
        addToQueue: function() {
            var self = this;
            var hotelName = $('#hrs-hotel-name').val();
            var location = $('#hrs-hotel-location').val();

            if (!hotelName) {
                self.showNotice('error', 'ホテル名を入力してください');
                return;
            }

            var settings = this.collectSettings();
            self.showLoading('キューに追加中...');

            self.ajaxPost('hrs_add_to_queue', {
                hotel_name: hotelName,
                location: location,
                settings: settings
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', response.data?.message || 'キューに追加しました');
                    // ホテル名をクリア
                    $('#hrs-hotel-name').val('');
                    $('#hrs-hotel-location').val('');
                    // ページリロードでキュー一覧を更新
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    self.showNotice('error', response.data?.message || 'キュー追加に失敗しました');
                }
            }).fail(function(xhr) {
                self.showNotice('error', '通信エラー: ' + xhr.status);
            }).always(function() {
                self.hideLoading();
            });
        },

        // ===========================
        // キューから削除
        // ===========================
        removeFromQueue: function(hotelName) {
            var self = this;
            if (!confirm('「' + hotelName + '」をキューから削除しますか？')) return;

            self.ajaxPost('hrs_remove_from_queue', {
                hotel_name: hotelName
            }).done(function(response) {
                if (response.success) {
                    self.showNotice('success', response.data?.message || '削除しました');
                    setTimeout(function() { location.reload(); }, 500);
                }
            }).fail(function(xhr) {
                self.showNotice('error', '通信エラー: ' + xhr.status);
            });
        },

        // ===========================
        // キュー処理
        // ===========================
        processQueue: function() {
            var self = this;
            if (!confirm('キュー内のホテルの記事を一括生成しますか？')) return;

            var settings = this.collectSettings();
            self.showLoading('キューを処理中...');

            self.ajaxPost('hrs_process_queue', {
                settings: settings
            }).done(function(response) {
                if (response.success && response.data) {
                    var d = response.data;
                    self.showNotice('success', d.message || ('成功: ' + d.success_count + '件'));
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    self.showNotice('error', response.data?.message || '処理に失敗しました');
                }
            }).fail(function(xhr) {
                self.showNotice('error', '通信エラー: ' + xhr.status);
            }).always(function() {
                self.hideLoading();
            });
        },

        // ===========================
        // UI通知ヘルパー
        // ===========================
        showNotice: function(type, message) {
            // HRS.Notify が使える場合はそれを使う
            if (typeof HRS !== 'undefined' && HRS.Notify) {
                if (type === 'success') HRS.Notify.success(message);
                else if (type === 'error') HRS.Notify.error(message);
                else if (type === 'info') HRS.Notify.info ? HRS.Notify.info(message) : HRS.Notify.success(message);
                return;
            }

            // フォールバック: WordPress標準の通知
            var classes = {
                'success': 'notice-success',
                'error': 'notice-error',
                'info': 'notice-info'
            };
            var $notice = $('<div class="notice ' + (classes[type] || 'notice-info') + ' is-dismissible" style="position:fixed; top:40px; right:20px; z-index:99999; min-width:300px; padding:12px 16px; box-shadow:0 4px 12px rgba(0,0,0,0.15);"><p>' + message + '</p></div>');
            $('body').append($notice);
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        },

        showLoading: function(message) {
            if (typeof HRS !== 'undefined' && HRS.Loading) {
                HRS.Loading.show(message);
                return;
            }
            // フォールバック
            if ($('#hrs-loading-overlay').length === 0) {
                $('body').append('<div id="hrs-loading-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.3); z-index:99998; display:flex; align-items:center; justify-content:center;"><div style="background:#fff; padding:24px 40px; border-radius:12px; font-size:16px; box-shadow:0 4px 20px rgba(0,0,0,0.2);">⏳ ' + (message || '処理中...') + '</div></div>');
            }
        },

        hideLoading: function() {
            if (typeof HRS !== 'undefined' && HRS.Loading) {
                HRS.Loading.hide();
                return;
            }
            $('#hrs-loading-overlay').remove();
        }
    };

    // ===========================
    // 初期化（.hrs-hqc-wrap があれば起動）
    // ===========================
    $(document).ready(function() {
        if ($('.hrs-hqc-wrap').length > 0) {
            HRS.HQC.init();
        }
    });

})(jQuery);