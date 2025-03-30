<?php
/**
 * Trendyol WooCommerce Admin Sınıfı
 * 
 * Admin paneli ve sayfaları için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Admin {

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        // Admin CSS ve JS dosyalarını yükle
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Ürün meta kutusu ekle
        add_action('add_meta_boxes', array($this, 'add_product_meta_boxes'));
        
        // Ürün metası kaydet
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        
        // Ürün listesine sütun ekle
        add_filter('manage_product_posts_columns', array($this, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'render_product_columns'), 10, 2);
        
        // Sipariş meta kutusu ekle
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        
        // Sipariş işlemleri
        add_action('woocommerce_order_status_changed', array($this, 'order_status_changed'), 10, 3);
    }

    /**
     * Admin menüsünü oluştur
     */
    public function setup_menu() {
        // Ana menü
        add_menu_page(
            __('Trendyol WooCommerce', 'trendyol-woocommerce'),
            __('Trendyol', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-woocommerce',
            array($this, 'render_dashboard_page'),
            'dashicons-store',
            58
        );
        
        // Alt menüler
        add_submenu_page(
            'trendyol-woocommerce',
            __('Gösterge Paneli', 'trendyol-woocommerce'),
            __('Gösterge Paneli', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-woocommerce',
            array($this, 'render_dashboard_page')
        );
        // Alt menüler
        add_submenu_page(
            'trendyol-woocommerce',
            __('İşlem Durumları', 'trendyol-woocommerce'),
            __('İşlem Durumları', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-batch-requests',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'trendyol-woocommerce',
            __('Ürünler', 'trendyol-woocommerce'),
            __('Ürünler', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-products',
            array($this, 'render_products_page')
        );
        
        add_submenu_page(
            'trendyol-woocommerce',
            __('Siparişler', 'trendyol-woocommerce'),
            __('Siparişler', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-orders',
            array($this, 'render_orders_page')
        );
        
        add_submenu_page(
            'trendyol-woocommerce',
            __('Kategoriler', 'trendyol-woocommerce'),
            __('Kategoriler', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-categories',
            array($this, 'render_categories_page')
        );
        
        add_submenu_page(
            'trendyol-woocommerce',
            __('Markalar', 'trendyol-woocommerce'),
            __('Markalar', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-brands',
            array($this, 'render_brands_page')
        );
        
        add_submenu_page(
            'trendyol-woocommerce',
            __('Ayarlar', 'trendyol-woocommerce'),
            __('Ayarlar', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-settings',
            array($this, 'render_settings_page')
        );
		
        add_submenu_page(
            'trendyol-woocommerce',
            __('Webhook', 'trendyol-woocommerce'),
            __('Webhook', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-webhook',
            array($this, 'render_webhook_page')
        );
		
        add_submenu_page(
            'trendyol-woocommerce',
            __('Loglar', 'trendyol-woocommerce'),
            __('Loglar', 'trendyol-woocommerce'),
            'manage_woocommerce',
            'trendyol-wc-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Admin CSS ve JS dosyalarını yükle
     */
    public function enqueue_admin_assets($hook) {
        // Sadece Trendyol sayfalarında yükle
        if (strpos($hook, 'trendyol-wc') === false) {
            return;
        }
        
        wp_enqueue_style('trendyol-wc-admin-css');
        wp_enqueue_script('trendyol-wc-admin-js');
    }
    
    /**
     * Log dosyalarını temizle
     */
    public function delete_log_files() {
        if (isset($_POST['trendyol_delete_logs']) && check_admin_referer('trendyol_delete_logs')) {
            $log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/';
            
            // Belirli bir log dosyasını sil
            if (isset($_POST['log_file']) && !empty($_POST['log_file'])) {
                $log_file = sanitize_file_name($_POST['log_file']);
                $file_path = $log_dir . $log_file;
                
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                    return [
                        'success' => true,
                        'message' => sprintf(__('"%s" log dosyası başarıyla silindi.', 'trendyol-woocommerce'), $log_file)
                    ];
                }
            } 
            // Tüm log dosyalarını sil
            else if (isset($_POST['delete_all_logs']) && $_POST['delete_all_logs'] == '1') {
                $deleted_count = 0;
                
                if (file_exists($log_dir)) {
                    $files = scandir($log_dir);
                    
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'log') {
                            if (unlink($log_dir . $file)) {
                                $deleted_count++;
                            }
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'message' => sprintf(__('%d log dosyası başarıyla silindi.', 'trendyol-woocommerce'), $deleted_count)
                ];
            }
            
            return [
                'success' => false,
                'message' => __('Silinecek log dosyası belirtilmedi.', 'trendyol-woocommerce')
            ];
        }
        
        return null;
    }
    /**
     * Gösterge paneli sayfasını oluştur
     */
    public function render_dashboard_page() {
    // API bağlantı durumu kontrolü
    $settings = get_option('trendyol_wc_settings', array());
    
    // Bağlantı durumu kontrolü
    if (!empty($settings['api_username']) && !empty($settings['api_password']) && !empty($settings['supplier_id'])) {
        $connection_status = array(
            'connected' => true,
            'message' => ''
        );
    } else {
        $connection_status = array(
            'connected' => false,
            'message' => __('API kimlik bilgileri eksik', 'trendyol-woocommerce')
        );
    }
    
    // Özet istatistikleri al
    $product_stats = $this->get_product_stats();
    $order_stats = $this->get_order_stats();
    
    // Batch işlem durumlarını al
    $batch_requests = $this->get_recent_batch_requests();
    
    include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/dashboard.php');
}

/**
 * Son batch isteklerini al
 */
private function get_recent_batch_requests() {
    $batch_requests = get_option('trendyol_batch_requests', array());
    
    // En son 5 isteği göster, en yeniler önce
    $batch_requests = array_slice(array_reverse($batch_requests), 0, 5);
    
    // Her batch için güncel durumu kontrol et ve güncelle
    $this->update_batch_status_if_needed($batch_requests);
    
    return $batch_requests;
}

/**
 * Batch durumlarını kontrol et ve gerekirse güncelle
 */
private function update_batch_status_if_needed($batch_requests) {
    $api = new Trendyol_WC_API();
    $settings = get_option('trendyol_wc_settings', array());
    $seller_id = isset($settings['supplier_id']) ? $settings['supplier_id'] : '';
    
    if (empty($seller_id) || empty($batch_requests)) {
        return;
    }
    
    $all_requests = get_option('trendyol_batch_requests', array());
    $updated = false;
    
    foreach ($batch_requests as $key => $request) {
        // Son kontrol üzerinden en az 5 dakika geçmiş ve hala PROCESSING durumundaysa kontrol et
        $last_check_time = isset($request['last_check']) ? $request['last_check'] : 0;
        $current_time = time();
        
        if (($current_time - $last_check_time > 300) && $request['status'] === 'PROCESSING') {
            $endpoint = "integration/product/sellers/{$seller_id}/products/batch-requests/{$request['id']}";
            $response = $api->get($endpoint);
            
            if (!is_wp_error($response) && isset($response['status'])) {
                // Tüm batch isteklerini bul ve güncelle
                foreach ($all_requests as $i => $stored_request) {
                    if ($stored_request['id'] === $request['id']) {
                        $all_requests[$i]['status'] = $response['status'];
                        $all_requests[$i]['last_check'] = $current_time;
                        $updated = true;
                        break;
                    }
                }
            }
        }
    }
    
    if ($updated) {
        update_option('trendyol_batch_requests', $all_requests);
    }
}
    /**
     * Ürünler sayfasını oluştur
     */
    public function render_products_page() {
        // Ürün listesi sayfası
        $products_admin = new Trendyol_WC_Products();
        $products_admin->render_products_page();
    }

    /**
     * Siparişler sayfasını oluştur
     */
    public function render_orders_page() {
        // Sipariş listesi sayfası
        $orders_admin = new Trendyol_WC_Orders();
        $orders_admin->render_orders_page();
    }
	/**
	 * Webhook sayfasını oluştur
	 */
	public function render_webhook_page() {
		// Webhook ayarlarını al
		$settings = get_option('trendyol_wc_settings', array());
		$webhook_endpoint = isset($settings['webhook_endpoint']) ? $settings['webhook_endpoint'] : 'trendyol-webhook';
		$webhook_url = home_url($webhook_endpoint);
		$webhook_enabled = isset($settings['webhook_enabled']) ? $settings['webhook_enabled'] : 'yes';
		$webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
		
		// Webhook log dosyalarını kontrol et
		$log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/';
		$webhook_logs = array();
		
		if (file_exists($log_dir)) {
			$files = scandir($log_dir);
			foreach ($files as $file) {
				if (strpos($file, 'webhook-') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
					$webhook_logs[] = $file;
				}
			}
			rsort($webhook_logs); // En yeni logları üste getir
		}
		
		// Şablonu göster
		include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/webhook.php');
	}
    /**
     * Kategoriler sayfasını oluştur
     */
    /**
     * Kategoriler sayfasını oluştur
     */
    public function render_categories_page() {
        // Kategoriler
        $categories_api = new Trendyol_WC_Categories_API();
        
        // Kategori senkronizasyonu işlemi
        if (isset($_POST['trendyol_sync_categories']) && check_admin_referer('trendyol_sync_categories')) {
            $create_new = isset($_POST['trendyol_create_categories']) ? true : false;
            $result = $categories_api->import_categories_to_woocommerce($create_new);
            
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $success = false;
            } else {
                $message = sprintf(__('%d kategori başarıyla senkronize edildi.', 'trendyol-woocommerce'), count($result));
                $success = true;
            }
        }
        
        // Tekli kategori eşleme işlemi
        if (isset($_POST['trendyol_map_single_category']) && check_admin_referer('trendyol_map_category')) {
            $trendyol_category_id = isset($_POST['trendyol_category_id']) ? intval($_POST['trendyol_category_id']) : 0;
            $wc_category_id = isset($_POST['wc_category_id']) ? intval($_POST['wc_category_id']) : 0;
            
            if ($trendyol_category_id > 0 && $wc_category_id > 0) {
                // Mevcut eşlemeleri al
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                
                // Eşlemeyi güncelle veya ekle
                $category_mappings[$wc_category_id] = $trendyol_category_id;
                
                // Trendyol kategori ID'sini meta veri olarak kaydet
                update_term_meta($wc_category_id, '_trendyol_category_id', $trendyol_category_id);
                
                // Eşlemeleri kaydet
                update_option('trendyol_wc_category_mappings', $category_mappings);
                
                $message = __('Kategori eşlemesi başarıyla güncellendi.', 'trendyol-woocommerce');
                $success = true;
            } else {
                $message = __('Geçersiz kategori bilgileri.', 'trendyol-woocommerce');
                $success = false;
            }
        }
        // Çoklu kategori eşleme işlemi
        if (isset($_POST['trendyol_map_multiple_categories']) && check_admin_referer('trendyol_map_category')) {
            $trendyol_category_id = isset($_POST['trendyol_category_id']) ? intval($_POST['trendyol_category_id']) : 0;
            $wc_category_ids = isset($_POST['wc_category_id']) ? $_POST['wc_category_id'] : array();
            
            if ($trendyol_category_id > 0) {
                // Mevcut eşlemeleri al
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                
                // Önce bu Trendyol kategorisi için tüm mevcut eşlemeleri kaldır
                foreach ($category_mappings as $wc_id => $trend_id) {
                    if ($trend_id == $trendyol_category_id) {
                        unset($category_mappings[$wc_id]);
                        delete_term_meta($wc_id, '_trendyol_category_id');
                    }
                }
                
                // Yeni eşlemeleri ekle
                $updated_count = 0;
                if (!empty($wc_category_ids)) {
                    foreach ($wc_category_ids as $wc_category_id) {
                        $wc_category_id = intval($wc_category_id);
                        if ($wc_category_id > 0) {
                            // Eşlemeyi güncelle veya ekle
                            $category_mappings[$wc_category_id] = $trendyol_category_id;
                            
                            // Trendyol kategori ID'sini meta veri olarak kaydet
                            update_term_meta($wc_category_id, '_trendyol_category_id', $trendyol_category_id);
                            $updated_count++;
                        }
                    }
                }
                
                // Eşlemeleri kaydet
                update_option('trendyol_wc_category_mappings', $category_mappings);
                
                $message = sprintf(__('%d kategori eşlemesi başarıyla güncellendi.', 'trendyol-woocommerce'), $updated_count);
                $success = true;
            } else {
                $message = __('Geçersiz kategori bilgileri.', 'trendyol-woocommerce');
                $success = false;
            }
        }
        // Kategori eşleme kaldırma işlemi
        if (isset($_POST['trendyol_unmap_category']) && check_admin_referer('trendyol_unmap_category')) {
            $wc_category_id = isset($_POST['wc_category_id']) ? intval($_POST['wc_category_id']) : 0;
            
            if ($wc_category_id > 0) {
                // Mevcut eşlemeleri al
                $category_mappings = get_option('trendyol_wc_category_mappings', array());
                
                // Eşlemeyi kaldır
                if (isset($category_mappings[$wc_category_id])) {
                    unset($category_mappings[$wc_category_id]);
                    
                    // Meta veriyi temizle
                    delete_term_meta($wc_category_id, '_trendyol_category_id');
                    
                    // Eşlemeleri kaydet
                    update_option('trendyol_wc_category_mappings', $category_mappings);
                    
                    $message = __('Kategori eşlemesi başarıyla kaldırıldı.', 'trendyol-woocommerce');
                    $success = true;
                } else {
                    $message = __('Kaldırılacak eşleşme bulunamadı.', 'trendyol-woocommerce');
                    $success = false;
                }
            } else {
                $message = __('Geçersiz kategori bilgileri.', 'trendyol-woocommerce');
                $success = false;
            }
        }
        
        // Kategori listesi
        $categories_response = $categories_api->get_categories();
        $categories = isset($categories_response['categories']) ? $categories_response['categories'] : array();
        
        // Mevcut eşleşmeler
        $category_mappings = get_option('trendyol_wc_category_mappings', array());
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/categories.php');
    }

    /**
     * Markalar sayfasını oluştur
     */
    public function render_brands_page() {
        // Markalar
        $brands_api = new Trendyol_WC_Brands_API();
        
        // Marka senkronizasyonu işlemi
        if (isset($_POST['trendyol_sync_brands']) && check_admin_referer('trendyol_sync_brands')) {
            $create_new = isset($_POST['trendyol_create_brands']) ? true : false;
            $result = $brands_api->import_brands_to_woocommerce($create_new);
            
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $success = false;
            } else {
                $message = sprintf(__('%d marka başarıyla senkronize edildi.', 'trendyol-woocommerce'), count($result));
                $success = true;
            }
        }
        
        // Marka listesi
        $brands_response = $brands_api->get_brands();
        $brands = isset($brands_response['brands']) ? $brands_response['brands'] : array();
        
        // Mevcut eşleşmeler
        $brand_mappings = $brands_api->get_brand_mappings();
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/brands.php');
    }

    /**
     * Ayarlar sayfasını oluştur
     */
    public function render_settings_page() {
        $settings = new Trendyol_WC_Settings();
        $settings->render_settings_page();
    }

    
    /**
     * Loglar sayfasını oluştur
     */
    public function render_logs_page() {
        // Silme işlemi sonucunu kontrol et
        $delete_result = $this->delete_log_files();
        
        // Log dosya listesi
        $log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/';
        $log_files = array();
        
        if (file_exists($log_dir)) {
            $files = scandir($log_dir);
            
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'log') {
                    $log_files[] = $file;
                }
            }
            
            // En yeni log dosyalarını üste getir
            rsort($log_files);
        }
        
        // Belirli bir log dosyasını görüntüleme
        $current_log = isset($_GET['log']) ? sanitize_file_name($_GET['log']) : '';
        $log_content = '';
        
        if (!empty($current_log) && file_exists($log_dir . $current_log)) {
            $log_content = file_get_contents($log_dir . $current_log);
        }
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/logs.php');
    }

    /**
     * Ürün meta kutusu ekle
     */
    public function add_product_meta_boxes() {
        add_meta_box(
            'trendyol_product_data',
            __('Trendyol Ürün Bilgileri', 'trendyol-woocommerce'),
            array($this, 'render_product_meta_box'),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Ürün meta kutusu içeriği
     */
    public function render_product_meta_box($post) {
        $product = wc_get_product($post->ID);
        
        // Trendyol ürün bilgileri
        $trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
        $trendyol_barcode = $product->get_meta('_trendyol_barcode', true);
        $trendyol_brand = $product->get_meta('_trendyol_brand', true);
        $trendyol_category_id = $product->get_meta('_trendyol_category_id', true);
        $trendyol_last_sync = $product->get_meta('_trendyol_last_sync', true);
        
        // Nonce 
        wp_nonce_field('trendyol_product_meta_save', 'trendyol_product_meta_nonce');
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/product-meta-box.php');
    }

    /**
     * Ürün meta verilerini kaydet
     */
    public function save_product_meta($post_id) {
        // Nonce kontrolü
        if (!isset($_POST['trendyol_product_meta_nonce']) || !wp_verify_nonce($_POST['trendyol_product_meta_nonce'], 'trendyol_product_meta_save')) {
            return;
        }
        
        // Yetki kontrolü
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }
        
        // Meta verileri güncelle
        $product = wc_get_product($post_id);
        
        if (isset($_POST['trendyol_barcode'])) {
            $product->update_meta_data('_trendyol_barcode', sanitize_text_field($_POST['trendyol_barcode']));
        }
        
        if (isset($_POST['trendyol_brand'])) {
            $product->update_meta_data('_trendyol_brand', sanitize_text_field($_POST['trendyol_brand']));
        }
        
        if (isset($_POST['trendyol_category_id'])) {
            $product->update_meta_data('_trendyol_category_id', absint($_POST['trendyol_category_id']));
        }
        
        if (isset($_POST['trendyol_sync_to_trendyol'])) {
            // Ürünü Trendyol'a gönder
            $product_sync = new Trendyol_WC_Product_Sync();
            $product_sync->sync_product_to_trendyol($product);
        }
        
        $product->save();
    }

    /**
     * Ürün listesi sütunlarını ekle
     */
    public function add_product_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // "price" sütunundan sonra Trendyol sütununu ekle
            if ($key === 'price') {
                $new_columns['trendyol_status'] = __('Trendyol Durumu', 'trendyol-woocommerce');
            }
        }
        
        return $new_columns;
    }

    /**
     * Ürün listesi sütunlarını oluştur
     */
    public function render_product_columns($column, $post_id) {
        if ($column === 'trendyol_status') {
            $product = wc_get_product($post_id);
            $trendyol_product_id = $product->get_meta('_trendyol_product_id', true);
            $trendyol_last_sync = $product->get_meta('_trendyol_last_sync', true);
            
            if (!empty($trendyol_product_id)) {
                echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ';
                echo __('Trendyol\'da', 'trendyol-woocommerce');
                
                if (!empty($trendyol_last_sync)) {
                    echo ' (' . human_time_diff(strtotime($trendyol_last_sync), current_time('timestamp')) . ' ' . __('önce', 'trendyol-woocommerce') . ')';
                }
                
                echo '</mark>';
            } else {
                echo '<mark class="no"><span class="dashicons dashicons-no"></span> ';
                echo __('Trendyol\'da değil', 'trendyol-woocommerce');
                echo '</mark>';
            }
        }
    }

    /**
     * Sipariş meta kutusu ekle
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'trendyol_order_data',
            __('Trendyol Sipariş Bilgileri', 'trendyol-woocommerce'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Sipariş meta kutusu içeriği
     */
    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        // Trendyol sipariş bilgileri
        $trendyol_order_number = $order->get_meta('_trendyol_order_number', true);
        $trendyol_package_number = $order->get_meta('_trendyol_package_number', true);
        $trendyol_order_status = $order->get_meta('_trendyol_order_status', true);
        $trendyol_last_sync = $order->get_meta('_trendyol_last_sync', true);
        $trendyol_tracking_number = $order->get_meta('_trendyol_tracking_number', true);
        $trendyol_cargo_provider = $order->get_meta('_trendyol_cargo_provider', true);
        
        include(TRENDYOL_WC_PLUGIN_DIR . 'templates/admin/order-meta-box.php');
    }

    /**
     * Sipariş durumu değişikliği
     */
    public function order_status_changed($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        
        // Trendyol siparişi mi kontrol et
        $trendyol_order_number = $order->get_meta('_trendyol_order_number', true);
        
        if (!empty($trendyol_order_number)) {
            // Trendyol sipariş durumunu güncelle
            $orders_api = new Trendyol_WC_Orders_API();
            $trendyol_status = $orders_api->get_trendyol_status_from_wc_order_status($new_status);
            
            $shipment_package_id = $order->get_meta('_trendyol_shipment_package_id', true);
            
            if (!empty($shipment_package_id) && !empty($trendyol_status)) {
                $orders_api->update_package_status($shipment_package_id, $trendyol_status);
                
                // Sipariş meta verisini güncelle
                $order->update_meta_data('_trendyol_order_status', $trendyol_status);
                $order->update_meta_data('_trendyol_last_sync', current_time('mysql'));
                $order->save();
            }
        }
    }

    /**
     * Ürün istatistiklerini al
     */
    private function get_product_stats() {
        global $wpdb;
        
        // Toplam Trendyol'a gönderilen ürün sayısı
        $total_synced = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_trendyol_product_id' AND meta_value != ''"
        );
        
        // Son 24 saatte gönderilen ürün sayısı
        $last_24h_synced = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_trendyol_last_sync' 
             AND meta_value > '" . date('Y-m-d H:i:s', strtotime('-24 hours')) . "'"
        );
        
        // Son başarısız senkronizasyonlar
        $failed_syncs = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value as error_message
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_trendyol_sync_error'
             WHERE p.post_type = 'product'
             ORDER BY pm.meta_id DESC
             LIMIT 5"
        );
        
        return array(
            'total_synced' => $total_synced,
            'last_24h_synced' => $last_24h_synced,
            'failed_syncs' => $failed_syncs
        );
    }

    /**
     * Sipariş istatistiklerini al
     */
    private function get_order_stats() {
        global $wpdb;
        
        // Toplam Trendyol siparişi sayısı
        $total_orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_trendyol_order_number' AND meta_value != ''"
        );
        
        // Son 24 saatte alınan sipariş sayısı
        $last_24h_orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_trendyol_order_number'
             WHERE p.post_type = 'shop_order'
             AND p.post_date > '" . date('Y-m-d H:i:s', strtotime('-24 hours')) . "'"
        );
        
        // Trendyol sipariş durumları dağılımı
        $order_statuses = $wpdb->get_results(
            "SELECT pm.meta_value as status, COUNT(*) as count
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = '_trendyol_order_status'
             GROUP BY pm.meta_value
             ORDER BY count DESC"
        );
        
        return array(
            'total_orders' => $total_orders,
            'last_24h_orders' => $last_24h_orders,
            'order_statuses' => $order_statuses
        );
    }
	
	/**
	 * API bağlantı durumunu kontrol et
	 *
	 * @return array Bağlantı durumu bilgileri
	 */
	private function get_connection_status() {
		$api = new Trendyol_WC_API();
		$settings = get_option('trendyol_wc_settings', array());
		
		// API kimlik bilgileri kontrol et
		if (empty($settings['api_username']) || empty($settings['api_password']) || empty($settings['supplier_id'])) {
			return array(
				'connected' => false,
				'message' => __('API kimlik bilgileri eksik', 'trendyol-woocommerce')
			);
		}
		
		// Test API isteği gönder
		$brands_api = new Trendyol_WC_Brands_API();
		$response = $brands_api->get_brands(array('size' => 1));
		
		if (is_wp_error($response)) {
			return array(
				'connected' => false,
				'message' => $response->get_error_message()
			);
		}
		
		return array(
			'connected' => true,
			'message' => __('Bağlantı başarılı', 'trendyol-woocommerce')
		);
	}
}
