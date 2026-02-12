<?php
/**
 * HQC学習システム DBインストーラー
 * 
 * 学習システム用のデータベーステーブルを作成・管理
 * 
 * @package HRS
 * @subpackage Learning
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_HQC_DB_Installer {

    /**
     * DBバージョン
     */
    const DB_VERSION = '1.0.0';

    /**
     * テーブル名を取得
     */
    public static function get_table_names() {
        global $wpdb;
        return array(
            'history' => $wpdb->prefix . 'hrs_hqc_history',
            'learning' => $wpdb->prefix . 'hrs_hotel_learning',
            'patterns' => $wpdb->prefix . 'hrs_success_patterns',
            'ab_tests' => $wpdb->prefix . 'hrs_ab_tests',
        );
    }

    /**
     * テーブルを作成
     */
    public static function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_names();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 生成履歴テーブル
        $sql_history = "CREATE TABLE IF NOT EXISTS {$tables['history']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT NULL,
            hotel_name varchar(255) NOT NULL DEFAULT '',
            location varchar(255) DEFAULT '',
            h_score decimal(5,2) DEFAULT 0,
            q_score decimal(5,2) DEFAULT 0,
            c_score decimal(5,2) DEFAULT 0,
            total_score decimal(5,2) DEFAULT 0,
            h_details longtext,
            q_details longtext,
            c_details longtext,
            weak_points longtext,
            prompt_used longtext,
            model_used varchar(50) DEFAULT 'gpt-4',
            generation_params longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY hotel_name (hotel_name),
            KEY total_score (total_score),
            KEY created_at (created_at)
        ) $charset_collate;";

        // ホテル別学習テーブル
        $sql_learning = "CREATE TABLE IF NOT EXISTS {$tables['learning']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hotel_name varchar(255) NOT NULL,
            location varchar(255) DEFAULT '',
            generation_count int(11) DEFAULT 0,
            avg_score decimal(5,2) DEFAULT 0,
            best_score decimal(5,2) DEFAULT 0,
            last_score decimal(5,2) DEFAULT 0,
            chronic_weak_points longtext,
            best_params longtext,
            best_persona varchar(50) DEFAULT '',
            best_tone varchar(50) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY hotel_name (hotel_name),
            KEY avg_score (avg_score)
        ) $charset_collate;";

        // 成功パターンテーブル
        $sql_patterns = "CREATE TABLE IF NOT EXISTS {$tables['patterns']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hotel_name varchar(255) DEFAULT '',
            location varchar(255) DEFAULT '',
            total_score decimal(5,2) DEFAULT 0,
            h_score decimal(5,2) DEFAULT 0,
            q_score decimal(5,2) DEFAULT 0,
            c_score decimal(5,2) DEFAULT 0,
            prompt_used longtext,
            generation_params longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY total_score (total_score)
        ) $charset_collate;";

        // A/Bテストテーブル
        $sql_ab = "CREATE TABLE IF NOT EXISTS {$tables['ab_tests']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_name varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            variant_a_params longtext,
            variant_b_params longtext,
            variant_a_score decimal(5,2) DEFAULT NULL,
            variant_b_score decimal(5,2) DEFAULT NULL,
            variant_a_post_id bigint(20) unsigned DEFAULT NULL,
            variant_b_post_id bigint(20) unsigned DEFAULT NULL,
            winner varchar(10) DEFAULT NULL,
            hotel_name varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_history);
        dbDelta($sql_learning);
        dbDelta($sql_patterns);
        dbDelta($sql_ab);

        // DBバージョンを保存
        update_option('hrs_hqc_db_version', self::DB_VERSION);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS HQC] Database tables created/updated');
        }
    }

    /**
     * テーブルを削除
     */
    public static function uninstall() {
        global $wpdb;
        
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('hrs_hqc_db_version');
        delete_option('hrs_hqc_learning_enabled');
        delete_option('hrs_hqc_confidence_config');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HRS HQC] Database tables removed');
        }
    }

    /**
     * アップグレードチェック
     */
    public static function maybe_upgrade() {
        $current_version = get_option('hrs_hqc_db_version', '0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::install();
        }
    }

    /**
     * テーブル存在チェック
     */
    public static function tables_exist() {
        global $wpdb;
        
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
            if ($exists !== $table) {
                return false;
            }
        }
        
        return true;
    }
}