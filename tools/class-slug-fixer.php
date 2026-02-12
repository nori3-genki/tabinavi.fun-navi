<?php
/**
 * 既存ホテルレビュー記事のスラッグを公式サイトURLから一括自動変更
 * 
 * 使い方: WordPress管理画面の「ツール」メニューから実行、
 * またはこのファイルをプラグインディレクトリに配置して有効化
 * 
 * 設置場所: /wp-content/plugins/5d-review-builder/includes/admin/tools/
 */
if (!defined('ABSPATH')) {
    exit;
}

class HRS_Slug_Fixer {

    /**
     * 初期化 - 管理メニューとAJAXハンドラーを登録
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu'], 99);
        add_action('wp_ajax_hrs_fix_slugs', [__CLASS__, 'ajax_fix_slugs']);
        add_action('wp_ajax_hrs_preview_slugs', [__CLASS__, 'ajax_preview_slugs']);
    }

    /**
     * 管理メニュー追加（5D Review Builder配下）
     */
    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=hotel-review',
            'スラッグ一括修正',
            '🔧 スラッグ修正',
            'manage_options',
            'hrs-slug-fixer',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * 公式URLからスラッグを抽出
     */
    public static function extract_slug_from_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) return '';

        // www. を除去
        $host = preg_replace('/^www\./', '', $host);

        // 日本のドメインと一般TLDを除去
        $host = preg_replace(
            '/\.(co\.jp|or\.jp|ne\.jp|ac\.jp|go\.jp|ed\.jp|gr\.jp|ad\.jp|lg\.jp|com|net|org|jp|info|biz|io|travel)$/i',
            '',
            $host
        );

        $slug = sanitize_title($host);

        if (!empty($slug) && strlen($slug) > 2) {
            return $slug;
        }

        return '';
    }

    /**
     * スラッグの重複チェック（自分自身を除外）
     */
    public static function ensure_unique_slug($slug, $post_id) {
        global $wpdb;
        $original = $slug;
        $counter = 1;

        while ($existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'hotel-review' AND ID != %d LIMIT 1",
            $slug, $post_id
        ))) {
            $counter++;
            $slug = $original . '-' . $counter;
        }

        return $slug;
    }

    /**
     * 全記事のスラッグ状態をプレビュー取得
     */
    public static function get_slug_preview() {
        global $wpdb;

        // hotel-review 投稿を全件取得
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_name 
             FROM {$wpdb->posts} 
             WHERE post_type = 'hotel-review' 
             AND post_status IN ('publish', 'draft', 'private', 'pending')
             ORDER BY ID ASC"
        );

        $results = [];

        foreach ($posts as $post) {
            // 公式URLを取得（ACFとネイティブ両方チェック）
            $official_url = get_post_meta($post->ID, 'hrp_booking_official_url', true);
            if (empty($official_url)) {
                $official_url = get_post_meta($post->ID, '_hrp_booking_official_url', true);
            }

            // 現在のスラッグが自動生成っぽいか判定
            $is_auto_slug = preg_match('/^hotel-review-\d+$/', $post->post_name);

            // 公式URLからスラッグ抽出
            $new_slug = '';
            if (!empty($official_url)) {
                $new_slug = self::extract_slug_from_url($official_url);
            }

            // 変更が必要か判定
            $needs_fix = false;
            $reason = '';

            if ($is_auto_slug && !empty($new_slug)) {
                $needs_fix = true;
                $reason = '自動生成スラッグ → 公式URL';
            } elseif ($is_auto_slug && empty($official_url)) {
                $needs_fix = false;
                $reason = '公式URLなし（手動修正が必要）';
            } elseif (!$is_auto_slug && !empty($new_slug) && $post->post_name !== $new_slug) {
                // 既にカスタムスラッグだが公式URLと異なる場合
                $needs_fix = false; // 既に手動設定済みの場合はスキップ
                $reason = '手動設定済み';
            } else {
                $reason = 'OK';
            }

            $results[] = [
                'post_id'       => $post->ID,
                'title'         => $post->post_title,
                'current_slug'  => $post->post_name,
                'official_url'  => $official_url ?: '（なし）',
                'new_slug'      => $new_slug ?: '—',
                'needs_fix'     => $needs_fix,
                'is_auto_slug'  => $is_auto_slug,
                'reason'        => $reason,
            ];
        }

        return $results;
    }

    /**
     * AJAX: プレビュー取得
     */
    public static function ajax_preview_slugs() {
        check_ajax_referer('hrs_slug_fixer_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $results = self::get_slug_preview();
        wp_send_json_success(['items' => $results]);
    }

    /**
     * AJAX: スラッグ一括修正実行
     */
    public static function ajax_fix_slugs() {
        check_ajax_referer('hrs_slug_fixer_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        // 対象の投稿IDリスト（指定がなければ全自動検出）
        $target_ids = [];
        if (!empty($_POST['post_ids'])) {
            $target_ids = array_map('intval', (array) $_POST['post_ids']);
        }

        // force_all: 自動生成スラッグ以外も公式URLで上書き
        $force_all = !empty($_POST['force_all']);

        $preview = self::get_slug_preview();
        $updated = [];
        $skipped = [];
        $errors  = [];

        foreach ($preview as $item) {
            // 対象IDが指定されていればフィルタ
            if (!empty($target_ids) && !in_array($item['post_id'], $target_ids)) {
                continue;
            }

            // 修正が必要か判定
            $should_fix = $item['needs_fix'];
            if ($force_all && !empty($item['new_slug']) && $item['new_slug'] !== '—') {
                $should_fix = true;
            }

            if (!$should_fix || empty($item['new_slug']) || $item['new_slug'] === '—') {
                $skipped[] = $item;
                continue;
            }

            // 重複チェック付きでスラッグ確定
            $final_slug = self::ensure_unique_slug($item['new_slug'], $item['post_id']);

            // WordPress更新
            $result = wp_update_post([
                'ID'        => $item['post_id'],
                'post_name' => $final_slug,
            ], true);

            if (is_wp_error($result)) {
                $errors[] = [
                    'post_id' => $item['post_id'],
                    'title'   => $item['title'],
                    'error'   => $result->get_error_message(),
                ];
            } else {
                $updated[] = [
                    'post_id'   => $item['post_id'],
                    'title'     => $item['title'],
                    'old_slug'  => $item['current_slug'],
                    'new_slug'  => $final_slug,
                ];
            }
        }

        // rewrite rulesをフラッシュ
        flush_rewrite_rules();

        wp_send_json_success([
            'updated' => $updated,
            'skipped' => count($skipped),
            'errors'  => $errors,
        ]);
    }

    /**
     * 管理画面レンダリング
     */
    public static function render_page() {
        $nonce = wp_create_nonce('hrs_slug_fixer_nonce');
        ?>
        <div class="wrap">
            <h1>🔧 スラッグ一括修正ツール</h1>
            <p>公式サイトURLからスラッグを自動生成し、<code>hotel-review-XXXX</code> 形式のスラッグを修正します。</p>

            <div style="margin: 20px 0; display: flex; gap: 12px;">
                <button id="btn-preview" class="button button-primary button-large">
                    📋 プレビュー（確認のみ）
                </button>
                <button id="btn-fix" class="button button-large" style="background:#d63638;color:#fff;border-color:#d63638;" disabled>
                    🚀 一括修正を実行
                </button>
                <label style="display:flex;align-items:center;gap:6px;margin-left:16px;">
                    <input type="checkbox" id="force-all" />
                    手動設定済みも公式URLで上書き
                </label>
            </div>

            <div id="slug-status" style="margin:16px 0;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;display:none;"></div>

            <table id="slug-table" class="wp-list-table widefat fixed striped" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="check-all" /></th>
                        <th style="width:60px;">ID</th>
                        <th>タイトル</th>
                        <th>現在のスラッグ</th>
                        <th>公式URL</th>
                        <th>新スラッグ</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody id="slug-tbody"></tbody>
            </table>

            <div id="result-box" style="margin:20px 0;display:none;"></div>
        </div>

        <style>
            .slug-needs-fix { background: #fff3cd !important; }
            .slug-ok { }
            .slug-no-url { background: #f8d7da !important; }
            .slug-old { color: #d63638; text-decoration: line-through; }
            .slug-new { color: #00a32a; font-weight: bold; }
            .slug-badge {
                display: inline-block; padding: 2px 8px; border-radius: 3px;
                font-size: 12px; font-weight: bold;
            }
            .badge-fix { background: #fff3cd; color: #856404; }
            .badge-ok { background: #d4edda; color: #155724; }
            .badge-nourl { background: #f8d7da; color: #721c24; }
            .badge-manual { background: #e2e3e5; color: #383d41; }
        </style>

        <script>
        jQuery(function($) {
            var nonce = '<?php echo $nonce; ?>';
            var previewData = [];

            // プレビュー
            $('#btn-preview').on('click', function() {
                var $btn = $(this).prop('disabled', true).text('読み込み中...');
                $('#slug-status').hide();

                $.post(ajaxurl, {
                    action: 'hrs_preview_slugs',
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).html('📋 プレビュー（確認のみ）');
                    if (!res.success) {
                        alert('エラー: ' + (res.data.message || '不明'));
                        return;
                    }

                    previewData = res.data.items;
                    var $tbody = $('#slug-tbody').empty();
                    var fixCount = 0;

                    $.each(previewData, function(i, item) {
                        var rowClass = '';
                        var badgeClass = 'badge-ok';
                        var badgeText = 'OK';

                        if (item.needs_fix) {
                            rowClass = 'slug-needs-fix';
                            badgeClass = 'badge-fix';
                            badgeText = '要修正';
                            fixCount++;
                        } else if (item.official_url === '（なし）' && item.is_auto_slug) {
                            rowClass = 'slug-no-url';
                            badgeClass = 'badge-nourl';
                            badgeText = 'URL無し';
                        } else if (item.reason === '手動設定済み') {
                            badgeClass = 'badge-manual';
                            badgeText = '手動済';
                        }

                        var officialDisplay = item.official_url;
                        if (officialDisplay !== '（なし）' && officialDisplay.length > 40) {
                            officialDisplay = officialDisplay.substring(0, 40) + '...';
                        }

                        $tbody.append(
                            '<tr class="' + rowClass + '">' +
                            '<td><input type="checkbox" class="slug-check" value="' + item.post_id + '"' + (item.needs_fix ? ' checked' : '') + ' /></td>' +
                            '<td>' + item.post_id + '</td>' +
                            '<td>' + $('<span>').text(item.title).html() + '</td>' +
                            '<td><code>' + item.current_slug + '</code></td>' +
                            '<td title="' + $('<span>').text(item.official_url).html() + '">' + $('<span>').text(officialDisplay).html() + '</td>' +
                            '<td>' + (item.new_slug !== '—' ? '<code class="slug-new">' + item.new_slug + '</code>' : '—') + '</td>' +
                            '<td><span class="slug-badge ' + badgeClass + '">' + badgeText + '</span></td>' +
                            '</tr>'
                        );
                    });

                    $('#slug-table').show();
                    $('#btn-fix').prop('disabled', fixCount === 0);
                    $('#slug-status').html(
                        '<strong>合計: ' + previewData.length + '件</strong> ｜ ' +
                        '<span style="color:#856404;">要修正: ' + fixCount + '件</span>'
                    ).show();
                });
            });

            // 全選択
            $('#check-all').on('change', function() {
                $('.slug-check').prop('checked', $(this).prop('checked'));
            });

            // 一括修正実行
            $('#btn-fix').on('click', function() {
                var ids = [];
                $('.slug-check:checked').each(function() {
                    ids.push($(this).val());
                });

                if (ids.length === 0) {
                    alert('修正対象を選択してください');
                    return;
                }

                if (!confirm(ids.length + '件のスラッグを修正します。よろしいですか？')) {
                    return;
                }

                var $btn = $(this).prop('disabled', true).text('修正中...');

                $.post(ajaxurl, {
                    action: 'hrs_fix_slugs',
                    nonce: nonce,
                    post_ids: ids,
                    force_all: $('#force-all').is(':checked') ? 1 : 0
                }, function(res) {
                    $btn.prop('disabled', false).html('🚀 一括修正を実行');

                    if (!res.success) {
                        alert('エラー: ' + (res.data.message || '不明'));
                        return;
                    }

                    var html = '<div style="padding:16px;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;">';
                    html += '<h3 style="margin:0 0 12px;">✅ 修正完了</h3>';
                    html += '<p>更新: <strong>' + res.data.updated.length + '件</strong> ｜ スキップ: ' + res.data.skipped + '件</p>';

                    if (res.data.updated.length > 0) {
                        html += '<table class="widefat" style="margin-top:12px;"><thead><tr><th>ID</th><th>タイトル</th><th>旧スラッグ</th><th>新スラッグ</th></tr></thead><tbody>';
                        $.each(res.data.updated, function(i, u) {
                            html += '<tr><td>' + u.post_id + '</td><td>' + $('<span>').text(u.title).html() + '</td>';
                            html += '<td><code class="slug-old">' + u.old_slug + '</code></td>';
                            html += '<td><code class="slug-new">' + u.new_slug + '</code></td></tr>';
                        });
                        html += '</tbody></table>';
                    }

                    if (res.data.errors.length > 0) {
                        html += '<div style="margin-top:12px;color:#721c24;">';
                        html += '<strong>エラー:</strong><ul>';
                        $.each(res.data.errors, function(i, e) {
                            html += '<li>ID ' + e.post_id + ' (' + e.title + '): ' + e.error + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    html += '</div>';
                    $('#result-box').html(html).show();
                });
            });
        });
        </script>
        <?php
    }
}

// 初期化
HRS_Slug_Fixer::init();