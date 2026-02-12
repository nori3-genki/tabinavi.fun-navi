<?php
/**
 * HRS_Article_Post_Handler - 投稿操作ハンドラー
 * @package HRS\Admin\Generator
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('HRS_Article_Post_Handler')) :

class HRS_Article_Post_Handler {

    // ========================================
    // ensure_price_section
    // ========================================
    public function ensure_price_section($post_id) {
        $post = get_post($post_id);
        if (!$post) return;

        $content = $post->post_content;
        $shortcode = '[hrs_price_section]';

        if (strpos($content, $shortcode) !== false) return;

        $inserted = false;
        $patterns = array(
            '/<h2[^>]*>.*?まとめ.*?<\/h2>/iu',
            '/<h2[^>]*>.*?おわりに.*?<\/h2>/iu'
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
                $content = substr($content, 0, $m[0][1]) . "\n" . $shortcode . "\n" . substr($content, $m[0][1]);
                $inserted = true;
                break;
            }
        }

        if (!$inserted && ($pos = strrpos($content, '<h2')) !== false) {
            $content = substr($content, 0, $pos) . "\n" . $shortcode . "\n" . substr($content, $pos);
            $inserted = true;
        }

        if (!$inserted) {
            $content .= "\n" . $shortcode;
        }

        wp_update_post(array('ID' => $post_id, 'post_content' => $content));
        $this->log("[PRICE_SECTION] Inserted in post_id: {$post_id}");
    }

    // ========================================
    // insert_post_direct
    // ========================================
    public function insert_post_direct($hotel_name, $article, $options = []) {
        $slug = $this->generate_slug_from_data($hotel_name, $options);

        $post_data = [
            'post_title'   => $hotel_name,
            'post_content' => $article,
            'post_status'  => 'draft',
            'post_type'    => 'hotel-review',
            'post_name'    => $slug,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (!is_wp_error($post_id) && $post_id > 0) {
            $hqc_score = $options['hqc_score'] ?? 0.8;
            update_post_meta($post_id, '_hrs_hqc_score', $hqc_score);
            update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
            update_post_meta($post_id, '_hrs_location', $options['location'] ?? '');
        }

        return is_wp_error($post_id) ? false : $post_id;
    }

    // ========================================
    // record_section_regeneration_success
    // ========================================
    public function record_section_regeneration_success(array $data) {
        if (empty($data['hotel_name']) || empty($data['section_type'])) {
            return false;
        }

        if (!isset($data['before_score'], $data['after_score']) || $data['after_score'] <= $data['before_score']) {
            return false;
        }

        $handled = apply_filters('hrs_handle_section_learning', false, $data);
        if ($handled !== false) return true;

        if (class_exists('HRS_HQC_Learning_Module')) {
            $learning = HRS_HQC_Learning_Module::get_instance();
            $learning_data = [
                'hotel_name'   => $data['hotel_name'],
                'section_type' => $data['section_type'],
                'before_score' => round($data['before_score'], 3),
                'after_score'  => round($data['after_score'], 3),
                'improvement'  => round($data['after_score'] - $data['before_score'], 3),
                'confidence'   => floatval($data['confidence'] ?? 0),
                'content'      => $data['content'] ?? '',
                'learned_at'   => current_time('mysql'),
            ];

            if (method_exists($learning, 'record_section_learning')) {
                $learning->record_section_learning($learning_data);
            } else {
                do_action('hrs_section_learning_record', $learning_data);
            }
        }

        return true;
    }

    // ========================================
    // Slug生成メソッド
    // ========================================
    private function generate_slug_from_data($hotel_name, $options = []) {
        $urls = $options['urls'] ?? [];
        $official_url = $urls['official'] ?? '';

        // 1. 公式URLからスラッグ抽出を試行
        if (!empty($official_url)) {
            $slug = $this->extract_slug_from_url($official_url);
            if (!empty($slug)) {
                $this->log("[SLUG] Generated from official URL: {$slug}");
                return $this->ensure_unique_slug($slug);
            }
        }

        // 2. 楽天URLからスラッグ抽出を試行
        $rakuten_url = $urls['rakuten'] ?? '';
        if (!empty($rakuten_url) && preg_match('/\/([a-z0-9_-]+)\/?(?:\?|$)/i', parse_url($rakuten_url, PHP_URL_PATH), $m)) {
            $slug = sanitize_title($m[1]);
            if (!empty($slug) && strlen($slug) > 3) {
                $this->log("[SLUG] Generated from Rakuten URL: {$slug}");
                return $this->ensure_unique_slug($slug);
            }
        }

        // 3. ホテル名からローマ字変換（フォールバック）
        $slug = $this->hotel_name_to_slug($hotel_name);
        $this->log("[SLUG] Generated from hotel name: {$slug}");
        return $this->ensure_unique_slug($slug);
    }

    /**
     * ✅ 修正版: 公式URLからスラッグを正しく抽出
     * 例: https://www.hotel-newgrand.co.jp/ → hotel-newgrand
     */
    private function extract_slug_from_url($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) return '';

        // www. を除去
        $host = preg_replace('/^www\./', '', $host);

        // 日本のドメイン（co.jp, or.jp, ne.jp等）と一般TLDを除去
        $host = preg_replace('/\.(co\.jp|or\.jp|ne\.jp|ac\.jp|go\.jp|ed\.jp|gr\.jp|ad\.jp|lg\.jp|com|net|org|jp|info|biz|io|travel)$/i', '', $host);

        // スラッグとしてサニタイズ
        $slug = sanitize_title($host);

        // 有効なスラッグかチェック（2文字以上）
        if (!empty($slug) && strlen($slug) > 2) {
            return $slug;
        }

        return '';
    }

    private function hotel_name_to_slug($hotel_name) {
        $map = array(
            'あ'=>'a','い'=>'i','う'=>'u','え'=>'e','お'=>'o',
            'か'=>'ka','き'=>'ki','く'=>'ku','け'=>'ke','こ'=>'ko',
            'さ'=>'sa','し'=>'shi','す'=>'su','せ'=>'se','そ'=>'so',
            'た'=>'ta','ち'=>'chi','つ'=>'tsu','て'=>'te','と'=>'to',
            'な'=>'na','に'=>'ni','ぬ'=>'nu','ね'=>'ne','の'=>'no',
            'は'=>'ha','ひ'=>'hi','ふ'=>'fu','へ'=>'he','ほ'=>'ho',
            'ま'=>'ma','み'=>'mi','む'=>'mu','め'=>'me','も'=>'mo',
            'や'=>'ya','ゆ'=>'yu','よ'=>'yo',
            'ら'=>'ra','り'=>'ri','る'=>'ru','れ'=>'re','ろ'=>'ro',
            'わ'=>'wa','を'=>'wo','ん'=>'n',
            'が'=>'ga','ぎ'=>'gi','ぐ'=>'gu','げ'=>'ge','ご'=>'go',
            'ざ'=>'za','じ'=>'ji','ず'=>'zu','ぜ'=>'ze','ぞ'=>'zo',
            'だ'=>'da','ぢ'=>'di','づ'=>'du','で'=>'de','ど'=>'do',
            'ば'=>'ba','び'=>'bi','ぶ'=>'bu','べ'=>'be','ぼ'=>'bo',
            'ぱ'=>'pa','ぴ'=>'pi','ぷ'=>'pu','ぺ'=>'pe','ぽ'=>'po',
            'ゃ'=>'ya','ゅ'=>'yu','ょ'=>'yo','っ'=>'',
            'ー'=>'-','　'=>'-',' '=>'-',
        );

        $name = mb_convert_kana($hotel_name, 'c');
        $slug = strtr($name, $map);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        return !empty($slug) ? $slug : 'hotel-' . time();
    }

    private function ensure_unique_slug($slug) {
        global $wpdb;
        $original = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'hotel-review' LIMIT 1",
            $slug
        ))) {
            $counter++;
            $slug = $original . '-' . $counter;
        }

        return $slug;
    }

    // ========================================
    // ログ出力
    // ========================================
    private function log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS_Post_Handler] ' . $msg);
        }
    }
}

endif;