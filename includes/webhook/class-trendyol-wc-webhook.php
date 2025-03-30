<?php
/**
 * Trendyol WooCommerce Webhook Sınıfı
 * 
 * Trendyol webhook isteklerini alır ve işler
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Webhook {

    /**
     * Webhook işleyici sınıfı
     *
     * @var Trendyol_WC_Webhook_Handler
     */
    protected $handler;

    /**
     * Webhook endpoint'i
     *
     * @var string
     */
    protected $webhook_endpoint;

    /**
     * Webhook anahtarı
     *
     * @var string
     */
    protected $webhook_secret;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->init_settings();
        $this->init_hooks();
        
        // Webhook işleyicilerini başlat
        require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/webhook/class-trendyol-wc-webhook-handler.php';
        require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/webhook/class-trendyol-wc-webhook-orders.php';
        require_once TRENDYOL_WC_PLUGIN_DIR . 'includes/webhook/class-trendyol-wc-webhook-products.php';
        
        $this->handler = new Trendyol_WC_Webhook_Handler();
    }

    /**
     * Ayarları başlat
     */
    private function init_settings() {
        $settings = get_option('trendyol_wc_settings', array());
        
        $this->webhook_endpoint = isset($settings['webhook_endpoint']) ? sanitize_title($settings['webhook_endpoint']) : 'trendyol-webhook';
        $this->webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Webhook endpoint'ini ekle
        add_action('init', array($this, 'add_webhook_endpoint'));
        
        // Webhook isteklerini işle
        add_action('parse_request', array($this, 'process_webhook_requests'));
        
        // Yönetici menüsünü ekle
        add_action('admin_init', array($this, 'register_webhook_settings'));
    }

    /**
     * Webhook endpoint'ini ekle
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            $this->webhook_endpoint . '/?$',
            'index.php?trendyol-webhook=1',
            'top'
        );
        
        add_rewrite_tag('%trendyol-webhook%', '([^/]*)');
        
        // Rewrite kuralları kaydedilmemişse kaydet
        $rules_option = get_option('rewrite_rules');
        $webhook_regex = $this->webhook_endpoint . '/?$';
        
        if (!isset($rules_option[$webhook_regex])) {
            flush_rewrite_rules();
        }
    }

    /**
     * Webhook isteklerini işle
     *
     * @param WP $wp WordPress nesnesini al
     */
    public function process_webhook_requests($wp) {
        // Webhook isteklerini kontrol et
        if (isset($wp->query_vars['trendyol-webhook'])) {
            // Debug modu kontrolü
            $settings = get_option('trendyol_wc_settings', array());
            $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : 'no';
            
            // İstek verilerini al
            $webhook_data = file_get_contents('php://input');
            $event_type = isset($_SERVER['HTTP_X_TRENDYOL_EVENT']) ? sanitize_text_field($_SERVER['HTTP_X_TRENDYOL_EVENT']) : '';
            $signature = isset($_SERVER['HTTP_X_TRENDYOL_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_X_TRENDYOL_SIGNATURE']) : '';
            
            // İmza kontrolü
            if ($this->verify_webhook_signature($webhook_data, $signature)) {
                // İstek verilerini JSON olarak ayrıştır
                $webhook_payload = json_decode($webhook_data, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Webhook işleme
                    $result = $this->handler->process_webhook($event_type, $webhook_payload);
                    
                    // Loglama
                    if ($debug_mode === 'yes') {
                        $this->log_webhook(array(
                            'event' => $event_type,
                            'payload' => $webhook_payload,
                            'result' => $result
                        ));
                    }
                    
                    // Başarılı yanıt gönder
                    status_header(200);
                    echo json_encode(array('status' => 'success'));
                } else {
                    // Geçersiz JSON
                    status_header(400);
                    echo json_encode(array('status' => 'error', 'message' => 'Invalid JSON'));
                    
                    if ($debug_mode === 'yes') {
                        $this->log_webhook(array(
                            'event' => $event_type,
                            'error' => 'Invalid JSON',
                            'data' => $webhook_data
                        ));
                    }
                }
            } else {
                // Geçersiz imza
                status_header(401);
                echo json_encode(array('status' => 'error', 'message' => 'Invalid signature'));
                
                if ($debug_mode === 'yes') {
                    $this->log_webhook(array(
                        'event' => $event_type,
                        'error' => 'Invalid signature',
                        'provided_signature' => $signature
                    ));
                }
            }
            
            exit;
        }
    }

    /**
     * Webhook imzasını doğrula
     *
     * @param string $data Webhook verileri
     * @param string $signature İmza
     * @return bool Doğrulama sonucu
     */
    private function verify_webhook_signature($data, $signature) {
        // Webhook güvenlik anahtarı boşsa doğrulamadan geç (test modu)
        if (empty($this->webhook_secret)) {
            return true;
        }
        
        // İmza yoksa geçersiz
        if (empty($signature)) {
            return false;
        }
        
        // İmzayı doğrula (HMAC-SHA256)
        $calculated_signature = hash_hmac('sha256', $data, $this->webhook_secret);
        
        return hash_equals($calculated_signature, $signature);
    }

    /**
     * Webhook olayını logla
     *
     * @param array $data Log verileri
     */
    private function log_webhook($data) {
        $log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/webhook-' . date('Y-m-d') . '.log';
        
        // Log klasörünü kontrol et
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Log kaydı
        $log_entry = date('Y-m-d H:i:s') . ' - ' . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        error_log($log_entry, 3, $log_file);
    }

    /**
     * Webhook ayarlarını kaydet
     */
    public function register_webhook_settings() {
        // Webhook ayarları bölümü
        add_settings_section(
            'trendyol_wc_webhook_section',
            __('Webhook Ayarları', 'trendyol-woocommerce'),
            array($this, 'webhook_section_callback'),
            'trendyol_wc_settings'
        );
        
        // Webhook endpoint ayarı
        add_settings_field(
            'trendyol_webhook_endpoint',
            __('Webhook Endpoint', 'trendyol-woocommerce'),
            array($this, 'webhook_endpoint_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
        
        // Webhook güvenlik anahtarı ayarı
        add_settings_field(
            'trendyol_webhook_secret',
            __('Webhook Güvenlik Anahtarı', 'trendyol-woocommerce'),
            array($this, 'webhook_secret_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
        
        // Webhook durumu ayarı
        add_settings_field(
            'trendyol_webhook_status',
            __('Webhook Durumu', 'trendyol-woocommerce'),
            array($this, 'webhook_status_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
    }

    /**
     * Webhook bölümü açıklaması
     */
    public function webhook_section_callback() {
        echo '<p>' . __('Trendyol\'dan gerçek zamanlı bildirimler almak için webhook ayarlarını yapılandırın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Webhook endpoint ayarı
     */
    public function webhook_endpoint_callback() {
        $settings = get_option('trendyol_wc_settings', array());
        $endpoint = isset($settings['webhook_endpoint']) ? $settings['webhook_endpoint'] : 'trendyol-webhook';
        
        echo '<input type="text" id="trendyol_webhook_endpoint" name="trendyol_wc_settings[webhook_endpoint]" value="' . esc_attr($endpoint) . '" class="regular-text" />';
        echo '<p class="description">' . __('Webhook URL yolunu belirtin. Varsayılan: trendyol-webhook', 'trendyol-woocommerce') . '</p>';
        
        // Tam webhook URL'sini göster
        $webhook_url = home_url($endpoint);
        echo '<p><strong>' . __('Tam Webhook URL:', 'trendyol-woocommerce') . '</strong> <code>' . esc_html($webhook_url) . '</code></p>';
        echo '<p>' . __('Bu URL\'yi Trendyol entegrasyon ayarlarında webhook URL\'si olarak kullanın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Webhook güvenlik anahtarı ayarı
     */
    public function webhook_secret_callback() {
        $settings = get_option('trendyol_wc_settings', array());
        $secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        echo '<input type="text" id="trendyol_webhook_secret" name="trendyol_wc_settings[webhook_secret]" value="' . esc_attr($secret) . '" class="regular-text" />';
        echo '<button type="button" id="trendyol-generate-secret" class="button button-secondary">' . __('Rastgele Anahtar Oluştur', 'trendyol-woocommerce') . '</button>';
        echo '<p class="description">' . __('Webhook güvenlik anahtarını belirtin. Bu anahtar, webhook isteklerinin güvenliğini sağlamak için kullanılır.', 'trendyol-woocommerce') . '</p>';
        
        // Rastgele anahtar oluşturmak için JS
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#trendyol-generate-secret").on("click", function() {
                    var randomString = "";
                    var characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
                    for (var i = 0; i < 32; i++) {
                        randomString += characters.charAt(Math.floor(Math.random() * characters.length));
                    }
                    $("#trendyol_webhook_secret").val(randomString);
                });
            });
        </script>';
    }

    /**
	 * Webhook durumu ayarı
	 */
	public function webhook_status_callback() {
		$settings = get_option('trendyol_wc_settings', array());
		$enabled = isset($settings['webhook_enabled']) ? $settings['webhook_enabled'] : 'yes';
		
		echo '<label><input type="checkbox" name="trendyol_wc_settings[webhook_enabled]" value="yes" ' . checked($enabled, 'yes', false) . ' /> ' . __('Webhook\'ları etkinleştir', 'trendyol-woocommerce') . '</label>';
		echo '<p class="description">' . __('Webhook\'ları etkinleştir veya devre dışı bırak. Devre dışı bırakıldığında, Trendyol\'dan gelen webhook istekleri işlenmeyecektir.', 'trendyol-woocommerce') . '</p>';
	}
}