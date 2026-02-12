<?php
/**
 * Yoast SEO 最適化クラス
 * 
 * - フォーカスキーフレーズ自動設定
 * - メタディスクリプション（60〜80文字）
 * - SEOタイトル
 * - スラッグ（公式URL優先 → OTA URL → ローマ字 → 投稿IDフォールバック）
 * - 抜粋（excerpt）自動生成
 * 
 * カスタム投稿タイプ限定
 * 
 * @package HRS
 * @version 4.6.0-META-DESC-FIX
 * 
 * 変更履歴:
 * - 4.5.0: スラッグ抽出改善
 * - 4.6.0: メタディスクリプション60〜80文字に修正
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Yoast_SEO_Optimizer {

    private $post_type = 'hotel-review';
    private $meta_max_length = 80;
    private $meta_min_length = 60;
    private $excerpt_max_length = 160;

    public function __construct() {
        add_action('save_post_' . $this->post_type, array($this, 'optimize_yoast_seo'), 30, 2);
    }

    public function optimize_yoast_seo($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if ($post->post_type !== $this->post_type) {
            return;
        }

        $hotel_name = get_post_meta($post_id, '_hrs_hotel_name', true);
        if (empty($hotel_name)) {
            $hotel_name = $post->post_title;
        }

        $this->set_focus_keyphrase($post_id, $hotel_name);
        $this->set_meta_description($post_id, $hotel_name, $post->post_content);
        $this->set_seo_title($post_id, $hotel_name);
        $this->set_slug($post_id, $hotel_name);
        $this->set_excerpt($post_id, $hotel_name, $post->post_content);
    }

    private function set_focus_keyphrase($post_id, $hotel_name) {
        $existing = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!empty($existing)) {
            return;
        }
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $hotel_name);
    }

    private function set_meta_description($post_id, $hotel_name, $content) {
        $existing = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty($existing)) {
            return;
        }
        $description = $this->generate_meta_description($hotel_name, $content);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
    }

    /**
     * メタディスクリプション生成（60〜80文字）
     * 
     * @param string $hotel_name
     * @param string $content
     * @return string
     */
    public function generate_meta_description($hotel_name, $content) {
        $hotel_length = mb_strlen($hotel_name);
        
        if ($hotel_length >= 35) {
            // 長いホテル名（35文字以上）: 短いサフィックス
            $description = $hotel_name . 'の宿泊体験レビュー。予約前に要チェック。';
        } elseif ($hotel_length >= 25) {
            // やや長いホテル名（25-34文字）: 中程度のサフィックス
            $templates = array(
                '{hotel}の魅力と口コミを徹底解説。宿泊前に必見の情報満載。',
                '{hotel}の宿泊レビュー。客室・温泉・料理の実体験をご紹介。',
                '{hotel}を実際に体験。おすすめポイントと注意点をまとめました。',
            );
            $template = $templates[array_rand($templates)];
            $description = str_replace('{hotel}', $hotel_name, $template);
        } elseif ($hotel_length >= 15) {
            // 中程度のホテル名（15-24文字）: 標準テンプレート
            $templates = array(
                '{hotel}の魅力を徹底解説。客室、温泉、料理の口コミ情報と予約のコツをお届けします。',
                '{hotel}の宿泊レビュー。アクセス、設備、サービスの実体験を詳しくご紹介します。',
                '{hotel}を実際に体験した感想。おすすめポイントと予約方法を分かりやすくまとめました。',
            );
            $template = $templates[array_rand($templates)];
            $description = str_replace('{hotel}', $hotel_name, $template);
        } else {
            // 短いホテル名（14文字以下）: 長めのテンプレート
            $templates = array(
                '{hotel}の魅力を徹底解説。客室の快適さ、温泉の泉質、料理の美味しさなど口コミ情報と予約のコツをお届けします。',
                '{hotel}の宿泊レビュー。立地・アクセス、設備の充実度、スタッフのサービス品質を実体験をもとに詳しくご紹介。',
                '{hotel}を実際に宿泊して体験した感想をお届け。おすすめポイントや注意点、お得な予約方法を分かりやすく解説。',
            );
            $template = $templates[array_rand($templates)];
            $description = str_replace('{hotel}', $hotel_name, $template);
        }

        // 80文字を超えた場合は切り詰め
        if (mb_strlen($description) > $this->meta_max_length) {
            $description = mb_substr($description, 0, $this->meta_max_length - 1) . '…';
        }
        
        // 60文字未満の場合は補足を追加
        if (mb_strlen($description) < $this->meta_min_length) {
            $description .= '宿泊予約の参考にどうぞ。';
            // 再度80文字チェック
            if (mb_strlen($description) > $this->meta_max_length) {
                $description = mb_substr($description, 0, $this->meta_max_length - 1) . '…';
            }
        }

        return $description;
    }

    private function set_seo_title($post_id, $hotel_name) {
        $existing = get_post_meta($post_id, '_yoast_wpseo_title', true);
        if (!empty($existing)) {
            return;
        }
        $seo_title = $this->generate_seo_title($hotel_name);
        update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
    }

    public function generate_seo_title($hotel_name) {
        $suffixes = array(
            'の魅力と口コミ',
            '宿泊レビュー',
            'おすすめポイント',
            '徹底ガイド',
        );

        $hotel_length = mb_strlen($hotel_name);
        
        if ($hotel_length > 25) {
            $suffix = 'の魅力';
        } else {
            $suffix = $suffixes[array_rand($suffixes)];
        }

        return $hotel_name . $suffix;
    }

    /**
     * ★ スラッグを設定（公式URL優先 → OTA URL → ローマ字 → 投稿IDフォールバック）
     * 
     * @param int $post_id
     * @param string $hotel_name
     */
    private function set_slug($post_id, $hotel_name) {
        $post = get_post($post_id);
        
        // 既にカスタムスラッグが設定されていればスキップ
        // ただし、不完全なスラッグ（漢字が消えた結果）は再設定
        $current_slug = $post->post_name;
        if (!empty($current_slug)) {
            // 正常なスラッグかチェック（5文字以上のローマ字）
            if (preg_match('/^[a-z0-9\-]{5,}$/', $current_slug)) {
                return; // 正常なスラッグなのでスキップ
            }
        }

        $slug = '';

        // 1. 公式URLからスラッグを抽出
        $slug = $this->extract_slug_from_official_url($post_id);

        // 2. 公式URLがない場合、OTA URLからスラッグを抽出
        if (empty($slug)) {
            $slug = $this->extract_slug_from_ota_url($post_id);
        }

        // 3. OTA URLもない場合、ローマ字変換を試行
        if (empty($slug)) {
            $romaji = $this->convert_to_romaji($hotel_name);
            
            // 漢字が含まれていたかチェック（変換後が短すぎる場合は失敗とみなす）
            if (!empty($romaji) && strlen($romaji) >= 5) {
                $slug = $romaji;
            }
        }

        // 4. フォールバック：投稿IDベース
        if (empty($slug)) {
            $slug = 'hotel-review-' . $post_id;
        }

        // スラッグを更新
        remove_action('save_post_' . $this->post_type, array($this, 'optimize_yoast_seo'), 30);
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_name' => $slug,
        ));

        add_action('save_post_' . $this->post_type, array($this, 'optimize_yoast_seo'), 30, 2);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Yoast] Slug set: ' . $slug . ' for post_id=' . $post_id);
        }
    }

    /**
     * ★ 公式URLからスラッグを抽出（ホテル固有のslug部分を優先抽出）
     * 
     * URLのパスからホテル固有のslugを抽出します
     * 例：https://www.example.com/hotel/hotel-kawakyu/ → hotel-kawakyu
     * 例：https://kawakyu-hotel.example.com/ → kawakyu-hotel
     * 
     * @param int $post_id
     * @return string
     */
    private function extract_slug_from_official_url($post_id) {
        // OTA URLsメタデータを取得
        $ota_urls = get_post_meta($post_id, '_hrs_ota_urls', true);
        
        if (empty($ota_urls) || !is_array($ota_urls)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HRS Yoast] No OTA URLs found for post_id=' . $post_id);
            }
            return '';
        }

        // 公式URLを取得
        $official_url = '';
        if (!empty($ota_urls['official'])) {
            $official_url = $ota_urls['official'];
        }

        if (empty($official_url)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HRS Yoast] No official URL in OTA URLs for post_id=' . $post_id);
            }
            return '';
        }

        // URLをパース
        $parsed = parse_url($official_url);
        if (empty($parsed['host'])) {
            return '';
        }

        // ★ 優先：URLのパスからslugを抽出
        // 例：/hotel/hotel-kawakyu/ → hotel-kawakyu
        if (!empty($parsed['path'])) {
            $slug = $this->extract_slug_from_path($parsed['path']);
            if (!empty($slug)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[HRS Yoast] Extracted slug from official URL path: ' . $official_url . ' => ' . $slug);
                }
                return $slug;
            }
        }

        // ★ フォールバック：ドメイン名からslugを抽出
        // 例：kawakyu-hotel.example.com → kawakyu-hotel
        $host = $parsed['host'];

        // www. を除去
        $host = preg_replace('/^www\./', '', $host);

        // TLDを除去 (.jp, .com, .co.jp など)
        $host = preg_replace('/\.(co\.jp|or\.jp|ne\.jp|ac\.jp|go\.jp|jp|com|net|org|info|biz)$/', '', $host);

        // ハイフンとアルファベット・数字のみ
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($host));

        // 連続ハイフンを1つに
        $slug = preg_replace('/-+/', '-', $slug);
        
        // 先頭・末尾のハイフンを削除
        $slug = trim($slug, '-');

        // 短すぎる場合は無効
        if (strlen($slug) < 3) {
            return '';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Yoast] Extracted slug from official URL domain: ' . $official_url . ' => ' . $slug);
        }

        return $slug;
    }

    /**
     * URLのパスからホテル固有のslugを抽出
     * 
     * 例：
     * /hotel/hotel-kawakyu/ → hotel-kawakyu
     * /hotels/kawakyu-onsen/ → kawakyu-onsen
     * /stay/detail/hotel-kawakyu → hotel-kawakyu
     * 
     * @param string $path
     * @return string
     */
    private function extract_slug_from_path($path) {
        // スラッシュで分割
        $segments = array_filter(explode('/', trim($path, '/')));
        
        if (empty($segments)) {
            return '';
        }

        // 最後のセグメントを取得
        $last_segment = end($segments);

        // クエリ文字列があれば除去
        $last_segment = preg_replace('/\?.*$/', '', $last_segment);

        // .html や .php などの拡張子を除去
        $last_segment = preg_replace('/\.(html|php|aspx|jsp)$/i', '', $last_segment);

        // ハイフンとアルファベット・数字のみ
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($last_segment));

        // 連続ハイフンを1つに
        $slug = preg_replace('/-+/', '-', $slug);
        
        // 先頭・末尾のハイフンを削除
        $slug = trim($slug, '-');

        // ★ ホテルサイトのslugは通常5文字以上
        // 短すぎる場合（例：数字のみ、1-2文字など）は無効
        if (strlen($slug) < 5 || !preg_match('/[a-z]/', $slug)) {
            return '';
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS Yoast] Extracted slug from path: ' . $path . ' => ' . $slug);
        }

        return $slug;
    }

    /**
     * ★ OTA URLからスラッグを抽出（新機能）
     * 
     * 優先順位：楽天 → じゃらん → 一休 → その他
     * 
     * @param int $post_id
     * @return string
     */
    private function extract_slug_from_ota_url($post_id) {
        $ota_urls = get_post_meta($post_id, '_hrs_ota_urls', true);
        
        if (empty($ota_urls) || !is_array($ota_urls)) {
            return '';
        }

        // 楽天トラベルから抽出を試みる
        if (!empty($ota_urls['rakuten'])) {
            $slug = $this->extract_rakuten_slug($ota_urls['rakuten']);
            if (!empty($slug)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[HRS Yoast] Extracted slug from Rakuten: ' . $slug);
                }
                return $slug;
            }
        }

        // じゃらんから抽出を試みる
        if (!empty($ota_urls['jalan'])) {
            $slug = $this->extract_jalan_slug($ota_urls['jalan']);
            if (!empty($slug)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[HRS Yoast] Extracted slug from Jalan: ' . $slug);
                }
                return $slug;
            }
        }

        // 一休から抽出を試みる
        if (!empty($ota_urls['ikyu'])) {
            $slug = $this->extract_ikyu_slug($ota_urls['ikyu']);
            if (!empty($slug)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[HRS Yoast] Extracted slug from Ikyu: ' . $slug);
                }
                return $slug;
            }
        }

        return '';
    }

    /**
     * 楽天トラベルURLからスラッグ抽出
     * 
     * 例：https://travel.rakuten.co.jp/HOTEL/5950/5950.html
     * → rakuten-5950
     * 
     * @param string $url
     * @return string
     */
    private function extract_rakuten_slug($url) {
        // パターン1: /HOTEL/数字/
        if (preg_match('/\/HOTEL\/(\d+)\//i', $url, $matches)) {
            return 'rakuten-' . $matches[1];
        }

        // パターン2: /hotel/数字/
        if (preg_match('/\/hotel\/(\d+)\//i', $url, $matches)) {
            return 'rakuten-' . $matches[1];
        }

        return '';
    }

    /**
     * じゃらんURLからスラッグ抽出
     * 
     * 例：https://www.jalan.net/yad328561/
     * → jalan-328561
     * 
     * @param string $url
     * @return string
     */
    private function extract_jalan_slug($url) {
        // パターン1: /yad数字/
        if (preg_match('/\/yad(\d+)/i', $url, $matches)) {
            return 'jalan-' . $matches[1];
        }

        return '';
    }

    /**
     * 一休URLからスラッグ抽出
     * 
     * 例：https://www.ikyu.com/00030726/
     * → ikyu-30726
     * 
     * @param string $url
     * @return string
     */
    private function extract_ikyu_slug($url) {
        // パターン1: /数字/
        if (preg_match('/\/(\d{5,})\/?/i', $url, $matches)) {
            // 先頭の0を除去
            $id = ltrim($matches[1], '0');
            return 'ikyu-' . $id;
        }

        return '';
    }

    private function set_excerpt($post_id, $hotel_name, $content) {
        $post = get_post($post_id);
        
        if (!empty($post->post_excerpt)) {
            return;
        }

        $excerpt = $this->generate_excerpt($hotel_name, $content);
        
        if (empty($excerpt)) {
            return;
        }

        remove_action('save_post_' . $this->post_type, array($this, 'optimize_yoast_seo'), 30);
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_excerpt' => $excerpt,
        ));

        add_action('save_post_' . $this->post_type, array($this, 'optimize_yoast_seo'), 30, 2);
    }

    public function generate_excerpt($hotel_name, $content) {
        $plain_text = wp_strip_all_tags($content);
        $plain_text = preg_replace('/\s+/', ' ', $plain_text);
        $plain_text = trim($plain_text);
        
        if (empty($plain_text)) {
            return $this->generate_excerpt_from_template($hotel_name);
        }

        $excerpt = $this->extract_excerpt_with_keyphrase($plain_text, $hotel_name);
        
        if (!empty($excerpt)) {
            return $excerpt;
        }

        return $this->extract_excerpt_from_beginning($plain_text, $hotel_name);
    }

    private function extract_excerpt_with_keyphrase($text, $keyphrase) {
        $pos = mb_strpos($text, $keyphrase);
        
        if ($pos === false) {
            return null;
        }

        $start = max(0, $pos - 20);
        $length = $this->excerpt_max_length;
        
        $excerpt = mb_substr($text, $start, $length);
        
        if ($start > 0) {
            $first_break = mb_strpos($excerpt, '。');
            if ($first_break === false) {
                $first_break = mb_strpos($excerpt, '、');
            }
            if ($first_break !== false && $first_break < 20) {
                $excerpt = mb_substr($excerpt, $first_break + 1);
            }
        }

        $excerpt = $this->truncate_excerpt($excerpt);
        
        return $excerpt;
    }

    private function extract_excerpt_from_beginning($text, $hotel_name) {
        $excerpt = mb_substr($text, 0, $this->excerpt_max_length);
        $excerpt = $this->truncate_excerpt($excerpt);
        
        if (mb_strpos($excerpt, $hotel_name) === false) {
            $prefix = $hotel_name . 'について。';
            $remaining = $this->excerpt_max_length - mb_strlen($prefix);
            if ($remaining > 50) {
                $excerpt = $prefix . mb_substr($excerpt, 0, $remaining - 1) . '…';
            }
        }
        
        return $excerpt;
    }

    private function generate_excerpt_from_template($hotel_name) {
        $templates = array(
            '{hotel}の魅力をご紹介します。客室、温泉、料理など、実際に宿泊した体験をもとに詳しくレビュー。予約前に知っておきたい情報をまとめました。',
            '{hotel}の宿泊レビューです。立地やアクセス、設備、サービスの質など、実際の滞在体験をもとに徹底解説します。',
            '{hotel}を徹底紹介。おすすめポイントや注意点、お得な予約方法まで、旅行計画に役立つ情報をお届けします。',
        );

        $template = $templates[array_rand($templates)];
        $excerpt = str_replace('{hotel}', $hotel_name, $template);

        if (mb_strlen($excerpt) > $this->excerpt_max_length) {
            $excerpt = $this->truncate_excerpt($excerpt);
        }

        return $excerpt;
    }

    private function truncate_excerpt($text) {
        if (mb_strlen($text) <= $this->excerpt_max_length) {
            return $text;
        }

        $text = mb_substr($text, 0, $this->excerpt_max_length);
        
        $last_period = mb_strrpos($text, '。');
        $last_comma = mb_strrpos($text, '、');
        
        $cut_pos = max($last_period, $last_comma);
        
        if ($cut_pos !== false && $cut_pos > $this->excerpt_max_length * 0.6) {
            $text = mb_substr($text, 0, $cut_pos + 1);
        } else {
            $text = mb_substr($text, 0, $this->excerpt_max_length - 1) . '…';
        }
        
        return $text;
    }

    public function convert_to_romaji($text) {
        $hiragana_map = array(
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'さ' => 'sa', 'し' => 'shi', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'た' => 'ta', 'ち' => 'chi', 'つ' => 'tsu', 'て' => 'te', 'と' => 'to',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'を' => 'wo', 'ん' => 'n',
            'が' => 'ga', 'ぎ' => 'gi', 'ぐ' => 'gu', 'げ' => 'ge', 'ご' => 'go',
            'ざ' => 'za', 'じ' => 'ji', 'ず' => 'zu', 'ぜ' => 'ze', 'ぞ' => 'zo',
            'だ' => 'da', 'ぢ' => 'di', 'づ' => 'du', 'で' => 'de', 'ど' => 'do',
            'ば' => 'ba', 'び' => 'bi', 'ぶ' => 'bu', 'べ' => 'be', 'ぼ' => 'bo',
            'ぱ' => 'pa', 'ぴ' => 'pi', 'ぷ' => 'pu', 'ぺ' => 'pe', 'ぽ' => 'po',
            'きゃ' => 'kya', 'きゅ' => 'kyu', 'きょ' => 'kyo',
            'しゃ' => 'sha', 'しゅ' => 'shu', 'しょ' => 'sho',
            'ちゃ' => 'cha', 'ちゅ' => 'chu', 'ちょ' => 'cho',
            'にゃ' => 'nya', 'にゅ' => 'nyu', 'にょ' => 'nyo',
            'ひゃ' => 'hya', 'ひゅ' => 'hyu', 'ひょ' => 'hyo',
            'みゃ' => 'mya', 'みゅ' => 'myu', 'みょ' => 'myo',
            'りゃ' => 'rya', 'りゅ' => 'ryu', 'りょ' => 'ryo',
            'ぎゃ' => 'gya', 'ぎゅ' => 'gyu', 'ぎょ' => 'gyo',
            'じゃ' => 'ja', 'じゅ' => 'ju', 'じょ' => 'jo',
            'びゃ' => 'bya', 'びゅ' => 'byu', 'びょ' => 'byo',
            'ぴゃ' => 'pya', 'ぴゅ' => 'pyu', 'ぴょ' => 'pyo',
            'っ' => '',
            'ー' => '',
            '　' => '-',
            ' ' => '-',
            '・' => '-',
        );

        $text = mb_convert_kana($text, 'c');
        $text = mb_strtolower($text);

        $romaji = '';
        $length = mb_strlen($text);
        
        for ($i = 0; $i < $length; $i++) {
            if ($i < $length - 1) {
                $two_chars = mb_substr($text, $i, 2);
                if (isset($hiragana_map[$two_chars])) {
                    $romaji .= $hiragana_map[$two_chars];
                    $i++;
                    continue;
                }
            }

            $char = mb_substr($text, $i, 1);
            if (isset($hiragana_map[$char])) {
                $romaji .= $hiragana_map[$char];
            } elseif (preg_match('/[a-z0-9\-]/', $char)) {
                $romaji .= $char;
            }
        }

        $romaji = preg_replace('/-+/', '-', $romaji);
        $romaji = trim($romaji, '-');

        return $romaji;
    }

    public function analyze($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);

        return array(
            'focus_keyphrase' => array(
                'value' => $keyphrase,
                'status' => !empty($keyphrase) ? 'good' : 'missing',
            ),
            'meta_description' => array(
                'value' => $meta_desc,
                'length' => mb_strlen($meta_desc),
                'min_length' => $this->meta_min_length,
                'max_length' => $this->meta_max_length,
                'status' => (!empty($meta_desc) && mb_strlen($meta_desc) >= $this->meta_min_length && mb_strlen($meta_desc) <= $this->meta_max_length) ? 'good' : 'needs_improvement',
            ),
            'seo_title' => array(
                'value' => $seo_title,
                'status' => !empty($seo_title) ? 'good' : 'missing',
            ),
            'slug' => array(
                'value' => $post->post_name,
                'is_romaji' => preg_match('/^[a-z0-9\-]+$/', $post->post_name) ? true : false,
            ),
            'excerpt' => array(
                'value' => $post->post_excerpt,
                'length' => mb_strlen($post->post_excerpt),
                'max_length' => $this->excerpt_max_length,
                'has_keyphrase' => !empty($keyphrase) && mb_strpos($post->post_excerpt, $keyphrase) !== false,
                'status' => !empty($post->post_excerpt) ? 'good' : 'missing',
            ),
        );
    }
}

new HRS_Yoast_SEO_Optimizer();