<?php
/**
 * Trendyol API Sınıfı
 * 
 * Trendyol API istekleri için ana sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_API {
    /**
     * API Kullanıcı adı
     *
     * @var string
     */
    protected $api_username;

    /**
     * API Şifresi
     *
     * @var string
     */
    protected $api_password;

    /**
     * Satıcı ID
     *
     * @var string
     */
    protected $supplier_id;

    /**
     * API URL
     *
     * @var string
     */
    protected $api_url;

    /**
     * API Supplier URL
     *
     * @var string
     */
    protected $api_suppliers_url;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $settings = get_option('trendyol_wc_settings', array());
        
        $this->api_username = isset($settings['api_username']) ? $settings['api_username'] : '';
        $this->api_password = isset($settings['api_password']) ? $settings['api_password'] : '';
        $this->supplier_id = isset($settings['supplier_id']) ? $settings['supplier_id'] : '';
        
        $this->api_url = TRENDYOL_API_URL;
        $this->api_suppliers_url = TRENDYOL_API_SUPPLIERS_URL;
    }
    
    /**
     * API'ye GET isteği gönder
     *
     * @param string $endpoint API Endpoint
     * @param array $query_params Sorgu parametreleri
     * @return array|WP_Error API yanıtı veya hata
     */
    public function get($endpoint, $query_params = array()) {
        return $this->request('GET', $endpoint, $query_params);
    }

    /**
     * API'ye POST isteği gönder
     *
     * @param string $endpoint API Endpoint
     * @param array $data Gönderilecek veri
     * @return array|WP_Error API yanıtı veya hata
     */
    public function post($endpoint, $data = array()) {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * API'ye PUT isteği gönder
     *
     * @param string $endpoint API Endpoint
     * @param array $data Gönderilecek veri
     * @return array|WP_Error API yanıtı veya hata
     */
    public function put($endpoint, $data = array()) {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * API'ye DELETE isteği gönder
     *
     * @param string $endpoint API Endpoint
     * @param array $data Gönderilecek veri
     * @return array|WP_Error API yanıtı veya hata
     */
    public function delete($endpoint, $data = array()) {
        return $this->request('DELETE', $endpoint, $data);
    }

    /**
     * API isteği oluştur ve gönder
     *
     * @param string $method HTTP metodu (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Gönderilecek veri veya sorgu parametreleri
     * @return array|WP_Error API yanıtı veya hata
     */
    protected function request($method, $endpoint, $data = array()) {
        // İşlemin başlangıcında detaylı loglama ekleyin:
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/api-debug-' . date('Y-m-d') . '.log';
        error_log("\n\n" . date('Y-m-d H:i:s') . " - API İSTEĞİ BAŞLIYOR\n", 3, $log_file);
        error_log("Method: $method, Endpoint: $endpoint\n", 3, $log_file);
        error_log("Data: " . print_r($data, true) . "\n", 3, $log_file);
        
        // API kimlik bilgileri kontrolü
        if (empty($this->api_username) || empty($this->api_password)) {
            error_log("API Hata: Kimlik bilgileri eksik\n", 3, $log_file);
            return new WP_Error('api_credentials_missing', __('Trendyol API bilgileri eksik. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce'));
        }
        
        // Supplier ID kontrolü ekleyin
        if (empty($this->supplier_id)) {
            error_log("API Hata: Supplier ID eksik\n", 3, $log_file);
            return new WP_Error('supplier_id_missing', __('Trendyol Supplier ID eksik. Lütfen ayarları kontrol edin.', 'trendyol-woocommerce'));
        }
    
        // Tam URL oluştur
        $url = $this->get_api_url($endpoint);
        
        // URL'yi loga kaydet
        error_log("API URL: $url\n", 3, $log_file);
        
        // GET isteği için sorgu parametreleri
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
            error_log("GET URL (with params): $url\n", 3, $log_file);
        }
        
        // HTTP argümanlarını hazırla
        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_username . ':' . $this->api_password),
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'Trendyol WooCommerce Plugin/' . TRENDYOL_WC_VERSION
            ),
        );
        
        // Log request headers
        error_log("API headers: " . print_r($args['headers'], true) . "\n", 3, $log_file);
        
        // POST, PUT, DELETE istekleri için body ekle
        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
            error_log("Request Body (JSON): " . $args['body'] . "\n", 3, $log_file);
        }
    
        // İsteği gönder
        error_log("İstek gönderiliyor...\n", 3, $log_file);
        $response = wp_remote_request($url, $args);
        
        // Hata kontrolü
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("WP Error: $error_message\n", 3, $log_file);
            $this->log_error('API Request Error: ' . $error_message, array(
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data
            ));
            return $response;
        }
        
        // HTTP yanıt kodunu ve body'yi logla
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        error_log("API yanıt kodu: $response_code\n", 3, $log_file);
        error_log("API yanıt headers: " . print_r($headers, true) . "\n", 3, $log_file);
        error_log("API yanıt body: " . $body . "\n", 3, $log_file);
    
        // HTTP durum kodu kontrolü
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = sprintf(__('API Hatası (HTTP %s): %s', 'trendyol-woocommerce'), $response_code, $body);
            
            $this->log_error('API Response Error', array(
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response_code,
                'body' => $body
            ));
            
            error_log("API Hata: $error_message\n", 3, $log_file);
            return new WP_Error('api_error', $error_message, array('status' => $response_code));
        }
    
        // Yanıtı JSON olarak ayrıştır
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = __('API yanıtı JSON olarak ayrıştırılamadı.', 'trendyol-woocommerce');
            $this->log_error('API JSON Parse Error', array(
                'method' => $method,
                'endpoint' => $endpoint,
                'body' => $body
            ));
            
            error_log("JSON parse error: " . json_last_error_msg() . "\n", 3, $log_file);
            return new WP_Error('api_json_error', $error_message);
        }
        
        // Batch tracking için hook (bu satırı ekleyin)
        do_action('trendyol_wc_api_response', $data, $endpoint, $method);
        
        error_log("API isteği başarılı tamamlandı\n", 3, $log_file);
        return $data;
    }

    /**
     * API URL'i oluştur
     *
     * @param string $endpoint API endpoint
     * @return string Tam API URL'i
     */
    protected function get_api_url($endpoint) {
        // Entegrasyon endpoint'leri için
        if (strpos($endpoint, 'integration/') === 0) {
            $base_url = rtrim($this->api_url, '/');
            return $base_url . '/' . $endpoint;
        }
        
        
        
        // Normal API endpoint için URL oluştur
        $base_url = rtrim($this->api_url, '/');
        $clean_endpoint = ltrim($endpoint, '/');
        return $base_url . '/' . $clean_endpoint;
    }

    /**
     * Hata logla
     *
     * @param string $message Hata mesajı
     * @param array $data İlgili veri
     */
    protected function log_error($message, $data = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($data) . "\n";
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/api-' . date('Y-m-d') . '.log';
        
        // Log dosyası kontrolleri
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        error_log($log_entry, 3, $log_file);
    }
}
