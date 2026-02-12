<?php
/**
 * HQC改善ガイドクラス
 * 
 * @package HRS
 * @version 4.5.0-SPLIT
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_Guide {

    /**
     * HQC改善ガイドを取得（6カテゴリ）
     */
    public function get_improvement_guide($hotel_data = array(), $location = '') {
        $guide = array();

        // 1. コンテンツ不足（E-E-A-T の薄さ）
        $guide['eeat'] = array(
            'title' => 'コンテンツ不足（E-E-A-T の薄さ）',
            'icon' => '🟦',
            'priority' => 1,
            'description' => '一次体験と固有情報を追加することでHQCが20〜30%向上します',
            'actions' => array(
                array(
                    'title' => '一次体験（ファーストハンド体験）を入れる',
                    'items' => array(
                        '宿泊した感想・写真の状況',
                        '匂い・音・光などの五感描写',
                        '実際に体験したことを具体的に書く',
                    ),
                ),
                array(
                    'title' => '固有情報を入れる（どのブログにも載っていない内容）',
                    'items' => array(
                        '部屋番号・部屋タイプ',
                        '宿の人との会話',
                        '実際に食べた料理・選んだプラン',
                        '宿に行く前の期待とギャップ',
                    ),
                ),
            ),
            'impact' => '+20〜30%',
        );

        // 2. 構造化の不足
        $guide['structure'] = array(
            'title' => '構造化の不足（情報が整理されていない）',
            'icon' => '🟦',
            'priority' => 2,
            'description' => '情報を整理して読みやすくすることでスコアが向上します',
            'actions' => array(
                array(
                    'title' => '5点構成にする',
                    'items' => array(
                        '結論 → 理由 → 体験談 → 具体例 → まとめ',
                    ),
                ),
                array(
                    'title' => '見出しと箇条書きを活用',
                    'items' => array(
                        '各セクションにH2/H3を付ける',
                        '箇条書きの密度を上げる',
                        '最後にFAQを入れる（LLMO評価に効果的）',
                    ),
                ),
            ),
            'impact' => '+10〜15%',
        );

        // 3. 文章の抽象度が高い
        $guide['abstraction'] = array(
            'title' => '文章の"抽象度"が高く、AI生成に見える',
            'icon' => '🟦',
            'priority' => 3,
            'description' => '具体的な描写に置き換えることで人間らしさが増します',
            'actions' => array(
                array(
                    'title' => '固有名詞密度を高める',
                    'items' => array(
                        '❌「景色が良かった」',
                        '✅「新緑の長瀞渓谷が朝もやに包まれていた」',
                    ),
                ),
                array(
                    'title' => '主観＋客観のセットを書く',
                    'items' => array(
                        '❌「美味しい」',
                        '✅「特に〇〇の△△は□□という味で…」',
                    ),
                ),
                array(
                    'title' => '物語・ユーモア・体験談を入れる',
                    'items' => array(
                        'あなたのブログの得意領域を活かす',
                    ),
                ),
            ),
            'impact' => '+10〜20%',
        );

        // 4. AIっぽいワード
        $guide['ai_words'] = array(
            'title' => 'LLMO（生成AI評価）に弱いワードが多い',
            'icon' => '🟦',
            'priority' => 4,
            'description' => 'AIに特有の「フラットで均質な表現」を避けます',
            'actions' => array(
                array(
                    'title' => '禁止ワードを減らす',
                    'items' => array(
                        '「素晴らしい」「最高でした」「とても良かった」などの典型AIっぽい形容詞',
                    ),
                ),
                array(
                    'title' => '文脈＋描写の組み合わせに置換',
                    'items' => array(
                        '❌「素晴らしい露天風呂」',
                        '✅「夜風が肌を撫でて、湯面に映る月が揺れる露天風呂」',
                    ),
                ),
            ),
            'impact' => '+5〜10%',
        );

        // 5. 読者ニーズとのズレ
        $hotel_name = $hotel_data['hotel_name'] ?? 'ホテル名';
        $guide['search_intent'] = array(
            'title' => '読者ニーズ（検索意図）とのズレ',
            'icon' => '🟦',
            'priority' => 5,
            'description' => '検索キーワードに対する「網羅性」を高めます',
            'actions' => array(
                array(
                    'title' => '想定キーワードの検索意図を洗い出す',
                    'items' => array(
                        "「{$hotel_name} 口コミ」",
                        "「{$hotel_name} 宿泊記」",
                        "「{$hotel_name} 朝食 美味しい？」",
                    ),
                ),
                array(
                    'title' => '各検索意図に回答パラグラフを追加',
                    'items' => array(
                        'それぞれの疑問に答えるセクションを作成',
                    ),
                ),
            ),
            'impact' => '+5〜15%',
        );

        // 6. JSON-LD / 構造化データ
        $guide['structured_data'] = array(
            'title' => 'JSON-LD / 構造化データが弱い',
            'icon' => '🟦',
            'priority' => 6,
            'description' => 'Google評価では構造化データの有無も重要です',
            'actions' => array(
                array(
                    'title' => '構造化データを追加',
                    'items' => array(
                        'レビュー（Review）のJSON-LDを挿入',
                        '宿泊施設（LodgingBusiness）の情報を追加',
                        'FAQの構造化マークアップを入れる',
                    ),
                ),
                array(
                    'title' => 'WordPress連携',
                    'items' => array(
                        'テンプレートに自動埋め込み可能',
                        'ACFとの連携で効率化',
                    ),
                ),
            ),
            'impact' => '+5〜10%',
        );

        // ロードマップ
        $guide['roadmap'] = array(
            'title' => 'HQC 50% → 80% 最短ロードマップ',
            'icon' => '📈',
            'steps' => array(
                '1. ファーストハンド体験（固有描写）を3倍に強化',
                '2. 検索意図に沿った6〜8セクション構成化',
                '3. レビュー＋FAQのJSON-LDを追加',
                '4. 共起語 × 物語性 × ユーモアの最適化',
                '5. AIっぽい表現を削ぎ落とす（スタイル修正）',
            ),
        );

        // 即座に試せる対策
        $guide['quick_fixes'] = array(
            'title' => '今すぐ試せる対策',
            'icon' => '⚡',
            'items' => array(),
        );

        if (empty($location)) {
            $guide['quick_fixes']['items'][] = '地域名（都道府県や温泉地名）を追加して再検索';
        }

        $gaps = $hotel_data['content_gaps'] ?? array();
        
        if (in_array('高信頼ソースが不足', $gaps)) {
            $guide['quick_fixes']['items'][] = '正式なホテル名で再検索';
            $guide['quick_fixes']['items'][] = 'Google CSE設定で楽天・じゃらん等が検索対象か確認';
        }

        if (in_array('情報ソースが少ない', $gaps)) {
            $guide['quick_fixes']['items'][] = 'より具体的な地域名を追加（例：箱根、熱海）';
        }

        $guide['quick_fixes']['items'][] = '上記を試しても改善しない場合は「HQCチェックをスキップ」で強制生成';

        return $guide;
    }

    /**
     * HQC改善ガイドをHTML形式で取得
     */
    public function render($guide) {
        if (empty($guide)) {
            return '';
        }

        $html = '<div class="hrs-hqc-improvement-guide">';

        // ロードマップ
        if (!empty($guide['roadmap'])) {
            $html .= '<div class="hrs-hqc-roadmap">';
            $html .= '<h4>' . esc_html($guide['roadmap']['icon'] . ' ' . $guide['roadmap']['title']) . '</h4>';
            $html .= '<ol>';
            foreach ($guide['roadmap']['steps'] as $step) {
                $html .= '<li>' . esc_html($step) . '</li>';
            }
            $html .= '</ol>';
            $html .= '</div>';
        }

        // 即座に試せる対策
        if (!empty($guide['quick_fixes']['items'])) {
            $html .= '<div class="hrs-hqc-quick-fixes">';
            $html .= '<h4>' . esc_html($guide['quick_fixes']['icon'] . ' ' . $guide['quick_fixes']['title']) . '</h4>';
            $html .= '<ul>';
            foreach ($guide['quick_fixes']['items'] as $item) {
                $html .= '<li>' . esc_html($item) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // 各カテゴリ
        $categories = array('eeat', 'structure', 'abstraction', 'ai_words', 'search_intent', 'structured_data');
        
        foreach ($categories as $key) {
            if (empty($guide[$key])) {
                continue;
            }

            $cat = $guide[$key];
            $html .= '<div class="hrs-hqc-category">';
            $html .= '<h4>' . esc_html($cat['icon'] . ' ' . $cat['title']) . '</h4>';
            
            if (!empty($cat['description'])) {
                $html .= '<p class="description">' . esc_html($cat['description']) . '</p>';
            }

            if (!empty($cat['impact'])) {
                $html .= '<span class="hrs-hqc-impact">効果: ' . esc_html($cat['impact']) . '</span>';
            }

            if (!empty($cat['actions'])) {
                foreach ($cat['actions'] as $action) {
                    $html .= '<div class="hrs-hqc-action">';
                    $html .= '<strong>' . esc_html($action['title']) . '</strong>';
                    
                    if (!empty($action['items'])) {
                        $html .= '<ul>';
                        foreach ($action['items'] as $item) {
                            $html .= '<li>' . esc_html($item) . '</li>';
                        }
                        $html .= '</ul>';
                    }
                    
                    $html .= '</div>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 後方互換用エイリアス
     */
    public function get_hqc_improvement_guide($hotel_data = array(), $location = '') {
        return $this->get_improvement_guide($hotel_data, $location);
    }

    public function render_hqc_improvement_guide($guide) {
        return $this->render($guide);
    }
}