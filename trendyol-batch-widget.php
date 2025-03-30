<?php
/**
 * Trendyol Batch İşlem Durumu Widget'ı
 * 
 * Trendyol'a yapılan toplu ürün işlemlerinin durumunu gösteren widget
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

class Trendyol_WC_Batch_Widget {
    
    /**
     * Yapılandırıcı
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_trendyol_check_batch_status', array($this, 'ajax_check_batch_status'));
    }
    
    /**
     * Dashboard widget'ını ekle
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'trendyol_batch_status_widget',
            __('Trendyol İşlem Durumları', 'trendyol-woocommerce'),
            array($this, 'display_widget')
        );
    }
    
    /**
     * Widget script ve stillerini yükle
     */
    public function enqueue_scripts($hook) {
        if ('index.php' != $hook) {
            return;
        }
        
        wp_enqueue_style(
            'trendyol-batch-widget-css', 
            TRENDYOL_WC_PLUGIN_URL . 'assets/css/batch-widget.css',
            array(),
            TRENDYOL_WC_VERSION
        );
        
        wp_enqueue_script(
            'trendyol-batch-widget-js',
            TRENDYOL_WC_PLUGIN_URL . 'assets/js/batch-widget.js',
            array('jquery'),
            TRENDYOL_WC_VERSION,
            true
        );
        
        wp_localize_script('trendyol-batch-widget-js', 'trendyol_batch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('trendyol-batch-nonce'),
            'checking' => __('Kontrol ediliyor...', 'trendyol-woocommerce'),
            'refresh' => __('Yenile', 'trendyol-woocommerce'),
            'no_requests' => __('İşlenecek toplu istek bulunamadı.', 'trendyol-woocommerce')
        ));
    }
    
    /**
     * Widget içeriğini göster
     */
    public function display_widget() {
        $batch_requests = $this->get_recent_batch_requests();
        ?>
        <div class="trendyol-batch-widget">
            <div class="trendyol-batch-header">
                <h3><?php _e('Son Trendyol Toplu İşlemleri', 'trendyol-woocommerce'); ?></h3>
                <button id="trendyol-refresh-batch" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span> <?php _e('Yenile', 'trendyol-woocommerce'); ?>
                </button>
            </div>
            
            <div id="trendyol-batch-list" class="trendyol-batch-list">
                <?php $this->display_batch_list($batch_requests); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Batch listesini göster
     */
    private function display_batch_list($batch_requests) {
        if (empty($batch_requests)) {
            echo '<p class="trendyol-no-batch">' . __('Henüz toplu işlem kaydı bulunmuyor.', 'trendyol-woocommerce') . '</p>';
            return;
        }
        
        echo '<table class="trendyol-batch-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Batch ID', 'trendyol-woocommerce') . '</th>';
        echo '<th>' . __('İşlem Tipi', 'trendyol-woocommerce') . '</th>';
        echo '<th>' . __('Durum', 'trendyol-woocommerce') . '</th>';
        echo '<th>' . __('Tarih', 'trendyol-woocommerce') . '</th>';
        echo '<th>' . __('İşlemler', 'trendyol-woocommerce') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($batch_requests as $request) {
            $status_class = $this->get_status_class($request['status']);
            $date_format = get_option('date_format') . ' ' . get_option('time_format');
            $date = date_i18n($date_format, $request['date']);
            
            echo '<tr data-batch-id="' . esc_attr($request['id']) . '">';
            echo '<td class="batch-id">' . esc_html($request['id']) . '</td>';
            echo '<td>' . esc_html($request['type']) . '</td>';
            echo '<td class="batch-status ' . esc_attr($status_class) . '">' . esc_html($request['status']) . '</td>';
            echo '<td>' . esc_html($date) . '</td>';
            echo '<td><button class="button check-batch-status" data-batch-id="' . esc_attr($request['id']) . '">' . 
                __('Durumu Kontrol Et', 'trendyol-woocommerce') . '</button></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Durum sınıfını al
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'COMPLETED':
                return 'status-success';
            case 'PROCESSING':
                return 'status-processing';
            case 'FAILED':
                return 'status-failed';
            default:
                return 'status-unknown';
        }
    }
    
    /**
     * Son batch isteklerini al
     */
    private function get_recent_batch_requests() {
        global $wpdb;
        
        $batch_requests = get_option('trendyol_batch_requests', array());
        
        // İlk kez çalıştırıldıysa ve veri yoksa örnek veri oluştur
        if (empty($batch_requests)) {
            return array();
        }
        
        // En son 10 isteği göster, en yeniler önce
        $batch_requests = array_slice(array_reverse($batch_requests), 0, 10);
        
        return $batch_requests;
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
        $this->update_batch_status($batch_id, $response);
        
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
            'statusClass' => $this->get_status_class(isset($response['status']) ? $response['status'] : 'UNKNOWN'),
            'errorReasons' => $error_reasons
        ));
    }
    
    /**
     * Batch durumunu güncelle
     */
    private function update_batch_status($batch_id, $response) {
        $batch_requests = get_option('trendyol_batch_requests', array());
        
        foreach ($batch_requests as $key => $request) {
            if ($request['id'] === $batch_id) {
                $batch_requests[$key]['status'] = isset($response['status']) ? $response['status'] : 'UNKNOWN';
                $batch_requests[$key]['last_check'] = time();
                break;
            }
        }
        
        update_option('trendyol_batch_requests', $batch_requests);
    }
}
