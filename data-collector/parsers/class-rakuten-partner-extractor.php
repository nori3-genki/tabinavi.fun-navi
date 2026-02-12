<?php
if (!defined('ABSPATH')) exit;

/**
 * 楽天詳細ページから他OTAのIDを抽出する拡張版
 */
class HRS_Rakuten_Partner_Extractor {

    /**
     * 楽天ホテル詳細ページから、連携OTAのURLを抽出
     *
     * @param string $hotel_no 楽天hotelNo
     * @return array ['jalan_id' => '330299', 'ikyu_id' => '00030784', ...]
     */
    public function extract_partner_ids_from_rakuten_page($hotel_no) {
        $url = 'https://travel.rakuten.co.jp/HOTEL/' . rawurlencode($hotel_no) . '/';

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; HRS-LinkBot/1.0; +https://tabinavi.fun/bot)',
                'Accept' => 'text/html,application/xhtml+xml',
            ),
        ));

        if (is_wp_error($response)) {
            error_log("[HRS Rakuten] Failed to fetch detail page for hotelNo={$hotel_no}: " . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $ids = array();

        // じゃらん宿番号抽出
        if (preg_match('/https?:\/\/www\.jalan\.net\/yad(\d+)\//i', $body, $matches)) {
            $ids['jalan_id'] = $matches[1];
            error_log("[HRS Rakuten] Extracted jalan_id={$matches[1]} from rakuten hotelNo={$hotel_no}");
        }

        // 一休 hotel_id 抽出
        if (preg_match('/https?:\/\/www\.ikyu\.com\/(\d+)\//i', $body, $matches)) {
            $ids['ikyu_id'] = $matches[1];
            error_log("[HRS Rakuten] Extracted ikyu_id={$matches[1]} from rakuten hotelNo={$hotel_no}");
        }

        // JTB / るるぶ（共通ドメイン）
        if (preg_match('/https?:\/\/(?:www\.)?(jtb\.co\.jp|rurubu\.travel)\/[^"]*code=([A-Z0-9\-]+)/i', $body, $matches)) {
            $ids['jtb_code'] = $matches[2];
        }

        // ゆこゆこ（数値ID）
        if (preg_match('/https?:\/\/www\.yukoyuko\.net\/(\d+)/i', $body, $matches)) {
            $ids['yukoyuko_id'] = $matches[1];
        }

        // Booking.com hotel_id（必要に応じて）
        if (preg_match('/https?:\/\/www\.booking\.com\/hotel\/jp\/([^\/\?]+)\.ja\.html/i', $body, $matches)) {
            $ids['booking_slug'] = $matches[1];
        }

        return $ids;
    }
}