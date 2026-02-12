<?php
if (!defined('ABSPATH')) exit;

/**
 * ゆこゆこ向けプラン・口コミ情報パーサー
 */
class HRS_Yukoyuko_Plan_Parser {

    public function parse($html, $url) {
        $data = array(
            'plans' => $this->extract_plans($html),
            'yukoyuko_exclusive' => $this->extract_yukoyuko_navi($html),
            'reviews_summary' => $this->extract_review_summary($html),
        );
        return $data;
    }

    private function extract_plans($html) {
        $plans = array();

        // ゆこゆこは <script type="application/json" id="hotelData"> に全データ
        if (preg_match('/<script type="application\/json" id="hotelData">({.*?})<\/script>/s', $html, $m)) {
            $json = json_decode($m[1], true);
            if (!empty($json['plans'])) {
                foreach ($json['plans'] as $p) {
                    $plans[] = array(
                        'name' => $p['planName'] ?? '',
                        'price_min' => $p['minPrice'] ?? 0,
                        'price_max' => $p['maxPrice'] ?? 0,
                        'meals' => $p['meal'] ?? '',
                        'room_type' => $p['roomType'] ?? '',
                        'features' => $this->extract_features_from_yukoyuko_plan($p),
                    );
                }
            }
        }

        return array_filter($plans, function($p) { return !empty($p['name']); });
    }

    private function extract_features_from_yukoyuko_plan($plan) {
        $features = array();
        $desc = ($plan['description'] ?? '') . ' ' . ($plan['special'] ?? '');

        $keywords = array(
            '天空露天風呂', 'ライブキッチン', '感染症対策', 'お子様無料', 'ペット可',
            '貸切風呂', 'ビアガーデン', 'ウェルカムフルーツ', '記念日プレート'
        );
        foreach ($keywords as $kw) {
            if (strpos($desc, $kw) !== false) {
                $features[] = $kw;
            }
        }
        return array_unique($features);
    }

    private function extract_yukoyuko_navi($html) {
        if (preg_match('/<h3[^>]*>ゆこゆこナビ特典<\/h3>.*?<ul>(.*?)<\/ul>/is', $html, $m)) {
            if (preg_match_all('/<li>(.*?)<\/li>/is', $m[1], $items)) {
                return array_map(function($x) {
                    return trim(strip_tags(html_entity_decode($x, ENT_QUOTES, 'UTF-8')));
                }, $items[1]);
            }
        }
        return array();
    }

    private function extract_review_summary($html) {
        $count = 0;
        $avg = 0.0;
        if (preg_match('/総合評価：(\d+\.\d)点\s*\((\d+)件\)/u', $html, $m)) {
            $avg = (float)$m[1];
            $count = (int)$m[2];
        }
        return compact('count', 'avg');
    }
}