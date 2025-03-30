<?php
/**
 * Trendyol WooCommerce - Webhook Template
 *
 * Webhook ayarları sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap trendyol-wc-webhook">
    <h1><?php echo esc_html__('Trendyol Webhook Yönetimi', 'trendyol-woocommerce'); ?></h1>

    <?php if (isset($message)) : ?>
        <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Webhook Bilgisi', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <p><?php echo esc_html__('Webhooklar, Trendyol\'da gerçekleşen olaylara gerçek zamanlı olarak tepki vermenize olanak tanır. Aşağıdaki URL\'yi Trendyol Satıcı Panelinizdeki entegrasyon ayarlarına ekleyin.', 'trendyol-woocommerce'); ?></p>
            
            <?php
            $settings = get_option('trendyol_wc_settings', array());
            $webhook_endpoint = isset($settings['webhook_endpoint']) ? $settings['webhook_endpoint'] : 'trendyol-webhook';
            $webhook_url = home_url($webhook_endpoint);
            $webhook_enabled = isset($settings['webhook_enabled']) ? $settings['webhook_enabled'] : 'yes';
            $webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
            ?>
            
            <div class="trendyol-wc-webhook-info">
                <div class="trendyol-wc-webhook-status">
                    <h3><?php echo esc_html__('Webhook Durumu', 'trendyol-woocommerce'); ?></h3>
                    <?php if ($webhook_enabled === 'yes') : ?>
                        <span class="webhook-status-active"><span class="dashicons dashicons-yes"></span> <?php echo esc_html__('Etkin', 'trendyol-woocommerce'); ?></span>
                    <?php else : ?>
                        <span class="webhook-status-inactive"><span class="dashicons dashicons-no"></span> <?php echo esc_html__('Devre Dışı', 'trendyol-woocommerce'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="trendyol-wc-webhook-url">
                    <h3><?php echo esc_html__('Webhook URL', 'trendyol-woocommerce'); ?></h3>
                    <div class="webhook-url-container">
                        <code id="webhook-url"><?php echo esc_html($webhook_url); ?></code>
                        <button type="button" id="copy-webhook-url" class="button button-secondary" data-clipboard-target="#webhook-url">
                            <span class="dashicons dashicons-clipboard"></span> <?php echo esc_html__('Kopyala', 'trendyol-woocommerce'); ?>
                        </button>
                    </div>
                </div>
                
                <?php if (!empty($webhook_secret)) : ?>
                <div class="trendyol-wc-webhook-secret">
                    <h3><?php echo esc_html__('Webhook Güvenlik Anahtarı', 'trendyol-woocommerce'); ?></h3>
                    <div class="webhook-secret-container">
                        <code id="webhook-secret"><?php echo esc_html($webhook_secret); ?></code>
                        <button type="button" id="copy-webhook-secret" class="button button-secondary" data-clipboard-target="#webhook-secret">
                            <span class="dashicons dashicons-clipboard"></span> <?php echo esc_html__('Kopyala', 'trendyol-woocommerce'); ?>
                        </button>
                    </div>
                    <p class="description"><?php echo esc_html__('Bu anahtarı Trendyol Webhook ayarlarınıza ekleyerek webhook güvenliğini arttırabilirsiniz.', 'trendyol-woocommerce'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Webhook Ayarları', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <form method="post" action="options.php">
                <?php settings_fields('trendyol_wc_settings_group'); ?>
                
                <div class="trendyol-wc-form-section">
                    <h3><?php echo esc_html__('Temel Ayarlar', 'trendyol-woocommerce'); ?></h3>
                    
                    <div class="trendyol-wc-form-row">
                        <label for="trendyol_webhook_enabled">
                            <input type="checkbox" id="trendyol_webhook_enabled" name="trendyol_wc_settings[webhook_enabled]" value="yes" <?php checked($webhook_enabled, 'yes'); ?> />
                            <?php echo esc_html__('Webhook\'ları etkinleştir', 'trendyol-woocommerce'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Webhook\'ları devre dışı bırakırsanız, Trendyol\'dan gelen bildirimler işlenmeyecektir.', 'trendyol-woocommerce'); ?></p>
                    </div>
                    
                    <div class="trendyol-wc-form-row">
                        <label for="trendyol_webhook_endpoint"><?php echo esc_html__('Webhook Endpoint', 'trendyol-woocommerce'); ?></label>
                        <input type="text" id="trendyol_webhook_endpoint" name="trendyol_wc_settings[webhook_endpoint]" value="<?php echo esc_attr($webhook_endpoint); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Webhook URL yolunu belirtin. Varsayılan: trendyol-webhook', 'trendyol-woocommerce'); ?></p>
                    </div>
                    
                    <div class="trendyol-wc-form-row">
                        <label for="trendyol_webhook_secret"><?php echo esc_html__('Webhook Güvenlik Anahtarı', 'trendyol-woocommerce'); ?></label>
                        <input type="text" id="trendyol_webhook_secret" name="trendyol_wc_settings[webhook_secret]" value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text" />
                        <button type="button" id="trendyol-generate-secret" class="button button-secondary"><?php echo esc_html__('Rastgele Anahtar Oluştur', 'trendyol-woocommerce'); ?></button>
                        <p class="description"><?php echo esc_html__('Webhook güvenlik anahtarını belirtin. Bu anahtar, webhook isteklerinin güvenliğini sağlamak için kullanılır.', 'trendyol-woocommerce'); ?></p>
                    </div>
                </div>
                
                <div class="trendyol-wc-form-section">
                    <h3><?php echo esc_html__('Webhook Olayları', 'trendyol-woocommerce'); ?></h3>
                    
                    <div class="trendyol-wc-form-row">
                        <p><?php echo esc_html__('Aşağıdaki webhook olaylarını Trendyol Satıcı Panelinde yapılandırabilirsiniz:', 'trendyol-woocommerce'); ?></p>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Olay', 'trendyol-woocommerce'); ?></th>
                                    <th><?php echo esc_html__('Açıklama', 'trendyol-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>order-created</code></td>
                                    <td><?php echo esc_html__('Yeni bir sipariş oluşturulduğunda', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>order-updated</code></td>
                                    <td><?php echo esc_html__('Sipariş güncellendiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>order-status-changed</code></td>
                                    <td><?php echo esc_html__('Sipariş durumu değiştiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>order-package-created</code></td>
                                    <td><?php echo esc_html__('Sipariş paketi oluşturulduğunda', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>order-package-updated</code></td>
                                    <td><?php echo esc_html__('Sipariş paketi güncellendiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>product-created</code></td>
                                    <td><?php echo esc_html__('Yeni bir ürün oluşturulduğunda', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>product-updated</code></td>
                                    <td><?php echo esc_html__('Ürün güncellendiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>product-stock-updated</code></td>
                                    <td><?php echo esc_html__('Ürün stok durumu değiştiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>product-price-updated</code></td>
                                    <td><?php echo esc_html__('Ürün fiyatı değiştiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                                <tr>
                                    <td><code>product-status-changed</code></td>
                                    <td><?php echo esc_html__('Ürün durumu değiştiğinde', 'trendyol-woocommerce'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="trendyol-wc-form-actions">
                    <?php submit_button(); ?>
                </div>
            </form>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Son Webhook İstekleri', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <?php
            // Log dosyalarını kontrol et
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
            
            if (!empty($webhook_logs)) : 
                $log_file = $webhook_logs[0]; // En son log dosyası
                $log_content = file_exists($log_dir . $log_file) ? file_get_contents($log_dir . $log_file) : '';
                $log_entries = preg_split('/\n\n/', $log_content, -1, PREG_SPLIT_NO_EMPTY);
                $log_entries = array_slice($log_entries, 0, 5); // Son 5 girdi
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Zaman', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Olay', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Durum', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_entries as $entry) : 
                            preg_match('/^([\d-]+ [\d:]+) - (.*)$/m', $entry, $matches);
                            
                            if (count($matches) >= 3) {
                                $timestamp = $matches[1];
                                $data = json_decode($matches[2], true);
                                
                                $event = isset($data['event']) ? $data['event'] : '-';
                                $success = isset($data['result']['success']) ? $data['result']['success'] : false;
                            } else {
                                continue;
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($timestamp); ?></td>
                                <td><?php echo esc_html($event); ?></td>
                                <td>
                                    <?php if ($success) : ?>
                                        <span class="webhook-status-success"><span class="dashicons dashicons-yes"></span> <?php echo esc_html__('Başarılı', 'trendyol-woocommerce'); ?></span>
                                    <?php else : ?>
                                        <span class="webhook-status-error"><span class="dashicons dashicons-no"></span> <?php echo esc_html__('Hata', 'trendyol-woocommerce'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-logs')); ?>" class="button">
                        <?php echo esc_html__('Tüm Webhook Loglarını Görüntüle', 'trendyol-woocommerce'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p><?php echo esc_html__('Henüz webhook isteği alınmamış veya log kaydı bulunmuyor.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // URL ve güvenlik anahtarı kopyalama
    $('#copy-webhook-url, #copy-webhook-secret').on('click', function() {
        var copyTarget = $(this).data('clipboard-target');
        var tempElem = document.createElement('textarea');
        tempElem.value = $(copyTarget).text();
        document.body.appendChild(tempElem);
        
        tempElem.select();
        document.execCommand('copy');
        document.body.removeChild(tempElem);
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('<?php echo esc_js(__('Kopyalandı!', 'trendyol-woocommerce')); ?>');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Rastgele anahtar oluşturma
    $('#trendyol-generate-secret').on('click', function() {
        var randomString = "";
        var characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        for (var i = 0; i < 32; i++) {
            randomString += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        $('#trendyol_webhook_secret').val(randomString);
    });
});
</script>