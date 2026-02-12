<?php
/**
 * HQC Presets - プリセット管理クラス
 * * プリセット定義、設定のサニタイズ・バリデーション
 * * @package Hotel_Review_System
 * @subpackage HQC
 * @version 6.7.5-FIX
 * * 変更履歴:
 * - 6.7.1: 初期実装
 * - 6.7.4-FIX: Q層に expression/volume/target/seo/reliability を追加
 * - 6.7.5-FIX: 未定義キーによるPHP警告(Notice)を回避するissetチェックを追加
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Hqc_Presets {

    /** キャッシュ用 */
    private static $presets = null;

    /**
     * プリセット一覧を取得
     */
    public static function get_presets() {
        if (self::$presets !== null) {
            return self::$presets;
        }

        self::$presets = [
            'presets' => [
                'starter' => [
                    'name' => 'Starter',
                    'icon' => '🚀',
                    'desc' => '幅広い読者向けの軽量標準設定',
                    'h' => ['persona' => 'general', 'purpose' => ['sightseeing'], 'depth' => 'L2'],
                    'q' => [
                        'tone' => 'casual',
                        'structure' => 'timeline',
                        'sensory' => 'G1',
                        'story' => 'S1',
                        'info' => 'I1',
                        'expression' => 'E2',
                        'volume' => 'V2',
                        'target' => 'T2',
                        'seo' => 'SEO2',
                        'reliability' => 'R2',
                    ],
                    'c' => ['commercial' => 'none', 'experience' => 'recommend', 'contents' => ['cta', 'price_info', 'access_info']]
                ],
                'drama' => [
                    'name' => 'Drama',
                    'icon' => '💝',
                    'desc' => '感動重視の記念日向け',
                    'h' => ['persona' => 'couple', 'purpose' => ['anniversary'], 'depth' => 'L3'],
                    'q' => [
                        'tone' => 'emotional',
                        'structure' => 'hero_journey',
                        'sensory' => 'G3',
                        'story' => 'S3',
                        'info' => 'I2',
                        'expression' => 'E3',
                        'volume' => 'V3',
                        'target' => 'T3',
                        'seo' => 'SEO2',
                        'reliability' => 'R3',
                    ],
                    'c' => ['commercial' => 'conversion', 'experience' => 'immersive', 'contents' => ['cta', 'affiliate_links', 'price_info', 'seasonal_info']]
                ],
                'seo' => [
                    'name' => 'SEO',
                    'icon' => '📈',
                    'desc' => '検索上位狙いの情報重視（バランス調整版）',
                    'h' => ['persona' => 'general', 'purpose' => ['gourmet'], 'depth' => 'L1'],
                    'q' => [
                        'tone' => 'journalistic',
                        'structure' => 'review',
                        'sensory' => 'G1',
                        'story' => 'S1',
                        'info' => 'I2',
                        'expression' => 'E2',
                        'volume' => 'V3',
                        'target' => 'T2',
                        'seo' => 'SEO3',
                        'reliability' => 'R2',
                    ],
                    'c' => ['commercial' => 'seo', 'experience' => 'record', 'contents' => ['cta', 'price_info', 'faq', 'pros_cons', 'access_info', 'reviews']]
                ],
                'family' => [
                    'name' => 'Family',
                    'icon' => '👨‍👩‍👧‍👦',
                    'desc' => '家族旅行向け',
                    'h' => ['persona' => 'family', 'purpose' => ['family'], 'depth' => 'L2'],
                    'q' => [
                        'tone' => 'casual',
                        'structure' => 'timeline',
                        'sensory' => 'G2',
                        'story' => 'S2',
                        'info' => 'I2',
                        'expression' => 'E2',
                        'volume' => 'V2',
                        'target' => 'T2',
                        'seo' => 'SEO2',
                        'reliability' => 'R2',
                    ],
                    'c' => ['commercial' => 'none', 'experience' => 'recommend', 'contents' => ['cta', 'price_info', 'target_audience', 'access_info']]
                ],
                'luxury' => [
                    'name' => 'Luxury',
                    'icon' => '💎',
                    'desc' => '高級志向向け',
                    'h' => ['persona' => 'luxury', 'purpose' => ['onsen', 'anniversary'], 'depth' => 'L3'],
                    'q' => [
                        'tone' => 'luxury',
                        'structure' => 'five_sense',
                        'sensory' => 'G3',
                        'story' => 'S3',
                        'info' => 'I2',
                        'expression' => 'E3',
                        'volume' => 'V3',
                        'target' => 'T3',
                        'seo' => 'SEO2',
                        'reliability' => 'R3',
                    ],
                    'c' => ['commercial' => 'conversion', 'experience' => 'immersive', 'contents' => ['cta', 'affiliate_links', 'comparison', 'seasonal_info']]
                ],
                'workation' => [
                    'name' => 'Workation',
                    'icon' => '💻',
                    'desc' => 'リモートワーカー向け',
                    'h' => ['persona' => 'workation', 'purpose' => ['workation'], 'depth' => 'L1'],
                    'q' => [
                        'tone' => 'journalistic',
                        'structure' => 'timeline',
                        'sensory' => 'G1',
                        'story' => 'S1',
                        'info' => 'I1',
                        'expression' => 'E1',
                        'volume' => 'V1',
                        'target' => 'T1',
                        'seo' => 'SEO1',
                        'reliability' => 'R2',
                    ],
                    'c' => ['commercial' => 'seo', 'experience' => 'record', 'contents' => ['cta', 'price_info', 'pros_cons', 'access_info']]
                ],
                'budget' => [
                    'name' => 'Budget',
                    'icon' => '💰',
                    'desc' => 'コスパ重視',
                    'h' => ['persona' => 'budget', 'purpose' => ['budget'], 'depth' => 'L1'],
                    'q' => [
                        'tone' => 'casual',
                        'structure' => 'review',
                        'sensory' => 'G1',
                        'story' => 'S1',
                        'info' => 'I1',
                        'expression' => 'E1',
                        'volume' => 'V1',
                        'target' => 'T1',
                        'seo' => 'SEO1',
                        'reliability' => 'R1',
                    ],
                    'c' => ['commercial' => 'conversion', 'experience' => 'record', 'contents' => ['cta', 'affiliate_links', 'price_info', 'comparison', 'pros_cons']]
                ],
                'fivesense' => [
                    'name' => 'Five Sense',
                    'icon' => '✨',
                    'desc' => '五感没入型',
                    'h' => ['persona' => 'solo', 'purpose' => ['healing'], 'depth' => 'L3'],
                    'q' => [
                        'tone' => 'cinematic',
                        'structure' => 'five_sense',
                        'sensory' => 'G3',
                        'story' => 'S3',
                        'info' => 'I2',
                        'expression' => 'E3',
                        'volume' => 'V2',
                        'target' => 'T2',
                        'seo' => 'SEO2',
                        'reliability' => 'R2',
                    ],
                    'c' => ['commercial' => 'none', 'experience' => 'immersive', 'contents' => ['cta', 'seasonal_info', 'access_info']]
                ],
            ],
        ];

        return self::$presets;
    }

    /**
     * 設定をサニタイズ・バリデーション
     */
    public static function sanitize_and_validate_settings($raw) {
        $defaults = HRS_Hqc_Data::get_default_settings();
        $sanitized = $defaults;

        // H層
        if (isset($raw['h']) && is_array($raw['h'])) {
            $h = $raw['h'];
            $allowed_personas = array_keys(HRS_Hqc_Data::get_personas());
            $persona = isset($h['persona']) ? sanitize_text_field($h['persona']) : $defaults['h']['persona'];
            $sanitized['h']['persona'] = in_array($persona, $allowed_personas, true) ? $persona : $defaults['h']['persona'];

            $allowed_depths = ['L1', 'L2', 'L3'];
            $depth = isset($h['depth']) ? sanitize_text_field($h['depth']) : $defaults['h']['depth'];
            $sanitized['h']['depth'] = in_array($depth, $allowed_depths, true) ? $depth : $defaults['h']['depth'];

            $purposes = [];
            $allowed_purposes = array_keys(HRS_Hqc_Data::get_purposes());
            if (isset($h['purpose']) && is_array($h['purpose'])) {
                foreach ($h['purpose'] as $p) {
                    $p = sanitize_text_field($p);
                    if (in_array($p, $allowed_purposes, true)) {
                        $purposes[] = $p;
                    }
                }
            }
            $sanitized['h']['purpose'] = !empty($purposes) ? $purposes : $defaults['h']['purpose'];
        }

        // Q層
        if (isset($raw['q']) && is_array($raw['q'])) {
            $q = $raw['q'];
            
            // 既存項目 (issetチェックを追加して安全に)
            $allowed_tones = array_keys(HRS_Hqc_Data::get_tones());
            if (isset($q['tone'])) {
                $sanitized['q']['tone'] = in_array($q['tone'], $allowed_tones, true) ? $q['tone'] : $defaults['q']['tone'];
            }

            $allowed_structures = array_keys(HRS_Hqc_Data::get_structures());
            if (isset($q['structure'])) {
                $sanitized['q']['structure'] = in_array($q['structure'], $allowed_structures, true) ? $q['structure'] : $defaults['q']['structure'];
            }

            if (isset($q['sensory'])) $sanitized['q']['sensory'] = in_array($q['sensory'], ['G1', 'G2', 'G3'], true) ? $q['sensory'] : $defaults['q']['sensory'];
            if (isset($q['story']))   $sanitized['q']['story']   = in_array($q['story'], ['S1', 'S2', 'S3'], true) ? $q['story'] : $defaults['q']['story'];
            if (isset($q['info']))    $sanitized['q']['info']    = in_array($q['info'], ['I1', 'I2', 'I3'], true) ? $q['info'] : $defaults['q']['info'];

            // === 修正箇所: isset()を追加して未定義警告を防止 ===
            if (isset($q['expression']))  $sanitized['q']['expression']  = in_array($q['expression'], ['E1', 'E2', 'E3'], true) ? $q['expression'] : $defaults['q']['expression'];
            if (isset($q['volume']))      $sanitized['q']['volume']      = in_array($q['volume'], ['V1', 'V2', 'V3'], true) ? $q['volume'] : $defaults['q']['volume'];
            if (isset($q['target']))      $sanitized['q']['target']      = in_array($q['target'], ['T1', 'T2', 'T3'], true) ? $q['target'] : $defaults['q']['target'];
            if (isset($q['seo']))         $sanitized['q']['seo']         = in_array($q['seo'], ['SEO1', 'SEO2', 'SEO3'], true) ? $q['seo'] : $defaults['q']['seo'];
            if (isset($q['reliability'])) $sanitized['q']['reliability'] = in_array($q['reliability'], ['R1', 'R2', 'R3'], true) ? $q['reliability'] : $defaults['q']['reliability'];
        }

        // C層
        if (isset($raw['c']) && is_array($raw['c'])) {
            $c = $raw['c'];
            if (isset($c['commercial'])) $sanitized['c']['commercial'] = in_array($c['commercial'], ['none', 'seo', 'conversion'], true) ? $c['commercial'] : $defaults['c']['commercial'];
            if (isset($c['experience'])) $sanitized['c']['experience'] = in_array($c['experience'], ['record', 'recommend', 'immersive'], true) ? $c['experience'] : $defaults['c']['experience'];

            $contents = [];
            $allowed_contents = array_keys(HRS_Hqc_UI::get_c_content_items());
            if (isset($c['contents']) && is_array($c['contents'])) {
                foreach ($c['contents'] as $content) {
                    $content = sanitize_text_field($content);
                    if (in_array($content, $allowed_contents, true)) {
                        $contents[] = $content;
                    }
                }
            }
            $sanitized['c']['contents'] = !empty($contents) ? $contents : $defaults['c']['contents'];
        }

        return $sanitized;
    }

    public static function sanitize_settings_for_output($data) {
        $defaults = HRS_Hqc_Data::get_default_settings();
        $out = wp_parse_args((array)$data, $defaults);
        $out['h'] = wp_parse_args((array)($out['h'] ?? []), $defaults['h']);
        $out['q'] = wp_parse_args((array)($out['q'] ?? []), $defaults['q']);
        $out['c'] = wp_parse_args((array)($out['c'] ?? []), $defaults['c']);
        return $out;
    }
}