<?php
/**
 * Trendyol WooCommerce Sipariş Webhook İşleyici Sınıfı
 * 
 * Trendyol sipariş webhook'larını işleyen sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Webhook_Orders {

    /**
     * Sipariş senkronizasyonu sınıfı
     *
     * @var Trendyol_WC_Order_Sync
     */
    protected $order_sync;

    /**
	 * Yapılandırıcı
	 */
	public function __construct() {
		// Trendyol_WC_Order_Sync sınıfının varlığını kontrol et
		if (!class_exists('Trendyol_WC_Order_Sync')) {
			require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/sync/class-trendyol-wc-order-sync.php';
		}
		
		$this->order_sync = new Trendyol_WC_Order_Sync();
	}

    /**
     * Sipariş webhook'unu işle
     *
     * @param string $event_type Webhook olay türü
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    public function process_order_webhook($event_type, $payload) {
        // Sipariş bilgilerini al
        $order_number = isset($payload['orderNumber']) ? $payload['orderNumber'] : null;
        
        if (empty($order_number)) {
            return array(
                'success' => false,
                'message' => 'Order number is missing in payload'
            );
        }
        
        // Olay türüne göre işleme
        switch ($event_type) {
            case 'order-created':
                return $this->handle_order_created($payload);
                
            case 'order-updated':
            case 'order-status-changed':
                return $this->handle_order_updated($payload);
                
            case 'order-package-created':
            case 'order-package-updated':
                return $this->handle_package_updated($payload);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unsupported order event: ' . $event_type
                );
        }
    }

    /**
     * Yeni sipariş oluşturma olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_order_created($payload) {
        // Sipariş numarasını al
        $order_number = isset($payload['orderNumber']) ? $payload['orderNumber'] : '';
        
        // Sipariş zaten mevcut mu kontrol et
        $existing_order_id = $this->get_order_id_by_trendyol_number($order_number);
        
        if ($existing_order_id) {
            // Sipariş zaten mevcut, güncelleme yap
            return $this->handle_order_updated($payload);
        }
        
        // Mağaza bilgilerini kontrol et
        $settings = get_option('trendyol_wc_settings', array());
        $supplier_id = isset($settings['supplier_id']) ? $settings['supplier_id'] : '';
        
        if (!empty($supplier_id) && isset($payload['supplierId']) && $payload['supplierId'] != $supplier_id) {
            return array(
                'success' => false,
                'message' => 'Order belongs to different supplier'
            );
        }
        
        try {
            // Sipariş detaylarını Trendyol API'den al
            $orders_api = new Trendyol_WC_Orders_API();
            $order_details = $orders_api->get_order($order_number);
            
            if (is_wp_error($order_details)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to get order details: ' . $order_details->get_error_message()
                );
            }
            
            // Siparişi WooCommerce'e ekle
            $result = $this->order_sync->create_wc_order_from_trendyol($order_details);
            
            if (!$result) {
                return array(
                    'success' => false,
                    'message' => 'Failed to create order in WooCommerce'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $result
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing order: ' . $e->getMessage()
            );
        }
    }

    /**
     * Sipariş güncelleme olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_order_updated($payload) {
        // Sipariş numarasını al
        $order_number = isset($payload['orderNumber']) ? $payload['orderNumber'] : '';
        
        // Sipariş ID'sini bul
        $order_id = $this->get_order_id_by_trendyol_number($order_number);
        
        if (!$order_id) {
            // Sipariş mevcut değil, yeni oluştur
            return $this->handle_order_created($payload);
        }
        
        try {
            // Sipariş detaylarını Trendyol API'den al
            $orders_api = new Trendyol_WC_Orders_API();
            $order_details = $orders_api->get_order($order_number);
            
            if (is_wp_error($order_details)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to get order details: ' . $order_details->get_error_message()
                );
            }
            
            // Siparişi WooCommerce'de güncelle
            $result = $this->order_sync->update_wc_order_from_trendyol($order_id, $order_details);
            
            if (!$result) {
                return array(
                    'success' => false,
                    'message' => 'Failed to update order in WooCommerce'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Order updated successfully',
                'order_id' => $order_id
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing order update: ' . $e->getMessage()
            );
        }
    }

    /**
     * Paket güncelleme olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_package_updated($payload) {
        // Sipariş numarasını al
        $order_number = isset($payload['orderNumber']) ? $payload['orderNumber'] : '';
        $package_id = isset($payload['packageId']) ? $payload['packageId'] : '';
        
        if (empty($order_number) || empty($package_id)) {
            return array(
                'success' => false,
                'message' => 'Order number or package ID missing'
            );
        }
        
        // Sipariş ID'sini bul
        $order_id = $this->get_order_id_by_trendyol_number($order_number);
        
        if (!$order_id) {
            // Sipariş mevcut değil, yeni oluştur
            return $this->handle_order_created($payload);
        }
        
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return array(
                    'success' => false,
                    'message' => 'Order not found in WooCommerce'
                );
            }
            
            // Paket durumunu güncelle
            $order->update_meta_data('_trendyol_shipment_package_id', $package_id);
            
            // Paket durumu
            if (isset($payload['status'])) {
                $trendyol_status = $payload['status'];
                $order->update_meta_data('_trendyol_order_status', $trendyol_status);
                
                // WooCommerce sipariş durumunu güncellemek için API'yi kullan
                $orders_api = new Trendyol_WC_Orders_API();
                $wc_status = $orders_api->get_wc_order_status_from_trendyol_status($trendyol_status);
                
                if ($wc_status !== $order->get_status()) {
                    $order->set_status($wc_status);
                }
            }
            
            // Kargo bilgileri
            if (isset($payload['trackingNumber'])) {
                $order->update_meta_data('_trendyol_tracking_number', $payload['trackingNumber']);
            }
            
            if (isset($payload['cargoProviderName'])) {
                $order->update_meta_data('_trendyol_cargo_provider', $payload['cargoProviderName']);
            }
            
            // Son güncelleme zamanı
            $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            
            // Sipariş notunu ekle
            $order->add_order_note(sprintf(
                __('Sipariş Trendyol webhook ile güncellendi. Paket ID: %s, Durum: %s', 'trendyol-woocommerce'),
                $package_id,
                isset($payload['status']) ? $payload['status'] : 'Bilinmiyor'
            ));
            
            // Siparişi kaydet
            $order->save();
            
            return array(
                'success' => true,
                'message' => 'Package updated successfully',
                'order_id' => $order_id
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing package update: ' . $e->getMessage()
            );
        }
    }

    /**
     * Trendyol sipariş numarasına göre WooCommerce sipariş ID'sini bul
     *
     * @param string $order_number Trendyol sipariş numarası
     * @return int|false WooCommerce sipariş ID'si veya bulunamadıysa false
     */
    protected function get_order_id_by_trendyol_number($order_number) {
        global $wpdb;
        
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
             WHERE meta_key = '_trendyol_order_number' 
             AND meta_value = %s 
             LIMIT 1",
            $order_number
        ));
        
        return $order_id ? (int) $order_id : false;
    }
}