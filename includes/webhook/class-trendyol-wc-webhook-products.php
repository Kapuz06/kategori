<?php
/**
 * Trendyol WooCommerce Ürün Webhook İşleyici Sınıfı
 * 
 * Trendyol ürün webhook'larını işleyen sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Webhook_Products {

    /**
     * Ürün senkronizasyonu sınıfı
     *
     * @var Trendyol_WC_Product_Sync
     */
    protected $product_sync;

    /**
	 * Yapılandırıcı
	 */
	public function __construct() {
		// Trendyol_WC_Product_Sync sınıfının varlığını kontrol et
		if (!class_exists('Trendyol_WC_Product_Sync')) {
			require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/sync/class-trendyol-wc-product-sync.php';
		}
		
		$this->product_sync = new Trendyol_WC_Product_Sync();
	}

    /**
     * Ürün webhook'unu işle
     *
     * @param string $event_type Webhook olay türü
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    public function process_product_webhook($event_type, $payload) {
        // Ürün bilgilerini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : null;
        $product_id = isset($payload['productId']) ? $payload['productId'] : null;
        
        if (empty($barcode) && empty($product_id)) {
            return array(
                'success' => false,
                'message' => 'Product barcode or ID is missing in payload'
            );
        }
        
        // Olay türüne göre işleme
        switch ($event_type) {
            case 'product-created':
                return $this->handle_product_created($payload);
                
            case 'product-updated':
                return $this->handle_product_updated($payload);
                
            case 'product-stock-updated':
                return $this->handle_stock_updated($payload);
                
            case 'product-price-updated':
                return $this->handle_price_updated($payload);
                
            case 'product-status-changed':
                return $this->handle_status_changed($payload);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unsupported product event: ' . $event_type
                );
        }
    }

    /**
     * Yeni ürün oluşturma olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_product_created($payload) {
        // Barkod bilgisini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : '';
        $product_id = isset($payload['productId']) ? $payload['productId'] : '';
        
        // Ürün zaten mevcut mu kontrol et
        $existing_product_id = $this->get_product_id_by_barcode_or_trendyol_id($barcode, $product_id);
        
        if ($existing_product_id) {
            // Ürün zaten mevcut, güncelleme yap
            return $this->handle_product_updated($payload);
        }
        
        try {
            // Ürün detaylarını Trendyol API'den al
            $products_api = new Trendyol_WC_Products_API();
            
            if (!empty($product_id)) {
                $product_details = $products_api->get_product($product_id);
            } else {
                // Barkoda göre ürün ara
                $products_response = $products_api->get_products(array('barcode' => $barcode));
                if (is_wp_error($products_response)) {
                    return array(
                        'success' => false,
                        'message' => 'Failed to get product details: ' . $products_response->get_error_message()
                    );
                }
                
                $products = isset($products_response['content']) ? $products_response['content'] : array();
                if (empty($products)) {
                    return array(
                        'success' => false,
                        'message' => 'Product not found in Trendyol'
                    );
                }
                
                $product_details = $products[0];
            }
            
            if (is_wp_error($product_details)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to get product details: ' . $product_details->get_error_message()
                );
            }
            
            // Ürünü WooCommerce'e ekle
            $wc_product_data = $products_api->format_product_for_woocommerce($product_details);
            $result = $this->product_sync->create_wc_product_from_trendyol($product_details);
            
            if (!$result) {
                return array(
                    'success' => false,
                    'message' => 'Failed to create product in WooCommerce'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Product created successfully',
                'product_id' => $result
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing product: ' . $e->getMessage()
            );
        }
    }

    /**
     * Ürün güncelleme olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_product_updated($payload) {
        // Barkod bilgisini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : '';
        $product_id = isset($payload['productId']) ? $payload['productId'] : '';
        
        // Ürün ID'sini bul
        $wc_product_id = $this->get_product_id_by_barcode_or_trendyol_id($barcode, $product_id);
        
        if (!$wc_product_id) {
            // Ürün mevcut değil, yeni oluştur
            return $this->handle_product_created($payload);
        }
        
        try {
            // Ürün detaylarını Trendyol API'den al
            $products_api = new Trendyol_WC_Products_API();
            
            if (!empty($product_id)) {
                $product_details = $products_api->get_product($product_id);
            } else {
                // Barkoda göre ürün ara
                $products_response = $products_api->get_products(array('barcode' => $barcode));
                if (is_wp_error($products_response)) {
                    return array(
                        'success' => false,
                        'message' => 'Failed to get product details: ' . $products_response->get_error_message()
                    );
                }
                
                $products = isset($products_response['content']) ? $products_response['content'] : array();
                if (empty($products)) {
                    return array(
                        'success' => false,
                        'message' => 'Product not found in Trendyol'
                    );
                }
                
                $product_details = $products[0];
            }
            
            if (is_wp_error($product_details)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to get product details: ' . $product_details->get_error_message()
                );
            }
            
            // Ürünü WooCommerce'de güncelle
            $product = wc_get_product($wc_product_id);
            
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => 'Product not found in WooCommerce'
                );
            }
            
            $result = $this->product_sync->update_wc_product_from_trendyol($product, $product_details);
            
            if (!$result) {
                return array(
                    'success' => false,
                    'message' => 'Failed to update product in WooCommerce'
                );
            }
            
            return array(
                'success' => true,
                'message' => 'Product updated successfully',
                'product_id' => $wc_product_id
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing product update: ' . $e->getMessage()
            );
        }
    }

    /**
     * Stok güncelleme olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_stock_updated($payload) {
        // Barkod bilgisini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : '';
        $product_id = isset($payload['productId']) ? $payload['productId'] : '';
        $stock_quantity = isset($payload['quantity']) ? intval($payload['quantity']) : null;
        
        if (is_null($stock_quantity)) {
            return array(
                'success' => false,
                'message' => 'Stock quantity is missing in payload'
            );
        }
        
        // Ürün ID'sini bul
        $wc_product_id = $this->get_product_id_by_barcode_or_trendyol_id($barcode, $product_id);
        
        if (!$wc_product_id) {
            // Ürün mevcut değil, yeni oluştur
            return $this->handle_product_created($payload);
        }
        
        try {
            $product = wc_get_product($wc_product_id);
            
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => 'Product not found in WooCommerce'
                );
            }
            
            // Stok durumunu güncelle
            $product->set_stock_quantity($stock_quantity);
            $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
            
            // Son güncelleme zamanı
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            
            // Ürünü kaydet
            $product->save();
            
            return array(
                'success' => true,
                'message' => 'Stock updated successfully',
                'product_id' => $wc_product_id,
                'new_stock' => $stock_quantity
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error updating stock: ' . $e->getMessage()
            );
        }
    }

    /**
     * Fiyat güncelleme olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_price_updated($payload) {
        // Barkod bilgisini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : '';
        $product_id = isset($payload['productId']) ? $payload['productId'] : '';
        $list_price = isset($payload['listPrice']) ? floatval($payload['listPrice']) : null;
        $sale_price = isset($payload['salePrice']) ? floatval($payload['salePrice']) : null;
        
        if (is_null($list_price) && is_null($sale_price)) {
            return array(
                'success' => false,
                'message' => 'Price information is missing in payload'
            );
        }
        
        // Ürün ID'sini bul
        $wc_product_id = $this->get_product_id_by_barcode_or_trendyol_id($barcode, $product_id);
        
        if (!$wc_product_id) {
            // Ürün mevcut değil, yeni oluştur
            return $this->handle_product_created($payload);
        }
        
        try {
            $product = wc_get_product($wc_product_id);
            
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => 'Product not found in WooCommerce'
                );
            }
            
            // Fiyatları güncelle
            if (!is_null($list_price)) {
                $product->set_regular_price($list_price);
            }
            
            if (!is_null($sale_price)) {
                $product->set_sale_price($sale_price);
            }
            
            // Son güncelleme zamanı
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            
            // Ürünü kaydet
            $product->save();
            
            return array(
                'success' => true,
                'message' => 'Prices updated successfully',
                'product_id' => $wc_product_id,
                'new_regular_price' => $list_price,
                'new_sale_price' => $sale_price
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error updating prices: ' . $e->getMessage()
            );
        }
    }

    /**
     * Durum değişikliği olayını işle
     *
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    protected function handle_status_changed($payload) {
        // Barkod bilgisini al
        $barcode = isset($payload['barcode']) ? $payload['barcode'] : '';
        $product_id = isset($payload['productId']) ? $payload['productId'] : '';
        $status = isset($payload['status']) ? $payload['status'] : '';
        
        if (empty($status)) {
            return array(
                'success' => false,
                'message' => 'Status information is missing in payload'
            );
        }
        
        // Ürün ID'sini bul
        $wc_product_id = $this->get_product_id_by_barcode_or_trendyol_id($barcode, $product_id);
        
        if (!$wc_product_id) {
            // Ürün mevcut değil, yeni oluştur
            return $this->handle_product_created($payload);
        }
        
        try {
            $product = wc_get_product($wc_product_id);
            
            if (!$product) {
                return array(
                    'success' => false,
                    'message' => 'Product not found in WooCommerce'
                );
            }
            
            // Trendyol durumunu WooCommerce durumuna çevir
            $wc_status = $this->get_wc_status_from_trendyol_status($status);
            
            // Ürün durumunu güncelle
            $product->set_status($wc_status);
            
            // Son güncelleme zamanı
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            $product->update_meta_data('_trendyol_product_status', $status);
            
            // Ürünü kaydet
            $product->save();
            
            return array(
                'success' => true,
                'message' => 'Status updated successfully',
                'product_id' => $wc_product_id,
                'new_status' => $wc_status
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            );
        }
    }

    /**
     * Trendyol barkodu veya ID'sine göre WooCommerce ürün ID'sini bul
     *
     * @param string $barcode Trendyol barkodu
     * @param int $trendyol_id Trendyol ürün ID'si
     * @return int|false WooCommerce ürün ID'si veya bulunamadıysa false
     */
    protected function get_product_id_by_barcode_or_trendyol_id($barcode, $trendyol_id) {
        global $wpdb;
        
        // Önce barkod ile ara
        if (!empty($barcode)) {
            // Doğrudan SKU ile eşleşen ürünü ara
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
            
            if ($product_id) {
                return (int) $product_id;
            }
        }
        
        // Trendyol ID ile ara
        if (!empty($trendyol_id)) {
            $meta_key = '_trendyol_product_id';
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                $meta_key,
                $trendyol_id
            ));
            
            if ($product_id) {
                return (int) $product_id;
            }
        }
        
        return false;
    }

    /**
     * Trendyol ürün durumunu WooCommerce durumuna çevir
     *
     * @param string $trendyol_status Trendyol ürün durumu
     * @return string WooCommerce ürün durumu
     */
    protected function get_wc_status_from_trendyol_status($trendyol_status) {
        $status_map = array(
            'ACTIVE' => 'publish',
            'PASSIVE' => 'draft',
            'SUSPENDED' => 'draft',
            'REJECTED' => 'draft',
            'PENDING_APPROVAL' => 'pending',
            'APPROVED' => 'publish',
            'ON_SALE' => 'publish'
        );
        
        return isset($status_map[$trendyol_status]) ? $status_map[$trendyol_status] : 'draft';
    }
}