<?php
/**
 * Trendyol WooCommerce Ürün Senkronizasyon Sınıfı
 * 
 * Ürün senkronizasyonu işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Product_Sync {

    /**
     * Ürün API'si
     *
     * @var Trendyol_WC_Products_API
     */
    protected $products_api;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->products_api = new Trendyol_WC_Products_API();
    }

    /**
     * Tüm ürünleri senkronize et
     *
     * @param array $args Senkronizasyon parametreleri
     * @return array Senkronizasyon sonuçları
     */
    public function sync_products($args = array()) {
        $default_args = array(
            'direction' => 'both', // both, to_wc, to_trendyol
            'limit' => 50,
            'product_ids' => array(), // Belirli ürünleri senkronize et
            'skip_existing' => false, // Mevcut ürünleri atla
        );
        
        $args = wp_parse_args($args, $default_args);
        
        // Yön kontrolü
        if ($args['direction'] == 'both' || $args['direction'] == 'to_wc') {
            $this->sync_products_from_trendyol($args);
        }
        
        if ($args['direction'] == 'both' || $args['direction'] == 'to_trendyol') {
            $this->sync_products_to_trendyol($args);
        }
        
        return array(
            'success' => true,
            'message' => __('Ürün senkronizasyonu tamamlandı.', 'trendyol-woocommerce')
        );
    }

    /**
     * Trendyol'dan ürünleri WooCommerce'e senkronize et
     *
     * @param array $args Senkronizasyon parametreleri
     * @return array Senkronizasyon sonuçları
     */
    public function sync_products_from_trendyol($args = array()) {
        $default_args = array(
            'page' => 0,
            'size' => 50,
            'skip_existing' => false,
        );
        
        $args = wp_parse_args($args, $default_args);
        
        // Trendyol ürünlerini getir
        $response = $this->products_api->get_products(array(
            'page' => $args['page'],
            'size' => $args['size']
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        // Sonuçlar
        $products = isset($response['content']) ? $response['content'] : array();
        $total_count = isset($response['totalElements']) ? $response['totalElements'] : 0;
        
        if (empty($products)) {
            return array(
                'success' => true,
                'message' => __('Trendyol\'dan içe aktarılacak ürün bulunamadı.', 'trendyol-woocommerce'),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0
            );
        }
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        foreach ($products as $trendyol_product) {
            // Ürün barkodu (SKU)
            $barcode = isset($trendyol_product['barcode']) ? $trendyol_product['barcode'] : '';
            
            if (empty($barcode)) {
                $skipped++;
                continue;
            }
            
            // Mevcut ürünü bul
            $product_id = wc_get_product_id_by_sku($barcode);
            
            // Trendyol ürün ID'si ile de arama yap
            if (!$product_id && isset($trendyol_product['id'])) {
                $product_id = $this->get_product_id_by_trendyol_id($trendyol_product['id']);
            }
            
            if ($product_id) {
                // Mevcut ürünü güncelle
                if ($args['skip_existing']) {
                    $skipped++;
                    continue;
                }
                
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    $skipped++;
                    continue;
                }
                
                $this->update_wc_product_from_trendyol($product, $trendyol_product);
                $updated++;
            } else {
                // Yeni ürün oluştur
                $result = $this->create_wc_product_from_trendyol($trendyol_product);
                
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
            
            $next_result = $this->sync_products_from_trendyol($next_args);
            
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
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d ürün içe aktarıldı, %d ürün güncellendi, %d ürün atlandı.', 'trendyol-woocommerce'), $imported, $updated, $skipped),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $total_count
        );
    }

    
    /**
     * Toplu ürün senkronizasyonu
     * 
     * @param array $args Senkronizasyon parametreleri
     * @return array Senkronizasyon sonuçları
     */
    public function sync_products_to_trendyol($args = array()) {
        $default_args = array(
            'limit' => 50,
            'product_ids' => array(),
            'skip_existing' => false,
        );
        
        $args = wp_parse_args($args, $default_args);
        
        // Ürün sorgusunu hazırla
        $query_args = array(
            'status' => 'publish',
            'limit' => $args['limit'],
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Belirli ürünler
        if (!empty($args['product_ids'])) {
            $query_args['include'] = $args['product_ids'];
        }
        
        // Ürünleri getir
        $products = wc_get_products($query_args);
        
        if (empty($products)) {
            return array(
                'success' => true,
                'message' => __('Trendyol\'a gönderilecek ürün bulunamadı.', 'trendyol-woocommerce'),
                'exported' => 0,
                'updated' => 0,
                'skipped' => 0
            );
        }
        
        $exported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = array();
        
        // Toplu işlem için maksimum 100 ürün gönderebiliriz
        $batch_size = 100;
        $batches = array_chunk($products, $batch_size);
        
        // Ayarları al
        $settings = get_option('trendyol_wc_settings', array());
        
        foreach ($batches as $batch) {
            $batch_items = array();
            
            foreach ($batch as $product) {
                // Ürün senkronize edilmiş mi kontrol et
                $trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
                
                if (!empty($trendyol_product_id) && $args['skip_existing']) {
                    $skipped++;
                    continue;
                }
                
                // Stok ve SKU kontrolü
                if (empty($product->get_sku())) {
                    // Hata logla
                    $product->update_meta_data('_trendyol_sync_error', __('Ürün SKU/Barkod bilgisi eksik.', 'trendyol-woocommerce'));
                    $product->save();
                    $skipped++;
                    $errors[] = sprintf(__('Ürün #%d: SKU eksik', 'trendyol-woocommerce'), $product->get_id());
                    continue;
                }
                
                // Gerekli ayarlar kontrol edilmeli
                if (empty($settings['cargo_company_id'])) {
                    $errors[] = __('Kargo firması seçilmemiş. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce');
                    continue;
                }
                
                // Ürünü Trendyol formatına dönüştür
                $trendyol_product = $this->products_api->format_product_for_trendyol($product);
                
                if (is_wp_error($trendyol_product)) {
                    // Hata logla
                    $product->update_meta_data('_trendyol_sync_error', $trendyol_product->get_error_message());
                    $product->save();
                    $skipped++;
                    $errors[] = sprintf(__('Ürün #%d: %s', 'trendyol-woocommerce'), $product->get_id(), $trendyol_product->get_error_message());
                    continue;
                }
                
                // Varyasyonlu ürün kontrolü
                if (isset($trendyol_product['items']) && !empty($trendyol_product['items'])) {
                    // Varyasyonlu ürünleri doğrudan ekle
                    foreach ($trendyol_product['items'] as $variant) {
                        $batch_items[] = $variant;
                    }
                } else {
                    // Basit ürün
                    $batch_items[] = $trendyol_product;
                }
                
                // İşlem sayacını güncelle
                if (!empty($trendyol_product_id)) {
                    $updated++;
                } else {
                    $exported++;
                }
                
                // Ürün meta verilerini güncelle
                $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
                $product->delete_meta_data('_trendyol_sync_error');
                $product->save();
            }
            
            // Batch içinde gönderilebilecek ürün varsa gönder
            if (!empty($batch_items)) {
                // Data formatını hazırla - items içinde gönderilmeli
                $data = [
                    'items' => $batch_items
                ];
                
                // Toplu ürün gönderimi için endpoint
                $supplier_id = $settings['supplier_id'];
                $endpoint = "integration/product/sellers/{$supplier_id}/products/batch";
                
                $response = $this->products_api->post($endpoint, $data);
                
                // Hata kontrolü
                if (is_wp_error($response)) {
                    $errors[] = $response->get_error_message();
                } else if (isset($response['batchRequestId'])) {
                    // Başarılı - batch ID kaydet
                    update_option('_trendyol_last_batch_id', $response['batchRequestId']);
                }
            }
        }
        
        $result = array(
            'success' => true,
            'message' => sprintf(__('%d ürün dışa aktarıldı, %d ürün güncellendi, %d ürün atlandı.', 'trendyol-woocommerce'), $exported, $updated, $skipped),
            'exported' => $exported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($products),
            'errors' => $errors
        );
        
        if (!empty($errors)) {
            $result['error_details'] = implode("\n", $errors);
        }
        
        return $result;
    }
    /**
     * Trendyol ürün verilerindeki kategori bilgilerini kontrol et ve düzelt
     *
     * @param array $product_data Trendyol ürün verileri
     * @return array Düzeltilmiş ürün verileri
     */
    private function validate_and_fix_category($product_data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/category-fix-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - Kategori doğrulama başlıyor\n", 3, $log_file);
        
        // Kategori ID kontrolü
        $category_id = isset($product_data['categoryId']) ? $product_data['categoryId'] : null;
        
        if (empty($category_id)) {
            error_log("Kategori ID bulunamadı, düzeltme denenecek\n", 3, $log_file);
            
            // pimCategoryId kontrol et
            if (isset($product_data['pimCategoryId']) && !empty($product_data['pimCategoryId'])) {
                $product_data['categoryId'] = $product_data['pimCategoryId'];
                error_log("pimCategoryId ({$product_data['pimCategoryId']}) alanı categoryId olarak ayarlandı\n", 3, $log_file);
                return $product_data;
            }
            
            // Kategori adından ID'yi bul
            if (isset($product_data['categoryName']) && !empty($product_data['categoryName'])) {
                error_log("Kategori adından ID bulma deneniyor: {$product_data['categoryName']}\n", 3, $log_file);
                
                // WooCommerce kategori eşleştirmelerini kontrol et
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                
                // Kategori adı ile eşleşen Trendyol kategorisini bul
                $categories_api = new Trendyol_WC_Categories_API();
                $categories_response = $categories_api->get_categories();
                
                if (!is_wp_error($categories_response) && isset($categories_response['categories'])) {
                    foreach ($categories_response['categories'] as $category) {
                        if (strtolower($category['name']) === strtolower($product_data['categoryName'])) {
                            $product_data['categoryId'] = $category['id'];
                            error_log("Kategori adından kategori ID bulundu: {$category['id']}\n", 3, $log_file);
                            break;
                        }
                    }
                }
            }
            
            // Hala kategori ID yoksa, varsayılan kategori ID'sini kullan
            if (empty($product_data['categoryId'])) {
                $settings = get_option('trendyol_wc_settings', array());
                if (isset($settings['default_category_id']) && !empty($settings['default_category_id'])) {
                    $product_data['categoryId'] = $settings['default_category_id'];
                    error_log("Varsayılan kategori ID kullanıldı: {$product_data['categoryId']}\n", 3, $log_file);
                } else {
                    error_log("Varsayılan kategori ID bulunamadı, kategori atanmayacak\n", 3, $log_file);
                }
            }
        } else {
            error_log("Kategori ID mevcut: $category_id\n", 3, $log_file);
            
            // Kategori ID'sinin geçerli olup olmadığını kontrol et
            $categories_api = new Trendyol_WC_Categories_API();
            $categories_response = $categories_api->get_categories();
            
            $category_valid = false;
            if (!is_wp_error($categories_response) && isset($categories_response['categories'])) {
                foreach ($categories_response['categories'] as $category) {
                    if ($category['id'] == $category_id) {
                        $category_valid = true;
                        error_log("Kategori ID doğrulandı: $category_id ({$category['name']})\n", 3, $log_file);
                        break;
                    }
                }
            }
            
            if (!$category_valid) {
                error_log("Kategori ID geçerli değil: $category_id, varsayılan kategori kullanılacak\n", 3, $log_file);
                
                // Varsayılan kategori ID'sini kullan
                $settings = get_option('trendyol_wc_settings', array());
                if (isset($settings['default_category_id']) && !empty($settings['default_category_id'])) {
                    $product_data['categoryId'] = $settings['default_category_id'];
                    error_log("Varsayılan kategori ID kullanıldı: {$product_data['categoryId']}\n", 3, $log_file);
                }
            }
        }
        
        error_log("Kategori doğrulama tamamlandı\n", 3, $log_file);
        return $product_data;
    }
    /**
     * Bir WooCommerce ürününü Trendyol'a gönder
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @return bool|WP_Error Başarılı ise true, başarısız ise hata
     */
    public function sync_product_to_trendyol($product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-sync-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - ÜRÜN SENKRONIZASYONU BAŞLIYOR\n", 3, $log_file);
        error_log("Ürün ID: " . $product->get_id() . ", Adı: " . $product->get_name() . "\n", 3, $log_file);
        
        // Ürün SKU kontrolü
        if (empty($product->get_sku())) {
            error_log("HATA: Ürün SKU/Barkod bilgisi eksik.\n", 3, $log_file);
            return new WP_Error('missing_sku', __('Ürün SKU/Barkod bilgisi eksik.', 'trendyol-woocommerce'));
        }
        
        error_log("Ürün SKU: " . $product->get_sku() . "\n", 3, $log_file);
        
        // Ürün ayarları kontrolü
        $settings = get_option('trendyol_wc_settings', array());
        error_log("Trendyol ayarları: " . print_r($settings, true) . "\n", 3, $log_file);
        
        if (empty($settings['api_username']) || empty($settings['api_password']) || empty($settings['supplier_id'])) {
            error_log("HATA: Trendyol API bilgileri eksik\n", 3, $log_file);
            return new WP_Error('api_credentials_missing', __('Trendyol API bilgileri eksik. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce'));
        }
        
        // Kargo firması kontrolü
        if (empty($settings['cargo_company_id'])) {
            error_log("HATA: Kargo firması seçilmemiş\n", 3, $log_file);
            return new WP_Error('missing_cargo_company', __('Kargo firması seçilmemiş. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce'));
        }
        
        // KDV oranı kontrolü
        if (!isset($settings['vat_rate'])) {
            error_log("UYARI: KDV oranı belirtilmemiş, varsayılan %18 kullanılacak\n", 3, $log_file);
            $settings['vat_rate'] = '20';
        }
        
        // Ürün verilerini hazırla
        error_log("Ürün verilerini hazırlama\n", 3, $log_file);
        $trendyol_product = $this->products_api->format_product_for_trendyol($product);
        
        // Hata kontrolü
        if (is_wp_error($trendyol_product)) {
            error_log("Ürün formatlanırken hata: " . $trendyol_product->get_error_message() . "\n", 3, $log_file);
            return $trendyol_product;
        }
        
        error_log("Ürün verisi hazırlandı: " . print_r($trendyol_product, true) . "\n", 3, $log_file);
        
        // Trendyol ID kontrol et
        $trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
        error_log("Mevcut Trendyol ürün ID: " . ($trendyol_product_id ? $trendyol_product_id : "yok") . "\n", 3, $log_file);
        
        // Ürünü oluştur veya güncelle
        $response = $this->products_api->create_product($trendyol_product);
        
        // Yanıt kontrolü
        if (is_wp_error($response)) {
            error_log("API işlemi hatası: " . $response->get_error_message() . "\n", 3, $log_file);
            $error_data = $response->get_error_data();
            if ($error_data) {
                error_log("Hata detayları: " . print_r($error_data, true) . "\n", 3, $log_file);
            }
            return $response;
        }
        
        error_log("API yanıtı: " . print_r($response, true) . "\n", 3, $log_file);
        
        // Başarılı yanıt işleme - Batch Request ID varsa kaydet
        if (isset($response['batchRequestId'])) {
            error_log("Batch ID: " . $response['batchRequestId'] . "\n", 3, $log_file);
            $product->update_meta_data('_trendyol_batch_id', $response['batchRequestId']);
            $product->save();
            
            // Başarı durumunu belirle
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            $product->delete_meta_data('_trendyol_sync_error');
            $product->save();
            
            return true;
        }
        // Diğer başarılı yanıt türleri
        if (isset($response['status']) && $response['status'] == 'success') {
            error_log("Başarılı işlem: " . (isset($response['message']) ? $response['message'] : "Trendyol'a başarıyla gönderildi") . "\n", 3, $log_file);
            
            // Trendyol ürün ID'si varsa güncelle
            if (isset($response['id'])) {
                $product->update_meta_data('_trendyol_product_id', $response['id']);
            }
            
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            $product->delete_meta_data('_trendyol_sync_error');
            $product->save();
            
            return true;
        }
        
        // Yanıt beklenen formatta değil
        error_log("HATA: Trendyol'dan beklenmeyen yanıt alındı\n", 3, $log_file);
        error_log("Beklenmeyen yanıt: " . print_r($response, true) . "\n", 3, $log_file);
        return new WP_Error('unexpected_response', __('Trendyol\'dan beklenmeyen yanıt alındı.', 'trendyol-woocommerce'));
    }
    
    /**
     * Trendyol ürün verilerinden WooCommerce ürünü oluştur
     *
     * @param array $trendyol_product Trendyol ürün verileri
     * @return int|false Ürün ID'si veya başarısız ise false
     */
    public function create_wc_product_from_trendyol($product_data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-create-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - WC ÜRÜN OLUŞTURMA BAŞLIYOR\n", 3, $log_file);
        error_log("Ürün verileri: " . print_r($product_data, true) . "\n", 3, $log_file);
        
        try {
            // Ürün formatını dönüştür
            $wc_product_data = $this->format_product_for_woocommerce($product_data);
            
            if (is_wp_error($wc_product_data)) {
                error_log("Format hatası: " . $wc_product_data->get_error_message() . "\n", 3, $log_file);
                return $wc_product_data;
            }
            
            error_log("WC format: " . print_r($wc_product_data, true) . "\n", 3, $log_file);
            
            // SKU'yu kontrol et - varsa çift SKU hatasını önle
            $sku = isset($wc_product_data['sku']) ? $wc_product_data['sku'] : '';
            $existing_product_id = wc_get_product_id_by_sku($sku);
            
            if ($existing_product_id) {
                error_log("Bu SKU ile ürün zaten mevcut (ID: $existing_product_id). Güncelleniyor...\n", 3, $log_file);
                $product = wc_get_product($existing_product_id);
                
                if (!$product) {
                    error_log("Ürün alınamadı ID: $existing_product_id\n", 3, $log_file);
                    return new WP_Error('invalid_product', __('Ürün alınamadı.', 'trendyol-woocommerce'));
                }
                
                // Temel verileri güncelle
                $product->set_name($wc_product_data['name']);
                $product->set_description($wc_product_data['description']);
                $product->set_short_description($wc_product_data['short_description'] ?? '');
                
                // Fiyat bilgilerini güncelle
                if (isset($wc_product_data['regular_price'])) {
                    $product->set_regular_price($wc_product_data['regular_price']);
                }
                
                if (isset($wc_product_data['sale_price'])) {
                    $product->set_sale_price($wc_product_data['sale_price']);
                }
                
                // Stok bilgilerini güncelle
                if (isset($wc_product_data['manage_stock'])) {
                    $product->set_manage_stock($wc_product_data['manage_stock']);
                }
                
                if (isset($wc_product_data['stock_quantity'])) {
                    $product->set_stock_quantity($wc_product_data['stock_quantity']);
                }
                
                if (isset($wc_product_data['stock_status'])) {
                    $product->set_stock_status($wc_product_data['stock_status']);
                }
                
                // Meta verileri güncelle
                if (isset($wc_product_data['meta_data']) && is_array($wc_product_data['meta_data'])) {
                    foreach ($wc_product_data['meta_data'] as $meta) {
                        $product->update_meta_data($meta['key'], $meta['value']);
                    }
                }
                
                // Özellikleri güncelle
                if (isset($wc_product_data['attributes']) && is_array($wc_product_data['attributes'])) {
                    $attributes = array();
                    
                    foreach ($wc_product_data['attributes'] as $attribute) {
                        $attr = new WC_Product_Attribute();
                        $attr->set_name($attribute['name']);
                        $attr->set_options($attribute['options']);
                        $attr->set_visible($attribute['visible']);
                        $attr->set_variation($attribute['variation'] ?? false);
                        
                        $attributes[] = $attr;
                    }
                    
                    $product->set_attributes($attributes);
                }
                
                // Görselleri işle
                if (isset($wc_product_data['images']) && is_array($wc_product_data['images'])) {
                    $image_ids = array();
                    
                    foreach ($wc_product_data['images'] as $image) {
                        $image_url = $image['src'];
                        $attachment_id = $this->upload_image_from_url($image_url);
                        
                        if ($attachment_id) {
                            if ($image['position'] === 0) {
                                $product->set_image_id($attachment_id);
                            } else {
                                $image_ids[] = $attachment_id;
                            }
                        }
                    }
                    
                    if (!empty($image_ids)) {
                        $product->set_gallery_image_ids($image_ids);
                    }
                }
                
                // Ürünü kaydet
                $product->save();
                error_log("Ürün güncellendi ID: $existing_product_id\n", 3, $log_file);
                return $existing_product_id;
                
            } else {
                // Yeni ürün oluştur
                error_log("Yeni ürün oluşturuluyor\n", 3, $log_file);
                
                // Ürün türüne göre işlem yap
                if (isset($wc_product_data['type']) && $wc_product_data['type'] === 'variable') {
                    error_log("Varyasyonlu ürün için create_variable_product çağrılıyor\n", 3, $log_file);
                    return $this->create_variable_product($wc_product_data);
                } else {
                    error_log("Basit ürün için create_simple_product çağrılıyor\n", 3, $log_file);
                    return $this->create_simple_product($wc_product_data);
                }
            }
            
        } catch (Exception $e) {
            error_log("HATA: " . $e->getMessage() . "\n", 3, $log_file);
            error_log("Hata Satır: " . $e->getLine() . "\n", 3, $log_file);
            error_log("Hata Dosya: " . $e->getFile() . "\n", 3, $log_file);
            error_log("Hata Stack Trace: " . $e->getTraceAsString() . "\n", 3, $log_file);
            return new WP_Error('product_creation_error', $e->getMessage());
        }
    }
    
    /**
     * Basit ürün oluştur
     * 
     * @param array $product_data Ürün verileri
     * @return int|WP_Error Ürün ID'si veya hata
     */
   private function create_simple_product($product_data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-create-simple-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - SIMPLE PRODUCT CREATION STARTING\n", 3, $log_file);
        error_log("Product data: " . print_r($product_data, true) . "\n", 3, $log_file);
        
        try {
            // Create simple product object
            $product = new WC_Product_Simple();
            
            // Basic data
            $product->set_name($product_data['name']);
            $product->set_status($product_data['status'] ?? 'publish');
            $product->set_catalog_visibility($product_data['catalog_visibility'] ?? 'visible');
            $product->set_description($product_data['description'] ?? '');
            $product->set_short_description($product_data['short_description'] ?? '');
            $product->set_sku($product_data['sku'] ?? '');
            
            // Price
            if (isset($product_data['regular_price'])) {
                $product->set_regular_price($product_data['regular_price']);
            }
            
            if (isset($product_data['sale_price'])) {
                $product->set_sale_price($product_data['sale_price']);
            }
            
            // Stock
            if (isset($product_data['manage_stock'])) {
                $product->set_manage_stock($product_data['manage_stock']);
            }
            
            if (isset($product_data['stock_quantity'])) {
                $product->set_stock_quantity($product_data['stock_quantity']);
            }
            
            if (isset($product_data['stock_status'])) {
                $product->set_stock_status($product_data['stock_status']);
            }
            
            // Weight
            if (isset($product_data['weight']) && !empty($product_data['weight'])) {
                $product->set_weight($product_data['weight']);
            }
            
            // Meta data - Do this BEFORE processing categories and attributes
            if (isset($product_data['meta_data']) && is_array($product_data['meta_data'])) {
                foreach ($product_data['meta_data'] as $meta) {
                    error_log("Adding meta: {$meta['key']} = {$meta['value']}\n", 3, $log_file);
                    $product->update_meta_data($meta['key'], $meta['value']);
                }
            }
            
            // Category assignment
            $category_ids = [];
            $trendyol_category_id = null;
            
            // First check meta data for Trendyol category ID
            if (isset($product_data['meta_data']) && is_array($product_data['meta_data'])) {
                foreach ($product_data['meta_data'] as $meta) {
                    if ($meta['key'] === '_trendyol_category_id' && !empty($meta['value'])) {
                        $trendyol_category_id = $meta['value'];
                        break;
                    }
                }
            }
            
            if ($trendyol_category_id) {
                // Get category mappings
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                error_log("Category mappings: " . print_r($category_mappings, true) . "\n", 3, $log_file);
                error_log("Looking for Trendyol category ID: " . $trendyol_category_id . "\n", 3, $log_file);
                
                // Find matching WooCommerce category IDs
                foreach ($category_mappings as $wc_cat_id => $trend_cat_id) {
                    if ($trend_cat_id == $trendyol_category_id) {
                        $category_ids[] = (int)$wc_cat_id;
                        error_log("Found matching WooCommerce category ID: " . $wc_cat_id . "\n", 3, $log_file);
                    }
                }
                
                if (!empty($category_ids)) {
                    // Set categories
                    $product->set_category_ids($category_ids);
                    error_log("Setting category IDs: " . implode(', ', $category_ids) . "\n", 3, $log_file);
                } else {
                    error_log("No WooCommerce category mappings found!\n", 3, $log_file);
                    
                    // Try to use default category
                    $settings = get_option('trendyol_wc_settings', array());
                    if (isset($settings['default_category_id']) && !empty($settings['default_category_id'])) {
                        // Look for WC category mapped to the default Trendyol category
                        foreach ($category_mappings as $wc_cat_id => $trend_cat_id) {
                            if ($trend_cat_id == $settings['default_category_id']) {
                                $product->set_category_ids([$wc_cat_id]);
                                error_log("Using default category ID: " . $wc_cat_id . "\n", 3, $log_file);
                                break;
                            }
                        }
                    }
                }
            } else {
                error_log("No Trendyol category ID found!\n", 3, $log_file);
            }
            
            // Attributes processing
            if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
                error_log("Processing " . count($product_data['attributes']) . " attributes\n", 3, $log_file);
                $attributes = array();
                
                foreach ($product_data['attributes'] as $attribute) {
                    $attr_name = isset($attribute['name']) ? $attribute['name'] : '';
                    if (empty($attr_name)) {
                        continue;
                    }
                    
                    error_log("Processing attribute: $attr_name\n", 3, $log_file);
                    
                    // Prepare attribute slug
                    $attr_slug = wc_sanitize_taxonomy_name($attr_name);
                    $taxonomy_name = 'pa_' . $attr_slug;
                    
                    // Create or get attribute taxonomy
                    $attribute_id = $this->create_or_get_attribute_taxonomy($attr_name, $attr_slug);
                    error_log("Attribute taxonomy ID: $attribute_id, taxonomy name: $taxonomy_name\n", 3, $log_file);
                    
                    if (!$attribute_id) {
                        error_log("Could not create attribute taxonomy, skipping attribute\n", 3, $log_file);
                        continue;
                    }
                    
                    // Attribute options
                    $attr_options = isset($attribute['options']) ? $attribute['options'] : array();
                    $terms_to_set = array();
                    
                    foreach ($attr_options as $option) {
                        error_log("Processing attribute value: $option\n", 3, $log_file);
                        
                        // Create or get term
                        $term = $this->create_or_get_term($option, $taxonomy_name);
                        if ($term && !is_wp_error($term)) {
                            $terms_to_set[] = $term->term_id;
                            error_log("Term created/found: " . $term->name . " (ID: " . $term->term_id . ")\n", 3, $log_file);
                        } else {
                            error_log("Could not create term: " . ($term instanceof WP_Error ? $term->get_error_message() : 'Unknown error') . "\n", 3, $log_file);
                        }
                    }
                    
                    // Create WooCommerce attribute object
                    $attr = new WC_Product_Attribute();
                    $attr->set_id($attribute_id);
                    $attr->set_name($taxonomy_name);
                    $attr->set_options($terms_to_set);
                    $attr->set_visible($attribute['visible'] ?? true);
                    $attr->set_variation($attribute['variation'] ?? false);
                    
                    $attributes[$taxonomy_name] = $attr;
                    error_log("Attribute added: $attr_name (taxonomy: $taxonomy_name)\n", 3, $log_file);
                }
                
                if (!empty($attributes)) {
                    error_log("Adding " . count($attributes) . " attributes to product\n", 3, $log_file);
                    $product->set_attributes($attributes);
                }
            }
            
            // Images
            if (isset($product_data['images']) && is_array($product_data['images'])) {
                $image_ids = array();
                $has_main_image = false;
                
                foreach ($product_data['images'] as $index => $image) {
                    if (!isset($image['src']) || empty($image['src'])) {
                        error_log("WARNING: No src value for image #$index, skipping\n", 3, $log_file);
                        continue;
                    }
                    
                    $image_url = $image['src'];
                    error_log("Uploading image #$index: $image_url\n", 3, $log_file);
                    
                    $attachment_id = $this->upload_image_from_url($image_url);
                    
                    if ($attachment_id) {
                        $position = isset($image['position']) ? $image['position'] : $index;
                        
                        if ($position === 0 || (!$has_main_image && $index === 0)) {
                            $product->set_image_id($attachment_id);
                            $has_main_image = true;
                            error_log("Set as main image: $attachment_id\n", 3, $log_file);
                        } else {
                            $image_ids[] = $attachment_id;
                            error_log("Added as gallery image: $attachment_id\n", 3, $log_file);
                        }
                    } else {
                        error_log("Failed to upload image: $image_url\n", 3, $log_file);
                    }
                }
                
                if (!empty($image_ids)) {
                    $product->set_gallery_image_ids($image_ids);
                    error_log("Gallery images set: " . implode(', ', $image_ids) . "\n", 3, $log_file);
                }
            }
            
            // Final save
            $product_id = $product->save();
            error_log("Product saved successfully, ID: $product_id\n", 3, $log_file);
            
            // After save processing - fix for categories and attributes not registering properly
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
                error_log("Categories set directly using wp_set_object_terms\n", 3, $log_file);
            }
            
            if (!empty($attributes)) {
                foreach ($attributes as $taxonomy_name => $attr) {
                    $term_ids = $attr->get_options();
                    if (!empty($term_ids)) {
                        wp_set_object_terms($product_id, $term_ids, $taxonomy_name);
                        error_log("Attribute terms set directly for $taxonomy_name\n", 3, $log_file);
                    }
                }
            }
            
            return $product_id;
            
        } catch (Exception $e) {
            error_log("ERROR: " . $e->getMessage() . "\n", 3, $log_file);
            error_log("Error Line: " . $e->getLine() . "\n", 3, $log_file);
            error_log("Error File: " . $e->getFile() . "\n", 3, $log_file);
            error_log("Error Stack Trace: " . $e->getTraceAsString() . "\n", 3, $log_file);
            return new WP_Error('simple_product_creation_error', $e->getMessage());
        }
    }
    
    /**
     * Create or get attribute taxonomy
     *
     * @param string $name Attribute name
     * @param string $slug Attribute slug
     * @return int Attribute ID
     */
    private function create_or_get_attribute_taxonomy($name, $slug) {
        global $wpdb;
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/attribute-create-' . date('Y-m-d') . '.log';
        error_log("\nCreating/getting attribute: $name ($slug)\n", 3, $log_file);
        
        // Check if attribute exists
        $attribute_id = wc_attribute_taxonomy_id_by_name($slug);
        
        if ($attribute_id) {
            error_log("Attribute already exists, ID: $attribute_id\n", 3, $log_file);
            return $attribute_id;
        }
        
        // Create new attribute
        $attribute_id = wc_create_attribute(array(
            'name'         => $name,
            'slug'         => $slug,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ));
        
        if (is_wp_error($attribute_id)) {
            error_log("Error creating attribute: " . $attribute_id->get_error_message() . "\n", 3, $log_file);
            
            // Fallback method for attribute creation
            $attribute_name = wc_clean($name);
            $attribute_label = wc_clean($name);
            $attribute_slug = wc_sanitize_taxonomy_name($slug);
            
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array(
                    'attribute_label'   => $attribute_label,
                    'attribute_name'    => $attribute_slug,
                    'attribute_type'    => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public'  => 0
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
            
            $attribute_id = $wpdb->insert_id;
            
            if (!$attribute_id) {
                error_log("Failed to create attribute even with fallback method\n", 3, $log_file);
                return 0;
            }
            
            error_log("Created attribute with fallback method, ID: $attribute_id\n", 3, $log_file);
        } else {
            error_log("Created new attribute, ID: $attribute_id\n", 3, $log_file);
        }
        
        // Register taxonomy
        $taxonomy_name = 'pa_' . $slug;
        if (!taxonomy_exists($taxonomy_name)) {
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
                    'labels'       => array(
                        'name' => $name,
                    ),
                    'hierarchical' => true,
                    'show_ui'      => false,
                    'query_var'    => true,
                    'rewrite'      => false,
                ))
            );
            
            error_log("Registered taxonomy: $taxonomy_name\n", 3, $log_file);
        }
        
        // Clear attribute cache
        delete_transient('wc_attribute_taxonomies');
        WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
        
        return $attribute_id;
    }
    
    /**
     * Create or get term
     *
     * @param string $name Term name
     * @param string $taxonomy Taxonomy name
     * @return WP_Term|false|WP_Error Term object, false, or error
     */
    private function create_or_get_term($name, $taxonomy) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/term-create-' . date('Y-m-d') . '.log';
        error_log("Creating/getting term: $name in $taxonomy\n", 3, $log_file);
        
        // Check if taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            error_log("Taxonomy does not exist: $taxonomy\n", 3, $log_file);
            
            // Trying to register the taxonomy dynamically
            // Extract the attribute name from pa_X format
            $attr_name = str_replace('pa_', '', $taxonomy);
            
            // Register the taxonomy
            $this->create_or_get_attribute_taxonomy($attr_name, $attr_name);
            
            if (!taxonomy_exists($taxonomy)) {
                error_log("Failed to create taxonomy: $taxonomy\n", 3, $log_file);
                return false;
            }
        }
        
        // Check if term exists
        $term = get_term_by('name', $name, $taxonomy);
        
        if ($term) {
            error_log("Term already exists: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
            return $term;
        }
        
        // Create new term
        $result = wp_insert_term($name, $taxonomy);
        
        if (is_wp_error($result)) {
            error_log("Error creating term: " . $result->get_error_message() . "\n", 3, $log_file);
            
            // If the term already exists but we couldn't find it
            if ($result->get_error_code() === 'term_exists') {
                $term_id = $result->get_error_data();
                $term = get_term($term_id, $taxonomy);
                if ($term) {
                    error_log("Found existing term via error data: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
                    return $term;
                }
            }
            
            return $result;
        }
        
        $term = get_term($result['term_id'], $taxonomy);
        error_log("Created new term: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
        
        return $term;
    }
    
    /**
     * Upload image from URL
     *
     * @param string $url Image URL
     * @return int|false Attachment ID or false on failure
     */
    private function upload_image_from_url($url) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/image-upload-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - IMAGE UPLOAD STARTING\n", 3, $log_file);
        error_log("Image URL: $url\n", 3, $log_file);
        
        // Check if image already exists in the media library
        $existing_image = $this->get_attachment_by_url($url);
        
        if ($existing_image) {
            error_log("Image already uploaded, ID: $existing_image\n", 3, $log_file);
            return $existing_image;
        }
        
        // Sanitize URL
        $url = str_replace(' ', '%20', $url);
        error_log("Sanitized URL: $url\n", 3, $log_file);
        
        // Check if URL is valid
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            error_log("Invalid URL: $url\n", 3, $log_file);
            return false;
        }
        
        // Download image
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            error_log("Error downloading image: " . $temp_file->get_error_message() . "\n", 3, $log_file);
            return false;
        }
        
        // Determine file name and type
        $file_name = basename(parse_url($url, PHP_URL_PATH));
        if (empty($file_name)) {
            $file_name = 'trendyol-image-' . time() . '.jpg';
        }
        
        error_log("File name: $file_name\n", 3, $log_file);
        
        $file_type = wp_check_filetype($file_name);
        $file_name = sanitize_file_name($file_name);
        
        // Upload directory info
        $upload_dir = wp_upload_dir();
        $unique_file_name = wp_unique_filename($upload_dir['path'], $file_name);
        $file_path = $upload_dir['path'] . '/' . $unique_file_name;
        
        error_log("Upload directory: " . $upload_dir['path'] . "\n", 3, $log_file);
        error_log("Unique file name: $unique_file_name\n", 3, $log_file);
        
        // Move temporary file
        $copy_result = copy($temp_file, $file_path);
        if (!$copy_result) {
            error_log("ERROR: Failed to move temporary file\n", 3, $log_file);
            @unlink($temp_file); // Clean temporary file
            return false;
        }
        
        @unlink($temp_file); // Clean temporary file
        
        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $unique_file_name,
            'post_mime_type' => $file_type['type'] ?: 'image/jpeg',
            'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Create attachment
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (!$attachment_id) {
            error_log("ERROR: Failed to create attachment\n", 3, $log_file);
            return false;
        }
        
        error_log("Attachment created, ID: $attachment_id\n", 3, $log_file);
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        error_log("Attachment metadata updated\n", 3, $log_file);
        
        return $attachment_id;
    }

    /**
     * Trendyol kategori ID'sine göre ürünün kategorilerini ayarla
     *
     * @param int $product_id WooCommerce ürün ID
     * @param int $trendyol_category_id Trendyol kategori ID
     */
    private function set_product_categories($product_id, $trendyol_category_id) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/category-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - KATEGORİ AYARLAMA: Ürün ID=$product_id, Trendyol Kategori ID=$trendyol_category_id\n", 3, $log_file);
        
        // Kategori eşleştirmelerini al
        $category_mappings = get_option('trendyol_wc_category_mappings', array());
        error_log("Kategori eşleştirmeleri: " . print_r($category_mappings, true) . "\n", 3, $log_file);
        
        $category_ids = array();
        
        // Ters arama yap: Trendyol kategori ID'sinden WooCommerce kategori ID bul
        foreach ($category_mappings as $wc_cat_id => $trend_cat_id) {
            if ($trend_cat_id == $trendyol_category_id) {
                $category_ids[] = $wc_cat_id;
                error_log("Eşleşme bulundu: WC Kategori ID=$wc_cat_id\n", 3, $log_file);
            }
        }
        
        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
            error_log("Ürün kategorileri ayarlandı: " . implode(', ', $category_ids) . "\n", 3, $log_file);
        } else {
            error_log("Eşleşen kategori bulunamadı\n", 3, $log_file);
            
            // Varsayılan kategori kullan
            $settings = get_option('trendyol_wc_settings', array());
            $default_category = isset($settings['default_category']) ? $settings['default_category'] : 0;
            
            if (!empty($default_category)) {
                wp_set_object_terms($product_id, array($default_category), 'product_cat');
                error_log("Varsayılan kategori kullanıldı: $default_category\n", 3, $log_file);
            }
        }
    }
    /**
     * Varyasyonlu ürün oluştur
     * 
     * @param array $product_data Ürün verileri
     * @return int|WP_Error Ürün ID'si veya hata
     */
    private function create_variable_product($product_data) {
        // Varyasyonlu ürün nesnesi oluştur
        $product = new WC_Product_Variable();
        
        // Temel veriler
        $product->set_name($product_data['name']);
        $product->set_status($product_data['status']);
        $product->set_catalog_visibility($product_data['catalog_visibility']);
        $product->set_description($product_data['description']);
        $product->set_short_description($product_data['short_description'] ?? '');
        $product->set_sku($product_data['sku']);
        
        // Meta veriler
        if (isset($product_data['meta_data']) && is_array($product_data['meta_data'])) {
            foreach ($product_data['meta_data'] as $meta) {
                $product->update_meta_data($meta['key'], $meta['value']);
            }
        }
        
        // Önce ürünü kaydet - varyasyon eklemeden önce ana ürünü kaydetmemiz gerekiyor
        $product->save();
        
        // Özellikler
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            $attributes = array();
            
            foreach ($product_data['attributes'] as $attribute) {
                $attr = new WC_Product_Attribute();
                $attr->set_name($attribute['name']);
                $attr->set_options($attribute['options']);
                $attr->set_visible($attribute['visible']);
                $attr->set_variation($attribute['variation'] ?? false);
                
                $attributes[] = $attr;
            }
            
            $product->set_attributes($attributes);
            $product->save();
        }
        
        // Görseller
        if (isset($product_data['images']) && is_array($product_data['images'])) {
            $image_ids = array();
            
            foreach ($product_data['images'] as $image) {
                $attachment_id = $this->upload_image_from_url($image['src']);
                
                if ($attachment_id) {
                    if ($image['position'] === 0) {
                        $product->set_image_id($attachment_id);
                    } else {
                        $image_ids[] = $attachment_id;
                    }
                }
            }
            
            if (!empty($image_ids)) {
                $product->set_gallery_image_ids($image_ids);
            }
            
            $product->save();
        }
        
        // Varyasyonlar
        if (isset($product_data['variations']) && is_array($product_data['variations'])) {
            foreach ($product_data['variations'] as $variation_data) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product->get_id());
                
                // Temel veriler
                if (isset($variation_data['sku'])) {
                    $variation->set_sku($variation_data['sku']);
                }
                
                // Fiyat
                if (isset($variation_data['regular_price'])) {
                    $variation->set_regular_price($variation_data['regular_price']);
                }
                
                if (isset($variation_data['sale_price'])) {
                    $variation->set_sale_price($variation_data['sale_price']);
                }
                
                // Stok
                if (isset($variation_data['manage_stock'])) {
                    $variation->set_manage_stock($variation_data['manage_stock']);
                }
                
                if (isset($variation_data['stock_quantity'])) {
                    $variation->set_stock_quantity($variation_data['stock_quantity']);
                }
                
                if (isset($variation_data['stock_status'])) {
                    $variation->set_stock_status($variation_data['stock_status']);
                }
                
                // Varyasyon nitelikleri
                if (isset($variation_data['attributes']) && is_array($variation_data['attributes'])) {
                    foreach ($variation_data['attributes'] as $attribute) {
                        $variation->set_attribute($attribute['name'], $attribute['option']);
                    }
                }
                
                // Meta veriler
                if (isset($variation_data['meta_data']) && is_array($variation_data['meta_data'])) {
                    foreach ($variation_data['meta_data'] as $meta) {
                        $variation->update_meta_data($meta['key'], $meta['value']);
                    }
                }
                
                // Varyasyonu kaydet
                $variation->save();
            }
        }
        
        // Ürünü tekrar kaydet
        $product->save();
        
        return $product->get_id();
    }

    
    /**
     * WooCommerce'de mevcut bir ürünü kontrol et
     * 
     * @param string $trendyol_id Trendyol ürün ID
     * @param string $product_main_id Trendyol ana ürün ID
     * @param string $barcode Trendyol ürün barkodu
     * @return int|false Mevcut ürün ID'si veya false
     */
    private function check_if_product_exists($trendyol_id, $product_main_id, $barcode) {
        global $wpdb;
        
        // Trendyol ID ile kontrol
        if (!empty($trendyol_id)) {
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta 
                 WHERE meta_key = '_trendyol_product_id' 
                 AND meta_value = %s 
                 LIMIT 1",
                $trendyol_id
            ));
            
            if ($product_id) {
                return (int) $product_id;
            }
        }
        
        // Ana ürün ID ile kontrol
        if (!empty($product_main_id)) {
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta 
                 WHERE meta_key = '_trendyol_product_main_id' 
                 AND meta_value = %s 
                 LIMIT 1",
                $product_main_id
            ));
            
            if ($product_id) {
                return (int) $product_id;
            }
        }
        
        // Barkod ile kontrol (SKU)
        if (!empty($barcode)) {
            $product_id = wc_get_product_id_by_sku($barcode);
            
            if ($product_id) {
                return (int) $product_id;
            }
            
            // Trendyol barkod meta ile de kontrol et
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta 
                 WHERE meta_key = '_trendyol_barcode' 
                 AND meta_value = %s 
                 LIMIT 1",
                $barcode
            ));
            
            if ($product_id) {
                return (int) $product_id;
            }
        }
        
        return false;
    }
    
    
    /**
     * Trendyol ürün ID'sine göre WooCommerce ürün ID'sini bul
     *
     * @param string $trendyol_id Trendyol ürün ID'si
     * @return int|false WooCommerce ürün ID'si veya bulunamadıysa false
     */
    private function get_wc_product_id_by_trendyol_id($trendyol_id) {
        if (empty($trendyol_id)) {
            return false;
        }
        
        global $wpdb;
        
        // Hem _trendyol_product_id hem de _trendyol_product_main_id meta alanlarında ara
        $meta_keys = array('_trendyol_product_id', '_trendyol_product_main_id');
        
        foreach ($meta_keys as $meta_key) {
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta 
                 WHERE meta_key = %s AND meta_value = %s 
                 LIMIT 1",
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
     * Mevcut WooCommerce ürününü Trendyol verilerine göre güncelle
     *
     * @param WC_Product $product WooCommerce ürün nesnesi
     * @param array $trendyol_product Trendyol ürün verileri
     * @return bool Güncelleme başarılı ise true
     */
    public function update_wc_product_from_trendyol($product_id, $trendyol_product) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-update-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - TRENDYOL'DAN WC ÜRÜN GÜNCELLEME BAŞLIYOR\n", 3, $log_file);
        error_log("WooCommerce Ürün ID: $product_id\n", 3, $log_file);
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            error_log("HATA: WooCommerce ürün bulunamadı\n", 3, $log_file);
            return new WP_Error('product_not_found', __('WooCommerce ürünü bulunamadı.', 'trendyol-woocommerce'));
        }
        
        // WooCommerce formatına dönüştür
        $wc_product_data = $this->products_api->format_product_for_woocommerce($trendyol_product);
        
        if (is_wp_error($wc_product_data)) {
            error_log("Format hatası: " . $wc_product_data->get_error_message() . "\n", 3, $log_file);
            return $wc_product_data;
        }
        
        try {
            // Ürün türü değişti mi kontrol et
            $old_type = $product->get_type();
            $new_type = isset($wc_product_data['type']) ? $wc_product_data['type'] : 'simple';
            
            if ($old_type !== $new_type) {
                error_log("UYARI: Ürün türü değişti: $old_type -> $new_type\n", 3, $log_file);
                
                // Ürün türü değiştiyse, eski ürünü sil ve yenisini oluştur
                $product->delete(true);
                
                if ($new_type === 'variable') {
                    return $this->create_variable_product($wc_product_data);
                } else {
                    return $this->create_simple_product($wc_product_data);
                }
            }
            
            // Ürün tipine göre güncelleme
            if ($product->is_type('variable')) {
                $this->update_variable_product($product, $wc_product_data);
            } else {
                $this->update_simple_product($product, $wc_product_data);
            }
            
            error_log("Ürün başarıyla güncellendi\n", 3, $log_file);
            return $product->get_id();
            
        } catch (Exception $e) {
            error_log("Exception: " . $e->getMessage() . "\n", 3, $log_file);
            return false;
        }
    }
    
    /**
     * Basit ürün güncelle
     * 
     * @param WC_Product $product Ürün nesnesi
     * @param array $product_data Ürün verileri
     * @return void
     */
    private function update_simple_product($product, $product_data) {
        // Temel veriler
        $product->set_name($product_data['name']);
        $product->set_description($product_data['description']);
        
        if (isset($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        // SKU güncelleme - mevcut değilse
        if (empty($product->get_sku()) && isset($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }
        
        // Fiyat
        if (isset($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }
        
        if (isset($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
        }
        
        // Stok
        if (isset($product_data['manage_stock'])) {
            $product->set_manage_stock($product_data['manage_stock']);
        }
        
        if (isset($product_data['stock_quantity'])) {
            $product->set_stock_quantity($product_data['stock_quantity']);
        }
        
        if (isset($product_data['stock_status'])) {
            $product->set_stock_status($product_data['stock_status']);
        }
        
        // Ağırlık
        if (isset($product_data['weight']) && !empty($product_data['weight'])) {
            $product->set_weight($product_data['weight']);
        }
        
        // Meta veriler
        if (isset($product_data['meta_data']) && is_array($product_data['meta_data'])) {
            foreach ($product_data['meta_data'] as $meta) {
                $product->update_meta_data($meta['key'], $meta['value']);
            }
        }
        
        // Özellikler
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            $attributes = array();
            
            foreach ($product_data['attributes'] as $attribute) {
                $attr = new WC_Product_Attribute();
                $attr->set_name($attribute['name']);
                $attr->set_options($attribute['options']);
                $attr->set_visible($attribute['visible']);
                $attr->set_variation($attribute['variation'] ?? false);
                
                $attributes[] = $attr;
            }
            
            $product->set_attributes($attributes);
        }
        
        // Görseller
        if (isset($product_data['images']) && is_array($product_data['images'])) {
            // Mevcut görselleri temizleme
            $this->maybe_clear_product_images($product, $product_data['images']);
            
            $image_ids = array();
            
            foreach ($product_data['images'] as $image) {
                $attachment_id = $this->upload_image_from_url($image['src']);
                
                if ($attachment_id) {
                    if ($image['position'] === 0) {
                        $product->set_image_id($attachment_id);
                    } else {
                        $image_ids[] = $attachment_id;
                    }
                }
            }
            
            if (!empty($image_ids)) {
                $product->set_gallery_image_ids($image_ids);
            }
        }
        
        // Ürünü kaydet
        $product->save();
    }
    
    /**
     * Varyasyonlu ürün güncelle
     * 
     * @param WC_Product_Variable $product Ürün nesnesi
     * @param array $product_data Ürün verileri
     * @return void
     */
    private function update_variable_product($product, $product_data) {
        // Temel veriler
        $product->set_name($product_data['name']);
        $product->set_description($product_data['description']);
        
        if (isset($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }
        
        // SKU güncelleme - mevcut değilse
        if (empty($product->get_sku()) && isset($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }
        
        // Meta veriler
        if (isset($product_data['meta_data']) && is_array($product_data['meta_data'])) {
            foreach ($product_data['meta_data'] as $meta) {
                $product->update_meta_data($meta['key'], $meta['value']);
            }
        }
        
        // Ürünü ara kaydet
        $product->save();
        
        // Özellikler
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            $attributes = array();
            
            foreach ($product_data['attributes'] as $attribute) {
                $attr = new WC_Product_Attribute();
                $attr->set_name($attribute['name']);
                $attr->set_options($attribute['options']);
                $attr->set_visible($attribute['visible']);
                $attr->set_variation($attribute['variation'] ?? false);
                
                $attributes[] = $attr;
            }
            
            $product->set_attributes($attributes);
            $product->save();
        }
        
        // Görseller
        if (isset($product_data['images']) && is_array($product_data['images'])) {
            // Mevcut görselleri temizleme
            $this->maybe_clear_product_images($product, $product_data['images']);
            
            $image_ids = array();
            
            foreach ($product_data['images'] as $image) {
                $attachment_id = $this->upload_image_from_url($image['src']);
                
                if ($attachment_id) {
                    if ($image['position'] === 0) {
                        $product->set_image_id($attachment_id);
                    } else {
                        $image_ids[] = $attachment_id;
                    }
                }
            }
            
            if (!empty($image_ids)) {
                $product->set_gallery_image_ids($image_ids);
            }
            
            $product->save();
        }
        
        // Varyasyonlar
        if (isset($product_data['variations']) && is_array($product_data['variations'])) {
            // Mevcut varyasyonları kontrol et ve güncelle/ekle
            $existing_variations = $product->get_children();
            $variation_skus = array_column($product_data['variations'], 'sku');
            
            // Mevcut varyasyonları dolaş ve eşleştir
            foreach ($existing_variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                
                if (!$variation) {
                    continue;
                }
                
                $sku = $variation->get_sku();
                $variation_key = array_search($sku, $variation_skus);
                
                if ($variation_key !== false) {
                    // Varyasyonu güncelle
                    $variation_data = $product_data['variations'][$variation_key];
                    
                    // Fiyat
                    if (isset($variation_data['regular_price'])) {
                        $variation->set_regular_price($variation_data['regular_price']);
                    }
                    
                    if (isset($variation_data['sale_price'])) {
                        $variation->set_sale_price($variation_data['sale_price']);
                    }
                    
                    // Stok
                    if (isset($variation_data['manage_stock'])) {
                        $variation->set_manage_stock($variation_data['manage_stock']);
                    }
                    
                    if (isset($variation_data['stock_quantity'])) {
                        $variation->set_stock_quantity($variation_data['stock_quantity']);
                    }
                    
                    if (isset($variation_data['stock_status'])) {
                        $variation->set_stock_status($variation_data['stock_status']);
                    }
                    
                    // Varyasyon nitelikleri
                    if (isset($variation_data['attributes']) && is_array($variation_data['attributes'])) {
                        foreach ($variation_data['attributes'] as $attribute) {
                            $variation->set_attribute($attribute['name'], $attribute['option']);
                        }
                    }
                    
                    // Meta veriler
                    if (isset($variation_data['meta_data']) && is_array($variation_data['meta_data'])) {
                        foreach ($variation_data['meta_data'] as $meta) {
                            $variation->update_meta_data($meta['key'], $meta['value']);
                        }
                    }
                    
                    // Varyasyonu kaydet
                    $variation->save();
                    
                    // Güncellenen varyasyonu listeden çıkar
                    unset($product_data['variations'][$variation_key]);
                } else {
                    // Bu varyasyon artık yok, silinebilir (opsiyonel)
                    // $variation->delete(true);
                }
            }
            
            // Kalan varyasyonları ekle
            foreach ($product_data['variations'] as $variation_data) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product->get_id());
                
                // Temel veriler
                if (isset($variation_data['sku'])) {
                    $variation->set_sku($variation_data['sku']);
                }
                
                // Fiyat
                if (isset($variation_data['regular_price'])) {
                    $variation->set_regular_price($variation_data['regular_price']);
                }
                
                if (isset($variation_data['sale_price'])) {
                    $variation->set_sale_price($variation_data['sale_price']);
                }
                
                // Stok
                if (isset($variation_data['manage_stock'])) {
                    $variation->set_manage_stock($variation_data['manage_stock']);
                }
                
                if (isset($variation_data['stock_quantity'])) {
                    $variation->set_stock_quantity($variation_data['stock_quantity']);
                }
                
                if (isset($variation_data['stock_status'])) {
                    $variation->set_stock_status($variation_data['stock_status']);
                }
                
                // Varyasyon nitelikleri
                if (isset($variation_data['attributes']) && is_array($variation_data['attributes'])) {
                    foreach ($variation_data['attributes'] as $attribute) {
                        $variation->set_attribute($attribute['name'], $attribute['option']);
                    }
                }
                
                // Meta veriler
                if (isset($variation_data['meta_data']) && is_array($variation_data['meta_data'])) {
                    foreach ($variation_data['meta_data'] as $meta) {
                        $variation->update_meta_data($meta['key'], $meta['value']);
                    }
                }
                
                // Varyasyonu kaydet
                $variation->save();
            }
        }
        
        // Ürünü tekrar kaydet
        $product->save();
        
    }
    
  
    
    /**
     * URL'ye göre mevcut eki bul
     *
     * @param string $url Resim URL'si
     * @return int|false Ek ID'si veya bulunamadıysa false
     */
    private function get_attachment_by_url($url) {
        global $wpdb;
        
        // URL'den dosya adını çıkar
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        if (empty($filename)) {
            return false;
        }
        
        // GUID veya meta değerinde dosya adını ara
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM $wpdb->posts p 
            LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
            WHERE (p.guid LIKE %s OR (pm.meta_key = '_wp_attached_file' AND pm.meta_value LIKE %s)) 
            AND p.post_type = 'attachment' 
            LIMIT 1",
            '%' . $wpdb->esc_like($filename),
            '%' . $wpdb->esc_like($filename)
        ));
        
        return $attachment ? (int) $attachment : false;
    }

    
    /**
     * Ürün özelliği oluştur veya mevcut olanı getir
     *
     * @param string $name Özellik adı
     * @return int Özellik ID'si
     */
    private function get_or_create_attribute($name) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/attribute-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - ÖZELLİK OLUŞTURMA/ARAMA\n", 3, $log_file);
        error_log("Özellik adı: $name\n", 3, $log_file);
        
        global $wpdb;
        
        // Özellik slugını oluştur
        $attribute_name = wc_sanitize_taxonomy_name($name);
        error_log("Özellik slug: $attribute_name\n", 3, $log_file);
        
        // Mevcut özelliği kontrol et
        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);
        
        if ($attribute_id) {
            error_log("Mevcut özellik bulundu, ID: $attribute_id\n", 3, $log_file);
            return $attribute_id;
        }
        
        // Yeni özellik oluştur
        error_log("Yeni özellik oluşturuluyor...\n", 3, $log_file);
        
        $attribute_id = wc_create_attribute(array(
            'name' => $name,
            'slug' => $attribute_name,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => 0
        ));
        
        if (is_wp_error($attribute_id)) {
            error_log("Özellik oluşturma hatası: " . $attribute_id->get_error_message() . "\n", 3, $log_file);
            // Varolan bir özelliği bulmayı dene
            $existing_attributes = wc_get_attribute_taxonomies();
            foreach ($existing_attributes as $existing_attribute) {
                if (strtolower($existing_attribute->attribute_label) === strtolower($name)) {
                    $attribute_id = $existing_attribute->attribute_id;
                    error_log("Benzer bir özellik bulundu, ID: $attribute_id\n", 3, $log_file);
                    return $attribute_id;
                }
            }
            return 0;
        }
        
        error_log("Yeni özellik oluşturuldu, ID: $attribute_id\n", 3, $log_file);
        
        // Özellik taksonomisini kaydet
        $taxonomy_name = 'pa_' . $attribute_name;
        
        register_taxonomy(
            $taxonomy_name,
            'product',
            array(
                'label' => $name,
                'rewrite' => array('slug' => $attribute_name),
                'hierarchical' => true
            )
        );
        
        error_log("Özellik taksonomisi kaydedildi: $taxonomy_name\n", 3, $log_file);
        
        return $attribute_id;
    }
    
    /**
     * Trendyol ürün ID'sine göre WooCommerce ürün ID'sini bul
     *
     * @param int $trendyol_id Trendyol ürün ID'si
     * @return int|false WooCommerce ürün ID'si veya bulunamadıysa false
     */
    private function get_product_id_by_trendyol_id($trendyol_id) {
        global $wpdb;
        
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
             WHERE meta_key = '_trendyol_product_id' 
             AND meta_value = %s 
             LIMIT 1",
            $trendyol_id
        ));
        
        return $product_id ? (int) $product_id : false;
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
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/product-sync-' . date('Y-m-d') . '.log';
        
        // Log dizini kontrolü
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Log kaydı
        $log_entry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($data) . "\n";
        error_log($log_entry, 3, $log_file);
    }
	
	/**
     *
     * @param int $product_id WooCommerce ürün ID
     * @return bool|WP_Error Başarılı ise true, başarısız ise hata
     */
    public function update_product_stock($product_id) {
		$product = wc_get_product($product_id);
		
		if (!$product) {
			return new WP_Error('invalid_product', __('Ürün bulunamadı.', 'trendyol-woocommerce'));
		}
		
		// Trendyol ID kontrolü
		$trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
		
		if (empty($trendyol_product_id)) {
			return new WP_Error('not_synced', __('Ürün henüz Trendyol ile senkronize edilmemiş.', 'trendyol-woocommerce'));
		}
		
		// Stok bilgisi kontrolü
		$stock_quantity = $product->get_stock_quantity();
		
		if (is_null($stock_quantity)) {
			return new WP_Error('no_stock_info', __('Ürün stok bilgisi bulunamadı.', 'trendyol-woocommerce'));
		}
		
		// Stok güncelleme
		$result = $this->products_api->update_product_stock($product_id, $stock_quantity);
		
		if (is_wp_error($result)) {
			$product->update_meta_data('_trendyol_sync_error', $result->get_error_message());
			$product->save();
			return $result;
		}
		
		// Başarılı güncelleme
		$product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
		$product->delete_meta_data('_trendyol_sync_error');
		$product->save();
		
		return true;
	}
}
	