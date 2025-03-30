<?php
/**
 * Trendyol WooCommerce Batch Tracker
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trendyol batch isteklerini takip eden sınıf
 */
class Trendyol_WC_Batch_Tracker {
    
    /**
     * Yapılandırıcı
     */
    public function __construct() {
        // Batch yanıtlarını takip et
        add_action('trendyol_wc_api_response', array($this, 'track_batch_response'), 10, 3);
    }
    
    /**
     * API yanıtlarını takip et ve batch ID'leri kaydet
     */
    public function track_batch_response($response, $endpoint, $method) {
        // Sadece POST veya PUT isteklerini takip et
        if (!in_array($method, array('POST', 'PUT'))) {
            return;
        }
        
        // Batch ID kontrolü
        if (!isset($response['batchRequestId'])) {
            return;
        }
        
        $batch_id = $response['batchRequestId'];
        $batch_type = $this->determine_batch_type($endpoint);
        
        // Batch isteklerini al
        $batch_requests = get_option('trendyol_batch_requests', array());
        
        // Yeni batch isteği ekle
        $batch_requests[] = array(
            'id' => $batch_id,
            'type' => $batch_type,
            'status' => 'PROCESSING',
            'date' => time(),
            'endpoint' => $endpoint,
            'last_check' => 0
        );
        
        // Maksimum 100 istek sakla
        if (count($batch_requests) > 100) {
            $batch_requests = array_slice($batch_requests, -100);
        }
        
        // Batch isteklerini kaydet
        update_option('trendyol_batch_requests', $batch_requests);
    }
    
    /**
     * Endpoint'e göre batch tipini belirle
     */
    private function determine_batch_type($endpoint) {
        if (strpos($endpoint, 'createProducts') !== false) {
            return 'Ürün Oluşturma';
        } else if (strpos($endpoint, 'updatePriceAndInventory') !== false) {
            return 'Fiyat ve Stok Güncelleme';
        } else if (strpos($endpoint, 'updateProducts') !== false) {
            return 'Ürün Güncelleme';
        } else {
            return 'Bilinmeyen İşlem';
        }
    }
}
