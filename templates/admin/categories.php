<?php
/**
 * Trendyol WooCommerce - Categories Template
 *
 * Kategoriler sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap trendyol-wc-categories">
    <h1><?php echo esc_html__('Trendyol Kategori Yönetimi', 'trendyol-woocommerce'); ?></h1>

    <?php if (isset($message)) : ?>
        <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Kategori Senkronizasyonu', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <p><?php echo esc_html__('Trendyol kategorilerini WooCommerce\'e aktarın. Bu işlem, ürün senkronizasyonu sırasında kullanılacak kategori eşleştirmelerini oluşturacaktır.', 'trendyol-woocommerce'); ?></p>
            
            <?php if (!empty($last_sync)) : ?>
                <p class="trendyol-last-sync">
                    <?php echo esc_html__('Son senkronizasyon: ', 'trendyol-woocommerce'); ?>
                    <strong><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))); ?></strong>
                </p>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-categories')); ?>">
                <?php wp_nonce_field('trendyol_sync_categories'); ?>
                <div class="trendyol-wc-form-actions">
                    <input type="hidden" name="trendyol_sync_categories" value="1">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Kategorileri Senkronize Et', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kategori Eşleştirme Alanı -->
    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Kategori Eşleştirme', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <!-- Trendyol Kategori Arama Alanı -->
            <div class="trendyol-category-search-container">
                <h3><?php echo esc_html__('1. Trendyol Kategorisi Seçin', 'trendyol-woocommerce'); ?></h3>
                <div class="trendyol-search-field">
                    <input type="text" id="trendyol-category-search-input" class="regular-text" placeholder="<?php echo esc_attr__('Kategori adı veya ID\'si yazarak arayın...', 'trendyol-woocommerce'); ?>" data-nonce="<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>">
                    <div id="trendyol-search-results" class="trendyol-search-results"></div>
                    <div class="trendyol-selected-category-container" style="display:none;">
                        <h4><?php echo esc_html__('Seçilen Trendyol Kategorisi', 'trendyol-woocommerce'); ?></h4>
                        <div id="trendyol-selected-category" class="trendyol-selected-item"></div>
                    </div>
                </div>
            </div>

            <!-- WooCommerce Kategori Arama Alanı -->
            <div class="wc-category-search-container" style="display:none;">
                <h3><?php echo esc_html__('2. WooCommerce Kategorilerini Seçin', 'trendyol-woocommerce'); ?></h3>
                <div class="wc-search-field">
                    <input type="text" id="wc-category-search-input" class="regular-text" placeholder="<?php echo esc_attr__('WooCommerce kategori adı yazarak arayın...', 'trendyol-woocommerce'); ?>" data-nonce="<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>">
                    <div id="wc-search-results" class="wc-search-results"></div>
                    <div class="wc-selected-categories-container">
                        <h4><?php echo esc_html__('Seçilen WooCommerce Kategorileri', 'trendyol-woocommerce'); ?></h4>
                        <div id="wc-selected-categories" class="wc-selected-items"></div>
                    </div>
                </div>
                <div class="trendyol-actions">
                    <button id="save-category-mapping" class="button button-primary" data-nonce="<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>" disabled>
                        <?php echo esc_html__('Eşleştirmeyi Kaydet', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Eşleştirilmiş Kategoriler Tablosu -->
    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Mevcut Kategori Eşleşmeleri', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <div id="mapped-categories-container">
                <?php if (!empty($mapped_categories)) : ?>
                    <table class="wp-list-table widefat fixed striped trendyol-wc-table">
                        <thead>
                            <tr>
                                <th width="30%"><?php echo esc_html__('Trendyol Kategori', 'trendyol-woocommerce'); ?></th>
                                <th width="30%"><?php echo esc_html__('WooCommerce Kategorileri', 'trendyol-woocommerce'); ?></th>
                                <th width="20%"><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                                <th width="20%"><?php echo esc_html__('Nitelikler', 'trendyol-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mapped_categories as $trendyol_id => $mapping) : ?>
                                <tr data-category-id="<?php echo esc_attr($trendyol_id); ?>">
                                    <td>
                                        <strong><?php echo esc_html($mapping['trendyol_name']); ?></strong>
                                        <div class="category-id-display">ID: <?php echo esc_html($trendyol_id); ?></div>
                                    </td>
                                    <td>
                                        <ul class="wc-categories-list">
                                            <?php foreach ($mapping['wc_categories'] as $wc_category) : ?>
                                                <li><?php echo esc_html($wc_category['name']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <button class="button remove-mapping" data-category-id="<?php echo esc_attr($trendyol_id); ?>" data-nonce="<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>">
                                            <?php echo esc_html__('Eşleşmeyi Kaldır', 'trendyol-woocommerce'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <button class="button manage-attributes" data-category-id="<?php echo esc_attr($trendyol_id); ?>" data-nonce="<?php echo wp_create_nonce('trendyol-wc-nonce'); ?>">
                                            <?php echo esc_html__('Nitelikleri Ayarla', 'trendyol-woocommerce'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="attributes-row" id="attributes-row-<?php echo esc_attr($trendyol_id); ?>" style="display: none;">
                                    <td colspan="4" class="attributes-container">
                                        <div class="attributes-loading">
                                            <?php echo esc_html__('Nitelikler yükleniyor...', 'trendyol-woocommerce'); ?>
                                        </div>
                                        <div class="attributes-content" id="attributes-content-<?php echo esc_attr($trendyol_id); ?>"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('Henüz kategori eşleşmesi bulunmuyor. Yukarıdaki arama kutularını kullanarak eşleştirme yapabilirsiniz.', 'trendyol-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let selectedTrendyolCategory = null;
    let selectedWcCategories = [];

    // Trendyol kategorilerini ara
    $('#trendyol-category-search-input').on('keyup', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length < 3) {
            $('#trendyol-search-results').empty().hide();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_search_categories_from_db',
                search: searchTerm,
                nonce: $(this).data('nonce')
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<ul>';
                    response.data.forEach(function(category) {
                        html += `<li data-id="${category.id}" data-name="${category.name}">
                            <span class="category-name">${category.name}</span>
                            <span class="category-id">ID: ${category.id}</span>
                        </li>`;
                    });
                    html += '</ul>';
                    $('#trendyol-search-results').html(html).show();
                } else {
                    $('#trendyol-search-results').html('<p class="no-results"><?php echo esc_js(__('Sonuç bulunamadı.', 'trendyol-woocommerce')); ?></p>').show();
                }
            },
            error: function() {
                $('#trendyol-search-results').html('<p class="no-results"><?php echo esc_js(__('Arama sırasında bir hata oluştu.', 'trendyol-woocommerce')); ?></p>').show();
            }
        });
    });

    // Trendyol kategori seçimi
    $(document).on('click', '#trendyol-search-results li', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        selectedTrendyolCategory = {
            id: categoryId,
            name: categoryName
        };
        
        $('#trendyol-selected-category').html(`
            <div class="selected-item" data-id="${categoryId}">
                <span class="item-name">${categoryName}</span>
                <span class="item-id">ID: ${categoryId}</span>
            </div>
        `);
        
        $('.trendyol-selected-category-container').show();
        $('#trendyol-search-results').hide();
        $('#trendyol-category-search-input').val('');
        
        // WooCommerce kategori seçim alanını göster
        $('.wc-category-search-container').show();
        
        // Seçimleri kontrol et
        checkSelections();
    });

    // WooCommerce kategorilerini ara
    $('#wc-category-search-input').on('keyup', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length < 2) {
            $('#wc-search-results').empty().hide();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_search_wc_categories',
                search: searchTerm,
                nonce: $(this).data('nonce')
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<ul>';
                    response.data.forEach(function(category) {
                        // Zaten seçili mi kontrol et
                        const isSelected = selectedWcCategories.some(cat => cat.id === category.id);
                        if (!isSelected) {
                            html += `<li data-id="${category.id}" data-name="${category.name}">
                                <span class="category-name">${category.name}</span>
                            </li>`;
                        }
                    });
                    html += '</ul>';
                    $('#wc-search-results').html(html).show();
                } else {
                    $('#wc-search-results').html('<p class="no-results"><?php echo esc_js(__('Sonuç bulunamadı.', 'trendyol-woocommerce')); ?></p>').show();
                }
            },
            error: function() {
                $('#wc-search-results').html('<p class="no-results"><?php echo esc_js(__('Arama sırasında bir hata oluştu.', 'trendyol-woocommerce')); ?></p>').show();
            }
        });
    });

    // WooCommerce kategori çoklu seçimi
    $(document).on('click', '#wc-search-results li', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        // Zaten seçili değilse ekle
        if (!selectedWcCategories.some(cat => cat.id === categoryId)) {
            selectedWcCategories.push({
                id: categoryId,
                name: categoryName
            });
            
            updateSelectedWcCategories();
        }
        
        $('#wc-search-results').hide();
        $('#wc-category-search-input').val('');
        
        // Seçimleri kontrol et
        checkSelections();
    });

    // Seçili WooCommerce kategorisini kaldır
    $(document).on('click', '.wc-selected-item .remove-item', function() {
        const categoryId = $(this).closest('.wc-selected-item').data('id');
        selectedWcCategories = selectedWcCategories.filter(cat => cat.id !== categoryId);
        
        updateSelectedWcCategories();
        checkSelections();
    });

    // Seçili WooCommerce kategorilerini güncelle
    function updateSelectedWcCategories() {
        let html = '';
        if (selectedWcCategories.length > 0) {
            selectedWcCategories.forEach(function(category) {
                html += `<div class="wc-selected-item" data-id="${category.id}">
                    <span class="item-name">${category.name}</span>
                    <button type="button" class="remove-item" title="<?php echo esc_js(__('Kaldır', 'trendyol-woocommerce')); ?>">×</button>
                </div>`;
            });
        } else {
            html = `<p class="no-selection"><?php echo esc_js(__('Henüz kategori seçilmedi.', 'trendyol-woocommerce')); ?></p>`;
        }
        
        $('#wc-selected-categories').html(html);
    }

    // Seçimleri kontrol et ve kaydet butonunu aktifleştir/deaktifleştir
    function checkSelections() {
        if (selectedTrendyolCategory && selectedWcCategories.length > 0) {
            $('#save-category-mapping').prop('disabled', false);
        } else {
            $('#save-category-mapping').prop('disabled', true);
        }
    }

    // Kategori eşleştirmesini kaydet
    $('#save-category-mapping').on('click', function() {
        if (!selectedTrendyolCategory || selectedWcCategories.length === 0) {
            return;
        }
        
        const wcCategoryIds = selectedWcCategories.map(cat => cat.id);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_map_categories',
                trendyol_category_id: selectedTrendyolCategory.id,
                wc_category_ids: wcCategoryIds,
                nonce: $(this).data('nonce')
            },
            beforeSend: function() {
                $('#save-category-mapping').prop('disabled', true).text('<?php echo esc_js(__('Kaydediliyor...', 'trendyol-woocommerce')); ?>');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Sayfayı yenile
                    location.reload();
                } else {
                    alert(response.data.message);
                    $('#save-category-mapping').prop('disabled', false).text('<?php echo esc_js(__('Eşleştirmeyi Kaydet', 'trendyol-woocommerce')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Eşleştirme kaydedilirken bir hata oluştu.', 'trendyol-woocommerce')); ?>');
                $('#save-category-mapping').prop('disabled', false).text('<?php echo esc_js(__('Eşleştirmeyi Kaydet', 'trendyol-woocommerce')); ?>');
            }
        });
    });

    // Eşleşmeyi kaldır
    $(document).on('click', '.remove-mapping', function() {
        if (!confirm('<?php echo esc_js(__('Bu kategori eşleşmesini kaldırmak istediğinizden emin misiniz?', 'trendyol-woocommerce')); ?>')) {
            return;
        }
        
        const categoryId = $(this).data('category-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_unmap_category',
                trendyol_category_id: categoryId,
                nonce: $(this).data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Sayfayı yenile
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Eşleşme kaldırılırken bir hata oluştu.', 'trendyol-woocommerce')); ?>');
            }
        });
    });

    // Nitelikleri göster/gizle
    $(document).on('click', '.manage-attributes', function() {
        const categoryId = $(this).data('category-id');
        const attributesRow = $(`#attributes-row-${categoryId}`);
        const attributesContent = $(`#attributes-content-${categoryId}`);
        
        if (attributesRow.is(':visible')) {
            attributesRow.hide();
            return;
        }
        
        // Diğer açık nitelik satırlarını kapat
        $('.attributes-row').not(attributesRow).hide();
        
        // Nitelikleri yükle
        if (attributesContent.is(':empty')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'trendyol_get_category_attributes',
                    category_id: categoryId,
                    nonce: $(this).data('nonce')
                },
                beforeSend: function() {
                    attributesRow.show();
                    attributesContent.empty();
                    attributesRow.find('.attributes-loading').show();
                },
                success: function(response) {
                    attributesRow.find('.attributes-loading').hide();
                    
                    if (response.success) {
                        const trendyolAttributes = response.data.trendyol_attributes;
                        const wcAttributes = response.data.wc_attributes;
                        
                        if (trendyolAttributes.length === 0) {
                            attributesContent.html(`<p class="no-attributes"><?php echo esc_js(__('Bu kategori için nitelik bulunmuyor.', 'trendyol-woocommerce')); ?></p>`);
                            return;
                        }
                        
                        // Gerekli nitelikleri önde göster
                        const requiredAttributes = trendyolAttributes.filter(attr => attr.required);
                        const optionalAttributes = trendyolAttributes.filter(attr => !attr.required);
                        const sortedAttributes = [...requiredAttributes, ...optionalAttributes];
                        
                        let html = '<div class="attributes-list">';
                        sortedAttributes.forEach(function(attr) {
                            const attrId = attr.attribute.id;
                            const attrName = attr.attribute.name;
                            const isRequired = attr.required;
                            
                            html += `<div class="attribute-item ${isRequired ? 'required-attribute' : ''}">
                                <div class="attribute-info">
                                    <span class="attribute-name">${attrName}</span>
                                    ${isRequired ? '<span class="required-badge">Zorunlu</span>' : ''}
                                </div>
                                <div class="attribute-mapping">
                                    <select class="wc-attribute-select" data-category-id="${categoryId}" data-attribute-id="${attrId}" data-nonce="${$(this).data('nonce')}">
                                        <option value=""><?php echo esc_js(__('WooCommerce niteliği seçin', 'trendyol-woocommerce')); ?></option>`;
                                        
                            wcAttributes.forEach(function(wcAttr) {
                                html += `<option value="${wcAttr.slug}">${wcAttr.name}</option>`;
                            });
                                        
                            html += `</select>
                                    <button class="button save-attribute-mapping" data-category-id="${categoryId}" data-attribute-id="${attrId}">
                                        <?php echo esc_js(__('Kaydet', 'trendyol-woocommerce')); ?>
                                    </button>
                                </div>
                            </div>`;
                        });
                        html += '</div>';
                        
                        attributesContent.html(html);
                    } else {
                        attributesContent.html(`<p class="error">${response.data.message}</p>`);
                    }
                },
                error: function() {
                    attributesRow.find('.attributes-loading').hide();
                    attributesContent.html(`<p class="error"><?php echo esc_js(__('Nitelikler yüklenirken bir hata oluştu.', 'trendyol-woocommerce')); ?></p>`);
                }
            });
        } else {
            attributesRow.show();
        }
    });

    // Nitelik eşleştirmesini kaydet
    $(document).on('click', '.save-attribute-mapping', function() {
        const categoryId = $(this).data('category-id');
        const attributeId = $(this).data('attribute-id');
        const wcAttributeId = $(this).prev('.wc-attribute-select').val();
        
        if (!wcAttributeId) {
            alert('<?php echo esc_js(__('Lütfen önce bir WooCommerce niteliği seçin.', 'trendyol-woocommerce')); ?>');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_map_category_attribute',
                category_id: categoryId,
                attribute_id: attributeId,
                wc_attribute_id: wcAttributeId,
                nonce: $(this).prev('.wc-attribute-select').data('nonce')
            },
            beforeSend: function() {
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Kaydediliyor...', 'trendyol-woocommerce')); ?>');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Kaydet butonunu güncelle
                    $(this).prop('disabled', false).text('<?php echo esc_js(__('Kaydet', 'trendyol-woocommerce')); ?>');
                } else {
                    alert(response.data.message);
                    $(this).prop('disabled', false).text('<?php echo esc_js(__('Kaydet', 'trendyol-woocommerce')); ?>');
                }
            }.bind(this),
            error: function() {
                alert('<?php echo esc_js(__('Nitelik eşleştirmesi kaydedilirken bir hata oluştu.', 'trendyol-woocommerce')); ?>');
                $(this).prop('disabled', false).text('<?php echo esc_js(__('Kaydet', 'trendyol-woocommerce')); ?>');
            }.bind(this)
        });
    });
});
</script>

<style>
/* Genel stil ayarları */
.trendyol-wc-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
}

