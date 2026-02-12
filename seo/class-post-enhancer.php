<?php
/**
 * 投稿自動強化クラス
 * 
 * hotel-review投稿タイプの保存時に自動で以下を追加:
 * 1. OTAリンク（CSE収集URLのみ、フォールバックなし）
 * 2. アイキャッチ画像（楽天APIから取得）
 * 3. ホテル名メタデータ
 * 
 * 手動生成した記事にも対応
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Post_Enhancer {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * カスタム投稿タイプ
     */
    private $post_type = 'hotel-review';

    /**
     * 処理中フラグ（再帰防止）
     */
    private static $is_processing = false;

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 投稿保存時のフック（優先度を高めに設定）
        add_action('save_post_' . $this->post_type, array($this, 'enhance_post'), 15, 2);
        
        // 新規投稿時のフック
        add_action('wp_insert_post', array($this, 'on_insert_post'), 10, 3);
    }

    /**
     * 投稿保存時の自動強化処理
     */
    public function enhance_post($post_id, $post) {
        // 再帰防止
        if (self::$is_processing) {
            return;
        }

        // 基本チェック
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_type !== $this->post_type) return;
        if ($post->post_status === 'trash') return;
        if (!current_user_can('edit_post', $post_id)) return;

        self::$is_processing = true;

        try {
            // ホテル名を取得または推測
            $hotel_name = $this->get_or_detect_hotel_name($post_id, $post);
            
            if (empty($hotel_name)) {
                $this->log('ホテル名を特定できませんでした: post_id=' . $post_id);
                self::$is_processing = false;
                return;
            }

            $this->log('処理開始: ' . $hotel_name . ' (post_id=' . $post_id . ')');

            // 1. ホテル名メタデータを保存
            $this->save_hotel_name_meta($post_id, $hotel_name);

            // 2. OTA URLを収集（まだない場合）
            $ota_urls = $this->ensure_ota_urls($post_id, $hotel_name);

            // 3. OTAリンクを追加（まだない場合）
            $this->ensure_ota_links($post_id, $hotel_name, $ota_urls);

            // 4. アイキャッチ画像を設定（まだない場合）
            $this->ensure_featured_image($post_id, $hotel_name);

            $this->log('処理完了: ' . $hotel_name);

        } catch (Exception $e) {
            $this->log('エラー: ' . $e->getMessage());
        }

        self::$is_processing = false;
    }

    /**
     * 新規投稿時の処理
     */
    public function on_insert_post($post_id, $post, $update) {
        if ($update) return; // 更新時はスキップ（enhance_postで処理）
        if ($post->post_type !== $this->post_type) return;
        
        // 新規投稿時は enhance_post に任せる
    }

    /**
     * ホテル名を取得または推測
     */
    private function get_or_detect_hotel_name($post_id, $post) {
        // 1. メタデータから取得
        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (!empty($hotel_name)) {
            return $hotel_name;
        }

        // 2. タイトルから推測
        $title = $post->post_title;
        $hotel_name = $this->extract_hotel_name_from_title($title);
        
        if (!empty($hotel_name)) {
            return $hotel_name;
        }

        // 3. 本文から推測（最初のH2見出しの後の文章から）
        $hotel_name = $this->extract_hotel_name_from_content($post->post_content);
        
        return $hotel_name;
    }

    /**
     * タイトルからホテル名を抽出
     */
    private function extract_hotel_name_from_title($title) {
        if (empty($title)) return '';

        // パターン1: 「{ホテル名}の魅力 - {特徴}」
        if (mb_strpos($title, 'の魅力') !== false) {
            $hotel_name = mb_substr($title, 0, mb_strpos($title, 'の魅力'));
            return trim($hotel_name);
        }

        // パターン2: 「{ホテル名}を徹底レビュー」
        if (mb_strpos($title, 'を徹底レビュー') !== false) {
            $hotel_name = mb_substr($title, 0, mb_strpos($title, 'を徹底レビュー'));
            // 「の{特徴}」を除去
            if (preg_match('/^(.+?)の[^の]+$/u', $hotel_name, $matches)) {
                return trim($matches[1]);
            }
            return trim($hotel_name);
        }

        // パターン3: 「{ホテル名}宿泊レビュー」
        if (mb_strpos($title, '宿泊レビュー') !== false) {
            $hotel_name = mb_substr($title, 0, mb_strpos($title, '宿泊レビュー'));
            return trim($hotel_name);
        }

        // パターン4: 「{ホテル名} - {説明}」
        if (mb_strpos($title, ' - ') !== false) {
            $parts = explode(' - ', $title);
            return trim($parts[0]);
        }

        // パターン5: 「{ホテル名}｜{説明}」
        if (mb_strpos($title, '｜') !== false) {
            $parts = explode('｜', $title);
            return trim($parts[0]);
        }

        // パターン6: 「{ホテル名}の{何か}」（一般的なパターン）
        if (preg_match('/^(.+?)(の[魅力特徴おすすめレビュー]+)/u', $title, $matches)) {
            return trim($matches[1]);
        }

        // フォールバック: タイトル全体（ただし長すぎる場合は無効）
        if (mb_strlen($title) <= 30) {
            return trim($title);
        }

        return '';
    }

    /**
     * 本文からホテル名を抽出
     */
    private function extract_hotel_name_from_content($content) {
        if (empty($content)) return '';

        // HTMLタグを除去
        $text = wp_strip_all_tags($content);

        // 「○○へようこそ」パターン
        if (preg_match('/「?([^」\n]+?)」?へようこそ/u', $text, $matches)) {
            return trim($matches[1]);
        }

        // 「○○に宿泊」パターン
        if (preg_match('/「?([^」\n]+?)」?に宿泊/u', $text, $matches)) {
            return trim($matches[1]);
        }

        // 「○○を訪れ」パターン
        if (preg_match('/「?([^」\n]+?)」?を訪れ/u', $text, $matches)) {
            return trim($matches[1]);
        }

        // 「○○は」で始まる文（最初の100文字以内）
        $first_100 = mb_substr($text, 0, 100);
        if (preg_match('/^「?([^」、。\n]{5,30}?)」?は/u', $first_100, $matches)) {
            $candidate = trim($matches[1]);
            // ホテル・旅館らしい名前かチェック
            if ($this->looks_like_hotel_name($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * ホテル名らしいかチェック
     */
    private function looks_like_hotel_name($name) {
        $hotel_keywords = array(
            'ホテル', 'Hotel', 'HOTEL',
            '旅館', '宿', 'やど',
            'リゾート', 'Resort', 'RESORT',
            'イン', 'Inn', 'INN',
            '温泉', '荘', '亭', '館', '閣',
            'ヴィラ', 'Villa', 'VILLA',
            'ロッジ', 'Lodge',
            'ペンション', 'Pension',
            '民宿',
        );

        foreach ($hotel_keywords as $keyword) {
            if (mb_stripos($name, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * ホテル名メタデータを保存
     */
    private function save_hotel_name_meta($post_id, $hotel_name) {
        $existing = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($existing)) {
            update_post_meta($post_id, '_hrs_hotel_name', $hotel_name);
            $this->log('ホテル名メタ保存: ' . $hotel_name);
        }
    }

    /**
     * OTA URLを確保（CSEから収集）
     */
    private function ensure_ota_urls($post_id, $hotel_name) {
        $ota_urls = get_post_meta($post_id, '_hrs_ota_urls', true);
        
        if (!empty($ota_urls) && is_array($ota_urls)) {
            return $ota_urls;
        }

        // DataCollectorでOTA URLを収集
        $ota_urls = $this->collect_ota_urls($hotel_name);
        
        if (!empty($ota_urls)) {
            update_post_meta($post_id, '_hrs_ota_urls', $ota_urls);
            $this->log('OTA URL収集・保存: ' . count($ota_urls) . '件');
        }

        return $ota_urls;
    }

    /**
     * OTA URLを収集
     */
    private function collect_ota_urls($hotel_name) {
    // ★ Post Enhancerではデータ収集をスキップ
    // OTA URLは記事生成時に既に収集・保存されているはず
    // ここで再収集すると処理が重複して遅くなる
    $this->log('OTA URL収集をスキップ（記事生成時に収集済みのはず）');
    return array();
}

    /**
     * OTAリンクを確保（なければ追加）
     */
    private function ensure_ota_links($post_id, $hotel_name, $ota_urls) {
        $post = get_post($post_id);
        if (!$post) return;

        // 既にリンクがある場合はスキップ
        if (strpos($post->post_content, 'hrs-booking-links') !== false) {
            return;
        }

        // OTA URLがない場合はスキップ
        if (empty($ota_urls) || !is_array($ota_urls)) {
            $this->log('OTA URLがないためリンク追加をスキップ');
            return;
        }

        // リンクジェネレーターを使用
        if (class_exists('HRS_Internal_Link_Generator')) {
            $generator = HRS_Internal_Link_Generator::get_instance();
            $link_section = $generator->generate_link_section($hotel_name, $ota_urls);
            
            if (!empty($link_section)) {
                $new_content = $post->post_content . "\n\n" . $link_section;
                
                // 再帰防止のためフックを一時解除
                remove_action('save_post_' . $this->post_type, array($this, 'enhance_post'), 15);
                
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $new_content,
                ));
                
                add_action('save_post_' . $this->post_type, array($this, 'enhance_post'), 15, 2);
                
                $this->log('OTAリンク追加完了');
            }
        }
    }

    /**
     * アイキャッチ画像を確保（なければ設定）
     */
    private function ensure_featured_image($post_id, $hotel_name) {
        // 既にアイキャッチがある場合はスキップ
        if (has_post_thumbnail($post_id)) {
            return;
        }

        // 楽天画像フェッチャーを使用
        if (class_exists('HRS_Rakuten_Image_Fetcher')) {
            $fetcher = new HRS_Rakuten_Image_Fetcher();
            $result = $fetcher->set_featured_image($post_id, $hotel_name);
            
            if ($result) {
                $this->log('アイキャッチ画像設定完了');
            } else {
                $this->log('アイキャッチ画像設定失敗');
            }
        } else {
            $this->log('HRS_Rakuten_Image_Fetcher クラスが見つかりません');
        }
    }

    /**
     * ログ出力
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Post Enhancer] ' . $message);
        }
    }
}

// クラスを初期化
add_action('init', function() {
    HRS_Post_Enhancer::get_instance();
});