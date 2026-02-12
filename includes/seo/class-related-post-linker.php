<?php
/**
 * 関連記事内部リンク生成クラス
 * 
 * <!-- INTERNAL_LINK_POINT --> コメントを検出して
 * 同じ都道府県・エリアの関連記事リンクを自動挿入
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Related_Post_Linker {

    private static $instance = null;

    private $post_type = 'hotel-review';
    private $taxonomy = 'hotel-category';
    
    /**
     * 1箇所あたりの関連記事表示数
     */
    private $links_per_point = 3;
    
    /**
     * 最大挿入箇所数
     */
    private $max_points = 3;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35, 2);
    }

    /**
     * 記事保存時に関連記事リンクを挿入
     */
    public function insert_related_links($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if ($post->post_type !== $this->post_type) {
            return;
        }

        $content = $post->post_content;

        // <!-- INTERNAL_LINK_POINT --> がなければスキップ
        if (strpos($content, '<!-- INTERNAL_LINK_POINT -->') === false) {
            return;
        }

        // 既に関連記事セクションが挿入済みならスキップ
        if (strpos($content, 'hrs-related-posts') !== false) {
            return;
        }

        // 関連記事を取得
        $related_posts = $this->get_related_posts($post_id);
        
        if (empty($related_posts)) {
            // 関連記事がない場合はコメントを削除
            $content = str_replace('<!-- INTERNAL_LINK_POINT -->', '', $content);
        } else {
            // 関連記事リンクを挿入
            $content = $this->replace_link_points($content, $related_posts, $post_id);
        }

        // 更新
        remove_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35);
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ));
        add_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35, 2);
    }

    /**
     * 関連記事を取得
     */
    private function get_related_posts($post_id) {
        $related = array();

        // 1. 同じ都道府県カテゴリの記事を取得
        $prefecture_posts = $this->get_posts_by_same_prefecture($post_id);
        $related = array_merge($related, $prefecture_posts);

        // 2. 同じペルソナカテゴリの記事を取得
        $persona_posts = $this->get_posts_by_same_persona($post_id);
        foreach ($persona_posts as $p) {
            if (!in_array($p->ID, array_column($related, 'ID'))) {
                $related[] = $p;
            }
        }

        // 3. 自分自身を除外
        $related = array_filter($related, function($p) use ($post_id) {
            return $p->ID !== $post_id;
        });

        // 最大数を制限
        $max_total = $this->links_per_point * $this->max_points;
        return array_slice(array_values($related), 0, $max_total);
    }

    /**
     * 同じ都道府県の記事を取得
     */
    private function get_posts_by_same_prefecture($post_id) {
        // 都道府県メタまたはカテゴリから取得
        $prefecture = get_post_meta($post_id, '_hrs_prefecture', true);
        
        if (empty($prefecture)) {
            // カテゴリから都道府県を推測
            $terms = wp_get_post_terms($post_id, $this->taxonomy, array('fields' => 'names'));
            if (!empty($terms)) {
                foreach ($terms as $term_name) {
                    if ($this->is_prefecture($term_name)) {
                        $prefecture = $term_name;
                        break;
                    }
                }
            }
        }

        if (empty($prefecture)) {
            return array();
        }

        // 同じ都道府県の記事を検索
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'post__not_in' => array($post_id),
            'orderby' => 'rand',
            'meta_query' => array(
                array(
                    'key' => '_hrs_prefecture',
                    'value' => $prefecture,
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts;
        }

        // メタがない場合はカテゴリで検索
        $term = get_term_by('name', $prefecture, $this->taxonomy);
        if ($term) {
            $args = array(
                'post_type' => $this->post_type,
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'post__not_in' => array($post_id),
                'orderby' => 'rand',
                'tax_query' => array(
                    array(
                        'taxonomy' => $this->taxonomy,
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ),
                ),
            );
            $query = new WP_Query($args);
            return $query->posts;
        }

        return array();
    }

    /**
     * 同じペルソナカテゴリの記事を取得
     */
    private function get_posts_by_same_persona($post_id) {
        $terms = wp_get_post_terms($post_id, $this->taxonomy, array('fields' => 'all'));
        
        if (empty($terms) || is_wp_error($terms)) {
            return array();
        }

        // ペルソナ系カテゴリを抽出
        $persona_terms = array();
        $persona_slugs = array('couple', 'family', 'solo', 'senior', 'workation', 'luxury', 'budget', 'general');
        
        foreach ($terms as $term) {
            if (in_array($term->slug, $persona_slugs)) {
                $persona_terms[] = $term->term_id;
            }
        }

        if (empty($persona_terms)) {
            return array();
        }

        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => array($post_id),
            'orderby' => 'rand',
            'tax_query' => array(
                array(
                    'taxonomy' => $this->taxonomy,
                    'field' => 'term_id',
                    'terms' => $persona_terms,
                ),
            ),
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * 都道府県名かどうか判定
     */
    private function is_prefecture($name) {
        $prefectures = array(
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
            // 県なし版
            '北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島',
            '茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川',
            '新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜',
            '静岡', '愛知', '三重', '滋賀', '京都', '大阪', '兵庫',
            '奈良', '和歌山', '鳥取', '島根', '岡山', '広島', '山口',
            '徳島', '香川', '愛媛', '高知', '福岡', '佐賀', '長崎',
            '熊本', '大分', '宮崎', '鹿児島', '沖縄',
            // エリア名
            '伊豆', '箱根', '熱海', '軽井沢', '那須', '日光', '草津',
            '有馬', '城崎', '白浜', '別府', '湯布院', '由布院', '黒川',
        );
        
        return in_array($name, $prefectures);
    }

    /**
     * リンクポイントを関連記事で置換
     */
    private function replace_link_points($content, $related_posts, $post_id) {
        $point_count = substr_count($content, '<!-- INTERNAL_LINK_POINT -->');
        $point_count = min($point_count, $this->max_points);
        
        $posts_per_point = ceil(count($related_posts) / $point_count);
        $posts_chunks = array_chunk($related_posts, $posts_per_point);
        
        $replaced = 0;
        
        foreach ($posts_chunks as $chunk) {
            if ($replaced >= $point_count) {
                break;
            }
            
            $link_html = $this->generate_related_links_html($chunk);
            
            // 最初のポイントのみ置換
            $pos = strpos($content, '<!-- INTERNAL_LINK_POINT -->');
            if ($pos !== false) {
                $content = substr_replace($content, $link_html, $pos, strlen('<!-- INTERNAL_LINK_POINT -->'));
                $replaced++;
            }
        }
        
        // 残りのポイントを削除
        $content = str_replace('<!-- INTERNAL_LINK_POINT -->', '', $content);
        
        return $content;
    }

    /**
     * 関連記事リンクHTMLを生成
     */
    private function generate_related_links_html($posts) {
        if (empty($posts)) {
            return '';
        }

        $html = '<div class="hrs-related-posts">';
        $html .= '<p class="hrs-related-posts-title">▼ 関連するおすすめ宿</p>';
        $html .= '<ul class="hrs-related-posts-list">';
        
        foreach ($posts as $post) {
            $title = get_the_title($post->ID);
            $url = get_permalink($post->ID);
            $hotel_name = get_post_meta($post->ID, '_hrs_hotel_name', true);
            
            // ホテル名があればそちらを使用
            $display_name = !empty($hotel_name) ? $hotel_name : $title;
            
            // 長すぎる場合は省略
            if (mb_strlen($display_name) > 30) {
                $display_name = mb_substr($display_name, 0, 28) . '…';
            }
            
            $html .= '<li><a href="' . esc_url($url) . '">' . esc_html($display_name) . '</a></li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 手動で関連記事リンクを挿入
     */
    public function insert_links_manually($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->post_type) {
            return false;
        }

        // 既に挿入済みなら削除して再挿入
        $content = $post->post_content;
        $content = preg_replace('/<div class="hrs-related-posts">.*?<\/div>/s', '', $content);
        
        // リンクポイントがなければ追加
        if (strpos($content, '<!-- INTERNAL_LINK_POINT -->') === false) {
            // まとめセクションの前に挿入
            $patterns = array(
                '/(<h2[^>]*>.*?(まとめ|おわりに|最後に).*?<\/h2>)/iu',
                '/(<h2[^>]*>[^<]*<\/h2>\s*$)/iu',
            );
            
            $inserted = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_MATCH)) {
                    $pos = $matches[0][1];
                    $content = substr($content, 0, $pos) . "\n<!-- INTERNAL_LINK_POINT -->\n" . substr($content, $pos);
                    $inserted = true;
                    break;
                }
            }
            
            // パターンにマッチしなければ末尾に追加
            if (!$inserted) {
                $content .= "\n<!-- INTERNAL_LINK_POINT -->\n";
            }
        }

        // 関連記事を取得して挿入
        $related_posts = $this->get_related_posts($post_id);
        
        if (!empty($related_posts)) {
            $content = $this->replace_link_points($content, $related_posts, $post_id);
        } else {
            $content = str_replace('<!-- INTERNAL_LINK_POINT -->', '', $content);
        }

        remove_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35);
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ));
        add_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35, 2);

        return !is_wp_error($result);
    }

    /**
     * 関連記事リンクを削除
     */
    public function remove_links($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $content = $post->post_content;
        $content = preg_replace('/<div class="hrs-related-posts">.*?<\/div>/s', '', $content);
        $content = trim($content);

        remove_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35);
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ));
        add_action('save_post_' . $this->post_type, array($this, 'insert_related_links'), 35, 2);

        return !is_wp_error($result);
    }
}

add_action('init', function() {
    HRS_Related_Post_Linker::get_instance();
});