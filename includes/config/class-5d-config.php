<?php
/**
 * 設定定数クラス
 * 
 * プラグイン全体で使用する定数・デフォルト値・設定を一元管理
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_5D_Config {

    /**
     * プラグインバージョン
     */
    const VERSION = '4.3.0-HQC';

    /**
     * 最小PHP要件
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * 最小WordPress要件
     */
    const MIN_WP_VERSION = '5.8';

    /**
     * カスタム投稿タイプ
     */
    const POST_TYPE = 'hotel-review';

    /**
     * タクソノミー
     */
    const TAXONOMY = 'hotel-category';

    /**
     * オプション接頭辞
     */
    const OPTION_PREFIX = 'hrs_';

    /**
     * メタキー接頭辞
     */
    const META_PREFIX = '_hrs_';

    /**
     * AIモデル設定
     */
    const AI_MODELS = array(
        'chatgpt' => array(
            'gpt-4o-mini' => array(
                'name' => 'GPT-4o mini',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.00015,
                'recommended' => true,
            ),
            'gpt-4o' => array(
                'name' => 'GPT-4o',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.005,
                'recommended' => false,
            ),
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.01,
                'recommended' => false,
            ),
        ),
        'claude' => array(
            'claude-3-5-sonnet-20241022' => array(
                'name' => 'Claude 3.5 Sonnet',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.003,
                'recommended' => true,
            ),
            'claude-3-opus-20240229' => array(
                'name' => 'Claude 3 Opus',
                'max_tokens' => 4096,
                'cost_per_1k' => 0.015,
                'recommended' => false,
            ),
        ),
        'gemini' => array(
            'gemini-1.5-flash' => array(
                'name' => 'Gemini 1.5 Flash',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.000075,
                'recommended' => true,
            ),
            'gemini-1.5-pro' => array(
                'name' => 'Gemini 1.5 Pro',
                'max_tokens' => 8192,
                'cost_per_1k' => 0.00125,
                'recommended' => false,
            ),
        ),
    );

    /**
     * HQCプリセット
     */
    const HQC_PRESETS = array(
        'custom' => array(
            'name' => 'カスタム設定',
            'icon' => '⚙️',
            'description' => '独自のパラメータ組み合わせ',
            'h' => array('persona' => 'general', 'purpose' => array(), 'depth' => 2),
            'q' => array('tone' => 'casual', 'structure' => 'timeline', 'sensory' => 2, 'story' => 2, 'info' => 2),
            'c' => array('commercial' => 'none', 'experience' => 'record'),
        ),
        'starter' => array(
            'name' => 'Starter',
            'icon' => '🎯',
            'description' => 'スタンダードな基本設定',
            'h' => array('persona' => 'general', 'purpose' => array('sightseeing'), 'depth' => 2),
            'q' => array('tone' => 'casual', 'structure' => 'timeline', 'sensory' => 2, 'story' => 2, 'info' => 2),
            'c' => array('commercial' => 'seo', 'experience' => 'record'),
        ),
        'drama' => array(
            'name' => 'Drama',
            'icon' => '🎭',
            'description' => '感動を重視した物語調',
            'h' => array('persona' => 'couple', 'purpose' => array('anniversary', 'healing'), 'depth' => 3),
            'q' => array('tone' => 'emotional', 'structure' => 'hero_journey', 'sensory' => 3, 'story' => 3, 'info' => 2),
            'c' => array('commercial' => 'none', 'experience' => 'drama'),
        ),
        'seo_starter' => array(
            'name' => 'SEO Starter',
            'icon' => '🔍',
            'description' => '検索エンジン最適化重視',
            'h' => array('persona' => 'general', 'purpose' => array('sightseeing', 'gourmet'), 'depth' => 2),
            'q' => array('tone' => 'journalistic', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
            'c' => array('commercial' => 'seo', 'experience' => 'record'),
        ),
        'anniversary' => array(
            'name' => 'Anniversary',
            'icon' => '💝',
            'description' => '記念日・特別な日向け',
            'h' => array('persona' => 'couple', 'purpose' => array('anniversary'), 'depth' => 3),
            'q' => array('tone' => 'luxury', 'structure' => 'hero_journey', 'sensory' => 3, 'story' => 3, 'info' => 2),
            'c' => array('commercial' => 'conversion', 'experience' => 'immersive'),
        ),
        'premium' => array(
            'name' => 'Premium',
            'icon' => '👑',
            'description' => '高級志向・ラグジュアリー',
            'h' => array('persona' => 'luxury', 'purpose' => array('healing', 'anniversary'), 'depth' => 3),
            'q' => array('tone' => 'luxury', 'structure' => 'five_sense', 'sensory' => 3, 'story' => 2, 'info' => 3),
            'c' => array('commercial' => 'conversion', 'experience' => 'immersive'),
        ),
        'family_comfort' => array(
            'name' => 'Family Comfort',
            'icon' => '👨‍👩‍👧‍👦',
            'description' => 'ファミリー向け実用重視',
            'h' => array('persona' => 'family', 'purpose' => array('family', 'sightseeing'), 'depth' => 2),
            'q' => array('tone' => 'casual', 'structure' => 'review', 'sensory' => 2, 'story' => 1, 'info' => 3),
            'c' => array('commercial' => 'seo', 'experience' => 'record'),
        ),
        'workation' => array(
            'name' => 'Workation Pro',
            'icon' => '💼',
            'description' => 'ワーケーション特化',
            'h' => array('persona' => 'workation', 'purpose' => array('workation'), 'depth' => 2),
            'q' => array('tone' => 'journalistic', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
            'c' => array('commercial' => 'seo', 'experience' => 'record'),
        ),
        'fivesense' => array(
            'name' => 'FiveSense Immersion',
            'icon' => '👁️',
            'description' => '五感没入型体験',
            'h' => array('persona' => 'solo', 'purpose' => array('healing', 'onsen'), 'depth' => 3),
            'q' => array('tone' => 'cinematic', 'structure' => 'five_sense', 'sensory' => 3, 'story' => 3, 'info' => 2),
            'c' => array('commercial' => 'none', 'experience' => 'immersive'),
        ),
        'cost_performance' => array(
            'name' => 'CostPerformance',
            'icon' => '💰',
            'description' => 'コスパ重視',
            'h' => array('persona' => 'budget', 'purpose' => array('budget', 'sightseeing'), 'depth' => 1),
            'q' => array('tone' => 'casual', 'structure' => 'review', 'sensory' => 1, 'story' => 1, 'info' => 3),
            'c' => array('commercial' => 'conversion', 'experience' => 'record'),
        ),
    );

    /**
     * ペルソナ定義
     */
    const PERSONAS = array(
        'general' => array('name' => '一般', 'emoji' => '👤', 'description' => '幅広い読者層'),
        'solo' => array('name' => '一人旅', 'emoji' => '🚶', 'description' => 'ソロトラベラー'),
        'couple' => array('name' => 'カップル・夫婦', 'emoji' => '💑', 'description' => '二人旅'),
        'family' => array('name' => 'ファミリー', 'emoji' => '👨‍👩‍👧‍👦', 'description' => '子連れ家族'),
        'senior' => array('name' => 'シニア', 'emoji' => '👴👵', 'description' => 'シニア世代'),
        'workation' => array('name' => 'ワーケーション', 'emoji' => '💼', 'description' => 'リモートワーク'),
        'luxury' => array('name' => 'ラグジュアリー', 'emoji' => '👑', 'description' => '高級志向'),
        'budget' => array('name' => '節約志向', 'emoji' => '💰', 'description' => 'コスパ重視'),
    );

    /**
     * 旅の目的
     */
    const TRAVEL_PURPOSES = array(
        'sightseeing' => array('name' => '観光', 'emoji' => '🗼'),
        'onsen' => array('name' => '温泉', 'emoji' => '♨️'),
        'gourmet' => array('name' => 'グルメ', 'emoji' => '🍽️'),
        'anniversary' => array('name' => '記念日', 'emoji' => '🎂'),
        'workation' => array('name' => 'ワーケーション', 'emoji' => '💼'),
        'healing' => array('name' => '癒し', 'emoji' => '🧘'),
        'family' => array('name' => '家族旅行', 'emoji' => '👨‍👩‍👧'),
        'budget' => array('name' => '節約旅', 'emoji' => '💰'),
    );

    /**
     * トーン設定
     */
    const TONES = array(
        'casual' => array('name' => 'カジュアル', 'emoji' => '😊'),
        'luxury' => array('name' => 'ラグジュアリー', 'emoji' => '👑'),
        'emotional' => array('name' => 'エモーショナル', 'emoji' => '💖'),
        'cinematic' => array('name' => '映画的', 'emoji' => '🎬'),
        'journalistic' => array('name' => '報道的', 'emoji' => '📰'),
    );

    /**
     * 構造設定
     */
    const STRUCTURES = array(
        'timeline' => array('name' => '時系列', 'emoji' => '⏰', 'mapping' => 'story'),
        'hero_journey' => array('name' => '物語構造', 'emoji' => '🗺️', 'mapping' => 'emotional'),
        'five_sense' => array('name' => '五感描写', 'emoji' => '👁️', 'mapping' => 'five_sense'),
        'dialogue' => array('name' => '対話形式', 'emoji' => '💬', 'mapping' => 'story'),
        'review' => array('name' => 'レビュー', 'emoji' => '⭐', 'mapping' => 'review'),
    );

    /**
     * OTA設定
     */
    const OTA_SITES = array(
        'rakuten' => array(
            'name' => '楽天トラベル',
            'priority' => '◎',
            'affiliate' => 'moshimo',
            'moshimo_id' => '5247247',
            'url_pattern' => 'https://travel.rakuten.co.jp/',
        ),
        'jalan' => array(
            'name' => 'じゃらん',
            'priority' => '◯',
            'affiliate' => 'direct',
            'url_pattern' => 'https://www.jalan.net/',
        ),
        'ikyu' => array(
            'name' => '一休.com',
            'priority' => '◯',
            'affiliate' => 'direct',
            'url_pattern' => 'https://www.ikyu.com/',
        ),
        'booking' => array(
            'name' => 'Booking.com',
            'priority' => '△',
            'affiliate' => 'direct',
            'url_pattern' => 'https://www.booking.com/',
        ),
        'yahoo' => array(
            'name' => 'Yahoo!トラベル',
            'priority' => '△',
            'affiliate' => 'direct',
            'url_pattern' => 'https://travel.yahoo.co.jp/',
        ),
    );

    /**
     * ソース信頼性スコア
     */
    const SOURCE_TRUST_SCORES = array(
        'official' => 0.95,
        'rakuten' => 0.90,
        'ikyu' => 0.90,
        'jalan' => 0.85,
        'jtb' => 0.85,
        'booking' => 0.80,
        'rurubu' => 0.80,
        'yahoo' => 0.75,
        'expedia' => 0.75,
        'tripadvisor' => 0.70,
        'google' => 0.65,
        'other' => 0.50,
    );

    /**
     * SEO設定
     */
    const SEO_CONFIG = array(
        'meta_description_length' => 80,
        'min_h2_count' => 6,
        'keyphrase_density' => 0.015,
        'min_word_count' => 2000,
        'max_word_count' => 4000,
    );

    /**
     * HQCスコア閾値
     */
    const HQC_SCORE_THRESHOLDS = array(
        'excellent' => 0.85,
        'good' => 0.70,
        'fair' => 0.50,
        'poor' => 0.0,
    );

    /**
     * デフォルト設定取得
     */
    public static function get_defaults() {
        return array(
            'chatgpt_api_key' => '',
            'google_cse_api_key' => '',
            'google_cse_id' => '',
            'rakuten_app_id' => '',
            'default_ai_model' => 'gpt-4o-mini',
            'default_post_status' => 'draft',
            'auto_generate_enabled' => false,
            'auto_generate_interval' => 'hrs_hourly',
            'auto_generate_batch_size' => 1,
            'hqc_current_preset' => 'starter',
        );
    }

    /**
     * オプション取得
     */
    public static function get_option($key, $default = null) {
        $defaults = self::get_defaults();
        $default_value = $default ?? ($defaults[$key] ?? null);
        return get_option(self::OPTION_PREFIX . $key, $default_value);
    }

    /**
     * オプション保存
     */
    public static function update_option($key, $value) {
        return update_option(self::OPTION_PREFIX . $key, $value);
    }

    /**
     * メタ取得
     */
    public static function get_meta($post_id, $key, $single = true) {
        return get_post_meta($post_id, self::META_PREFIX . $key, $single);
    }

    /**
     * メタ保存
     */
    public static function update_meta($post_id, $key, $value) {
        return update_post_meta($post_id, self::META_PREFIX . $key, $value);
    }

    /**
     * プリセット取得
     */
    public static function get_preset($preset_id) {
        return self::HQC_PRESETS[$preset_id] ?? self::HQC_PRESETS['starter'];
    }

    /**
     * AIモデル情報取得
     */
    public static function get_ai_model_info($provider, $model) {
        return self::AI_MODELS[$provider][$model] ?? null;
    }

    /**
     * ソース信頼性スコア取得
     */
    public static function get_source_trust_score($source_name) {
        $source_lower = strtolower($source_name);
        
        foreach (self::SOURCE_TRUST_SCORES as $key => $score) {
            if (strpos($source_lower, $key) !== false) {
                return $score;
            }
        }
        
        return self::SOURCE_TRUST_SCORES['other'];
    }

    /**
     * HQCスコアラベル取得
     */
    public static function get_hqc_score_label($score) {
        if ($score >= self::HQC_SCORE_THRESHOLDS['excellent']) {
            return 'excellent';
        } elseif ($score >= self::HQC_SCORE_THRESHOLDS['good']) {
            return 'good';
        } elseif ($score >= self::HQC_SCORE_THRESHOLDS['fair']) {
            return 'fair';
        }
        return 'poor';
    }
}