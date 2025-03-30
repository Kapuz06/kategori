<?php
/**
 * Trendyol WooCommerce - Product Meta Box Template
 *
 * Ürün meta kutusu şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="trendyol-product-meta-box">
    <div class="trendyol-meta-field">
        <p>
            <label for="trendyol_product_id"><?php echo esc_html__('Trendyol Ürün ID:', 'trendyol-woocommerce'); ?></label>
            <input type="text" id="trendyol_product_id" name="trendyol_product_id" value="<?php echo esc_attr($trendyol_product_id); ?>" readonly class="widefat" />
        </p>
    </div>

    <div class="trendyol-meta-field">
        <p>
            <label for="trendyol_barcode"><?php echo esc_html__('Trendyol Barkod:', 'trendyol-woocommerce'); ?></label>
            <input type="text" id="trendyol_barcode" name="trendyol_barcode" value="<?php echo esc_attr($trendyol_barcode); ?>" class="widefat" />
        </p>
    </div>

    <div class="trendyol-meta-field">
        <p>
            <label for="trendyol_brand"><?php echo esc_html__('Trendyol Marka:', 'trendyol-woocommerce'); ?></label>
            <input type="text" id="trendyol_brand" name="trendyol_brand" value="<?php echo esc_attr($trendyol_brand); ?>" class="widefat" />
        </p>
    </div>

    <div class="trendyol-meta-field">
        <p>
            <label for="trendyol_category_id"><?php echo esc_html__('Trendyol Kategori ID:', 'trendyol-woocommerce'); ?></label>
            <input type="text" id="trendyol_category_id" name="trendyol_category_id" value="<?php echo esc_attr($trendyol_category_id); ?>" class="widefat" />
        </p>
    </div>

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
        <?php if (!empty($trendyol_product_id)) : ?>
            <p class="trendyol-status">
                <span class="trendyol-status-active"><span class="dashicons dashicons-yes"></span> <?php echo esc_html__('Trendyol\'da Mevcut', 'trendyol-woocommerce'); ?></span>
            </p>
        <?php else : ?>
            <p class="trendyol-status">
                <span class="trendyol-status-inactive"><span class="dashicons dashicons-no"></span> <?php echo esc_html__('Trendyol\'da Mevcut Değil', 'trendyol-woocommerce'); ?></span>
            </p>
        <?php endif; ?>

        <p>
            <label for="trendyol_sync_to_trendyol">
                <input type="checkbox" id="trendyol_sync_to_trendyol" name="trendyol_sync_to_trendyol" value="1" />
                <?php echo esc_html__('Kaydederken Trendyol\'a Gönder', 'trendyol-woocommerce'); ?>
            </label>
        </p>
    </div>
</div>