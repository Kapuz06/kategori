<?php
/**
 * Plugin Name: Trendyol WooCommerce Entegrasyonu
 * Plugin URI: https://www.example.com/trendyol-woocommerce
 * Description: WooCommerce ve Trendyol arasında ürün, stok ve sipariş senkronizasyonu sağlar.
 * Version: 1.0.0
 * Author: Trendyol Entegrasyon
 * Author URI: https://www.example.com
 * Text Domain: trendyol-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('TRENDYOL_WC_VERSION', '1.0.0');
define('TRENDYOL_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRENDYOL_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRENDYOL_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Eklenti ana sınıfı
 */
final class Trendyol_WooCommerce {
    /**
     * Sınıf örneği
     *
     * @var Trendyol_WooCommerce
     */
    protected static $_instance = null;

    /**
     * Trendyol_WooCommerce örneği alır
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Yapılandırıcı
     */
    public function __construct() {
	    $this->define_constants();
	    $this->includes();
	    $this->init_hooks();
	    // Webhook sınıfını başlat
	    new Trendyol_WC_Webhook();
	    // Batch tracker'ı başlat
	    new Trendyol_WC_Batch_Tracker();
	    // Batch widget'ı başlat
	    new Trendyol_WC_Batch_Widget();
	}

    /**
     * Sabitleri tanımla
     */
    private function define_constants() {
        // API sabitleri
        define('TRENDYOL_API_URL', 'https://apigw.trendyol.com/');
        define('TRENDYOL_API_SUPPLIERS_URL', 'https://apigw.trendyol.com/');
    }

    /**
     * Gerekli dosyaları dahil et
     */
    private function includes() {
	    // Mevcut dosyalar
	
	    // Yardımcı fonksiyonlar
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/helpers/trendyol-wc-helpers.php';
	
	    // API sınıfları
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-api.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-products-api.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-orders-api.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-categories-api.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-brands-api.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-batch-tracker.php'; // Yeni satır
	    
	    // Admin sınıfları
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/admin/class-trendyol-wc-admin.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/admin/class-trendyol-wc-settings.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/admin/class-trendyol-wc-products.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/admin/class-trendyol-wc-orders.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'trendyol-batch-widget.php'; // Yeni satır
	
	    // Senkronizasyon sınıfları
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/sync/class-trendyol-wc-product-sync.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/sync/class-trendyol-wc-order-sync.php';
	    // Batch işleme sınıfları
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/api/class-trendyol-wc-batch-tracker.php';
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/admin/class-trendyol-wc-batch-manager.php';
	    // Webhook sınıfları
	    require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/webhook/class-trendyol-wc-webhook.php';
	}

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Ana plugin dosyasında, hook'ları başlatırken:
        $this->brands_api = new Trendyol_WC_Brands_API();
        add_action('wp_ajax_trendyol_map_brand', array($this->brands_api, 'ajax_map_brand'));
        add_action('wp_ajax_trendyol_unmap_brand', array($this->brands_api, 'ajax_unmap_brand'));
                
        // Kategori ve marka arama işleyicileri
        add_action('wp_ajax_trendyol_search_categories', array($this, 'ajax_search_categories'));
        add_action('wp_ajax_trendyol_search_brands', array($this, 'ajax_search_brands'));
        
        // WooCommerce varlığını kontrol et
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // Ayarlar bağlantısı ekle
        add_filter('plugin_action_links_' . TRENDYOL_WC_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Admin menüsünü ve sayfaları yükle
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
        }
        // AJAX işleyicileri kaydını kontrol et
        $products = new Trendyol_WC_Products();
        // AJAX işleyicilerini kaydet
        add_action('wp_ajax_trendyol_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_trendyol_sync_orders', array($this, 'ajax_sync_orders'));
        // AJAX loglama ayarlarını ekleyin
        $this->setup_ajax_logging();
        // Stok güncellemeleri için hook
        add_action('woocommerce_product_set_stock', array($this, 'product_stock_changed'));
        add_action('woocommerce_variation_set_stock', array($this, 'product_stock_changed'));
        
        // Zamanlı görevler 
        add_action('trendyol_wc_hourly_event', array($this, 'scheduled_sync'));
        register_activation_hook(__FILE__, array($this,'trendyol_create_brands_table'));
        register_activation_hook(__FILE__, array($this,'trendyol_wc_create_brand_tables'));
        
        register_deactivation_hook(__FILE__, array($this,'trendyol_clear_brand_sync_cron'));
        // Etkinleştirme hook'u
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        // AJAX işleyicilerini kaydet
        
