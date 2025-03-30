<?php
/**
 * Trendyol WooCommerce Sipariş Senkronizasyon Sınıfı
 * 
 * Sipariş senkronizasyonu işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Order_Sync {

    /**
     * Sipariş API'si
     *
     * @var Trendyol_WC_Orders_API
     */
    protected $orders_api;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->orders_api = new Trendyol_WC_Orders_API();
    }

    /**
     * Siparişleri senkronize et
     *
     * @param string $start_date Başlangıç tarihi (Y-m-d)
     * @param string $end_date Bitiş tarihi (Y-m-d)
     * @param array $args Ek parametreler
     * @return array|WP_Error Senkronizasyon sonuçları veya hata
     */
    public function sync_orders($start_date = '', $end_date = '', $args = array()) {
        // Varsayılan argümanlar
        $default_args = array(
            'page' => 0,
            'size' => 50,
            'skip_existing' => false,
            'order_statuses' => array() // Belirli sipariş durumlarını filtrelemek için
        );
        
        $args = wp_parse_args($args, $default_args);
        
        // Tarih kontrolü
        if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-7 days')); // Varsayılan olarak son 7 gün
        }
        
        if (empty($end_date)) {
            $end_date = date('Y-m-d'); // Bugün
        }
        
        // Trendyol siparişlerini getir
        $response = $this->orders_api->get_orders_by_date_range($start_date, $end_date, array(
            'page' => $args['page'],
            'size' => $args['size']
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Sonuçlar
        $orders = isset($response['content']) ? $response['content'] : array();
        $total_count = isset($response['totalElements']) ? $response['totalElements'] : 0;
        
        if (empty($orders)) {
            return array(
                'success' => true,
                'message' => __('İçe aktarılacak sipariş bulunamadı.', 'trendyol-woocommerce'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            );
        }
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($orders as $trendyol_order) {
            // Sipariş durumu filtresi
            if (!empty($args['order_statuses'])) {
                if (!in_array($trendyol_order['status'], $args['order_statuses'])) {
                    $skipped++;
                    continue;
                }
            }
            
            // Sipariş numarası kontrolü
            $order_number = isset($trendyol_order['orderNumber']) ? $trendyol_order['orderNumber'] : '';
            
            if (empty($order_number)) {
                $skipped++;
                continue;
            }
            
            // Mevcut siparişi bul
            $existing_order_id = $this->get_order_id_by_trendyol_number($order_number);
            
            if ($existing_order_id) {
                // Mevcut siparişi güncelle
                if ($args['skip_existing']) {
                    $skipped++;
                    continue;
                }
                
                $result = $this->update_wc_order_from_trendyol($existing_order_id, $trendyol_order);
                
                if ($result) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Yeni sipariş oluştur
                $result = $this->create_wc_order_from_trendyol($trendyol_order);
                
                if ($result) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        
        // Sonraki sayfa varsa devam et
        $total_pages = isset($response['totalPages']) ? $response['totalPages'] : 1;
        
        if ($args['page'] < $total_pages - 1) {
            $next_args = $args;
            $next_args['page'] = $args['page'] + 1;
            
            $next_result = $this->sync_orders($start_date, $end_date, $next_args);
            
            if (!is_wp_error($next_result)) {
                if (isset($next_result['imported'])) {
                    $imported += $next_result['imported'];
                }
                
                if (isset($next_result['updated'])) {
                    $updated += $next_result['updated'];
                }
                
                if (isset($next_result['skipped'])) {
                    $skipped += $next_result['skipped'];
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d sipariş içe aktarıldı, %d sipariş güncellendi, %d sipariş atlandı.', 'trendyol-woocommerce'), $imported, $updated, $skipped),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $total_count
        );
    }

    /**
     * Son siparişleri senkronize et
     *
     * @param int $hours Saat sayısı
     * @param array $args Ek parametreler
     * @return array|WP_Error Senkronizasyon sonuçları veya hata
     */
    public function sync_recent_orders($hours = 24, $args = array()) {
        $end_date = date('Y-m-d H:i:s');
        $start_date = date('Y-m-d H:i:s', strtotime('-' . $hours . ' hours'));
        
        // Trendyol siparişlerini getir
        $response = $this->orders_api->get_recent_orders();
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Sonuçlar
        $orders = isset($response['content']) ? $response['content'] : array();
        $total_count = isset($response['totalElements']) ? $response['totalElements'] : 0;
        
        if (empty($orders)) {
            return array(
                'success' => true,
                'message' => __('Son ' . $hours . ' saatte içe aktarılacak sipariş bulunamadı.', 'trendyol-woocommerce'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            );
        }
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($orders as $trendyol_order) {
            // Sipariş numarası kontrolü
            $order_number = isset($trendyol_order['orderNumber']) ? $trendyol_order['orderNumber'] : '';
            
            if (empty($order_number)) {
                $skipped++;
                continue;
            }
            
            // Mevcut siparişi bul
            $existing_order_id = $this->get_order_id_by_trendyol_number($order_number);
            
            if ($existing_order_id) {
                // Mevcut siparişi güncelle
                $result = $this->update_wc_order_from_trendyol($existing_order_id, $trendyol_order);
                
                if ($result) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Yeni sipariş oluştur
                $result = $this->create_wc_order_from_trendyol($trendyol_order);
                
                if ($result) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d sipariş içe aktarıldı, %d sipariş güncellendi, %d sipariş atlandı.', 'trendyol-woocommerce'), $imported, $updated, $skipped),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($orders)
        );
    }

    /**
     * Trendyol sipariş numarasına göre WooCommerce sipariş ID'sini bul
     *
     * @param string $order_number Trendyol sipariş numarası
     * @return int|false WooCommerce sipariş ID'si veya bulunamadıysa false
     */
    private function get_order_id_by_trendyol_number($order_number) {
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

    /**
     * Trendyol sipariş verilerinden WooCommerce siparişi oluştur
     *
     * @param array $trendyol_order Trendyol sipariş verileri
     * @return int|false Sipariş ID'si veya başarısız ise false
     */
    public function create_wc_order_from_trendyol($trendyol_order) {
        // Gerekli alanları kontrol et
        if (empty($trendyol_order['orderNumber'])) {
            return false;
        }
        
        // WooCommerce formatına dönüştür
        $wc_order_data = $this->orders_api->format_order_for_woocommerce($trendyol_order);
        
        try {
            // Sipariş oluştur
            $order = wc_create_order();
            
            // Sipariş bilgilerini ayarla
            if (isset($wc_order_data['status'])) {
                $order->set_status($wc_order_data['status']);
            }
            
            if (isset($wc_order_data['date_created'])) {
                $order->set_date_created($wc_order_data['date_created']);
            }
            
            // Müşteri notu
            if (isset($wc_order_data['customer_note'])) {
                $order->set_customer_note($wc_order_data['customer_note']);
            }
            
            // Ödeme metodu
            if (isset($wc_order_data['payment_method'])) {
                $order->set_payment_method($wc_order_data['payment_method']);
                $order->set_payment_method_title($wc_order_data['payment_method_title']);
            }
            
            // Fatura bilgileri
            if (isset($wc_order_data['billing'])) {
                $order->set_billing_first_name($wc_order_data['billing']['first_name']);
                $order->set_billing_last_name($wc_order_data['billing']['last_name']);
                $order->set_billing_company($wc_order_data['billing']['company']);
                $order->set_billing_address_1($wc_order_data['billing']['address_1']);
                $order->set_billing_address_2($wc_order_data['billing']['address_2']);
                $order->set_billing_city($wc_order_data['billing']['city']);
                $order->set_billing_state($wc_order_data['billing']['state']);
                $order->set_billing_postcode($wc_order_data['billing']['postcode']);
                $order->set_billing_country($wc_order_data['billing']['country']);
                $order->set_billing_email($wc_order_data['billing']['email']);
                $order->set_billing_phone($wc_order_data['billing']['phone']);
            }
            
            // Teslimat bilgileri
            if (isset($wc_order_data['shipping'])) {
                $order->set_shipping_first_name($wc_order_data['shipping']['first_name']);
                $order->set_shipping_last_name($wc_order_data['shipping']['last_name']);
                $order->set_shipping_company($wc_order_data['shipping']['company']);
                $order->set_shipping_address_1($wc_order_data['shipping']['address_1']);
                $order->set_shipping_address_2($wc_order_data['shipping']['address_2']);
                $order->set_shipping_city($wc_order_data['shipping']['city']);
                $order->set_shipping_state($wc_order_data['shipping']['state']);
                $order->set_shipping_postcode($wc_order_data['shipping']['postcode']);
                $order->set_shipping_country($wc_order_data['shipping']['country']);
            }
            
            // Ürün satırları
            if (isset($wc_order_data['line_items'])) {
                foreach ($wc_order_data['line_items'] as $item) {
                    $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                    $variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;
                    $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                    
                    $order_item_id = $order->add_product(
                        wc_get_product($variation_id > 0 ? $variation_id : $product_id),
                        $quantity,
                        array(
                            'subtotal' => isset($item['subtotal']) ? $item['subtotal'] : 0,
                            'total' => isset($item['total']) ? $item['total'] : 0
                        )
                    );
                    
                    // Ürün meta verileri
                    if (isset($item['meta_data'])) {
                        foreach ($item['meta_data'] as $meta) {
                            wc_add_order_item_meta($order_item_id, $meta['key'], $meta['value']);
                        }
                    }
                }
            }
            
            // Kargo satırları
            if (isset($wc_order_data['shipping_lines'])) {
                foreach ($wc_order_data['shipping_lines'] as $shipping) {
                    $shipping_item = new WC_Order_Item_Shipping();
                    $shipping_item->set_method_title($shipping['method_title']);
                    $shipping_item->set_method_id($shipping['method_id']);
                    $shipping_item->set_total($shipping['total']);
                    $order->add_item($shipping_item);
                }
            }
            
            // Meta veriler
            if (isset($wc_order_data['meta_data'])) {
                foreach ($wc_order_data['meta_data'] as $meta) {
                    $order->update_meta_data($meta['key'], $meta['value']);
                }
            }
            
            // Sipariş toplamlarını hesapla
            $order->calculate_totals();
            
            // Siparişi kaydet
            $order->save();
            
            // Sipariş notu ekle
            $order->add_order_note(__('Sipariş Trendyol\'dan içe aktarıldı.', 'trendyol-woocommerce'));
            
            return $order->get_id();
            
        } catch (Exception $e) {
            // Hata durumunda loglama
            error_log('Trendyol WC - Sipariş oluşturma hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mevcut WooCommerce siparişini Trendyol verilerine göre güncelle
     *
     * @param int $order_id WooCommerce sipariş ID'si
     * @param array $trendyol_order Trendyol sipariş verileri
     * @return bool Güncelleme başarılı ise true
     */
    public function update_wc_order_from_trendyol($order_id, $trendyol_order) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        try {
            // Sipariş durumunu güncelle
            $order_status = $this->orders_api->get_wc_order_status_from_trendyol_status($trendyol_order['status']);
            $order->set_status($order_status);
            
            // Meta verileri güncelle
            $order->update_meta_data('_trendyol_order_status', $trendyol_order['status']);
            $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            
            // Kargo bilgileri varsa güncelle
            if (isset($trendyol_order['cargoTrackingNumber']) && !empty($trendyol_order['cargoTrackingNumber'])) {
                $order->update_meta_data('_trendyol_tracking_number', $trendyol_order['cargoTrackingNumber']);
            }
            
            if (isset($trendyol_order['cargoProviderName']) && !empty($trendyol_order['cargoProviderName'])) {
                $order->update_meta_data('_trendyol_cargo_provider', $trendyol_order['cargoProviderName']);
            }
            
            if (isset($trendyol_order['shipmentPackageId']) && !empty($trendyol_order['shipmentPackageId'])) {
                $order->update_meta_data('_trendyol_shipment_package_id', $trendyol_order['shipmentPackageId']);
            }
            
            // Siparişi kaydet
            $order->save();
            
            // Sipariş notu ekle
            $order->add_order_note(sprintf(
                __('Sipariş Trendyol\'dan güncellendi. Trendyol Durumu: %s', 'trendyol-woocommerce'),
                $trendyol_order['status']
            ));
            
            return true;
        } catch (Exception $e) {
            // Hata durumunda loglama
            error_log('Trendyol WC - Sipariş güncelleme hatası: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Trendyol üzerinde sipariş durumunu güncelle
     *
     * @param int $order_id WooCommerce sipariş ID'si
     * @param string $trendyol_status Trendyol sipariş durumu
     * @return bool|WP_Error Başarılı ise true, başarısız ise hata
     */
    public function update_trendyol_order_status($order_id, $trendyol_status) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Sipariş bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // Trendyol sipariş bilgilerini kontrol et
        $shipment_package_id = $order->get_meta('_trendyol_shipment_package_id', true);
        
        if (empty($shipment_package_id)) {
            return new WP_Error('missing_package_id', __('Trendyol sevkiyat paketi ID\'si bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // Sipariş durumunu güncelle
        $result = $this->orders_api->update_package_status($shipment_package_id, $trendyol_status);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Meta verileri güncelle
        $order->update_meta_data('_trendyol_order_status', $trendyol_status);
        $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
        $order->save();
        
        // Sipariş notu ekle
        $order->add_order_note(sprintf(
            __('Sipariş durumu Trendyol\'da güncellendi: %s', 'trendyol-woocommerce'),
            $trendyol_status
        ));
        
        return true;
    }

    /**
     * Trendyol üzerinde kargo takip numarasını güncelle
     *
     * @param int $order_id WooCommerce sipariş ID'si
     * @param string $tracking_number Kargo takip numarası
     * @param int $cargo_provider_id Kargo firması ID'si
     * @return bool|WP_Error Başarılı ise true, başarısız ise hata
     */
    public function update_trendyol_tracking_number($order_id, $tracking_number, $cargo_provider_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Sipariş bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // Trendyol sipariş bilgilerini kontrol et
        $shipment_package_id = $order->get_meta('_trendyol_shipment_package_id', true);
        
        if (empty($shipment_package_id)) {
            return new WP_Error('missing_package_id', __('Trendyol sevkiyat paketi ID\'si bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // Kargo takip numarasını güncelle
        $result = $this->orders_api->update_tracking_number($shipment_package_id, $tracking_number, $cargo_provider_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Meta verileri güncelle
        $order->update_meta_data('_trendyol_tracking_number', $tracking_number);
        $order->update_meta_data('_trendyol_cargo_provider', $cargo_provider_id);
        $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
        $order->save();
        
        // Sipariş notu ekle
        $order->add_order_note(sprintf(
            __('Kargo takip numarası Trendyol\'da güncellendi: %s', 'trendyol-woocommerce'),
            $tracking_number
        ));
        
        return true;
    }

    /**
     * Trendyol üzerinde sipariş iptal et
     *
     * @param int $order_id WooCommerce sipariş ID'si
     * @param int $line_id Trendyol sipariş satır ID'si
     * @param string $reason İptal nedeni
     * @return bool|WP_Error Başarılı ise true, başarısız ise hata
     */
    public function cancel_trendyol_order($order_id, $line_id, $reason) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Sipariş bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // Sipariş iptal et
        $result = $this->orders_api->cancel_order_line($line_id, $reason);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Meta verileri güncelle
        $order->update_meta_data('_trendyol_order_status', 'Cancelled');
        $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
        $order->save();
        
        // WooCommerce sipariş durumunu güncelle
        $order->update_status('cancelled', sprintf(
            __('Trendyol\'da iptal edildi: %s', 'trendyol-woocommerce'),
            $reason
        ));
        
        return true;
    }

    /**
     * Senkronizasyon hatalarını logla
     *
     * @param string $message Hata mesajı
     * @param array $data İlgili veriler
     */
    private function log_error($message, $data = array()) {
        // Debug modu kontrolü
        $settings = get_option('trendyol_wc_settings', array());
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : 'no';
        
        if ($debug_mode !== 'yes') {
            return;
        }
        
        // Log dosyası
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/order-sync-' . date('Y-m-d') . '.log';
        
        // Log dizini kontrolü
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Log kaydı
        $log_entry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($data) . "\n";
        error_log($log_entry, 3, $log_file);
    }
}