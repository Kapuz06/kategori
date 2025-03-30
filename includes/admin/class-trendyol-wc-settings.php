<?php
/**
 * Trendyol WooCommerce Ayarlar Sınıfı
 * 
 * Admin ayarlar sayfası ve işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Settings {

    /**
     * Ayarlar sayfasını oluştur
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Ayarları kaydet
     */
    public function register_settings() {
        // Önce ayar grubunu kaydet
        register_setting(
            'trendyol_wc_settings_group', // Grup adı
            'trendyol_wc_settings',       // Seçenek adı
            array($this, 'sanitize_settings') // Temizleme fonksiyonu
        );
        
        // Sonra ayar bölümlerini ekle
        add_settings_section(
            'trendyol_wc_general_section',
            __('API Ayarları', 'trendyol-woocommerce'),
            array($this, 'general_section_callback'),
            'trendyol_wc_settings'
        );
        
        // Sonra ayar alanlarını ekle
        add_settings_field(
            'trendyol_api_username',
            __('API Kullanıcı Adı', 'trendyol-woocommerce'),
            array($this, 'api_username_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_general_section'
        );
		
		// API Şifre alanı
		add_settings_field(
			'trendyol_api_password',
			__('API Şifre', 'trendyol-woocommerce'),
			array($this, 'api_password_callback'),
			'trendyol_wc_settings',
			'trendyol_wc_general_section'
		);
		
		// Satıcı ID alanı
		add_settings_field(
			'trendyol_supplier_id',
			__('Satıcı ID', 'trendyol-woocommerce'),
			array($this, 'supplier_id_callback'),
			'trendyol_wc_settings',
			'trendyol_wc_general_section'
		);
        
        // Otomatik Senkronizasyon Ayarları
        add_settings_section(
            'trendyol_wc_sync_section',
            __('Senkronizasyon Ayarları', 'trendyol-woocommerce'),
            array($this, 'sync_section_callback'),
            'trendyol_wc_settings'
        );
        
        add_settings_field(
            'trendyol_auto_sync',
            __('Otomatik Senkronizasyon', 'trendyol-woocommerce'),
            array($this, 'auto_sync_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_sync_section'
        );
        
        add_settings_field(
            'trendyol_sync_schedule',
            __('Senkronizasyon Sıklığı', 'trendyol-woocommerce'),
            array($this, 'sync_schedule_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_sync_section'
        );
        
        // Ürün Ayarları
        add_settings_section(
            'trendyol_wc_product_section',
            __('Ürün Ayarları', 'trendyol-woocommerce'),
            array($this, 'product_section_callback'),
            'trendyol_wc_settings'
        );
        
        add_settings_field(
            'trendyol_default_category',
            __('Varsayılan Kategori', 'trendyol-woocommerce'),
            array($this, 'default_category_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        add_settings_field(
            'trendyol_default_brand',
            __('Varsayılan Marka', 'trendyol-woocommerce'),
            
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        // KDV Oranı alanı
        add_settings_field(
            'trendyol_vat_rate',
            __('KDV Oranı', 'trendyol-woocommerce'),
            array($this, 'vat_rate_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        add_settings_field(
            'trendyol_cargo_settings',
            __('Kargo ve Teslimat Ayarları', 'trendyol-woocommerce'),
            array($this, 'cargo_settings_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        // Sevkiyat Adresi ID'si
        add_settings_field(
            'trendyol_shipment_address_id',
            __('Sevkiyat Adresi ID', 'trendyol-woocommerce'),
            array($this, 'shipment_address_id_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        // İade Adresi ID'si
        add_settings_field(
            'trendyol_returning_address_id',
            __('İade Adresi ID', 'trendyol-woocommerce'),
            array($this, 'returning_address_id_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_product_section'
        );
        
        // Gelişmiş Ayarlar
        add_settings_section(
            'trendyol_wc_advanced_section',
            __('Gelişmiş Ayarlar', 'trendyol-woocommerce'),
            array($this, 'advanced_section_callback'),
            'trendyol_wc_settings'
        );
        
        add_settings_field(
            'trendyol_log_settings',
            __('Log Ayarları', 'trendyol-woocommerce'),
            array($this, 'log_settings_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_advanced_section'
        );
        
        add_settings_field(
            'trendyol_debug_mode',
            __('Hata Ayıklama Modu', 'trendyol-woocommerce'),
            array($this, 'debug_mode_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_advanced_section'
        );
        
        // Webhook Ayarları Bölümü
        add_settings_section(
            'trendyol_wc_webhook_section',
            __('Webhook Ayarları', 'trendyol-woocommerce'),
            array($this, 'webhook_section_callback'),
            'trendyol_wc_settings'
        );
        
        add_settings_field(
            'trendyol_webhook_endpoint',
            __('Webhook Endpoint', 'trendyol-woocommerce'),
            array($this, 'webhook_endpoint_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
        
        add_settings_field(
            'trendyol_webhook_secret',
            __('Webhook Güvenlik Anahtarı', 'trendyol-woocommerce'),
            array($this, 'webhook_secret_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
        
        add_settings_field(
            'trendyol_webhook_status',
            __('Webhook Durumu', 'trendyol-woocommerce'),
            array($this, 'webhook_status_callback'),
            'trendyol_wc_settings',
            'trendyol_wc_webhook_section'
        );
    }

    /**
     * Genel ayarlar bölümü açıklaması
     */
    public function general_section_callback() {
        echo '<p>' . __('Trendyol API bağlantısı için gerekli temel ayarları yapılandırın.', 'trendyol-woocommerce') . '</p>';
    }
	/**
	 * API Kullanıcı Adı alanı
	 */
	public function api_username_callback() {
		$settings = get_option('trendyol_wc_settings', array());
		$api_username = isset($settings['api_username']) ? $settings['api_username'] : '';
		
		echo '<input type="text" id="trendyol_api_username" name="trendyol_wc_settings[api_username]" value="' . esc_attr($api_username) . '" class="regular-text" />';
		echo '<p class="description">' . __('Trendyol API kullanıcı adınızı girin.', 'trendyol-woocommerce') . '</p>';
	}

	/**
	 * API Şifre alanı
	 */
	public function api_password_callback() {
		$settings = get_option('trendyol_wc_settings', array());
		$api_password = isset($settings['api_password']) ? $settings['api_password'] : '';
		
		echo '<input type="password" id="trendyol_api_password" name="trendyol_wc_settings[api_password]" value="' . esc_attr($api_password) . '" class="regular-text" />';
		echo '<p class="description">' . __('Trendyol API şifrenizi girin.', 'trendyol-woocommerce') . '</p>';
	}

	/**
	 * Satıcı ID alanı
	 */
	public function supplier_id_callback() {
		$settings = get_option('trendyol_wc_settings', array());
		$supplier_id = isset($settings['supplier_id']) ? $settings['supplier_id'] : '';
		
		echo '<input type="text" id="trendyol_supplier_id" name="trendyol_wc_settings[supplier_id]" value="' . esc_attr($supplier_id) . '" class="regular-text" />';
		echo '<p class="description">' . __('Trendyol Satıcı ID\'nizi girin. Bu bilgiyi Trendyol Satıcı Panelinden alabilirsiniz.', 'trendyol-woocommerce') . '</p>';
	}
    /**
     * API kimlik bilgileri ayarı
     */
    public function api_credentials_callback() {
        $settings = get_option('trendyol_wc_settings');
        $api_username = isset($settings['api_username']) ? $settings['api_username'] : '';
        $api_password = isset($settings['api_password']) ? $settings['api_password'] : '';
        
        echo '<label for="trendyol_api_username">' . __('API Kullanıcı Adı:', 'trendyol-woocommerce') . '</label> ';
        echo '<input type="text" id="trendyol_api_username" name="trendyol_wc_settings[api_username]" value="' . esc_attr($api_username) . '" class="regular-text" />';
        echo '<br/><br/>';
        echo '<label for="trendyol_api_password">' . __('API Şifre:', 'trendyol-woocommerce') . '</label> ';
        echo '<input type="password" id="trendyol_api_password" name="trendyol_wc_settings[api_password]" value="' . esc_attr($api_password) . '" class="regular-text" />';
        echo '<p class="description">' . __('Trendyol entegrasyon API kullanıcı adı ve şifrenizi girin. Bu bilgileri Trendyol Satıcı Panelinden alabilirsiniz.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Senkronizasyon bölümü açıklaması
     */
    public function sync_section_callback() {
        echo '<p>' . __('Trendyol ve WooCommerce arasındaki senkronizasyon ayarlarını yapılandırın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Otomatik senkronizasyon ayarları
     */
    public function auto_sync_callback() {
        $settings = get_option('trendyol_wc_settings');
        $auto_stock_sync = isset($settings['auto_stock_sync']) ? $settings['auto_stock_sync'] : 'no';
        $auto_order_sync = isset($settings['auto_order_sync']) ? $settings['auto_order_sync'] : 'no';
        $auto_product_sync = isset($settings['auto_product_sync']) ? $settings['auto_product_sync'] : 'no';
        
        echo '<label><input type="checkbox" name="trendyol_wc_settings[auto_stock_sync]" value="yes" ' . checked($auto_stock_sync, 'yes', false) . ' /> ' . __('Otomatik stok senkronizasyonu', 'trendyol-woocommerce') . '</label><br/>';
        echo '<label><input type="checkbox" name="trendyol_wc_settings[auto_order_sync]" value="yes" ' . checked($auto_order_sync, 'yes', false) . ' /> ' . __('Otomatik sipariş senkronizasyonu', 'trendyol-woocommerce') . '</label><br/>';
        echo '<label><input type="checkbox" name="trendyol_wc_settings[auto_product_sync]" value="yes" ' . checked($auto_product_sync, 'yes', false) . ' /> ' . __('Otomatik ürün senkronizasyonu', 'trendyol-woocommerce') . '</label><br/>';
        echo '<p class="description">' . __('WooCommerce ürünlerinde değişiklik olduğunda Trendyol\'a otomatik güncelleme gönderilsin mi?', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Senkronizasyon zaman ayarı
     */
    public function sync_schedule_callback() {
        $settings = get_option('trendyol_wc_settings');
        $sync_schedule = isset($settings['sync_schedule']) ? $settings['sync_schedule'] : 'hourly';
        
        $schedules = array(
            'hourly' => __('Saatlik', 'trendyol-woocommerce'),
            'twicedaily' => __('Günde İki Kez', 'trendyol-woocommerce'),
            'daily' => __('Günlük', 'trendyol-woocommerce')
        );
        
        echo '<select name="trendyol_wc_settings[sync_schedule]">';
        foreach ($schedules as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($sync_schedule, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Otomatik senkronizasyonun hangi sıklıkta yapılacağını seçin.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Ürün bölümü açıklaması
     */
    public function product_section_callback() {
        echo '<p>' . __('Trendyol\'a gönderilecek ürünler için varsayılan ayarları yapılandırın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * KDV Oranı ayarı
     */
    public function vat_rate_callback() {
        $settings = get_option('trendyol_wc_settings');
        $vat_rate = isset($settings['vat_rate']) ? $settings['vat_rate'] : '18';
        
        // KDV oranları
        $vat_rates = array(
            '0' => '%0',
            '1' => '%1',
            '8' => '%8',
            '10' => '%10',
            '18' => '%18',
            '20' => '%20'
        );
        
        echo '<select name="trendyol_wc_settings[vat_rate]" id="trendyol_vat_rate">';
        foreach ($vat_rates as $rate => $label) {
            echo '<option value="' . esc_attr($rate) . '" ' . selected($vat_rate, $rate, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Trendyol\'a gönderilen ürünler için varsayılan KDV oranını seçin.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Varsayılan kategori ayarı
     */
    public function default_category_callback() {
        $settings = get_option('trendyol_wc_settings');
        $default_category_id = isset($settings['default_category_id']) ? $settings['default_category_id'] : '';
        $default_category_name = isset($settings['default_category_name']) ? $settings['default_category_name'] : '';
        
        // Gizli input alanları 
        echo '<input type="hidden" id="trendyol_default_category_id" name="trendyol_wc_settings[default_category_id]" value="' . esc_attr($default_category_id) . '" />';
        echo '<input type="hidden" id="trendyol_default_category_name" name="trendyol_wc_settings[default_category_name]" value="' . esc_attr($default_category_name) . '" />';
        
        // Arama kutusu
        echo '<div class="trendyol-category-search-container">';
        echo '<input type="text" id="trendyol_category_search" class="regular-text" placeholder="' . esc_attr__('Kategori ara...', 'trendyol-woocommerce') . '" value="' . esc_attr($default_category_name) . '" />';
        echo '<div id="trendyol_category_search_results" class="trendyol-search-results"></div>';
        echo '</div>';
        
        echo '<p class="description">' . __('Trendyol\'a gönderilen ürünler için varsayılan kategoriyi seçin. Kategori belirtilmeyen ürünler için kullanılacaktır.', 'trendyol-woocommerce') . '</p>';
        
        // JavaScript kodu
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var searchTimeout;
            var minChars = 3;
            
            // Kategori arama
            $('#trendyol_category_search').on('keyup', function() {
                var searchTerm = $(this).val();
                
                // Temizleme
                clearTimeout(searchTimeout);
                
                // Eğer arama terimi minimum karakter sayısından kısaysa, sonuçları temizle
                if (searchTerm.length < minChars) {
                    $('#trendyol_category_search_results').empty().hide();
                    return;
                }
                
                // Arama işlemini geciktir
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'trendyol_search_categories',
                            nonce: '<?php echo wp_create_nonce('trendyol-settings-search'); ?>',
                            search: searchTerm
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '<ul>';
                                $.each(response.data, function(index, category) {
                                    html += '<li data-id="' + category.id + '" data-name="' + category.name + '">' + 
                                           category.name + ' <small>(ID: ' + category.id + ')</small></li>';
                                });
                                html += '</ul>';
                                
                                $('#trendyol_category_search_results').html(html).show();
                            } else {
                                $('#trendyol_category_search_results').html('<p class="no-results"><?php echo esc_js(__('Kategori bulunamadı', 'trendyol-woocommerce')); ?></p>').show();
                            }
                        },
                        error: function() {
                            $('#trendyol_category_search_results').html('<p class="error"><?php echo esc_js(__('Hata oluştu', 'trendyol-woocommerce')); ?></p>').show();
                        }
                    });
                }, 500); // 500ms gecikme
            });
            
            // Kategori seçme
            $(document).on('click', '#trendyol_category_search_results li', function() {
                var categoryId = $(this).data('id');
                var categoryName = $(this).data('name');
                
                $('#trendyol_default_category_id').val(categoryId);
                $('#trendyol_default_category_name').val(categoryName);
                $('#trendyol_category_search').val(categoryName);
                
                $('#trendyol_category_search_results').empty().hide();
            });
            
            // Dışarı tıklama ile sonuçları kapat
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.trendyol-category-search-container').length) {
                    $('#trendyol_category_search_results').empty().hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Varsayılan marka ayarı - Arama kutulu versiyonu
     */
    public function default_brand_callback() {
        $settings = get_option('trendyol_wc_settings', array());
        $default_brand_id = isset($settings['default_brand_id']) ? $settings['default_brand_id'] : '';
        $default_brand = isset($settings['default_brand']) ? $settings['default_brand'] : '';
        
        // Gizli input alanları
        echo '<input type="hidden" id="trendyol_default_brand_id" name="trendyol_wc_settings[default_brand_id]" value="' . esc_attr($default_brand_id) . '" />';
        echo '<input type="hidden" id="trendyol_default_brand" name="trendyol_wc_settings[default_brand]" value="' . esc_attr($default_brand) . '" />';
        
        // Arama kutusu
        echo '<div class="trendyol-brand-search-container">';
        echo '<input type="text" id="trendyol_brand_search" class="regular-text" placeholder="' . esc_attr__('Marka ara...', 'trendyol-woocommerce') . '" value="' . esc_attr($default_brand) . '" />';
        echo '<div id="trendyol_brand_search_results" class="trendyol-search-results"></div>';
        echo '</div>';
        
        echo '<p class="description">' . __('Trendyol\'a gönderilen ürünler için varsayılan markayı seçin. Marka belirtilmeyen ürünler için kullanılacaktır.', 'trendyol-woocommerce') . '</p>';
        
        // JavaScript kodu
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var searchTimeout;
            var minChars = 3;
            
            // Marka arama
            $('#trendyol_brand_search').on('keyup', function() {
                var searchTerm = $(this).val();
                
                // Temizleme
                clearTimeout(searchTimeout);
                
                // Sonuçları temizle
                $('#trendyol_brand_search_results').empty().hide();
                
                // Eğer arama terimi minimum karakter sayısından kısaysa, sonuçları temizle
                if (searchTerm.length < minChars) {
                    return;
                }
                
                // Arama işlemini geciktir
                searchTimeout = setTimeout(function() {
                    // Yükleniyor mesajı göster
                    $('#trendyol_brand_search_results').html('<p class="loading"><?php echo esc_js(__('Aranıyor...', 'trendyol-woocommerce')); ?></p>').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'trendyol_search_brands',
                            nonce: '<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>',
                            search: searchTerm
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '<ul>';
                                $.each(response.data, function(index, brand) {
                                    html += '<li data-id="' + brand.id + '" data-name="' + brand.name + '">' + 
                                           brand.name + ' <small>(ID: ' + brand.id + ')</small></li>';
                                });
                                html += '</ul>';
                                
                                $('#trendyol_brand_search_results').html(html).show();
                            } else {
                                $('#trendyol_brand_search_results').html('<p class="no-results"><?php echo esc_js(__('Marka bulunamadı', 'trendyol-woocommerce')); ?></p>').show();
                            }
                        },
                        error: function() {
                            $('#trendyol_brand_search_results').html('<p class="error"><?php echo esc_js(__('Hata oluştu', 'trendyol-woocommerce')); ?></p>').show();
                        }
                    });
                }, 500); // 500ms gecikme
            });
            
            // Marka seçme
            $(document).on('click', '#trendyol_brand_search_results li', function() {
                var brandId = $(this).data('id');
                var brandName = $(this).data('name');
                
                $('#trendyol_default_brand_id').val(brandId);
                $('#trendyol_default_brand').val(brandName);
                $('#trendyol_brand_search').val(brandName);
                
                $('#trendyol_brand_search_results').empty().hide();
            });
            
            // Dışarı tıklama ile sonuçları kapat
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.trendyol-brand-search-container').length) {
                    $('#trendyol_brand_search_results').empty().hide();
                }
            });
        });
        </script>
        
        <?php
    }

    /**
     * Kargo ve Teslimat ayarları
     */
    public function cargo_settings_callback() {
        $settings = get_option('trendyol_wc_settings');
        $cargo_company_id = isset($settings['cargo_company_id']) ? $settings['cargo_company_id'] : '';
        $delivery_duration = isset($settings['delivery_duration']) ? $settings['delivery_duration'] : '';
        $fast_delivery_type = isset($settings['fast_delivery_type']) ? $settings['fast_delivery_type'] : '';
        
        // Trendyol kargo şirketleri
        $cargo_companies = array(
            '42' => 'DHL Kargo',
            '38' => 'Sendeo Kargo',
            '30' => 'Borusan Kargo',
            '14' => 'Cainiao Kargo',
            '10' => 'MNG Kargo',
            '19' => 'PTT Kargo',
            '9' => 'Sürat Kargo',
            '17' => 'TY Express Kargo',
            '6' => 'Horoz Kargo',
            '20' => 'Ceva Kargo',
            '4' => 'Yurtiçi Kargo',
            '7' => 'Aras Kargo'
            // Diğer kargo şirketleri eklenebilir
        );
        
        // Kargo firması seçimi
        echo '<div class="trendyol-wc-form-row">';
        echo '<label for="trendyol_cargo_company_id">' . __('Kargo Firması:', 'trendyol-woocommerce') . '</label>';
        echo '<select name="trendyol_wc_settings[cargo_company_id]" id="trendyol_cargo_company_id">';
        foreach ($cargo_companies as $id => $name) {
            echo '<option value="' . esc_attr($id) . '" ' . selected($cargo_company_id, $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Trendyol siparişleri için varsayılan kargo firmasını seçin.', 'trendyol-woocommerce') . '</p>';
        echo '</div>';
        
        // Sevkiyat süresi
        echo '<div class="trendyol-wc-form-row">';
        echo '<label for="trendyol_delivery_duration">' . __('Sevkiyat Süresi (Gün):', 'trendyol-woocommerce') . '</label>';
        echo '<input type="number" id="trendyol_delivery_duration" name="trendyol_wc_settings[delivery_duration]" value="' . esc_attr($delivery_duration) . '" min="1" max="30" class="small-text" />';
        echo '<p class="description">' . __('Ürünlerin sevkiyat süresini gün olarak belirtin. Hızlı teslimat için 1 gün kullanın.', 'trendyol-woocommerce') . '</p>';
        echo '</div>';
        
        // Hızlı teslimat seçenekleri
        echo '<div class="trendyol-wc-form-row">';
        echo '<label for="trendyol_fast_delivery_type">' . __('Hızlı Teslimat Tipi:', 'trendyol-woocommerce') . '</label>';
        echo '<select name="trendyol_wc_settings[fast_delivery_type]" id="trendyol_fast_delivery_type">';
        echo '<option value="">' . __('Yok', 'trendyol-woocommerce') . '</option>';
        echo '<option value="SAME_DAY_SHIPPING" ' . selected($fast_delivery_type, 'SAME_DAY_SHIPPING', false) . '>' . __('Aynı Gün Kargo', 'trendyol-woocommerce') . '</option>';
        echo '<option value="FAST_DELIVERY" ' . selected($fast_delivery_type, 'FAST_DELIVERY', false) . '>' . __('Hızlı Teslimat', 'trendyol-woocommerce') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Hızlı teslimat seçeneği. Sevkiyat süresi 1 gün olduğunda kullanılabilir.', 'trendyol-woocommerce') . '</p>';
        echo '</div>';
        
        // Sevkiyat süresi 1 olduğunda hızlı teslimat seçeneklerini aktifleştirme JavaScript
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateFastDeliveryVisibility() {
                    var deliveryDuration = parseInt($("#trendyol_delivery_duration").val()) || 0;
                    if (deliveryDuration === 1) {
                        $("#trendyol_fast_delivery_type").prop("disabled", false);
                    } else {
                        $("#trendyol_fast_delivery_type").val("").prop("disabled", true);
                    }
                }
                
                // Sayfa yüklendiğinde kontrol et
                updateFastDeliveryVisibility();
                
                // Sevkiyat süresi değiştiğinde kontrol et
                $("#trendyol_delivery_duration").on("change", updateFastDeliveryVisibility);
            });
        </script>';
    }

    /**
     * Sevkiyat Adresi ID ayarı
     */
    public function shipment_address_id_callback() {
        $settings = get_option('trendyol_wc_settings');
        $shipment_address_id = isset($settings['shipment_address_id']) ? $settings['shipment_address_id'] : '';
        
        echo '<input type="number" id="trendyol_shipment_address_id" name="trendyol_wc_settings[shipment_address_id]" value="' . esc_attr($shipment_address_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Trendyol sistemindeki sevkiyat depo adresi ID\'sini girin. Bu ID\'yi Trendyol Satıcı Panelinden öğrenebilirsiniz.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * İade Adresi ID ayarı
     */
    public function returning_address_id_callback() {
        $settings = get_option('trendyol_wc_settings');
        $returning_address_id = isset($settings['returning_address_id']) ? $settings['returning_address_id'] : '';
        
        echo '<input type="number" id="trendyol_returning_address_id" name="trendyol_wc_settings[returning_address_id]" value="' . esc_attr($returning_address_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Trendyol sistemindeki iade depo adresi ID\'sini girin. Bu ID\'yi Trendyol Satıcı Panelinden öğrenebilirsiniz.', 'trendyol-woocommerce') . '</p>';
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
        $settings = get_option('trendyol_wc_settings');
        $webhook_endpoint = isset($settings['webhook_endpoint']) ? $settings['webhook_endpoint'] : 'trendyol-webhook';
        
        echo '<input type="text" id="trendyol_webhook_endpoint" name="trendyol_wc_settings[webhook_endpoint]" value="' . esc_attr($webhook_endpoint) . '" class="regular-text" />';
        echo '<p class="description">' . __('Webhook URL yolunu belirtin. Varsayılan: trendyol-webhook', 'trendyol-woocommerce') . '</p>';
        
        $webhook_url = home_url($webhook_endpoint);
        echo '<p><strong>' . __('Tam Webhook URL:', 'trendyol-woocommerce') . '</strong> <code>' . esc_html($webhook_url) . '</code></p>';
        echo '<p>' . __('Bu URL\'yi Trendyol entegrasyon ayarlarında webhook URL\'si olarak kullanın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Webhook güvenlik anahtarı ayarı
     */
    public function webhook_secret_callback() {
        $settings = get_option('trendyol_wc_settings');
        $webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        echo '<input type="text" id="trendyol_webhook_secret" name="trendyol_wc_settings[webhook_secret]" value="' . esc_attr($webhook_secret) . '" class="regular-text" />';
        echo '<button type="button" id="trendyol-generate-secret" class="button button-secondary">' . __('Rastgele Anahtar Oluştur', 'trendyol-woocommerce') . '</button>';
        echo '<p class="description">' . __('Webhook güvenlik anahtarını belirtin. Bu anahtar, webhook isteklerinin güvenliğini sağlamak için kullanılır.', 'trendyol-woocommerce') . '</p>';
        
        // Rastgele anahtar oluşturmak için JavaScript
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
        $settings = get_option('trendyol_wc_settings');
        $webhook_enabled = isset($settings['webhook_enabled']) ? $settings['webhook_enabled'] : 'yes';
        
        echo '<label><input type="checkbox" name="trendyol_wc_settings[webhook_enabled]" value="yes" ' . checked($webhook_enabled, 'yes', false) . ' /> ' . __('Webhook\'ları etkinleştir', 'trendyol-woocommerce') . '</label>';
        echo '<p class="description">' . __('Webhook\'ları etkinleştir veya devre dışı bırak. Devre dışı bırakıldığında, Trendyol\'dan gelen webhook istekleri işlenmeyecektir.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Gelişmiş ayarlar bölümü açıklaması
     */
    public function advanced_section_callback() {
        echo '<p>' . __('Gelişmiş entegrasyon ayarlarını yapılandırın.', 'trendyol-woocommerce') . '</p>';
    }

    /**
     * Log ayarları
     */
    public function log_settings_callback() {
        $settings = get_option('trendyol_wc_settings');
        $enable_logging = isset($settings['enable_logging']) ? $settings['enable_logging'] : 'yes';
        $log_retention = isset($settings['log_retention']) ? $settings['log_retention'] : '30';
        
        echo '<label><input type="checkbox" name="trendyol_wc_settings[enable_logging]" value="yes" ' . checked($enable_logging, 'yes', false) . ' /> ' . __('Loglama etkinleştir', 'trendyol-woocommerce') . '</label><br/><br/>';
        
        echo '<label for="trendyol_log_retention">' . __('Log saklama süresi (gün):', 'trendyol-woocommerce') . '</label> ';
        echo '<input type="number" id="trendyol_log_retention" name="trendyol_wc_settings[log_retention]" value="' . esc_attr($log_retention) . '" min="1" max="90" step="1" style="width: 70px;" />';
        
        echo '<p class="description">' . __('Entegrasyon işlemlerinin loglarını ne kadar süre saklamak istediğinizi belirtin.', 'trendyol-woocommerce') . '</p>';
        
        // Log dosyalarını görüntüleme bağlantısı
        $log_dir = WP_CONTENT_DIR . '/uploads/trendyol-wc-logs/';
        if (file_exists($log_dir)) {
            echo '<br/><a href="' . esc_url(admin_url('admin.php?page=trendyol-wc-logs')) . '" class="button">' . __('Logları Görüntüle', 'trendyol-woocommerce') . '</a>';
        }
    }

    /**
     * Hata ayıklama modu ayarı
     */
    public function debug_mode_callback() {
        $settings = get_option('trendyol_wc_settings');
        $debug_mode = isset($settings['debug_mode']) ? $settings['debug_mode'] : 'no';
        
        echo '<label><input type="checkbox" name="trendyol_wc_settings[debug_mode]" value="yes" ' . checked($debug_mode, 'yes', false) . ' /> ' . __('Hata ayıklama modunu etkinleştir', 'trendyol-woocommerce') . '</label>';
        echo '<p class="description">' . __('Hata ayıklama modu etkinleştirildiğinde, API istekleri ve yanıtları log dosyalarına kaydedilir. Sadece sorun giderme amaçlı kullanın.', 'trendyol-woocommerce') . '</p>';
    }
    
    /**
     * Ayarları doğrula ve temizle
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Mevcut ayarları al
        $existing_settings = get_option('trendyol_wc_settings', array());
        
        // API kimlik bilgileri
        $sanitized_input['api_username'] = sanitize_text_field($input['api_username']);
        $sanitized_input['api_password'] = sanitize_text_field($input['api_password']);
        
        // Satıcı ID
        $sanitized_input['supplier_id'] = sanitize_text_field($input['supplier_id']);
        
        // Senkronizasyon ayarları
        $sanitized_input['auto_stock_sync'] = isset($input['auto_stock_sync']) ? 'yes' : 'no';
        $sanitized_input['auto_order_sync'] = isset($input['auto_order_sync']) ? 'yes' : 'no';
        $sanitized_input['auto_product_sync'] = isset($input['auto_product_sync']) ? 'yes' : 'no';
        $sanitized_input['sync_schedule'] = isset($input['sync_schedule']) ? sanitize_text_field($input['sync_schedule']) : 'hourly';
        
        // Ürün ayarları
        $sanitized_input['default_category_id'] = isset($input['default_category_id']) ? absint($input['default_category_id']) : '';
        $sanitized_input['default_category_name'] = isset($input['default_category_name']) ? sanitize_text_field($input['default_category_name']) : '';
        $sanitized_input['default_brand_id'] = isset($input['default_brand_id']) ? absint($input['default_brand_id']) : '';
        $sanitized_input['default_brand'] = isset($input['default_brand']) ? sanitize_text_field($input['default_brand']) : '';
        $sanitized_input['cargo_company_id'] = isset($input['cargo_company_id']) ? absint($input['cargo_company_id']) : '';
        
        // KDV oranı
        $sanitized_input['vat_rate'] = isset($input['vat_rate']) ? sanitize_text_field($input['vat_rate']) : '18';
        
        // Teslimat ayarları
        $sanitized_input['delivery_duration'] = isset($input['delivery_duration']) ? absint($input['delivery_duration']) : '';
        $sanitized_input['fast_delivery_type'] = isset($input['fast_delivery_type']) ? sanitize_text_field($input['fast_delivery_type']) : '';
        
        // Adres ID'leri
        $sanitized_input['shipment_address_id'] = isset($input['shipment_address_id']) ? absint($input['shipment_address_id']) : '';
        $sanitized_input['returning_address_id'] = isset($input['returning_address_id']) ? absint($input['returning_address_id']) : '';
        
        // Webhook ayarları
        $sanitized_input['webhook_enabled'] = isset($input['webhook_enabled']) ? 'yes' : 'no';
        $sanitized_input['webhook_endpoint'] = isset($input['webhook_endpoint']) ? sanitize_title($input['webhook_endpoint']) : 'trendyol-webhook';
        $sanitized_input['webhook_secret'] = isset($input['webhook_secret']) ? sanitize_text_field($input['webhook_secret']) : '';
        
        // Gelişmiş ayarlar
        $sanitized_input['enable_logging'] = isset($input['enable_logging']) ? 'yes' : 'no';
        $sanitized_input['log_retention'] = isset($input['log_retention']) ? absint($input['log_retention']) : 30;
        $sanitized_input['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';
        
        // Diğer ayarları koru
        foreach ($existing_settings as $key => $value) {
            if (!isset($sanitized_input[$key])) {
                $sanitized_input[$key] = $value;
            }
        }
        
        // API bağlantısı ayarları değiştiyse, zamanlı görevleri yeniden planlayın
        if (
            $sanitized_input['api_username'] !== (isset($existing_settings['api_username']) ? $existing_settings['api_username'] : '') ||
            $sanitized_input['api_password'] !== (isset($existing_settings['api_password']) ? $existing_settings['api_password'] : '') ||
            $sanitized_input['supplier_id'] !== (isset($existing_settings['supplier_id']) ? $existing_settings['supplier_id'] : '') ||
            $sanitized_input['sync_schedule'] !== (isset($existing_settings['sync_schedule']) ? $existing_settings['sync_schedule'] : 'hourly')
        ) {
            // Mevcut zamanlı görevi temizle
            wp_clear_scheduled_hook('trendyol_wc_hourly_event');
            
            // Yeni zamanlı görev oluştur
            if (!empty($sanitized_input['api_username']) && !empty($sanitized_input['api_password']) && !empty($sanitized_input['supplier_id'])) {
                if (!wp_next_scheduled('trendyol_wc_hourly_event')) {
                    wp_schedule_event(time(), $sanitized_input['sync_schedule'], 'trendyol_wc_hourly_event');
                }
            }
        }
        
        // Webhook endpoint değiştiyse, rewrite kurallarını temizle
        if (isset($existing_settings['webhook_endpoint']) && $sanitized_input['webhook_endpoint'] !== $existing_settings['webhook_endpoint']) {
            flush_rewrite_rules();
        }
        
        return $sanitized_input;
    }

    /**
     * Ayarlar sayfasını oluştur
     */
    public function render_settings_page() {
        // Ayarları yükle
        $settings = get_option('trendyol_wc_settings', array());
        
        // Form gönderildi mi kontrol et
        if (isset($_POST['trendyol_wc_settings_submit']) && check_admin_referer('trendyol_wc_settings_action', 'trendyol_wc_settings_nonce')) {
            // Formu işle
            $new_settings = $this->sanitize_settings($_POST['trendyol_wc_settings']);
            update_option('trendyol_wc_settings', $new_settings);
            
            // Başarı mesajı göster
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Ayarlar başarıyla kaydedildi.', 'trendyol-woocommerce') . '</p></div>';
            
            // Güncel ayarları yükle
            $settings = get_option('trendyol_wc_settings', array());
        }
        
        // API bağlantı durumu kontrolü
        $api = new Trendyol_WC_API();
        $connection_status = $this->check_api_connection($api);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Trendyol WooCommerce Entegrasyonu Ayarları', 'trendyol-woocommerce'); ?></h1>
            
            <?php if ($connection_status['connected']) : ?>
                <div class="notice notice-success inline">
                    <p><?php echo esc_html__('Trendyol API bağlantısı başarılı!', 'trendyol-woocommerce'); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-error inline">
                    <p><?php echo esc_html__('Trendyol API bağlantısı kurulamadı:', 'trendyol-woocommerce') . ' ' . esc_html($connection_status['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('trendyol_wc_settings_action', 'trendyol_wc_settings_nonce'); ?>
                
                <h2><?php echo esc_html__('API Ayarları', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Trendyol API bağlantısı için gerekli temel ayarları yapılandırın.', 'trendyol-woocommerce'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="trendyol_api_username"><?php echo esc_html__('API Kullanıcı Adı', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <input type="text" id="trendyol_api_username" name="trendyol_wc_settings[api_username]" value="<?php echo esc_attr(isset($settings['api_username']) ? $settings['api_username'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Trendyol API kullanıcı adınızı girin.', 'trendyol-woocommerce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_api_password"><?php echo esc_html__('API Şifre', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <input type="password" id="trendyol_api_password" name="trendyol_wc_settings[api_password]" value="<?php echo esc_attr(isset($settings['api_password']) ? $settings['api_password'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Trendyol API şifrenizi girin.', 'trendyol-woocommerce'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_supplier_id"><?php echo esc_html__('Satıcı ID', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <input type="text" id="trendyol_supplier_id" name="trendyol_wc_settings[supplier_id]" value="<?php echo esc_attr(isset($settings['supplier_id']) ? $settings['supplier_id'] : ''); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Trendyol Satıcı ID\'nizi girin. Bu bilgiyi Trendyol Satıcı Panelinden alabilirsiniz.', 'trendyol-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php echo esc_html__('Ürün Ayarları', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Trendyol\'a gönderilecek ürünler için varsayılan ayarları yapılandırın.', 'trendyol-woocommerce'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="trendyol_default_category_id"><?php echo esc_html__('Varsayılan Kategori', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->default_category_callback(); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html__('Varsayılan Marka', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <div class="trendyol-brand-search-wrapper">
                                <input type="text" id="trendyol-default-brand-search" 
                                       placeholder="<?php echo esc_attr__('Marka adını yazmaya başlayın...', 'trendyol-woocommerce'); ?>" 
                                       class="regular-text">
                                <div id="trendyol-brand-search-results" class="trendyol-search-results"></div>
                                
                                <input type="hidden" name="trendyol_wc_settings[default_brand_id]" 
                                       id="trendyol-default-brand-id" 
                                       value="<?php echo esc_attr($settings['default_brand_id'] ?? ''); ?>">
                                
                                <div id="trendyol-selected-brand" class="trendyol-selected-item">
                                    <?php 
                                    if (!empty($settings['default_brand_id'])) {
                                        $brands_api = new Trendyol_WC_Brands_API();
                                        $brand = $brands_api->get_brand_from_database($settings['default_brand_id']);
                                        if ($brand) {
                                            echo '<span class="trendyol-selected-name">' . esc_html($brand['name']) . '</span>';
                                            echo '<button type="button" class="button trendyol-remove-selected">×</button>';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <p class="description">
                                    <?php echo esc_html__('Ürünler için varsayılan Trendyol markasını seçin.', 'trendyol-woocommerce'); ?>
                                </p>
                            </div>
                            
                            <style>
                                .trendyol-brand-search-wrapper {
                                    position: relative;
                                    margin-bottom: 10px;
                                }
                                .trendyol-search-results {
                                    position: absolute;
                                    background: #fff;
                                    border: 1px solid #ddd;
                                    max-height: 200px;
                                    overflow-y: auto;
                                    width: 100%;
                                    z-index: 100;
                                    display: none;
                                }
                                .trendyol-search-results div {
                                    padding: 8px 10px;
                                    cursor: pointer;
                                }
                                .trendyol-search-results div:hover {
                                    background: #f1f1f1;
                                }
                                .trendyol-selected-item {
                                    display: flex;
                                    align-items: center;
                                    margin-top: 5px;
                                }
                                .trendyol-selected-name {
                                    background: #f1f1f1;
                                    padding: 5px 10px;
                                    border-radius: 3px;
                                    margin-right: 5px;
                                }
                                .trendyol-remove-selected {
                                    padding: 0 !important;
                                    width: 22px !important;
                                    height: 22px !important;
                                    line-height: 20px !important;
                                    font-size: 16px !important;
                                }
                            </style>
                            
                            <script>
                            jQuery(document).ready(function($) {
                                // Marka arama
                                var searchTimeout;
                                
                                $('#trendyol-default-brand-search').on('keyup', function() {
                                    var searchTerm = $(this).val();
                                    
                                    // En az 2 karakter gerekli
                                    if (searchTerm.length < 2) {
                                        $('#trendyol-brand-search-results').empty().hide();
                                        return;
                                    }
                                    
                                    clearTimeout(searchTimeout);
                                    searchTimeout = setTimeout(function() {
                                        $.ajax({
                                            url: ajaxurl,
                                            type: 'POST',
                                            data: {
                                                action: 'trendyol_search_brands_from_database',
                                                nonce: '<?php echo wp_create_nonce('trendyol-brands-search'); ?>',
                                                search: searchTerm
                                            },
                                            beforeSend: function() {
                                                $('#trendyol-brand-search-results').html('<div>Aranıyor...</div>').show();
                                            },
                                            success: function(response) {
                                                if (response.success && response.data.brands.length > 0) {
                                                    var resultsHtml = '';
                                                    $.each(response.data.brands, function(index, brand) {
                                                        resultsHtml += '<div data-id="' + brand.id + '">' + brand.name + '</div>';
                                                    });
                                                    $('#trendyol-brand-search-results').html(resultsHtml).show();
                                                } else {
                                                    $('#trendyol-brand-search-results').html('<div>Sonuç bulunamadı</div>').show();
                                                }
                                            },
                                            error: function() {
                                                $('#trendyol-brand-search-results').html('<div>Arama hatası</div>').show();
                                            }
                                        });
                                    }, 300);
                                });
                                
                                // Arama sonuçlarından seçim
                                $(document).on('click', '#trendyol-brand-search-results div', function() {
                                    var brandId = $(this).data('id');
                                    var brandName = $(this).text();
                                    
                                    // Değeri gizli alana ayarla
                                    $('#trendyol-default-brand-id').val(brandId);
                                    
                                    // Seçilen markayı göster
                                    $('#trendyol-selected-brand').html(
                                        '<span class="trendyol-selected-name">' + brandName + '</span>' +
                                        '<button type="button" class="button trendyol-remove-selected">×</button>'
                                    );
                                    
                                    // Arama kutusunu temizle ve sonuçları gizle
                                    $('#trendyol-default-brand-search').val('');
                                    $('#trendyol-brand-search-results').empty().hide();
                                });
                                
                                // Seçilen markayı kaldır
                                $(document).on('click', '.trendyol-remove-selected', function() {
                                    $('#trendyol-default-brand-id').val('');
                                    $('#trendyol-selected-brand').empty();
                                });
                                
                                // Sayfa dışı tıklamada sonuçları kapat
                                $(document).on('click', function(e) {
                                    if (!$(e.target).closest('.trendyol-brand-search-wrapper').length) {
                                        $('#trendyol-brand-search-results').empty().hide();
                                    }
                                });
                            });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_vat_rate"><?php echo esc_html__('KDV Oranı', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->vat_rate_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Kargo ve Teslimat Ayarları', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->cargo_settings_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_shipment_address_id"><?php echo esc_html__('Sevkiyat Adresi ID', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->shipment_address_id_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_returning_address_id"><?php echo esc_html__('İade Adresi ID', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->returning_address_id_callback(); ?>
                        </td>
                    </tr>
                </table>
                
                <h2><?php echo esc_html__('Senkronizasyon Ayarları', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Trendyol ve WooCommerce arasındaki senkronizasyon ayarlarını yapılandırın.', 'trendyol-woocommerce'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Otomatik Senkronizasyon', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->auto_sync_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Senkronizasyon Sıklığı', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->sync_schedule_callback(); ?>
                        </td>
                    </tr>
                </table>
                
                <h2><?php echo esc_html__('Webhook Ayarları', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Trendyol\'dan gerçek zamanlı bildirimler almak için webhook ayarlarını yapılandırın.', 'trendyol-woocommerce'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="trendyol_webhook_endpoint"><?php echo esc_html__('Webhook Endpoint', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->webhook_endpoint_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trendyol_webhook_secret"><?php echo esc_html__('Webhook Güvenlik Anahtarı', 'trendyol-woocommerce'); ?></label></th>
                        <td>
                            <?php $this->webhook_secret_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Webhook Durumu', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->webhook_status_callback(); ?>
                        </td>
                    </tr>
                </table>
                
                <h2><?php echo esc_html__('Gelişmiş Ayarlar', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Gelişmiş entegrasyon ayarlarını yapılandırın.', 'trendyol-woocommerce'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Log Ayarları', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->log_settings_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Hata Ayıklama Modu', 'trendyol-woocommerce'); ?></th>
                        <td>
                            <?php $this->debug_mode_callback(); ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="trendyol_wc_settings_submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Değişiklikleri Kaydet', 'trendyol-woocommerce'); ?>">
                </p>
            </form>
            
            <div class="trendyol-wc-actions">
                <h2><?php echo esc_html__('Hızlı İşlemler', 'trendyol-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Aşağıdaki butonları kullanarak senkronizasyon işlemlerini manuel olarak başlatabilirsiniz.', 'trendyol-woocommerce'); ?></p>
                
                <div class="trendyol-wc-action-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-products')); ?>" class="button button-primary">
                        <?php echo esc_html__('Ürünleri Senkronize Et', 'trendyol-woocommerce'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-orders')); ?>" class="button button-primary">
                        <?php echo esc_html__('Siparişleri Senkronize Et', 'trendyol-woocommerce'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-categories')); ?>" class="button">
                        <?php echo esc_html__('Kategorileri Senkronize Et', 'trendyol-woocommerce'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-brands')); ?>" class="button">
                        <?php echo esc_html__('Markaları Senkronize Et', 'trendyol-woocommerce'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * API bağlantısını kontrol et
     */
    private function check_api_connection($api) {
        $settings = get_option('trendyol_wc_settings');
        
        // API kimlik bilgileri kontrol et
        if (empty($settings['api_username']) || empty($settings['api_password']) || empty($settings['supplier_id'])) {
            return array(
                'connected' => false,
                'message' => __('API kimlik bilgileri eksik', 'trendyol-woocommerce')
            );
        }
        
        // Test API isteği gönder
        $brands_api = new Trendyol_WC_Brands_API();
        $response = $brands_api->get_brands(array('size' => 1));
        
        if (is_wp_error($response)) {
            return array(
                'connected' => false,
                'message' => $response->get_error_message()
            );
        }
        
        return array(
            'connected' => true,
            'message' => ''
        );
    }
}