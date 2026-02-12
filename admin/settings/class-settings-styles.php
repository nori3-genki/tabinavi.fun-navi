<?php
/**
 * Settings Styles - CSSスタイル管理
 * 
 * @package Hotel_Review_System
 * @subpackage Settings
 * @version 6.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Settings_Styles {

    public static function render() {
        ?>
        <style>
        .hrs-settings-wrap { max-width: 1200px; margin: 20px 0; }
        
        .hrs-settings-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 20px; }
        .hrs-settings-header h1 { color: white; font-size: 28px; margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px; }
        .hrs-subtitle { margin: 0; opacity: 0.95; font-size: 14px; }
        
        .hrs-tab-nav { background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 0; margin-bottom: 20px; display: flex; overflow: hidden; }
        .hrs-tab-link { flex: 1; padding: 16px 20px; text-decoration: none; color: #2c3338; border-right: 1px solid #c3c4c7; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .hrs-tab-link:last-child { border-right: none; }
        .hrs-tab-link:hover { background: #f6f7f7; }
        .hrs-tab-link.active { background: #2271b1; color: white; }
        
        .hrs-settings-card { background: white; border: 1px solid #c3c4c7; border-radius: 8px; margin-bottom: 20px; }
        .hrs-card-header { padding: 20px 24px; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center; }
        .hrs-card-header h2 { margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        
        .hrs-status-badge { padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .hrs-status-badge.configured { background: #d4edda; color: #155724; }
        .hrs-status-badge.not-configured { background: #fff3cd; color: #856404; }
        
        .hrs-card-body { padding: 24px; }
        
        .hrs-info-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 16px; margin-bottom: 24px; display: flex; gap: 12px; }
        .hrs-info-box .dashicons { color: #2271b1; font-size: 24px; width: 24px; height: 24px; flex-shrink: 0; }
        .hrs-info-box strong { display: block; margin-bottom: 4px; }
        .hrs-info-box p { margin: 0; font-size: 13px; line-height: 1.6; }
        .hrs-info-box a { color: #2271b1; text-decoration: underline; }
        
        .required { color: #d63638; }
        
        .hrs-test-section { background: #f9f9f9; padding: 20px; border-radius: 4px; margin-top: 24px; }
        .hrs-test-section h3 { margin-top: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        
        #api-test-result, #google-test-result, #rakuten-test-result { margin-top: 12px; padding: 12px; border-radius: 4px; display: none; }
        #api-test-result.success, #google-test-result.success, #rakuten-test-result.success { display: block; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        #api-test-result.error, #google-test-result.error, #rakuten-test-result.error { display: block; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        
        .hrs-ota-tiers { display: grid; gap: 20px; margin-top: 20px; }
        .hrs-tier-section { background: #f9f9f9; padding: 20px; border-radius: 4px; }
        .hrs-tier-section h4 { margin-top: 0; display: flex; align-items: center; gap: 10px; }
        
        .tier-badge { display: inline-block; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; font-weight: bold; }
        .tier-badge.tier-1 { background: #d4edda; color: #155724; }
        .tier-badge.tier-2 { background: #d1ecf1; color: #0c5460; }
        .tier-badge.tier-3 { background: #fff3cd; color: #856404; }
        
        .hrs-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
        .hrs-checkbox-label { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: white; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer; transition: all 0.2s; }
        .hrs-checkbox-label:hover { border-color: #2271b1; background: #f6f7f7; }
        
        .hrs-preset-gallery { margin-top: 30px; }
        .hrs-preset-gallery h3 { margin-top: 0; display: flex; align-items: center; gap: 8px; }
        .hrs-preset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; margin-top: 16px; }
        .hrs-preset-card { background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; text-align: center; transition: all 0.2s; }
        .hrs-preset-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .preset-icon { font-size: 48px; margin-bottom: 12px; }
        .hrs-preset-card h4 { margin: 0 0 8px 0; color: #1d2327; }
        .hrs-preset-card p { margin: 0; font-size: 13px; color: #50575e; }
        
        .hrs-form-footer { background: white; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px 24px; text-align: right; }
        </style>
        <?php
    }
}