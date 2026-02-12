<?php
/**
 * キーフレーズ注入クラス
 * 
 * Yoast SEO フォーカスキーフレーズを記事全体に自然に配置
 * カスタム投稿タイプ限定
 * 
 * @package HRS
 * @version 4.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Keyphrase_Injector {

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * 目標キーフレーズ密度（%）
     */
    private $target_density = 1.5;

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('save_post_' . $this->post_type, array($this, 'inject_keyphrase'), 25, 2);
    }

    /**
     * キーフレーズを記事に注入
     * 
     * @param int $post_id
     * @param WP_Post $post
     */
    public function inject_keyphrase($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_type !== $this->post_type) {
            return;
        }

        // Yoast SEOのフォーカスキーフレーズを取得
        $keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        
        // なければホテル名を使用
        if (empty($keyphrase)) {
            $keyphrase = get_post_meta($post_id, '_hrs_hotel_name', true);
        }

        if (empty($keyphrase)) {
            return;
        }

        $content = $post->post_content;
        
        // 現在の密度を計算
        $current_density = $this->calculate_density($content, $keyphrase);

        // 目標密度に達していれば何もしない
        if ($current_density >= $this->target_density) {
            return;
        }

        // キーフレーズを自然に追加
        $optimized_content = $this->optimize_content($content, $keyphrase, $current_density);

        if ($optimized_content !== $content) {
            remove_action('save_post_' . $this->post_type, array($this, 'inject_keyphrase'), 25);
            
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $optimized_content,
            ));

            add_action('save_post_' . $this->post_type, array($this, 'inject_keyphrase'), 25, 2);
        }
    }

    /**
     * キーフレーズ密度を計算
     * 
     * @param string $content
     * @param string $keyphrase
     * @return float
     */
    public function calculate_density($content, $keyphrase) {
        // HTMLタグを除去
        $text = wp_strip_all_tags($content);
        
        // 単語数をカウント（日本語対応）
        $word_count = mb_strlen(preg_replace('/\s+/', '', $text));
        
        if ($word_count === 0) {
            return 0;
        }

        // キーフレーズ出現回数
        $keyphrase_count = mb_substr_count($text, $keyphrase);
        $keyphrase_length = mb_strlen($keyphrase);

        // 密度計算（キーフレーズ文字数 × 出現回数 / 総文字数 × 100）
        $density = ($keyphrase_length * $keyphrase_count) / $word_count * 100;

        return round($density, 2);
    }

    /**
     * コンテンツを最適化
     * 
     * @param string $content
     * @param string $keyphrase
     * @param float $current_density
     * @return string
     */
    private function optimize_content($content, $keyphrase, $current_density) {
        // 必要な追加回数を計算
        $text = wp_strip_all_tags($content);
        $word_count = mb_strlen(preg_replace('/\s+/', '', $text));
        $keyphrase_length = mb_strlen($keyphrase);
        
        $current_count = mb_substr_count($text, $keyphrase);
        $target_count = ceil(($this->target_density * $word_count) / ($keyphrase_length * 100));
        $need_to_add = max(0, $target_count - $current_count);

        if ($need_to_add === 0) {
            return $content;
        }

        // 段落を分割
        $paragraphs = preg_split('/(<\/p>|<\/h[2-6]>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $paragraph_count = 0;
        foreach ($paragraphs as $p) {
            if (preg_match('/<p|<h[2-6]/i', $p)) {
                $paragraph_count++;
            }
        }

        if ($paragraph_count === 0) {
            return $content;
        }

        // 追加する間隔を計算
        $interval = max(1, floor($paragraph_count / ($need_to_add + 1)));
        
        $added = 0;
        $counter = 0;
        $new_content = '';

        foreach ($paragraphs as $i => $part) {
            $new_content .= $part;
            
            // 段落終了タグの後にキーフレーズを挿入
            if (preg_match('/<\/p>/i', $part)) {
                $counter++;
                
                if ($added < $need_to_add && $counter % $interval === 0) {
                    // 既にキーフレーズが含まれている段落には追加しない
                    $prev_part = isset($paragraphs[$i - 1]) ? $paragraphs[$i - 1] : '';
                    if (mb_strpos($prev_part, $keyphrase) === false) {
                        // 自然な文脈で追加（前の段落を修正）
                        // ここでは単純に追加せず、既存の実装に任せる
                    }
                    $added++;
                }
            }
        }

        return $content; // 現在は変更なしで返す（過度な自動挿入を避ける）
    }

    /**
     * 冒頭にキーフレーズがあるか確認
     * 
     * @param string $content
     * @param string $keyphrase
     * @return bool
     */
    public function has_keyphrase_in_intro($content, $keyphrase) {
        // 最初の段落を取得
        preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $matches);
        
        if (empty($matches[1])) {
            return false;
        }

        $first_paragraph = wp_strip_all_tags($matches[1]);
        return mb_strpos($first_paragraph, $keyphrase) !== false;
    }

    /**
     * 分析結果を取得
     * 
     * @param int $post_id
     * @return array
     */
    public function analyze($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (empty($keyphrase)) {
            $keyphrase = get_post_meta($post_id, '_hrs_hotel_name', true);
        }

        $content = $post->post_content;
        $density = $this->calculate_density($content, $keyphrase);
        $has_intro = $this->has_keyphrase_in_intro($content, $keyphrase);
        $count = mb_substr_count(wp_strip_all_tags($content), $keyphrase);

        return array(
            'keyphrase' => $keyphrase,
            'density' => $density,
            'target_density' => $this->target_density,
            'count' => $count,
            'has_intro' => $has_intro,
            'status' => $density >= $this->target_density ? 'good' : 'needs_improvement',
        );
    }
}

// 初期化
// new HRS_Keyphrase_Injector();