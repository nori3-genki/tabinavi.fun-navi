<?php
if (!defined('ABSPATH')) exit;

/**
 * JTB / るるぶトラベル向けプラン情報パーサー
 */
class HRS_JTB_Plan_Parser {

    public function parse($html, $url) {
        $data = array(
            'plans' => $this->extract_plans($html),
            'special_features' => $this->extract_special_features($html),
            'access_info' => $this->extract_access($html),
        );
        return $data;
    }

    private function extract_plans($html) {
        $plans = array();

        // JTBのプランブロックを抽出
        if (preg_match_all('/<div class="planBox"[^>]*>(.*?)<\/div>/is', $html, $plan_blocks)) {
            foreach ($plan_blocks[1] as $block) {
                $plan = array(
                    'name' => $this->extract_text('/<h3[^>]*class="planTtl"[^>]*>(.*?)<\/h3>/is', $block),
                    'price_range' => $this->extract_text('/<p class="price">.*?(\d{1,3},\d{3}円～\d{1,3},\d{3}円).*?<\/p>/is', $block),
                    'period' => $this->extract_text('/設定期間：([^<]+)/u', $block),
                    'features' => $this->extract_features_from_block($block),
                );

                // 特典リスト
                if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $block, $features)) {
                    $plan['features'] = array_merge($plan['features'] ?? array(), $features[1]);
                }

                // 食事・部屋タイプ
                if (preg_match('/1泊(\d+)食/', $block, $m)) {
                    $plan['meals'] = (int)$m[1];
                }
                if (preg_match('/部屋タイプ：([^<]+)/u', $block, $m)) {
                    $plan['room_type'] = trim($m[1]);
                }

                if (!empty($plan['name'])) {
                    $plans[] = $this->sanitize_plan($plan);
                }
            }
        }

        return $plans;
    }

    private function extract_special_features($html) {
        $features = array();
        if (preg_match('/<div id="facility"[^>]*>.*?<h2[^>]*>特典<\/h2>(.*?)<div class="sectionTitle"/is', $html, $m)) {
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $m[1], $items)) {
                foreach ($items[1] as $item) {
                    $text = $this->clean_text($item);
                    if ($text && !in_array($text, $features)) {
                        $features[] = $text;
                    }
                }
            }
        }
        return $features;
    }

    private function extract_access($html) {
        if (preg_match('/<h3[^>]*>アクセス<\/h3>.*?<p>(.*?)<\/p>/is', $html, $m)) {
            return $this->clean_text($m[1]);
        }
        return '';
    }

    private function extract_features_from_block($block) {
        $features = array();
        $keywords = array('貸切風呂', '露天風呂', '源泉掛け流し', 'レイトチェックアウト', 'ウェルカムドリンク', '記念撮影');
        foreach ($keywords as $kw) {
            if (strpos($block, $kw) !== false) {
                $features[] = $kw;
            }
        }
        return array_unique($features);
    }

    private function extract_text($pattern, $subject) {
        if (preg_match($pattern, $subject, $m)) {
            return $this->clean_text($m[1]);
        }
        return '';
    }

    private function clean_text($html) {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function sanitize_plan($plan) {
        if (!empty($plan['price_range'])) {
            if (preg_match('/(\d{1,3},\d{3})円～(\d{1,3},\d{3})円/', $plan['price_range'], $m)) {
                $plan['price_min'] = (int)str_replace(',', '', $m[1]);
                $plan['price_max'] = (int)str_replace(',', '', $m[2]);
            }
        }
        return $plan;
    }
}