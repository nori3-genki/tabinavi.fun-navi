<?php
/**
 * Dashboard Styles - スタイル出力（修正版）
 * @package Hotel_Review_System
 * @version 6.8.1 - スコア分布グラフCSS追加
 */

if (!defined('ABSPATH')) exit;

class HRS_Dashboard_Styles {
    
    public static function render() {
        ?>
        <style>
        /* ========================================
         * Dashboard Base Styles
         * ======================================== */
        .hrs-dashboard-wrap {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .hrs-page-header {
            margin-bottom: 30px;
        }
        
        .hrs-page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hrs-page-header .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
            color: #6366f1;
        }
        
        .hrs-page-subtitle {
            color: #64748b;
            margin-top: 5px;
            font-size: 14px;
        }
        
        /* ========================================
         * Stats Cards
         * ======================================== */
        .hrs-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .hrs-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .hrs-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
        }
        
        .stat-icon .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #64748b;
        }
        
        .stat-info {
            flex: 1;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
        }
        
        /* Card color variants */
        .card-total { border-left: 4px solid #6366f1; }
        .card-total .stat-icon { background: #eef2ff; }
        .card-total .stat-icon .dashicons { color: #6366f1; }
        
        .card-published { border-left: 4px solid #10b981; }
        .card-published .stat-icon { background: #d1fae5; }
        .card-published .stat-icon .dashicons { color: #10b981; }
        
        .card-draft { border-left: 4px solid #f59e0b; }
        .card-draft .stat-icon { background: #fef3c7; }
        .card-draft .stat-icon .dashicons { color: #f59e0b; }
        
        .card-today { border-left: 4px solid #ec4899; }
        .card-today .stat-icon { background: #fce7f3; }
        .card-today .stat-icon .dashicons { color: #ec4899; }
        
        /* ========================================
         * HQC Stats Cards
         * ======================================== */
        .hrs-hqc-stats {
            margin-top: 0;
        }
        
        .card-hqc-total { border-left: 4px solid #6366f1; }
        .card-hqc-total .stat-icon { background: #eef2ff; }
        .card-hqc-total .stat-icon .dashicons { color: #6366f1; }
        
        .card-hqc-high { border-left: 4px solid #10b981; }
        .card-hqc-high .stat-icon { background: #d1fae5; }
        .card-hqc-high .stat-icon .dashicons { color: #10b981; }
        
        .card-hqc-hotels { border-left: 4px solid #f59e0b; }
        .card-hqc-hotels .stat-icon { background: #fef3c7; }
        .card-hqc-hotels .stat-icon .dashicons { color: #f59e0b; }
        
        .card-hqc-patterns { border-left: 4px solid #ec4899; }
        .card-hqc-patterns .stat-icon { background: #fce7f3; }
        .card-hqc-patterns .stat-icon .dashicons { color: #ec4899; }
        
        /* ========================================
         * Dashboard Grid & Cards
         * ======================================== */
        .hrs-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .hrs-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .hrs-card-wide {
            grid-column: span 2;
        }
        
        .hrs-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .hrs-card-header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hrs-card-header .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
            color: #6366f1;
        }
        
        .hrs-card-body {
            padding: 20px;
        }
        
        /* ========================================
         * Chart Container
         * ======================================== */
        .chart-container {
            position: relative;
            width: 100%;
            height: 250px;
        }
        
        .chart-fallback {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 8px;
            border: 2px dashed #cbd5e1;
        }
        
        .chart-fallback-small {
            padding: 40px 20px;
        }
        
        .fallback-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .fallback-message {
            color: #475569;
            font-weight: 500;
            margin: 10px 0 5px 0;
        }
        
        .fallback-hint {
            color: #94a3b8;
            font-size: 13px;
            margin: 0 0 15px 0;
        }
        
        .chart-fallback .button {
            margin-top: 10px;
        }
        
        /* ========================================
         * API Status
         * ======================================== */
        .api-status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .api-status-item:last-child {
            border-bottom: none;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #059669;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #d97706;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        /* ========================================
         * Quick Actions
         * ======================================== */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .action-card {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        
        .action-card:hover {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }
        
        /* ========================================
         * Axis Score Bars
         * ======================================== */
        .axis-score-bars {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .axis-score-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .axis-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
        }
        
        .axis-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }
        
        .axis-badge.axis-h { background: #ec4899; }
        .axis-badge.axis-q { background: #10b981; }
        .axis-badge.axis-c { background: #f59e0b; }
        
        .axis-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            border-radius: 4px;
            height: 24px;
            padding: 3px;
        }
        
        .axis-bar {
            height: 18px;
            border-radius: 3px;
            transition: width 0.5s ease;
            min-width: 2px;
        }
        
        .axis-bar-h { background: linear-gradient(90deg, #ec4899, #f472b6); }
        .axis-bar-q { background: linear-gradient(90deg, #10b981, #34d399); }
        .axis-bar-c { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        
        .axis-value {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            min-width: 40px;
        }
        
        .axis-stats-summary {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .summary-label {
            font-size: 11px;
            color: #64748b;
        }
        
        .summary-value {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* ========================================
         * Weak Points List
         * ======================================== */
        .weak-points-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .weak-point-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background: #fef3c7;
            border-radius: 8px;
            border-left: 3px solid #f59e0b;
        }
        
        .weak-category {
            flex: 1;
            font-weight: 500;
            color: #374151;
        }
        
        .weak-count {
            font-size: 12px;
            color: #6b7280;
            background: #fff;
            padding: 4px 10px;
            border-radius: 12px;
        }
        
        .weak-points-hint {
            margin-top: 15px;
            font-size: 13px;
            color: #64748b;
        }
        
        .weak-points-hint a {
            color: #6366f1;
            text-decoration: none;
        }
        
        .weak-points-hint a:hover {
            text-decoration: underline;
        }
        
        /* ========================================
         * Recent Articles
         * ======================================== */
        .recent-article-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .recent-article-item:last-child {
            border-bottom: none;
        }
        
        .recent-article-item a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
        }
        
        .recent-article-item a:hover {
            color: #6366f1;
        }
        
        .recent-article-item span {
            font-size: 12px;
            color: #64748b;
        }
        
        /* ========================================
         * Responsive
         * ======================================== */
        @media (max-width: 1200px) {
            .hrs-card-wide {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 768px) {
            .hrs-dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .hrs-card-wide {
                grid-column: span 1;
            }
            
            .hrs-stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .axis-stats-summary {
                flex-wrap: wrap;
            }
            
            .chart-container {
                height: 200px;
            }
        }
        
        @media (max-width: 480px) {
            .hrs-stats-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 150px;
            }
        }
        </style>
        <?php
    }
}