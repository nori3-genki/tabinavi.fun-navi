<?php
/**
 * OTA マッピングマネージャー
 * 
 * ホテル名とOTA URLの紐付けを管理
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRS_Mapping_Manager {

    /**
     * マッピングファイルパス
     */
    private $mapping_file;
    private $pending_file;

    /**
     * キャッシュ
     */
    private $mappings = array();
    private $pending = array();

    /**
     * コンストラクタ
     */
    public function __construct() {
        $dir = plugin_dir_path(__FILE__);
        $this->mapping_file = $dir . 'ota-known-mappings.php';
        $this->pending_file = $dir . 'mapping-pending.json';

        $this->load_mappings();
        $this->load_pending();
    }

    /**
     * マッピング読み込み
     */
    private function load_mappings() {
        if (file_exists($this->mapping_file)) {
            $this->mappings = require $this->mapping_file;
        }
    }

    /**
     * 保留中マッピング読み込み
     */
    private function load_pending() {
        if (file_exists($this->pending_file)) {
            $content = file_get_contents($this->pending_file);
            $this->pending = json_decode($content, true) ?: array();
        }
    }

    /**
     * ホテルのマッピングを取得
     */
    public function get_mapping($hotel_name) {
        return $this->mappings[$hotel_name] ?? null;
    }

    /**
     * ホテルのURL一覧を取得
     */
    public function get_urls($hotel_name) {
        $mapping = $this->get_mapping($hotel_name);
        return $mapping['urls'] ?? array();
    }

    /**
     * 特定OTAのURLを取得
     */
    public function get_url($hotel_name, $ota_id) {
        $urls = $this->get_urls($hotel_name);
        return $urls[$ota_id] ?? '';
    }

    /**
     * マッピングを追加/更新
     */
    public function set_mapping($hotel_name, $ota_id, $url) {
        if (!isset($this->mappings[$hotel_name])) {
            $this->mappings[$hotel_name] = array(
                'urls' => array(),
                'created_at' => current_time('mysql'),
            );
        }

        $this->mappings[$hotel_name]['urls'][$ota_id] = $url;
        $this->mappings[$hotel_name]['updated_at'] = current_time('mysql');

        return $this->save_mappings();
    }

    /**
     * 複数URLを一括設定
     */
    public function set_urls($hotel_name, $urls) {
        if (!isset($this->mappings[$hotel_name])) {
            $this->mappings[$hotel_name] = array(
                'urls' => array(),
                'created_at' => current_time('mysql'),
            );
        }

        foreach ($urls as $ota_id => $url) {
            if (!empty($url)) {
                $this->mappings[$hotel_name]['urls'][$ota_id] = $url;
            }
        }

        $this->mappings[$hotel_name]['updated_at'] = current_time('mysql');

        return $this->save_mappings();
    }

    /**
     * マッピングを削除
     */
    public function delete_mapping($hotel_name, $ota_id = null) {
        if (!isset($this->mappings[$hotel_name])) {
            return false;
        }

        if ($ota_id === null) {
            unset($this->mappings[$hotel_name]);
        } else {
            unset($this->mappings[$hotel_name]['urls'][$ota_id]);
        }

        return $this->save_mappings();
    }

    /**
     * マッピングファイル保存
     */
    private function save_mappings() {
        $content = "<?php\n/**\n * OTA Known Mappings\n * Auto-generated file\n */\nreturn " . var_export($this->mappings, true) . ";\n";

        $result = file_put_contents($this->mapping_file, $content);
        
        if ($result === false) {
            error_log('[HRS Mapping Manager] Failed to save mappings file');
            return false;
        }

        return true;
    }

    /**
     * 保留中マッピングを追加
     */
    public function add_pending($hotel_name, $ota_id, $url, $source = 'auto') {
        $key = md5($hotel_name . $ota_id);

        $this->pending[$key] = array(
            'hotel_name' => $hotel_name,
            'ota_id' => $ota_id,
            'url' => $url,
            'source' => $source,
            'created_at' => current_time('mysql'),
        );

        return $this->save_pending();
    }

    /**
     * 保留中マッピングを承認
     */
    public function approve_pending($key) {
        if (!isset($this->pending[$key])) {
            return false;
        }

        $item = $this->pending[$key];
        $result = $this->set_mapping($item['hotel_name'], $item['ota_id'], $item['url']);

        if ($result) {
            unset($this->pending[$key]);
            $this->save_pending();
        }

        return $result;
    }

    /**
     * 保留中マッピングを拒否
     */
    public function reject_pending($key) {
        if (!isset($this->pending[$key])) {
            return false;
        }

        unset($this->pending[$key]);
        return $this->save_pending();
    }

    /**
     * 保留中一覧取得
     */
    public function get_pending_list() {
        return $this->pending;
    }

    /**
     * 保留中ファイル保存
     */
    private function save_pending() {
        $content = json_encode($this->pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($this->pending_file, $content);
        return $result !== false;
    }

    /**
     * 全マッピング取得
     */
    public function get_all_mappings() {
        return $this->mappings;
    }

    /**
     * マッピング数取得
     */
    public function get_mapping_count() {
        return count($this->mappings);
    }

    /**
     * 検索
     */
    public function search_mappings($keyword) {
        $results = array();

        foreach ($this->mappings as $hotel_name => $data) {
            if (mb_stripos($hotel_name, $keyword) !== false) {
                $results[$hotel_name] = $data;
            }
        }

        return $results;
    }

    /**
     * URLからホテル名を逆引き
     */
    public function find_by_url($url) {
        foreach ($this->mappings as $hotel_name => $data) {
            foreach ($data['urls'] as $ota_id => $mapping_url) {
                if ($mapping_url === $url) {
                    return array(
                        'hotel_name' => $hotel_name,
                        'ota_id' => $ota_id,
                    );
                }
            }
        }

        return null;
    }

    /**
     * エクスポート（JSON）
     */
    public function export_json() {
        return json_encode($this->mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * インポート（JSON）
     */
    public function import_json($json) {
        $data = json_decode($json, true);
        
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $hotel_name => $mapping) {
            if (isset($mapping['urls']) && is_array($mapping['urls'])) {
                $this->set_urls($hotel_name, $mapping['urls']);
            }
        }

        return true;
    }

    /**
     * 統計情報取得
     */
    public function get_statistics() {
        $stats = array(
            'total_hotels' => count($this->mappings),
            'total_urls' => 0,
            'ota_breakdown' => array(),
            'pending_count' => count($this->pending),
        );

        foreach ($this->mappings as $data) {
            foreach ($data['urls'] as $ota_id => $url) {
                if (!empty($url)) {
                    $stats['total_urls']++;
                    if (!isset($stats['ota_breakdown'][$ota_id])) {
                        $stats['ota_breakdown'][$ota_id] = 0;
                    }
                    $stats['ota_breakdown'][$ota_id]++;
                }
            }
        }

        return $stats;
    }
}