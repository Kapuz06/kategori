<?php
/**
 * Trendyol WooCommerce - Orders Template
 *
 * Siparişler sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap trendyol-wc-orders">
    <h1><?php echo esc_html__('Trendyol Sipariş Yönetimi', 'trendyol-woocommerce'); ?></h1>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Sipariş Senkronizasyonu', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders&action=sync_orders')); ?>">
                <?php wp_nonce_field('trendyol_sync_orders'); ?>
                <div class="trendyol-wc-form-row">
                    <label for="start_date"><?php echo esc_html__('Başlangıç Tarihi:', 'trendyol-woocommerce'); ?></label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr(date('Y-m-d', strtotime('-7 days'))); ?>">
                </div>
                <div class="trendyol-wc-form-row">
                    <label for="end_date"><?php echo esc_html__('Bitiş Tarihi:', 'trendyol-woocommerce'); ?></label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                </div>
                <div class="trendyol-wc-form-actions">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Siparişleri Senkronize Et', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Trendyol Siparişleri', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <?php if (!empty($trendyol_orders)) : ?>
                <table class="wp-list-table widefat fixed striped trendyol-wc-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Sipariş', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol Sipariş No', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Tarih', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Durum', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Müşteri', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Tutar', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trendyol_orders as $item) : 
                            $order = $item['order'];
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $item['id'] . '&action=edit')); ?>">
                                        #<?php echo esc_html($item['id']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($item['trendyol_order_number']); ?></td>
                                <td><?php echo esc_html($order->get_date_created()->date_i18n('Y-m-d H:i')); ?></td>
                                <td>
                                    <span class="order-status status-<?php echo esc_attr(sanitize_html_class($order->get_status())); ?>">
                                        <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                    </span>
                                    <br>
                                    <small>
                                        <?php echo esc_html(trendyol_wc_get_readable_order_status($item['trendyol_status'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                                    if (empty($billing_name)) {
                                        echo '-';
                                    } else {
                                        echo esc_html($billing_name);
                                    }
                                    ?>
                                </td>
                                <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                                <td>
                                    <div class="trendyol-wc-actions">
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $item['id'] . '&action=edit')); ?>" class="button" title="<?php echo esc_attr__('Siparişi görüntüle', 'trendyol-woocommerce'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <button type="button" class="button trendyol-wc-show-update-status" title="<?php echo esc_attr__('Durumu güncelle', 'trendyol-woocommerce'); ?>" data-id="<?php echo esc_attr($item['id']); ?>">
                                            <span class="dashicons dashicons-update-alt"></span>
                                        </button>
                                        <button type="button" class="button trendyol-wc-show-update-tracking" title="<?php echo esc_attr__('Kargo bilgisini güncelle', 'trendyol-woocommerce'); ?>" data-id="<?php echo esc_attr($item['id']); ?>">
                                            <span class="dashicons dashicons-cart"></span>
                                        </button>
                                        <?php if ($order->get_status() !== 'cancelled' && $item['trendyol_status'] !== 'Cancelled') : ?>
                                            <button type="button" class="button trendyol-wc-show-cancel-order" title="<?php echo esc_attr__('Siparişi iptal et', 'trendyol-woocommerce'); ?>" data-id="<?php echo esc_attr($item['id']); ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('Henüz Trendyol siparişi bulunmuyor.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Durum güncelleme modal gösterimi
    $('.trendyol-wc-show-update-status').on('click', function() {
        var orderId = $(this).data('id');
        $('#trendyol-update-status-' + orderId).show();
    });

    // Kargo bilgisi güncelleme modal gösterimi
    $('.trendyol-wc-show-update-tracking').on('click', function() {
        var orderId = $(this).data('id');
        $('#trendyol-update-tracking-' + orderId).show();
    });

    // Sipariş iptal modal gösterimi
    $('.trendyol-wc-show-cancel-order').on('click', function() {
        var orderId = $(this).data('id');
        $('#trendyol-cancel-order-' + orderId).show();
    });

    // Modal kapatma
    $('.trendyol-wc-close-modal').on('click', function() {
        $(this).closest('.trendyol-wc-modal-form').hide();
    });
});
</script>

                                    <!-- Durum Güncelleme Formu (gizli) -->
                                    <div id="trendyol-update-status-<?php echo esc_attr($item['id']); ?>" class="trendyol-wc-modal-form" style="display:none;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders&action=update_order_status')); ?>">
                                            <?php wp_nonce_field('trendyol_update_order_status'); ?>
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($item['id']); ?>">
                                            <div class="trendyol-wc-form-row">
                                                <label for="trendyol_status_<?php echo esc_attr($item['id']); ?>">
                                                    <?php echo esc_html__('Yeni Durum:', 'trendyol-woocommerce'); ?>
                                                </label>
                                                <select name="trendyol_status" id="trendyol_status_<?php echo esc_attr($item['id']); ?>">
                                                    <option value="Created" <?php selected($item['trendyol_status'], 'Created'); ?>>
                                                        <?php echo esc_html__('Oluşturuldu', 'trendyol-woocommerce'); ?>
                                                    </option>
                                                    <option value="Picking" <?php selected($item['trendyol_status'], 'Picking'); ?>>
                                                        <?php echo esc_html__('Toplama', 'trendyol-woocommerce'); ?>
                                                    </option>
                                                    <option value="Invoiced" <?php selected($item['trendyol_status'], 'Invoiced'); ?>>
                                                        <?php echo esc_html__('Faturalı', 'trendyol-woocommerce'); ?>
                                                    </option>
                                                    <option value="Shipped" <?php selected($item['trendyol_status'], 'Shipped'); ?>>
                                                        <?php echo esc_html__('Kargoya Verildi', 'trendyol-woocommerce'); ?>
                                                    </option>
                                                    <option value="Delivered" <?php selected($item['trendyol_status'], 'Delivered'); ?>>
                                                        <?php echo esc_html__('Teslim Edildi', 'trendyol-woocommerce'); ?>
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="trendyol-wc-form-actions">
                                                <button type="submit" class="button button-primary">
                                                    <?php echo esc_html__('Güncelle', 'trendyol-woocommerce'); ?>
                                                </button>
                                                <button type="button" class="button trendyol-wc-close-modal">
                                                    <?php echo esc_html__('İptal', 'trendyol-woocommerce'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Kargo Bilgisi Güncelleme Formu (gizli) -->
                                    <div id="trendyol-update-tracking-<?php echo esc_attr($item['id']); ?>" class="trendyol-wc-modal-form" style="display:none;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders&action=update_tracking_number')); ?>">
                                            <?php wp_nonce_field('trendyol_update_tracking_number'); ?>
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($item['id']); ?>">
                                            <div class="trendyol-wc-form-row">
                                                <label for="tracking_number_<?php echo esc_attr($item['id']); ?>">
                                                    <?php echo esc_html__('Kargo Takip No:', 'trendyol-woocommerce'); ?>
                                                </label>
                                                <input type="text" name="tracking_number" id="tracking_number_<?php echo esc_attr($item['id']); ?>" value="<?php echo esc_attr($item['tracking_number']); ?>" required>
                                            </div>
                                            <div class="trendyol-wc-form-row">
                                                <label for="cargo_provider_id_<?php echo esc_attr($item['id']); ?>">
                                                    <?php echo esc_html__('Kargo Firması:', 'trendyol-woocommerce'); ?>
                                                </label>
                                                <select name="cargo_provider_id" id="cargo_provider_id_<?php echo esc_attr($item['id']); ?>" required>
                                                    <?php foreach ($cargo_companies as $id => $name) : ?>
                                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($item['cargo_provider'], $id); ?>>
                                                            <?php echo esc_html($name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="trendyol-wc-form-actions">
                                                <button type="submit" class="button button-primary">
                                                    <?php echo esc_html__('Güncelle', 'trendyol-woocommerce'); ?>
                                                </button>
                                                <button type="button" class="button trendyol-wc-close-modal">
                                                    <?php echo esc_html__('İptal', 'trendyol-woocommerce'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Sipariş İptal Formu (gizli) -->
                                    <div id="trendyol-cancel-order-<?php echo esc_attr($item['id']); ?>" class="trendyol-wc-modal-form" style="display:none;">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders&action=cancel_order')); ?>">
                                            <?php wp_nonce_field('trendyol_cancel_order'); ?>
                                            <input type="hidden" name="order_id" value="<?php echo esc_attr($item['id']); ?>">
                                            <input type="hidden" name="line_id" value="<?php echo esc_attr($order->get_meta('_trendyol_line_id', true)); ?>">
                                            <div class="trendyol-wc-form-row">
                                                <label for="cancel_reason_<?php echo esc_attr($item['id']); ?>">
                                                    <?php echo esc_html__('İptal Nedeni:', 'trendyol-woocommerce'); ?>
                                                </label>
                                                <select name="cancel_reason" id="cancel_reason_<?php echo esc_attr($item['id']); ?>" required>
                                                    <?php foreach ($cancel_reasons as $code => $label) : ?>
                                                        <option value="<?php echo esc_attr($code); ?>">
                                                            <?php echo esc_html($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="trendyol-wc-form-actions">
                                                <button type="submit" class="button button-primary">
                                                    <?php echo esc_html__('Siparişi İptal Et', 'trendyol-woocommerce'); ?>
                                                </button>
                                                <button type="button" class="button trendyol-wc-close-modal">
                                                    <?php echo esc_html__('Vazgeç', 'trendyol-woocommerce'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>