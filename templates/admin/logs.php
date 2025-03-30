<?php
/**
 * Trendyol WooCommerce - Logs Template
 *
 * Loglar sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Silme işlemi sonucunu kontrol et
$delete_result = null;
if (isset($_POST['trendyol_delete_logs'])) {
    $admin = new Trendyol_WC_Admin();
    $delete_result = $admin->delete_log_files();
}
?>
<div class="wrap trendyol-wc-logs">
    <h1><?php echo esc_html__('Trendyol Entegrasyon Logları', 'trendyol-woocommerce'); ?></h1>

    <?php if ($delete_result !== null) : ?>
        <div class="notice notice-<?php echo $delete_result['success'] ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($delete_result['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Log Dosyaları', 'trendyol-woocommerce'); ?></h2>
            <?php if (!empty($log_files)) : ?>
                <div class="trendyol-wc-card-actions">
                    <form method="post" action="" class="delete-all-logs-form">
                        <?php wp_nonce_field('trendyol_delete_logs'); ?>
                        <input type="hidden" name="trendyol_delete_logs" value="1">
                        <input type="hidden" name="delete_all_logs" value="1">
                        <button type="submit" class="button delete-logs-button" onclick="return confirm('<?php echo esc_js(__('Tüm log dosyalarını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.', 'trendyol-woocommerce')); ?>');">
                            <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Tüm Logları Temizle', 'trendyol-woocommerce'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="trendyol-wc-card-body">
            <?php if (!empty($log_files)) : ?>
                <div class="trendyol-wc-log-files">
                    <ul>
                        <?php foreach ($log_files as $file) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-logs&log=' . urlencode($file))); ?>" class="<?php echo $current_log == $file ? 'current' : ''; ?>">
                                    <?php echo esc_html($file); ?>
                                </a>
                                <?php if ($current_log == $file) : ?>
                                    <span class="dashicons dashicons-yes"></span>
                                <?php endif; ?>
                                
                                <form method="post" action="" class="delete-log-form">
                                    <?php wp_nonce_field('trendyol_delete_logs'); ?>
                                    <input type="hidden" name="trendyol_delete_logs" value="1">
                                    <input type="hidden" name="log_file" value="<?php echo esc_attr($file); ?>">
                                    <button type="submit" class="delete-log-button" title="<?php echo esc_attr__('Bu log dosyasını sil', 'trendyol-woocommerce'); ?>" onclick="return confirm('<?php echo esc_js(sprintf(__('"%s" log dosyasını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.', 'trendyol-woocommerce'), $file)); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else : ?>
                <p><?php echo esc_html__('Henüz log dosyası bulunmuyor.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($current_log) && !empty($log_content)) : ?>
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html($current_log); ?></h2>
                <div class="trendyol-wc-card-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-logs')); ?>" class="button">
                        <?php echo esc_html__('Tüm Loglar', 'trendyol-woocommerce'); ?>
                    </a>
                    <button type="button" id="trendyol-wc-copy-log" class="button" data-clipboard-target="#trendyol-wc-log-content">
                        <?php echo esc_html__('Kopyala', 'trendyol-woocommerce'); ?>
                    </button>
                    <form method="post" action="" class="delete-current-log-form" style="display:inline;">
                        <?php wp_nonce_field('trendyol_delete_logs'); ?>
                        <input type="hidden" name="trendyol_delete_logs" value="1">
                        <input type="hidden" name="log_file" value="<?php echo esc_attr($current_log); ?>">
                        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(sprintf(__('"%s" log dosyasını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.', 'trendyol-woocommerce'), $current_log)); ?>');">
                            <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Bu Logu Sil', 'trendyol-woocommerce'); ?>
                        </button>
                    </form>
                </div>
            </div>
            <div class="trendyol-wc-card-body">
                <div class="trendyol-wc-log-content">
                    <pre id="trendyol-wc-log-content"><?php echo esc_html($log_content); ?></pre>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.trendyol-wc-log-files ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.trendyol-wc-log-files li {
    margin: 0;
    padding: 8px 0;
    border-bottom: 1px solid #f5f5f5;
    display: flex;
    align-items: center;
}

.trendyol-wc-log-files li:last-child {
    border-bottom: none;
}

.trendyol-wc-log-files a.current {
    font-weight: 700;
    color: #000;
}

.trendyol-wc-log-files .dashicons {
    margin: 0 5px;
    color: #0073aa;
}

.delete-log-form {
    margin-left: auto;
}

.delete-log-button {
    background: none;
    border: none;
    color: #a00;
    cursor: pointer;
    padding: 0;
}

.delete-log-button:hover {
    color: #dc3232;
}

.delete-log-button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: inherit;
}

.trendyol-wc-card-actions {
    display: flex;
    gap: 5px;
}

.delete-all-logs-form {
    margin-left: 10px;
}

.delete-logs-button {
    color: #a00;
}

.delete-logs-button:hover {
    color: #dc3232;
}

.delete-current-log-form {
    margin-left: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Log içeriğini kopyalama
    $('#trendyol-wc-copy-log').on('click', function() {
        var tempElem = document.createElement('textarea');
        tempElem.value = $('#trendyol-wc-log-content').text();
        document.body.appendChild(tempElem);
        
        tempElem.select();
        document.execCommand('copy');
        document.body.removeChild(tempElem);
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('Kopyalandı!');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});
</script>