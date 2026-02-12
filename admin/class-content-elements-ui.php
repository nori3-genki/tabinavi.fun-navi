<?php
/**
 * コンテンツ要素選択UI
 * 
 * 記事生成画面でC層コンテンツ要素を選択するインターフェース
 * AI生成要素とAPI連動要素を視覚的に区別
 * 
 * @package HRS
 * @version 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Content_Elements_UI {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンテンツ要素選択UIを出力
     * 
     * @param string $selected_persona 選択中のペルソナ
     * @param array $selected_elements 選択済み要素（空の場合はペルソナデフォルト）
     */
    public function render($selected_persona = 'general', $selected_elements = array()) {
        $content_elements = HRS_Content_Elements::get_instance();
        $all_items = $content_elements->get_c_content_items();
        
        // 選択済みがない場合はペルソナデフォルトを使用
        if (empty($selected_elements)) {
            $selected_elements = $content_elements->get_persona_c_defaults($selected_persona);
        }
        ?>
        <div class="hrs-content-elements-ui">
            <style>
                .hrs-content-elements-ui {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                }
                .hrs-content-elements-ui h3 {
                    margin-top: 0;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .hrs-elements-legend {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f9f9f9;
                    border-radius: 4px;
                    font-size: 12px;
                }
                .hrs-legend-item {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
                .hrs-legend-ai { color: #1565c0; }
                .hrs-legend-api { color: #e65100; }
                .hrs-elements-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }
                @media (max-width: 782px) {
                    .hrs-elements-grid { grid-template-columns: 1fr; }
                }
                .hrs-element-card {
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 15px;
                    cursor: pointer;
                    transition: all 0.2s;
                    position: relative;
                }
                .hrs-element-card:hover {
                    border-color: #2271b1;
                    background: #f0f7ff;
                }
                .hrs-element-card.selected {
                    border-color: #2271b1;
                    background: #e3f2fd;
                }
                .hrs-element-card.type-api {
                    border-style: dashed;
                }
                .hrs-element-card.type-api.selected {
                    border-color: #e65100;
                    background: #fff3e0;
                }
                .hrs-element-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 8px;
                }
                .hrs-element-checkbox {
                    width: 20px;
                    height: 20px;
                }
                .hrs-element-name {
                    font-weight: 600;
                    flex-grow: 1;
                }
                .hrs-element-type-badge {
                    font-size: 10px;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-weight: 600;
                }
                .hrs-type-ai {
                    background: #e3f2fd;
                    color: #1565c0;
                }
                .hrs-type-api {
                    background: #fff3e0;
                    color: #e65100;
                }
                .hrs-element-description {
                    font-size: 12px;
                    color: #666;
                    margin-left: 30px;
                }
                .hrs-element-api-note {
                    font-size: 11px;
                    color: #e65100;
                    margin-top: 5px;
                    margin-left: 30px;
                    font-style: italic;
                }
                .hrs-preset-buttons {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e0e0e0;
                }
                .hrs-preset-buttons label {
                    margin-right: 10px;
                    font-weight: 600;
                }
                .hrs-preset-btn {
                    margin-right: 5px;
                    margin-bottom: 5px;
                }
            </style>

            <h3>
                📋 コンテンツ要素
                <span style="font-size: 12px; font-weight: normal; color: #666;">
                    記事に含める要素を選択
                </span>
            </h3>

            <!-- 凡例 -->
            <div class="hrs-elements-legend">
                <div class="hrs-legend-item hrs-legend-ai">
                    <span class="hrs-element-type-badge hrs-type-ai">AI</span>
                    <span>AIが記事内に生成</span>
                </div>
                <div class="hrs-legend-item hrs-legend-api">
                    <span class="hrs-element-type-badge hrs-type-api">API</span>
                    <span>楽天APIから自動挿入（AIは書かない）</span>
                </div>
            </div>

            <!-- 要素グリッド -->
            <div class="hrs-elements-grid">
                <?php foreach ($all_items as $element_id => $item) : 
                    $is_api = ($item['type'] ?? 'ai') === 'api';
                    $is_selected = in_array($element_id, $selected_elements);
                    $type_class = $is_api ? 'type-api' : 'type-ai';
                    $selected_class = $is_selected ? 'selected' : '';
                ?>
                    <div class="hrs-element-card <?php echo $type_class; ?> <?php echo $selected_class; ?>"
                         data-element-id="<?php echo esc_attr($element_id); ?>">
                        <div class="hrs-element-header">
                            <input type="checkbox" 
                                   class="hrs-element-checkbox" 
                                   name="hrs_content_elements[]" 
                                   value="<?php echo esc_attr($element_id); ?>"
                                   id="element-<?php echo esc_attr($element_id); ?>"
                                   <?php checked($is_selected); ?>>
                            <label class="hrs-element-name" for="element-<?php echo esc_attr($element_id); ?>">
                                <?php echo esc_html($item['name']); ?>
                            </label>
                            <span class="hrs-element-type-badge <?php echo $is_api ? 'hrs-type-api' : 'hrs-type-ai'; ?>">
                                <?php echo $is_api ? 'API' : 'AI'; ?>
                            </span>
                        </div>
                        <div class="hrs-element-description">
                            <?php 
                            if ($is_api) {
                                echo esc_html($item['prompt_exclude']);
                            } else {
                                // プロンプトの先頭部分を表示
                                echo esc_html(mb_substr($item['prompt'], 0, 60)) . '...';
                            }
                            ?>
                        </div>
                        <?php if ($is_api) : ?>
                            <div class="hrs-element-api-note">
                                ⚡ 楽天トラベルの実データを自動表示
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- プリセットボタン -->
            <div class="hrs-preset-buttons">
                <label>プリセット:</label>
                <?php
                $persona_labels = array(
                    'general' => '一般',
                    'solo' => '一人旅',
                    'couple' => 'カップル',
                    'family' => 'ファミリー',
                    'senior' => 'シニア',
                    'workation' => 'ワーケーション',
                    'luxury' => 'ラグジュアリー',
                    'budget' => 'コスパ重視',
                );
                foreach ($persona_labels as $persona_id => $label) :
                    $defaults = $content_elements->get_persona_c_defaults($persona_id);
                ?>
                    <button type="button" 
                            class="button hrs-preset-btn" 
                            data-preset="<?php echo esc_attr($persona_id); ?>"
                            data-elements='<?php echo esc_attr(json_encode($defaults)); ?>'>
                        <?php echo esc_html($label); ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="button hrs-preset-btn" data-preset="all">全選択</button>
                <button type="button" class="button hrs-preset-btn" data-preset="none">全解除</button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // カードクリックでチェックボックス切り替え
            $('.hrs-element-card').on('click', function(e) {
                if ($(e.target).is('input')) return;
                
                var $checkbox = $(this).find('.hrs-element-checkbox');
                $checkbox.prop('checked', !$checkbox.prop('checked'));
                $(this).toggleClass('selected', $checkbox.prop('checked'));
            });

            // チェックボックス変更時のカード状態更新
            $('.hrs-element-checkbox').on('change', function() {
                $(this).closest('.hrs-element-card').toggleClass('selected', $(this).prop('checked'));
            });

            // プリセットボタン
            $('.hrs-preset-btn').on('click', function() {
                var preset = $(this).data('preset');
                
                if (preset === 'all') {
                    $('.hrs-element-checkbox').prop('checked', true);
                    $('.hrs-element-card').addClass('selected');
                } else if (preset === 'none') {
                    $('.hrs-element-checkbox').prop('checked', false);
                    $('.hrs-element-card').removeClass('selected');
                } else {
                    var elements = $(this).data('elements');
                    
                    // 全解除
                    $('.hrs-element-checkbox').prop('checked', false);
                    $('.hrs-element-card').removeClass('selected');
                    
                    // 指定要素を選択
                    elements.forEach(function(el) {
                        var $card = $('.hrs-element-card[data-element-id="' + el + '"]');
                        $card.addClass('selected');
                        $card.find('.hrs-element-checkbox').prop('checked', true);
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 選択された要素を保存
     */
    public static function save_selected_elements($post_id, $elements) {
        if (!is_array($elements)) {
            $elements = array();
        }
        
        // サニタイズ
        $elements = array_map('sanitize_text_field', $elements);
        
        // 有効な要素のみ保存
        $content_def = HRS_Content_Elements::get_instance();
        $valid_ids = array_keys($content_def->get_c_content_items());
        $elements = array_intersect($elements, $valid_ids);
        
        update_post_meta($post_id, '_hrs_content_elements', $elements);
        
        return $elements;
    }

    /**
     * 投稿から選択された要素を取得
     */
    public static function get_selected_elements($post_id) {
        $elements = get_post_meta($post_id, '_hrs_content_elements', true);
        return is_array($elements) ? $elements : array();
    }
}