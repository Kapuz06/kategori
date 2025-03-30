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

// Kategori ağacını oluşturmak için recursive fonksiyon
function display_category_tree($categories, $category_mappings, $parent_id = 0, $level = 0) {
    $html = '';
    
    foreach ($categories as $category) {
        if (isset($category['parentId']) && $category['parentId'] == $parent_id) {
            $category_id = $category['id'];
            $has_children = isset($category['has_children']) ? $category['has_children'] : false;
            $wc_category_id = array_search($category_id, $category_mappings);
            
            $html .= '<li class="trendyol-category-item' . ($has_children ? ' has-children' : '') . '" data-id="' . esc_attr($category_id) . '" data-name="' . esc_attr($category['name']) . '">';
            $html .= '<div class="trendyol-category-data">';
            $html .= '<span class="trendyol-category-toggle">' . ($has_children ? '+' : '&nbsp;') . '</span>';
            $html .= '<span class="trendyol-category-name">' . esc_html($category['name']) . '</span>';
            $html .= '<span class="trendyol-category-id">ID: ' . esc_html($category_id) . '</span>';
            
            if ($wc_category_id) {
                $wc_term = get_term($wc_category_id, 'product_cat');
                if ($wc_term && !is_wp_error($wc_term)) {
                    $html .= '<span class="trendyol-category-mapping">';
                    $html .= __('Eşleşme: ', 'trendyol-woocommerce');
                    $html .= '<a href="' . esc_url(get_edit_term_link($wc_term->term_id, 'product_cat')) . '">' . esc_html($wc_term->name) . '</a>';
                    $html .= '</span>';
                }
            } else {
                $html .= '<span class="trendyol-category-mapping no-mapping">';
                $html .= __('Eşleşme yok', 'trendyol-woocommerce');
                $html .= '</span>';
            }
            
            // Eşleştirme formu
            $html .= '<form method="post" action="' . esc_url(admin_url('admin.php?page=trendyol-wc-categories')) . '" class="trendyol-category-mapping-form">';
            $html .= wp_nonce_field('trendyol_map_category', '_wpnonce', true, false);
            $html .= '<input type="hidden" name="trendyol_category_id" value="' . esc_attr($category_id) . '">';
            
            $html .= '<select name="wc_category_id[]" class="trendyol-wc-category-select" multiple="multiple" size="4">';
            $html .= '<option value="">' . __('-- WooCommerce kategorisi seç --', 'trendyol-woocommerce') . '</option>';
            
            $wc_categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false
            ));
            
            if (!is_wp_error($wc_categories) && !empty($wc_categories)) {
                foreach ($wc_categories as $wc_category) {
                    $selected = ($wc_category_id && $wc_category_id == $wc_category->term_id) ? 'selected' : '';
                    $html .= '<option value="' . esc_attr($wc_category->term_id) . '" ' . $selected . '>' . esc_html($wc_category->name) . '</option>';
                }
            }
            
            $html .= '</select>';
            $html .= '<input type="hidden" name="trendyol_map_multiple_categories" value="1">';
            $html .= '<button type="submit" class="button button-small">' . __('Eşle', 'trendyol-woocommerce') . '</button>';
            $html .= '</form>';
            
            $html .= '</div>';
            
            // Alt kategoriler var mı kontrol et
            $child_categories = array_filter($categories, function($child) use ($category_id) {
                return isset($child['parentId']) && $child['parentId'] == $category_id;
            });
            
            if (!empty($child_categories)) {
                $html .= '<ul class="trendyol-category-level-' . ($level + 1) . '" style="display:none;">';
                $html .= display_category_tree($categories, $category_mappings, $category_id, $level + 1);
                $html .= '</ul>';
            }
            
            $html .= '</li>';
        }
    }
    
    return $html;
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
            
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-categories')); ?>">
                <?php wp_nonce_field('trendyol_sync_categories'); ?>
                <div class="trendyol-wc-form-row">
                    <label>
                        <input type="checkbox" name="trendyol_create_categories" value="1">
                        <?php echo esc_html__('WooCommerce\'de eşleşmeyen kategorileri otomatik oluştur', 'trendyol-woocommerce'); ?>
                    </label>
                </div>
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

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Trendyol Kategori Ağacı', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <?php if (!empty($categories)) : ?>
                <div class="trendyol-wc-search-box">
                    <input type="text" id="trendyol-category-search" placeholder="<?php echo esc_attr__('Kategori ara...', 'trendyol-woocommerce'); ?>" class="regular-text">
                </div>
                
                <div class="trendyol-wc-category-tree">
                    <ul class="trendyol-category-level-0">
                        <?php echo display_category_tree($categories, $category_mappings); ?>
                    </ul>
                </div>
                
                <style>
                /* Kategori ağacı stilleri */
                .trendyol-wc-category-tree {
                    margin: 15px 0;
                    max-height: 600px;
                    overflow-y: auto;
                    padding: 10px;
                    background: #f9f9f9;
                    border: 1px solid #e5e5e5;
                }

                .trendyol-wc-category-tree ul {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }

                .trendyol-wc-category-tree li {
                    margin: 0;
                    padding: 3px 0;
                    position: relative;
                }

                .trendyol-category-level-0 > li {
                    border-bottom: 1px solid #eee;
                    margin-bottom: 5px;
                    padding-bottom: 5px;
                }

                .trendyol-category-level-1, 
                .trendyol-category-level-2, 
                .trendyol-category-level-3, 
                .trendyol-category-level-4, 
                .trendyol-category-level-5 {
                    margin-left: 20px !important;
                    border-left: 1px solid #ddd;
                    padding-left: 10px !important;
                }

                .trendyol-category-data {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 10px;
                    padding: 5px;
                    background: #fff;
                    border: 1px solid #e5e5e5;
                    border-radius: 3px;
                }

                .trendyol-category-toggle {
                    cursor: pointer;
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    text-align: center;
                    line-height: 16px;
                    background: #f0f0f0;
                    border-radius: 3px;
                    font-weight: bold;
                }

                .trendyol-category-name {
                    font-weight: bold;
                    min-width: 150px;
                }

                .trendyol-category-id {
                    color: #666;
                    font-size: 12px;
                }

                .trendyol-category-mapping {
                    padding: 2px 6px;
                    background: #eee;
                    border-radius: 3px;
                }

                .trendyol-category-mapping.no-mapping {
                    color: #a00;
                }

                .trendyol-category-mapping-form {
                    display: flex;
                    gap: 5px;
                    align-items: center;
                    margin-left: auto;
                }

                .trendyol-wc-category-select {
                    max-width: 200px;
                }
                
                .no-results {
                    padding: 10px;
                    background: #fff8e5;
                    border-left: 4px solid #ffb900;
                }
                
                .category-found {
                    background-color: #fffbcc !important;
                    border: 1px solid #ffb900 !important;
                }
                </style>
                
                <script>
                jQuery(document).ready(function($) {
                    // Kategori arama - geliştirilmiş sürüm
                    $('#trendyol-category-search').on('keyup', function() {
                        var value = $(this).val().toLowerCase();
                        
                        // Arama sonuçlarını sıfırla
                        $(".no-results").remove();
                        $(".trendyol-category-item").removeClass('category-found');
                        
                        // Arama yoksa tüm ağacı normal haline getir
                        if (value === '') {
                            $(".trendyol-category-item").show();
                            $(".trendyol-category-level-1, .trendyol-category-level-2, .trendyol-category-level-3, .trendyol-category-level-4, .trendyol-category-level-5").hide();
                            $(".trendyol-category-toggle").text('+');
                            return;
                        }
                        
                        // Önce tüm kategorileri gizle
                        $(".trendyol-category-item").hide();
                        
                        // Tüm kategorileri dolaş ve eşleşenleri bul
                        var found = false;
                        $(".trendyol-category-item").each(function() {
                            var categoryName = $(this).data('name').toLowerCase();
                            var categoryId = $(this).data('id').toString();
                            
                            if (categoryName.indexOf(value) > -1 || categoryId.indexOf(value) > -1) {
                                $(this).show().addClass('category-found');
                                // Üst kategorileri göster
                                $(this).parents('li').show();
                                $(this).parents('ul').show();
                                // Kategori toggle simgesini güncelle
                                $(this).find('> .trendyol-category-data .trendyol-category-toggle').text('-');
                                $(this).parents('li').find('> .trendyol-category-data .trendyol-category-toggle').text('-');
                                found = true;
                            }
                        });
                        
                        // Sonuç bulunamadı mesajı
                        if (!found) {
                            $(".trendyol-wc-category-tree").append('<p class="no-results">' + 
                                '<?php echo esc_js(__('Arama kriterinize uyan kategori bulunamadı.', 'trendyol-woocommerce')); ?>' + 
                            '</p>');
                        }
                    });
                    
                    // Kategori genişletme/daraltma
                    $(document).on('click', '.trendyol-category-toggle', function() {
                        var $item = $(this).closest('li');
                        var $children = $item.find('> ul');
                        
                        if ($children.is(':visible')) {
                            $children.slideUp(200);
                            $(this).text('+');
                        } else {
                            $children.slideDown(200);
                            $(this).text('-');
                        }
                    });
                });
                </script>
            <?php else : ?>
                <p><?php echo esc_html__('Trendyol kategorileri yüklenemedi. Lütfen API bağlantınızı kontrol edin.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Mevcut Kategori Eşleşmeleri', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <?php if (!empty($category_mappings)) : ?>
                <table class="wp-list-table widefat fixed striped trendyol-wc-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('WooCommerce Kategori', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol Kategori', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol Kategori ID', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_mappings as $wc_id => $trendyol_id) : 
                            $wc_term = get_term($wc_id, 'product_cat');
                            if (!$wc_term || is_wp_error($wc_term)) {
                                continue;
                            }
                            
                            $trendyol_cat_name = '';
                            foreach ($categories as $category) {
                                if ($category['id'] == $trendyol_id) {
                                    $trendyol_cat_name = $category['name'];
                                    break;
                                }
                            }
                            
                            if (empty($trendyol_cat_name)) {
                                continue;
                            }
                        ?>
                            <tr>
                                <td><a href="<?php echo esc_url(get_edit_term_link($wc_term->term_id, 'product_cat')); ?>"><?php echo esc_html($wc_term->name); ?></a></td>
                                <td><?php echo esc_html($trendyol_cat_name); ?></td>
                                <td><?php echo esc_html($trendyol_id); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=trendyol-wc-categories')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('trendyol_unmap_category'); ?>
                                        <input type="hidden" name="wc_category_id" value="<?php echo esc_attr($wc_id); ?>">
                                        <input type="hidden" name="trendyol_unmap_category" value="1">
                                        <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Bu eşleşmeyi kaldırmak istediğinize emin misiniz?', 'trendyol-woocommerce')); ?>');">
                                            <span class="dashicons dashicons-no"></span> <?php echo esc_html__('Eşleşmeyi Kaldır', 'trendyol-woocommerce'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('Henüz kategori eşleşmesi bulunmuyor. Senkronizasyon işlemini başlatarak eşleşmeleri oluşturabilirsiniz.', 'trendyol-woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>