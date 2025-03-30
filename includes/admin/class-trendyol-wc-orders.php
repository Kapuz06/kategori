<?php
/**
 * Trendyol WooCommerce Siparişler Sınıfı
 * 
 * Trendyol sipariş yönetimi admin işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Orders {

    /**
     * Sipariş senkronizasyon sınıfı
     *
     * @var Trendyol_WC_Order_Sync
     */
    protected $order_sync;
    
    /**
     * Siparişler API sınıfı
     *
     * @var Trendyol_WC_Orders_API
     */
    protected $orders_api;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->order_sync = new Trendyol_WC_Order_Sync();
        $this->orders_api = new Trendyol_WC_Orders_API();
    }

    /**
     * Siparişler sayfasını oluştur
     */
    public function render_orders_page() {
        // İşlem kontrolü
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        $message = '';
        $success = false;

        // Senkronizasyon işlemi
        if ($action === 'sync_orders' && check_admin_referer('trendyol_sync_orders')) {
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            
            $result = $this->order_sync->sync_orders($start_date, $end_date);
            
            if (is_wp_error($result)) {
                $success = false;
                $message = $result->get_error_message();
            } else {
                $success = true;
                $message = isset($result['message']) ? $result['message'] : __('Sipariş senkronizasyonu tamamlandı.', 'trendyol-woocommerce');
            }
        }
        
        // Sipariş durumu güncelleme
        if ($action === 'update_order_status' && check_admin_referer('trendyol_update_order_status')) {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $new_status = isset($_POST['trendyol_status']) ? sanitize_text_field($_POST['trendyol_status']) : '';
            
            if ($order_id > 0 && !empty($new_status)) {
                $order = wc_get_order($order_id);
                
                if ($order) {
                    $shipment_package_id = $order->get_meta('_trendyol_shipment_package_id', true);
                    
                    if (!empty($shipment_package_id)) {
                        $result = $this->orders_api->update_package_status($shipment_package_id, $new_status);
                        
                        if (is_wp_error($result)) {
                            $success = false;
                            $message = $result->get_error_message();
                        } else {
                            // Meta verileri güncelle
                            $order->update_meta_data('_trendyol_order_status', $new_status);
                            $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
                            $order->save();
                            
                            $success = true;
                            $message = __('Sipariş durumu başarıyla güncellendi.', 'trendyol-woocommerce');
                        }
                    } else {
                        $success = false;
                        $message = __('Sipariş paketi ID\'si bulunamadı.', 'trendyol-woocommerce');
                    }
                } else {
                    $success = false;
                    $message = __('Sipariş bulunamadı.', 'trendyol-woocommerce');
                }
            } else {
                $success = false;
                $message = __('Geçersiz sipariş ID\'si veya durum.', 'trendyol-woocommerce');
            }
        }
        
        // Kargo takip numarası güncelleme
        if ($action === 'update_tracking_number' && check_admin_referer('trendyol_update_tracking_number')) {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $tracking_number = isset($_POST['tracking_number']) ? sanitize_text_field($_POST['tracking_number']) : '';
            $cargo_provider_id = isset($_POST['cargo_provider_id']) ? absint($_POST['cargo_provider_id']) : 0;
            
            if ($order_id > 0 && !empty($tracking_number) && $cargo_provider_id > 0) {
                $order = wc_get_order($order_id);
                
                if ($order) {
                    $shipment_package_id = $order->get_meta('_trendyol_shipment_package_id', true);
                    
                    if (!empty($shipment_package_id)) {
                        $result = $this->orders_api->update_tracking_number($shipment_package_id, $tracking_number, $cargo_provider_id);
                        
                        if (is_wp_error($result)) {
                            $success = false;
                            $message = $result->get_error_message();
                        } else {
                            // Meta verileri güncelle
                            $order->update_meta_data('_trendyol_tracking_number', $tracking_number);
                            $order->update_meta_data('_trendyol_cargo_provider', $cargo_provider_id);
                            $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
                            $order->save();
                            
                            $success = true;
                            $message = __('Kargo takip numarası başarıyla güncellendi.', 'trendyol-woocommerce');
                        }
                    } else {
                        $success = false;
                        $message = __('Sipariş paketi ID\'si bulunamadı.', 'trendyol-woocommerce');
                    }
                } else {
                    $success = false;
                    $message = __('Sipariş bulunamadı.', 'trendyol-woocommerce');
                }
            } else {
                $success = false;
                $message = __('Geçersiz sipariş ID\'si, kargo takip numarası veya kargo firması.', 'trendyol-woocommerce');
            }
        }
        
        // Sipariş iptal etme
        if ($action === 'cancel_order' && check_admin_referer('trendyol_cancel_order')) {
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $line_id = isset($_POST['line_id']) ? absint($_POST['line_id']) : 0;
            $reason = isset($_POST['cancel_reason']) ? sanitize_text_field($_POST['cancel_reason']) : '';
            
            if ($order_id > 0 && $line_id > 0 && !empty($reason)) {
                $order = wc_get_order($order_id);
                
                if ($order) {
                    $result = $this->orders_api->cancel_order_line($line_id, $reason);
                    
                    if (is_wp_error($result)) {
                        $success = false;
                        $message = $result->get_error_message();
                    } else {
                        // Sipariş durumunu güncelle
                        $order->update_status('cancelled', __('Trendyol\'da iptal edildi: ', 'trendyol-woocommerce') . $reason);
                        
                        // Meta verileri güncelle
                        $order->update_meta_data('_trendyol_order_status', 'Cancelled');
                        $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
                        $order->save();
                        
                        $success = true;
                        $message = __('Sipariş başarıyla iptal edildi.', 'trendyol-woocommerce');
                    }
                } else {
                    $success = false;
                    $message = __('Sipariş bulunamadı.', 'trendyol-woocommerce');
                }
            } else {
                $success = false;
                $message = __('Geçersiz sipariş ID\'si, satır ID\'si veya iptal nedeni.', 'trendyol-woocommerce');
            }
        }
        
        // Trendyol siparişlerini getir
        $trendyol_orders = $this->get_trendyol_orders();
        
        // Kargo firmalarını getir
        $cargo_companies = $this->get_cargo_companies();
        
        // İptal nedenlerini getir
        $cancel_reasons = $this->get_cancel_reasons();
        
        // Şablonu göster
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/orders.php');
    }
    
    /**
     * Trendyol siparişlerini getir
     *
     * @param int $limit Limit
     * @return array Sipariş listesi
     */
    private function get_trendyol_orders($limit = 20) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_date, p.post_status, pm1.meta_value as trendyol_order_number, 
                    pm2.meta_value as trendyol_status, pm3.meta_value as last_sync
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_trendyol_order_number'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trendyol_order_status'
             LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_trendyol_last_sync'
             WHERE p.post_type = 'shop_order'
             ORDER BY p.post_date DESC
             LIMIT %d",
            $limit
        );
        
        $orders = $wpdb->get_results($sql);
        
        // Sipariş nesnelerini oluştur
        $result = array();
        
        foreach ($orders as $order_data) {
            $order = wc_get_order($order_data->ID);
            
            if ($order) {
                $result[] = array(
                    'id' => $order_data->ID,
                    'order' => $order,
                    'trendyol_order_number' => $order_data->trendyol_order_number,
                    'trendyol_status' => $order_data->trendyol_status,
                    'last_sync' => $order_data->last_sync,
                    'tracking_number' => $order->get_meta('_trendyol_tracking_number', true),
                    'cargo_provider' => $order->get_meta('_trendyol_cargo_provider', true)
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Kargo firmalarını getir
     *
     * @return array Kargo firmaları
     */
    private function get_cargo_companies() {
        return array(
            '1' => 'Aras Kargo',
            '2' => 'Sürat Kargo',
            '3' => 'MNG Kargo',
            '4' => 'PTT Kargo',
            '5' => 'UPS Kargo',
            '6' => 'Yurtiçi Kargo'
            // Diğer kargo firmaları eklenebilir
        );
    }
    
    /**
     * İptal nedenlerini getir
     *
     * @return array İptal nedenleri
     */
    private function get_cancel_reasons() {
        return array(
            'OUT_OF_STOCK' => __('Stok yok', 'trendyol-woocommerce'),
            'CUSTOMER_REQUEST' => __('Müşteri talebi', 'trendyol-woocommerce'),
            'DUPLICATE_ORDER' => __('Tekrarlanan sipariş', 'trendyol-woocommerce'),
            'LATE_SHIPMENT' => __('Geç sevkiyat', 'trendyol-woocommerce'),
            'WRONG_PRICE' => __('Yanlış fiyat', 'trendyol-woocommerce'),
            'OTHER' => __('Diğer', 'trendyol-woocommerce')
        );
    }
}