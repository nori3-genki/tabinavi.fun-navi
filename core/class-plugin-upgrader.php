<?php
/**
 * HRS Plugin Upgrader
 * バージョンアップグレード処理
 * 
 * @package Hotel_Review_System
 * @version 7.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Plugin_Upgrader {
    
    /**
     * アップグレードチェック・実行
     */
    public static function maybe_upgrade() {
        $installed_version = get_option('hrs_version', '0.0.0');
        
        if (version_compare($installed_version, HRS_VERSION, '<')) {
            self::run_upgrade($installed_version, HRS_VERSION);
            update_option('hrs_version', HRS_VERSION);
        }
    }
    
    /**
     * アップグレード実行
     * 
     * @param string $old_version 旧バージョン
     * @param string $new_version 新バージョン
     */
    private static function run_upgrade($old_version, $new_version) {
        hrs_log(sprintf('Upgrading from %s to %s', $old_version, $new_version), 'info');
        
        // v6.1.0
        if (version_compare($old_version, '6.1.0', '<')) {
            self::upgrade_to_6_1_0();
        }
        
        // v6.4.0
        if (version_compare($old_version, '6.4.0', '<')) {
            self::upgrade_to_6_4_0();
        }
        
        // v6.5.0
        if (version_compare($old_version, '6.5.0', '<')) {
            self::upgrade_to_6_5_0();
        }
        
        // v6.6.0
        if (version_compare($old_version, '6.6.0', '<')) {
            self::upgrade_to_6_6_0();
        }
        
        // v6.7.0
        if (version_compare($old_version, '6.7.0', '<')) {
            self::upgrade_to_6_7_0();
        }
        
        // v6.8.0
        if (version_compare($old_version, '6.8.0', '<')) {
            self::upgrade_to_6_8_0();
        }
        
        // v6.8.1
        if (version_compare($old_version, '6.8.1', '<')) {
            self::upgrade_to_6_8_1();
        }
        
        // v6.9.0 - HQC学習システム
        if (version_compare($old_version, '6.9.0', '<')) {
            self::upgrade_to_6_9_0();
        }
        
        // v7.0.0 - パフォーマンスダッシュボード
        if (version_compare($old_version, '7.0.0', '<')) {
            self::upgrade_to_7_0_0();
        }
        
        // v7.1.0 - ファイル分割
        if (version_compare($old_version, '7.1.0', '<')) {
            self::upgrade_to_7_1_0();
        }
    }
    
    /**
     * v6.1.0 アップグレード
     */
    private static function upgrade_to_6_1_0() {
        global $wpdb;
        
        $prompt_options = array(
            'hrs_prompt_style',
            'hrs_prompt_persona',
            'hrs_prompt_tone',
            'hrs_prompt_policy',
            'hrs_prompt_purpose',
            'hrs_google_cse_api_key',
            'hrs_google_cse_id',
            'hrs_chatgpt_api_key',
            'hrs_rakuten_app_id',
            'hrs_rakuten_affiliate_id',
        );
        
        foreach ($prompt_options as $option) {
            if (get_option($option) !== false) {
                $wpdb->update(
                    $wpdb->options,
                    array('autoload' => 'no'),
                    array('option_name' => $option),
                    array('%s'),
                    array('%s')
                );
            }
        }
        
        hrs_log('upgrade_to_6_1_0 completed', 'info');
    }
    
    /**
     * v6.4.0 アップグレード
     */
    private static function upgrade_to_6_4_0() {
        hrs_log('upgrade_to_6_4_0 completed', 'info');
    }
    
    /**
     * v6.5.0 アップグレード
     */
    private static function upgrade_to_6_5_0() {
        hrs_log('upgrade_to_6_5_0 completed', 'info');
    }
    
    /**
     * v6.6.0 アップグレード
     */
    private static function upgrade_to_6_6_0() {
        hrs_log('upgrade_to_6_6_0 completed', 'info');
    }
    
    /**
     * v6.7.0 アップグレード
     */
    private static function upgrade_to_6_7_0() {
        flush_rewrite_rules();
        hrs_log('upgrade_to_6_7_0 completed - rewrite rules flushed', 'info');
    }
    
    /**
     * v6.8.0 アップグレード
     */
    private static function upgrade_to_6_8_0() {
        hrs_log('upgrade_to_6_8_0 completed - file paths corrected', 'info');
    }
    
    /**
     * v6.8.1 アップグレード
     */
    private static function upgrade_to_6_8_1() {
        hrs_log('upgrade_to_6_8_1 completed - LinkSwitch init fixed', 'info');
    }
    
    /**
     * v6.9.0 アップグレード - HQC学習システム
     */
    private static function upgrade_to_6_9_0() {
        // HQC学習システムのDBテーブル作成
        if (class_exists('HRS_HQC_DB_Installer')) {
            HRS_HQC_DB_Installer::install();
        }
        
        // 学習システムオプション初期化
        if (get_option('hrs_hqc_learning_enabled') === false) {
            update_option('hrs_hqc_learning_enabled', true);
        }
        
        hrs_log('upgrade_to_6_9_0 completed - HQC Learning System installed', 'info');
    }
    
    /**
     * v7.0.0 アップグレード - パフォーマンスダッシュボード
     */
    private static function upgrade_to_7_0_0() {
        // パフォーマンスDBテーブル作成
        if (class_exists('HRS_Performance_Tracker')) {
            try {
                $tracker = new HRS_Performance_Tracker();
                $tracker->create_table();
            } catch (Throwable $t) {
                error_log('[5DRB] Performance DB upgrade error: ' . $t->getMessage());
            }
        }
        
        hrs_log('upgrade_to_7_0_0 completed - Performance Dashboard installed', 'info');
    }
    
    /**
     * v7.1.0 アップグレード - ファイル分割
     */
    private static function upgrade_to_7_1_0() {
        hrs_log('upgrade_to_7_1_0 completed - File structure refactored', 'info');
    }
}