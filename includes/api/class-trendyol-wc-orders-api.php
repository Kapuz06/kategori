<?php
/**
 * Trendyol Siparişler API Sınıfı
 * 
 * Trendyol sipariş API işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Orders_API extends Trendyol_WC_API {

    /**
     * Siparişleri getir
     *
     * @param array $params Sorgu parametreleri
     * @return array|WP_Error Sipariş listesi veya hata
     */
    public function get_orders($params = array()) {
        $default_params = array(
            'size' => 100,
            'page' => 0,
            'orderByField' => 'PackageLastModifiedDate',
            'orderByDirection' => 'DESC'
        );
        
        $query_params = array_merge($default_params, $params);
        
        // Yeni endpoint'i kullan
        return $this->get("integration/order/sellers/{$this->supplier_id}/orders", $query_params);
    }
    
    public function get_order($order_number) {
        // Yeni endpoint'i kullan
        return $this->get("integration/order/sellers/{$this->supplier_id}/orders/{$order_number}");
    }
    
    public function create_package($package_data) {
        // Yeni endpoint'i kullan
        return $this->post("integration/order/sellers/{$this->supplier_id}/shipment-packages", $package_data);
    }
    
    public function update_package_status($shipment_package_id, $status) {
        $data = array(
            'lines' => array(
                array(
                    'id' => $shipment_package_id,
                    'status' => $status
                )
            )
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/order/sellers/{$this->supplier_id}/shipment-packages/update-status", $data);
    }
    
    public function update_tracking_number($shipment_package_id, $tracking_number, $cargo_provider_id) {
        $data = array(
            'lines' => array(
                array(
                    'id' => $shipment_package_id,
                    'trackingNumber' => $tracking_number,
                    'cargoProviderCode' => $cargo_provider_id
                )
            )
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/order/sellers/{$this->supplier_id}/shipment-packages/update-tracking-number", $data);
    }
    
    public function cancel_order_line($line_id, $reason) {
        $data = array(
            'lines' => array(
                array(
                    'lineId' => $line_id,
                    'cancelReason' => $reason
                )
            )
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/order/sellers/{$this->supplier_id}/cancel-order-lines", $data);
    }
    
    public function respond_to_return_request($return_id, $status, $reason) {
        $data = array(
            'returnId' => $return_id,
            'status' => $status,
            'reason' => $reason
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/order/sellers/{$this->supplier_id}/returns/respond", $data);
    }
    
    public function get_return_requests($params = array()) {
        $default_params = array(
            'size' => 100,
            'page' => 0,
            'orderByField' => 'ReturnCreateDate',
            'orderByDirection' => 'DESC'
        );
        
        $query_params = array_merge($default_params, $params);
        
        // Yeni endpoint'i kullan
        return $this->get("integration/order/sellers/{$this->supplier_id}/returns", $query_params);
    }
    
    public function get_return_request($return_id) {
        // Yeni endpoint'i kullan
        return $this->get("integration/order/sellers/{$this->supplier_id}/returns/{$return_id}");
    }

    /**
     * Trendyol siparişini WooCommerce formatına dönüştür
     *
     * @param array $trendyol_order Trendyol sipariş verileri
     * @return array WooCommerce sipariş verileri
     */
    public function format_order_for_woocommerce($trendyol_order) {
        // Müşteri bilgileri
        $customer = isset($trendyol_order['customerDetails']) ? $trendyol_order['customerDetails'] : array();
        $shipping_address = isset($trendyol_order['shipmentAddress']) ? $trendyol_order['shipmentAddress'] : array();
        $invoice_address = isset($trendyol_order['invoiceAddress']) ? $trendyol_order['invoiceAddress'] : $shipping_address;
        
        // Sipariş bilgileri
        $order_number = isset($trendyol_order['orderNumber']) ? $trendyol_order['orderNumber'] : '';
        $package_number = isset($trendyol_order['packageNumber']) ? $trendyol_order['packageNumber'] : '';
        $order_date = isset($trendyol_order['orderDate']) ? date('Y-m-d H:i:s', strtotime($trendyol_order['orderDate'])) : current_time('mysql');
        
        // WooCommerce sipariş verilerini oluştur
        $wc_order = array(
            'payment_method' => 'trendyol',
            'payment_method_title' => 'Trendyol',
            'status' => $this->get_wc_order_status_from_trendyol_status($trendyol_order['status']),
            'customer_id' => 0, // Trendyol müşterisi WooCommerce'de olmayacak
            'customer_note' => isset($trendyol_order['customerNote']) ? $trendyol_order['customerNote'] : '',
            'date_created' => $order_date,
            'billing' => array(
                'first_name' => isset($invoice_address['firstName']) ? $invoice_address['firstName'] : '',
                'last_name' => isset($invoice_address['lastName']) ? $invoice_address['lastName'] : '',
                'company' => isset($invoice_address['company']) ? $invoice_address['company'] : '',
                'address_1' => isset($invoice_address['address1']) ? $invoice_address['address1'] : '',
                'address_2' => isset($invoice_address['address2']) ? $invoice_address['address2'] : '',
                'city' => isset($invoice_address['city']) ? $invoice_address['city'] : '',
                'state' => isset($invoice_address['district']) ? $invoice_address['district'] : '',
                'postcode' => isset($invoice_address['postalCode']) ? $invoice_address['postalCode'] : '',
                'country' => 'TR',
                'email' => isset($customer['email']) ? $customer['email'] : '',
                'phone' => isset($customer['phone']) ? $customer['phone'] : ''
            ),
            'shipping' => array(
                'first_name' => isset($shipping_address['firstName']) ? $shipping_address['firstName'] : '',
                'last_name' => isset($shipping_address['lastName']) ? $shipping_address['lastName'] : '',
                'company' => isset($shipping_address['company']) ? $shipping_address['company'] : '',
                'address_1' => isset($shipping_address['address1']) ? $shipping_address['address1'] : '',
                'address_2' => isset($shipping_address['address2']) ? $shipping_address['address2'] : '',
                'city' => isset($shipping_address['city']) ? $shipping_address['city'] : '',
                'state' => isset($shipping_address['district']) ? $shipping_address['district'] : '',
                'postcode' => isset($shipping_address['postalCode']) ? $shipping_address['postalCode'] : '',
                'country' => 'TR'
            ),
            'meta_data' => array(
                array(
                    'key' => '_trendyol_order_number',
                    'value' => $order_number
                ),
                array(
                    'key' => '_trendyol_package_number',
                    'value' => $package_number
                ),
                array(
                    'key' => '_trendyol_order_status',
                    'value' => $trendyol_order['status']
                ),
                array(
                    'key' => '_trendyol_shipment_package_id',
                    'value' => isset($trendyol_order['shipmentPackageId']) ? $trendyol_order['shipmentPackageId'] : ''
                ),
                array(
                    'key' => '_trendyol_cargo_provider',
                    'value' => isset($trendyol_order['cargoProviderName']) ? $trendyol_order['cargoProviderName'] : ''
                ),
                array(
                    'key' => '_trendyol_tracking_number',
                    'value' => isset($trendyol_order['trackingNumber']) ? $trendyol_order['trackingNumber'] : ''
                ),
                array(
                    'key' => '_trendyol_last_sync',
                    'value' => current_time('mysql')
                )
            )
        );
        
        // Sipariş satırları
        if (isset($trendyol_order['lines']) && !empty($trendyol_order['lines'])) {
            $wc_order['line_items'] = array();
            
            foreach ($trendyol_order['lines'] as $line) {
                $product_id = $this->get_product_id_by_barcode($line['barcode']);
                
                $line_item = array(
                    'name' => $line['productName'],
                    'product_id' => $product_id,
                    'variation_id' => 0,
                    'quantity' => $line['quantity'],
                    'tax_class' => '',
                    'subtotal' => $line['price'],
                    'total' => $line['price'],
                    'meta_data' => array(
                        array(
                            'key' => '_trendyol_line_id',
                            'value' => $line['id']
                        ),
                        array(
                            'key' => '_trendyol_barcode',
                            'value' => $line['barcode']
                        ),
                        array(
                            'key' => '_trendyol_merchant_sku',
                            'value' => isset($line['merchantSku']) ? $line['merchantSku'] : ''
                        )
                    )
                );
                
                // Varyasyon varsa ekle
                if (isset($line['attributes']) && !empty($line['attributes'])) {
                    $line_item['meta_data'][] = array(
                        'key' => '_trendyol_attributes',
                        'value' => $line['attributes']
                    );
                    
                    // Varyasyon ürününü bul
                    $variation_id = $this->get_variation_id_by_barcode_and_attributes($line['barcode'], $line['attributes']);
                    if ($variation_id > 0) {
                        $line_item['variation_id'] = $variation_id;
                    }
                }
                
                $wc_order['line_items'][] = $line_item;
            }
        }
        
        // Kargo bilgileri
        $shipping_line = array(
            'method_id' => 'trendyol_shipping',
            'method_title' => 'Trendyol Kargo',
            'total' => '0.00'
        );
        
        $wc_order['shipping_lines'] = array($shipping_line);
        
        return $wc_order;
    }
    
    /**
     * Trendyol sipariş durumu ile WooCommerce sipariş durumu eşleştir
     *
     * @param string $trendyol_status Trendyol sipariş durumu
     * @return string WooCommerce sipariş durumu
     */
    public function get_wc_order_status_from_trendyol_status($trendyol_status) {
        $status_mappings = array(
            'Created' => 'processing',
            'Picking' => 'processing',
            'Invoiced' => 'processing',
            'Shipped' => 'completed',
            'Cancelled' => 'cancelled',
            'UnDelivered' => 'failed',
            'Delivered' => 'completed',
            'UnPacked' => 'processing',
            'Returned' => 'refunded'
        );
        
        return isset($status_mappings[$trendyol_status]) ? $status_mappings[$trendyol_status] : 'processing';
    }
    
    /**
     * WooCommerce sipariş durumu ile Trendyol sipariş durumu eşleştir
     *
     * @param string $wc_status WooCommerce sipariş durumu
     * @return string Trendyol sipariş durumu
     */
    public function get_trendyol_status_from_wc_order_status($wc_status) {
        $status_mappings = array(
            'pending' => 'Created',
            'processing' => 'Picking',
            'on-hold' => 'Created',
            'completed' => 'Shipped',
            'cancelled' => 'Cancelled',
            'refunded' => 'Returned',
            'failed' => 'UnDelivered'
        );
        
        return isset($status_mappings[$wc_status]) ? $status_mappings[$wc_status] : 'Created';
    }
    
    /**
     * Barkod ile WooCommerce ürün ID bul
     *
     * @param string $barcode Ürün barkodu
     * @return int WooCommerce ürün ID'si
     */
    private function get_product_id_by_barcode($barcode) {
        global $wpdb;
        
        // Önce doğrudan SKU ile eşleşen ürünü ara
        $product_id = wc_get_product_id_by_sku($barcode);
        
        if ($product_id > 0) {
            return $product_id;
        }
        
        // Trendyol barkodu ile meta verilerde ara
        $meta_key = '_trendyol_barcode';
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $barcode
        ));
        
        return $product_id ? (int) $product_id : 0;
    }
    
    /**
     * Barkod ve özellikler ile varyasyon ID bul
     *
     * @param string $barcode Ürün barkodu
     * @param array $attributes Ürün özellikleri
     * @return int Varyasyon ID'si
     */
    private function get_variation_id_by_barcode_and_attributes($barcode, $attributes) {
        global $wpdb;
        
        // Önce barkod ile ürün ID'sini bul
        $variation_id = wc_get_product_id_by_sku($barcode);
        
        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            
            // Ürün bir varyasyon mu kontrol et
            if ($variation && $variation->is_type('variation')) {
                return $variation_id;
            }
        }
        
        // Ürün ID'sini bul
        $product_id = $this->get_product_id_by_barcode($barcode);
        
        if ($product_id <= 0) {
            return 0;
        }
        
        // Ürün varyasyonlu mu kontrol et
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            return 0;
        }
        
        // Trendyol özellikler dizisini WooCommerce formatına dönüştür
        $wc_attributes = array();
        
        foreach ($attributes as $attr) {
            $attr_name = wc_sanitize_taxonomy_name($attr['attributeName']);
            $wc_attributes['attribute_' . $attr_name] = sanitize_title($attr['attributeValue']);
        }
        
        // Varyasyon bul
        $data_store = WC_Data_Store::load('product');
        $variation_id = $data_store->find_matching_product_variation($product, $wc_attributes);
        
        return $variation_id;
    }
}