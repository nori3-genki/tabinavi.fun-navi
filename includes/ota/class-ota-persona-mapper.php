<?php
/**
 * ペルソナ→OTAマッピングクラス
 * 
 * 読者ペルソナに基づいた最適なOTA推薦と
 * アフィリエイトリンクの自動生成
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_OTA_Persona_Mapper {

    /**
     * ペルソナ別OTAマッピング
     * 優先度: ◎ = primary, ◯ = secondary, △ = tertiary
     */
    private $persona_map = array(
        // 一般
        'general' => array(
            'primary' => array('rakuten'),
            'secondary' => array('jalan', 'booking'),
            'tertiary' => array('yahoo', 'jtb'),
            'message' => '幅広い選択肢から最適なプランを',
        ),
        
        // 一人旅
        'solo' => array(
            'primary' => array('rakuten', 'booking'),
            'secondary' => array('jalan'),
            'tertiary' => array('yahoo'),
            'message' => '気ままな一人旅にぴったりのプラン',
        ),
        
        // カップル・夫婦
        'couple' => array(
            'primary' => array('ikyu', 'rakuten'),
            'secondary' => array('jalan'),
            'tertiary' => array('jtb'),
            'message' => '二人だけの特別な時間を',
        ),
        
        // ファミリー
        'family' => array(
            'primary' => array('rakuten', 'jalan'),
            'secondary' => array('yahoo', 'jtb'),
            'tertiary' => array('rurubu'),
            'message' => '家族みんなが楽しめるプラン',
        ),
        
        // シニア
        'senior' => array(
            'primary' => array('jtb', 'jalan'),
            'secondary' => array('rakuten'),
            'tertiary' => array('rurubu'),
            'message' => 'ゆったり過ごせる安心プラン',
        ),
        
        // ワーケーション
        'workation' => array(
            'primary' => array('rakuten', 'booking'),
            'secondary' => array('ikyu'),
            'tertiary' => array('yahoo'),
            'message' => '仕事も休暇も充実のワーケーション',
        ),
        
        // ラグジュアリー
        'luxury' => array(
            'primary' => array('ikyu'),
            'secondary' => array('rakuten', 'jtb'),
            'tertiary' => array('jalan'),
            'message' => '最高のおもてなしを体験する',
        ),
        
        // 節約志向
        'budget' => array(
            'primary' => array('rakuten', 'yahoo'),
            'secondary' => array('jalan', 'booking'),
            'tertiary' => array('rurubu'),
            'message' => 'お得に泊まれる賢い選択',
        ),
        
        // 記念日
        'anniversary' => array(
            'primary' => array('ikyu'),
            'secondary' => array('rakuten', 'jtb'),
            'tertiary' => array('jalan'),
            'message' => '特別な日を彩る最高の滞在',
        ),
    );

    /**
     * 旅の目的別調整
     */
    private $purpose_adjustments = array(
        'onsen' => array(
            'boost' => array('jalan' => 20, 'rakuten' => 10),
            'description' => '温泉宿に強い',
        ),
        'gourmet' => array(
            'boost' => array('ikyu' => 15, 'rakuten' => 10),
            'description' => 'グルメプラン充実',
        ),
        'sightseeing' => array(
            'boost' => array('jtb' => 15, 'rurubu' => 15),
            'description' => '観光情報連携',
        ),
        'healing' => array(
            'boost' => array('ikyu' => 20, 'jalan' => 10),
            'description' => '癒しの宿に特化',
        ),
        'anniversary' => array(
            'boost' => array('ikyu' => 25),
            'description' => '記念日プラン豊富',
        ),
        'workation' => array(
            'boost' => array('booking' => 15, 'rakuten' => 10),
            'description' => 'ワーケーション対応',
        ),
    );

    /**
     * OTAセレクター
     */
    private $ota_selector = null;

    /**
     * コンストラクタ
     */
    public function __construct() {
        if (class_exists('HRS_OTA_Selector')) {
            $this->ota_selector = new HRS_OTA_Selector();
        }
    }

    /**
     * ペルソナに最適なOTAリストを取得
     * 
     * @param string $persona ペルソナID
     * @param array $purposes 旅の目的（配列）
     * @param array $options その他オプション
     * @return array
     */
    public function get_recommended_otas($persona, $purposes = array(), $options = array()) {
        $persona = $this->normalize_persona($persona);
        $mapping = $this->persona_map[$persona] ?? $this->persona_map['general'];
        
        $recommendations = array();
        
        // Primary OTAs (◎)
        foreach ($mapping['primary'] as $ota_id) {
            $recommendations[$ota_id] = array(
                'priority' => '◎',
                'score' => 100,
                'reason' => 'ペルソナに最適',
            );
        }
        
        // Secondary OTAs (◯)
        foreach ($mapping['secondary'] as $ota_id) {
            if (!isset($recommendations[$ota_id])) {
                $recommendations[$ota_id] = array(
                    'priority' => '◯',
                    'score' => 70,
                    'reason' => 'おすすめ',
                );
            }
        }
        
        // Tertiary OTAs (△)
        foreach ($mapping['tertiary'] as $ota_id) {
            if (!isset($recommendations[$ota_id])) {
                $recommendations[$ota_id] = array(
                    'priority' => '△',
                    'score' => 50,
                    'reason' => '選択肢',
                );
            }
        }
        
        // 旅の目的による調整
        foreach ($purposes as $purpose) {
            if (isset($this->purpose_adjustments[$purpose])) {
                $adjustments = $this->purpose_adjustments[$purpose];
                foreach ($adjustments['boost'] as $ota_id => $boost) {
                    if (isset($recommendations[$ota_id])) {
                        $recommendations[$ota_id]['score'] += $boost;
                        $recommendations[$ota_id]['reason'] .= ' + ' . $adjustments['description'];
                    }
                }
            }
        }
        
        // 楽天は常にアフィリエイト収益のためブースト
        if (isset($recommendations['rakuten'])) {
            $recommendations['rakuten']['score'] += 15;
            $recommendations['rakuten']['affiliate_priority'] = true;
        }
        
        // スコアでソートして優先度記号を再計算
        uasort($recommendations, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $rank = 0;
        foreach ($recommendations as $ota_id => &$rec) {
            $rank++;
            if ($rank <= 2) {
                $rec['priority'] = '◎';
            } elseif ($rank <= 4) {
                $rec['priority'] = '◯';
            } else {
                $rec['priority'] = '△';
            }
        }
        
        return $recommendations;
    }

    /**
     * 記事用OTAセクションを生成
     * 
     * @param string $persona ペルソナID
     * @param string $hotel_name ホテル名
     * @param array $purposes 旅の目的
     * @param array $existing_urls 既存のOTA URL
     * @return string HTML
     */
    public function generate_ota_section($persona, $hotel_name, $purposes = array(), $existing_urls = array()) {
        $recommendations = $this->get_recommended_otas($persona, $purposes);
        $mapping = $this->persona_map[$this->normalize_persona($persona)] ?? $this->persona_map['general'];
        
        $html = '<div class="hrs-ota-section">';
        $html .= '<h3>🏨 ' . esc_html($hotel_name) . ' の予約</h3>';
        $html .= '<p>' . esc_html($mapping['message']) . '</p>';
        $html .= '<div class="hrs-ota-links">';
        
        $count = 0;
        foreach ($recommendations as $ota_id => $rec) {
            if ($count >= 5) break;
            
            $ota_info = $this->get_ota_info($ota_id);
            if (!$ota_info) continue;
            
            // URL取得
            $url = $existing_urls[$ota_id] ?? null;
            if (!$url && $this->ota_selector) {
                $url = $this->ota_selector->generate_search_url($ota_id, $hotel_name);
            }
            
            // アフィリエイトリンク化（楽天のみ）
            if ($ota_id === 'rakuten' && $url) {
                $url = $this->generate_moshimo_link($url);
            }
            
            $priority_class = $this->get_priority_class($rec['priority']);
            $cta_text = $this->get_cta_text($ota_id, $persona);
            
            $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow noopener" ';
            $html .= 'class="hrs-ota-link ' . esc_attr($priority_class) . '">';
            $html .= '<span class="ota-priority">' . esc_html($rec['priority']) . '</span>';
            $html .= '<span class="ota-name">' . esc_html($ota_info['name']) . '</span>';
            $html .= '<span class="ota-cta">' . esc_html($cta_text) . '</span>';
            $html .= '</a>';
            
            $count++;
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * シンプルなOTAリンクリストを生成
     * 
     * @param string $persona ペルソナID
     * @param string $hotel_name ホテル名
     * @param array $existing_urls 既存URL
     * @return string HTML
     */
    public function generate_simple_links($persona, $hotel_name, $existing_urls = array()) {
        $recommendations = $this->get_recommended_otas($persona);
        
        $html = '<ul class="hrs-booking-links">';
        
        $count = 0;
        foreach ($recommendations as $ota_id => $rec) {
            if ($count >= 3) break;
            
            $ota_info = $this->get_ota_info($ota_id);
            if (!$ota_info) continue;
            
            $url = $existing_urls[$ota_id] ?? null;
            if (!$url && $this->ota_selector) {
                $url = $this->ota_selector->generate_search_url($ota_id, $hotel_name);
            }
            
            if ($ota_id === 'rakuten' && $url) {
                $url = $this->generate_moshimo_link($url);
            }
            
            $html .= '<li>';
            $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="nofollow noopener">';
            $html .= esc_html($rec['priority']) . ' ' . esc_html($ota_info['name']) . 'で予約';
            $html .= '</a>';
            $html .= '</li>';
            
            $count++;
        }
        
        $html .= '</ul>';
        
        return $html;
    }

    /**
     * MOSHIMOアフィリエイトリンク生成
     */
    private function generate_moshimo_link($url) {
        $moshimo_id = '5247247';
        $encoded_url = urlencode($url);
        return "https://af.moshimo.com/af/c/click?a_id={$moshimo_id}&p_id=54&pc_id=54&pl_id=616&url={$encoded_url}";
    }

    /**
     * ペルソナ正規化
     */
    private function normalize_persona($persona) {
        $aliases = array(
            '一人旅' => 'solo',
            'ソロ' => 'solo',
            'カップル' => 'couple',
            '夫婦' => 'couple',
            'ファミリー' => 'family',
            '家族' => 'family',
            'シニア' => 'senior',
            '高齢者' => 'senior',
            'ワーケーション' => 'workation',
            'リモートワーク' => 'workation',
            'ラグジュアリー' => 'luxury',
            '高級' => 'luxury',
            '節約' => 'budget',
            'コスパ' => 'budget',
            '記念日' => 'anniversary',
        );
        
        return $aliases[$persona] ?? $persona;
    }

    /**
     * OTA情報取得
     */
    private function get_ota_info($ota_id) {
        $otas = array(
            'rakuten' => array('name' => '楽天トラベル'),
            'jalan' => array('name' => 'じゃらん'),
            'ikyu' => array('name' => '一休.com'),
            'booking' => array('name' => 'Booking.com'),
            'yahoo' => array('name' => 'Yahoo!トラベル'),
            'jtb' => array('name' => 'JTB'),
            'rurubu' => array('name' => 'るるぶトラベル'),
        );
        
        return $otas[$ota_id] ?? null;
    }

    /**
     * 優先度CSSクラス取得
     */
    private function get_priority_class($priority) {
        $classes = array(
            '◎' => 'priority-high',
            '◯' => 'priority-medium',
            '△' => 'priority-low',
        );
        
        return $classes[$priority] ?? 'priority-low';
    }

    /**
     * CTAテキスト取得
     */
    private function get_cta_text($ota_id, $persona) {
        $texts = array(
            'rakuten' => array(
                'default' => '楽天ポイントでお得に',
                'budget' => 'ポイント還元でお得',
            ),
            'jalan' => array(
                'default' => '口コミをチェック',
            ),
            'ikyu' => array(
                'default' => '特別プランを見る',
                'luxury' => '最高のおもてなしを',
            ),
            'booking' => array(
                'default' => '空室確認・予約',
            ),
            'yahoo' => array(
                'default' => 'PayPayがお得',
            ),
            'jtb' => array(
                'default' => '安心の大手で予約',
            ),
        );
        
        $ota_texts = $texts[$ota_id] ?? array('default' => '詳細を見る');
        
        return $ota_texts[$persona] ?? $ota_texts['default'];
    }

    /**
     * ペルソナマップ取得
     */
    public function get_persona_map() {
        return $this->persona_map;
    }

    /**
     * 旅の目的調整取得
     */
    public function get_purpose_adjustments() {
        return $this->purpose_adjustments;
    }
}