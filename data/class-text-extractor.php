<?php
/**
 * テキスト抽出クラス
 * 
 * OTAサイトやホテル公式サイトからテキスト情報を抽出する
 * 
 * @package HRS
 * @subpackage DataCollector
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Text_Extractor {

    /**
     * ユーザーエージェント
     */
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * タイムアウト（秒）
     */
    private $timeout = 10;

    /**
     * OTAサイト別のセレクタ設定
     */
    private $selectors = array(
        'rakuten' => array(
            'title' => '.htlName, .hotelName, h1.hotel-name',
            'description' => '.htlDescription, .hotelCatch, .hotel-description',
            'features' => '.htlFacility li, .facility-item, .amenity-item',
            'address' => '.htlAddress, .hotel-address, .address',
            'price' => '.price, .room-price, .plan-price',
        ),
        'jalan' => array(
            'title' => '.p-hotelName, h1.hotel_name',
            'description' => '.p-hotelDescription, .hotel_description',
            'features' => '.facility_list li, .amenity li',
            'address' => '.p-hotelAddress, .hotel_address',
            'price' => '.price_value, .plan_price',
        ),
        'ikyu' => array(
            'title' => '.hotelName, h1.title',
            'description' => '.hotelDescription, .catch',
            'features' => '.facility li, .amenity li',
            'address' => '.address, .hotel-address',
            'price' => '.price, .plan-price',
        ),
        'relux' => array(
            'title' => '.property-name, h1',
            'description' => '.property-description, .catch-copy',
            'features' => '.facility-item, .amenity-item',
            'address' => '.property-address, .address',
            'price' => '.price, .room-price',
        ),
        'jtb' => array(
            'title' => '.hotel-name, h1.title',
            'description' => '.hotel-description, .catch',
            'features' => '.facility li, .amenity li',
            'address' => '.hotel-address, .address',
            'price' => '.price, .plan-price',
        ),
        'default' => array(
            'title' => 'h1, .title, .hotel-name, .property-name',
            'description' => '.description, .catch, .summary, p',
            'features' => '.feature, .facility, .amenity, li',
            'address' => '.address, .location',
            'price' => '.price',
        ),
    );

    /**
     * コンストラクタ
     */
    public function __construct() {
        // 初期化
    }

    /**
     * URLからページを取得
     * 
     * @param string $url 対象URL
     * @return string|false HTML内容またはfalse
     */
    public function fetch_page($url) {
        if (empty($url)) {
            return false;
        }

        $args = array(
            'timeout' => $this->timeout,
            'headers' => array(
                'User-Agent' => $this->user_agent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ja,en-US;q=0.7,en;q=0.3',
            ),
            'sslverify' => false,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('[HRS Text Extractor] Fetch error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('[HRS Text Extractor] HTTP error: ' . $status_code . ' for ' . $url);
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * HTMLからテキストを抽出
     * 
     * @param string $html HTML内容
     * @param string $ota_type OTAタイプ（rakuten, jalan等）
     * @return array 抽出されたデータ
     */
    public function extract_text($html, $ota_type = 'default') {
        if (empty($html)) {
            return array(
                'title' => '',
                'description' => '',
                'features' => array(),
                'address' => '',
                'raw_text' => '',
            );
        }

        // DOMDocument使用
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // セレクタ取得
        $selectors = isset($this->selectors[$ota_type]) 
            ? $this->selectors[$ota_type] 
            : $this->selectors['default'];

        // タイトル抽出
        $title = $this->extract_by_selector($dom, $selectors['title']);

        // 説明抽出
        $description = $this->extract_by_selector($dom, $selectors['description']);

        // 住所抽出
        $address = $this->extract_by_selector($dom, $selectors['address']);

        // 特徴抽出
        $features = $this->extract_features_from_html($dom, $selectors['features']);

        // 生テキスト（bodyから）
        $raw_text = $this->extract_body_text($dom);

        return array(
            'title' => $this->clean_text($title),
            'description' => $this->clean_text($description),
            'features' => $features,
            'address' => $this->clean_text($address),
            'raw_text' => $this->clean_text($raw_text),
        );
    }

    /**
     * CSSセレクタでテキスト抽出
     * 
     * @param DOMDocument $dom DOMオブジェクト
     * @param string $selector_string カンマ区切りのセレクタ
     * @return string 抽出テキスト
     */
    private function extract_by_selector($dom, $selector_string) {
        $selectors = array_map('trim', explode(',', $selector_string));
        $xpath = new DOMXPath($dom);

        foreach ($selectors as $selector) {
            // CSSセレクタをXPathに変換（簡易版）
            $xpath_query = $this->css_to_xpath($selector);
            $nodes = $xpath->query($xpath_query);

            if ($nodes && $nodes->length > 0) {
                return $nodes->item(0)->textContent;
            }
        }

        return '';
    }

    /**
     * CSSセレクタをXPathに変換（簡易版）
     * 
     * @param string $selector CSSセレクタ
     * @return string XPath
     */
    private function css_to_xpath($selector) {
        $selector = trim($selector);

        // クラスセレクタ
        if (strpos($selector, '.') === 0) {
            $class = substr($selector, 1);
            return "//*[contains(@class, '{$class}')]";
        }

        // IDセレクタ
        if (strpos($selector, '#') === 0) {
            $id = substr($selector, 1);
            return "//*[@id='{$id}']";
        }

        // タグ.クラス
        if (preg_match('/^(\w+)\.(.+)$/', $selector, $matches)) {
            $tag = $matches[1];
            $class = $matches[2];
            return "//{$tag}[contains(@class, '{$class}')]";
        }

        // タグのみ
        if (preg_match('/^\w+$/', $selector)) {
            return "//{$selector}";
        }

        // その他はそのまま
        return "//{$selector}";
    }

    /**
     * 特徴リストをHTMLから抽出
     * 
     * @param DOMDocument $dom
     * @param string $selector_string
     * @return array
     */
    private function extract_features_from_html($dom, $selector_string) {
        $features = array();
        $selectors = array_map('trim', explode(',', $selector_string));
        $xpath = new DOMXPath($dom);

        foreach ($selectors as $selector) {
            $xpath_query = $this->css_to_xpath($selector);
            $nodes = $xpath->query($xpath_query);

            if ($nodes) {
                foreach ($nodes as $node) {
                    $text = $this->clean_text($node->textContent);
                    if (!empty($text) && mb_strlen($text) < 100) {
                        $features[] = $text;
                    }
                }
            }

            if (count($features) >= 20) {
                break;
            }
        }

        return array_slice(array_unique($features), 0, 20);
    }

    /**
     * bodyのテキストを抽出
     * 
     * @param DOMDocument $dom
     * @return string
     */
    private function extract_body_text($dom) {
        // script, styleを除去
        $xpath = new DOMXPath($dom);
        
        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//header') as $node) {
            $node->parentNode->removeChild($node);
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            return $body->textContent;
        }

        return '';
    }

    /**
     * テキストをクリーンアップ
     * 
     * @param string $text
     * @return string
     */
    private function clean_text($text) {
        // 改行・タブを空白に
        $text = preg_replace('/[\r\n\t]+/', ' ', $text);
        // 連続空白を単一に
        $text = preg_replace('/\s+/', ' ', $text);
        // 前後空白除去
        $text = trim($text);

        return $text;
    }

    /**
     * URLからOTAタイプを判定
     * 
     * @param string $url
     * @return string
     */
    public function detect_ota_type($url) {
        $patterns = array(
            'rakuten' => '/travel\.rakuten\.co\.jp/i',
            'jalan' => '/jalan\.net/i',
            'ikyu' => '/ikyu\.com/i',
            'relux' => '/rlx\.jp/i',
            'jtb' => '/jtb\.co\.jp/i',
            'rurubu' => '/rurubu\.travel/i',
            'yukoyuko' => '/yukoyuko\.net/i',
            'booking' => '/booking\.com/i',
            'yahoo' => '/travel\.yahoo\.co\.jp/i',
            'expedia' => '/expedia\./i',
        );

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $url)) {
                return $type;
            }
        }

        return 'default';
    }

    /**
     * URLからテキストを一括抽出
     * 
     * @param string $url 対象URL
     * @return array 抽出データ
     */
    public function extract_from_url($url) {
        $html = $this->fetch_page($url);
        if (!$html) {
            return array(
                'success' => false,
                'error' => 'ページの取得に失敗しました',
            );
        }

        $ota_type = $this->detect_ota_type($url);
        $data = $this->extract_text($html, $ota_type);
        $data['success'] = true;
        $data['ota_type'] = $ota_type;
        $data['url'] = $url;

        return $data;
    }

    /**
     * 複数URLから情報を収集
     * 
     * @param array $urls URL配列
     * @return array 収集データ
     */
    public function extract_from_multiple_urls($urls) {
        $results = array();

        foreach ($urls as $key => $url) {
            if (empty($url)) {
                continue;
            }

            $result = $this->extract_from_url($url);
            $result['source'] = $key;
            $results[$key] = $result;

            // API負荷軽減
            usleep(200000); // 0.2秒待機
        }

        return $results;
    }

    /**
     * テキストから住所を抽出
     * 
     * @param string $text
     * @return string
     */
    public function extract_address_from_text($text) {
        // 日本の住所パターン
        $patterns = array(
            '/〒?\d{3}-?\d{4}[^0-9]*[都道府県].+?[市区町村郡].+?(?=\s|$|電話|TEL|FAX)/u',
            '/[都道府県]{2,3}[市区町村郡].+?(?:\d+[-−ー]\d+[-−ー]?\d*|番地?\d*号?)/u',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->clean_text($matches[0]);
            }
        }

        return '';
    }

    /**
     * スニペットを短縮
     * 
     * @param string $text
     * @param int $length
     * @return string
     */
    public function shorten_text($text, $length = 300) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }
}