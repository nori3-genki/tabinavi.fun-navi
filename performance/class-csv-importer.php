<?php
/**
 * HRS CSV Importer
 * GA4・Search Console CSVインポート処理
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_CSV_Importer {
    
    /** @var HRS_Performance_Tracker */
    private $tracker;
    
    /** @var array インポートログ */
    private $import_log = array();
    
    /** @var string ログ保存用オプション名 */
    private $log_option_name = 'hrs_csv_import_log';
    
    /** @var array GA4 CSVの必須カラム */
    private $ga4_required_columns = array(
        'page_path'     => array('ページパス', 'Page path', 'page_path', 'ページ'),
        'session_time'  => array('平均セッション時間', 'Average session duration', 'avg_session_duration', '平均エンゲージメント時間'),
        'bounce_rate'   => array('直帰率', 'Bounce rate', 'bounce_rate', 'エンゲージメント率')
    );
    
    /** @var array Search Console CSVの必須カラム */
    private $gsc_required_columns = array(
        'page'          => array('ページ', 'Page', 'page', 'URL'),
        'clicks'        => array('クリック数', 'Clicks', 'clicks'),
        'impressions'   => array('表示回数', 'Impressions', 'impressions'),
        'ctr'           => array('CTR', 'ctr', 'クリック率'),
        'position'      => array('掲載順位', 'Position', 'position', '平均掲載順位')
    );
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->tracker = new HRS_Performance_Tracker();
    }
    
    /**
     * GA4用CSVをインポート
     * 
     * @param string $file_path CSVファイルパス
     * @param string $data_date データ集計日
     * @return array インポート結果
     */
    public function import_ga4_csv($file_path, $data_date) {
        $this->reset_log();
        
        // ファイル存在確認
        if (!file_exists($file_path)) {
            return $this->error_result('ファイルが見つかりません: ' . $file_path);
        }
        
        // CSV読み込み
        $rows = $this->read_csv($file_path);
        if (is_wp_error($rows)) {
            return $this->error_result($rows->get_error_message());
        }
        
        if (empty($rows)) {
            return $this->error_result('CSVにデータがありません');
        }
        
        // ヘッダー行を取得してカラムマッピング
        $header = array_shift($rows);
        $column_map = $this->map_columns($header, $this->ga4_required_columns);
        
        if (is_wp_error($column_map)) {
            return $this->error_result($column_map->get_error_message());
        }
        
        $success_count = 0;
        $skip_count = 0;
        $error_count = 0;
        
        foreach ($rows as $row_index => $row) {
            $result = $this->process_ga4_row($row, $column_map, $data_date, $row_index + 2);
            
            if ($result === true) {
                $success_count++;
            } elseif ($result === 'skip') {
                $skip_count++;
            } else {
                $error_count++;
            }
        }
        
        // ログ保存
        $this->save_import_log('ga4', $file_path, $success_count, $skip_count, $error_count);
        
        return array(
            'success'      => true,
            'type'         => 'ga4',
            'total'        => count($rows),
            'success_count'=> $success_count,
            'skip_count'   => $skip_count,
            'error_count'  => $error_count,
            'log'          => $this->import_log
        );
    }
    
    /**
     * Search Console用CSVをインポート
     * 
     * @param string $file_path CSVファイルパス
     * @param string $data_date データ集計日
     * @return array インポート結果
     */
    public function import_gsc_csv($file_path, $data_date) {
        $this->reset_log();
        
        // ファイル存在確認
        if (!file_exists($file_path)) {
            return $this->error_result('ファイルが見つかりません: ' . $file_path);
        }
        
        // CSV読み込み
        $rows = $this->read_csv($file_path);
        if (is_wp_error($rows)) {
            return $this->error_result($rows->get_error_message());
        }
        
        if (empty($rows)) {
            return $this->error_result('CSVにデータがありません');
        }
        
        // ヘッダー行を取得してカラムマッピング
        $header = array_shift($rows);
        $column_map = $this->map_columns($header, $this->gsc_required_columns);
        
        if (is_wp_error($column_map)) {
            return $this->error_result($column_map->get_error_message());
        }
        
        $success_count = 0;
        $skip_count = 0;
        $error_count = 0;
        
        foreach ($rows as $row_index => $row) {
            $result = $this->process_gsc_row($row, $column_map, $data_date, $row_index + 2);
            
            if ($result === true) {
                $success_count++;
            } elseif ($result === 'skip') {
                $skip_count++;
            } else {
                $error_count++;
            }
        }
        
        // ログ保存
        $this->save_import_log('gsc', $file_path, $success_count, $skip_count, $error_count);
        
        return array(
            'success'      => true,
            'type'         => 'gsc',
            'total'        => count($rows),
            'success_count'=> $success_count,
            'skip_count'   => $skip_count,
            'error_count'  => $error_count,
            'log'          => $this->import_log
        );
    }
    
    /**
     * GA4行データを処理
     * 
     * @param array $row 行データ
     * @param array $column_map カラムマッピング
     * @param string $data_date データ日付
     * @param int $row_number 行番号（エラー表示用）
     * @return bool|string 成功時true、スキップ時'skip'、エラー時false
     */
    private function process_ga4_row($row, $column_map, $data_date, $row_number) {
        // ページパス取得
        $page_path = trim($row[$column_map['page_path']] ?? '');
        
        if (empty($page_path)) {
            $this->add_log('skip', "行{$row_number}: ページパスが空です");
            return 'skip';
        }
        
        // URLから投稿IDを取得
        $post_id = $this->url_to_post_id($page_path);
        
        if (!$post_id) {
            $this->add_log('skip', "行{$row_number}: 記事が見つかりません - {$page_path}");
            return 'skip';
        }
        
        // データ抽出
        $session_time = $this->parse_time($row[$column_map['session_time']] ?? '0');
        $bounce_rate = $this->parse_percentage($row[$column_map['bounce_rate']] ?? '0');
        
        // 既存データがあれば取得してマージ
        $existing = $this->tracker->get_data_by_post($post_id, $data_date);
        
        $data = array(
            'post_id'          => $post_id,
            'avg_time_on_page' => $session_time,
            'bounce_rate'      => $bounce_rate,
            'ctr'              => $existing ? $existing->ctr : 0,
            'avg_position'     => $existing ? $existing->avg_position : 0,
            'impressions'      => $existing ? $existing->impressions : 0,
            'data_date'        => $data_date,
            'source'           => 'csv'
        );
        
        $result = $this->tracker->save_data($data);
        
        if ($result) {
            $this->add_log('success', "行{$row_number}: インポート成功 - {$page_path}");
            return true;
        } else {
            $this->add_log('error', "行{$row_number}: 保存失敗 - {$page_path}");
            return false;
        }
    }
    
    /**
     * Search Console行データを処理
     * 
     * @param array $row 行データ
     * @param array $column_map カラムマッピング
     * @param string $data_date データ日付
     * @param int $row_number 行番号（エラー表示用）
     * @return bool|string 成功時true、スキップ時'skip'、エラー時false
     */
    private function process_gsc_row($row, $column_map, $data_date, $row_number) {
        // ページURL取得
        $page_url = trim($row[$column_map['page']] ?? '');
        
        if (empty($page_url)) {
            $this->add_log('skip', "行{$row_number}: ページURLが空です");
            return 'skip';
        }
        
        // URLから投稿IDを取得
        $post_id = $this->url_to_post_id($page_url);
        
        if (!$post_id) {
            $this->add_log('skip', "行{$row_number}: 記事が見つかりません - {$page_url}");
            return 'skip';
        }
        
        // データ抽出
        $clicks = intval($row[$column_map['clicks']] ?? 0);
        $impressions = intval($row[$column_map['impressions']] ?? 0);
        $ctr = $this->parse_percentage($row[$column_map['ctr']] ?? '0');
        $position = floatval($row[$column_map['position']] ?? 0);
        
        // 既存データがあれば取得してマージ
        $existing = $this->tracker->get_data_by_post($post_id, $data_date);
        
        $data = array(
            'post_id'          => $post_id,
            'avg_time_on_page' => $existing ? $existing->avg_time_on_page : 0,
            'bounce_rate'      => $existing ? $existing->bounce_rate : 0,
            'ctr'              => $ctr,
            'avg_position'     => $position,
            'impressions'      => $impressions,
            'data_date'        => $data_date,
            'source'           => 'csv'
        );
        
        $result = $this->tracker->save_data($data);
        
        if ($result) {
            $this->add_log('success', "行{$row_number}: インポート成功 - {$page_url}");
            return true;
        } else {
            $this->add_log('error', "行{$row_number}: 保存失敗 - {$page_url}");
            return false;
        }
    }
    
    /**
     * CSVファイルを読み込む
     * 
     * @param string $file_path ファイルパス
     * @return array|WP_Error 行データ配列またはエラー
     */
    private function read_csv($file_path) {
        $rows = array();
        
        // BOM対応でファイルを開く
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return new WP_Error('file_error', 'ファイルを開けませんでした');
        }
        
        // BOMをスキップ
        $bom = fread($handle, 3);
        if ($bom !== "\xef\xbb\xbf") {
            rewind($handle);
        }
        
        // エンコーディング検出用に最初の行を読む
        $first_line = fgets($handle);
        rewind($handle);
        
        // BOMを再度スキップ
        $bom = fread($handle, 3);
        if ($bom !== "\xef\xbb\xbf") {
            rewind($handle);
        }
        
        // Shift_JISの場合はUTF-8に変換
        $encoding = mb_detect_encoding($first_line, array('UTF-8', 'SJIS', 'SJIS-win', 'CP932'), true);
        
        while (($row = fgetcsv($handle)) !== false) {
            // エンコーディング変換
            if ($encoding && $encoding !== 'UTF-8') {
                $row = array_map(function($cell) use ($encoding) {
                    return mb_convert_encoding($cell, 'UTF-8', $encoding);
                }, $row);
            }
            $rows[] = $row;
        }
        
        fclose($handle);
        
        return $rows;
    }
    
    /**
     * ヘッダー行からカラムをマッピング
     * 
     * @param array $header ヘッダー行
     * @param array $required_columns 必須カラム定義
     * @return array|WP_Error マッピング結果またはエラー
     */
    private function map_columns($header, $required_columns) {
        $map = array();
        $missing = array();
        
        foreach ($required_columns as $key => $possible_names) {
            $found = false;
            foreach ($header as $index => $col_name) {
                $col_name_clean = trim(strtolower($col_name));
                foreach ($possible_names as $name) {
                    if ($col_name_clean === strtolower($name)) {
                        $map[$key] = $index;
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $missing[] = $possible_names[0];
            }
        }
        
        if (!empty($missing)) {
            return new WP_Error(
                'missing_columns',
                '必須カラムが見つかりません: ' . implode(', ', $missing)
            );
        }
        
        return $map;
    }
    
    /**
     * URLから投稿IDを取得
     * 
     * @param string $url URLまたはパス
     * @return int|null 投稿IDまたはnull
     */
    public function url_to_post_id($url) {
        // 完全URLの場合はパスを抽出
        if (strpos($url, 'http') === 0) {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '';
        } else {
            $path = $url;
        }
        
        // 前後のスラッシュを正規化
        $path = '/' . trim($path, '/') . '/';
        
        // WordPressのurl_to_postidを試行
        $full_url = home_url($path);
        $post_id = url_to_postid($full_url);
        
        if ($post_id) {
            return $post_id;
        }
        
        // スラッグから検索
        $slug = trim($path, '/');
        $slug_parts = explode('/', $slug);
        $last_slug = end($slug_parts);
        
        if (!empty($last_slug)) {
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s AND post_status = 'publish' 
                LIMIT 1",
                $last_slug
            ));
            
            if ($post_id) {
                return intval($post_id);
            }
        }
        
        return null;
    }
    
    /**
     * GA4とGSCのデータを統合
     * 両方のCSVをインポート後、同じ日付のデータを統合
     * 
     * @param string $data_date データ日付
     * @return bool 成功/失敗
     */
    public function merge_data($data_date) {
        // 現在の実装では save_data 内でマージされるため追加処理不要
        // 将来的に複雑なマージロジックが必要な場合はここに実装
        return true;
    }
    
    /**
     * インポートログを取得
     * 
     * @param int $limit 取得件数
     * @return array ログ配列
     */
    public function get_import_log($limit = 10) {
        $logs = get_option($this->log_option_name, array());
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * 時間文字列をパース（秒数に変換）
     * 
     * @param string $value 時間文字列（例: "1:30", "90", "00:01:30"）
     * @return float 秒数
     */
    private function parse_time($value) {
        $value = trim($value);
        
        // 数値のみの場合はそのまま秒数として扱う
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        // HH:MM:SS または MM:SS 形式
        if (strpos($value, ':') !== false) {
            $parts = array_reverse(explode(':', $value));
            $seconds = 0;
            foreach ($parts as $i => $part) {
                $seconds += floatval($part) * pow(60, $i);
            }
            return $seconds;
        }
        
        return 0;
    }
    
    /**
     * パーセント文字列をパース
     * 
     * @param string $value パーセント文字列（例: "50%", "0.5", "50"）
     * @return float パーセント値（0-100）
     */
    private function parse_percentage($value) {
        $value = trim($value);
        $value = str_replace(array('%', '％'), '', $value);
        $value = str_replace(',', '.', $value);
        
        $num = floatval($value);
        
        // 0.5 のような小数の場合は100倍
        if ($num > 0 && $num < 1) {
            $num = $num * 100;
        }
        
        return $num;
    }
    
    /**
     * エラー結果を返す
     * 
     * @param string $message エラーメッセージ
     * @return array エラー結果
     */
    private function error_result($message) {
        $this->add_log('error', $message);
        
        return array(
            'success' => false,
            'error'   => $message,
            'log'     => $this->import_log
        );
    }
    
    /**
     * ログをリセット
     */
    private function reset_log() {
        $this->import_log = array();
    }
    
    /**
     * ログを追加
     * 
     * @param string $type ログタイプ（success/skip/error）
     * @param string $message メッセージ
     */
    private function add_log($type, $message) {
        $this->import_log[] = array(
            'type'    => $type,
            'message' => $message,
            'time'    => current_time('mysql')
        );
    }
    
    /**
     * インポートログを保存
     * 
     * @param string $type インポートタイプ（ga4/gsc）
     * @param string $file_path ファイルパス
     * @param int $success 成功件数
     * @param int $skip スキップ件数
     * @param int $error エラー件数
     */
    private function save_import_log($type, $file_path, $success, $skip, $error) {
        $logs = get_option($this->log_option_name, array());
        
        $new_log = array(
            'type'       => $type,
            'filename'   => basename($file_path),
            'imported_at'=> current_time('mysql'),
            'success'    => $success,
            'skip'       => $skip,
            'error'      => $error
        );
        
        array_unshift($logs, $new_log);
        
        // 最大50件保持
        $logs = array_slice($logs, 0, 50);
        
        update_option($this->log_option_name, $logs);
    }
    
    /**
     * CSVバリデーション（アップロード前チェック用）
     * 
     * @param string $file_path ファイルパス
     * @param string $type CSVタイプ（ga4/gsc）
     * @return array バリデーション結果
     */
    public function validate_csv($file_path, $type) {
        if (!file_exists($file_path)) {
            return array('valid' => false, 'error' => 'ファイルが見つかりません');
        }
        
        $rows = $this->read_csv($file_path);
        if (is_wp_error($rows)) {
            return array('valid' => false, 'error' => $rows->get_error_message());
        }
        
        if (count($rows) < 2) {
            return array('valid' => false, 'error' => 'データ行がありません');
        }
        
        $header = $rows[0];
        $required = ($type === 'ga4') ? $this->ga4_required_columns : $this->gsc_required_columns;
        
        $column_map = $this->map_columns($header, $required);
        if (is_wp_error($column_map)) {
            return array('valid' => false, 'error' => $column_map->get_error_message());
        }
        
        return array(
            'valid'      => true,
            'row_count'  => count($rows) - 1,
            'columns'    => array_keys($column_map)
        );
    }
}