<?php
/**
 * Nurture Data - データ取得ロジック
 * @package Hotel_Review_System
 * @version 7.0.0-HQC-ONLY ✅ HQCスコアのみ使用（SEOスコア削除）
 */

if (!defined('ABSPATH')) exit;

class HRS_Nurture_Data {

    /**
     * 投稿タイプ（一元管理）
     */
    private static $post_type = 'hotel-review';

    /**
     * 1ページあたりの表示件数
     */
    const PER_PAGE = 20;

    /**
     * 記事一覧取得（ページネーション対応）
     */
    public static function get_articles($score_filter, $order_filter, $direction_filter, $paged = 1) {
        $args = [
            'post_type'      => self::$post_type,
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'post_status'    => 'any',
        ];
        
        // スコアフィルタリング用のメタクエリ（HQCスコア使用）
        if ($score_filter !== 'all') {
            $score_ranges = [
                'excellent'   => ['min' => 80, 'max' => 100],
                'good'        => ['min' => 60, 'max' => 79.99],
                'needs_work'  => ['min' => 40, 'max' => 59.99],
                'poor'        => ['min' => 0, 'max' => 39.99],
            ];
            if (isset($score_ranges[$score_filter])) {
                $range = $score_ranges[$score_filter];
                $args['meta_query'] = [
                    [
                        'key'     => '_hrs_hqc_score',
                        'value'   => [$range['min'], $range['max']],
                        'type'    => 'DECIMAL(5,2)',
                        'compare' => 'BETWEEN',
                    ],
                ];
            }
        }
        
        // ソートロジック（HQCスコア使用）
        if ($order_filter === 'score') {
            $args['meta_key'] = '_hrs_hqc_score';
            $args['orderby']  = 'meta_value_num';
        } elseif ($order_filter === 'title') {
            $args['orderby']  = 'title';
        } else {
            $args['orderby']  = 'date';
        }
        $args['order'] = strtoupper($direction_filter);
        
        $query = new WP_Query($args);
        $articles = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // HQCスコア取得
                $h_score = floatval(get_post_meta($post_id, '_hrs_hqc_h_score', true));
                $q_score = floatval(get_post_meta($post_id, '_hrs_hqc_q_score', true));
                $c_score = floatval(get_post_meta($post_id, '_hrs_hqc_c_score', true));
                $hqc_score = floatval(get_post_meta($post_id, '_hrs_hqc_score', true));
                
                // スコアが0の場合、旧キーも確認
                if ($h_score == 0) {
                    $h_score = floatval(get_post_meta($post_id, '_hrs_h_score', true));
                }
                if ($q_score == 0) {
                    $q_score = floatval(get_post_meta($post_id, '_hrs_q_score', true));
                }
                if ($c_score == 0) {
                    $c_score = floatval(get_post_meta($post_id, '_hrs_c_score', true));
                }
                
                // HQCスコアで判定
                $score = round($hqc_score, 1);
                
                if ($score >= 80) { $score_class = 'excellent'; $score_label = '優良'; }
                elseif ($score >= 60) { $score_class = 'good'; $score_label = '良好'; }
                elseif ($score >= 40) { $score_class = 'needs-work'; $score_label = '要改善'; }
                else { $score_class = 'poor'; $score_label = '低品質'; }
                
                $status = get_post_status();
                $status_labels = [
                    'publish' => '公開',
                    'draft' => '下書き',
                    'pending' => '承認待ち',
                    'future' => '予約',
                    'private' => '非公開',
                    'trash' => 'ゴミ箱'
                ];
                
                // 配列の構築
                $articles[] = [
                    'id'           => $post_id,
                    'title'        => get_the_title(),
                    'date'         => get_the_date('Y/m/d H:i'),
                    'status'       => $status,
                    'status_label' => isset($status_labels[$status]) ? $status_labels[$status] : $status,
                    'score'        => $score,
                    'score_class'  => $score_class,
                    'score_label'  => $score_label,
                    'issues'       => self::get_hqc_issues($post_id, $h_score, $q_score, $c_score),
                    'h_score'      => round($h_score, 1),
                    'q_score'      => round($q_score, 1),
                    'c_score'      => round($c_score, 1),
                    'hqc_score'    => $score,
                ];
            }
            wp_reset_postdata();
        }
        
        return [
            'articles'    => $articles,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current'     => $paged,
            'per_page'    => self::PER_PAGE,
        ];
    }

    /**
     * 統計データの取得（HQCスコアベース）
     */
    public static function get_statistics() {
        $query = new WP_Query([
            'post_type'      => self::$post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        
        $total = $query->found_posts;
        $excellent = $good = $needs_work = $poor = 0;
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $score = floatval(get_post_meta($post_id, '_hrs_hqc_score', true));
                if ($score >= 80) $excellent++;
                elseif ($score >= 60) $good++;
                elseif ($score >= 40) $needs_work++;
                else $poor++;
            }
        }
        
        return [
            'total'              => $total,
            'excellent'          => $excellent, 
            'excellent_percent'  => $total > 0 ? round(($excellent / $total) * 100) : 0,
            'good'               => $good, 
            'good_percent'       => $total > 0 ? round(($good / $total) * 100) : 0,
            'needs_work'         => $needs_work, 
            'needs_work_percent' => $total > 0 ? round(($needs_work / $total) * 100) : 0,
            'poor'               => $poor, 
            'poor_percent'       => $total > 0 ? round(($poor / $total) * 100) : 0,
        ];
    }

    /**
     * HQCの改善項目チェック
     */
    public static function get_hqc_issues($post_id, $h_score, $q_score, $c_score) {
        $issues = [];
        
        // H層（人間性）の問題
        if ($h_score < 50) {
            $issues[] = 'H層不足: 感情表現・体験談を追加';
        }
        
        // Q層（品質）の問題
        if ($q_score < 50) {
            $issues[] = 'Q層不足: 五感描写・具体的情報を追加';
        }
        
        // C層（構造）の問題
        if ($c_score < 50) {
            $issues[] = 'C層不足: 見出し構造・内部リンクを改善';
        }
        
        // 画像チェック
        if (!has_post_thumbnail($post_id)) {
            $issues[] = 'アイキャッチ画像が未設定';
        }
        
        // 内部リンクチェック
        $content = get_post_field('post_content', $post_id);
        if (substr_count($content, get_site_url()) < 3) {
            $issues[] = '内部リンク不足: 関連記事へのリンクを追加';
        }
        
        return $issues;
    }

    /**
     * 記事をゴミ箱へ移動
     */
    public static function trash_article($post_id) {
        if (!current_user_can('delete_post', $post_id)) {
            return new WP_Error('permission_denied', '権限がありません');
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::$post_type) {
            return new WP_Error('invalid_post', '記事が見つかりません');
        }
        
        $result = wp_trash_post($post_id);
        return $result ? true : new WP_Error('trash_failed', 'ゴミ箱への移動に失敗しました');
    }

    /**
     * 複数記事をゴミ箱へ移動
     */
    public static function trash_articles($post_ids) {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($post_ids as $post_id) {
            $result = self::trash_article(intval($post_id));
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = $post_id . ': ' . $result->get_error_message();
            } else {
                $results['success']++;
            }
        }
        
        return $results;
    }
}