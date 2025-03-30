<?php
/**
 * Trendyol WooCommerce - Order Meta Box Template
 *
 * Sipariş meta kutusu şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="trendyol-order-meta-box">
    <?php if (!empty($trendyol_order_number)) : ?>
        <div class="trendyol-meta-field">
            <p>
                <label><?php echo esc_html__('Trendyol Sipariş No:', 'trendyol-woocommerce'); ?></label>
                <strong><?php echo esc_html($trendyol_order_number); ?></strong>
            </p>
        </div>

        <?php if (!empty($trendyol_package_number)) : ?>
            <div class="trendyol-meta-field">
                <p>
                    <label><?php echo esc_html__('Trendyol Paket No:', 'trendyol-woocommerce'); ?></label>
                    <strong><?php echo esc_html($trendyol_package_number); ?></strong>
                </p>
            </div>
        <?php endif; ?>

        <div class="trendyol-meta-field">
            <p>
                <label><?php echo esc_html__('Trendyol Sipariş Durumu:', 'trendyol-woocommerce'); ?></label>
                <span class="trendyol-order-status">
                    <?php echo esc_html(trendyol_wc_get_readable_order_status($trendyol_order_status)); ?>
                </span>
            </p>
        </div>

        <?php if (!empty($trendyol_tracking_number)) : ?>
            <div class="trendyol-meta-field">
                <p>
                    <label><?php echo esc_html__('Kargo Takip No:', 'trendyol-woocommerce'); ?></label>
                    <strong><?php echo esc_html($trendyol_tracking_number); ?></strong>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($trendyol_cargo_provider)) : ?>
            <div class="trendyol-meta-field">
                <p>
                    <label><?php echo esc_html__('Kargo Şirketi:', 'trendyol-woocommerce'); ?></label>
                    <strong><?php echo esc_html($trendyol_cargo_provider); ?></strong>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($trendyol_last_sync)) : ?>
            <div class="trendyol-meta-field">
                <p>
                    <label><?php echo esc_html__('Son Senkronizasyon:', 'trendyol-woocommerce'); ?></label>
                    <span>
                        <?php 
                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($trendyol_last_sync)));
                        echo ' (' . esc_html(human_time_diff(strtotime($trendyol_last_sync), current_time('timestamp'))) . ' ' . esc_html__('önce', 'trendyol-woocommerce') . ')';
                        ?>
                    </span>
                </p>
            </div>
        <?php endif; ?>

        <div class="trendyol-meta-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders')); ?>" class="button">
                <?php echo esc_html__('Trendyol Siparişleri Yönet', 'trendyol-woocommerce'); ?>
            </a>
        </div>
    <?php else : ?>
        <p><?php echo esc_html__('Bu sipariş Trendyol\'dan alınmamıştır.', 'trendyol-woocommerce'); ?></p>
    <?php endif; ?>
</div>