        add_action('wp_ajax_trendyol_import_product', array($this, 'ajax_import_product'));
        add_action('wp_ajax_trendyol_bulk_import_products', array($this, 'ajax_bulk_import_products'));
        add_action('wp_ajax_trendyol_import_cached_product', array($this, 'ajax_import_cached_product'));
        add_action('wp', array($this, 'trendyol_setup_brand_sync_cron'));
        add_action('trendyol_brands_sync_event', array($this,'trendyol_brands_sync_cron_job'));
        
        // AJAX işleyicilerini kaydet
        add_action('wp_ajax_trendyol_sync_brands_to_database', array($this->brands_api, 'ajax_sync_brands_to_database'));
        add_action('wp_ajax_trendyol_search_brands_from_database', array($this->brands_api, 'ajax_search_brands_from_database'));
        add_action('wp_ajax_search_trendyol_brands', array($this->brands_api, 'ajax_search_trendyol_brands'));
    }
    
    /**
     * Marka senkronizasyonu için cron görevini ayarla
     */
    function trendyol_setup_brand_sync_cron() {
        if (!wp_next_scheduled('trendyol_brands_sync_event')) {
            wp_schedule_event(time(), 'weekly', 'trendyol_brands_sync_event');
        }
    }
    /**
     * Marka senkronizasyonu cron işi
     */
    function trendyol_brands_sync_cron_job() {
        $brands_api = new Trendyol_WC_Brands_API();
        $brands_api->sync_all_brands_to_database();
    }
    /**
     * Eklenti devre dışı bırakıldığında cron görevini temizle
     */
    function trendyol_clear_brand_sync_cron() {
        $timestamp = wp_next_scheduled('trendyol_brands_sync_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'trendyol_brands_sync_event');
        }
    }
    
    function trendyol_wc_create_brand_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Marka eşleşmeleri tablosu - yeni yapıyla
        $brand_matches_table = $wpdb->prefix . 'trendyol_brand_matches';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$brand_matches_table}'") != $brand_matches_table) {
            $sql = "CREATE TABLE {$brand_matches_table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                wc_attribute_term varchar(255) NOT NULL,
                trendyol_brand_id bigint(20) NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY wc_attribute_term (wc_attribute_term)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Trendyol markalarını saklamak için veritabanı tablosu oluştur
     */
    function trendyol_create_brands_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendyol_brands';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            last_updated DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    /**
     * AJAX Önbellekten ürün aktarma
     */
    public function ajax_import_cached_product() {
        $products = new Trendyol_WC_Products();
        $products->ajax_import_cached_product();
    }
    /**
     * AJAX ürün içe aktarımı
     */
    public function ajax_import_product() {
        $products = new Trendyol_WC_Products();
        $products->ajax_import_product();
    }
    
    /**
     * AJAX toplu ürün içe aktarımı
     */
    public function ajax_bulk_import_products() {
        $products = new Trendyol_WC_Products();
        $products->ajax_bulk_import_products();
    }
    /**
     * WooCommerce varlığını kontrol et
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    /**
     * WooCommerce eksik uyarısı
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>' . 
             sprintf(__('Trendyol WooCommerce Entegrasyonu, WooCommerce\'in kurulu ve aktif olmasını gerektirir. %s', 'trendyol-woocommerce'), 
                '<a href="' . admin_url('plugin-install.php?tab=search&s=woocommerce') . '">' . 
                __('Lütfen WooCommerce\'i yükleyin ve etkinleştirin', 'trendyol-woocommerce') . '</a>') . 
             '</p></div>';
    }

    /**
     * Ayarlar sayfası bağlantısı ekle
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=trendyol-wc-settings') . '">' . __('Ayarlar', 'trendyol-woocommerce') . '</a>',
        );
        return array_merge($plugin_links, $links);
    }
    public function setup_ajax_logging() {
        // AJAX isteklerini logla
        add_action('wp_ajax_nopriv_trendyol_get_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_get_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_get_wc_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_import_product', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_export_product', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_search_wc_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_search_trendyol_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_match_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_sync_products', array($this, 'log_ajax_request'), 1);
        add_action('wp_ajax_trendyol_sync_orders', array($this, 'log_ajax_request'), 1);
        
        // AJAX yanıtlarını logla
        add_filter('wp_die_ajax_handler', array($this, 'register_ajax_response_logger'), 1);
    }
    
    // AJAX isteğini loglama fonksiyonu
    public function log_ajax_request() {
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : 'unknown';
        $log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs';
        $log_file = $log_dir . '/ajax-' . date('Y-m-d') . '.log';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_entry = date('Y-m-d H:i:s') . " - AJAX İSTEĞİ - $action\n";
        $log_entry .= "POST Verileri: " . print_r($_POST, true) . "\n";
        $log_entry .= "GET Verileri: " . print_r($_GET, true) . "\n";
        $log_entry .= "Referrer: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Bilinmiyor') . "\n";
        $log_entry .= "User Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Bilinmiyor') . "\n";
        $log_entry .= "--------------------------\n";
        
        error_log($log_entry, 3, $log_file);
    }
    
    // AJAX yanıt loglamasını kaydet
    public function register_ajax_response_logger($handler) {
        return array($this, 'log_ajax_response');
    }
    
    // AJAX yanıtını loglama fonksiyonu
    public function log_ajax_response($message) {
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : 'unknown';
        $log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs';
        $log_file = $log_dir . '/ajax-' . date('Y-m-d') . '.log';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_entry = date('Y-m-d H:i:s') . " - AJAX YANITI - $action\n";
        
        // Buffered output'u al
        $output = ob_get_contents();
        if (!empty($output)) {
            $log_entry .= "Response Output: " . $output . "\n";
        }
        
        $log_entry .= "--------------------------\n";
        
        error_log($log_entry, 3, $log_file);
        
        // Orijinal handler'ı çağır
        $handler = '_ajax_wp_die_handler';
        return call_user_func($handler, $message);
    }
    /**
     * Admin başlatma
     */
    public function admin_init() {
        // Admin JS ve CSS dosyalarını kaydet
        wp_register_style('trendyol-wc-admin-css', TRENDYOL_WC_PLUGIN_URL . 'assets/css/admin.css', array(), TRENDYOL_WC_VERSION);
        wp_register_script('trendyol-wc-admin-js', TRENDYOL_WC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TRENDYOL_WC_VERSION, true);
    
        // JavaScript için yerelleştirme
        wp_localize_script('trendyol-wc-admin-js', 'trendyol_wc_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('trendyol-wc-nonce'),
            'i18n' => array(
                'syncing' => __('Senkronize Ediliyor...', 'trendyol-woocommerce'),
                'sync_complete' => __('Senkronizasyon Tamamlandı!', 'trendyol-woocommerce'),
                'sync_error' => __('Senkronizasyon Hatası!', 'trendyol-woocommerce'),
                'confirm_sync' => __('Bu işlem mevcut ürün verilerini güncelleyecek. Devam etmek istiyor musunuz?', 'trendyol-woocommerce'),
                'import' => __('Aktar', 'trendyol-woocommerce'),
                'export' => __('Aktar', 'trendyol-woocommerce'),
                'match' => __('Eşle', 'trendyol-woocommerce'),
                'imported' => __('Aktarıldı', 'trendyol-woocommerce'),
                'exported' => __('Aktarıldı', 'trendyol-woocommerce'),
                'matched' => __('Eşleştirildi', 'trendyol-woocommerce'),
                'error' => __('Hata', 'trendyol-woocommerce'),
                'ajax_error' => __('İstek işlenirken bir hata oluştu. Lütfen tekrar deneyin.', 'trendyol-woocommerce'),
                'no_products' => __('Ürün bulunamadı.', 'trendyol-woocommerce'),
                'search_to_match' => __('Eşleştirilecek ürünü aramak için yukarıdaki kutuyu kullanın.', 'trendyol-woocommerce'),
                'search_min_chars' => __('Arama için en az 3 karakter girin.', 'trendyol-woocommerce'),
                'searching' => __('Aranıyor...', 'trendyol-woocommerce'),
                'no_match_found' => __('Eşleşme bulunamadı.', 'trendyol-woocommerce')
            )
        ));
    }

    /**
     * Admin menüsü oluştur
     */
    public function admin_menu() {
        $admin = new Trendyol_WC_Admin();
        $admin->setup_menu();
    }

    /**
     * AJAX ürün senkronizasyonu
     */
    public function ajax_sync_products() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $product_sync = new Trendyol_WC_Product_Sync();
        $result = $product_sync->sync_products();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Trendyol kategorilerini arama AJAX işleyicisi
     */
    public function ajax_search_categories() {
        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'trendyol-settings-search')) {
            wp_send_json_error(__('Güvenlik doğrulaması başarısız.', 'trendyol-woocommerce'));
            return;
        }
        
        // Arama terimi
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term) || strlen($search_term) < 3) {
            wp_send_json_error(__('Arama terimi çok kısa. En az 3 karakter giriniz.', 'trendyol-woocommerce'));
            return;
        }
        
        // Kategorileri getir
        $categories_api = new Trendyol_WC_Categories_API();
        $response = $categories_api->get_categories();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $categories = isset($response['categories']) ? $response['categories'] : array();
        
        // Arama termiyle eşleşen kategorileri filtrele
        $filtered_categories = array();
        foreach ($categories as $category) {
            if (stripos($category['name'], $search_term) !== false) {
                $filtered_categories[] = array(
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'parentId' => isset($category['parentId']) ? $category['parentId'] : 0
                );
            }
            
            // Maksimum 10 sonuç göster
            if (count($filtered_categories) >= 10) {
                break;
            }
        }
        
        wp_send_json_success($filtered_categories);
    }
    
    /**
     * Trendyol markalarını arama AJAX işleyicisi
     */
    public function ajax_search_brands() {
        // Güvenlik kontrolü
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bu işlemi yapmaya yetkiniz yok.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Arama metni
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term) || strlen($search_term) < 3) {
            wp_send_json_error(['message' => __('Arama için en az 3 karakter gerekli.', 'trendyol-woocommerce')]);
            return;
        }
        
        // Brands API'sini başlat
        $brands_api = new Trendyol_WC_Brands_API();
        $results = $brands_api->search_brands_by_name($search_term);
        
        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
            return;
        }
        
        $brands = isset($results['brands']) ? $results['brands'] : [];
        
        wp_send_json_success($brands);
    }
        
    
    /**
     * AJAX sipariş senkronizasyonu
     */
    public function ajax_sync_orders() {
        check_ajax_referer('trendyol-wc-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Yetkiniz bulunmuyor.', 'trendyol-woocommerce'));
            return;
        }
        
        $order_sync = new Trendyol_WC_Order_Sync();
        $result = $order_sync->sync_orders();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Ürün stok değişikliği
     */
    public function product_stock_changed($product) {
        $settings = get_option('trendyol_wc_settings', array());
        $auto_stock_sync = isset($settings['auto_stock_sync']) ? $settings['auto_stock_sync'] : 'no';
        
        if ($auto_stock_sync === 'yes') {
            $product_sync = new Trendyol_WC_Product_Sync();
            $product_sync->update_product_stock($product->get_id());
        }
    }

    /**
     * Zamanlanmış senkronizasyon
     */
    public function scheduled_sync() {
        $settings = get_option('trendyol_wc_settings', array());
        
        // Otomatik sipariş senkronizasyonu
        $auto_order_sync = isset($settings['auto_order_sync']) ? $settings['auto_order_sync'] : 'no';
        if ($auto_order_sync === 'yes') {
            $order_sync = new Trendyol_WC_Order_Sync();
            $order_sync->sync_orders();
        }
        
        // Otomatik ürün senkronizasyonu
        $auto_product_sync = isset($settings['auto_product_sync']) ? $settings['auto_product_sync'] : 'no';
        if ($auto_product_sync === 'yes') {
            $product_sync = new Trendyol_WC_Product_Sync();
            $product_sync->sync_products();
        }
    }

    /**
     * Eklenti etkinleştirme
     */
    public function activate() {
        // İlk ayarları oluştur
        $default_settings = array(
            'api_username' => '',
            'api_password' => '',
            'supplier_id' => '',
            'auto_stock_sync' => 'no',
            'auto_order_sync' => 'no',
            'auto_product_sync' => 'no',
        );
        add_option('trendyol_wc_settings', $default_settings);
        
        // Zamanlı görev oluştur
        if (!wp_next_scheduled('trendyol_wc_hourly_event')) {
            wp_schedule_event(time(), 'hourly', 'trendyol_wc_hourly_event');
        }
        
        // Gerekli dizinleri oluştur
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/trendyol-wc-logs');
    }

    /**
     * Eklenti devre dışı bırakma
     */
    public function deactivate() {
        // Zamanlı görevi kaldır
        wp_clear_scheduled_hook('trendyol_wc_hourly_event');
    }
}

/**
 * Ana eklenti fonksiyonu
 */
function Trendyol_WC() {
    return Trendyol_WooCommerce::instance();
}

// Eklentiyi başlat
Trendyol_WC();
