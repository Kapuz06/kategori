<?php
/**
 * Trendyol WooCommerce - Brands Template
 *
 * Markalar sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Yeni marka tablosunun adı
$brands_table = $wpdb->prefix . 'trendyol_brands';
$brand_matches_table = $wpdb->prefix . 'trendyol_brand_matches';

// Seçilen nitelik
$selected_attribute = isset($_POST['selected_attribute']) ? sanitize_text_field($_POST['selected_attribute']) : get_option('trendyol_wc_brand_attribute', '');

// Nitelik seçilmiş mi kontrol et
$attribute_selected = !empty($selected_attribute);

// Nitelik değiştirildiğinde kaydet
if (isset($_POST['save_attribute']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'save_attribute')) {
    $new_attribute = sanitize_text_field($_POST['selected_attribute']);
    update_option('trendyol_wc_brand_attribute', $new_attribute);
    $selected_attribute = $new_attribute;
    $attribute_selected = !empty($selected_attribute);
    $message = __('Nitelik seçimi kaydedildi.', 'trendyol-woocommerce');
    $success = true;
}

// Formdan gönderilmiş olan verileri işle
if (isset($_POST['save_brand_match']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'save_brand_match')) {
    $trendyol_brand_id = isset($_POST['trendyol_brand_id']) ? intval($_POST['trendyol_brand_id']) : 0;
    $wc_attribute_term = isset($_POST['wc_attribute_term']) ? sanitize_text_field($_POST['wc_attribute_term']) : '';
    
    if ($trendyol_brand_id > 0 && !empty($wc_attribute_term)) {
        // Eğer eşleşme zaten varsa güncelle, yoksa yeni ekle
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$brand_matches_table} WHERE wc_attribute_term = %s",
            $wc_attribute_term
        ));

        if ($existing) {
            $wpdb->update(
                $brand_matches_table,
                array('trendyol_brand_id' => $trendyol_brand_id),
                array('wc_attribute_term' => $wc_attribute_term),
                array('%d'),
                array('%s')
            );
            $message = __('Marka eşleşmesi güncellendi.', 'trendyol-woocommerce');
        } else {
            $wpdb->insert(
                $brand_matches_table,
                array(
                    'wc_attribute_term' => $wc_attribute_term,
                    'trendyol_brand_id' => $trendyol_brand_id
                ),
                array('%s', '%d')
            );
            $message = __('Yeni marka eşleşmesi oluşturuldu.', 'trendyol-woocommerce');
        }
        $success = true;
    } else {
        $message = __('Geçersiz marka verileri.', 'trendyol-woocommerce');
        $success = false;
    }
}

// Eşleşmeyi kaldırma işlemi
if (isset($_GET['action']) && $_GET['action'] == 'delete_match' && isset($_GET['term']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_brand_match')) {
    $term = sanitize_text_field($_GET['term']);
    
    $deleted = $wpdb->delete(
        $brand_matches_table,
        array('wc_attribute_term' => $term),
        array('%s')
    );
    
    if ($deleted) {
        $message = __('Marka eşleşmesi kaldırıldı.', 'trendyol-woocommerce');
        $success = true;
    } else {
        $message = __('Marka eşleşmesi kaldırılırken bir hata oluştu.', 'trendyol-woocommerce');
        $success = false;
    }
}

// Kullanılabilir ürün niteliklerini al
$attribute_taxonomies = wc_get_attribute_taxonomies();

// Seçilen nitelik için değerleri al
$attribute_terms = array();
if ($attribute_selected) {
    $taxonomy = 'pa_' . $selected_attribute;
    $attribute_terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ));
}

// Mevcut eşleşmeleri al
$matched_brands = array();
if ($attribute_selected) {
    $matches_query = $wpdb->get_results($wpdb->prepare(
        "SELECT bm.wc_attribute_term, bm.trendyol_brand_id, tb.name as trendyol_brand_name 
         FROM {$brand_matches_table} bm 
         LEFT JOIN {$brands_table} tb ON bm.trendyol_brand_id = tb.id
         WHERE bm.wc_attribute_term LIKE %s
         ORDER BY bm.wc_attribute_term ASC",
        $selected_attribute . ":%"
    ));

    foreach ($matches_query as $match) {
        $term_value = str_replace($selected_attribute . ':', '', $match->wc_attribute_term);
        $matched_brands[$match->wc_attribute_term] = array(
            'id' => $match->trendyol_brand_id,
            'name' => $match->trendyol_brand_name,
            'term_value' => $term_value
        );
    }
}
?>
<div class="wrap trendyol-wc-brands">
    <h1><?php echo esc_html__('Trendyol Marka Yönetimi', 'trendyol-woocommerce'); ?></h1>

    <?php if (isset($message)) : ?>
        <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Marka Senkronizasyonu', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <p><?php echo esc_html__('Trendyol markalarını WooCommerce\'e aktarın. Bu işlem, ürün senkronizasyonu sırasında kullanılacak marka eşleştirmelerini oluşturacaktır.', 'trendyol-woocommerce'); ?></p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-brands')); ?>">
                <?php wp_nonce_field('trendyol_sync_brands'); ?>
                <div class="trendyol-wc-form-row">
                    <label>
                        <input type="checkbox" name="trendyol_create_brands" value="1">
                        <?php echo esc_html__('WooCommerce\'de eşleşmeyen markaları otomatik oluştur', 'trendyol-woocommerce'); ?>
                    </label>
                </div>
                <div class="trendyol-wc-form-actions">
                    <input type="hidden" name="trendyol_sync_brands" value="1">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Markaları Senkronize Et', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Marka Niteliği Seçimi', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <p><?php echo esc_html__('Marka eşleştirmesi için kullanılacak ürün niteliğini seçin.', 'trendyol-woocommerce'); ?></p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-brands')); ?>">
                <?php wp_nonce_field('save_attribute'); ?>
                <div class="trendyol-wc-form-row">
                    <label for="selected_attribute"><?php echo esc_html__('Ürün Niteliği:', 'trendyol-woocommerce'); ?></label>
                    <select name="selected_attribute" id="selected_attribute" class="regular-text">
                        <option value=""><?php esc_html_e('-- Nitelik Seçin --', 'trendyol-woocommerce'); ?></option>
                        <?php 
                        if (!empty($attribute_taxonomies)) {
                            foreach ($attribute_taxonomies as $attribute) {
                                echo '<option value="' . esc_attr($attribute->attribute_name) . '" ' . selected($selected_attribute, $attribute->attribute_name, false) . '>' . esc_html($attribute->attribute_label) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="trendyol-wc-form-actions">
                    <button type="submit" name="save_attribute" class="button button-primary">
                        <?php echo esc_html__('Niteliği Kaydet', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($attribute_selected): ?>
    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Yeni Marka Eşleştirmesi Oluştur', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-brands')); ?>">
                <?php wp_nonce_field('save_brand_match'); ?>
                
                <div class="trendyol-wc-form-row">
                    <label for="wc_attribute_term"><?php echo esc_html__('Ürün Nitelik Değeri:', 'trendyol-woocommerce'); ?></label>
                    <select name="wc_attribute_term" id="wc_attribute_term" class="regular-text" required>
                        <option value=""><?php esc_html_e('-- Değer Seçin --', 'trendyol-woocommerce'); ?></option>
                        <?php 
                        if (!empty($attribute_terms) && !is_wp_error($attribute_terms)) {
                            foreach ($attribute_terms as $term) {
                                $term_key = $selected_attribute . ':' . $term->slug;
                                $disabled = isset($matched_brands[$term_key]) ? 'disabled' : '';
                                echo '<option value="' . esc_attr($term_key) . '" ' . $disabled . '>' . esc_html($term->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="trendyol-wc-form-row">
                    <label for="trendyol_brand_search"><?php echo esc_html__('Trendyol Markası Ara:', 'trendyol-woocommerce'); ?></label>
                    <input type="text" id="trendyol_brand_search" class="regular-text" placeholder="<?php esc_attr_e('Marka adı yazın...', 'trendyol-woocommerce'); ?>">
                    <div id="trendyol_brand_results" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; margin-top: 5px;"></div>
                    <input type="hidden" name="trendyol_brand_id" id="trendyol_brand_id" value="">
                    <p id="selected_brand_display" style="font-weight: bold;"></p>
                </div>
                
                <div class="trendyol-wc-form-actions">
                    <button type="submit" name="save_brand_match" class="button button-primary">
                        <?php echo esc_html__('Marka Eşleştirmesini Kaydet', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Mevcut Marka Eşleşmeleri', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <div class="trendyol-wc-search-box">
                <input type="text" id="match-search" placeholder="<?php echo esc_attr__('Eşleşmelerde ara...', 'trendyol-woocommerce'); ?>" class="regular-text">
            </div>
            
            <?php if (!empty($matched_brands)) : ?>
                <table class="wp-list-table widefat fixed striped trendyol-wc-table" id="brand-matches-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Nitelik Değeri', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol Marka ID', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol Marka', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matched_brands as $term_key => $brand) : ?>
                            <tr>
                                <td><?php echo esc_html($brand['term_value']); ?></td>
                                <td><?php echo esc_html($brand['id']); ?></td>
                                <td><?php echo esc_html($brand['name']); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_match', 'term' => $term_key), admin_url('admin.php?page=trendyol-wc-brands')), 'delete_brand_match'); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Bu marka eşleşmesini kaldırmak istediğinize emin misiniz?', 'trendyol-woocommerce'); ?>');">
                                        <?php esc_html_e('Eşleşmeyi Kaldır', 'trendyol-woocommerce'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('Henüz marka eşleşmesi bulunmuyor. Yukarıdaki formu kullanarak yeni bir eşleşme oluşturabilirsiniz.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Kart stilleri */
