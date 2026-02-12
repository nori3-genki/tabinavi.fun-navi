<?php
/**
 * HQC Styles - CSSスタイル管理クラス
 * 
 * @package Hotel_Review_System
 * @subpackage HQC
 * @version 7.1.0
 * 
 * 変更履歴:
 * - 7.1.0: コンパクトレイアウトに修正、2カラム維持
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Hqc_Styles {

    public static function get_inline_styles() {
        return '
/* HQC Generator v7.1.0 - Compact */
.hrs-hqc-wrap { max-width:1400px; margin:20px auto; padding:0 20px; }

/* ヘッダー */
.hrs-hqc-header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; padding:24px 32px; border-radius:12px; margin-bottom:24px; }
.hrs-hqc-header h1 { margin:0 0 6px; font-size:24px; font-weight:600; display:flex; align-items:center; gap:10px; color:#fff; }
.hrs-hqc-header p { margin:0; opacity:0.9; font-size:14px; }

/* メインレイアウト - 2カラム */
.hrs-hqc-main { display:grid; grid-template-columns:1fr 380px; gap:24px; }

/* カード共通 */
.hrs-hqc-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:16px; }
.hrs-hqc-card h3 { margin:0 0 16px; font-size:15px; color:#1e293b; display:flex; align-items:center; gap:8px; padding-bottom:10px; border-bottom:1px solid #f1f5f9; }

/* ホテル入力 */
.hrs-hotel-card { border-left:4px solid #667eea; }
.hrs-form-row { margin-bottom:14px; }
.hrs-form-row label { display:flex; align-items:center; gap:6px; margin-bottom:6px; font-weight:600; font-size:13px; color:#374151; }
.hrs-form-row .required { color:#ef4444; }
.hrs-input { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px; transition:all 0.2s; }
.hrs-input:focus { border-color:#667eea; outline:none; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
.hrs-button-row { display:flex; gap:10px; margin-top:16px; }
.hrs-button { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; border:none; }
.hrs-button-primary { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; }
.hrs-button-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,0.4); }
.hrs-button-secondary { background:#fff; color:#667eea; border:2px solid #667eea; }
.hrs-button-secondary:hover { background:#f5f3ff; }
.hrs-button-success { background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:#fff; width:100%; justify-content:center; }

/* レイヤーセクション */
.hrs-layer-section { background:#f8fafc; border-radius:8px; padding:16px; margin-bottom:14px; }
.hrs-layer-section:last-child { margin-bottom:0; }
.hrs-layer-section h4 { margin:0 0 12px; font-size:13px; font-weight:600; color:#374151; display:flex; align-items:center; gap:6px; }
.hrs-layer-section h4 .badge { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; font-size:11px; font-weight:700; color:#fff; }
.hrs-layer-section h4 .badge.h { background:#667eea; }
.hrs-layer-section h4 .badge.q { background:#ec4899; }
.hrs-layer-section h4 .badge.c { background:#f59e0b; }

/* ペルソナグリッド */
.hrs-persona-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.hrs-persona-card { border:2px solid #e5e7eb; border-radius:8px; padding:10px 6px; text-align:center; cursor:pointer; transition:all 0.2s; background:#fff; }
.hrs-persona-card:hover { border-color:#667eea; background:#f5f3ff; }
.hrs-persona-card.active { border-color:#667eea; background:linear-gradient(135deg,rgba(102,126,234,0.1) 0%,rgba(118,75,162,0.1) 100%); box-shadow:0 0 0 2px rgba(102,126,234,0.2); }
.hrs-persona-card .icon { font-size:22px; margin-bottom:4px; }
.hrs-persona-card .name { font-weight:600; font-size:11px; color:#374151; }

/* チェックボックスグループ */
.hrs-checkbox-group { display:flex; flex-wrap:wrap; gap:6px; }
.hrs-checkbox-item { display:flex; align-items:center; gap:4px; background:#fff; padding:6px 10px; border-radius:16px; border:1px solid #d1d5db; cursor:pointer; transition:all 0.2s; font-size:12px; }
.hrs-checkbox-item:hover { border-color:#667eea; }
.hrs-checkbox-item.checked { background:#667eea; color:#fff; border-color:#667eea; }
.hrs-checkbox-item.recommended { border-color:#10b981; }
.hrs-checkbox-item.recommended::after { content:"★"; margin-left:2px; color:#10b981; font-size:10px; }
.hrs-checkbox-item.checked.recommended::after { color:#fff; }
.hrs-checkbox-item input { display:none; }

/* レベルグループ */
.hrs-level-group { display:flex; gap:6px; }
.hrs-level-item { flex:1; text-align:center; padding:10px 6px; background:#fff; border:2px solid #e5e7eb; border-radius:6px; cursor:pointer; transition:all 0.2s; }
.hrs-level-item:hover { border-color:#667eea; }
.hrs-level-item.checked { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-color:transparent; }
.hrs-level-item input { display:none; }
.hrs-level-item .level { font-weight:700; font-size:14px; }
.hrs-level-item .desc { font-size:10px; opacity:0.8; margin-top:2px; }

/* フォームグループ */
.hrs-form-group { margin-bottom:14px; }
.hrs-form-group:last-child { margin-bottom:0; }
.hrs-form-group>label { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#374151; margin-bottom:8px; }
.hrs-form-group select { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
.hrs-form-group select:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 2px rgba(102,126,234,0.1); }

/* コンテンツ要素グリッド */
.hrs-content-items { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
.hrs-content-items .hrs-checkbox-item { display:flex; flex-direction:column; padding:10px 12px; background:#fff; border:2px solid #e5e7eb; border-radius:6px; }
.hrs-content-items .hrs-checkbox-item:hover { border-color:#f59e0b; background:#fffbeb; }
.hrs-content-items .hrs-checkbox-item.checked { border-color:#f59e0b; background:#fef3c7; box-shadow:0 0 0 1px #f59e0b; }
.hrs-content-items .item-label { font-weight:600; font-size:12px; color:#1e293b; }
.hrs-content-items .item-desc { font-size:10px; color:#64748b; margin-top:2px; }

/* プリセット */
.hrs-preset-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
.hrs-preset-card { border:2px solid #e5e7eb; border-radius:8px; padding:12px; cursor:pointer; transition:all 0.2s; background:#fff; }
.hrs-preset-card:hover { border-color:#667eea; background:#f5f3ff; }
.hrs-preset-card.active { border-color:#667eea; background:linear-gradient(135deg,rgba(102,126,234,0.1) 0%,rgba(118,75,162,0.1) 100%); }
.hrs-preset-card .preset-header { display:flex; align-items:center; gap:6px; margin-bottom:4px; }
.hrs-preset-card .icon { font-size:18px; }
.hrs-preset-card .name { font-weight:600; font-size:12px; color:#1e293b; }
.hrs-preset-card .desc { font-size:10px; color:#64748b; }

/* プレビュー */
.hrs-preview-area { background:#f8fafc; border-radius:8px; padding:16px; }
.hrs-preview-content { background:#fff; border-radius:6px; padding:14px; border-left:3px solid #667eea; }
.hrs-preview-summary { font-size:11px; color:#667eea; font-weight:500; margin-bottom:10px; font-family:monospace; background:#f0f4ff; padding:6px 10px; border-radius:4px; }
.hrs-preview-sample h4 { margin:0 0 6px; font-size:12px; color:#374151; }
.hrs-preview-sample p { margin:0; color:#64748b; line-height:1.6; font-size:12px; }

/* ボタン */
.hrs-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; border:none; }
.hrs-btn-primary { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; }
.hrs-btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,0.4); color:#fff; }
.hrs-btn-secondary { background:#fff; color:#667eea; border:1px solid #667eea; }
.hrs-btn-secondary:hover { background:#f5f3ff; color:#667eea; }
.hrs-actions { display:flex; gap:10px; margin-top:16px; }

/* 警告・結果 */
.hrs-warning-box { background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:8px 12px; margin-top:10px; display:none; font-size:12px; color:#92400e; }
.hrs-warning-box.show { display:flex; align-items:center; gap:6px; }
.hrs-result-box { margin-top:14px; padding:14px; border-radius:6px; }
.hrs-result-box.success { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
.hrs-result-box.error { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }
.hrs-result-box.loading { background:#ede9fe; border:1px solid #c4b5fd; color:#5b21b6; }

/* ヒント */
.hrs-hint { background:#eff6ff; border-radius:6px; padding:12px; margin-top:14px; }
.hrs-hint h4 { margin:0 0 6px; font-size:12px; color:#1e40af; display:flex; align-items:center; gap:6px; }
.hrs-hint ul { margin:0; padding-left:16px; color:#1e40af; font-size:11px; line-height:1.6; }

/* キュー */
.hrs-queue-section { margin-top:16px; padding-top:14px; border-top:1px solid #e5e7eb; }
.hrs-queue-section h4 { margin:0 0 10px; font-size:13px; color:#374151; display:flex; align-items:center; gap:6px; }
.hrs-queue-list { list-style:none; margin:0 0 12px; padding:0; }
.hrs-queue-item { display:flex; align-items:center; padding:8px 12px; background:#f8fafc; border-radius:6px; margin-bottom:6px; }
.hrs-queue-item .hotel-name { flex:1; font-weight:600; font-size:13px; color:#1e293b; }
.hrs-queue-item .hotel-location { color:#64748b; font-size:12px; margin-right:10px; }
.hrs-remove-queue { background:none; border:none; color:#ef4444; cursor:pointer; padding:4px; }
.hrs-remove-queue:hover { background:#fee2e2; border-radius:4px; }

/* レスポンシブ */
@media (max-width:1200px) { .hrs-hqc-main { grid-template-columns:1fr; } }
@media (max-width:768px) { .hrs-persona-grid { grid-template-columns:repeat(2,1fr); } .hrs-preset-grid { grid-template-columns:1fr; } .hrs-content-items { grid-template-columns:1fr; } }
        ';
    }
}