<?php
if (!defined('ABSPATH')) exit;

/**
 * 一休.com向け口コミ情報パーサー
 */
class HRS_Ikyu_Review_Parser {

    public function parse($html, $url) {
        return array(
            'reviews' => $this->extract_reviews($html),
            'rating_summary' => $this->extract_rating_summary($html),
            'special_notes' => $this->extract_special_notes($html),
        );
    }

    private function extract_reviews($html) {
        $reviews = array();

        if (preg_match_all('/<div class="c-reviews__item"[^>]*>(.*?)<\/div>/is', $html, $items)) {
            foreach ($items[1] as $item) {
                $rating = 0;
                if (preg_match('/<span class="c-rating__value">(\d+\.\d)<\/span>/', $item, $m)) {
                    $rating = (float)$m[1];
                }

                $text = '';
                if (preg_match('/<div class="c-reviews__body"[^>]*>(.*?)<\/div>/is', $item, $m)) {
                    $text = $this->clean_text($m[1]);
                }

                $persona = 'general';
                if (strpos($item, 'ファミリー') !== false) $persona = 'family';
                elseif (strpos($item, 'カップル') !== false) $persona = 'couple';
                elseif (strpos($item, '友人') !== false) $persona = 'friend';
                elseif (strpos($item, '出張') !== false) $persona = 'solo';

                if ($text) {
                    $reviews[] = compact('rating', 'text', 'persona');
                }
            }
        }

        return array_slice($reviews, 0, 10);
    }

    private function extract_rating_summary($html) {
        $summary = array();
        if (preg_match('/総合評価：(\d+\.\d)点/', $html, $m)) {
            $summary['overall'] = (float)$m[1];
        }
        if (preg_match_all('/<dt>(.*?)<\/dt>\s*<dd>(\d+\.\d)点<\/dd>/u', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $key = $this->normalize_rating_key($m[1]);
                $summary[$key] = (float)$m[2];
            }
        }
        return $summary;
    }

    private function normalize_rating_key($key) {
        $map = array(
            'お部屋' => 'room',
            'お風呂' => 'bath',
            'お食事' => 'meal',
            'ロケーション' => 'location',
            '接客・サービス' => 'service',
        );
        return $map[$key] ?? 'other';
    }

    private function extract_special_notes($html) {
        $notes = array();
        if (preg_match('/<h3>一休限定特典<\/h3>.*?<ul>(.*?)<\/ul>/is', $html, $m)) {
            if (preg_match_all('/<li>(.*?)<\/li>/is', $m[1], $items)) {
                foreach ($items[1] as $item) {
                    $text = $this->clean_text($item);
                    if ($text) $notes[] = $text;
                }
            }
        }
        return $notes;
    }

    private function clean_text($html) {
        $text = preg_replace('#<[^>]+>#', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}