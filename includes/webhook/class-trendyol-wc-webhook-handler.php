<?php
/**
 * Trendyol WooCommerce Webhook İşleyici Sınıfı
 * 
 * Trendyol webhook isteklerini işleyen ana sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Webhook_Handler {

    /**
     * Sipariş webhook'ları için işleyici
     *
     * @var Trendyol_WC_Webhook_Orders
     */
    protected $orders_handler;

    /**
     * Ürün webhook'ları için işleyici
     *
     * @var Trendyol_WC_Webhook_Products
     */
    protected $products_handler;

    /**
     * Yapılandırıcı
     */
    public function __construct() {
        $this->orders_handler = new Trendyol_WC_Webhook_Orders();
        $this->products_handler = new Trendyol_WC_Webhook_Products();
    }
	/**
	 * Webhook hatasını logla
	 *
	 * @param string $message Hata mesajı
	 * @param array $data İlgili veriler
	 */
	protected function log_webhook_error($message, $data = array()) {
		$log_file = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/webhook-error-' . date('Y-m-d') . '.log';
		
		// Log klasörünü kontrol et
		$log_dir = dirname($log_file);
		if (!file_exists($log_dir)) {
			wp_mkdir_p($log_dir);
		}
		
		// Log kaydı
		$log_entry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
		error_log($log_entry, 3, $log_file);
	}
	
    /**
     * Webhook'u işle
     *
     * @param string $event_type Webhook olay türü
     * @param array $payload Webhook verileri
     * @return array İşleme sonucu
     */
    public function process_webhook($event_type, $payload) {
        // Webhook'un etkin olup olmadığını kontrol et
        $settings = get_option('trendyol_wc_settings', array());
        $webhook_enabled = isset($settings['webhook_enabled']) ? $settings['webhook_enabled'] : 'yes';
        
        if ($webhook_enabled !== 'yes') {
            return array(
                'success' => false,
                'message' => 'Webhooks are disabled in settings'
            );
        }
        
        // Olay türüne göre doğru işleyiciyi çağır
        switch ($event_type) {
            // Sipariş olayları
            case 'order-created':
            case 'order-updated':
            case 'order-status-changed':
            case 'order-package-created':
            case 'order-package-updated':
                return $this->orders_handler->process_order_webhook($event_type, $payload);
                
            // Ürün olayları
            case 'product-created':
            case 'product-updated':
            case 'product-stock-updated':
            case 'product-price-updated':
            case 'product-status-changed':
                return $this->products_handler->process_product_webhook($event_type, $payload);
                
            // Bilinmeyen olay türü
            default:
                $this->log_webhook_error('Unknown event type: ' . $event_type, $payload);
                return array(
                    'success' => false,
                    'message' => 'Unknown event type: ' . $event_type
                );
        }
    }

    
}