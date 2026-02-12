<?php
/**
 * HQC A/Bテスト - スタイル
 * 
 * @package HRS
 * @subpackage Learning
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_AB_Test_Styles {

    /**
     * インラインCSS取得
     */
    public static function get_inline_styles() {
        return <<<'CSS'
.hrs-ab-wrap { max-width: 1400px; }

/* 統計カード */
.ab-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin: 20px 0;
}
.ab-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}
.ab-stat-card .stat-icon { font-size: 28px; margin-bottom: 8px; }
.ab-stat-card .stat-value { font-size: 36px; font-weight: bold; color: #1d2327; }
.ab-stat-card .stat-label { color: #666; font-size: 14px; }
.ab-stat-card.variant-a { border-left: 4px solid #2196F3; }
.ab-stat-card.variant-b { border-left: 4px solid #9C27B0; }

/* タブ */
.ab-tabs {
    display: flex;
    gap: 8px;
    margin: 20px 0;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0;
}
.ab-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}
.ab-tab:hover { color: #2271b1; }
.ab-tab.active {
    color: #2271b1;
    border-bottom-color: #2271b1;
}
.ab-tab-panel { display: none; }
.ab-tab-panel.active { display: block; }

/* フォームカード */
.ab-form-card, .ab-list-card {
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}
.ab-form-card h2, .ab-list-card h2 {
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* フォーム */
.ab-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 16px;
}
.ab-form-group { flex: 1; }
.ab-form-group.full-width { flex: 100%; }
.ab-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 13px;
}
.ab-form-group label .required { color: #d63638; }
.ab-form-group input,
.ab-form-group select,
.ab-form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    font-size: 14px;
}
.ab-form-group input:focus,
.ab-form-group select:focus,
.ab-form-group textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 2px rgba(34,113,177,0.1);
    outline: none;
}

/* バリアント */
.ab-variants-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 20px 0;
}
.ab-variant {
    padding: 20px;
    border-radius: 8px;
}
.ab-variant h3 {
    margin: 0 0 16px 0;
    font-size: 16px;
}
.ab-variant.variant-a {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
}
.ab-variant.variant-a h3 { color: #1565C0; }
.ab-variant.variant-b {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
}
.ab-variant.variant-b h3 { color: #7B1FA2; }

/* アクション */
.ab-form-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
    text-align: center;
}
.ab-form-actions .button-large {
    padding: 8px 32px;
    height: auto;
    font-size: 14px;
}

/* テーブル */
.ab-test-table {
    width: 100%;
    border-collapse: collapse;
}
.ab-test-table th,
.ab-test-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}
.ab-test-table th {
    background: #f9f9f9;
    font-weight: 600;
    font-size: 13px;
}
.ab-test-table tr:hover { background: #f9f9f9; }
.ab-test-table tr.row-failed { background: #fff8f8; }
.ab-test-table tr.row-failed:hover { background: #fff0f0; }
.ab-test-table .actions {
    display: flex;
    gap: 8px;
}

/* ステータス */
.status-pending { color: #666; }
.status-running { color: #FF9800; font-weight: 600; }
.status-completed { color: #4CAF50; font-weight: 600; }
.status-failed { color: #d63638; font-weight: 600; }

/* スコア失敗 */
.score-failed { color: #999; }

/* 勝者バッジ */
.winner-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.winner-a { background: #e3f2fd; color: #1565C0; }
.winner-b { background: #f3e5f5; color: #7B1FA2; }
.winner-tie { background: #f5f5f5; color: #666; }
.winner-failed { background: #fce4e4; color: #d63638; }

/* 空の状態 */
.ab-empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}
.ab-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ccc;
}

/* レスポンシブ */
@media (max-width: 1024px) {
    .ab-stats-grid { grid-template-columns: repeat(3, 1fr); }
    .ab-variants-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .ab-stats-grid { grid-template-columns: 1fr; }
    .ab-tabs { flex-direction: column; }
}
CSS;
    }
}