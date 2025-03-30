<?php
/**
 * Trendyol WooCommerce - Dashboard Template
 *
 * Gösterge paneli sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap trendyol-wc-dashboard">
    <h1><?php echo esc_html__('Trendyol WooCommerce Entegrasyonu', 'trendyol-woocommerce'); ?></h1>

    <?php if (!$connection_status['connected']) : ?>
        <div class="notice notice-error">
            <p>
                <?php echo esc_html__('Trendyol API bağlantısı kurulamadı:', 'trendyol-woocommerce'); ?> 
                <strong><?php echo esc_html($connection_status['message']); ?></strong>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-settings')); ?>" class="button">
                    <?php echo esc_html__('Ayarları Kontrol Et', 'trendyol-woocommerce'); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-success">
            <p>
                <?php echo esc_html__('Trendyol API bağlantısı başarılı!', 'trendyol-woocommerce'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-dashboard-grid">
        <!-- Ürün İstatistikleri -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Ürün İstatistikleri', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <div class="trendyol-wc-stat-item">
                    <span class="trendyol-wc-stat-label"><?php echo esc_html__('Toplam Senkronize Ürün:', 'trendyol-woocommerce'); ?></span>
                    <span class="trendyol-wc-stat-value"><?php echo esc_html($product_stats['total_synced']); ?></span>
                </div>
                <div class="trendyol-wc-stat-item">
                    <span class="trendyol-wc-stat-label"><?php echo esc_html__('Son 24 Saatte Senkronize Edilen:', 'trendyol-woocommerce'); ?></span>
                    <span class="trendyol-wc-stat-value"><?php echo esc_html($product_stats['last_24h_synced']); ?></span>
                </div>
            </div>
            <div class="trendyol-wc-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-products')); ?>" class="button">
                    <?php echo esc_html__('Ürünleri Yönet', 'trendyol-woocommerce'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-products&action=sync_products')); ?>" class="button button-primary">
                    <?php echo esc_html__('Senkronize Et', 'trendyol-woocommerce'); ?>
                </a>
            </div>
        </div>

        <!-- Sipariş İstatistikleri -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Sipariş İstatistikleri', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <div class="trendyol-wc-stat-item">
                    <span class="trendyol-wc-stat-label"><?php echo esc_html__('Toplam Trendyol Siparişi:', 'trendyol-woocommerce'); ?></span>
                    <span class="trendyol-wc-stat-value"><?php echo esc_html($order_stats['total_orders']); ?></span>
                </div>
                <div class="trendyol-wc-stat-item">
                    <span class="trendyol-wc-stat-label"><?php echo esc_html__('Son 24 Saatte Alınan:', 'trendyol-woocommerce'); ?></span>
                    <span class="trendyol-wc-stat-value"><?php echo esc_html($order_stats['last_24h_orders']); ?></span>
                </div>
            </div>
            <div class="trendyol-wc-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders')); ?>" class="button">
                    <?php echo esc_html__('Siparişleri Yönet', 'trendyol-woocommerce'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders&action=sync_orders')); ?>" class="button button-primary">
                    <?php echo esc_html__('Senkronize Et', 'trendyol-woocommerce'); ?>
                </a>
            </div>
        </div>

        <!-- Sipariş Durumları -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Sipariş Durumları', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <?php if (!empty($order_stats['order_statuses'])) : ?>
                    <div class="trendyol-wc-status-chart">
                        <?php foreach ($order_stats['order_statuses'] as $status) : ?>
                            <div class="trendyol-wc-chart-item">
                                <span class="trendyol-wc-status-label"><?php echo esc_html(trendyol_wc_get_readable_order_status($status->status)); ?></span>
                                <span class="trendyol-wc-status-count"><?php echo esc_html($status->count); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p><?php echo esc_html__('Henüz sipariş durumu verisi bulunmuyor.', 'trendyol-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Son Hatalar -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Son Hatalar', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <?php if (!empty($product_stats['failed_syncs'])) : ?>
                    <table class="trendyol-wc-errors-table">
                        <tbody>
                            <?php foreach ($product_stats['failed_syncs'] as $error) : ?>
                                <tr>
                                    <td><a href="<?php echo esc_url(get_edit_post_link($error->ID)); ?>"><?php echo esc_html($error->post_title); ?></a></td>
                                    <td><?php echo esc_html($error->error_message); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('Herhangi bir senkronizasyon hatası bulunmuyor.', 'trendyol-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
            <div class="trendyol-wc-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-logs')); ?>" class="button">
                    <?php echo esc_html__('Logları Görüntüle', 'trendyol-woocommerce'); ?>
                </a>
            </div>
        </div>
        <!-- Trendyol İşlem Durumları -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Trendyol İşlem Durumları', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <?php if (!empty($batch_requests)) : ?>
                    <table class="trendyol-wc-batch-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Batch ID', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('İşlem Tipi', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('Durum', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('Tarih', 'trendyol-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch_requests as $request) : 
                                $date_format = get_option('date_format') . ' ' . get_option('time_format');
                                $date = date_i18n($date_format, $request['date']);
                                
                                // Durum sınıfını belirle
                                $status_class = '';
                                switch ($request['status']) {
                                    case 'COMPLETED':
                                        $status_class = 'status-success';
                                        break;
                                    case 'PROCESSING':
                                        $status_class = 'status-processing';
                                        break;
                                    case 'FAILED':
                                        $status_class = 'status-failed';
                                        break;
                                    default:
                                        $status_class = 'status-unknown';
                                }
                            ?>
                            <tr>
                                <td class="batch-id"><?php echo esc_html($request['id']); ?></td>
                                <td><?php echo esc_html($request['type']); ?></td>
                                <td class="batch-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($request['status']); ?>
                                </td>
                                <td><?php echo esc_html($date); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('Henüz işlem kaydı bulunmuyor.', 'trendyol-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
            <div class="trendyol-wc-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-batch-requests')); ?>" class="button">
                    <?php echo esc_html__('Tüm İşlemleri Görüntüle', 'trendyol-woocommerce'); ?>
                </a>
            </div>
        </div>
        <!-- Hızlı Bağlantılar -->
        <div class="trendyol-wc-card">
            <div class="trendyol-wc-card-header">
                <h2><?php echo esc_html__('Hızlı Bağlantılar', 'trendyol-woocommerce'); ?></h2>
            </div>
            <div class="trendyol-wc-card-body">
                <div class="trendyol-wc-action-links">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-settings')); ?>" class="trendyol-wc-action-link">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('Ayarlar', 'trendyol-woocommerce'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-categories')); ?>" class="trendyol-wc-action-link">
                        <span class="dashicons dashicons-category"></span>
                        <?php echo esc_html__('Kategoriler', 'trendyol-woocommerce'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-brands')); ?>" class="trendyol-wc-action-link">
                        <span class="dashicons dashicons-tag"></span>
                        <?php echo esc_html__('Markalar', 'trendyol-woocommerce'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="trendyol-wc-action-link">
                        <span class="dashicons dashicons-products"></span>
                        <?php echo esc_html__('WooCommerce Ürünleri', 'trendyol-woocommerce'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>" class="trendyol-wc-action-link">
                        <span class="dashicons dashicons-cart"></span>
                        <?php echo esc_html__('WooCommerce Siparişleri', 'trendyol-woocommerce'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