.trendyol-wc-card-header {
    border-bottom: 1px solid #eee;
    padding: 12px 15px;
}

.trendyol-wc-card-header h2 {
    margin: 0;
    font-size: 16px;
}

.trendyol-wc-card-body {
    padding: 15px;
}

/* Kategori arama stilleri */
.trendyol-category-search-container,
.wc-category-search-container {
    margin-bottom: 20px;
}

.trendyol-search-field,
.wc-search-field {
    position: relative;
    margin-bottom: 15px;
}

.trendyol-search-results,
.wc-search-results {
    position: absolute;
    z-index: 100;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #ddd;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    display: none;
}

.trendyol-search-results ul,
.wc-search-results ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.trendyol-search-results li,
.wc-search-results li {
    padding: 8px 10px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
}

.trendyol-search-results li:hover,
.wc-search-results li:hover {
    background: #f5f5f5;
}

.category-id {
    color: #999;
    font-size: 12px;
}

/* Seçili öğe stilleri */
.trendyol-selected-category-container,
.wc-selected-categories-container {
    margin-top: 15px;
    border: 1px solid #eee;
    padding: 10px;
    background: #f9f9f9;
}

.trendyol-selected-category-container h4,
.wc-selected-categories-container h4 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 14px;
}

.selected-item,
.wc-selected-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px solid #ddd;
    padding: 8px 10px;
    margin-bottom: 5px;
    border-radius: 3px;
}

