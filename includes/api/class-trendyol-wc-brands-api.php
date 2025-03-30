<?php
/**
 * Trendyol Markalar API Sınıfı
 * 
 * Trendyol marka API işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Brands_API extends Trendyol_WC_API {
    /**
     * Tüm markaları getir
     * 
     * @param array $params Sorgu parametreleri
     * @return array|WP_Error Marka listesi veya hata
     */
    public function get_brands($params = array()) {
        $default_params = array(
            'size' => 1000, // Minimum 1000 marka alabildiğimizi belirtmişsiniz
            'page' => isset($_GET['brand_page']) ? (int)$_GET['brand_page'] : 0
        );
    
        $params = array_merge($default_params, $params);
        
        // Yeni API endpoint'ini kullan
        $response = $this->get("integration/product/brands", $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
    
        if (!isset($response['brands'])) {
            return new WP_Error('invalid_response', __('Geçersiz API yanıtı', 'trendyol-woocommerce'));
        }
    
        // Toplam sayfa hesaplaması
        $total_brands = isset($response['totalElements']) ? $response['totalElements'] : 
                       (isset($response['totalBrands']) ? $response['totalBrands'] : count($response['brands']) * $params['size']);
    
        return array(
            'brands' => $response['brands'],
            'total' => $total_brands,
            'current_page' => $params['page'],
            'per_page' => $params['size'],
            'total_pages' => ceil($total_brands / $params['size'])
        );
    }
    /**
     * Marka senkronizasyonunu başlatan AJAX işleyicisi
     */
    public function ajax_sync_brands_to_database() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'trendyol_brands_sync')) {
            wp_send_json_error(['message' => __('Güvenlik doğrulaması başarısız.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Yetki kontrolü
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        $result = $this->sync_all_brands_to_database();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'is_complete' => $result['is_complete'],
                'current_page' => $result['current_page'],
                'total_pages' => $result['total_pages']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * Veritabanından marka arayan AJAX işleyicisi
     */
    public function ajax_search_brands_from_database() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'trendyol-brands-search')) {
            wp_send_json_error(['message' => __('Güvenlik doğrulaması başarısız.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Arama terimi
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(['message' => __('Arama için en az 2 karakter gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $results = $this->search_brands_from_database($search_term, $limit);
        
        wp_send_json_success($results);
    }
    /**
     * Belirli bir sayfadaki markaları getir
     * 
     * @param int $page Sayfa numarası
     * @return array|WP_Error Marka listesi veya hata
     */
    public function get_brands_by_page($page = 0) {
        return $this->get_brands(array('page' => $page));
    }
    /**
     * Tüm markaları API'den çekip veritabanına kaydet
     * 
     * @return array İşlem sonucu
     */
    public function sync_all_brands_to_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trendyol_brands';
        
        // Log oluştur
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/brand-sync-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - BAŞLANGIS: TÜM MARKALAR SENKRONIZASYONU\n", 3, $log_file);
        
        $page = 0;
        $has_more = true;
        $size = 1000; // Her sayfada 1000 marka
        $total_brands = 0;
        $success_count = 0;
        $error_count = 0;
        $last_page_processed = get_option('trendyol_brands_last_page', 0);
        
        // İşlem başlangıç zamanı
        $start_time = microtime(true);
        
        try {
            // İlk olarak tüm sayfa sayısını öğrenelim
            $first_page = $this->get_brands(array('page' => 0, 'size' => $size));
            if (is_wp_error($first_page)) {
                error_log("API Hatası: " . $first_page->get_error_message() . "\n", 3, $log_file);
                return array(
                    'success' => false,
                    'message' => 'API Hatası: ' . $first_page->get_error_message(),
                );
            }
            
            $total_pages = ceil($first_page['total'] / $size);
            error_log("Toplam Marka Sayısı: " . $first_page['total'] . ", Toplam Sayfa: " . $total_pages . "\n", 3, $log_file);
            
            // Son kaldığımız sayfadan devam edelim
            $page = $last_page_processed;
            
            // En fazla 10 sayfa çekelim (her çalıştırmada en fazla 10000 marka)
            $max_pages_per_run = 2000;
            $pages_processed = 0;
            
            while ($has_more && $page < $total_pages && $pages_processed < $max_pages_per_run) {
                error_log("Sayfa işleniyor: " . ($page + 1) . "/" . $total_pages . "\n", 3, $log_file);
                
                $response = $this->get_brands(array('page' => $page, 'size' => $size));
                
                if (is_wp_error($response)) {
                    error_log("Sayfa " . $page . " için API Hatası: " . $response->get_error_message() . "\n", 3, $log_file);
                    $error_count++;
                    $page++;
                    $pages_processed++;
                    continue;
                }
                
                if (empty($response['brands'])) {
                    $has_more = false;
                    continue;
                }
                
                // Veritabanı işlemleri
                $wpdb->query('START TRANSACTION');
                
                $current_time = current_time('mysql');
                $values = array();
                $placeholders = array();
                
                foreach ($response['brands'] as $brand) {
                    $brand_id = absint($brand['id']);
                    $brand_name = sanitize_text_field($brand['name']);
                    
                    $values[] = $brand_id;
                    $values[] = $brand_name;
                    $values[] = $current_time;
                    
                    $placeholders[] = "(%d, %s, %s)";
                    $total_brands++;
                }
                
                // Toplu ekleme/güncelleme
                if (!empty($values)) {
                    $query = "INSERT INTO $table_name (id, name, last_updated) VALUES ";
                    $query .= implode(', ', $placeholders);
                    $query .= " ON DUPLICATE KEY UPDATE name = VALUES(name), last_updated = VALUES(last_updated)";
                    
                    $prepared = $wpdb->prepare($query, $values);
                    $result = $wpdb->query($prepared);
                    
                    if ($result === false) {
                        error_log("Veritabanı hatası: " . $wpdb->last_error . "\n", 3, $log_file);
                        $wpdb->query('ROLLBACK');
                        $error_count++;
                    } else {
                        $wpdb->query('COMMIT');
                        $success_count += count($response['brands']);
                    }
                }
                
                // Sayfa işleme durumunu kaydet
                update_option('trendyol_brands_last_page', $page);
                
                $page++;
                $pages_processed++;
                
                // API limitlemelerini aşmamak için kısa bir bekleme ekleyelim
                usleep(250000); // 250ms
            }
            
            // İşlem tamamlandı mı kontrol et
            $is_complete = ($page >= $total_pages);
            
            // İşlem tamamlandıysa, son sayfa işleme durumunu sıfırla
            if ($is_complete) {
                update_option('trendyol_brands_last_page', 0);
                update_option('trendyol_brands_last_sync', current_time('mysql'));
            }
            
            // İşlem süresini hesapla
            $execution_time = microtime(true) - $start_time;
            
            error_log("İŞLEM TAMAMLANDI - Toplam: $total_brands, Başarılı: $success_count, Hata: $error_count, Süre: " . round($execution_time, 2) . " sn\n", 3, $log_file);
            
            return array(
                'success' => true,
                'message' => sprintf('İşlem tamamlandı. Toplam: %d, Başarılı: %d, Hata: %d, Sayfa: %d/%d', 
                    $total_brands, $success_count, $error_count, $page, $total_pages),
                'is_complete' => $is_complete,
                'current_page' => $page,
                'total_pages' => $total_pages
            );
            
        } catch (Exception $e) {
            error_log("Beklenmeyen hata: " . $e->getMessage() . "\n", 3, $log_file);
            return array(
                'success' => false,
                'message' => 'Beklenmeyen hata: ' . $e->getMessage()
            );
        }
    }

    /**
     * Veritabanındaki markaları isme göre ara
     * 
     * @param string $search_term Arama terimi
     * @param int $limit Maksimum sonuç sayısı
     * @return array Bulunan markalar
     */
    public function search_brands_from_database($search_term, $limit = 20) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trendyol_brands';
        
        // Arama terimi en az 2 karakter olmalı
        if (strlen($search_term) < 2) {
            return array();
        }
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        $limit = absint($limit);
        
        $query = $wpdb->prepare(
            "SELECT id, name FROM $table_name 
             WHERE name LIKE %s 
             ORDER BY name ASC 
             LIMIT %d",
            $search_term,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array('brands' => $results ?: array());
    }
    
    /**
     * Veritabanından belirli bir marka ID'sini getir
     * 
     * @param int $brand_id Marka ID
     * @return array|bool Marka bilgisi veya bulunamadıysa false
     */
    public function get_brand_from_database($brand_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trendyol_brands';
        
        $query = $wpdb->prepare(
            "SELECT id, name FROM $table_name WHERE id = %d",
            $brand_id
        );
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return $result ?: false;
    }
    
    /**
     * Belirli ID'lere göre markaları veritabanından getir
     * 
     * @param array $brand_ids Marka ID'leri
     * @return array Marka bilgileri
     */
    public function get_brands_by_ids_from_database($brand_ids) {
        if (empty($brand_ids)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'trendyol_brands';
        
        // ID'leri temizle ve sayısal hale getir
        $brand_ids = array_map('absint', $brand_ids);
        
        // IN için güvenli dizge oluştur
        $placeholders = implode(',', array_fill(0, count($brand_ids), '%d'));
        
        $query = $wpdb->prepare(
            "SELECT id, name FROM $table_name 
             WHERE id IN ($placeholders)",
            $brand_ids
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // ID => Name şeklinde harita oluştur
        $brands_map = array();
        foreach ($results as $brand) {
            $brands_map[$brand['id']] = $brand['name'];
        }
        
        return $brands_map;
    }
    
    /**
     * Veritabanından marka arayan AJAX işleyicisi
     */
    public function ajax_search_trendyol_brands() {
        // Nonce kontrolü
        check_ajax_referer('trendyol_search_brands', 'nonce');
        
        global $wpdb;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search) || strlen($search) < 2) {
            wp_send_json_success(array());
            exit;
        }
        
        $brands_table = $wpdb->prefix . 'trendyol_brands';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$brands_table} 
                 WHERE name LIKE %s 
                 ORDER BY name ASC 
                 LIMIT 20",
                '%' . $wpdb->esc_like($search) . '%'
            )
        );
        
        wp_send_json_success($results);
        exit;
    }
    
    /**
     * Tüm markaları sayfalayarak getir
     * 
     * @return array Tüm markalar
     */
    public function get_all_brands() {
        $all_brands = array();
        $page = 0;
        $has_more = true;
        $size = 1000; // Bir seferde alınacak maksimum marka sayısı
    
        while ($has_more) {
            $response = $this->get_brands(array('page' => $page, 'size' => $size));
    
            if (is_wp_error($response)) {
                return $response;
            }
    
            if (empty($response['brands'])) {
                $has_more = false;
                continue;
            }
    
            $all_brands = array_merge($all_brands, $response['brands']);
            
            // Eğer toplam sayfa bilgisi varsa, ona göre döngüye devam et
            if (isset($response['total_pages']) && $page >= $response['total_pages'] - 1) {
                $has_more = false;
            } else {
                $page++;
            }
            
            // API limitlemelerini aşmamak için kısa bir bekleme ekleyelim
            usleep(250000); // 250ms
        }
    
        return array('brands' => $all_brands);
    }

    
    /**
     * Marka adına göre ara
     * 
     * @param string $name Marka adı
     * @return array|WP_Error Bulunan markalar
     */
    public function search_brands_by_name($name) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/brand-search-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - MARKA ARAMA: $name\n", 3, $log_file);
        
        // API çağrısı için parametreler
        $params = array(
            'name' => $name
        );
        
        // Yeni API endpoint'ini kullan
        $response = $this->get("integration/product/brands/by-name", $params);
        
        if (is_wp_error($response)) {
            error_log("API hatası: " . $response->get_error_message() . "\n", 3, $log_file);
            return $response;
        }
        
        // API yanıtını logla
        error_log("API yanıtı: " . print_r($response, true) . "\n", 3, $log_file);
        
        // API yanıtı doğrudan dizi mi, yoksa brands anahtarı altında mı kontrol et
        if (isset($response['brands'])) {
            return $response;
        } elseif (is_array($response)) {
            // API direkt olarak markalar dizisi dönüyorsa
            return array('brands' => $response);
        }
        
        error_log("Geçersiz API yanıtı formatı\n", 3, $log_file);
        return new WP_Error('invalid_response', __('Geçersiz API yanıtı', 'trendyol-woocommerce'));
    }

    /**
     * Marka ID'sine göre getir
     * 
     * @param int $brand_id Marka ID
     * @return array|bool|WP_Error Marka bilgisi veya hata
     */
    public function get_brand_by_id($brand_id) {
        // Tüm markaları döngü halinde getir
        $page = 0;
        $size = 1000;
        $has_more = true;
        
        while ($has_more) {
            $response = $this->get_brands(array('page' => $page, 'size' => $size));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (empty($response['brands'])) {
                $has_more = false;
                continue;
            }
            
            // Marka ID'yi ara
            foreach ($response['brands'] as $brand) {
                if ($brand['id'] == $brand_id) {
                    return $brand;
                }
            }
            
            // Sonraki sayfaya geç
            if (isset($response['total_pages']) && $page >= $response['total_pages'] - 1) {
                $has_more = false;
            } else {
                $page++;
            }
        }
        
        return new WP_Error('brand_not_found', __('Marka bulunamadı', 'trendyol-woocommerce'));
    }
    
    
    
    /**
     * Marka eşleştirme AJAX işleyicisi
     */
    public function ajax_map_brand() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'trendyol-brand-mapping')) {
            wp_send_json_error(['message' => __('Güvenlik doğrulaması başarısız.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Yetki kontrolü
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Parametreleri kontrol et
        $trendyol_id = isset($_POST['trendyol_id']) ? intval($_POST['trendyol_id']) : 0;
        $wc_brand_id = isset($_POST['wc_brand_id']) && !empty($_POST['wc_brand_id']) ? intval($_POST['wc_brand_id']) : 0;
        $new_brand_name = isset($_POST['new_brand_name']) ? sanitize_text_field($_POST['new_brand_name']) : '';
        
        if (empty($trendyol_id)) {
            wp_send_json_error(['message' => __('Geçersiz Trendyol marka ID\'si.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Eğer WooCommerce marka ID'si varsa, o markayı kullan
        // Yoksa ve yeni marka adı varsa, yeni marka oluştur
        if (empty($wc_brand_id) && !empty($new_brand_name)) {
            // Taxonomy'nin varlığını kontrol et
            if (!taxonomy_exists('product_brand')) {
                // Taxonomy yoksa oluştur
                $this->create_brand_taxonomy();
                
                if (!taxonomy_exists('product_brand')) {
                    wp_send_json_error(['message' => __('WooCommerce marka taksonomisi oluşturulamadı.', 'trendyol-woocommerce')]);
                    return;
                }
            }
            
            // Yeni marka oluştur
            $term_result = wp_insert_term($new_brand_name, 'product_brand');
            
            if (is_wp_error($term_result)) {
                // Eğer marka zaten varsa, onun ID'sini al
                if ($term_result->get_error_code() === 'term_exists') {
                    $wc_brand_id = $term_result->get_error_data();
                } else {
                    wp_send_json_error(['message' => $term_result->get_error_message()]);
                    return;
                }
            } elseif (isset($term_result['term_id'])) {
                $wc_brand_id = $term_result['term_id'];
            }
        }
        
        if (empty($wc_brand_id)) {
            wp_send_json_error(['message' => __('Geçerli bir WooCommerce markası seçilmedi veya oluşturulmadı.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Marka eşleştirme
        $brand_mappings = get_option('trendyol_wc_brand_mappings', array());
        
        // Eğer bu Trendyol markası başka bir WooCommerce markasıyla eşleştirilmişse, o eşleşmeyi kaldır
        foreach ($brand_mappings as $existing_wc_id => $existing_trendyol_id) {
            if ($existing_trendyol_id == $trendyol_id) {
                unset($brand_mappings[$existing_wc_id]);
            }
        }
        
        // Yeni eşleştirmeyi ekle
        $brand_mappings[$wc_brand_id] = $trendyol_id;
        
        // Meta veriyi güncelle
        update_term_meta($wc_brand_id, '_trendyol_brand_id', $trendyol_id);
        
        // Eşleştirmeleri kaydet
        update_option('trendyol_wc_brand_mappings', $brand_mappings);
        
        wp_send_json_success(['message' => __('Marka eşleştirmesi başarıyla tamamlandı.', 'trendyol-woocommerce')]);
    }
    
    /**
     * Marka taksonomisi oluştur (gerektiğinde)
     */
    private function create_brand_taxonomy() {
        register_taxonomy(
            'product_brand',
            'product',
            array(
                'label' => __('Markalar', 'trendyol-woocommerce'),
                'rewrite' => array('slug' => 'marka'),
                'hierarchical' => true,
                'show_admin_column' => true,
                'show_in_nav_menus' => true,
                'query_var' => true,
                'labels' => array(
                    'name' => __('Markalar', 'trendyol-woocommerce'),
                    'singular_name' => __('Marka', 'trendyol-woocommerce'),
                    'menu_name' => __('Markalar', 'trendyol-woocommerce'),
                    'all_items' => __('Tüm Markalar', 'trendyol-woocommerce'),
                    'edit_item' => __('Markayı Düzenle', 'trendyol-woocommerce'),
                    'view_item' => __('Markayı Göster', 'trendyol-woocommerce'),
                    'update_item' => __('Markayı Güncelle', 'trendyol-woocommerce'),
                    'add_new_item' => __('Yeni Marka Ekle', 'trendyol-woocommerce'),
                    'new_item_name' => __('Yeni Marka Adı', 'trendyol-woocommerce'),
                    'search_items' => __('Markaları Ara', 'trendyol-woocommerce'),
                    'popular_items' => __('Popüler Markalar', 'trendyol-woocommerce')
                )
            )
        );
    }
    
    /**
     * Marka eşleştirme kaldırma AJAX işleyicisi
     */
    public function ajax_unmap_brand() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'trendyol-brand-mapping')) {
            wp_send_json_error(['message' => __('Güvenlik doğrulaması başarısız.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Yetki kontrolü
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Parametreleri kontrol et
        $trendyol_id = isset($_POST['trendyol_id']) ? intval($_POST['trendyol_id']) : 0;
        $wc_brand_id = isset($_POST['wc_brand_id']) ? intval($_POST['wc_brand_id']) : 0;
        
        if (empty($trendyol_id) || empty($wc_brand_id)) {
            wp_send_json_error(['message' => __('Geçersiz marka ID\'leri.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Marka eşleştirmelerini al
        $brand_mappings = get_option('trendyol_wc_brand_mappings', array());
        
        // Eşleştirmeyi kaldır
        if (isset($brand_mappings[$wc_brand_id])) {
            unset($brand_mappings[$wc_brand_id]);
            
            // Meta veriyi temizle
            delete_term_meta($wc_brand_id, '_trendyol_brand_id');
            
            // Eşleştirmeleri güncelle
            update_option('trendyol_wc_brand_mappings', $brand_mappings);
            
            wp_send_json_success(['message' => __('Marka eşleştirmesi başarıyla kaldırıldı.', 'trendyol-woocommerce')]);
        } else {
            wp_send_json_error(['message' => __('Silinecek marka eşleştirmesi bulunamadı.', 'trendyol-woocommerce')]);
        }
    }
    
    /**
     * Trendyol markalarını WooCommerce'e içe aktar
     * 
     * @param bool $create_if_not_exists Yoksa marka oluştur
     * @return array İçe aktarılan markalar
     */
    public function import_brands_to_woocommerce($create_if_not_exists = false) {
        $all_brands = $this->get_all_brands();

        if (is_wp_error($all_brands)) {
            return $all_brands;
        }

        $brands = isset($all_brands['brands']) ? $all_brands['brands'] : array();

        if (empty($brands)) {
            return new WP_Error('no_brands', __('Trendyol\'dan marka alınamadı.', 'trendyol-woocommerce'));
        }

        $brand_mappings = $this->get_brand_mappings();
        $imported_brands = array();

        foreach ($brands as $brand) {
            $trendyol_brand_id = $brand['id'];
            $brand_name = $brand['name'];

            // WooCommerce'de markayı ara
            $existing_term = get_term_by('name', $brand_name, 'product_brand');

            if ($existing_term) {
                $brand_mappings[$existing_term->term_id] = $trendyol_brand_id;
                $imported_brands[$trendyol_brand_id] = $existing_term->term_id;

                update_term_meta($existing_term->term_id, '_trendyol_brand_id', $trendyol_brand_id);
            } elseif ($create_if_not_exists) {
                $term_result = wp_insert_term($brand_name, 'product_brand', array(
                    'slug' => sanitize_title($brand_name)
                ));

                if (!is_wp_error($term_result)) {
                    $wc_brand_id = $term_result['term_id'];
                    update_term_meta($wc_brand_id, '_trendyol_brand_id', $trendyol_brand_id);
                    $brand_mappings[$wc_brand_id] = $trendyol_brand_id;
                    $imported_brands[$trendyol_brand_id] = $wc_brand_id;
                }
            }
        }

        update_option('trendyol_wc_brand_mappings', $brand_mappings);
        return $imported_brands;
    }
    
    
    /**
     * Marka eşleşmelerini getir (WooCommerce -> Trendyol)
     *
     * @return array Marka eşleşmeleri
     */
    public function get_brand_mappings() {
        return get_option('trendyol_wc_brand_mappings', array());
    }
    
    /**
     * Marka adı ile Trendyol marka ID'sini bul
     *
     * @param string $brand_name Marka adı
     * @return int|bool Marka ID veya bulunamadıysa false
     */
    public function get_brand_id_by_name($brand_name) {
        // Trendyol markalarını getir
        $brands_response = $this->search_brands_by_name($brand_name);
        
        if (is_wp_error($brands_response)) {
            return false;
        }
        
        $brands = isset($brands_response['brands']) ? $brands_response['brands'] : array();
        
        // Tam eşleşme ara
        foreach ($brands as $brand) {
            if (strtolower($brand['name']) === strtolower($brand_name)) {
                return $brand['id'];
            }
        }
        
        // Kısmi eşleşme ara
        if (!empty($brands)) {
            return $brands[0]['id'];
        }
        
        return false;
    }
    
    /**
     * WooCommerce term ID'si ile Trendyol marka ID'sini bul
     *
     * @param int $term_id WooCommerce term ID
     * @return int|bool Marka ID veya bulunamadıysa false
     */
    public function get_trendyol_brand_id_by_term_id($term_id) {
        $brand_mappings = $this->get_brand_mappings();
        
        if (isset($brand_mappings[$term_id])) {
            return $brand_mappings[$term_id];
        }
        
        // Meta veriden kontrol et
        $trendyol_brand_id = get_term_meta($term_id, '_trendyol_brand_id', true);
        
        if (!empty($trendyol_brand_id)) {
            return $trendyol_brand_id;
        }
        
        return false;
    }
}