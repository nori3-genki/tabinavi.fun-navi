<?php
/**
 * Nurture Styles - CSS
 * @package Hotel_Review_System
 * @version 6.6.1
 */
if (!defined('ABSPATH')) exit;

class HRS_Nurture_Styles {
    public static function render() {
        ?>
        <style>
        /* ===== 全体レイアウト ===== */
        .hrs-nurture-wrap {
            margin: 20px 20px 20px 0;
        }

        /* ===== ヘッダー ===== */
        .hrs-page-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .hrs-page-header h1 {
            color: white;
            font-size: 32px;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ===== スタッツカード ===== */
        .hrs-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .hrs-stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid;
        }
        .hrs-stat-card.excellent { border-left-color: #00a32a; }
        .hrs-stat-card.good { border-left-color: #4ab866; }
        .hrs-stat-card.needs-work { border-left-color: #dba617; }
        .hrs-stat-card.poor { border-left-color: #d63638; }
        .stat-icon { font-size: 48px; line-height: 1; }
        .stat-info { flex: 1; }
        .stat-value { font-size: 32px; font-weight: bold; color: #1d2327; line-height: 1; }
        .stat-label { font-size: 14px; color: #50575e; margin-top: 4px; }
        .stat-percent { font-size: 20px; font-weight: 600; color: #2271b1; }

        /* ===== フィルター ===== */
        .hrs-filters-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        .hrs-filters-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d2327;
        }

        /* ===== ボタン：見切れ防止強化 ===== */
        #apply-filters,
        #bulk-action-apply {
            flex-shrink: 0; /* 縮まない */
            white-space: nowrap; /* 折り返さない */
            min-width: auto; /* デフォルト幅を尊重 */
        }
        .hrs-apply-button {
            padding-left: 16px;
            padding-right: 16px;
        }

        /* ===== SEO改善ヒント ===== */
        .hrs-tips-card {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .hrs-tips-card h3 {
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1d2327;
        }
        .hrs-tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .tip-item {
            background: white;
            padding: 16px;
            border-radius: 8px;
        }
        .tip-item strong {
            display: block;
            margin-bottom: 4px;
            color: #1d2327;
        }
        .tip-item p {
            margin: 0;
            font-size: 13px;
            color: #50575e;
        }

        /* ===== 記事一覧ヘッダー ===== */
        .hrs-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .hrs-section-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bulk-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* ===== 記事カードグリッド ===== */
        .hrs-articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .hrs-article-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
            overflow: hidden;
        }
        .hrs-article-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        /* ===== 記事カード：ヘッダー ===== */
        .article-header {
            padding: 16px;
            background: #f9f9f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .article-score-badge {
            text-align: center;
            padding: 12px 20px;
            border-radius: 8px;
            min-width: 80px;
        }
        .article-score-badge.score-excellent {
            background: linear-gradient(135deg, #00a32a 0%, #46b450 100%);
            color: white;
        }
        .article-score-badge.score-good {
            background: linear-gradient(135deg, #4ab866 0%, #7fcc76 100%);
            color: white;
        }
        .article-score-badge.score-needs-work {
            background: linear-gradient(135deg, #dba617 0%, #e5c100 100%);
            color: white;
        }
        .article-score-badge.score-poor {
            background: linear-gradient(135deg, #d63638 0%, #e04b4d 100%);
            color: white;
        }
        .score-number {
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
        }
        .score-label {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.9;
        }
        .score-breakdown {
            font-size: 10px;
            margin-top: 4px;
            opacity: 0.85;
            font-family: monospace;
        }

        /* ===== 記事カード：本文 ===== */
        .article-body {
            padding: 20px;
        }
        .article-title {
            margin: 0 0 12px 0;
            font-size: 16px;
            line-height: 1.4;
        }
        .article-title a {
            color: #1d2327;
            text-decoration: none;
        }
        .article-title a:hover {
            color: #2271b1;
        }
        .article-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #50575e;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ===== SEOプログレスバー ===== */
        .seo-progress {
            margin: 16px 0;
        }
        .progress-bar-container {
            height: 8px;
            background: #f0f0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        .progress-bar {
            height: 100%;
            transition: width 0.3s ease;
        }
        .progress-bar.score-excellent {
            background: linear-gradient(90deg, #00a32a 0%, #46b450 100%);
        }
        .progress-bar.score-good {
            background: linear-gradient(90deg, #4ab866 0%, #7fcc76 100%);
        }
        .progress-bar.score-needs-work {
            background: linear-gradient(90deg, #dba617 0%, #e5c100 100%);
        }
        .progress-bar.score-poor {
            background: linear-gradient(90deg, #d63638 0%, #e04b4d 100%);
        }
        .progress-percentage {
            text-align: right;
            font-size: 12px;
            font-weight: 600;
            color: #1d2327;
        }

        /* ===== 要改善項目 ===== */
        .article-issues {
            background: #fff3cd;
            border-left: 3px solid #dba617;
            padding: 12px;
            border-radius: 4px;
            margin-top: 12px;
        }
        .issues-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #856404;
            margin-bottom: 8px;
        }
        .issues-list {
            margin: 0;
            padding-left: 20px;
            font-size: 12px;
            color: #856404;
        }
        .issues-list li {
            margin-bottom: 4px;
        }

        /* ===== 記事カード：フッター ===== */
        .article-footer {
            padding: 12px 20px;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ===== 新規追加：ゴミ箱（削除）ボタン関連スタイル ===== */
        .trash-btn,
        .bulk-trash-btn {
            background: #d63638;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .trash-btn:hover,
        .bulk-trash-btn:hover {
            background: #b32d2e;
        }
        .trash-btn:disabled,
        .bulk-trash-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .trash-btn i,
        .bulk-trash-btn i {
            font-style: normal;
        }

        /* 一括アクション用のセレクトボックスとボタンの調整 */
        .bulk-actions select {
            min-width: 160px;
        }

        /* ローディングスピナー（AJAX処理中） */
        .hrs-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #d63638;
            border-radius: 50%;
            animation: hrs-spin 1s linear infinite;
            margin-left: 8px;
        }
        @keyframes hrs-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ===== レスポンシブ対応 ===== */
        @media (max-width: 1280px) {
            .hrs-articles-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        }
        @media (max-width: 782px) {
            .hrs-articles-grid { grid-template-columns: 1fr; }
            .hrs-filters-row { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
            .hrs-section-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .bulk-actions { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
            .article-footer { justify-content: center; }
        }
        </style>
        <?php
    }
}