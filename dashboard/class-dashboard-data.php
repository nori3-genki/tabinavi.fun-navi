<?php
/**
 * Dashboard Data - データ取得
 * @package Hotel_Review_System
 * @version 6.8.2 - 重複項目修正
 */
if (!defined('ABSPATH')) exit;

class HRS_Dashboard_Data {
    
    public static function get_statistics() {
        $post_type = 'hotel-review';
        
        $all = new WP_Query([
            'post_type' => $post_type, 
            'posts_per_page' => -1, 
            'post_status' => ['publish', 'draft'], 
            'fields' => 'ids'
        ]);
        
        $published = new WP_Query([
            'post_type' => $post_type, 
            'posts_per_page' => -1, 
            'post_status' => 'publish', 
            'fields' => 'ids'
        ]);
        
        $draft = new WP_Query([
            'post_type' => $post_type, 
            'posts_per_page' => -1, 
            'post_status' => 'draft', 
            'fields' => 'ids'
        ]);
        
        $today = new WP_Query([
            'post_type' => $post_type, 
            'posts_per_page' => -1, 
            'post_status' => ['publish', 'draft'], 
            'fields' => 'ids', 
            'date_query' => [['after' => 'today']]
        ]);
        
        return [
            'total' => $all->found_posts,
            'published' => $published->found_posts,
            'draft' => $draft->found_posts,
            'today' => $today->found_posts,
        ];
    }
    
    public static function get_api_status() {
        return [
            'chatgpt' => [
                'name' => 'ChatGPT API', 
                'icon' => '🤖', 
                'configured' => !empty(get_option('hrs_chatgpt_api_key')), 
                'tab' => 'api'
            ],
            'google_cse' => [
                'name' => 'Google CSE', 
                'icon' => '🔍', 
                'configured' => !empty(get_option('hrs_google_cse_id')) && !empty(get_option('hrs_google_cse_api_key')), 
                'tab' => 'api'
            ],
            'rakuten' => [
                'name' => 'Rakuten API', 
                'icon' => '🏨', 
                'configured' => !empty(get_option('hrs_rakuten_app_id')), 
                'tab' => 'api'
            ],
        ];
    }
    
    public static function get_recent_articles($limit = 5) {
        $query = new WP_Query([
            'post_type' => 'hotel-review', 
            'posts_per_page' => $limit, 
            'post_status' => ['publish', 'draft'], 
            'orderby' => 'date', 
            'order' => 'DESC'
        ]);
        
        $articles = [];
        $labels = ['publish' => '公開', 'draft' => '下書き'];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $s = get_post_status();
                $articles[] = [
                    'id' => get_the_ID(), 
                    'title' => get_the_title(), 
                    'date' => get_the_date('Y/m/d H:i'), 
                    'status' => isset($labels[$s]) ? $labels[$s] : $s
                ];
            }
            wp_reset_postdata();
        }
        return $articles;
    }
    
    /**
     * HQC学習システムの統計を取得
     */
    public static function get_hqc_statistics() {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            $learning_file = HRS_PLUGIN_DIR . 'includes/learning/class-hqc-learning-module.php';
            if (file_exists($learning_file)) {
                require_once $learning_file;
            } else {
                return self::get_empty_hqc_stats();
            }
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        return $learning->get_statistics();
    }
    
    /**
     * スコア推移データを取得（グラフ用）
     */
    public static function get_score_trend($days = 30) {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            $learning_file = HRS_PLUGIN_DIR . 'includes/learning/class-hqc-learning-module.php';
            if (file_exists($learning_file)) {
                require_once $learning_file;
            } else {
                return array();
            }
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        return $learning->get_score_trend($days);
    }
    
    /**
     * 慢性的弱点を取得（★重複修正）
     */
    public static function get_chronic_weak_points($limit = 5) {
        if (!class_exists('HRS_HQC_Learning_Module')) {
            $learning_file = HRS_PLUGIN_DIR . 'includes/learning/class-hqc-learning-module.php';
            if (file_exists($learning_file)) {
                require_once $learning_file;
            } else {
                return array();
            }
        }
        
        $learning = HRS_HQC_Learning_Module::get_instance();
        $weak_points = $learning->get_chronic_weak_points(2);
        
        // ★重複除去処理を追加
        // 同じcategoryが複数回出現する場合、hotel_countが大きい方を優先
        $unique_points = array();
        $seen_categories = array();
        
        foreach ($weak_points as $key => $point) {
            $category = $point['category'];
            
            // 初出の場合は追加
            if (!isset($seen_categories[$category])) {
                $unique_points[$key] = $point;
                $seen_categories[$category] = $key;
            } else {
                // 既出の場合、hotel_countが大きい方を残す
                $existing_key = $seen_categories[$category];
                if ($point['hotel_count'] > $unique_points[$existing_key]['hotel_count']) {
                    unset($unique_points[$existing_key]);
                    $unique_points[$key] = $point;
                    $seen_categories[$category] = $key;
                }
            }
        }
        
        // リミット適用
        return array_slice($unique_points, 0, $limit, true);
    }
    
    /**
     * 空のHQC統計を返す
     */
    private static function get_empty_hqc_stats() {
        return array(
            'history' => array(
                'total_count' => 0,
                'avg_score' => 0,
                'max_score' => 0,
                'min_score' => 0,
                'avg_h' => 0,
                'avg_q' => 0,
                'avg_c' => 0,
                'high_quality_count' => 0,
            ),
            'hotels' => array('count' => 0),
            'patterns' => array('count' => 0),
        );
    }
}