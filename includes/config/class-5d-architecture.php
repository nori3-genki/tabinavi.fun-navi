<?php
/**
 * 5D アーキテクチャ定義クラス（HQC対応版）
 * 
 * 5D Review Builder のアーキテクチャ定義
 * HQC Framework（H-Layer, Q-Layer, C-Layer）統合
 * 
 * @package HRS
 * @version 4.3.0-HQC
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_5D_Architecture {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * アーキテクチャバージョン
     */
    const ARCHITECTURE_VERSION = '2.0.0-HQC';

    /**
     * 5D次元定義
     */
    private $dimensions = array();

    /**
     * HQCレイヤー定義
     */
    private $hqc_layers = array();

    /**
     * コンポーネント依存関係
     */
    private $dependencies = array();

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_dimensions();
        $this->init_hqc_layers();
        $this->init_dependencies();
    }

    /**
     * 5D次元の初期化
     */
    private function init_dimensions() {
        $this->dimensions = array(
            'D1' => array(
                'name' => 'Display Style',
                'description' => '表示スタイル・見せ方',
                'mapped_to' => 'q.structure',
                'options' => array(
                    'timeline' => '時系列',
                    'hero_journey' => '物語構造',
                    'five_sense' => '五感描写',
                    'dialogue' => '対話形式',
                    'review' => 'レビュー形式',
                ),
            ),
            'D2' => array(
                'name' => 'Reader Persona',
                'description' => 'ターゲット読者像',
                'mapped_to' => 'h.persona',
                'options' => array(
                    'general' => '一般旅行者',
                    'solo' => '一人旅',
                    'couple' => 'カップル・夫婦',
                    'family' => 'ファミリー',
                    'senior' => 'シニア',
                    'workation' => 'ワーケーション',
                    'luxury' => 'ラグジュアリー',
                    'budget' => '節約志向',
                ),
            ),
            'D3' => array(
                'name' => 'Article Tone',
                'description' => '文章のトーン・雰囲気',
                'mapped_to' => 'q.tone',
                'options' => array(
                    'casual' => 'カジュアル',
                    'luxury' => 'ラグジュアリー',
                    'emotional' => 'エモーショナル',
                    'cinematic' => '映画的',
                    'journalistic' => '報道的',
                ),
            ),
            'D4' => array(
                'name' => 'Generation Policy',
                'description' => 'コンテンツ生成方針',
                'mapped_to' => 'c.commercial',
                'options' => array(
                    'none' => 'コンテンツ重視',
                    'seo' => 'SEO最適化',
                    'conversion' => 'コンバージョン重視',
                ),
            ),
            'D5' => array(
                'name' => 'AI Model',
                'description' => '使用AIモデル',
                'mapped_to' => 'ai_model',
                'options' => array(
                    'gpt-4o-mini' => 'GPT-4o mini（推奨）',
                    'gpt-4o' => 'GPT-4o',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'claude-3-5-sonnet' => 'Claude 3.5 Sonnet',
                    'claude-3-opus' => 'Claude 3 Opus',
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                ),
            ),
        );
    }

    /**
     * HQCレイヤーの初期化
     */
    private function init_hqc_layers() {
        $this->hqc_layers = array(
            'h' => array(
                'name' => 'Human Layer',
                'description' => '読者ペルソナ・旅の目的',
                'components' => array(
                    'persona' => array(
                        'type' => 'select',
                        'label' => 'ペルソナ',
                        'default' => 'general',
                    ),
                    'purpose' => array(
                        'type' => 'multiselect',
                        'label' => '旅の目的',
                        'default' => array('sightseeing'),
                        'options' => array(
                            'sightseeing' => '観光',
                            'onsen' => '温泉',
                            'gourmet' => 'グルメ',
                            'anniversary' => '記念日',
                            'workation' => 'ワーケーション',
                            'healing' => '癒し',
                            'family' => '家族旅行',
                            'budget' => '節約旅',
                        ),
                    ),
                    'depth' => array(
                        'type' => 'range',
                        'label' => '体験深度',
                        'min' => 1,
                        'max' => 3,
                        'default' => 2,
                    ),
                ),
            ),
            'q' => array(
                'name' => 'Quality Layer',
                'description' => '品質・スタイル設定',
                'components' => array(
                    'tone' => array(
                        'type' => 'select',
                        'label' => 'トーン',
                        'default' => 'casual',
                    ),
                    'structure' => array(
                        'type' => 'select',
                        'label' => '構造',
                        'default' => 'timeline',
                    ),
                    'sensory' => array(
                        'type' => 'range',
                        'label' => '五感深度',
                        'min' => 1,
                        'max' => 3,
                        'default' => 2,
                    ),
                    'story' => array(
                        'type' => 'range',
                        'label' => '物語強度',
                        'min' => 1,
                        'max' => 3,
                        'default' => 2,
                    ),
                    'info' => array(
                        'type' => 'range',
                        'label' => '情報量',
                        'min' => 1,
                        'max' => 3,
                        'default' => 2,
                    ),
                ),
            ),
            'c' => array(
                'name' => 'Content Layer',
                'description' => 'コンテンツ方針',
                'components' => array(
                    'commercial' => array(
                        'type' => 'select',
                        'label' => '商業方針',
                        'default' => 'seo',
                        'options' => array(
                            'none' => 'コンテンツ重視',
                            'seo' => 'SEO最適化',
                            'conversion' => 'コンバージョン重視',
                        ),
                    ),
                    'experience' => array(
                        'type' => 'select',
                        'label' => '体験タイプ',
                        'default' => 'record',
                        'options' => array(
                            'record' => '記録型',
                            'immersive' => '没入型',
                            'drama' => 'ドラマ型',
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * コンポーネント依存関係の初期化
     */
    private function init_dependencies() {
        $this->dependencies = array(
            'HRS_5D_Review_Builder' => array(
                'description' => 'メインクラス',
                'depends_on' => array(
                    'HRS_Data_Collector',
                    'HRS_Prompt_Engine',
                    'HRS_Auto_Generator',
                ),
            ),
            'HRS_Data_Collector' => array(
                'description' => 'データ収集',
                'depends_on' => array(),
            ),
            'HRS_Prompt_Engine' => array(
                'description' => 'プロンプト生成',
                'depends_on' => array(
                    'HRS_5D_Config',
                ),
            ),
            'HRS_Auto_Generator' => array(
                'description' => '自動生成',
                'depends_on' => array(
                    'HRS_5D_Review_Builder',
                ),
            ),
            'HRS_Yoast_SEO_Optimizer' => array(
                'description' => 'Yoast SEO最適化',
                'depends_on' => array(),
            ),
            'HRS_Heading_Optimizer' => array(
                'description' => '見出し最適化',
                'depends_on' => array(),
            ),
            'HRS_Keyphrase_Injector' => array(
                'description' => 'キーフレーズ挿入',
                'depends_on' => array(),
            ),
            'HRS_Internal_Link_Generator' => array(
                'description' => '内部リンク生成',
                'depends_on' => array(
                    'HRS_OTA_Selector',
                    'HRS_OTA_Persona_Mapper',
                ),
            ),
            'HRS_Rakuten_Image_Fetcher' => array(
                'description' => '楽天画像取得',
                'depends_on' => array(),
            ),
            'HRS_ChatGPT_Optimizer' => array(
                'description' => 'ChatGPT最適化',
                'depends_on' => array(
                    'HRS_Base_Optimizer',
                ),
            ),
            'HRS_Claude_Optimizer' => array(
                'description' => 'Claude最適化',
                'depends_on' => array(
                    'HRS_Base_Optimizer',
                ),
            ),
            'HRS_Gemini_Optimizer' => array(
                'description' => 'Gemini最適化',
                'depends_on' => array(
                    'HRS_Base_Optimizer',
                ),
            ),
            'HRS_OTA_Selector' => array(
                'description' => 'OTA選択',
                'depends_on' => array(),
            ),
            'HRS_OTA_Persona_Mapper' => array(
                'description' => 'OTAペルソナマッピング',
                'depends_on' => array(
                    'HRS_OTA_Selector',
                ),
            ),
        );
    }

    /**
     * 5DからHQCへのマッピング
     * 
     * @param array $d5_settings 5D設定
     * @return array HQC設定
     */
    public function map_5d_to_hqc($d5_settings) {
        $hqc = array(
            'h' => array(
                'persona' => $d5_settings['D2'] ?? 'general',
                'purpose' => $d5_settings['purposes'] ?? array('sightseeing'),
                'depth' => $d5_settings['depth'] ?? 2,
            ),
            'q' => array(
                'tone' => $d5_settings['D3'] ?? 'casual',
                'structure' => $d5_settings['D1'] ?? 'timeline',
                'sensory' => $d5_settings['sensory'] ?? 2,
                'story' => $d5_settings['story'] ?? 2,
                'info' => $d5_settings['info'] ?? 2,
            ),
            'c' => array(
                'commercial' => $d5_settings['D4'] ?? 'seo',
                'experience' => $d5_settings['experience'] ?? 'record',
            ),
        );
        
        return $hqc;
    }

    /**
     * HQCから5Dへのマッピング
     * 
     * @param array $hqc_settings HQC設定
     * @return array 5D設定
     */
    public function map_hqc_to_5d($hqc_settings) {
        return array(
            'D1' => $hqc_settings['q']['structure'] ?? 'timeline',
            'D2' => $hqc_settings['h']['persona'] ?? 'general',
            'D3' => $hqc_settings['q']['tone'] ?? 'casual',
            'D4' => $hqc_settings['c']['commercial'] ?? 'seo',
            'D5' => 'gpt-4o-mini',
            'purposes' => $hqc_settings['h']['purpose'] ?? array('sightseeing'),
            'depth' => $hqc_settings['h']['depth'] ?? 2,
            'sensory' => $hqc_settings['q']['sensory'] ?? 2,
            'story' => $hqc_settings['q']['story'] ?? 2,
            'info' => $hqc_settings['q']['info'] ?? 2,
            'experience' => $hqc_settings['c']['experience'] ?? 'record',
        );
    }

    /**
     * 5D次元定義を取得
     */
    public function get_dimensions() {
        return $this->dimensions;
    }

    /**
     * HQCレイヤー定義を取得
     */
    public function get_hqc_layers() {
        return $this->hqc_layers;
    }

    /**
     * コンポーネント依存関係を取得
     */
    public function get_dependencies() {
        return $this->dependencies;
    }

    /**
     * 特定次元の情報を取得
     * 
     * @param string $dimension D1-D5
     * @return array|null
     */
    public function get_dimension($dimension) {
        return $this->dimensions[$dimension] ?? null;
    }

    /**
     * 特定レイヤーの情報を取得
     * 
     * @param string $layer h, q, c
     * @return array|null
     */
    public function get_layer($layer) {
        return $this->hqc_layers[$layer] ?? null;
    }

    /**
     * 組み合わせ総数を計算
     * 
     * @return int
     */
    public function get_total_combinations() {
        $total = 1;
        foreach ($this->dimensions as $dimension) {
            $total *= count($dimension['options']);
        }
        return $total;
    }

    /**
     * コンポーネント依存関係を検証
     * 
     * @return array 検証結果
     */
    public function validate_dependencies() {
        $results = array();
        
        foreach ($this->dependencies as $class => $info) {
            $exists = class_exists($class);
            $deps_ok = true;
            $missing_deps = array();
            
            foreach ($info['depends_on'] as $dep) {
                if (!class_exists($dep)) {
                    $deps_ok = false;
                    $missing_deps[] = $dep;
                }
            }
            
            $results[$class] = array(
                'exists' => $exists,
                'description' => $info['description'],
                'dependencies_ok' => $deps_ok,
                'missing_dependencies' => $missing_deps,
                'status' => ($exists && $deps_ok) ? 'ok' : 'error',
            );
        }
        
        return $results;
    }

    /**
     * アーキテクチャ情報を取得
     * 
     * @return array
     */
    public function get_architecture_info() {
        return array(
            'version' => self::ARCHITECTURE_VERSION,
            'dimensions' => count($this->dimensions),
            'hqc_layers' => count($this->hqc_layers),
            'components' => count($this->dependencies),
            'total_combinations' => $this->get_total_combinations(),
        );
    }

    /**
     * デバッグ情報を出力
     * 
     * @return string
     */
    public function debug() {
        $info = $this->get_architecture_info();
        $validation = $this->validate_dependencies();
        
        $output = "=== 5D Architecture Debug ===\n";
        $output .= "Version: {$info['version']}\n";
        $output .= "Dimensions: {$info['dimensions']}\n";
        $output .= "HQC Layers: {$info['hqc_layers']}\n";
        $output .= "Components: {$info['components']}\n";
        $output .= "Total Combinations: {$info['total_combinations']}\n\n";
        
        $output .= "=== Component Status ===\n";
        foreach ($validation as $class => $status) {
            $icon = $status['status'] === 'ok' ? '✅' : '❌';
            $output .= "{$icon} {$class}: {$status['description']}\n";
            if (!empty($status['missing_dependencies'])) {
                $output .= "   Missing: " . implode(', ', $status['missing_dependencies']) . "\n";
            }
        }
        
        return $output;
    }
}

/**
 * グローバルアクセス関数
 */
function hrs_architecture() {
    return HRS_5D_Architecture::get_instance();
}