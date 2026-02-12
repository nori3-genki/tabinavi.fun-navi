<?php
/**
 * 見出し最適化クラス
 * 
 * H2見出しを6個以上、キーフレーズを含める
 * カスタム投稿タイプ限定
 * 
 * @package HRS
 * @version 4.3.1 - ✅ インスタンス化修正
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Heading_Optimizer {

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * 最小H2見出し数
     */
    private $min_h2_count = 6;

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('save_post_' . $this->post_type, array($this, 'optimize_headings'), 15, 2);
    }

    /**
     * 見出しを最適化
     * 
     * @param int $post_id
     * @param WP_Post $post
     */
    public function optimize_headings($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_type !== $this->post_type) {
            return;
        }

        // キーフレーズ取得
        $keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (empty($keyphrase)) {
            $keyphrase = get_post_meta($post_id, '_hrs_hotel_name', true);
        }

        $content = $post->post_content;
        
        // 現在のH2数をカウント
        $h2_count = preg_match_all('/<h2[^>]*>/i', $content, $matches);

        // H2にキーフレーズを追加
        if (!empty($keyphrase)) {
            $content = $this->add_keyphrase_to_headings($content, $keyphrase);
        }

        // 変更があれば更新
        if ($content !== $post->post_content) {
            remove_action('save_post_' . $this->post_type, array($this, 'optimize_headings'), 15);
            
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content,
            ));

            add_action('save_post_' . $this->post_type, array($this, 'optimize_headings'), 15, 2);
        }
    }

    /**
     * 見出しにキーフレーズを追加
     * 
     * @param string $content
     * @param string $keyphrase
     * @return string
     */
    private function add_keyphrase_to_headings($content, $keyphrase) {
        // H2見出しを取得
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return $content;
        }

        $total_h2 = count($matches);

        // ✅ 改善：最初と最後のH2のみにキーフレーズを追加
        foreach ($matches as $index => $match) {
            $full_tag = $match[0];
            $heading_text = $match[1];

            // 既にキーフレーズが含まれていればスキップ
            if (mb_strpos($heading_text, $keyphrase) !== false) {
                continue;
            }

            // 最初のH2のみ処理（タイトルH2）
            if ($index === 0) {
                // タイトルは既にホテル名を含んでいるはずなのでスキップ
                continue;
            }

            // 最後のH2にのみキーフレーズを追加
            if ($index === $total_h2 - 1) {
                $new_heading = $this->create_optimized_heading($heading_text, $keyphrase);
                $new_tag = str_replace($heading_text, $new_heading, $full_tag);
                $content = str_replace($full_tag, $new_tag, $content);
                break;
            }
        }

        return $content;
    }

    /**
     * 最適化された見出しを作成
     * 
     * @param string $heading
     * @param string $keyphrase
     * @return string
     */
    private function create_optimized_heading($heading, $keyphrase) {
        // 見出しが短い場合は追記
        if (mb_strlen(wp_strip_all_tags($heading)) < 20) {
            return $keyphrase . 'の' . $heading;
        }

        // 既に長い場合はそのまま（不自然になるため）
        return $heading;
    }

    /**
     * 推奨見出し構成を生成
     * 
     * @param string $hotel_name
     * @param array $features
     * @return array
     */
    public function generate_recommended_headings($hotel_name, $features = array()) {
        $headings = array(
            $hotel_name . 'の魅力とは',
            $hotel_name . 'のアクセス・立地',
            $hotel_name . 'の客室・お部屋',
            $hotel_name . 'の温泉・大浴場',
            $hotel_name . 'の料理・食事',
            $hotel_name . 'の口コミ・評判',
            $hotel_name . 'の予約方法・料金',
            $hotel_name . 'のまとめ',
        );

        // 特徴に基づいて見出しをカスタマイズ
        if (!empty($features)) {
            if (in_array('温泉', $features) || in_array('露天風呂', $features)) {
                // 温泉見出しを強調（既に含まれている）
            }
            if (in_array('グルメ', $features) || in_array('朝食', $features)) {
                // 料理見出しを強調（既に含まれている）
            }
        }

        return $headings;
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

        // H2カウント
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2_matches);
        $h2_count = count($h2_matches[0]);

        // H3カウント
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $content, $h3_matches);
        $h3_count = count($h3_matches[0]);

        // キーフレーズを含むH2
        $h2_with_keyphrase = 0;
        if (!empty($keyphrase) && !empty($h2_matches[1])) {
            foreach ($h2_matches[1] as $h2_text) {
                if (mb_strpos($h2_text, $keyphrase) !== false) {
                    $h2_with_keyphrase++;
                }
            }
        }

        return array(
            'h2_count' => $h2_count,
            'h3_count' => $h3_count,
            'min_h2_required' => $this->min_h2_count,
            'h2_with_keyphrase' => $h2_with_keyphrase,
            'keyphrase' => $keyphrase,
            'h2_status' => $h2_count >= $this->min_h2_count ? 'good' : 'needs_improvement',
            'keyphrase_status' => $h2_with_keyphrase >= 2 ? 'good' : 'needs_improvement',
        );
    }
}

// ✅ 初期化（コメントアウト解除）
new HRS_Heading_Optimizer();