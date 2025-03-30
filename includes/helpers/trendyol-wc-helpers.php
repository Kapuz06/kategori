<?php
/**
 * Trendyol WooCommerce Yardımcı Fonksiyonlar
 * 
 * Eklenti genelinde kullanılan yardımcı fonksiyonlar
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

/**
 * Trendyol tarih formatını WordPress tarih formatına dönüştür
 *
 * @param string $trendyol_date Trendyol tarih formatı (örn. 2021-01-01T00:00:00.000)
 * @return string WordPress tarih formatı (Y-m-d H:i:s)
 */
function trendyol_wc_format_date($trendyol_date) {
    if (empty($trendyol_date)) {
        return '';
    }
    
    $date = new DateTime($trendyol_date);
    return $date->format('Y-m-d H:i:s');
}

/**
 * WordPress tarih formatını Trendyol tarih formatına dönüştür
 *
 * @param string $wp_date WordPress tarih formatı (Y-m-d H:i:s)
 * @return string Trendyol tarih formatı (2021-01-01T00:00:00.000)
 */
function trendyol_wc_format_date_for_trendyol($wp_date) {
    if (empty($wp_date)) {
        return '';
    }
    
    $date = new DateTime($wp_date);
    return $date->format('Y-m-d\TH:i:s.000');
}

/**
 * Trendyol para birimini WooCommerce para birimine dönüştür
 *
 * @param float $amount Trendyol para birimi
 * @return float WooCommerce para birimi
 */
function trendyol_wc_format_price($amount) {
    if (!is_numeric($amount)) {
        return 0;
    }
    
    return floatval($amount);
}

/**
 * WooCommerce ürün türünü Trendyol ürün türüne dönüştür
 *
 * @param string $product_type WooCommerce ürün türü
 * @return string Trendyol ürün türü
 */
function trendyol_wc_get_product_type_for_trendyol($product_type) {
    $types = array(
        'simple' => 'SINGLE',
        'variable' => 'VARIANT'
    );
    
    return isset($types[$product_type]) ? $types[$product_type] : 'SINGLE';
}

/**
 * WooCommerce stok durumunu Trendyol stok durumuna dönüştür
 *
 * @param string $stock_status WooCommerce stok durumu
 * @return bool Trendyol stok durumu (true: stokta, false: stokta değil)
 */
function trendyol_wc_get_stock_status_for_trendyol($stock_status) {
    return ($stock_status === 'instock');
}

/**
 * Trendyol sipariş durumunu okunabilir formata dönüştür
 *
 * @param string $status Trendyol sipariş durumu
 * @return string Okunabilir sipariş durumu
 */
function trendyol_wc_get_readable_order_status($status) {
    $statuses = array(
        'Created' => __('Oluşturuldu', 'trendyol-woocommerce'),
        'Picking' => __('Toplama', 'trendyol-woocommerce'),
        'Invoiced' => __('Faturalı', 'trendyol-woocommerce'),
        'Shipped' => __('Kargoya Verildi', 'trendyol-woocommerce'),
        'Cancelled' => __('İptal Edildi', 'trendyol-woocommerce'),
        'UnDelivered' => __('Teslim Edilemedi', 'trendyol-woocommerce'),
        'Delivered' => __('Teslim Edildi', 'trendyol-woocommerce'),
        'UnPacked' => __('Paketlenmedi', 'trendyol-woocommerce'),
        'Returned' => __('İade Edildi', 'trendyol-woocommerce')
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Trendyol kargo şirketlerini getir
 *
 * @return array Kargo şirketleri
 */
function trendyol_wc_get_cargo_companies() {
    return array(
        '1' => 'Aras Kargo',
        '2' => 'Sürat Kargo',
        '3' => 'MNG Kargo',
        '4' => 'PTT Kargo',
        '5' => 'UPS Kargo',
        '6' => 'Yurtiçi Kargo'
    );
}

/**
 * Trendyol iptal nedenlerini getir
 *
 * @return array İptal nedenleri
 */
function trendyol_wc_get_cancel_reasons() {
    return array(
        'OUT_OF_STOCK' => __('Stok yok', 'trendyol-woocommerce'),
        'CUSTOMER_REQUEST' => __('Müşteri talebi', 'trendyol-woocommerce'),
        'DUPLICATE_ORDER' => __('Tekrarlanan sipariş', 'trendyol-woocommerce'),
        'LATE_SHIPMENT' => __('Geç sevkiyat', 'trendyol-woocommerce'),
        'WRONG_PRICE' => __('Yanlış fiyat', 'trendyol-woocommerce'),
        'OTHER' => __('Diğer', 'trendyol-woocommerce')
    );
}

/**
 * Gizli girdi alanı oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param string $value Girdi değeri
 * @return string HTML girdi alanı
 */
function trendyol_wc_hidden_field($id, $name, $value = '') {
    return '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
}

/**
 * Metin girdi alanı oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param string $value Girdi değeri
 * @param string $class Girdi sınıfı
 * @param bool $required Zorunlu mu
 * @return string HTML girdi alanı
 */
function trendyol_wc_text_field($id, $name, $value = '', $class = 'regular-text', $required = false) {
    $required_attr = $required ? ' required="required"' : '';
    return '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . esc_attr($class) . '"' . $required_attr . ' />';
}

/**
 * Sayısal girdi alanı oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param string $value Girdi değeri
 * @param int $min Minimum değer
 * @param int $max Maksimum değer
 * @param int $step Adım değeri
 * @param string $class Girdi sınıfı
 * @param bool $required Zorunlu mu
 * @return string HTML girdi alanı
 */
function trendyol_wc_number_field($id, $name, $value = '', $min = 0, $max = 999999, $step = 1, $class = 'small-text', $required = false) {
    $required_attr = $required ? ' required="required"' : '';
    return '<input type="number" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" class="' . esc_attr($class) . '"' . $required_attr . ' />';
}

/**
 * Tarih girdi alanı oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param string $value Girdi değeri
 * @param string $class Girdi sınıfı
 * @param bool $required Zorunlu mu
 * @return string HTML girdi alanı
 */
function trendyol_wc_date_field($id, $name, $value = '', $class = 'date-picker', $required = false) {
    $required_attr = $required ? ' required="required"' : '';
    return '<input type="date" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . esc_attr($class) . '"' . $required_attr . ' />';
}

/**
 * Seçim kutusu oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param array $options Seçenekler
 * @param string $selected Seçili değer
 * @param string $class Girdi sınıfı
 * @param bool $required Zorunlu mu
 * @return string HTML seçim kutusu
 */
function trendyol_wc_select_field($id, $name, $options, $selected = '', $class = '', $required = false) {
    $required_attr = $required ? ' required="required"' : '';
    $html = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="' . esc_attr($class) . '"' . $required_attr . '>';
    
    foreach ($options as $value => $label) {
        $html .= '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * İşaret kutusu oluştur
 *
 * @param string $id Girdi ID'si
 * @param string $name Girdi adı
 * @param string $label Etiket
 * @param bool $checked İşaretli mi
 * @return string HTML işaret kutusu
 */
function trendyol_wc_checkbox_field($id, $name, $label, $checked = false) {
    return '<label><input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="yes"' . checked($checked, true, false) . ' /> ' . esc_html($label) . '</label>';
}

/**
 * Bilgi mesajı oluştur
 *
 * @param string $message Mesaj
 * @param string $type Mesaj türü (success, error, warning, info)
 * @return string HTML mesaj kutusu
 */
function trendyol_wc_admin_notice($message, $type = 'info') {
    return '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . $message . '</p></div>';
}