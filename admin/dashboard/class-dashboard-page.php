<?php
/**
 * Dashboard Page - メイン表示クラス
 * @package Hotel_Review_System
 * @version 6.9.0 - HQCスコア関連を学習ダッシュボードに一本化
 * 
 * 変更内容：
 * - HQCスコア概要カード削除
 * - スコア推移グラフ削除
 * - スコア分布・軸別平均スコア削除
 * - 慢性的弱点セクション削除
 * - 学習ダッシュボードへのリンクカード追加
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-dashboard-data.php';
require_once __DIR__ . '/class-dashboard-styles.php';

class HRS_Dashboard_Page {

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('このページにアクセスする権限がありません。', '5d-review-builder'));
        }
        
        $stats = HRS_Dashboard_Data::get_statistics();
        $api_status = HRS_Dashboard_Data::get_api_status();
        $recent = HRS_Dashboard_Data::get_recent_articles(5);
        ?>
        <div class="wrap hrs-dashboard-wrap">
            <div class="hrs-page-header">
                <h1><span class="dashicons dashicons-dashboard"></span> 5D Review Builder ダッシュボード</h1>
                <p class="hrs-page-subtitle">AI powered Hotel Review System - v6.9.0</p>
            </div>
            
            <!-- 記事統計カード -->
            <div class="hrs-stats-cards">
                <?php foreach ([
                    ['class' => 'card-total', 'icon' => 'media-document', 'key' => 'total', 'label' => '総記事数'],
                    ['class' => 'card-published', 'icon' => 'yes-alt', 'key' => 'published', 'label' => '公開済み'],
                    ['class' => 'card-draft', 'icon' => 'edit', 'key' => 'draft', 'label' => '下書き'],
                    ['class' => 'card-today', 'icon' => 'star-filled', 'key' => 'today', 'label' => '本日の生成'],
                ] as $c): ?>
                <div class="hrs-stat-card <?php echo $c['class']; ?>">
                    <div class="stat-icon"><span class="dashicons dashicons-<?php echo $c['icon']; ?>"></span></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo number_format($stats[$c['key']]); ?></div>
                        <div class="stat-label"><?php echo $c['label']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="hrs-dashboard-grid">
                <div class="hrs-card">
                    <div class="hrs-card-header"><h2><span class="dashicons dashicons-admin-plugins"></span> API接続状態</h2></div>
                    <div class="hrs-card-body">
                        <?php foreach ($api_status as $s): ?>
                        <div class="api-status-item">
                            <span><?php echo $s['icon'] . ' ' . esc_html($s['name']); ?></span>
                            <?php echo $s['configured'] ? '<span class="badge-success">✓設定済み</span>' : '<span class="badge-warning">⚠未設定</span>'; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="hrs-card">
                    <div class="hrs-card-header"><h2><span class="dashicons dashicons-controls-play"></span> クイックアクション</h2></div>
                    <div class="hrs-card-body">
                        <div class="quick-actions-grid">
                            <a href="<?php echo admin_url('admin.php?page=5d-review-builder-generator'); ?>" class="action-card">🚀 自動生成</a>
                            <a href="<?php echo admin_url('admin.php?page=5d-review-builder-manual'); ?>" class="action-card">✍️ 手動生成</a>
                            <a href="<?php echo admin_url('admin.php?page=5d-review-builder-generator'); ?>" class="action-card">⚙️ HQC設定</a>
                            <a href="<?php echo admin_url('admin.php?page=5d-review-builder-nurture'); ?>" class="action-card">🌱 記事育成</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- HQC学習ダッシュボードへのリンクカード -->
            <div class="hrs-card hrs-hqc-link-card">
                <div class="hrs-card-body" style="text-align: center; padding: 30px 20px;">
                    <div style="font-size: 48px; margin-bottom: 12px;">📊</div>
                    <h2 style="margin: 0 0 8px; font-size: 18px; color: #1e293b;">HQCスコア・学習データ</h2>
                    <p style="color: #64748b; margin: 0 0 16px; font-size: 14px;">
                        スコア推移、スコア分布、軸別平均、改善ポイントは学習ダッシュボードで確認できます
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=hrs-hqc-dashboard'); ?>" class="button button-primary button-hero">
                        📈 学習ダッシュボードを開く
                    </a>
                </div>
            </div>
            
            <div class="hrs-card">
                <div class="hrs-card-header"><h2><span class="dashicons dashicons-media-document"></span> 最近の記事</h2></div>
                <div class="hrs-card-body">
                    <?php if (!empty($recent)): foreach ($recent as $a): ?>
                    <div class="recent-article-item">
                        <a href="<?php echo get_edit_post_link($a['id']); ?>"><?php echo esc_html($a['title']); ?></a>
                        <span><?php echo esc_html($a['date']); ?> | <?php echo esc_html($a['status']); ?></span>
                    </div>
                    <?php endforeach; else: ?>
                    <p>記事がありません</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="hrs-dashboard-grid">
                <div class="hrs-card">
                    <div class="hrs-card-header"><h2>システム情報</h2></div>
                    <div class="hrs-card-body">
                        <p>バージョン: v6.9.0</p>
                        <p>WordPress: <?php echo get_bloginfo('version'); ?></p>
                        <p>PHP: <?php echo PHP_VERSION; ?></p>
                    </div>
                </div>
                <div class="hrs-card">
                    <div class="hrs-card-header"><h2>ヘルプ</h2></div>
                    <div class="hrs-card-body">
                        <a href="<?php echo admin_url('admin.php?page=5d-review-builder-settings'); ?>">設定ガイド</a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
        HRS_Dashboard_Styles::render();
    }
}