.trendyol-wc-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    margin-bottom: 20px;
    border-radius: 4px;
}

.trendyol-wc-card-header {
    border-bottom: 1px solid #ccd0d4;
    padding: 10px 15px;
    background: #f8f9f9;
}

.trendyol-wc-card-header h2 {
    margin: 0;
    font-size: 16px;
}

.trendyol-wc-card-body {
    padding: 15px;
}

/* Form stilleri */
.trendyol-wc-form-row {
    margin-bottom: 15px;
}

.trendyol-wc-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.trendyol-wc-form-actions {
    margin-top: 20px;
}

/* Arama sonuçları stilleri */
#trendyol_brand_results {
    background: #fff;
    border: 1px solid #ddd;
    max-height: 200px;
    overflow-y: auto;
    margin-top: 5px;
}

#trendyol_brand_results ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

#trendyol_brand_results li {
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
}

#trendyol_brand_results li:hover {
    background-color: #f6f7f7;
}

/* Arama kutusunu tüm genişliğe yay */
.trendyol-wc-search-box {
    margin-bottom: 15px;
}

.trendyol-wc-search-box input[type="text"] {
    width: 100%;
}

/* Select2 desteği */
.select2-container {
    width: 100% !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select2 entegrasyonu
    if ($.fn.select2) {
        $('#selected_attribute, #wc_attribute_term').select2({
            placeholder: '<?php echo esc_js(__('Seçin...', 'trendyol-woocommerce')); ?>',
            width: '100%'
        });
    }
    
    // Trendyol marka arama için AJAX
    var searchTimer;
    $('#trendyol_brand_search').on('keyup', function() {
        var searchTerm = $(this).val().trim();
        
        clearTimeout(searchTimer);
        
        if (searchTerm.length < 2) {
            $('#trendyol_brand_results').hide();
            return;
        }
        
        searchTimer = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'search_trendyol_brands',
                    nonce: '<?php echo wp_create_nonce('trendyol_search_brands'); ?>',
                    search: searchTerm
                },
                beforeSend: function() {
                    $('#trendyol_brand_results').html('<div style="padding: 10px; text-align: center;"><span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js(__('Aranıyor...', 'trendyol-woocommerce')); ?></div>').show();
                },
                success: function(response) {
                    if (response.success) {
                        var brands = response.data;
                        var html = '<ul>';
                        
                        if (brands.length === 0) {
                            html = '<div style="padding: 10px; text-align: center;"><?php echo esc_js(__('Sonuç bulunamadı.', 'trendyol-woocommerce')); ?></div>';
                        } else {
                            for (var i = 0; i < brands.length; i++) {
                                html += '<li data-id="' + brands[i].id + '" data-name="' + brands[i].name + '">' + brands[i].name + ' (ID: ' + brands[i].id + ')</li>';
                            }
                            html += '</ul>';
                        }
                        
                        $('#trendyol_brand_results').html(html);
                        
                        // Sonuç öğelerine tıklama olayı ekle
                        $('#trendyol_brand_results li').on('click', function() {
                            var id = $(this).data('id');
                            var name = $(this).data('name');
                            
                            $('#trendyol_brand_id').val(id);
                            $('#trendyol_brand_search').val(name);
                            $('#selected_brand_display').text('<?php echo esc_js(__('Seçilen Marka:', 'trendyol-woocommerce')); ?> ' + name + ' (ID: ' + id + ')');
                            $('#trendyol_brand_results').hide();
                        });
                    } else {
                        $('#trendyol_brand_results').html('<div style="padding: 10px; text-align: center;"><?php echo esc_js(__('Arama sırasında bir hata oluştu.', 'trendyol-woocommerce')); ?></div>');
                    }
                },
                error: function() {
                    $('#trendyol_brand_results').html('<div style="padding: 10px; text-align: center;"><?php echo esc_js(__('Sunucu ile iletişim kurulamadı.', 'trendyol-woocommerce')); ?></div>');
                }
            });
        }, 500);
    });
    
    // Tıklama kontrolü
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#trendyol_brand_search, #trendyol_brand_results').length) {
            $('#trendyol_brand_results').hide();
        }
    });
    
    // Eşleşme listesi için arama filtreleme
    $('#match-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('#brand-matches-table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(searchTerm) > -1);
        });
    });
    
    // Form doğrulama
    $('form').on('submit', function(e) {
        var action = $(this).find('button[type="submit"][name]').attr('name');
        
        if (action === 'save_brand_match') {
            var trendyolId = $('#trendyol_brand_id').val();
            var wcAttributeTerm = $('#wc_attribute_term').val();
            
            if (!trendyolId || !wcAttributeTerm) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Lütfen hem nitelik değerini hem de Trendyol markasını seçin.', 'trendyol-woocommerce')); ?>');
                return false;
            }
        }
        
        return true;
    });
});
</script>