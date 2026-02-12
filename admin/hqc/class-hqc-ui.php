<?php
/**
 * HQC UI - ユーザーインターフェースクラス
 * 
 * @package Hotel_Review_System
 * @subpackage HQC
 * @version 7.3.0
 * 
 * 変更履歴:
 * - 7.3.0: キュー一覧にHQCパラメータサマリー表示追加（個別パラメータ対応）
 * - 7.2.2: 依存クラスの存在チェック＋デバッグ警告追加
 * - 7.2.1: HRS_HQC変数を render_page() 内で直接出力
 * - 7.2.0: Q層を9項目に拡張（表現スタイル、情報量、SEO強度追加）
 * - 7.1.0: 元のレイアウト維持、2カラム構成、コンパクト化
 */

if (!defined('ABSPATH')) {
    exit;
}

// ★ 依存クラスチェック（デバッグ警告）
if (defined('WP_DEBUG') && WP_DEBUG) {
    $required_classes = ['HRS_Hqc_Data', 'HRS_Hqc_Presets', 'HRS_HQC_Generator'];
    foreach ($required_classes as $_cls) {
        if (!class_exists($_cls, false)) {
            error_log('[HRS] WARNING: class-hqc-ui.php loaded but dependency missing: ' . $_cls);
        }
    }
    unset($required_classes, $_cls);
}

