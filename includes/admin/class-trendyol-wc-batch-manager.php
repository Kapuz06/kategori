<?php
/**
 * Trendyol WooCommerce Batch İşlemleri Yöneticisi
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

class Trendyol_WC_Batch_Manager {
    
    /**
     * Yapılandırıcı
     */
    public function __construct() {
        // Admin menüsüne sayfayı ekle
        
        
        // AJAX işleyicileri
        add_action('wp_ajax_trendyol_check_batch_status', array($this, 'ajax_check_batch_status'));
        add_action('wp_ajax_trendyol_delete_batch_request', array($this, 'ajax_delete_batch_request'));
    }
    
    
    
    /**
     * Batch istekleri sayfasını oluştur
     */
    public function render_batch_requests_page() {
        // Sayfalama için ayarlar
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Batch isteklerini al
        $batch_requests = get_option('trendyol_batch_requests', array());
        $total_items = count($batch_requests);
        
        // En son istek en üstte olacak şekilde sırala
        $batch_requests = array_reverse($batch_requests);
        
        // Sayfa için istekleri al
        $offset = ($current_page - 1) * $per_page;
        $batch_requests_paged = array_slice($batch_requests, $offset, $per_page);
        
        // Toplam sayfa sayısı
        $total_pages = ceil($total_items / $per_page);
        
        // Şablonu göster
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/batch-requests.php');
    }
    
    /**
     * AJAX ile batch durumunu kontrol et
     */
    public function ajax_check_batch_status() {
        // Nonce kontrolü
        check_ajax_referer('trendyol-batch-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Yetkiniz bulunmuyor.', 'trendyol-woocommerce')));
            return;
        }
        
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_send_json_error(array('message' => __('Geçersiz batch ID.', 'trendyol-woocommerce')));
            return;
        }
        
        // Trendyol API'si üzerinden batch durumunu kontrol et
        $api = new Trendyol_WC_API();
        $settings = get_option('trendyol_wc_settings', array());
        $seller_id = isset($settings['supplier_id']) ? $settings['supplier_id'] : '';
        
        if (empty($seller_id)) {
            wp_send_json_error(array('message' => __('Satıcı ID ayarlanmamış.', 'trendyol-woocommerce')));
            return;
        }
        
        $endpoint = "integration/product/sellers/{$seller_id}/products/batch-requests/{$batch_id}";
        $response = $api->get($endpoint);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
            return;
        }
        
        // Batch durumunu veritabanında güncelle
        $batch_requests = get_option('trendyol_batch_requests', array());
        $updated = false;
        
        foreach ($batch_requests as $key => $request) {
            if ($request['id'] === $batch_id) {
                $batch_requests[$key]['status'] = isset($response['status']) ? $response['status'] : 'UNKNOWN';
                $batch_requests[$key]['last_check'] = time();
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            update_option('trendyol_batch_requests', $batch_requests);
        }
        
        // Başarılı ve başarısız öğeleri say
        $total_items = isset($response['itemCount']) ? intval($response['itemCount']) : 0;
        $failed_items = isset($response['failedItemCount']) ? intval($response['failedItemCount']) : 0;
        $success_items = $total_items - $failed_items;
        
        // Hata nedenlerini toplama
        $error_reasons = array();
        if (isset($response['items']) && is_array($response['items'])) {
            foreach ($response['items'] as $item) {
                if (isset($item['status']) && $item['status'] !== 'SUCCESS' && !empty($item['failureReasons'])) {
                    foreach ($item['failureReasons'] as $reason) {
                        $error_reasons[] = isset($reason['message']) ? $reason['message'] : json_encode($reason);
                    }
                }
            }
        }
        
        wp_send_json_success(array(
            'status' => isset($response['status']) ? $response['status'] : 'UNKNOWN',
            'creationDate' => isset($response['creationDate']) ? $response['creationDate'] : 0,
            'lastModification' => isset($response['lastModification']) ? $response['lastModification'] : 0,
            'batchRequestType' => isset($response['batchRequestType']) ? $response['batchRequestType'] : '',
            'totalItems' => $total_items,
            'failedItems' => $failed_items,
            'successItems' => $success_items,
            'errorReasons' => $error_reasons
        ));
    }
    
    /**
     * AJAX ile batch isteğini sil
     */
    public function ajax_delete_batch_request() {
        // Nonce kontrolü
        check_ajax_referer('trendyol-batch-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Yetkiniz bulunmuyor.', 'trendyol-woocommerce')));
            return;
        }
        
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_send_json_error(array('message' => __('Geçersiz batch ID.', 'trendyol-woocommerce')));
            return;
        }
        
        // Batch isteklerini al
        $batch_requests = get_option('trendyol_batch_requests', array());
        $found = false;
        
        // İsteği bul ve sil
        foreach ($batch_requests as $key => $request) {
            if ($request['id'] === $batch_id) {
                unset($batch_requests[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // Dizin anahtarlarını yeniden düzenle
            $batch_requests = array_values($batch_requests);
            update_option('trendyol_batch_requests', $batch_requests);
            wp_send_json_success(array('message' => __('İşlem kaydı başarıyla silindi.', 'trendyol-woocommerce')));
        } else {
            wp_send_json_error(array('message' => __('İşlem kaydı bulunamadı.', 'trendyol-woocommerce')));
        }
    }
}

// Sınıfı başlat
new Trendyol_WC_Batch_Manager();
