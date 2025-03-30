<?php
/**
 * Trendyol WooCommerce Ürünler Sınıfı
 * 
 * Trendyol ürün yönetimi admin işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Products {

    /**
     * Ürün senkronizasyon sınıfı
     *
     * @var Trendyol_WC_Product_Sync
     */
    protected $product_sync;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->product_sync = new Trendyol_WC_Product_Sync();
        
        // AJAX işleyicileri
        add_action('wp_ajax_trendyol_get_products', array($this, 'ajax_get_trendyol_products'));
        add_action('wp_ajax_trendyol_get_wc_products', array($this, 'ajax_get_wc_products'));
        add_action('wp_ajax_trendyol_import_product', array($this, 'ajax_import_product'));
        add_action('wp_ajax_trendyol_export_product', array($this, 'ajax_export_product'));
        add_action('wp_ajax_trendyol_search_wc_products', array($this, 'ajax_search_wc_products'));
        add_action('wp_ajax_trendyol_search_trendyol_products', array($this, 'ajax_search_trendyol_products'));
        add_action('wp_ajax_trendyol_match_products', array($this, 'ajax_match_products'));
    }
    /**
     * AJAX: Trendyol ürünlerini getir
     */
    public function ajax_get_trendyol_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Trendyol ürünlerini çek
        $products_api = new Trendyol_WC_Products_API();
        
        $params = array(
            'page' => max(0, $page - 1), // Trendyol API'si 0 tabanlı sayfalama kullanıyor
            'size' => 10
        );
        
        if (!empty($search)) {
            $params['name'] = $search;
        }
        
        $response = $products_api->get_products($params);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        // API yanıtını formatlayarak gönder
        $products = isset($response['content']) ? $response['content'] : array();
        $total_pages = isset($response['totalPages']) ? $response['totalPages'] : 1;
        $total_elements = isset($response['totalElements']) ? $response['totalElements'] : count($products);
        
        if (isset($response['content'])) {
            $products = $response['content'];
            
            // Her Trendyol ürünü için, WooCommerce'de eşleşme durumunu kontrol et
            foreach ($products as &$product) {
                // Trendyol ID'sini kullanarak eşleşme kontrolü yap
                $trendyol_id = isset($product['id']) ? $product['id'] : '';
                
                // Ürünün WooCommerce'de eşleşme durumunu kontrol et
                $wc_product_id = $this->get_product_id_by_trendyol_id($trendyol_id);
                // Boolean değer olarak atayalım
                $product['is_matched'] = !empty($wc_product_id) ? true : false;
            }
        }
        
        wp_send_json_success(array(
            'products' => $products,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_products' => $total_elements
        ));
    }
    
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
     * AJAX: WooCommerce ürünlerini getir
     */
    public function ajax_get_wc_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // WooCommerce ürünlerini getir
        $args = array(
            'status' => 'publish',
            'limit' => 10,
            'page' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $products_query = new WC_Product_Query($args);
        $products = $products_query->get_products();
        
        // Toplam ürün sayısını ve sayfa sayısını hesapla
        $total_products = wc_get_products(array_merge(
            $args,
            array('limit' => -1, 'return' => 'ids')
        ));
        
        $total_count = count($total_products);
        $total_pages = ceil($total_count / 10);
        
        // Ürün verisini formatla
        $formatted_products = array();
        
        foreach ($products as $product) {
            $trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
            
            $formatted_products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'trendyol_id' => $trendyol_product_id,
                'is_matched' => !empty($trendyol_product_id), // Eşleşme durumu
                'stock_quantity' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
                'edit_link' => get_edit_post_link($product->get_id())
            );
        }
        
        wp_send_json_success(array(
            'products' => $formatted_products,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_products' => $total_count
        ));
    }
    
    /**
     * AJAX: Önbellekten ürün verisi ile yeni ürün oluştur
     */
    public function ajax_import_cached_product() {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/ajax-import-cache-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - AJAX ÖNBELLEKTEN ÜRÜN AKTARMA BAŞLIYOR\n", 3, $log_file);
        
        // PHP hata raporlama seviyesini yükselt
        $old_error_reporting = error_reporting(E_ALL);
        $old_display_errors = ini_get('display_errors');
        ini_set('display_errors', 1);
        
        try {
            check_ajax_referer('trendyol-wc-nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                error_log("Yetki hatası\n", 3, $log_file);
                wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
                return;
            }
            
            // Önbellekten gelen ürün verilerini al
            $cached_product_json = isset($_POST['cached_product']) ? stripslashes($_POST['cached_product']) : '';
            
            if (empty($cached_product_json)) {
                error_log("Önbellekten gelen ürün verisi boş\n", 3, $log_file);
                wp_send_json_error(__('Ürün verisi gönderilmedi.', 'trendyol-woocommerce'));
                return;
            }
            
            // JSON formatından PHP dizisine dönüştür
            $product_data = json_decode($cached_product_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON çözümleme hatası: " . json_last_error_msg() . "\n", 3, $log_file);
                wp_send_json_error(__('Ürün verisi geçersiz.', 'trendyol-woocommerce'));
                return;
            }
            
            error_log("Ürün ID: " . (isset($product_data['id']) ? $product_data['id'] : 'yok') . "\n", 3, $log_file);
            error_log("Ürün Adı: " . (isset($product_data['title']) ? $product_data['title'] : 'yok') . "\n", 3, $log_file);
            
            // Önbellekten gelen ürün verilerini işle ve formatını düzenle
            $product_data = $this->prepare_cached_product_data($product_data);
            error_log("Önişleme sonrası ürün verisi: " . print_r($product_data, true) . "\n", 3, $log_file);
            
            // WooCommerce ürünü oluştur
            error_log("WooCommerce ürün oluşturma işlemi başlatılıyor...\n", 3, $log_file);
            
            // Ürün API sınıfını başlat
            $products_api = new Trendyol_WC_Products_API();
            
            // WooCommerce formatına dönüştür
            error_log("WooCommerce formatına dönüştürme işlemi başlatılıyor...\n", 3, $log_file);
            $wc_product_data = $products_api->format_product_for_woocommerce($product_data);
            
            if (is_wp_error($wc_product_data)) {
                error_log("Format hatası: " . $wc_product_data->get_error_message() . "\n", 3, $log_file);
                wp_send_json_error($wc_product_data->get_error_message());
                return;
            }
            
            error_log("WooCommerce formatı: " . print_r($wc_product_data, true) . "\n", 3, $log_file);
            
            // Ürün oluşturma işlemi - basit ürün
            error_log("Ürün oluşturma işlemi başlatılıyor...\n", 3, $log_file);
            
            // Ürün nesnesi oluştur
            $product = new WC_Product_Simple();
            
            // Temel veriler
            $product->set_name($wc_product_data['name']);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_description($wc_product_data['description']);
            $product->set_short_description($wc_product_data['short_description'] ?? '');
            $product->set_sku($wc_product_data['sku']);
            
            // Fiyat
            if (isset($wc_product_data['regular_price'])) {
                $product->set_regular_price($wc_product_data['regular_price']);
            }
            
            if (isset($wc_product_data['sale_price'])) {
                $product->set_sale_price($wc_product_data['sale_price']);
            }
            
            // Stok
            $product->set_manage_stock(true);
            $product->set_stock_quantity($wc_product_data['stock_quantity'] ?? 0);
            $product->set_stock_status($wc_product_data['stock_status'] ?? 'instock');
            
            // Meta veriler
            if (isset($wc_product_data['meta_data']) && is_array($wc_product_data['meta_data'])) {
                foreach ($wc_product_data['meta_data'] as $meta) {
                    $product->update_meta_data($meta['key'], $meta['value']);
                }
            }
            
            // Kategori atama işlemi
            $trendyol_category_id = null;
            $wc_category_ids = array();
            
            // Meta verilerinden Trendyol kategori ID'sini bul
            if (isset($wc_product_data['meta_data']) && is_array($wc_product_data['meta_data'])) {
                foreach ($wc_product_data['meta_data'] as $meta) {
                    if ($meta['key'] === '_trendyol_category_id' && !empty($meta['value'])) {
                        $trendyol_category_id = $meta['value'];
                        error_log("Trendyol kategori ID bulundu: " . $trendyol_category_id . "\n", 3, $log_file);
                        break;
                    }
                }
            }
            
            // Eğer meta verilerden bulunamadıysa direkt ürün verilerinden bak
            if (empty($trendyol_category_id) && isset($product_data['categoryId'])) {
                $trendyol_category_id = $product_data['categoryId'];
                error_log("Ürün verilerinden Trendyol kategori ID bulundu: " . $trendyol_category_id . "\n", 3, $log_file);
            }
            
            // Kategori eşleştirmelerini al
            if (!empty($trendyol_category_id)) {
                error_log("Trendyol kategori ID'si için WooCommerce kategorisi aranıyor: " . $trendyol_category_id . "\n", 3, $log_file);
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                error_log("Kategori eşleştirmeleri: " . print_r($category_mappings, true) . "\n", 3, $log_file);
                
                // Eşleşen WooCommerce kategorilerini bul
                foreach ($category_mappings as $wc_cat_id => $trend_cat_id) {
                    if ($trend_cat_id == $trendyol_category_id) {
                        $wc_category_ids[] = (int)$wc_cat_id;
                        error_log("Eşleşen WooCommerce kategori ID'si bulundu: " . $wc_cat_id . "\n", 3, $log_file);
                    }
                }
                
                if (!empty($wc_category_ids)) {
                    error_log("Kategoriler atanıyor: " . implode(', ', $wc_category_ids) . "\n", 3, $log_file);
                    $product->set_category_ids($wc_category_ids);
                } else {
                    error_log("Eşleşen WooCommerce kategorisi bulunamadı\n", 3, $log_file);
                    
                    // Varsayılan kategoriyi kullan
                    $settings = get_option('trendyol_wc_settings', array());
                    $default_category = isset($settings['default_category']) ? intval($settings['default_category']) : 0;
                    
                    if ($default_category > 0) {
                        error_log("Varsayılan WooCommerce kategorisi kullanılıyor: " . $default_category . "\n", 3, $log_file);
                        $product->set_category_ids(array($default_category));
                    }
                }
            } else {
                error_log("Trendyol kategori ID'si bulunamadı\n", 3, $log_file);
            }
            
            // Ürün Nitelikleri (Attributes) İyileştirilmiş Sürüm
            if (isset($wc_product_data['attributes']) && is_array($wc_product_data['attributes'])) {
                error_log("Nitelikler (Attributes) işleniyor... Sayı: " . count($wc_product_data['attributes']) . "\n", 3, $log_file);
                
                $attributes = array();
                
                // Gerekli fonksiyonları içeri aktarma
                if (!function_exists('wc_create_attribute')) {
                    include_once(WC_ABSPATH . 'includes/admin/wc-admin-functions.php');
                }
                
                foreach ($wc_product_data['attributes'] as $attribute) {
                    $attr_name = isset($attribute['name']) ? $attribute['name'] : '';
                    $attr_options = isset($attribute['options']) ? $attribute['options'] : array();
                    $attr_visible = isset($attribute['visible']) ? $attribute['visible'] : true;
                    $attr_variation = isset($attribute['variation']) ? $attribute['variation'] : false;
                    
                    if (empty($attr_name) || empty($attr_options)) {
                        error_log("Eksik nitelik verileri, atlanıyor: " . print_r($attribute, true) . "\n", 3, $log_file);
                        continue;
                    }
                    
                    error_log("Nitelik işleniyor: $attr_name, Değerler: " . print_r($attr_options, true) . "\n", 3, $log_file);
                    
                    // Nitelik slugını oluştur
                    $attr_slug = wc_sanitize_taxonomy_name($attr_name);
                    $taxonomy_name = 'pa_' . $attr_slug;
                    
                    // Nitelik taksonomisini oluştur veya al
                    $attribute_id = $this->create_or_get_attribute_taxonomy($attr_name, $attr_slug, $log_file);
                    
                    if (!$attribute_id) {
                        error_log("Nitelik taksonomisi oluşturulamadı, nitelik atlanamadı: $attr_name\n", 3, $log_file);
                        continue;
                    }
                    
                    error_log("Nitelik taksonomisi oluşturuldu/bulundu. ID: $attribute_id, Taksonomi: $taxonomy_name\n", 3, $log_file);
                    
                    // Nitelik değerlerini oluştur
                    $terms_ids = array();
                    foreach ($attr_options as $option) {
                        $term = $this->create_or_get_term($option, $taxonomy_name, $log_file);
                        if ($term && !is_wp_error($term)) {
                            $terms_ids[] = $term->term_id;
                            error_log("Terim oluşturuldu/bulundu: " . $term->name . " (ID: " . $term->term_id . ")\n", 3, $log_file);
                        } else {
                            error_log("Terim oluşturulamadı: " . ($term instanceof WP_Error ? $term->get_error_message() : 'Bilinmeyen hata') . "\n", 3, $log_file);
                        }
                    }
                    
                    // WooCommerce ürün niteliği nesnesi oluştur
                    $attr_object = new WC_Product_Attribute();
                    $attr_object->set_id($attribute_id);
                    $attr_object->set_name($taxonomy_name);
                    $attr_object->set_options($terms_ids);
                    $attr_object->set_visible($attr_visible);
                    $attr_object->set_variation($attr_variation);
                    
                    $attributes[$taxonomy_name] = $attr_object;
                    error_log("Nitelik eklendi: $taxonomy_name\n", 3, $log_file);
                }
                
                if (!empty($attributes)) {
                    error_log("Ürüne " . count($attributes) . " nitelik ekleniyor\n", 3, $log_file);
                    $product->set_attributes($attributes);
                }
            }
            
            // Görseller
            if (isset($wc_product_data['images']) && is_array($wc_product_data['images'])) {
                error_log("Görseller işleniyor...\n", 3, $log_file);
                $image_ids = array();
                
                foreach ($wc_product_data['images'] as $index => $image) {
                    if (!isset($image['src'])) {
                        error_log("Görsel #$index'de src bulunamadı\n", 3, $log_file);
                        continue;
                    }
                    
                    $image_url = $image['src'];
                    error_log("Görsel yükleniyor: $image_url\n", 3, $log_file);
                    
                    // Görseli indir ve ekle
                    $upload = $this->upload_image_simple($image_url);
                    if (is_wp_error($upload)) {
                        error_log("Görsel yükleme hatası: " . $upload->get_error_message() . "\n", 3, $log_file);
                        continue;
                    }
                    
                    if ($upload) {
                        if ($index === 0) {
                            error_log("Ana görsel ayarlanıyor: " . $upload . "\n", 3, $log_file);
                            $product->set_image_id($upload);
                        } else {
                            error_log("Galeri görseli ekleniyor: " . $upload . "\n", 3, $log_file);
                            $image_ids[] = $upload;
                        }
                    }
                }
                
                if (!empty($image_ids)) {
                    $product->set_gallery_image_ids($image_ids);
                }
            }
            
            // Ürünü kaydet
            error_log("Ürün kaydediliyor...\n", 3, $log_file);
            $product_id = $product->save();
            
            if (!$product_id) {
                error_log("Ürün kaydedilemedi\n", 3, $log_file);
                wp_send_json_error(__('Ürün kaydedilemedi.', 'trendyol-woocommerce'));
                return;
            }
            
            // Kategori ve niteliklerin doğru atandığından emin olmak için direkt wp_set_object_terms ile de atama yap
            if (!empty($wc_category_ids)) {
                error_log("wp_set_object_terms ile kategori ataması tekrar yapılıyor\n", 3, $log_file);
                wp_set_object_terms($product_id, $wc_category_ids, 'product_cat');
            }
            
            
            // Nitelikleri ürüne doğrudan bağlama işlemi yapılıyor
            if (!empty($attributes)) {
                error_log("Nitelikleri ürüne doğrudan bağlama işlemi yapılıyor\n", 3, $log_file);
                
                // Ürün nitelikleri için kullanılacak meta veri
                $product_attributes_meta = array();
                
                foreach ($attributes as $taxonomy => $attr) {
                    $term_ids = $attr->get_options();
                    if (!empty($term_ids)) {
                        error_log("$taxonomy için " . count($term_ids) . " terim bağlanıyor\n", 3, $log_file);
                        wp_set_object_terms($product_id, $term_ids, $taxonomy);
                        
                        // Her bir taksonomiye ait meta veriyi hazırla
                        $product_attributes_meta[$taxonomy] = array(
                            'name' => $taxonomy,
                            'value' => '',
                            'position' => $attr->get_position(),
                            'is_visible' => $attr->get_visible() ? 1 : 0,
                            'is_variation' => $attr->get_variation() ? 1 : 0,
                            'is_taxonomy' => 1
                        );
                    }
                }
                
                // Tüm nitelikleri tek seferde güncelle
                if (!empty($product_attributes_meta)) {
                    update_post_meta($product_id, '_product_attributes', $product_attributes_meta);
                    error_log("Tüm nitelikler _product_attributes meta alanına kaydedildi: " . 
                              print_r($product_attributes_meta, true) . "\n", 3, $log_file);
                }
            }
            
            error_log("Ürün başarıyla oluşturuldu, WC Ürün ID: $product_id\n", 3, $log_file);
            
            wp_send_json_success(array(
                'product_id' => $product_id,
                'message' => __('Ürün başarıyla WooCommerce\'e aktarıldı.', 'trendyol-woocommerce')
            ));
        } catch (Exception $e) {
            error_log("HATA: " . $e->getMessage() . "\n", 3, $log_file);
            error_log("Hata satırı: " . $e->getLine() . "\n", 3, $log_file);
            error_log("Hata dosyası: " . $e->getFile() . "\n", 3, $log_file);
            error_log("Hata izi: " . $e->getTraceAsString() . "\n", 3, $log_file);
            wp_send_json_error('Hata: ' . $e->getMessage());
        } finally {
            // PHP hata raporlama ayarlarını eski haline getir
            error_reporting($old_error_reporting);
            ini_set('display_errors', $old_display_errors);
        }
    }
    
    /**
     * Nitelik taksonomisini oluştur veya al
     *
     * @param string $name Nitelik adı
     * @param string $slug Nitelik slug
     * @param string $log_file Log dosyası
     * @return int Nitelik ID'si
     */
    private function create_or_get_attribute_taxonomy($name, $slug, $log_file) {
        global $wpdb;
        error_log("\nNitelik taksonomi oluşturma/bulma: $name ($slug)\n", 3, $log_file);
        
        // Nitelik varsa mevcut olanı al
        $attribute_id = wc_attribute_taxonomy_id_by_name($slug);
        
        if ($attribute_id) {
            error_log("Nitelik zaten mevcut, ID: $attribute_id\n", 3, $log_file);
            return $attribute_id;
        }
        
        // Yeni nitelik oluştur
        $attribute_id = wc_create_attribute(array(
            'name'         => $name,
            'slug'         => $slug,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ));
        
        if (is_wp_error($attribute_id)) {
            error_log("Nitelik oluşturma hatası: " . $attribute_id->get_error_message() . "\n", 3, $log_file);
            
            // Plan B: Doğrudan veritabanına ekleyelim
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
                error_log("Plan B ile bile nitelik oluşturulamadı\n", 3, $log_file);
                return 0;
            }
            
            error_log("Plan B ile nitelik oluşturuldu, ID: $attribute_id\n", 3, $log_file);
        } else {
            error_log("Yeni nitelik oluşturuldu, ID: $attribute_id\n", 3, $log_file);
        }
        
        // Taksonomiyi kaydet
        $taxonomy_name = 'pa_' . $slug;
        if (!taxonomy_exists($taxonomy_name)) {
            register_taxonomy(
                $taxonomy_name,
                array('product'),
                array(
                    'labels'       => array(
                        'name' => $name,
                    ),
                    'hierarchical' => true,
                    'show_ui'      => true,
                    'query_var'    => true,
                    'rewrite'      => false,
                )
            );
            
            error_log("Taksonomi kaydedildi: $taxonomy_name\n", 3, $log_file);
        }
        
        // Önbelleği temizle
        delete_transient('wc_attribute_taxonomies');
        
        return $attribute_id;
    }
    
    /**
     * Terimi oluştur veya al
     *
     * @param string $name Terim adı
     * @param string $taxonomy Taksonomi adı
     * @param string $log_file Log dosyası
     * @return WP_Term|false|WP_Error Terim nesnesi, false veya hata
     */
    private function create_or_get_term($name, $taxonomy, $log_file) {
        error_log("Terim oluşturma/bulma: $name ($taxonomy)\n", 3, $log_file);
        
        // Taksonomi var mı kontrol et
        if (!taxonomy_exists($taxonomy)) {
            error_log("Taksonomi mevcut değil: $taxonomy\n", 3, $log_file);
            return false;
        }
        
        // Mevcut terimi kontrol et
        $term = get_term_by('name', $name, $taxonomy);
        
        if ($term) {
            error_log("Terim zaten mevcut: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
            return $term;
        }
        
        // Yeni terim oluştur
        $result = wp_insert_term($name, $taxonomy);
        
        if (is_wp_error($result)) {
            error_log("Terim oluşturma hatası: " . $result->get_error_message() . "\n", 3, $log_file);
            
            // Term exists hatası varsa, term_id'yi al
            if ($result->get_error_code() === 'term_exists') {
                $term_id = $result->get_error_data();
                $term = get_term($term_id, $taxonomy);
                if ($term) {
                    error_log("Hata verisi yoluyla mevcut terim bulundu: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
                    return $term;
                }
            }
            
            return $result;
        }
        
        $term = get_term($result['term_id'], $taxonomy);
        error_log("Yeni terim oluşturuldu: ID={$term->term_id}, name={$term->name}\n", 3, $log_file);
        
        return $term;
    }

    // Basitleştirilmiş resim yükleme fonksiyonu
    private function upload_image_simple($url) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/image-upload-simple-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - BASİT RESİM YÜKLEME: $url\n", 3, $log_file);
        
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        
        // Dosyayı indir
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            error_log("Dosya indirme hatası: " . $tmp->get_error_message() . "\n", 3, $log_file);
            return $tmp;
        }
        
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $tmp
        );
        
        // Medya kütüphanesine ekle
        $id = media_handle_sideload($file_array, 0);
        
        // Geçici dosyayı sil
        @unlink($tmp);
        
        if (is_wp_error($id)) {
            error_log("Medya yükleme hatası: " . $id->get_error_message() . "\n", 3, $log_file);
            return $id;
        }
        
        error_log("Resim başarıyla yüklendi, ID: $id\n", 3, $log_file);
        return $id;
    }
    /**
     * Önbellekten gelen ürün verilerini hazırla
     * 
     * @param array $product_data Önbellekten gelen ürün verileri
     * @return array Hazırlanmış ürün verileri
     */
    private function prepare_cached_product_data($product_data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/data-prep-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - Ürün verisi önişleme başlıyor\n", 3, $log_file);
        
        // API yanıtında pimCategoryId varsa, categoryId olarak da ayarla
        if (isset($product_data['pimCategoryId']) && !empty($product_data['pimCategoryId'])) {
            $product_data['categoryId'] = $product_data['pimCategoryId'];
            error_log("pimCategoryId ({$product_data['pimCategoryId']}) alanı categoryId olarak ayarlandı.\n", 3, $log_file);
        } elseif (isset($product_data['categoryId']) && !empty($product_data['categoryId'])) {
            error_log("categoryId zaten mevcut: {$product_data['categoryId']}\n", 3, $log_file);
        } elseif (isset($product_data['categoryName']) && !empty($product_data['categoryName'])) {
            error_log("Kategori ID eksik, kategori adından çıkarılmaya çalışılıyor.\n", 3, $log_file);
            
            // Kategori adından kategori ID'sini bul
            $category_id = $this->get_category_id_by_name($product_data['categoryName']);
            
            if ($category_id) {
                $product_data['categoryId'] = $category_id;
                error_log("Kategori adından ID bulundu: {$category_id}\n", 3, $log_file);
            } else {
                error_log("Kategori adından ID bulunamadı\n", 3, $log_file);
                
                // Varsayılan kategori ID'sini kullan
                $settings = get_option('trendyol_wc_settings', array());
                if (isset($settings['default_category_id']) && !empty($settings['default_category_id'])) {
                    $product_data['categoryId'] = $settings['default_category_id'];
                    error_log("Varsayılan kategori ID kullanıldı: {$product_data['categoryId']}\n", 3, $log_file);
                }
            }
        } else {
            error_log("Kategori bilgisi eksik!\n", 3, $log_file);
            
            // Varsayılan kategori ID'sini kullan
            $settings = get_option('trendyol_wc_settings', array());
            if (isset($settings['default_category_id']) && !empty($settings['default_category_id'])) {
                $product_data['categoryId'] = $settings['default_category_id'];
                error_log("Varsayılan kategori ID kullanıldı: {$product_data['categoryId']}\n", 3, $log_file);
            }
        }
        
        // Resimler formatını kontrol et
        if (isset($product_data['images']) && is_array($product_data['images'])) {
            error_log("Resimler formatı kontrol ediliyor. Resim sayısı: " . count($product_data['images']) . "\n", 3, $log_file);
            
            // Bazen API resim formatı farklı olabilir
            $formatted_images = [];
            foreach ($product_data['images'] as $index => $image) {
                if (is_string($image)) {
                    // Eğer sadece URL string ise, doğru formata çevir
                    $formatted_images[] = [
                        'url' => $image
                    ];
                } elseif (is_array($image) && isset($image['url'])) {
                    // Zaten doğru formatta
                    $formatted_images[] = $image;
                } else {
                    // Farklı bir format, URL alanı var mı kontrol et
                    if (is_array($image) && (isset($image['imageUrl']) || isset($image['imageurl']))) {
                        $url = isset($image['imageUrl']) ? $image['imageUrl'] : $image['imageurl'];
                        $formatted_images[] = [
                            'url' => $url
                        ];
                    }
                }
            }
            
            if (!empty($formatted_images)) {
                $product_data['images'] = $formatted_images;
                error_log("Resimler yeniden formatlandı. Yeni resim sayısı: " . count($formatted_images) . "\n", 3, $log_file);
            }
        }
        
        // Özellikler formatını kontrol et
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            error_log("Özellikler formatı kontrol ediliyor.\n", 3, $log_file);
            
            $formatted_attributes = [];
            foreach ($product_data['attributes'] as $attribute) {
                if (is_array($attribute)) {
                    // attributeName veya attributeValue yoksa ekle
                    if (!isset($attribute['attributeName']) && isset($attribute['name'])) {
                        $attribute['attributeName'] = $attribute['name'];
                    }
                    
                    if (!isset($attribute['attributeValue']) && isset($attribute['value'])) {
                        $attribute['attributeValue'] = $attribute['value'];
                    }
                    
                    // Eğer hala gerekli alanlar yoksa, atla
                    if (isset($attribute['attributeName']) && (isset($attribute['attributeValue']) || isset($attribute['customAttributeValue']))) {
                        $formatted_attributes[] = $attribute;
                    }
                }
            }
            
            if (!empty($formatted_attributes)) {
                $product_data['attributes'] = $formatted_attributes;
                error_log("Özellikler yeniden formatlandı.\n", 3, $log_file);
            }
        }
        
        error_log("Önişleme tamamlandı.\n", 3, $log_file);
        return $product_data;
    }
    /**
     * Kategori adından Trendyol kategori ID'sini bul
     *
     * @param string $category_name Kategori adı
     * @return int|false Kategori ID'si veya bulunamadıysa false
     */
    private function get_category_id_by_name($category_name) {
        if (empty($category_name)) {
            return false;
        }
        
        // Trendyol kategorileri API'sini kullan
        $categories_api = new Trendyol_WC_Categories_API();
        $categories_response = $categories_api->get_categories();
        
        if (is_wp_error($categories_response) || !isset($categories_response['categories'])) {
            return false;
        }
        
        foreach ($categories_response['categories'] as $category) {
            if (strtolower(trim($category['name'])) === strtolower(trim($category_name))) {
                return $category['id'];
            }
        }
        
        // Kesin eşleşme bulunamadıysa, benzer isimli kategorileri dene
        foreach ($categories_response['categories'] as $category) {
            if (strpos(strtolower($category['name']), strtolower($category_name)) !== false ||
                strpos(strtolower($category_name), strtolower($category['name'])) !== false) {
                return $category['id'];
            }
        }
        
        return false;
    }
    
    /**
     * Toplu ürün aktarımı AJAX işleyicisi
     */
    public function ajax_bulk_import_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
        $size = isset($_POST['size']) ? intval($_POST['size']) : 10;
        $skip_existing = isset($_POST['skip_existing']) ? (bool)$_POST['skip_existing'] : false;
        
        $product_sync = new Trendyol_WC_Product_Sync();
        $result = $product_sync->sync_products_from_trendyol(array(
            'page' => $page,
            'size' => $size,
            'skip_existing' => $skip_existing
        ));
        
        if (isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: WooCommerce'den Trendyol'a ürün aktar
     */
    public function ajax_export_product() {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/ajax-export-' . date('Y-m-d') . '.log';
        error_log("\n" . date('Y-m-d H:i:s') . " - AJAX export_product başlıyor\n", 3, $log_file);
        error_log("POST: " . print_r($_POST, true) . "\n", 3, $log_file);
        
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            error_log("Yetki hatası\n", 3, $log_file);
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if ($product_id <= 0) {
            error_log("Geçersiz ürün ID: $product_id\n", 3, $log_file);
            wp_send_json_error(__('Geçersiz ürün ID.', 'trendyol-woocommerce'));
            return;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            error_log("Ürün bulunamadı: $product_id\n", 3, $log_file);
            wp_send_json_error(__('Ürün bulunamadı.', 'trendyol-woocommerce'));
            return;
        }
        
        error_log("Ürün bulundu: " . $product->get_name() . " (SKU: " . $product->get_sku() . ")\n", 3, $log_file);
        
        // Trendyol'a gönder
        $product_sync = new Trendyol_WC_Product_Sync();
        
        error_log("Ürün senkronizasyonu başlatılıyor...\n", 3, $log_file);
        $result = $product_sync->sync_product_to_trendyol($product);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            error_log("Senkronizasyon hatası: $error_message\n", 3, $log_file);
            if ($error_data) {
                error_log("Hata detayları: " . print_r($error_data, true) . "\n", 3, $log_file);
            }
            
            wp_send_json_error($error_message);
            return;
        }
        
        error_log("Senkronizasyon başarılı\n", 3, $log_file);
        wp_send_json_success(array(
            'message' => __('Ürün başarıyla Trendyol\'a aktarıldı.', 'trendyol-woocommerce')
        ));
    }
    
    /**
     * AJAX: WooCommerce ürünlerini ara
     */
    public function ajax_search_wc_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search) || strlen($search) < 3) {
            wp_send_json_error(__('Arama terimi çok kısa. En az 3 karakter girin.', 'trendyol-woocommerce'));
            return;
        }
        
        // WooCommerce ürünlerini ara
        $args = array(
            'status' => 'publish',
            'limit' => 20,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => $search
        );
        
        $products_query = new WC_Product_Query($args);
        $products = $products_query->get_products();
        
        // Ürün verisini formatla
        $formatted_products = array();
        
        foreach ($products as $product) {
            $formatted_products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price()
            );
        }
        
        wp_send_json_success(array(
            'products' => $formatted_products
        ));
    }
    
    /**
     * AJAX: Trendyol ürünlerini ara
     */
    public function ajax_search_trendyol_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search) || strlen($search) < 3) {
            wp_send_json_error(__('Arama terimi çok kısa. En az 3 karakter girin.', 'trendyol-woocommerce'));
            return;
        }
        
        // Trendyol ürünlerini ara
        $products_api = new Trendyol_WC_Products_API();
        
        $params = array(
            'name' => $search,
            'size' => 20
        );
        
        $response = $products_api->get_products($params);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        // API yanıtını formatlayarak gönder
        $products = isset($response['content']) ? $response['content'] : array();
        
        $formatted_products = array();
        
        foreach ($products as $product) {
            $formatted_products[] = array(
                'id' => $product['id'],
                'name' => $product['title'],
                'barcode' => $product['barcode'],
                'salePrice' => $product['salePrice']
            );
        }
        
        wp_send_json_success(array(
            'products' => $formatted_products
        ));
    }
    
    /**
     * AJAX: Ürünleri eşleştir
     */
    public function ajax_match_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        // Parametre kontrollerini sıkılaştırın
        $source_id = isset($_POST['source_id']) ? sanitize_text_field($_POST['source_id']) : '';
        $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
        $target_id = isset($_POST['target_id']) ? sanitize_text_field($_POST['target_id']) : '';
        
        // Boş değer kontrolü
        if (empty($source_id) || empty($target_id) || empty($source_type)) {
            wp_send_json_error(__('Geçersiz ürün bilgileri. Lütfen tüm gerekli alanları doldurun.', 'trendyol-woocommerce'));
            return;
        }
        
        if ($source_type === 'trendyol') {
            // Trendyol ürününü WooCommerce ürünüyle eşleştir
            $product = wc_get_product($target_id);
            
            if (!$product) {
                wp_send_json_error(__('WooCommerce ürünü bulunamadı.', 'trendyol-woocommerce'));
                return;
            }
            
            // Trendyol ID'sini kaydet
            $product->update_meta_data('_trendyol_product_id', $source_id);
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            $product->save();
            
            wp_send_json_success(array(
                'message' => __('Ürün başarıyla eşleştirildi.', 'trendyol-woocommerce')
            ));
        } elseif ($source_type === 'woocommerce') {
            // WooCommerce ürününü Trendyol ürünüyle eşleştir
            $product = wc_get_product($source_id);
            
            if (!$product) {
                wp_send_json_error(__('WooCommerce ürünü bulunamadı.', 'trendyol-woocommerce'));
                return;
            }
            
            // Trendyol ürün ID'sini kaydet - API isteği olmadan doğrudan hedef ID'yi kullan
            $product->update_meta_data('_trendyol_product_id', $target_id);
            $product->update_meta_data('_trendyol_last_sync', current_time('mysql'));
            $product->save();
            
            wp_send_json_success(array(
                'message' => __('Ürün başarıyla eşleştirildi.', 'trendyol-woocommerce')
            ));
        } else {
            wp_send_json_error(__('Geçersiz kaynak türü.', 'trendyol-woocommerce'));
        }
    }
    /**
     * Ürünler sayfasını oluştur
     */
    public function render_products_page() {
        // İşlem kontrolü
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        $message = '';
        $success = false;

        // Senkronizasyon işlemi
        if ($action === 'sync_products' && check_admin_referer('trendyol_sync_products')) {
            $direction = isset($_POST['sync_direction']) ? sanitize_text_field($_POST['sync_direction']) : 'both';
            $limit = isset($_POST['sync_limit']) ? absint($_POST['sync_limit']) : 50;
            $skip_existing = isset($_POST['skip_existing']) ? true : false;
            
            $result = $this->product_sync->sync_products(array(
                'direction' => $direction,
                'limit' => $limit,
                'skip_existing' => $skip_existing
            ));
            
            $success = isset($result['success']) ? $result['success'] : false;
            $message = isset($result['message']) ? $result['message'] : '';
        }
        
        // Tekli ürün senkronizasyonu
        if ($action === 'sync_single_product' && check_admin_referer('trendyol_sync_single_product')) {
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                
                if ($product) {
                    $result = $this->product_sync->sync_product_to_trendyol($product);
                    
                    if (is_wp_error($result)) {
                        $success = false;
                        $message = $result->get_error_message();
                    } else {
                        $success = true;
                        $message = __('Ürün başarıyla Trendyol\'a gönderildi.', 'trendyol-woocommerce');
                    }
                } else {
                    $success = false;
                    $message = __('Ürün bulunamadı.', 'trendyol-woocommerce');
                }
            } else {
                $success = false;
                $message = __('Geçersiz ürün ID\'si.', 'trendyol-woocommerce');
            }
        }
        
        // Stok senkronizasyonu
        if ($action === 'sync_stock' && check_admin_referer('trendyol_sync_stock')) {
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            
            if ($product_id > 0) {
                $result = $this->product_sync->update_product_stock($product_id);
                
                if (is_wp_error($result)) {
                    $success = false;
                    $message = $result->get_error_message();
                } else {
                    $success = true;
                    $message = __('Ürün stok bilgisi başarıyla güncellendi.', 'trendyol-woocommerce');
                }
            } else {
                $success = false;
                $message = __('Geçersiz ürün ID\'si.', 'trendyol-woocommerce');
            }
        }
        
        // Son senkronize edilen ürünleri getir
        $synced_products = $this->get_synced_products();
        
        // Şablonu göster
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/products.php');
    }
    
    /**
     * Senkronize edilmiş ürünleri getir
     *
     * @param int $limit Limit
     * @return array Ürün listesi
     */
    private function get_synced_products($limit = 10) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, pm1.meta_value as trendyol_id, pm2.meta_value as last_sync 
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_trendyol_product_id'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trendyol_last_sync'
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm1.meta_value IS NOT NULL
             ORDER BY pm2.meta_value DESC
             LIMIT %d",
            $limit
        );
        
        $products = $wpdb->get_results($sql);
        
        // Ürün nesnelerini oluştur
        $result = array();
        
        foreach ($products as $product_data) {
            $product = wc_get_product($product_data->ID);
            
            if ($product) {
                $result[] = array(
                    'id' => $product_data->ID,
                    'product' => $product,
                    'trendyol_id' => $product_data->trendyol_id,
                    'last_sync' => $product_data->last_sync
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Senkronizasyon hatası olan ürünleri getir
     *
     * @param int $limit Limit
     * @return array Ürün listesi
     */
    private function get_sync_error_products($limit = 10) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as error_message 
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_trendyol_sync_error'
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             ORDER BY pm.meta_id DESC
             LIMIT %d",
            $limit
        );
        
        $products = $wpdb->get_results($sql);
        
        // Ürün nesnelerini oluştur
        $result = array();
        
        foreach ($products as $product_data) {
            $product = wc_get_product($product_data->ID);
            
            if ($product) {
                $result[] = array(
                    'id' => $product_data->ID,
                    'product' => $product,
                    'error_message' => $product_data->error_message
                );
            }
        }
        
        return $result;
    }
}