class HRS_Hqc_UI {

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }

        // 依存クラスの存在チェック
        if (!class_exists('HRS_Hqc_Data')) {
            echo '<div class="notice notice-error"><p><strong>エラー:</strong> HRS_Hqc_Data クラスが読み込まれていません。プラグインファイルの読み込み順を確認してください。</p></div>';
            return;
        }
        if (!class_exists('HRS_Hqc_Presets')) {
            echo '<div class="notice notice-error"><p><strong>エラー:</strong> HRS_Hqc_Presets クラスが読み込まれていません。</p></div>';
            return;
        }

        // nonce生成
        $nonce = wp_create_nonce('hrs_hqc_nonce');

        $defaults = HRS_Hqc_Data::get_default_settings();
        $saved = get_option('hrs_hqc_settings', []);
        $current = [
            'h' => wp_parse_args($saved['h'] ?? [], $defaults['h']),
            'q' => wp_parse_args($saved['q'] ?? [], $defaults['q']),
            'c' => wp_parse_args($saved['c'] ?? [], $defaults['c']),
        ];
        
        $personas = HRS_Hqc_Data::get_personas();
        $purposes = HRS_Hqc_Data::get_purposes();
        $tones = HRS_Hqc_Data::get_tones();
        $structures = HRS_Hqc_Data::get_structures();
        $presets = HRS_Hqc_Presets::get_presets();

        // HRS_HQCグローバル変数を直接出力
        ?>
        <script type="text/javascript">
        var HRS_HQC = <?php echo wp_json_encode([
            'nonce' => $nonce,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'current' => $current,
        ]); ?>;
        var hqcData = HRS_HQC; // 互換性のためのエイリアス
        </script>
        
        <div class="hrs-hqc-wrap">
            <div class="hrs-hqc-header">
                <h1><span class="dashicons dashicons-star-filled"></span> HQC Generator</h1>
                <p>Human/Quality/Content フレームワークで最適な記事生成パラメータを設定（v<?php echo esc_html(defined('HRS_HQC_Generator::VERSION') ? HRS_HQC_Generator::VERSION : '7.0.4'); ?>）</p>
            </div>

            <div class="hrs-hqc-main">
                <div class="hrs-settings-column">
                    <?php self::render_hotel_input(); ?>
                    <?php self::render_h_layer($current, $personas, $purposes); ?>
                    <?php self::render_q_layer($current, $tones, $structures); ?>
                    <?php self::render_c_layer($current); ?>
                </div>
                
                <div class="hrs-preview-column">
                    <?php self::render_presets($presets); ?>
                    <?php self::render_preview(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * ホテル入力 + キュー一覧
     * 
     * @version 7.3.0 キューにパラメータサマリー表示追加
     */
    private static function render_hotel_input() {
        $queue = get_option('hrs_generation_queue', []);
        ?>
        <div class="hrs-hqc-card hrs-hotel-card">
            <h3><span class="dashicons dashicons-building"></span> 育成ホテル</h3>
            <div class="hrs-form-row">
                <label><span class="dashicons dashicons-location"></span> ホテル名 <span class="required">*</span></label>
                <input type="text" id="hrs-hotel-name" class="hrs-input" placeholder="例: 星野リゾート 界 箱根">
            </div>
            <div class="hrs-form-row">
                <label><span class="dashicons dashicons-location-alt"></span> 所在地（任意）</label>
                <input type="text" id="hrs-hotel-location" class="hrs-input" placeholder="例: 神奈川県足柄下郡箱根町">
            </div>
            <div class="hrs-button-row">
                <button type="button" id="hrs-add-to-queue" class="hrs-button hrs-button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> キューに追加
                </button>
                <button type="button" id="hrs-generate-single" class="hrs-button hrs-button-primary">
                    <span class="dashicons dashicons-media-document"></span> 今すぐ生成
                </button>
            </div>
            
            <!-- ★【v7.3.0】キュー追加時の説明テキスト -->
            <p class="hrs-queue-hint" style="margin:8px 0 0; font-size:12px; color:#6b7280;">
                💡 「キューに追加」すると、<strong>その時点のHQC設定</strong>がホテルに紐づきます。ホテルごとにパラメータを変えて追加できます。
            </p>
            
            <?php if (!empty($queue)): ?>
            <div class="hrs-queue-section">
                <h4><span class="dashicons dashicons-list-view"></span> 生成キュー（<?php echo count($queue); ?>件）</h4>
                <ul class="hrs-queue-list">
                    <?php foreach ($queue as $item): ?>
                    <li class="hrs-queue-item">
                        <div class="hrs-queue-item-main">
                            <span class="hotel-name"><?php echo esc_html($item['hotel_name']); ?></span>
                            <?php if (!empty($item['options']['location'])): ?>
                            <span class="hotel-location">（<?php echo esc_html($item['options']['location']); ?>）</span>
                            <?php endif; ?>
                            <button type="button" class="hrs-remove-queue" data-hotel="<?php echo esc_attr($item['hotel_name']); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <!-- ★【v7.3.0】パラメータサマリー表示 -->
                        <?php if (!empty($item['summary'])): ?>
                        <div class="hrs-queue-item-params">
                            <span class="dashicons dashicons-admin-settings" style="font-size:13px; width:13px; height:13px; color:#9ca3af;"></span>
                            <span class="param-summary"><?php echo esc_html($item['summary']); ?></span>
                        </div>
                        <?php elseif (!empty($item['options']['settings'])): ?>
                        <div class="hrs-queue-item-params">
                            <span class="dashicons dashicons-admin-settings" style="font-size:13px; width:13px; height:13px; color:#9ca3af;"></span>
                            <span class="param-summary"><?php 
                                echo esc_html(HRS_Auto_Generator::build_settings_summary($item['options']['settings'])); 
                            ?></span>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" id="hrs-process-queue" class="hrs-button hrs-button-success">
                    <span class="dashicons dashicons-controls-play"></span> キューを処理（<?php echo count($queue); ?>件）
                </button>
            </div>
            <?php endif; ?>
            <div id="hrs-generation-result" class="hrs-result-box" style="display:none;"></div>
        </div>
        <?php
    }

    private static function render_h_layer($current, $personas, $purposes) {
        ?>
        <div class="hrs-hqc-card">
            <h3><span class="dashicons dashicons-admin-users"></span> H層：Human Layer（読者設定）</h3>
            
            <div class="hrs-layer-section">
                <h4><span class="badge h">H</span> ペルソナ（8種類）</h4>
                <div class="hrs-persona-grid">
                    <?php foreach ($personas as $id => $persona): ?>
                    <div class="hrs-persona-card <?php echo ($current['h']['persona'] === $id) ? 'active' : ''; ?>" data-persona="<?php echo esc_attr($id); ?>">
                        <div class="icon"><?php echo $persona['icon']; ?></div>
                        <div class="name"><?php echo esc_html($persona['name']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="hrs-layer-section">
                <h4><span class="badge h">H</span> 旅の目的（複数選択可）<small style="color:#10b981; margin-left:6px;">★ = 推奨</small></h4>
                <div class="hrs-checkbox-group">
                    <?php 
                    $selected_purposes = is_array($current['h']['purpose']) ? $current['h']['purpose'] : ['sightseeing'];
                    foreach ($purposes as $id => $purpose): 
                        $checked = in_array($id, $selected_purposes, true);
                    ?>
                    <label class="hrs-checkbox-item <?php echo $checked ? 'checked' : ''; ?>" data-value="<?php echo esc_attr($id); ?>">
                        <input type="checkbox" value="<?php echo esc_attr($id); ?>" <?php checked($checked); ?>>
                        <?php echo $purpose['icon']; ?> <?php echo esc_html($purpose['name']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="hrs-warning-box" class="hrs-warning-box"></div>
            </div>
            
            <div class="hrs-layer-section">
                <h4><span class="badge h">H</span> 情報深度</h4>
                <div class="hrs-level-group">
                    <?php foreach (['L1' => '概要', 'L2' => '標準', 'L3' => '詳細'] as $level => $desc): ?>
                    <label class="hrs-level-item <?php echo $current['h']['depth'] === $level ? 'checked' : ''; ?>" data-group="depth" data-value="<?php echo $level; ?>">
                        <input type="radio" name="depth" value="<?php echo $level; ?>">
                        <div class="level"><?php echo $level; ?></div>
                        <div class="desc"><?php echo $desc; ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_q_layer($current, $tones, $structures) {
        ?>
        <div class="hrs-hqc-card">
            <h3><span class="dashicons dashicons-art"></span> Q層：Quality Layer（品質設定）</h3>
            
            <div class="hrs-layer-section">
                <!-- Row 1: トーン & 構造 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> トーン</label>
                        <select id="hrs-tone">
                            <?php foreach ($tones as $id => $tone): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($current['q']['tone'], $id); ?>>
                                <?php echo esc_html($tone['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 構造</label>
                        <select id="hrs-structure">
                            <?php foreach ($structures as $id => $structure): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($current['q']['structure'], $id); ?>>
                                <?php echo esc_html($structure['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Row 2: 五感強度 & 物語強度 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 五感強度</label>
                        <div class="hrs-level-group">
                            <?php foreach (['G1' => '基本', 'G2' => '標準', 'G3' => '没入'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['sensory'] ?? 'G1') === $level ? 'checked' : ''; ?>" data-group="sensory" data-value="<?php echo $level; ?>">
                                <input type="radio" name="sensory" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 物語強度</label>
                        <div class="hrs-level-group">
                            <?php foreach (['S1' => '説明的', 'S2' => '物語的', 'S3' => 'ドラマ'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['story'] ?? 'S1') === $level ? 'checked' : ''; ?>" data-group="story" data-value="<?php echo $level; ?>">
                                <input type="radio" name="story" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: 情報強度 & 表現スタイル -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 情報強度</label>
                        <div class="hrs-level-group">
                            <?php foreach (['I1' => '軽量', 'I2' => '標準', 'I3' => '詳細'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['info'] ?? 'I1') === $level ? 'checked' : ''; ?>" data-group="info" data-value="<?php echo $level; ?>">
                                <input type="radio" name="info" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 表現スタイル</label>
                        <div class="hrs-level-group">
                            <?php foreach (['E1' => 'シンプル', 'E2' => 'バランス', 'E3' => '文学的'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['expression'] ?? 'E1') === $level ? 'checked' : ''; ?>" data-group="expression" data-value="<?php echo $level; ?>">
                                <input type="radio" name="expression" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Row 4: 情報量 & ターゲット最適化 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 情報量</label>
                        <div class="hrs-level-group">
                            <?php foreach (['V1' => 'コンパクト', 'V2' => '標準', 'V3' => '充実'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['volume'] ?? 'V1') === $level ? 'checked' : ''; ?>" data-group="volume" data-value="<?php echo $level; ?>">
                                <input type="radio" name="volume" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> ターゲット最適化</label>
                        <div class="hrs-level-group">
                            <?php foreach (['T1' => '汎用', 'T2' => '最適化', 'T3' => '特化'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['target'] ?? 'T1') === $level ? 'checked' : ''; ?>" data-group="target" data-value="<?php echo $level; ?>">
                                <input type="radio" name="target" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Row 5: SEO強度 & 信頼性 -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> SEO強度</label>
                        <div class="hrs-level-group">
                            <?php foreach (['SEO1' => '軽量', 'SEO2' => '標準', 'SEO3' => '強化'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['seo'] ?? 'SEO1') === $level ? 'checked' : ''; ?>" data-group="seo" data-value="<?php echo $level; ?>">
                                <input type="radio" name="seo" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge q">Q</span> 信頼性</label>
                        <div class="hrs-level-group">
                            <?php foreach (['R1' => '基本', 'R2' => '検証済', 'R3' => '高信頼'] as $level => $desc): ?>
                            <label class="hrs-level-item <?php echo ($current['q']['reliability'] ?? 'R1') === $level ? 'checked' : ''; ?>" data-group="reliability" data-value="<?php echo $level; ?>">
                                <input type="radio" name="reliability" value="<?php echo $level; ?>">
                                <div class="level"><?php echo $level; ?></div>
                                <div class="desc"><?php echo $desc; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_c_layer($current) {
        $c_content_items = self::get_c_content_items();
        $selected_contents = isset($current['c']['contents']) && is_array($current['c']['contents']) 
            ? $current['c']['contents'] 
            : ['cta', 'price_info', 'access_info'];
        ?>
        <div class="hrs-hqc-card">
            <h3><span class="dashicons dashicons-chart-line"></span> C層：Content Layer（商業性）</h3>
            
            <div class="hrs-layer-section">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="hrs-form-group">
                        <label><span class="badge c">C</span> 商業性レベル</label>
                        <select id="hrs-commercial">
                            <option value="none" <?php selected($current['c']['commercial'] ?? 'seo', 'none'); ?>>なし</option>
                            <option value="seo" <?php selected($current['c']['commercial'] ?? 'seo', 'seo'); ?>>SEO重視</option>
                            <option value="conversion" <?php selected($current['c']['commercial'] ?? 'seo', 'conversion'); ?>>CV重視</option>
                        </select>
                    </div>
                    <div class="hrs-form-group">
                        <label><span class="badge c">C</span> 体験表現</label>
                        <select id="hrs-experience">
                            <option value="record" <?php selected($current['c']['experience'] ?? 'recommend', 'record'); ?>>記録的</option>
                            <option value="recommend" <?php selected($current['c']['experience'] ?? 'recommend', 'recommend'); ?>>推薦的</option>
                            <option value="immersive" <?php selected($current['c']['experience'] ?? 'recommend', 'immersive'); ?>>没入的</option>
                        </select>
                    </div>
                </div>
                
                <div class="hrs-form-group" style="margin-top:14px;">
                    <label><span class="badge c">C</span> コンテンツ要素</label>
                    <div class="hrs-content-items">
                        <?php foreach ($c_content_items as $id => $item): 
                            $checked = in_array($id, $selected_contents, true);
                        ?>
                        <label class="hrs-checkbox-item <?php echo $checked ? 'checked' : ''; ?>" data-value="<?php echo esc_attr($id); ?>">
                            <input type="checkbox" name="c_contents[]" value="<?php echo esc_attr($id); ?>" <?php checked($checked); ?>>
                            <span class="item-label"><?php echo $item['icon']; ?> <?php echo esc_html($item['name']); ?></span>
                            <span class="item-desc"><?php echo esc_html($item['desc']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="hrs-actions">
                <button type="button" id="hrs-save" class="hrs-btn hrs-btn-primary">
                    <span class="dashicons dashicons-saved"></span> 設定を保存
                </button>
                <button type="button" id="hrs-reset" class="hrs-btn hrs-btn-secondary">
                    <span class="dashicons dashicons-image-rotate"></span> リセット
                </button>
            </div>
        </div>
        <?php
    }

    public static function get_c_content_items() {
        return [
            'cta' => ['name' => 'CTA', 'icon' => '🎯', 'desc' => '行動喚起', 'prompt' => ''],
            'affiliate_links' => ['name' => '予約リンク', 'icon' => '🔗', 'desc' => 'OTAリンク', 'prompt' => ''],
            'price_info' => ['name' => '価格情報', 'icon' => '💰', 'desc' => '料金・プラン', 'prompt' => ''],
            'comparison' => ['name' => '比較', 'icon' => '⚖️', 'desc' => '他との比較', 'prompt' => ''],
            'faq' => ['name' => 'FAQ', 'icon' => '❓', 'desc' => 'Q&A', 'prompt' => ''],
            'pros_cons' => ['name' => 'メリデメ', 'icon' => '👍', 'desc' => '良い点・注意点', 'prompt' => ''],
            'target_audience' => ['name' => 'ターゲット', 'icon' => '👤', 'desc' => 'おすすめ層', 'prompt' => ''],
            'seasonal_info' => ['name' => '季節情報', 'icon' => '🗓️', 'desc' => 'ベストシーズン', 'prompt' => ''],
            'access_info' => ['name' => 'アクセス', 'icon' => '🚃', 'desc' => '交通・周辺', 'prompt' => ''],
            'reviews' => ['name' => '口コミ', 'icon' => '⭐', 'desc' => '評判・感想', 'prompt' => ''],
        ];
    }

    private static function render_presets($presets) {
        ?>
        <div class="hrs-hqc-card">
            <h3><span class="dashicons dashicons-portfolio"></span> クイックプリセット</h3>
            <div class="hrs-preset-grid">
                <?php foreach ($presets['presets'] as $id => $preset): ?>
                <div class="hrs-preset-card" data-preset="<?php echo esc_attr($id); ?>">
                    <div class="preset-header">
                        <span class="icon"><?php echo $preset['icon']; ?></span>
                        <span class="name"><?php echo esc_html($preset['name']); ?></span>
                    </div>
                    <div class="desc"><?php echo esc_html($preset['desc']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_preview() {
        ?>
        <div class="hrs-hqc-card">
            <h3><span class="dashicons dashicons-visibility"></span> リアルタイムプレビュー</h3>
            <div class="hrs-preview-area">
                <div class="hrs-preview-content">
                    <div class="hrs-preview-summary" id="preview-summary">H[general/L2] Q[casual/timeline/G1/S1/I1/E1/V1/SEO1] C[none/recommend]</div>
                    <div class="hrs-preview-sample" id="preview-sample">
                        <h4>📝 サンプル導入文:</h4>
                        <p>旅の始まりは、いつも期待と発見に満ちている。このホテルで過ごす時間が、きっと忘れられない思い出になる。</p>
                    </div>
                </div>
            </div>
            <div class="hrs-hint">
                <h4><span class="dashicons dashicons-lightbulb"></span> ヒント</h4>
                <ul>
                    <li>プリセットで全パラメータを一括設定</li>
                    <li>ペルソナ変更で推奨設定が適用</li>
                    <li>★マークは推奨の目的</li>
                    <li>💡 キュー追加時の設定がホテルごとに保存されます</li>
                </ul>
            </div>
        </div>
        <?php
    }
}