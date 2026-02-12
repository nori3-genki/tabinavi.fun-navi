<?php
/**
 * Generator UI - ユーザーインターフェースクラス
 *
 * UI描画専用（CSS/JSの直接出力は禁止）
 *
 * @package Hotel_Review_System
 * @subpackage Generator
 * @version 6.8.1-SCORE-FIX
 * 
 * 変更履歴:
 * - 6.6.1: 初期版
 * - 6.7.0: 弱点補強型再生成対応
 * - 6.8.0: HQCスコア数値表示追加
 * - 6.8.1: HQCスコア0.0表示エラー修正（_hrs_hqc_h_score等に対応）
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Generator_UI {

    /**
     * メインページをレンダリング
     * ※ HTML出力のみ。重処理・CSS/JS出力は禁止
     */
    public static function render() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('このページにアクセスする権限がありません。', '5d-review-builder'));
        }

        $presets = HRS_Generator_Data::get_presets();
        
        // 再生成パラメータを取得
        $regenerate_id = isset($_GET['regenerate']) ? intval($_GET['regenerate']) : 0;
        
        // hotel パラメータも確認（URLパターンの違いに対応）
        if (!$regenerate_id && isset($_GET['hotel'])) {
            $regenerate_id = intval($_GET['hotel']);
        }
        
        // 修正: json_decode() 失敗時に null にならないように安全に処理
        $weak_points = [];
        if (isset($_GET['weak_points'])) {
            $decoded = json_decode(urldecode($_GET['weak_points']), true);
            if (is_array($decoded)) {
                $weak_points = $decoded;
            }
        }
        
        $remaining_ids = isset($_GET['remaining']) ? sanitize_text_field($_GET['remaining']) : '';
        
        // 再生成対象の記事情報を取得
        $regenerate_data = null;
        if ($regenerate_id > 0) {
            $post = get_post($regenerate_id);
            if ($post) {
                // HQCスコアを複数のmeta key名で取得
                $h_score = self::get_hqc_score($regenerate_id, 'h');
                $q_score = self::get_hqc_score($regenerate_id, 'q');
                $c_score = self::get_hqc_score($regenerate_id, 'c');
                $total_score = self::get_hqc_score($regenerate_id, 'total');
                
                $regenerate_data = [
                    'id' => $regenerate_id,
                    'title' => $post->post_title,
                    'hotel_name' => get_post_meta($regenerate_id, '_hrs_hotel_name', true) ?: $post->post_title,
                    'location' => get_post_meta($regenerate_id, '_hrs_location', true) ?: '',
                    'score' => $total_score,
                    'h_score' => $h_score,
                    'q_score' => $q_score,
                    'c_score' => $c_score,
                    'weak_points' => $weak_points,
                ];
            }
        }
        
        // 弱点から推奨パターンを決定
        $recommended_patterns = self::get_recommended_patterns($weak_points);
        ?>
        <div class="wrap hrs-manual-wrap">
            <?php self::render_header(); ?>
            
            <?php if ($regenerate_data): ?>
                <?php self::render_regenerate_alert($regenerate_data, $recommended_patterns, $remaining_ids); ?>
            <?php endif; ?>
            
            <?php self::render_guide(); ?>

            <div class="hrs-manual-container">
                <?php self::render_settings_panel($presets, $regenerate_data, $recommended_patterns); ?>
                <?php self::render_prompt_panel(); ?>
            </div>
        </div>
        
        <?php if ($regenerate_data): ?>
            <?php self::render_regenerate_script($regenerate_data); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * HQCスコアを取得（複数のmeta key名に対応）
     * 
     * @param int $post_id
     * @param string $type 'h', 'q', 'c', 'total'
     * @return float
     */
    private static function get_hqc_score($post_id, $type) {
        $keys = array();
        
        switch ($type) {
            case 'h':
                $keys = array('_hrs_hqc_h_score', '_hrs_h_score', 'hrs_hqc_h_score', 'hrs_h_score', '_h_score', 'h_score');
                break;
            case 'q':
                $keys = array('_hrs_hqc_q_score', '_hrs_q_score', 'hrs_hqc_q_score', 'hrs_q_score', '_q_score', 'q_score');
                break;
            case 'c':
                $keys = array('_hrs_hqc_c_score', '_hrs_c_score', 'hrs_hqc_c_score', 'hrs_c_score', '_c_score', 'c_score');
                break;
            case 'total':
                $keys = array('_hrs_hqc_score', 'hrs_hqc_score', '_hqc_score', 'hqc_score');
                break;
        }
        
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if ($value && is_numeric($value) && floatval($value) > 0) {
                return floatval($value);
            }
        }
        
        return 0.0;
    }

    /**
     * 弱点から推奨パターンを決定
     */
    private static function get_recommended_patterns($weak_points) {
        if (empty($weak_points)) {
            return [];
        }
        
        $patterns = [];
        $pattern_map = [
            'H' => [
                'timeline' => '時系列構成',
                'emotion' => '感情表現',
                'scene' => 'シーン描写',
                'first_person' => '一人称視点',
                'address' => '一人称視点',
            ],
            'Q' => [
                'five_senses' => '五感描写',
                'cuisine' => '料理詳細',
                'facility' => '施設情報',
                'specificity' => '五感描写',
            ],
            'C' => [
                'headings' => '見出し最適化',
                'keyphrase' => 'キーフレーズ',
            ],
        ];
        
        foreach ($weak_points as $wp) {
            $axis = $wp['axis'] ?? '';
            $category = $wp['category'] ?? '';
            
            if (isset($pattern_map[$axis][$category])) {
                $patterns[$category] = [
                    'axis' => $axis,
                    'name' => $pattern_map[$axis][$category],
                    'category' => $category,
                ];
            }
        }
        
        return $patterns;
    }

    /**
     * 再生成アラートを表示
     */
    private static function render_regenerate_alert($data, $patterns, $remaining_ids) {
        $score = is_numeric($data['score']) ? round($data['score'], 1) : 0;
        $h_score = is_numeric($data['h_score']) ? round($data['h_score'], 1) : 0;
        $q_score = is_numeric($data['q_score']) ? round($data['q_score'], 1) : 0;
        $c_score = is_numeric($data['c_score']) ? round($data['c_score'], 1) : 0;
        
        // 弱点判定
        $h_weak = $h_score < 50;
        $q_weak = $q_score < 50;
        $c_weak = $c_score < 50;
        ?>
        <div class="hrs-regenerate-alert">
            <div class="alert-icon">⚠️</div>
            <div class="alert-content">
                <h3>弱点補強型 再生成モード</h3>
                <p>
                    <strong>「<?php echo esc_html($data['hotel_name']); ?>」</strong> 
                    （現在のスコア: <span class="score-badge score-low"><?php echo esc_html($score); ?>点</span>）
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 16px 0;">
                    <div style="background: <?php echo $h_weak ? 'rgba(239, 68, 68, 0.2)' : 'rgba(34, 197, 94, 0.2)'; ?>; padding: 12px; border-radius: 8px; text-align: center; border: 2px solid <?php echo $h_weak ? '#ef4444' : '#22c55e'; ?>;">
                        <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo $h_score; ?></div>
                        <div style="font-size: 12px; margin-top: 4px; color: #666;">H層（体験性）</div>
                        <?php if ($h_weak): ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #ef4444;">⚠️ 要強化</div>
                        <?php else: ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #22c55e;">✓ 良好</div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: <?php echo $q_weak ? 'rgba(239, 68, 68, 0.2)' : 'rgba(34, 197, 94, 0.2)'; ?>; padding: 12px; border-radius: 8px; text-align: center; border: 2px solid <?php echo $q_weak ? '#ef4444' : '#22c55e'; ?>;">
                        <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo $q_score; ?></div>
                        <div style="font-size: 12px; margin-top: 4px; color: #666;">Q層（品質）</div>
                        <?php if ($q_weak): ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #ef4444;">⚠️ 要強化</div>
                        <?php else: ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #22c55e;">✓ 良好</div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: <?php echo $c_weak ? 'rgba(239, 68, 68, 0.2)' : 'rgba(34, 197, 94, 0.2)'; ?>; padding: 12px; border-radius: 8px; text-align: center; border: 2px solid <?php echo $c_weak ? '#ef4444' : '#22c55e'; ?>;">
                        <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo $c_score; ?></div>
                        <div style="font-size: 12px; margin-top: 4px; color: #666;">C層（構造）</div>
                        <?php if ($c_weak): ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #ef4444;">⚠️ 要強化</div>
                        <?php else: ?>
                        <div style="font-size: 10px; margin-top: 4px; color: #22c55e;">✓ 良好</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($patterns)): ?>
                    <div class="weak-points-summary">
                        <strong>検出された弱点:</strong>
                        <ul>
                            <?php foreach ($patterns as $p): ?>
                                <li>
                                    <span class="axis-badge axis-<?php echo esc_attr(strtolower($p['axis'])); ?>">
                                        <?php echo esc_html($p['axis']); ?>軸
                                    </span>
                                    <?php echo esc_html($p['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <p class="alert-note">
                        → これらの弱点を補強したプロンプトが自動適用されます。80点以上を目指して再生成してください。
                    </p>
                <?php endif; ?>
                
                <?php if ($remaining_ids): ?>
                    <p class="remaining-note">
                        📋 残り <?php echo count(explode(',', $remaining_ids)); ?> 件の低スコア記事があります
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .hrs-regenerate-alert {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border: 2px solid #ff9800;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .hrs-regenerate-alert .alert-icon {
            font-size: 32px;
        }
        .hrs-regenerate-alert h3 {
            margin: 0 0 8px 0;
            color: #e65100;
        }
        .hrs-regenerate-alert p {
            margin: 0 0 8px 0;
        }
        .hrs-regenerate-alert .score-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .hrs-regenerate-alert .score-low {
            background: #ffebee;
            color: #c62828;
        }
        .hrs-regenerate-alert .weak-points-summary {
            background: rgba(255,255,255,0.7);
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
        }
        .hrs-regenerate-alert .weak-points-summary ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        .hrs-regenerate-alert .weak-points-summary li {
            margin-bottom: 4px;
        }
        .hrs-regenerate-alert .axis-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 4px;
        }
        .hrs-regenerate-alert .axis-h { background: #e3f2fd; color: #1565c0; }
        .hrs-regenerate-alert .axis-q { background: #e8f5e9; color: #2e7d32; }
        .hrs-regenerate-alert .axis-c { background: #fce4ec; color: #c2185b; }
        .hrs-regenerate-alert .alert-note {
            color: #e65100;
            font-weight: 500;
        }
        .hrs-regenerate-alert .remaining-note {
            color: #666;
            font-size: 13px;
        }
        </style>
        <?php
    }

    /**
     * 再生成用JavaScriptを出力
     */
    private static function render_regenerate_script($data) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // ホテル名を自動入力
            $('#manual-hotel-name').val(<?php echo json_encode($data['hotel_name']); ?>);
            
            // 所在地を自動入力
            <?php if (!empty($data['location'])): ?>
            $('#manual-location').val(<?php echo json_encode($data['location']); ?>);
            <?php endif; ?>
            
            // 弱点データをhidden fieldに保存
            var weakPoints = <?php echo json_encode($data['weak_points']); ?>;
            if (weakPoints && weakPoints.length > 0) {
                $('<input>').attr({
                    type: 'hidden',
                    id: 'regenerate-weak-points',
                    value: JSON.stringify(weakPoints)
                }).appendTo('#hrs-manual-form');
                
                $('<input>').attr({
                    type: 'hidden',
                    id: 'regenerate-post-id',
                    value: <?php echo intval($data['id']); ?>
                }).appendTo('#hrs-manual-form');
            }
            
            // スタイルレイヤーを自動選択（弱点に基づく）
            <?php 
            // 修正: $data['weak_points'] が配列か確認してから foreach を実行
            $weak_axes = [];
            if (is_array($data['weak_points'])) {
                foreach ($data['weak_points'] as $wp) {
                    $weak_axes[] = $wp['axis'] ?? '';
                }
            }
            if (in_array('H', $weak_axes)): ?>
            // H軸が弱い → 季節感、地域色を追加
            $('input[value="seasonal"]').prop('checked', true);
            $('input[value="local"]').prop('checked', true);
            <?php endif; ?>
            
            <?php if (in_array('Q', $weak_axes)): ?>
            // Q軸が弱い → 高級感を追加（具体性向上）
            $('input[value="luxury"]').prop('checked', true);
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /**
     * ヘッダー
     */
    private static function render_header() {
        ?>
        <div class="hrs-page-header">
            <div class="hrs-header-content">
                <h1>
                    <span class="dashicons dashicons-editor-paste-text"></span>
                    手動プロンプト生成
                </h1>
                <p class="hrs-page-subtitle">
                    Claude、Gemini、ChatGPTで使えるプロンプトを生成してコピー
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * ガイドカード
     */
    private static function render_guide() {
        $steps = [
            ['num' => 1, 'title' => 'ホテル情報を入力', 'desc' => 'ホテル名と必要な設定を選択します'],
            ['num' => 2, 'title' => 'プロンプト生成', 'desc' => 'AIサービスを選択してプロンプトを生成'],
            ['num' => 3, 'title' => 'コピー＆貼り付け', 'desc' => '生成されたプロンプトを外部AIにコピー'],
            ['num' => 4, 'title' => '記事を保存', 'desc' => '生成された記事をWordPressに投稿'],
        ];
        ?>
        <div class="hrs-guide-card">
            <h3>
                <span class="dashicons dashicons-info"></span> 使い方ガイド
            </h3>
            <div class="guide-steps">
                <?php foreach ($steps as $i => $step): ?>
                    <?php if ($i > 0): ?>
                        <div class="guide-arrow">→</div>
                    <?php endif; ?>
                    <div class="guide-step">
                        <div class="step-number"><?php echo (int)$step['num']; ?></div>
                        <div class="step-content">
                            <strong><?php echo esc_html($step['title']); ?></strong>
                            <p><?php echo esc_html($step['desc']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 設定パネル
     */
    private static function render_settings_panel(array $presets, $regenerate_data = null, $recommended_patterns = []) {
        // プリセットデータをJSON化（JS用）
        $presets_json = json_encode($presets, JSON_UNESCAPED_UNICODE);
        ?>
        <div class="hrs-settings-panel">
            <div class="hrs-card">
                <div class="hrs-card-header">
                    <h2>
                        <span class="dashicons dashicons-admin-settings"></span> 設定
                        <?php if ($regenerate_data): ?>
                            <span class="regenerate-badge">再生成モード</span>
                        <?php endif; ?>
                    </h2>
                </div>

                <div class="hrs-card-body">
                    <form id="hrs-manual-form">
                        <div class="form-group">
                            <label for="manual-hotel-name" class="required">
                                <span class="dashicons dashicons-admin-home"></span> ホテル名
                            </label>
                            <input type="text" id="manual-hotel-name" class="hrs-input"
                                   placeholder="例: 星野リゾート 界 加賀" required>
                        </div>

                        <div class="form-group">
                            <label for="manual-location">
                                <span class="dashicons dashicons-location"></span> 所在地（任意）
                            </label>
                            <input type="text" id="manual-location" class="hrs-input"
                                   placeholder="例: 石川県加賀市">
                        </div>

                        <div class="form-group">
                            <label for="manual-preset">
                                <span class="dashicons dashicons-admin-appearance"></span> HQCプリセット
                            </label>
                            <select id="manual-preset" class="hrs-select">
                                <?php foreach ($presets as $id => $preset): 
                                    $scores = isset($preset['hqc_scores']) ? $preset['hqc_scores'] : ['H' => 33, 'Q' => 34, 'C' => 33];
                                ?>
                                    <option value="<?php echo esc_attr($id); ?>"
                                        <?php selected($id, 'balanced'); ?>
                                        data-h="<?php echo esc_attr($scores['H']); ?>"
                                        data-q="<?php echo esc_attr($scores['Q']); ?>"
                                        data-c="<?php echo esc_attr($scores['C']); ?>">
                                        <?php echo esc_html($preset['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- HQCスコア表示エリア -->
                            <div class="hqc-score-display" id="hqc-score-display">
                                <div class="hqc-score-item hqc-h">
                                    <span class="hqc-label">H</span>
                                    <span class="hqc-value" id="hqc-h-value">33</span>
                                    <div class="hqc-bar"><div class="hqc-bar-fill" id="hqc-h-bar" style="width:33%"></div></div>
                                </div>
                                <div class="hqc-score-item hqc-q">
                                    <span class="hqc-label">Q</span>
                                    <span class="hqc-value" id="hqc-q-value">34</span>
                                    <div class="hqc-bar"><div class="hqc-bar-fill" id="hqc-q-bar" style="width:34%"></div></div>
                                </div>
                                <div class="hqc-score-item hqc-c">
                                    <span class="hqc-label">C</span>
                                    <span class="hqc-value" id="hqc-c-value">33</span>
                                    <div class="hqc-bar"><div class="hqc-bar-fill" id="hqc-c-bar" style="width:33%"></div></div>
                                </div>
                            </div>
                            <p class="hqc-description" id="hqc-description">SEOと読みやすさのバランスを重視</p>
                        </div>

                        <div class="form-group">
                            <label for="manual-words">
                                <span class="dashicons dashicons-text"></span> 目標文字数
                            </label>
                            <select id="manual-words" class="hrs-select">
                                <option value="1500">1500文字（標準）</option>
                                <option value="2000" selected>2000文字（推奨）</option>
                                <option value="2500">2500文字（詳細）</option>
                                <option value="3000">3000文字（超詳細）</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="dashicons dashicons-art"></span>
                                スタイルレイヤー（任意）
                                <?php if (!empty($recommended_patterns)): ?>
                                    <span class="auto-selected-note">※弱点に基づき自動選択</span>
                                <?php endif; ?>
                            </label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" value="seasonal"> 🌸 季節感</label>
                                <label><input type="checkbox" value="local"> 🏞️ 地域色</label>
                                <label><input type="checkbox" value="luxury"> 💎 高級感</label>
                                <label><input type="checkbox" value="family"> 👨‍👩‍👧‍👦 ファミリー</label>
                            </div>
                        </div>
                        
                        <?php if (!empty($recommended_patterns)): ?>
                        <div class="form-group boost-patterns-info">
                            <label>
                                <span class="dashicons dashicons-superhero"></span>
                                自動適用される補強パターン
                            </label>
                            <div class="boost-patterns-list">
                                <?php foreach ($recommended_patterns as $p): ?>
                                    <span class="boost-pattern-tag">
                                        <?php echo esc_html($p['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <p class="boost-note">これらの補強がプロンプトに自動追加されます</p>
                        </div>
                        <?php endif; ?>

                        <button type="button" id="generate-prompt-btn"
                                class="hrs-button hrs-button-primary hrs-button-large">
                            <span class="dashicons dashicons-welcome-write-blog"></span>
                            <?php if ($regenerate_data): ?>
                                弱点補強プロンプトを生成
                            <?php else: ?>
                                プロンプトを生成
                            <?php endif; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .regenerate-badge {
            background: #ff9800;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 8px;
        }
        .auto-selected-note {
            font-size: 11px;
            color: #ff9800;
            font-weight: normal;
        }
        .boost-patterns-info {
            background: #e3f2fd;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #90caf9;
        }
        .boost-patterns-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0;
        }
        .boost-pattern-tag {
            background: #1976d2;
            color: #fff;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 13px;
        }
        .boost-note {
            font-size: 12px;
            color: #1565c0;
            margin: 0;
        }
        
        /* HQCスコア表示 */
        .hqc-score-display {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .hqc-score-item {
            flex: 1;
            text-align: center;
        }
        .hqc-label {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 12px;
            color: #fff;
            margin-right: 6px;
        }
        .hqc-h .hqc-label { background: #1565c0; }
        .hqc-q .hqc-label { background: #2e7d32; }
        .hqc-c .hqc-label { background: #c2185b; }
        .hqc-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .hqc-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 6px;
            overflow: hidden;
        }
        .hqc-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .hqc-h .hqc-bar-fill { background: #1565c0; }
        .hqc-q .hqc-bar-fill { background: #2e7d32; }
        .hqc-c .hqc-bar-fill { background: #c2185b; }
        .hqc-description {
            margin: 8px 0 0 0;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var presets = <?php echo $presets_json; ?>;
            
            $('#manual-preset').on('change', function() {
                var selected = $(this).find(':selected');
                var h = selected.data('h') || 33;
                var q = selected.data('q') || 34;
                var c = selected.data('c') || 33;
                var presetId = $(this).val();
                var desc = presets[presetId] ? presets[presetId].description : '';
                
                // 数値更新
                $('#hqc-h-value').text(h);
                $('#hqc-q-value').text(q);
                $('#hqc-c-value').text(c);
                
                // バー更新（50を最大として表示、見やすさのため）
                $('#hqc-h-bar').css('width', (h * 2) + '%');
                $('#hqc-q-bar').css('width', (q * 2) + '%');
                $('#hqc-c-bar').css('width', (c * 2) + '%');
                
                // 説明更新
                $('#hqc-description').text(desc);
            });
            
            // 初期表示
            $('#manual-preset').trigger('change');
        });
        </script>
        <?php
    }

    /**
     * プロンプトパネル
     */
    private static function render_prompt_panel() {
        ?>
        <div class="hrs-prompt-panel">
            <!-- AIタブ -->
            <div class="hrs-ai-tabs">
                <div class="ai-tab active" data-ai="chatgpt">
                    <span class="ai-logo">🟢</span>
                    <span class="ai-name">ChatGPT</span>
                </div>
                <div class="ai-tab" data-ai="claude">
                    <span class="ai-logo">🟤</span>
                    <span class="ai-name">Claude</span>
                </div>
                <div class="ai-tab" data-ai="gemini">
                    <span class="ai-logo">🔵</span>
                    <span class="ai-name">Gemini</span>
                </div>
            </div>

            <!-- プロンプトカード -->
            <div class="hrs-card hrs-prompt-card">
                <div class="hrs-card-header">
                    <h2>
                        <span class="dashicons dashicons-editor-code"></span>
                        生成されたプロンプト
                    </h2>
                    <button type="button" id="copy-prompt-btn"
                            class="hrs-button hrs-button-small" disabled>
                        コピー
                    </button>
                </div>

                <div class="hrs-card-body">
                    <div id="prompt-empty-state">
                        プロンプトを生成してください
                    </div>
                    <pre id="prompt-text" style="display:none;"></pre>
                </div>
            </div>

            <!-- AIリンクカード -->
            <div class="hrs-ai-links-card">
                <h3>
                    <span class="dashicons dashicons-external"></span>
                    AIサービスを開く
                </h3>
                <div class="ai-links-grid">
                    <a href="https://chat.openai.com" target="_blank" class="ai-link-card">
                        <span class="ai-link-icon">🟢</span>
                        <div class="ai-link-info">
                            <strong>ChatGPT</strong>
                            <span>chat.openai.com を開く</span>
                        </div>
                    </a>
                    <a href="https://claude.ai" target="_blank" class="ai-link-card">
                        <span class="ai-link-icon">🟤</span>
                        <div class="ai-link-info">
                            <strong>Claude</strong>
                            <span>claude.ai を開く</span>
                        </div>
                    </a>
                    <a href="https://gemini.google.com" target="_blank" class="ai-link-card">
                        <span class="ai-link-icon">🔵</span>
                        <div class="ai-link-info">
                            <strong>Gemini</strong>
                            <span>gemini.google.com を開く</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}