.wc-selected-item {
    display: inline-flex;
    margin-right: 5px;
    margin-bottom: 5px;
}

.remove-item {
    background: none;
    border: none;
    color: #a00;
    cursor: pointer;
    font-size: 18px;
    padding: 0 5px;
    margin-left: 10px;
}

.remove-item:hover {
    color: #dc3232;
}

/* Eşleştirilmiş kategoriler tablosu stilleri */
.trendyol-wc-table {
    border-collapse: collapse;
    width: 100%;
}

.trendyol-wc-table th {
    text-align: left;
}

.category-id-display {
    color: #999;
    font-size: 12px;
    margin-top: 3px;
}

.wc-categories-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.wc-categories-list li {
    margin-bottom: 3px;
}

/* Nitelik stilleri */
.attributes-row {
    background: #f9f9f9;
}

.attributes-container {
    padding: 15px !important;
}

.attributes-loading {
    text-align: center;
    padding: 20px;
}

.attributes-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.attribute-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 3px;
}

.attribute-item.required-attribute {
    border-left: 3px solid #dc3232;
}

.attribute-name {
    font-weight: bold;
}

.required-badge {
    display: inline-block;
    margin-left: 10px;
    padding: 2px 6px;
    background: #dc3232;
    color: #fff;
    font-size: 11px;
    border-radius: 3px;
}

.attribute-mapping {
    display: flex;
    align-items: center;
    gap: 5px;
}

.wc-attribute-select {
    width: 200px;
}

.no-results,
.no-attributes,
.error {
    padding: 10px;
    background: #fff8e5;
    border-left: 4px solid #ffb900;
}

.error {
    border-left-color: #dc3232;
}

@media (min-width: 782px) {
    .attributes-list {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
