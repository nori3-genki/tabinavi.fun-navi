<?php
/**
 * カテゴリ・タグ管理クラス（HQC対応版）
 * 
 * ホテルレビュー用カテゴリ・タグの自動設定
 * HQC設定（ペルソナ・目的）に基づく分類
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Category_Tag_Manager {

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * タクソノミー
     */
    private $taxonomy_category = 'hotel-category';
    private $taxonomy_tag = 'hotel-tag';

    /**
     * 定義済みカテゴリ（HQC連携）
     */
    private $predefined_categories = array();

    /**
     * 定義済みタグ（HQC連携）
     */
    private $predefined_tags = array();

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init_predefined_terms();
        
        add_action('init', array($this, 'register_taxonomies'), 5);
        add_action('save_post_' . $this->post_type, array($this, 'auto_assign_terms'), 25, 2);
        add_action('admin_init', array($this, 'maybe_create_default_terms'));
    }

    /**
     * 定義済みターム初期化
     */
    private function init_predefined_terms() {
        // ペルソナベースカテゴリ
        $this->predefined_categories = array(
            'general' => array(
                'name' => '一般・観光',
                'slug' => 'general-travel',
                'description' => '幅広い旅行者向けのホテル',
            ),
            'solo' => array(
                'name' => '一人旅',
                'slug' => 'solo-travel',
                'description' => '一人旅に最適なホテル',
            ),
            'couple' => array(
                'name' => 'カップル・夫婦',
                'slug' => 'couple-travel',
                'description' => '二人の特別な時間を過ごすホテル',
            ),
            'family' => array(
                'name' => 'ファミリー',
                'slug' => 'family-travel',
                'description' => '家族旅行に最適なホテル',
            ),
            'senior' => array(
                'name' => 'シニア',
                'slug' => 'senior-travel',
                'description' => 'シニア世代向けの快適なホテル',
            ),
            'workation' => array(
                'name' => 'ワーケーション',
                'slug' => 'workation',
                'description' => '仕事と休暇を両立できるホテル',
            ),
            'luxury' => array(
                'name' => 'ラグジュアリー',
                'slug' => 'luxury-hotel',
                'description' => '最高級のおもてなしを提供するホテル',
            ),
            'budget' => array(
                'name' => 'コスパ重視',
                'slug' => 'budget-friendly',
                'description' => 'リーズナブルで満足度の高いホテル',
            ),
        );

        // 目的・特徴ベースタグ
        $this->predefined_tags = array(
            // 旅の目的
            'sightseeing' => array('name' => '観光', 'slug' => 'sightseeing'),
            'onsen' => array('name' => '温泉', 'slug' => 'onsen'),
            'gourmet' => array('name' => 'グルメ', 'slug' => 'gourmet'),
            'anniversary' => array('name' => '記念日', 'slug' => 'anniversary'),
            'healing' => array('name' => '癒し・リラックス', 'slug' => 'healing'),
            'beach' => array('name' => 'ビーチ・海', 'slug' => 'beach'),
            'mountain' => array('name' => '山・高原', 'slug' => 'mountain'),
            
            // 設備・特徴
            'pool' => array('name' => 'プール', 'slug' => 'pool'),
            'spa' => array('name' => 'スパ', 'slug' => 'spa'),
            'wifi' => array('name' => 'WiFi完備', 'slug' => 'wifi'),
            'breakfast' => array('name' => '朝食付き', 'slug' => 'breakfast'),
            'pet-friendly' => array('name' => 'ペット可', 'slug' => 'pet-friendly'),
            'barrier-free' => array('name' => 'バリアフリー', 'slug' => 'barrier-free'),
            
            // エリア
            'city' => array('name' => '都市部', 'slug' => 'city'),
            'resort' => array('name' => 'リゾート', 'slug' => 'resort'),
            'rural' => array('name' => '田舎・郊外', 'slug' => 'rural'),
            
            // 評価
            'highly-rated' => array('name' => '高評価', 'slug' => 'highly-rated'),
            'new-opening' => array('name' => '新規オープン', 'slug' => 'new-opening'),
            'renovated' => array('name' => 'リニューアル', 'slug' => 'renovated'),
        );
    }

    /**
     * タクソノミー登録
     */
    public function register_taxonomies() {
        // カテゴリ（階層あり）
        register_taxonomy($this->taxonomy_category, $this->post_type, array(
            'labels' => array(
                'name' => 'ホテルカテゴリ',
                'singular_name' => 'ホテルカテゴリ',
                'search_items' => 'カテゴリを検索',
                'all_items' => 'すべてのカテゴリ',
                'parent_item' => '親カテゴリ',
                'parent_item_colon' => '親カテゴリ:',
                'edit_item' => 'カテゴリを編集',
                'update_item' => 'カテゴリを更新',
                'add_new_item' => '新規カテゴリを追加',
                'new_item_name' => '新規カテゴリ名',
                'menu_name' => 'カテゴリ',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'hotel-category'),
        ));

        // タグ（階層なし）
        register_taxonomy($this->taxonomy_tag, $this->post_type, array(
            'labels' => array(
                'name' => 'ホテルタグ',
                'singular_name' => 'ホテルタグ',
                'search_items' => 'タグを検索',
                'popular_items' => '人気のタグ',
                'all_items' => 'すべてのタグ',
                'edit_item' => 'タグを編集',
                'update_item' => 'タグを更新',
                'add_new_item' => '新規タグを追加',
                'new_item_name' => '新規タグ名',
                'menu_name' => 'タグ',
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'hotel-tag'),
        ));
    }

    /**
     * デフォルトターム作成
     */
    public function maybe_create_default_terms() {
        $created = get_option('hrs_default_terms_created', false);
        
        if ($created) {
            return;
        }

        // カテゴリ作成
        foreach ($this->predefined_categories as $key => $cat) {
            if (!term_exists($cat['slug'], $this->taxonomy_category)) {
                wp_insert_term($cat['name'], $this->taxonomy_category, array(
                    'slug' => $cat['slug'],
                    'description' => $cat['description'],
                ));
            }
        }

        // タグ作成
        foreach ($this->predefined_tags as $key => $tag) {
            if (!term_exists($tag['slug'], $this->taxonomy_tag)) {
                wp_insert_term($tag['name'], $this->taxonomy_tag, array(
                    'slug' => $tag['slug'],
                ));
            }
        }

        update_option('hrs_default_terms_created', true);
    }

    /**
     * ターム自動割り当て
     * 
     * @param int $post_id
     * @param WP_Post $post
     */
    public function auto_assign_terms($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_type !== $this->post_type) {
            return;
        }

        // HQC設定取得
        $hqc_settings = get_option('hrs_hqc_settings', array());
        $persona = $hqc_settings['h']['persona'] ?? 'general';
        $purposes = $hqc_settings['h']['purpose'] ?? array('sightseeing');

        // 記事メタから追加情報取得
        $hotel_data = get_post_meta($post_id, '_hrs_hotel_data', true);
        
        // カテゴリ割り当て（ペルソナベース）
        $this->assign_category_by_persona($post_id, $persona);
        
        // タグ割り当て（目的ベース + 記事内容分析）
        $this->assign_tags($post_id, $purposes, $post->post_content, $hotel_data);
    }

    /**
     * ペルソナに基づくカテゴリ割り当て
     * 
     * @param int $post_id
     * @param string $persona
     */
    private function assign_category_by_persona($post_id, $persona) {
        // 既にカテゴリが設定されていればスキップ
        $existing = wp_get_object_terms($post_id, $this->taxonomy_category);
        if (!empty($existing)) {
            return;
        }

        $category = $this->predefined_categories[$persona] ?? $this->predefined_categories['general'];
        $term = get_term_by('slug', $category['slug'], $this->taxonomy_category);
        
        if ($term) {
            wp_set_object_terms($post_id, array($term->term_id), $this->taxonomy_category);
        }
    }

    /**
     * タグ割り当て
     * 
     * @param int $post_id
     * @param array $purposes
     * @param string $content
     * @param array $hotel_data
     */
    private function assign_tags($post_id, $purposes, $content, $hotel_data = array()) {
        $tags_to_assign = array();

        // 1. 旅の目的からタグ追加
        foreach ($purposes as $purpose) {
            if (isset($this->predefined_tags[$purpose])) {
                $tags_to_assign[] = $this->predefined_tags[$purpose]['slug'];
            }
        }

        // 2. 記事内容から自動検出
        $detected_tags = $this->detect_tags_from_content($content);
        $tags_to_assign = array_merge($tags_to_assign, $detected_tags);

        // 3. ホテルデータから検出
        if (!empty($hotel_data)) {
            $data_tags = $this->detect_tags_from_hotel_data($hotel_data);
            $tags_to_assign = array_merge($tags_to_assign, $data_tags);
        }

        // 重複除去
        $tags_to_assign = array_unique($tags_to_assign);

        // タグ設定
        if (!empty($tags_to_assign)) {
            $term_ids = array();
            foreach ($tags_to_assign as $slug) {
                $term = get_term_by('slug', $slug, $this->taxonomy_tag);
                if ($term) {
                    $term_ids[] = $term->term_id;
                }
            }
            
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, $this->taxonomy_tag, true);
            }
        }
    }

    /**
     * コンテンツからタグを検出
     * 
     * @param string $content
     * @return array
     */
    private function detect_tags_from_content($content) {
        $detected = array();
        
        $keywords = array(
            'onsen' => array('温泉', '露天風呂', '内湯', '源泉'),
            'gourmet' => array('料理', '食事', 'ビュッフェ', 'コース', '懐石', 'グルメ'),
            'pool' => array('プール', 'スイミング'),
            'spa' => array('スパ', 'エステ', 'マッサージ', 'トリートメント'),
            'beach' => array('ビーチ', '海', 'オーシャン', '砂浜'),
            'mountain' => array('山', '高原', '森林', '自然'),
            'wifi' => array('WiFi', 'Wi-Fi', 'ワイファイ', 'インターネット'),
            'breakfast' => array('朝食', 'モーニング', '朝ごはん'),
            'pet-friendly' => array('ペット', '犬', '猫', 'わんちゃん'),
            'barrier-free' => array('バリアフリー', '車椅子', 'ユニバーサル'),
            'anniversary' => array('記念日', '誕生日', 'アニバーサリー', 'お祝い'),
            'healing' => array('癒し', 'リラックス', '静か', '穏やか'),
            'highly-rated' => array('高評価', '人気', '満足度', '口コミ高い'),
        );

        foreach ($keywords as $tag_slug => $words) {
            foreach ($words as $word) {
                if (mb_strpos($content, $word) !== false) {
                    $detected[] = $tag_slug;
                    break;
                }
            }
        }

        return $detected;
    }

    /**
     * ホテルデータからタグを検出
     * 
     * @param array $hotel_data
     * @return array
     */
    private function detect_tags_from_hotel_data($hotel_data) {
        $detected = array();
        
        // 評価スコアが高い場合
        if (isset($hotel_data['review_average']) && $hotel_data['review_average'] >= 4.5) {
            $detected[] = 'highly-rated';
        }

        // 特徴テキストから検出
        $features = $hotel_data['features'] ?? array();
        $feature_text = implode(' ', $features);
        
        $feature_keywords = array(
            'resort' => array('リゾート', 'resort'),
            'city' => array('駅近', '繁華街', 'ビジネス'),
            'new-opening' => array('新規', 'オープン', '開業'),
            'renovated' => array('リニューアル', '改装'),
        );

        foreach ($feature_keywords as $tag_slug => $words) {
            foreach ($words as $word) {
                if (mb_stripos($feature_text, $word) !== false) {
                    $detected[] = $tag_slug;
                    break;
                }
            }
        }

        return $detected;
    }

    /**
     * 記事のカテゴリを手動設定
     * 
     * @param int $post_id
     * @param string|array $categories カテゴリスラッグまたは配列
     * @return bool
     */
    public function set_categories($post_id, $categories) {
        if (!is_array($categories)) {
            $categories = array($categories);
        }

        $term_ids = array();
        foreach ($categories as $slug) {
            $term = get_term_by('slug', $slug, $this->taxonomy_category);
            if ($term) {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $this->taxonomy_category);
            return true;
        }

        return false;
    }

    /**
     * 記事のタグを手動設定
     * 
     * @param int $post_id
     * @param array $tags タグスラッグ配列
     * @param bool $append 追加モード
     * @return bool
     */
    public function set_tags($post_id, $tags, $append = false) {
        $term_ids = array();
        foreach ($tags as $slug) {
            $term = get_term_by('slug', $slug, $this->taxonomy_tag);
            if ($term) {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $this->taxonomy_tag, $append);
            return true;
        }

        return false;
    }

    /**
     * 利用可能なカテゴリ一覧を取得
     * 
     * @return array
     */
    public function get_available_categories() {
        return $this->predefined_categories;
    }

    /**
     * 利用可能なタグ一覧を取得
     * 
     * @return array
     */
    public function get_available_tags() {
        return $this->predefined_tags;
    }

    /**
     * カテゴリ統計を取得
     * 
     * @return array
     */
    public function get_category_stats() {
        $terms = get_terms(array(
            'taxonomy' => $this->taxonomy_category,
            'hide_empty' => false,
        ));

        $stats = array();
        foreach ($terms as $term) {
            $stats[$term->slug] = array(
                'name' => $term->name,
                'count' => $term->count,
            );
        }

        return $stats;
    }

    /**
     * タグ統計を取得
     * 
     * @return array
     */
    public function get_tag_stats() {
        $terms = get_terms(array(
            'taxonomy' => $this->taxonomy_tag,
            'hide_empty' => false,
        ));

        $stats = array();
        foreach ($terms as $term) {
            $stats[$term->slug] = array(
                'name' => $term->name,
                'count' => $term->count,
            );
        }

        return $stats;
    }
}

// 初期化
new HRS_Category_Tag_Manager();