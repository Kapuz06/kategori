<?php
/**
 * Trendyol Ürünler API Sınıfı
 * 
 * Trendyol ürün API işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Products_API extends Trendyol_WC_API {

    /**
     * Trendyol ürünlerini getir - API dökümantasyonuna uygun güncellendi
     *
     * @param array $params Sorgu parametreleri
     * @param int $page Sayfa numarası
     * @param int $size Sayfa başına ürün sayısı
     * @return array|WP_Error Ürün listesi veya hata
     */
    public function get_products($params = array(), $page = 1, $size = 100) {
        $default_params = array(
            'page' => $page,
            'size' => $size,
            'approved' => 'true', // sadece onaylı ürünler
        );
        
        $query_params = array_merge($default_params, $params);
        
        // Güncel API endpoint'i
        return $this->get("integration/product/sellers/{$this->supplier_id}/products", $query_params);
    }
    /**
     * Barkoda göre ürünü getir
     *
     * @param string $barcode Ürün barkodu
     * @return array|WP_Error Ürün bilgileri veya hata
     */
    public function get_product_by_barcode($barcode) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-get-barcode-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - GET_PRODUCT_BY_BARCODE BAŞLIYOR\n", 3, $log_file);
        error_log("İstek yapılan barkod: $barcode\n", 3, $log_file);
        
        // Supplier ID kontrolü
        if (empty($this->supplier_id)) {
            error_log("HATA: Supplier ID boş\n", 3, $log_file);
            return new WP_Error('missing_supplier_id', __('Trendyol Supplier ID eksik. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce'));
        }
        
        // Barkodla ürün listesini sorgula
        $params = array(
            'barcode' => $barcode,
            'size' => 1
        );
        
        // API endpoint
        $endpoint = "integration/product/sellers/{$this->supplier_id}/products";
        error_log("API Endpoint: $endpoint\n", 3, $log_file);
        error_log("Parametreler: " . print_r($params, true) . "\n", 3, $log_file);
        
        // API çağrısını yap
        $response = $this->get($endpoint, $params);
        
        // Yanıt kontrolü
        if (is_wp_error($response)) {
            error_log("API hatası: " . $response->get_error_message() . "\n", 3, $log_file);
            return $response;
        }
        
        // Ürün listesinden ilk ürünü al
        if (isset($response['content']) && !empty($response['content'])) {
            $product = $response['content'][0];
            error_log("Barkodla eşleşen ürün bulundu, ID: " . (isset($product['id']) ? $product['id'] : 'bilinmiyor') . "\n", 3, $log_file);
            return $product;
        }
        
        error_log("Barkodla eşleşen ürün bulunamadı\n", 3, $log_file);
        return new WP_Error('product_not_found', __('Bu barkod ile eşleşen ürün bulunamadı.', 'trendyol-woocommerce'));
    }
    public function get_product($product_id) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-get-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - GET_PRODUCT BAŞLIYOR\n", 3, $log_file);
        error_log("İstek yapılan Trendyol Ürün ID: $product_id\n", 3, $log_file);
        
        // Güncel API endpoint'i
        $endpoint = "integration/product/sellers/{$this->supplier_id}/products/{$product_id}";
        error_log("API Endpoint: $endpoint\n", 3, $log_file);
        
        // API çağrısını yap
        error_log("API isteği gönderiliyor...\n", 3, $log_file);
        $response = $this->get($endpoint);
        
        // Yanıt kontrolü
        if (is_wp_error($response)) {
            error_log("API hatası: " . $response->get_error_message() . "\n", 3, $log_file);
            return $response;
        }
        
        error_log("API yanıtı başarılı alındı\n", 3, $log_file);
        return $response;
    }

    
    /**
     * Ürün API'ye gönder - API dökümantasyonuna uygun olarak güncellendi
     * 
     * @param array $product_data Trendyol ürün verileri
     * @return array|WP_Error API yanıtı
     */
    public function create_product($product_data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/api-product-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - ÜRÜN OLUŞTURMA BAŞLIYOR\n", 3, $log_file);
        
        // Yeni API endpoint'i
        $endpoint = "integration/product/sellers/{$this->supplier_id}/products";
        error_log("Endpoint: $endpoint\n", 3, $log_file);
        
        // Varyasyonlu ürün kontrolü
        $is_variable = isset($product_data['items']) && !empty($product_data['items']);
        
        // API'nin beklediği format: items array içinde ürünler
        if ($is_variable) {
            // Varyasyonlu ürün zaten items içindeyse direkt gönder
            $data = [
                'items' => $product_data['items']
            ];
            error_log("Varyasyonlu ürün gönderiliyor. Varyasyon sayısı: " . count($product_data['items']) . "\n", 3, $log_file);
        } else {
            // Basit ürün için items array'inde tek eleman olarak gönder
            $data = [
                'items' => [$product_data]
            ];
            error_log("Basit ürün gönderiliyor\n", 3, $log_file);
        }
        
        error_log("Gönderilecek veri: " . json_encode($data, JSON_PRETTY_PRINT) . "\n", 3, $log_file);
        
        // API isteğini gönder
        $response = $this->post($endpoint, $data);
        error_log("API yanıtı: " . print_r($response, true) . "\n", 3, $log_file);
        
        return $response;
    }
    
    public function create_products_batch($products) {
        $data = array(
            'items' => $products
        );
        
        // Yeni endpoint'i kullan
        return $this->post("integration/product/sellers/{$this->supplier_id}/products/batch", $data);
    }
    
    public function update_product($product_data) {
        // Yeni endpoint'i kullan
        return $this->put("integration/product/sellers/{$this->supplier_id}/products", $product_data);
    }
    
    public function update_products_batch($products) {
        $data = array(
            'items' => $products
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/product/sellers/{$this->supplier_id}/products/batch", $data);
    }
    
    public function delete_product($barcode) {
        // Yeni endpoint'i kullan
        return $this->delete("integration/product/sellers/{$this->supplier_id}/products", array(
            'barcode' => $barcode
        ));
    }
    
    public function update_stock($stock_data) {
        // Yeni endpoint'i kullan
        return $this->put("integration/product/sellers/{$this->supplier_id}/products/price-and-inventory", $stock_data);
    }
    
    public function update_stocks_batch($stocks) {
        $data = array(
            'items' => $stocks
        );
        
        // Yeni endpoint'i kullan
        return $this->put("integration/product/sellers/{$this->supplier_id}/products/price-and-inventory/batch", $data);
    }
    
    public function update_price($price_data) {
        // Yeni endpoint'i kullan
        return $this->put("integration/product/sellers/{$this->supplier_id}/products/price-and-inventory", $price_data);
    }

    /**
     * Ürün bilgilerinden WooCommerce formatına dönüştür
     *
     * @param array $trendyol_product Trendyol ürün verileri
     * @return array WooCommerce ürün verileri
     */
    public function format_product_for_woocommerce($trendyol_product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-format-wc-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - ÜRÜN WC FORMATINA DÖNÜŞTÜRÜLÜYOR\n", 3, $log_file);
        
        // Trendyol ürün ID'si
        $trendyol_id = isset($trendyol_product['id']) ? $trendyol_product['id'] : 
                      (isset($trendyol_product['productId']) ? $trendyol_product['productId'] : 'bilinmiyor');
        
        error_log("Trendyol Ürün ID: $trendyol_id\n", 3, $log_file);
        
        // Temel ürün bilgilerini kontrol et
        if (!isset($trendyol_product['title']) || empty($trendyol_product['title'])) {
            error_log("HATA: Ürün başlığı eksik\n", 3, $log_file);
            return new WP_Error('missing_title', __('Trendyol ürün başlığı eksik.', 'trendyol-woocommerce'));
        }
        
        if (!isset($trendyol_product['barcode']) || empty($trendyol_product['barcode'])) {
            error_log("UYARI: Ürün barkodu eksik, otomatik oluşturulacak\n", 3, $log_file);
            $trendyol_product['barcode'] = 'TRND-' . $trendyol_id;
        }
        
        error_log("Ürün başlığı: " . $trendyol_product['title'] . "\n", 3, $log_file);
        error_log("Ürün barkodu: " . $trendyol_product['barcode'] . "\n", 3, $log_file);
        
        // Varyasyonların olup olmadığını kontrol et
        $has_variants = isset($trendyol_product['productVariants']) && !empty($trendyol_product['productVariants']);
        error_log("Ürün varyasyonlu mu: " . ($has_variants ? "Evet" : "Hayır") . "\n", 3, $log_file);
        
        // Temel ürün verileri
        $wc_product = array(
            'name'              => isset($trendyol_product['title']) ? $trendyol_product['title'] : '',
            'type'              => $has_variants ? 'variable' : 'simple',
            'status'            => 'publish',
            'catalog_visibility' => 'visible',
            'description'       => isset($trendyol_product['description']) ? $trendyol_product['description'] : '',
            'short_description' => isset($trendyol_product['shortDescription']) ? $trendyol_product['shortDescription'] : '',
            'sku'               => isset($trendyol_product['barcode']) ? $trendyol_product['barcode'] : '',
            'regular_price'     => isset($trendyol_product['listPrice']) ? $trendyol_product['listPrice'] : '',
            'sale_price'        => isset($trendyol_product['salePrice']) ? $trendyol_product['salePrice'] : '',
            'stock_quantity'    => isset($trendyol_product['quantity']) ? $trendyol_product['quantity'] : 0,
            'manage_stock'      => true,
            'stock_status'      => (isset($trendyol_product['quantity']) && $trendyol_product['quantity'] > 0) ? 'instock' : 'outofstock',
            'weight'            => isset($trendyol_product['dimensionalWeight']) ? $trendyol_product['dimensionalWeight'] : '',
            'meta_data'         => array(
                array(
                    'key'   => '_trendyol_product_id',
                    'value' => $trendyol_id
                ),
                array(
                    'key'   => '_trendyol_barcode',
                    'value' => isset($trendyol_product['barcode']) ? $trendyol_product['barcode'] : ''
                ),
                array(
                    'key'   => '_trendyol_supplier_id',
                    'value' => $this->supplier_id
                ),
                array(
                    'key'   => '_trendyol_brand',
                    'value' => isset($trendyol_product['brand']) && isset($trendyol_product['brand']['name']) ? 
                                $trendyol_product['brand']['name'] : ''
                ),
                array(
                    'key'   => '_trendyol_brand_id',
                    'value' => isset($trendyol_product['brandId']) ? $trendyol_product['brandId'] : 
                               (isset($trendyol_product['brand']) && isset($trendyol_product['brand']['id']) ? 
                               $trendyol_product['brand']['id'] : '')
                ),
                array(
                    'key'   => '_trendyol_category_id',
                    'value' => isset($trendyol_product['categoryId']) ? $trendyol_product['categoryId'] : ''
                ),
                array(
                    'key'   => '_trendyol_product_main_id',
                    'value' => isset($trendyol_product['productMainId']) ? $trendyol_product['productMainId'] : ''
                ),
                array(
                    'key'   => '_trendyol_stock_code',
                    'value' => isset($trendyol_product['stockCode']) ? $trendyol_product['stockCode'] : ''
                ),
                array(
                    'key'   => '_trendyol_last_sync',
                    'value' => current_time('mysql')
                )
            )
        );
        
        error_log("WooCommerce temel ürün bilgileri oluşturuldu\n", 3, $log_file);
        
        // Ürün görselleri
        if (!empty($trendyol_product['images'])) {
            $wc_product['images'] = array();
            
            error_log("Ürün görselleri işleniyor, resim sayısı: " . count($trendyol_product['images']) . "\n", 3, $log_file);
            
            foreach ($trendyol_product['images'] as $index => $image) {
                $image_url = isset($image['url']) ? $image['url'] : '';
                
                if (empty($image_url)) {
                    continue;
                }
                
                error_log("Resim #$index URL: $image_url\n", 3, $log_file);
                
                $image_data = array(
                    'src' => $image_url,
                    'position' => $index
                );
                
                if ($index === 0) {
                    $wc_product['images'][] = array_merge($image_data, array('position' => 0));
                } else {
                    $wc_product['images'][] = $image_data;
                }
            }
            
            error_log("Toplam " . count($wc_product['images']) . " resim işlendi\n", 3, $log_file);
        } else {
            error_log("UYARI: Ürün görseli bulunamadı\n", 3, $log_file);
        }
        
        // Ürün özellikleri/nitelikleri
        if (isset($trendyol_product['attributes']) && !empty($trendyol_product['attributes'])) {
            $wc_product['attributes'] = array();
            
            error_log("Ürün özellikleri işleniyor, özellik sayısı: " . count($trendyol_product['attributes']) . "\n", 3, $log_file);
            
            foreach ($trendyol_product['attributes'] as $attribute) {
                $attr_name = '';
                $attr_value = '';
                
                if (isset($attribute['attributeName']) && isset($attribute['attributeValue'])) {
                    $attr_name = $attribute['attributeName'];
                    $attr_value = $attribute['attributeValue'];
                } elseif (isset($attribute['attributeId']) && isset($attribute['attributeValueId'])) {
                    // API'den gelen ID'lere göre isim bulmamız gerekebilir
                    // Şimdilik ID'leri kullanacağız
                    $attr_name = 'Attribute-' . $attribute['attributeId'];
                    
                    if (isset($attribute['customAttributeValue'])) {
                        $attr_value = $attribute['customAttributeValue'];
                    } else {
                        $attr_value = 'Value-' . $attribute['attributeValueId'];
                    }
                } elseif (isset($attribute['customAttributeValue'])) {
                    $attr_name = isset($attribute['attributeName']) ? $attribute['attributeName'] : 'Attribute-' . $attribute['attributeId'];
                    $attr_value = $attribute['customAttributeValue'];
                } else {
                    continue; // Geçersiz özellik
                }
                
                error_log("Özellik: " . $attr_name . " = " . $attr_value . "\n", 3, $log_file);
                
                $wc_product['attributes'][] = array(
                    'name'      => $attr_name,
                    'position'  => 0,
                    'visible'   => true,
                    'variation' => false,
                    'options'   => array($attr_value)
                );
            }
            
            error_log("Toplam " . count($wc_product['attributes']) . " özellik işlendi\n", 3, $log_file);
        }
        
        // Varyasyonlu ürün işleme
        if ($has_variants) {
            error_log("Varyasyonlar işleniyor, varyasyon sayısı: " . count($trendyol_product['productVariants']) . "\n", 3, $log_file);
            
            // Varyasyon nitelikleri
            $variation_attributes = array();
            $variations = array();
            
            foreach ($trendyol_product['productVariants'] as $variant) {
                // Varyasyon temel bilgilerini kontrol et
                if (!isset($variant['barcode']) || empty($variant['barcode'])) {
                    error_log("UYARI: Bir varyasyon için barkod eksik, otomatik oluşturulacak\n", 3, $log_file);
                    $variant['barcode'] = 'TRND-' . $trendyol_id . '-VAR-' . uniqid();
                }
                
                error_log("Varyasyon barkod: " . $variant['barcode'] . "\n", 3, $log_file);
                
                // Varyasyon nitelikleri topla
                if (isset($variant['attributes']) && !empty($variant['attributes'])) {
                    foreach ($variant['attributes'] as $attr) {
                        $attr_name = isset($attr['attributeName']) ? $attr['attributeName'] : 'Attribute-' . $attr['attributeId'];
                        $attr_name = wc_sanitize_taxonomy_name($attr_name);
                        
                        $attr_value = isset($attr['attributeValue']) ? $attr['attributeValue'] : 
                                     (isset($attr['customAttributeValue']) ? $attr['customAttributeValue'] : 'Value-' . $attr['attributeValueId']);
                        
                        if (!isset($variation_attributes[$attr_name])) {
                            $variation_attributes[$attr_name] = array(
                                'name'      => $attr_name,
                                'position'  => 0,
                                'visible'   => true,
                                'variation' => true,
                                'options'   => array()
                            );
                        }
                        
                        if (!in_array($attr_value, $variation_attributes[$attr_name]['options'])) {
                            $variation_attributes[$attr_name]['options'][] = $attr_value;
                        }
                    }
                }
                
                // Varyasyon verileri
                $variation = array(
                    'sku'            => $variant['barcode'],
                    'regular_price'  => isset($variant['listPrice']) ? $variant['listPrice'] : '',
                    'sale_price'     => isset($variant['salePrice']) ? $variant['salePrice'] : '',
                    'stock_quantity' => isset($variant['quantity']) ? $variant['quantity'] : 0,
                    'manage_stock'   => true,
                    'stock_status'   => (isset($variant['quantity']) && $variant['quantity'] > 0) ? 'instock' : 'outofstock',
                    'attributes'     => array(),
                    'meta_data'      => array(
                        array(
                            'key'   => '_trendyol_barcode',
                            'value' => $variant['barcode']
                        ),
                        array(
                            'key'   => '_trendyol_stock_code',
                            'value' => isset($variant['stockCode']) ? $variant['stockCode'] : $variant['barcode']
                        )
                    )
                );
                
                // Varyasyon nitelikleri ekle
                if (isset($variant['attributes']) && !empty($variant['attributes'])) {
                    foreach ($variant['attributes'] as $attr) {
                        $attr_name = isset($attr['attributeName']) ? $attr['attributeName'] : 'Attribute-' . $attr['attributeId'];
                        $attr_name = wc_sanitize_taxonomy_name($attr_name);
                        
                        $attr_value = isset($attr['attributeValue']) ? $attr['attributeValue'] : 
                                     (isset($attr['customAttributeValue']) ? $attr['customAttributeValue'] : 'Value-' . $attr['attributeValueId']);
                        
                        $variation['attributes'][] = array(
                            'name'   => $attr_name,
                            'option' => $attr_value
                        );
                    }
                }
                
                $variations[] = $variation;
            }
            
            // Varyasyon nitelikleri ve varyasyonları ekle
            $wc_product['attributes'] = array_values($variation_attributes);
            $wc_product['variations'] = $variations;
            
            error_log("Toplam " . count($variation_attributes) . " varyasyon özelliği ve " . count($variations) . " varyasyon işlendi\n", 3, $log_file);
        }
        
        error_log("WooCommerce ürün formatına dönüştürme tamamlandı\n", 3, $log_file);
        
        return $wc_product;
    }
    private function get_product_brand_id($product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/brand-debug-' . date('Y-m-d') . '.log';
        
        // Önce meta verilerden kontrol et
        $brand_id = $product->get_meta('_trendyol_brand_id', true);
        error_log("Meta'dan alınan marka ID: " . ($brand_id ? $brand_id : "yok") . "\n", 3, $log_file);
        
        if (!empty($brand_id)) {
            return (int) $brand_id;
        }
        
        // Marka adını meta'dan al
        $brand_name = $product->get_meta('_trendyol_brand', true);
        error_log("Meta'dan alınan marka adı: " . ($brand_name ? $brand_name : "yok") . "\n", 3, $log_file);
        
        if (!empty($brand_name)) {
            // Marka API sınıfını başlat
            $brands_api = new Trendyol_WC_Brands_API();
            $brand_id = $brands_api->get_brand_id_by_name($brand_name);
            error_log("API'den alınan marka ID: " . ($brand_id ? $brand_id : "bulunamadı") . "\n", 3, $log_file);
            
            if ($brand_id) {
                return (int) $brand_id;
            }
        }
        
        // Eğer WooCommerce marka taksonomisi varsa
        if (taxonomy_exists('product_brand')) {
            $terms = get_the_terms($product->get_id(), 'product_brand');
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $brand_term = reset($terms);
                error_log("Taksonomi'den alınan marka: " . $brand_term->name . "\n", 3, $log_file);
                
                // Trendyol ID meta'dan kontrol et
                $trendyol_brand_id = get_term_meta($brand_term->term_id, '_trendyol_brand_id', true);
                
                if (!empty($trendyol_brand_id)) {
                    error_log("Term meta'dan alınan Trendyol marka ID: " . $trendyol_brand_id . "\n", 3, $log_file);
                    return (int) $trendyol_brand_id;
                }
                
                // Marka adını kullanarak API'den ara
                $brands_api = new Trendyol_WC_Brands_API();
                $brand_id = $brands_api->get_brand_id_by_name($brand_term->name);
                
                if ($brand_id) {
                    error_log("API'den bulunan marka ID: " . $brand_id . "\n", 3, $log_file);
                    return (int) $brand_id;
                }
            }
        }
        
        // Varsayılan marka ayarı
        $settings = get_option('trendyol_wc_settings', array());
        $default_brand_id = isset($settings['default_brand_id']) ? $settings['default_brand_id'] : 0;
        
        error_log("Varsayılan marka ID: " . ($default_brand_id ? $default_brand_id : "ayarlanmamış") . "\n", 3, $log_file);
        return (int) $default_brand_id;
    }
    
    /**
     * WooCommerce ürününü Trendyol formatına dönüştür
     * API dökümantasyonuna uygun şekilde güncellendi
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return array Trendyol ürün verileri
     */
    public function format_product_for_trendyol($product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-format-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - ÜRÜN FORMATLAMA BAŞLIYOR\n", 3, $log_file);
        error_log("Ürün ID: " . $product->get_id() . ", SKU: " . $product->get_sku() . "\n", 3, $log_file);
        
        // Ayarları al
        $settings = get_option('trendyol_wc_settings', array());
        
        // Ürün türünü kontrol et
        $is_variable = $product->is_type('variable');
        error_log("Ürün türü: " . ($is_variable ? "Variable" : "Simple") . "\n", 3, $log_file);
        
        // Barkod kontrolü - özel karakterler sadece ., -, _ olabilir
        $barcode = $product->get_sku();
        if (empty($barcode)) {
            error_log("HATA: Ürün SKU/Barkod bilgisi eksik.\n", 3, $log_file);
            return new WP_Error('missing_barcode', __('Ürün SKU/Barkod bilgisi eksik.', 'trendyol-woocommerce'));
        }
        
        // Barkodu uygun formata dönüştür (Türkçe karakterleri korur, boşlukları birleştirir)
        $barcode = str_replace(' ', '', $barcode);
        error_log("Barkod: $barcode\n", 3, $log_file);
        
        // Marka ID'sini al
        $brand_id = $this->get_product_brand_id($product);
        error_log("Marka ID: $brand_id\n", 3, $log_file);
        
        // Marka ID bulunamadıysa varsayılan markayı kullan
        if (empty($brand_id) || $brand_id == 0) {
            $default_brand_id = isset($settings['default_brand_id']) ? intval($settings['default_brand_id']) : 0;
            
            if ($default_brand_id > 0) {
                $brand_id = $default_brand_id;
                error_log("Varsayılan marka ID kullanılıyor: $brand_id\n", 3, $log_file);
            } else {
                error_log("HATA: Geçerli marka ID bulunamadı ve varsayılan marka ayarlanmamış!\n", 3, $log_file);
                return new WP_Error('missing_brand_id', __('Geçerli bir marka ID bulunamadı. Lütfen ürüne bir marka atayın veya varsayılan marka ayarlayın.', 'trendyol-woocommerce'));
            }
        }
        
        // Kategori ID'sini al
        $category_id = $this->get_product_category_id($product);
        error_log("Kategori ID: $category_id\n", 3, $log_file);
        
        // Kategori ID bulunamadıysa varsayılan kategoriyi kullan
        if (empty($category_id) || $category_id == 0) {
            $default_category_id = isset($settings['default_category_id']) ? intval($settings['default_category_id']) : 0;
            
            if ($default_category_id > 0) {
                $category_id = $default_category_id;
                error_log("Varsayılan kategori ID kullanılıyor: $category_id\n", 3, $log_file);
            } else {
                error_log("HATA: Geçerli kategori ID bulunamadı ve varsayılan kategori ayarlanmamış!\n", 3, $log_file);
                return new WP_Error('missing_category_id', __('Geçerli bir kategori ID bulunamadı. Lütfen ürüne bir kategori atayın veya varsayılan kategori ayarlayın.', 'trendyol-woocommerce'));
            }
        }
        
        // Kargo firması ID'sini al
        $cargo_company_id = isset($settings['cargo_company_id']) ? intval($settings['cargo_company_id']) : 0;
        if (empty($cargo_company_id) || $cargo_company_id == 0) {
            error_log("HATA: Geçerli kargo firması ID bulunamadı!\n", 3, $log_file);
            return new WP_Error('missing_cargo_company_id', __('Kargo firması seçilmemiş. Lütfen ayarlardan varsayılan kargo firması seçin.', 'trendyol-woocommerce'));
        }
        
        // KDV Oranı
        $vat_rate = isset($settings['vat_rate']) ? intval($settings['vat_rate']) : 20;
        error_log("KDV Oranı: $vat_rate\n", 3, $log_file);
        
        // Ürün açıklaması - eğer boşsa ürün adını kullan
        $description = $product->get_description();
        if (empty($description)) {
            $description = $product->get_name();
        }
        
        // HTML taglarını temizle
        $description = strip_tags($description);
        
        // Açıklama maksimum 30.000 karakter olabilir
        $description = substr($description, 0, 30000);
        
        // Temel ürün verileri
        $main_product_id = 'WCTYPL-' . $product->get_id(); // Benzersiz ürün ana ID'si
        
        // Ürün adını maksimum 100 karakter ile sınırla
        $title = substr($product->get_name(), 0, 100);
        
        // Stock code maksimum 100 karakter olabilir
        $stock_code = substr($barcode, 0, 100);
        
        // Ürün görsellerini al
        $images = $this->get_product_images($product);
        if (empty($images)) {
            error_log("UYARI: Ürünün resmi yok, varsayılan bir resim ekleniyor\n", 3, $log_file);
            $images = [
                ['url' => 'https://cdn.ondaon.com/wp-content/uploads/2024/01/ondaon-default-product-image.jpg']
            ];
        }
        
        // Temel ürün verileri - API dökümantasyonuna uygun olarak hazırla
        $trendyol_product = [
            'barcode' => $barcode,
            'title' => $title,
            'productMainId' => $main_product_id, 
            'brandId' => $brand_id,
            'categoryId' => $category_id,
            'quantity' => $is_variable ? 0 : intval($product->get_stock_quantity() ?: 0),
            'stockCode' => $stock_code,
            'dimensionalWeight' => floatval($product->get_weight() ?: 1), // Ağırlık yoksa 1 kullan
            'description' => $description,
            'currencyType' => 'TRY', // Para birimi TL
            'listPrice' => floatval($product->get_regular_price() ?: 0),
            'salePrice' => floatval($product->get_sale_price() ? $product->get_sale_price() : $product->get_regular_price() ?: 0),
            'vatRate' => $vat_rate, // Ayarlardan al
            'cargoCompanyId' => $cargo_company_id,
            'images' => $images,
            'attributes' => $this->get_product_attributes($product)
        ];
        
        // Sevkiyat süresi ve hızlı teslimat opsiyonu
        $delivery_duration = isset($settings['delivery_duration']) ? intval($settings['delivery_duration']) : null;
        $fast_delivery_type = isset($settings['fast_delivery_type']) ? $settings['fast_delivery_type'] : null;
        
        if ($delivery_duration !== null && $delivery_duration > 0) {
            $trendyol_product['deliveryOption'] = [
                'deliveryDuration' => $delivery_duration
            ];
            
            // Eğer hızlı teslimat tipi seçilmişse ve sevkiyat süresi 1 ise ekle
            if (!empty($fast_delivery_type) && $delivery_duration == 1) {
                $trendyol_product['deliveryOption']['fastDeliveryType'] = $fast_delivery_type;
            }
        }
        
        // Sevkiyat ve iade adresi ID'leri
        $shipment_address_id = isset($settings['shipment_address_id']) ? intval($settings['shipment_address_id']) : 0;
        if (!empty($shipment_address_id)) {
            $trendyol_product['shipmentAddressId'] = $shipment_address_id;
        }
        
        $returning_address_id = isset($settings['returning_address_id']) ? intval($settings['returning_address_id']) : 0;
        if (!empty($returning_address_id)) {
            $trendyol_product['returningAddressId'] = $returning_address_id;
        }
        
        // Varyasyonlar
        if ($is_variable) {
            $variants = $this->get_product_variants($product);
            error_log("Varyasyon sayısı: " . count($variants) . "\n", 3, $log_file);
            
            if (!empty($variants)) {
                $trendyol_product['items'] = $variants;
            } else {
                error_log("UYARI: Varyasyonlu ürün için varyasyon bulunamadı!\n", 3, $log_file);
            }
        }
        
        error_log("Oluşturulan Trendyol ürün verisi: " . print_r($trendyol_product, true) . "\n", 3, $log_file);
        return $trendyol_product;
    }

    
    /**
     * Ürünün markasını al
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return array Marka bilgileri
     */
    private function get_product_brand($product) {
        // Önce meta verilerden kontrol et
        $brand_id = $product->get_meta('_trendyol_brand_id', true);
        $brand_name = $product->get_meta('_trendyol_brand', true);
        
        if ($brand_id && $brand_name) {
            return array(
                'id' => $brand_id,
                'name' => $brand_name
            );
        }
        
        // Eğer WooCommerce marka taksonomisi varsa
        if (taxonomy_exists('product_brand')) {
            $terms = get_the_terms($product->get_id(), 'product_brand');
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $brand_term = reset($terms);
                
                return array(
                    'id' => 0, // Trendyol'da marka ID'si bulunmalı
                    'name' => $brand_term->name
                );
            }
        }
        
        // Varsayılan marka ayarı
        $settings = get_option('trendyol_wc_settings', array());
        $default_brand = isset($settings['default_brand']) ? $settings['default_brand'] : '';
        $default_brand_id = isset($settings['default_brand_id']) ? $settings['default_brand_id'] : 0;
        
        return array(
            'id' => $default_brand_id,
            'name' => $default_brand
        );
    }
    
    /**
     * Ürünün kategori ID'sini al
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return int Trendyol kategori ID'si
     */
    private function get_product_category_id($product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/category-debug-' . date('Y-m-d') . '.log';
        
        // Önce meta verilerden kontrol et
        $category_id = $product->get_meta('_trendyol_category_id', true);
        error_log("Meta'dan alınan kategori ID: " . ($category_id ? $category_id : "yok") . "\n", 3, $log_file);
        
        if (!empty($category_id)) {
            return (int) $category_id;
        }
        
        // Ürünün WooCommerce kategorilerini al
        $category_ids = $product->get_category_ids();
        error_log("WC kategori ID'leri: " . implode(', ', $category_ids) . "\n", 3, $log_file);
        
        if (!empty($category_ids)) {
            // Kategori eşleştirmelerini al
            $category_mappings = get_option('trendyol_wc_category_mappings', array());
            error_log("Kategori eşleştirmeleri: " . print_r($category_mappings, true) . "\n", 3, $log_file);
            
            foreach ($category_ids as $wc_category_id) {
                if (isset($category_mappings[$wc_category_id])) {
                    error_log("Eşleşen Trendyol kategori ID bulundu: " . $category_mappings[$wc_category_id] . "\n", 3, $log_file);
                    return (int) $category_mappings[$wc_category_id];
                }
            }
        }
        
        // Varsayılan kategori ayarı
        $settings = get_option('trendyol_wc_settings', array());
        $default_category_id = isset($settings['default_category_id']) ? $settings['default_category_id'] : 0;
        
        error_log("Varsayılan kategori ID: " . ($default_category_id ? $default_category_id : "ayarlanmamış") . "\n", 3, $log_file);
        return (int) $default_category_id;
    }
    
    /**
     * Ürün görsellerini al
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return array Görsel URLs dizisi
     */
    private function get_product_images($product) {
        $images = array();
        
        // Ana ürün görseli
        if ($product->get_image_id()) {
            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
            
            if ($image_url) {
                // HTTP URL'leri HTTPS'e dönüştür
                $image_url = str_replace('http://', 'https://', $image_url);
                
                $images[] = array(
                    'url' => $image_url
                );
            }
        }
        
        // Galeri görselleri
        $gallery_image_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_image_ids)) {
            foreach ($gallery_image_ids as $image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                
                if ($image_url) {
                    // HTTP URL'leri HTTPS'e dönüştür
                    $image_url = str_replace('http://', 'https://', $image_url);
                    
                    $images[] = array(
                        'url' => $image_url
                    );
                }
            }
        }
        
        // En az bir resim ekleyin
        if (empty($images)) {
            $images[] = array(
                'url' => 'https://cdn.dsmcdn.com/ty1625/prod/QC/20250110/16/7f8662c2-bc15-33a0-9a39-8a45818d456b/1_org_zoom.jpg'
            );
        }
        
        return $images;
    }
    
    /**
     * Ürün özelliklerini al
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return array Özellik dizisi
     */
    private function get_product_attributes($product) {
        $attributes = array();
        
        // WooCommerce özelliklerini al
        $product_attributes = $product->get_attributes();
        
        if (!empty($product_attributes)) {
            foreach ($product_attributes as $attribute_name => $attribute) {
                // Sadece varyasyon olmayan özellikleri al
                if ($attribute->get_variation()) {
                    continue;
                }
                
                $attribute_values = array();
                
                // Taksonomi tabanlı özellik
                if ($attribute->is_taxonomy()) {
                    $attribute_taxonomy = $attribute->get_taxonomy_object();
                    $attribute_terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'all'));
                    
                    if (!empty($attribute_terms)) {
                        foreach ($attribute_terms as $term) {
                            $attribute_values[] = $term->name;
                        }
                    }
                } else {
                    // Özel özellik
                    $attribute_values = $attribute->get_options();
                }
                
                if (!empty($attribute_values)) {
                    // Her değer için ayrı özellik ekle
                    foreach ($attribute_values as $value) {
                        $attributes[] = array(
                            'attributeId' => 0, // Trendyol özellik ID bulunmalı
                            'attributeName' => wc_attribute_label($attribute_name),
                            'attributeValue' => $value
                        );
                    }
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Ürün varyasyonlarını al
     *
     * @param WC_Product_Variable $product WooCommerce varyasyonlu ürün
     * @return array Varyasyon dizisi
     */
    private function get_product_variants($product) {
        $variants = array();
        $settings = get_option('trendyol_wc_settings', array());
        
        // Varyasyonları al
        $variations = $product->get_available_variations();
        
        if (!empty($variations)) {
            $main_product_id = 'WC-' . $product->get_id(); // Ana ürün ID'si
            
            foreach ($variations as $variation_data) {
                $variation = wc_get_product($variation_data['variation_id']);
                
                if (!$variation) {
                    continue;
                }
                
                // Varyasyon barkod kontrolü
                $barcode = $variation->get_sku();
                if (empty($barcode)) {
                    continue; // Barkodu olmayan varyasyonları atla
                }
                
                // Boşlukları kaldır
                $barcode = str_replace(' ', '', $barcode);
                
                // Trendyol için öznitelikler oluştur
                $attributes = [];
                $variation_attributes = $variation->get_attributes();
                
                foreach ($variation_attributes as $attr_name => $attr_value) {
                    $taxonomy = str_replace('pa_', '', $attr_name);
                    
                    if (!empty($attr_value)) {
                        $term = get_term_by('slug', $attr_value, 'pa_' . $taxonomy);
                        
                        if ($term && !is_wp_error($term)) {
                            // Trendyol attribute ID'sini al
                            $attribute_id = get_option('_trendyol_attribute_id_' . $taxonomy, 0);
                            
                            if (!$attribute_id) {
                                // ID bulunamadıysa ismiyle eşleştir
                                $attributes[] = [
                                    'attributeId' => 0, // Trendyol daha sonra eşleştirecek
                                    'customAttributeValue' => $term->name
                                ];
                            } else {
                                // Trendyol değer ID'sini al veya ismiyle gönder
                                $attribute_value_id = get_option('_trendyol_attribute_value_id_' . $term->term_id, 0);
                                
                                if ($attribute_value_id) {
                                    $attributes[] = [
                                        'attributeId' => intval($attribute_id),
                                        'attributeValueId' => intval($attribute_value_id)
                                    ];
                                } else {
                                    $attributes[] = [
                                        'attributeId' => intval($attribute_id),
                                        'customAttributeValue' => $term->name
                                    ];
                                }
                            }
                        }
                    }
                }
                
                // Ayarlardan gerekli değerleri al
                $vat_rate = isset($settings['vat_rate']) ? intval($settings['vat_rate']) : 18;
                $cargo_company_id = isset($settings['cargo_company_id']) ? intval($settings['cargo_company_id']) : 0;
                
                // Ürün adını maksimum 100 karakter ile sınırla
                $title = substr($product->get_name(), 0, 100);
                
                // Stock code maksimum 100 karakter olabilir
                $stock_code = substr($barcode, 0, 100);
                
                // Ürün açıklaması ve maksimum 30.000 karakter kontrolü
                $description = $product->get_description() ?: $product->get_name();
                $description = strip_tags($description);
                $description = substr($description, 0, 30000);
                
                // Marka ve kategori ID'leri
                $brand_id = $this->get_product_brand_id($product);
                if (empty($brand_id) || $brand_id == 0) {
                    $brand_id = isset($settings['default_brand_id']) ? intval($settings['default_brand_id']) : 0;
                }
                
                $category_id = $this->get_product_category_id($product);
                if (empty($category_id) || $category_id == 0) {
                    $category_id = isset($settings['default_category_id']) ? intval($settings['default_category_id']) : 0;
                }
                
                // Varyasyon ürün verilerini oluştur
                $variant = [
                    'barcode' => $barcode,
                    'title' => $title,
                    'productMainId' => $main_product_id,
                    'brandId' => $brand_id,
                    'categoryId' => $category_id,
                    'quantity' => intval($variation->get_stock_quantity() ?: 0),
                    'stockCode' => $stock_code,
                    'dimensionalWeight' => floatval($variation->get_weight() ?: $product->get_weight() ?: 1),
                    'description' => $description,
                    'currencyType' => 'TRY',
                    'listPrice' => floatval($variation->get_regular_price() ?: 0),
                    'salePrice' => floatval($variation->get_sale_price() ? $variation->get_sale_price() : $variation->get_regular_price() ?: 0),
                    'vatRate' => $vat_rate,
                    'cargoCompanyId' => $cargo_company_id,
                    'images' => $this->get_variation_images($variation, $product),
                    'attributes' => $attributes
                ];
                
                // Sevkiyat bilgileri
                $delivery_duration = isset($settings['delivery_duration']) ? intval($settings['delivery_duration']) : null;
                $fast_delivery_type = isset($settings['fast_delivery_type']) ? $settings['fast_delivery_type'] : null;
                
                if ($delivery_duration !== null && $delivery_duration > 0) {
                    $variant['deliveryOption'] = [
                        'deliveryDuration' => $delivery_duration
                    ];
                    
                    if (!empty($fast_delivery_type) && $delivery_duration == 1) {
                        $variant['deliveryOption']['fastDeliveryType'] = $fast_delivery_type;
                    }
                }
                
                // Sevkiyat ve iade adresi ID'leri
                $shipment_address_id = isset($settings['shipment_address_id']) ? intval($settings['shipment_address_id']) : 0;
                if (!empty($shipment_address_id)) {
                    $variant['shipmentAddressId'] = $shipment_address_id;
                }
                
                $returning_address_id = isset($settings['returning_address_id']) ? intval($settings['returning_address_id']) : 0;
                if (!empty($returning_address_id)) {
                    $variant['returningAddressId'] = $returning_address_id;
                }
                
                $variants[] = $variant;
            }
        }
        
        return $variants;
    }
    
    
    /**
     * Varyasyon ürünü için görsel al
     * 
     * @param WC_Product_Variation $variation Varyasyon ürünü
     * @param WC_Product_Variable $product Ana ürün
     * @return array Görsel URL'leri
     */
    private function get_variation_images($variation, $product) {
        $images = [];
        
        // Varyasyon görseli varsa kullan
        if ($variation->get_image_id()) {
            $image_url = wp_get_attachment_image_url($variation->get_image_id(), 'full');
            
            if ($image_url) {
                // HTTP URL'leri HTTPS'e dönüştür
                $image_url = str_replace('http://', 'https://', $image_url);
                
                $images[] = [
                    'url' => $image_url
                ];
                
                return $images;
            }
        }
        
        // Varyasyon görseli yoksa ana ürün görsellerini kullan
        return $this->get_product_images($product);
    }

    
    /**
     * Kargo şirketi ID'sini al
     *
     * @return int Kargo şirketi ID'si
     */
    private function get_cargo_company_id() {
        $settings = get_option('trendyol_wc_settings', array());
        $cargo_company_id = isset($settings['cargo_company_id']) ? $settings['cargo_company_id'] : 0;
        
        return (int) $cargo_company_id;
    }
    
    /**
     * Ürün stok durumunu güncelle
     *
     * @param int $product_id WooCommerce ürün ID'si
     * @param int $stock_quantity Stok miktarı
     * @return array|WP_Error API yanıtı veya hata
     */
    public function update_product_stock($product_id, $stock_quantity) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('invalid_product', __('Ürün bulunamadı.', 'trendyol-woocommerce'));
        }
        
        $barcode = $product->get_sku();
        
        if (empty($barcode)) {
            return new WP_Error('invalid_barcode', __('Ürün SKU/Barkod bilgisi eksik.', 'trendyol-woocommerce'));
        }
        
        $stock_data = array(
            'barcode' => $barcode,
            'quantity' => (int) $stock_quantity
        );
        
        if ($product->is_type('variable')) {
            // Varyasyonlu ürün
            $variations = $product->get_children();
            
            if (!empty($variations)) {
                $stock_items = array();
                
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    
                    if (!$variation) {
                        continue;
                    }
                    
                    $variation_barcode = $variation->get_sku();
                    
                    if (empty($variation_barcode)) {
                        continue;
                    }
                    
                    $stock_items[] = array(
                        'barcode' => $variation_barcode,
                        'quantity' => (int) $variation->get_stock_quantity()
                    );
                }
                
                if (!empty($stock_items)) {
                    return $this->update_stocks_batch($stock_items);
                }
            }
        }
        
        return $this->update_stock($stock_data);
    }
    
    /**
     * Ürün durumunu getir
     *
     * @param string $barcode Ürün barkodu
     * @return array|WP_Error Ürün durumu veya hata
     */
    public function get_product_status($barcode) {
        $query_params = array(
            'barcode' => $barcode
        );
        
        return $this->get("suppliers/{$this->supplier_id}/products/status", $query_params);
    }
    
    /**
     * Ürün fiyat geçmişini getir
     *
     * @param string $barcode Ürün barkodu
     * @param array $params Sorgu parametreleri
     * @return array|WP_Error Fiyat geçmişi veya hata
     */
    public function get_product_price_history($barcode, $params = array()) {
        $default_params = array(
            'barcode' => $barcode,
            'size' => 10,
            'page' => 0
        );
        
        $query_params = array_merge($default_params, $params);
        
        return $this->get("suppliers/{$this->supplier_id}/products/price-history", $query_params);
    }
    
    /**
     * Ürün satış geçmişini getir
     *
     * @param string $barcode Ürün barkodu
     * @param array $params Sorgu parametreleri
     * @return array|WP_Error Satış geçmişi veya hata
     */
    public function get_product_sales_history($barcode, $params = array()) {
        $default_params = array(
            'barcode' => $barcode,
            'size' => 10,
            'page' => 0
        );
        
        $query_params = array_merge($default_params, $params);
        
        return $this->get("suppliers/{$this->supplier_id}/products/sales-history", $query_params);
    }
}