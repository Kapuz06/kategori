<?php
/**
 * Trendyol WooCommerce Admin Kategoriler Sınıfı
 * 
 * Kategori yönetimi için admin işlemlerini içerir
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Admin_Categories {

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        // AJAX işleyicileri
        add_action('wp_ajax_trendyol_search_categories_from_db', array($this, 'ajax_search_categories_from_db'));
        add_action('wp_ajax_trendyol_search_wc_categories', array($this, 'ajax_search_wc_categories'));
        add_action('wp_ajax_trendyol_map_categories', array($this, 'ajax_map_categories'));
        add_action('wp_ajax_trendyol_get_category_attributes', array($this, 'ajax_get_category_attributes'));
        add_action('wp_ajax_trendyol_map_category_attribute', array($this, 'ajax_map_category_attribute'));
        
        // Cron job kurulumu
        add_action('wp', array($this, 'setup_category_sync_cron'));
        add_action('trendyol_categories_sync_event', array($this, 'sync_categories_cron_job'));
    }
    
    /**
     * Kategori senkronizasyonu için cron görevini ayarla (aylık)
     */
    public function setup_category_sync_cron() {
        if (!wp_next_scheduled('trendyol_categories_sync_event')) {
            wp_schedule_event(time(), 'monthly', 'trendyol_categories_sync_event');
        }
    }
    
    /**
     * Kategori senkronizasyonu cron işi
     */
    public function sync_categories_cron_job() {
        $categories_api = new Trendyol_WC_Categories_API();
        $categories_api->sync_categories_to_database();
    }
    
    /**
     * Eklenti devre dışı bırakıldığında cron görevini temizle
     */
    public function clear_category_sync_cron() {
        $timestamp = wp_next_scheduled('trendyol_categories_sync_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'trendyol_categories_sync_event');
        }
    }
    
    /**
     * Kategorileri veritabanından arama AJAX işleyicisi
     */
    public function ajax_search_categories_from_db() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Arama metni
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term) || strlen($search_term) < 3) {
            wp_send_json_error(['message' => __('Arama için en az 3 karakter gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Kategorileri veritabanından ara
        $categories_api = new Trendyol_WC_Categories_API();
        $results = $categories_api->search_categories_from_database($search_term);
        
        if (empty($results)) {
            wp_send_json_error(['message' => __('Arama sonucu bulunamadı.', 'trendyol-woocommerce')]);
            return;
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * WooCommerce kategorilerini arama AJAX işleyicisi
     */
    public function ajax_search_wc_categories() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Arama metni
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term)) {
            wp_send_json_error(['message' => __('Arama terimi gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // WooCommerce kategorilerini ara
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'name__like' => $search_term
        );
        
        $terms = get_terms($args);
        
        if (is_wp_error($terms) || empty($terms)) {
            wp_send_json_error(['message' => __('Arama sonucu bulunamadı.', 'trendyol-woocommerce')]);
            return;
        }
        
        $results = array();
        foreach ($terms as $term) {
            $results[] = array(
                'id' => $term->term_id,
                'name' => $term->name
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Kategori eşleştirme AJAX işleyicisi
     */
    public function ajax_map_categories() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Parametreleri al
        $trendyol_category_id = isset($_POST['trendyol_category_id']) ? intval($_POST['trendyol_category_id']) : 0;
        $wc_category_ids = isset($_POST['wc_category_ids']) ? $_POST['wc_category_ids'] : array();
        
        if (empty($trendyol_category_id)) {
            wp_send_json_error(['message' => __('Trendyol kategori ID\'si gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // String olarak gelirse, array'e çevir
        if (!is_array($wc_category_ids) && is_string($wc_category_ids)) {
            $wc_category_ids = explode(',', $wc_category_ids);
        }
        
        // Değerleri temizle
        $wc_category_ids = array_map('intval', $wc_category_ids);
        $wc_category_ids = array_filter($wc_category_ids);
        
        // Eşleştirmeyi kaydet
        $categories_api = new Trendyol_WC_Categories_API();
        $result = $categories_api->save_category_mappings($trendyol_category_id, $wc_category_ids);
        
        if (isset($result['inserted']) && $result['inserted'] > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    __('%d WooCommerce kategorisi Trendyol kategori ID %d ile eşleştirildi.', 'trendyol-woocommerce'),
                    $result['inserted'],
                    $trendyol_category_id
                ),
                'mapped_count' => $result['inserted']
            ]);
        } else {
            wp_send_json_error(['message' => __('Eşleştirme işlemi başarısız oldu veya hiçbir kategori eşleştirilmedi.', 'trendyol-woocommerce')]);
        }
    }
    
    /**
     * Kategori niteliklerini al AJAX işleyicisi
     */
    public function ajax_get_category_attributes() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Parametreleri al
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if (empty($category_id)) {
            wp_send_json_error(['message' => __('Kategori ID\'si gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Nitelikleri al ve kaydet
        $categories_api = new Trendyol_WC_Categories_API();
        $result = $categories_api->get_and_save_category_attributes($category_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        // WooCommerce öznitelikleri al
        $wc_attributes = wc_get_attribute_taxonomies();
        $attributes_for_mapping = array();
        
        foreach ($wc_attributes as $attribute) {
            $attributes_for_mapping[] = array(
                'id' => $attribute->attribute_id,
                'name' => $attribute->attribute_label,
                'slug' => 'pa_' . $attribute->attribute_name
            );
        }
        
        $response = array(
            'trendyol_attributes' => isset($result['attributes']) ? $result['attributes'] : array(),
            'wc_attributes' => $attributes_for_mapping,
            'message' => sprintf(
                __('%d kategori niteliği alındı.', 'trendyol-woocommerce'),
                count(isset($result['attributes']) ? $result['attributes'] : array())
            )
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Kategori nitelik eşleştirme AJAX işleyicisi
     */
    public function ajax_map_category_attribute() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Parametreleri al
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $attribute_id = isset($_POST['attribute_id']) ? intval($_POST['attribute_id']) : 0;
        $wc_attribute_id = isset($_POST['wc_attribute_id']) ? sanitize_text_field($_POST['wc_attribute_id']) : '';
        
        if (empty($category_id) || empty($attribute_id)) {
            wp_send_json_error(['message' => __('Kategori ID ve nitelik ID\'si gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Eşleştirmeyi kaydet
        $categories_api = new Trendyol_WC_Categories_API();
        $result = $categories_api->save_attribute_mapping($category_id, $attribute_id, $wc_attribute_id);
        
        if ($result) {
            wp_send_json_success([
                'message' => __('Nitelik eşleştirmesi başarıyla kaydedildi.', 'trendyol-woocommerce')
            ]);
        } else {
            wp_send_json_error(['message' => __('Nitelik eşleştirmesi kaydedilemedi.', 'trendyol-woocommerce')]);
        }
    }
    
    /**
     * Kategoriler sayfasını oluştur
     */
    public function render_categories_page() {
        global $wpdb;
        
        // Veritabanı tablosu oluştur
        $categories_api = new Trendyol_WC_Categories_API();
        $categories_api->create_categories_table();
        
        // Kategori senkronizasyonu işlemi
        if (isset($_POST['trendyol_sync_categories']) && check_admin_referer('trendyol_sync_categories')) {
            $result = $categories_api->sync_categories_to_database();
            
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $success = false;
            } else {
                $message = sprintf(
                    __('%d kategori veritabanına kaydedildi. (%d yeni, %d güncellendi)', 'trendyol-woocommerce'),
                    $result['total'],
                    $result['inserted'],
                    $result['updated']
                );
                $success = true;
            }
        }
        
        // Eşleşmiş kategorileri getir
        $mapped_categories = array();
        $mappings_table = $wpdb->prefix . 'trendyol_category_mappings';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$mappings_table'") == $mappings_table) {
            $mapped_result = $wpdb->get_results(
                "SELECT m.*, tc.name as trendyol_name, t.name as wc_name
                FROM $mappings_table m
                LEFT JOIN {$wpdb->prefix}trendyol_categories tc ON m.trendyol_category_id = tc.id
                LEFT JOIN {$wpdb->terms} t ON m.wc_category_id = t.term_id
                ORDER BY tc.name ASC",
                ARRAY_A
            );
            
            // Trendyol kategorisine göre grupla
            foreach ($mapped_result as $mapping) {
                $trendyol_id = $mapping['trendyol_category_id'];
                
                if (!isset($mapped_categories[$trendyol_id])) {
                    $mapped_categories[$trendyol_id] = array(
                        'trendyol_id' => $trendyol_id,
                        'trendyol_name' => $mapping['trendyol_name'],
                        'wc_categories' => array()
                    );
                }
                
                $mapped_categories[$trendyol_id]['wc_categories'][] = array(
                    'id' => $mapping['wc_category_id'],
                    'name' => $mapping['wc_name']
                );
            }
        }
        
        // Son senkronizasyon zamanı
        $last_sync = get_option('trendyol_categories_last_sync', '');
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/categories.php');
    }
}
