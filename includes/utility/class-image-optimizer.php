<?php
/**
 * 画像最適化クラス
 * 
 * - アイキャッチ画像のalt属性自動生成
 * - 遅延読み込み（lazy loading）自動付与
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Image_Optimizer {

    /**
     * コンストラクタ
     */
    public function __construct() {
        // アイキャッチ設定時にalt自動生成
        add_action('set_post_thumbnail', array($this, 'auto_generate_alt'), 10, 3);
        
        // 既存画像のalt更新（記事保存時）
        add_action('save_post_hotel-review', array($this, 'update_thumbnail_alt'), 20, 2);
        
        // 本文中の画像にlazy loading付与
        add_filter('the_content', array($this, 'add_lazy_loading'), 99);
        
        // wp_get_attachment_image にもlazy loading
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_attributes'), 10, 3);
        
        // 画像アップロード時のデフォルト設定
        add_filter('wp_handle_upload_prefilter', array($this, 'optimize_upload_settings'));
    }

    /**
     * アイキャッチ設定時にalt自動生成
     * 
     * @param int $post_id 投稿ID
     * @param int $thumbnail_id サムネイルID
     * @param string $previous_thumbnail_id 前のサムネイルID
     */
    public function auto_generate_alt($post_id, $thumbnail_id, $previous_thumbnail_id = '') {
        // hotel-review以外は無視
        if (get_post_type($post_id) !== 'hotel-review') {
            return;
        }

        $this->generate_and_set_alt($post_id, $thumbnail_id);
    }

    /**
     * 記事保存時にアイキャッチのalt更新
     * 
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     */
    public function update_thumbnail_alt($post_id, $post) {
        // 自動保存は無視
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return;
        }

        // 既存のaltが空、または自動生成されたものの場合のみ更新
        $current_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        if (empty($current_alt) || $this->is_auto_generated_alt($current_alt)) {
            $this->generate_and_set_alt($post_id, $thumbnail_id);
        }
    }

    /**
     * alt属性を生成して設定
     * 
     * @param int $post_id 投稿ID
     * @param int $thumbnail_id サムネイルID
     */
    private function generate_and_set_alt($post_id, $thumbnail_id) {
        $alt = $this->generate_alt_text($post_id);
        
        if (!empty($alt)) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $alt);
            
            // タイトルも更新（任意）
            wp_update_post(array(
                'ID' => $thumbnail_id,
                'post_title' => $alt,
            ));

            error_log('[HRS Image Optimizer] Alt set for post ' . $post_id . ': ' . $alt);
        }
    }

    /**
     * alt属性テキストを生成
     * 
     * @param int $post_id 投稿ID
     * @return string alt属性テキスト
     */
    public function generate_alt_text($post_id) {
        // ホテル名取得
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            $hotel_name = get_the_title($post_id);
        }

        // ペルソナ取得
        $persona = get_post_meta($post_id, '_hrs_persona', true);
        $persona_label = $this->get_persona_label($persona);

        // 地域取得
        $location = get_post_meta($post_id, '_hrs_location', true);
        if (empty($location)) {
            // addressから抽出を試みる
            $hotel_data = get_post_meta($post_id, '_hrs_hotel_data', true);
            if (!empty($hotel_data['address'])) {
                $location = $this->extract_short_location($hotel_data['address']);
            }
        }

        // alt構築
        $alt_parts = array($hotel_name);

        // 画像タイプ（外観がデフォルト）
        $alt_parts[] = '外観';

        // 地域があれば追加
        if (!empty($location)) {
            $alt_parts[] = $location;
        }

        // ペルソナがあれば追加
        if (!empty($persona_label)) {
            $alt_parts[] = $persona_label . '向け';
        }

        // 宿タイプ
        $alt_parts[] = '宿泊施設';

        // [HRS_AUTO] マーカーを追加（自動生成識別用）
        $alt = implode(' ', $alt_parts);

        return $alt;
    }

    /**
     * 自動生成されたaltかどうか判定
     * 
     * @param string $alt alt属性
     * @return bool
     */
    private function is_auto_generated_alt($alt) {
        // 「外観」「宿泊施設」を含む場合は自動生成と判定
        return (mb_strpos($alt, '外観') !== false && mb_strpos($alt, '宿泊施設') !== false);
    }

    /**
     * ペルソナラベル取得
     * 
     * @param string $persona ペルソナキー
     * @return string
     */
    private function get_persona_label($persona) {
        $labels = array(
            'family' => 'ファミリー',
            'couple' => 'カップル',
            'solo' => '一人旅',
            'senior' => 'シニア',
            'luxury' => 'ラグジュアリー',
            'budget' => 'コスパ重視',
            'workation' => 'ワーケーション',
            'general' => '',
        );
        return $labels[$persona] ?? '';
    }

    /**
     * 住所から短い地域名を抽出
     * 
     * @param string $address 住所
     * @return string
     */
    private function extract_short_location($address) {
        // 都道府県を抽出
        if (preg_match('/(北海道|東京都|大阪府|京都府|.{2,3}県)/u', $address, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * 本文中の画像にlazy loading付与
     * 
     * @param string $content 本文コンテンツ
     * @return string
     */
    public function add_lazy_loading($content) {
        if (empty($content)) {
            return $content;
        }

        // imgタグを処理
        $content = preg_replace_callback(
            '/<img([^>]*)>/i',
            array($this, 'process_img_tag'),
            $content
        );

        // iframeタグも処理（YouTube埋め込み等）
        $content = preg_replace_callback(
            '/<iframe([^>]*)>/i',
            array($this, 'process_iframe_tag'),
            $content
        );

        return $content;
    }

    /**
     * imgタグを処理
     * 
     * @param array $matches 正規表現マッチ
     * @return string
     */
    private function process_img_tag($matches) {
        $attributes = $matches[1];

        // 既にloading属性がある場合はスキップ
        if (stripos($attributes, 'loading=') !== false) {
            return $matches[0];
        }

        // ファーストビュー画像（最初の画像）はスキップする場合
        // ここでは全画像にlazy loadingを適用
        
        // loading="lazy" を追加
        $attributes .= ' loading="lazy"';

        // decoding="async" も追加（レンダリングブロック防止）
        if (stripos($attributes, 'decoding=') === false) {
            $attributes .= ' decoding="async"';
        }

        return '<img' . $attributes . '>';
    }

    /**
     * iframeタグを処理
     * 
     * @param array $matches 正規表現マッチ
     * @return string
     */
    private function process_iframe_tag($matches) {
        $attributes = $matches[1];

        // 既にloading属性がある場合はスキップ
        if (stripos($attributes, 'loading=') !== false) {
            return $matches[0];
        }

        // loading="lazy" を追加
        $attributes .= ' loading="lazy"';

        return '<iframe' . $attributes . '>';
    }

    /**
     * wp_get_attachment_image にlazy属性追加
     * 
     * @param array $attr 属性配列
     * @param WP_Post $attachment 添付ファイル
     * @param string|array $size サイズ
     * @return array
     */
    public function add_lazy_attributes($attr, $attachment, $size) {
        // loading属性がなければ追加
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }

        // decoding属性がなければ追加
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    /**
     * アップロード設定の最適化
     * 
     * @param array $file ファイル情報
     * @return array
     */
    public function optimize_upload_settings($file) {
        // 画像ファイルのみ処理
        $image_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        if (!in_array($file['type'], $image_types)) {
            return $file;
        }

        // ファイル名を最適化（日本語→ローマ字変換等は別途対応）
        // ここでは特に処理なし

        return $file;
    }

    /**
     * 既存の全アイキャッチ画像のaltを一括更新
     * 
     * @return array 結果
     */
    public function bulk_update_all_alts() {
        $args = array(
            'post_type' => 'hotel-review',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $query = new WP_Query($args);
        $updated = 0;
        $skipped = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $thumbnail_id = get_post_thumbnail_id($post_id);

                if ($thumbnail_id) {
                    $this->generate_and_set_alt($post_id, $thumbnail_id);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
            wp_reset_postdata();
        }

        return array(
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $query->found_posts,
        );
    }

    /**
     * 画像のSEO情報を取得（デバッグ用）
     * 
     * @param int $post_id 投稿ID
     * @return array
     */
    public function get_image_seo_info($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if (!$thumbnail_id) {
            return array('error' => 'No thumbnail');
        }

        $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        $title = get_the_title($thumbnail_id);
        $url = wp_get_attachment_url($thumbnail_id);
        $metadata = wp_get_attachment_metadata($thumbnail_id);

        return array(
            'thumbnail_id' => $thumbnail_id,
            'alt' => $alt,
            'title' => $title,
            'url' => $url,
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0,
            'file' => $metadata['file'] ?? '',
        );
    }
}

// 初期化
new HRS_Image_Optimizer();

/**
 * 一括更新用のCLIコマンド（WP-CLI対応）
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('hrs-update-alts', function() {
        $optimizer = new HRS_Image_Optimizer();
        $result = $optimizer->bulk_update_all_alts();
        WP_CLI::success("Updated: {$result['updated']}, Skipped: {$result['skipped']}, Total: {$result['total']}");
    });
}