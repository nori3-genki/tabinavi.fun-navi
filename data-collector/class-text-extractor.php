<?php
if (!defined('ABSPATH')) exit;

/**
 * テキスト抽出クラス
 * HTMLからテキストや特徴を抽出
 */
class HRS_Text_Extractor {
    
    public function __construct() {
        // 初期化
    }
    
    /**
     * HTMLからテキストを抽出
     */
    public function extract($html) {
        if (empty($html)) {
            return '';
        }
        return trim(strip_tags($html));
    }
    
    /**
     * テキストから特徴を抽出
     */
    public function extract_features($text) {
        return array();
    }
}