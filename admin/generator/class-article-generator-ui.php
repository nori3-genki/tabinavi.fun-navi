<?php
/**
 * HRS_Article_Generator_UI - UIコンポーネント
 * @package HRS\Admin\Generator
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('HRS_Article_Generator_UI')) :

class HRS_Article_Generator_UI {
    private $generator;

    public function __construct($generator) {
        $this->generator = $generator;
    }

    public function render() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('記事を生成する権限がありません。', '5d-review-builder'));
        }

        $hqc_enabled = $this->generator->hqc_enabled ?? true;
        $location_required = $this->generator->location_required ?? false;

        $regenerate_id = isset($_GET['regenerate']) ? intval($_GET['regenerate']) : 0;
        $weak_points = isset($_GET['weak_points']) ? json_decode(urldecode($_GET['weak_points']), true) : [];

        $post_title  = $regenerate_id ? get_the_title($regenerate_id) : '';
        $hotel_name  = $regenerate_id ? get_post_meta($regenerate_id, '_hrs_hotel_name', true) : '';
        $location    = $regenerate_id ? get_post_meta($regenerate_id, '_hrs_location', true) : '';

        $presets = [
            'story'  => __('物語形式', '5d-review-builder'),
            'review' => __('レビュー形式', '5d-review-builder'),
            'blog'   => __('ブログ形式', '5d-review-builder'),
        ];
        ?>
        <div class="wrap hrs-article-generator">
            <h1><span class="dashicons dashicons-welcome-write-blog"></span> 🚀 記事生成</h1>
            <p class="hrs-subtitle">AIで高品質なホテルレビュー記事を生成・保存します</p>

            <?php if ($regenerate_id): ?>
            <div class="notice notice-info">
                <p>
                    <strong>再生成モード：</strong>
                    <?php echo esc_html($hotel_name ?: $post_title); ?>
                    <?php if (!empty($weak_points)): ?>
                    （弱点補強中：<?php echo count($weak_points); ?>件）
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="hrs-container">
                <div class="hrs-card">
                    <h2><span class="dashicons dashicons-admin-settings"></span> 設定</h2>
                    <form id="article-gen-form">
                        <input type="hidden" name="regenerate_id" value="<?php echo esc_attr($regenerate_id); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('hrs_generator_nonce')); ?>">

                        <?php if (!empty($weak_points)): ?>
                        <input type="hidden" name="weak_points" value="<?php echo esc_attr(json_encode($weak_points)); ?>">
                        <?php endif; ?>

                        <div class="form-field">
                            <label for="hotel_name">
                                <?php _e('ホテル名', '5d-review-builder'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="hotel_name" name="hotel_name"
                                value="<?php echo esc_attr($hotel_name); ?>" required>
                        </div>

                        <div class="form-field">
                            <label for="location">
                                <?php _e('所在地', '5d-review-builder'); ?>
                                <?php if ($location_required): ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <input type="text" id="location" name="location"
                                value="<?php echo esc_attr($location); ?>"
                                <?php echo $location_required ? 'required' : ''; ?>>
                        </div>

                        <div class="form-field">
                            <label for="style"><?php _e('スタイル', '5d-review-builder'); ?></label>
                            <select id="style" name="style">
                                <?php foreach ($presets as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'story'); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label><?php _e('弱点補強', '5d-review-builder'); ?></label>
                            <label>
                                <input type="checkbox" name="apply_boost" value="1"
                                    <?php checked(!empty($weak_points)); ?>>
                                <?php _e('弱点を補強して生成', '5d-review-builder'); ?>
                            </label>
                            <?php if (!empty($weak_points)): ?>
                            <p class="description">
                                <?php _e('検出された弱点:', '5d-review-builder'); ?>
                                <?php
                                echo implode(', ', array_map(function ($wp) {
                                    return '<code>' . esc_html($wp['axis'] . '-' . $wp['category']) . '</code>';
                                }, $weak_points));
                                ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <button type="button" id="generate-btn"
                            class="button button-primary button-large">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('AIで記事を生成', '5d-review-builder'); ?>
                        </button>
                    </form>
                </div>

                <div class="hrs-card" id="preview-card" style="display:none;">
                    <h2><span class="dashicons dashicons-visibility"></span> プレビュー</h2>
                    <div id="preview-content" class="preview-content"></div>
                    <div class="preview-actions">
                        <button id="copy-btn" class="button">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php _e('コピー', '5d-review-builder'); ?>
                        </button>
                        <button id="save-btn" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('投稿として保存', '5d-review-builder'); ?>
                        </button>
                    </div>
                </div>

                <div id="loading" class="hrs-loading" style="display:none;">
                    <div class="spinner is-active"></div>
                    <p><?php _e('AIが記事を生成中...', '5d-review-builder'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .hrs-article-generator .hrs-subtitle { color:#666; margin:-10px 0 20px; }
            .hrs-container { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
            .hrs-card { padding:20px; background:#fff; border:1px solid #e2e4e7; }
            .form-field { margin-bottom:16px; }
            .required { color:#d63638; }
            .preview-content { background:#f9f9f9; padding:20px; min-height:300px; }
            @media (max-width:782px){ .hrs-container{grid-template-columns:1fr;} }
        </style>

        <script>
        jQuery(function($){
            const $form = $('#article-gen-form');
            const $btn = $('#generate-btn');
            const $load = $('#loading');
            const $pre = $('#preview-card');
            const $cnt = $('#preview-content');

            $btn.on('click', function(){
                $btn.prop('disabled', true);
                $load.show();

                $.post(ajaxurl, $form.serialize() + '&action=hrs_generate_article')
                    .done(function(res){
                        if(res.success){
                            $cnt.html(res.data.article);
                            $pre.show();
                        } else {
                            alert(res.data.message || '生成失敗');
                        }
                    })
                    .fail(function(){
                        alert('通信エラー');
                    })
                    .always(function(){
                        $btn.prop('disabled', false);
                        $load.hide();
                    });
            });
        });
        </script>
        <?php
    }
}

endif;