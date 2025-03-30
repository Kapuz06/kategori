<?php
/**
 * Trendyol Kategoriler API Sınıfı
 * 
 * Trendyol kategori API işlemleri için sınıf
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

class Trendyol_WC_Categories_API extends Trendyol_WC_API {

    
    /**
     * Tüm kategorileri getir
     *
     * @return array|WP_Error Kategori listesi veya hata
     */
    public function get_categories() {
        // Yeni endpoint kullanılıyor
        $response = $this->get("integration/product/product-categories");
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Kategorileri düzleştir
        $flat_categories = [];
        if (isset($response['categories'])) {
            $this->flatten_category_tree($response['categories'], $flat_categories);
        }
        
        return [
            'categories' => $flat_categories,
            'original_tree' => isset($response['categories']) ? $response['categories'] : []
        ];
    }
    
    /**
     * Kategori ağacını düz bir listeye dönüştür (recursive)
     *
     * @param array $categories Kategori ağacı
     * @param array &$result Sonuç dizisi (referans)
     * @param int $level Kategori seviyesi
     * @return void
     */
    private function flatten_category_tree($categories, &$result, $level = 0) {
        if (!is_array($categories)) {
            return;
        }
        
        foreach ($categories as $category) {
            // Kategori verisini düz listeye ekle
            $result[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'parentId' => isset($category['parentId']) ? $category['parentId'] : 0,
                'level' => $level, // Kategori seviyesini tutuyoruz
                'has_children' => !empty($category['subCategories'])
            ];
            
            // Alt kategorileri işle
            if (!empty($category['subCategories'])) {
                $this->flatten_category_tree($category['subCategories'], $result, $level + 1);
            }
        }
    }
        
    public function get_category_attributes($category_id) {
        // Yeni endpoint'i kullan
        return $this->get("integration/product/product-categories/{$category_id}/attributes");
    }



    

    /**
     * Kategoriyi WooCommerce uyumlu formata dönüştür
     *
     * @param array $trendyol_category Trendyol kategori verisi
     * @param array $parent_ids Üst kategori ID'leri (isteğe bağlı)
     * @return array WooCommerce kategori verisi
     */
    public function format_category_for_woocommerce($trendyol_category, $parent_ids = array()) {
        // Kategori adı
        $name = isset($trendyol_category['name']) ? $trendyol_category['name'] : '';
        
        // Kategori ID
        $category_id = isset($trendyol_category['id']) ? $trendyol_category['id'] : 0;
        
        // Ana kategori kontrolü
        $parent_id = 0;
        if (!empty($parent_ids) && isset($trendyol_category['parentId'])) {
            $parent_trendyol_id = $trendyol_category['parentId'];
            if (isset($parent_ids[$parent_trendyol_id])) {
                $parent_id = $parent_ids[$parent_trendyol_id];
            }
        }
        
        // WooCommerce kategori verisini oluştur
        $wc_category = array(
            'name' => $name,
            'slug' => sanitize_title($name),
            'description' => '',
            'parent' => $parent_id,
            'meta_data' => array(
                array(
                    'key' => '_trendyol_category_id',
                    'value' => $category_id
                )
            )
        );
        
        return $wc_category;
    }
    
    /**
     * Trendyol kategorilerini WooCommerce'e içe aktar
     *
     * @param bool $create_if_not_exists Yoksa kategori oluştur
     * @return array İçe aktarılan kategoriler
     */
    public function import_categories_to_woocommerce($create_if_not_exists = false) {
        // Trendyol kategorilerini getir
        $categories_response = $this->get_categories();
        
        if (is_wp_error($categories_response)) {
            return $categories_response;
        }
        
        // Kategoriler
        $categories = isset($categories_response['categories']) ? $categories_response['categories'] : array();
        
        if (empty($categories)) {
            return new WP_Error('no_categories', __('Trendyol\'dan kategori alınamadı.', 'trendyol-woocommerce'));
        }
        
        // Mevcut kategori eşleşmeleri
        $category_mappings = get_option('trendyol_wc_category_mappings', array());
        
        // Kategori hiyerarşisini oluştur
        $category_tree = array();
        $imported_ids = array();
        
        // Kategorileri parent ID'lerine göre grupla
        foreach ($categories as $category) {
            $parent_id = isset($category['parentId']) ? $category['parentId'] : 0;
            if (!isset($category_tree[$parent_id])) {
                $category_tree[$parent_id] = array();
            }
            $category_tree[$parent_id][] = $category;
        }
        
        // Önce tüm üst kategorileri içe aktar
        if (isset($category_tree[0])) {
            foreach ($category_tree[0] as $parent_category) {
                $trendyol_category_id = $parent_category['id'];
                
                // Kategori zaten eşleştirilmiş mi kontrol et
                if (array_search($trendyol_category_id, $category_mappings) !== false) {
                    $wc_category_id = array_search($trendyol_category_id, $category_mappings);
                    $imported_ids[$trendyol_category_id] = $wc_category_id;
                    continue;
                }
                
                // Kategori adıyla mevcut bir WooCommerce kategorisi ara
                $existing_term = get_term_by('name', $parent_category['name'], 'product_cat');
                
                if ($existing_term) {
                    // Mevcut kategoriye Trendyol ID meta verisi ekle
                    update_term_meta($existing_term->term_id, '_trendyol_category_id', $trendyol_category_id);
                    
                    // Eşleşme ekle
                    $category_mappings[$existing_term->term_id] = $trendyol_category_id;
                    $imported_ids[$trendyol_category_id] = $existing_term->term_id;
                } elseif ($create_if_not_exists) {
                    // Yeni kategori oluştur
                    $wc_category = $this->format_category_for_woocommerce($parent_category);
                    
                    $term_result = wp_insert_term(
                        $wc_category['name'],
                        'product_cat',
                        array(
                            'description' => $wc_category['description'],
                            'slug' => $wc_category['slug'],
                            'parent' => $wc_category['parent']
                        )
                    );
                    
                    if (!is_wp_error($term_result)) {
                        $wc_category_id = $term_result['term_id'];
                        
                        // Trendyol ID meta verisi ekle
                        update_term_meta($wc_category_id, '_trendyol_category_id', $trendyol_category_id);
                        
                        // Eşleşme ekle
                        $category_mappings[$wc_category_id] = $trendyol_category_id;
                        $imported_ids[$trendyol_category_id] = $wc_category_id;
                    }
                }
            }
        }
        
        // Sonra alt kategorileri seviye seviye içe aktar
        $max_depth = 5; // Maksimum kategori derinliği
        
        for ($depth = 1; $depth < $max_depth; $depth++) {
            foreach ($imported_ids as $trendyol_parent_id => $wc_parent_id) {
                if (isset($category_tree[$trendyol_parent_id])) {
                    foreach ($category_tree[$trendyol_parent_id] as $child_category) {
                        $trendyol_category_id = $child_category['id'];
                        
                        // Kategori zaten eşleştirilmiş mi kontrol et
                        if (array_search($trendyol_category_id, $category_mappings) !== false) {
                            $wc_category_id = array_search($trendyol_category_id, $category_mappings);
                            $imported_ids[$trendyol_category_id] = $wc_category_id;
                            
                            // Ebeveyn kontrolü ve güncelleme
                            $term = get_term($wc_category_id, 'product_cat');
                            if ($term && !is_wp_error($term) && $create_if_not_exists) {
                                // Ebeveyn farklıysa güncelle
                                if ($term->parent != $wc_parent_id) {
                                    wp_update_term($wc_category_id, 'product_cat', array(
                                        'parent' => $wc_parent_id
                                    ));
                                }
                            }
                            
                            continue;
                        }
                        
                        // Kategori adı ve ebeveyn ile mevcut bir WooCommerce kategorisi ara
                        $existing_terms = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'name' => $child_category['name'],
                            'parent' => $wc_parent_id,
                            'hide_empty' => false
                        ));
                        
                        if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
                            $existing_term = reset($existing_terms);
                            
                            // Mevcut kategoriye Trendyol ID meta verisi ekle
                            update_term_meta($existing_term->term_id, '_trendyol_category_id', $trendyol_category_id);
                            
                            // Eşleşme ekle
                            $category_mappings[$existing_term->term_id] = $trendyol_category_id;
                            $imported_ids[$trendyol_category_id] = $existing_term->term_id;
                        } elseif ($create_if_not_exists) {
                            // Yeni kategori oluştur
                            $term_result = wp_insert_term(
                                $child_category['name'],
                                'product_cat',
                                array(
                                    'description' => '',
                                    'slug' => sanitize_title($child_category['name']),
                                    'parent' => $wc_parent_id
                                )
                            );
                            
                            if (!is_wp_error($term_result)) {
                                $wc_category_id = $term_result['term_id'];
                                
                                // Trendyol ID meta verisi ekle
                                update_term_meta($wc_category_id, '_trendyol_category_id', $trendyol_category_id);
                                
                                // Eşleşme ekle
                                $category_mappings[$wc_category_id] = $trendyol_category_id;
                                $imported_ids[$trendyol_category_id] = $wc_category_id;
                            }
                        }
                    }
                }
            }
        }
        
        // Kategori eşleşmelerini güncelle
        update_option('trendyol_wc_category_mappings', $category_mappings);
        
        return $category_mappings;
    }
    
    /**
     * Kategori özelliklerini WooCommerce ürün özelliklerine dönüştür
     *
     * @param int $category_id Trendyol kategori ID
     * @return array WooCommerce ürün özellikleri
     */
    public function import_category_attributes_to_woocommerce($category_id) {
        // Kategori özelliklerini getir
        $attributes_response = $this->get_category_attributes($category_id);
        
        if (is_wp_error($attributes_response)) {
            return $attributes_response;
        }
        
        // API yanıt yapısını kontrol et ve uyarla
        $category_attributes = array();
        
        if (isset($attributes_response['categoryAttributes'])) {
            $category_attributes = $attributes_response['categoryAttributes'];
        } elseif (isset($attributes_response['attributes'])) {
            $category_attributes = $attributes_response['attributes'];
        }
        
        if (empty($category_attributes)) {
            return array();
        }
        
        $wc_attributes = array();
        
        foreach ($category_attributes as $attribute) {
            $attribute_id = isset($attribute['id']) ? $attribute['id'] : 0;
            $attribute_name = isset($attribute['name']) ? $attribute['name'] : '';
            
            // Zorunlu alan kontrolü
            $is_required = isset($attribute['required']) ? (bool) $attribute['required'] : false;
            
            // Varyant özelliği kontrolü
            $is_variant = isset($attribute['allowedForVariant']) ? (bool) $attribute['allowedForVariant'] : false;
            
            // Değerler
            $attribute_values = isset($attribute['attributeValues']) ? $attribute['attributeValues'] : array();
            $values = array();
            
            foreach ($attribute_values as $value) {
                $values[] = isset($value['name']) ? $value['name'] : '';
            }
            
            $wc_attributes[] = array(
                'id' => $attribute_id,
                'name' => $attribute_name,
                'slug' => sanitize_title($attribute_name),
                'required' => $is_required,
                'variant' => $is_variant,
                'values' => $values
            );
        }
        
        return $wc_attributes;
    }
    
    /**
     * Kategori özelliklerini WooCommerce özniteliklerine (attribute) dönüştür
     *
     * @param array $attributes Özellikler
     * @return array Oluşturulan WooCommerce öznitelik taksonomi adları
     */
    public function create_wc_product_attributes($attributes) {
        if (empty($attributes)) {
            return array();
        }
        
        $created_attributes = array();
        
        foreach ($attributes as $attribute) {
            $attribute_name = isset($attribute['name']) ? $attribute['name'] : '';
            $attribute_slug = isset($attribute['slug']) ? $attribute['slug'] : sanitize_title($attribute_name);
            $attribute_id = isset($attribute['id']) ? $attribute['id'] : 0;
            
            if (empty($attribute_name)) {
                continue;
            }
            
            // Taksonomi adı oluştur
            $taxonomy_name = 'pa_' . $attribute_slug;
            
            // Öznitelik mevcut mu kontrol et
            if (!taxonomy_exists($taxonomy_name)) {
                // Yeni öznitelik oluştur
                wc_create_attribute(array(
                    'name' => $attribute_name,
                    'slug' => $attribute_slug,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false
                ));
                
                // Taksonomi kaydet
                register_taxonomy(
                    $taxonomy_name,
                    'product',
                    array(
                        'label' => $attribute_name,
                        'rewrite' => array('slug' => $attribute_slug),
                        'hierarchical' => true
                    )
                );
            }
            
            // Trendyol öznitelik ID meta verisini ekle
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            
            foreach ($attribute_taxonomies as $tax) {
                if ($tax->attribute_name === $attribute_slug) {
                    update_option('_trendyol_attribute_id_' . $tax->attribute_id, $attribute_id);
                    break;
                }
            }
            
            // Değerleri ekle
            if (!empty($attribute['values'])) {
                foreach ($attribute['values'] as $value) {
                    if (empty($value)) {
                        continue;
                    }
                    
                    if (!term_exists($value, $taxonomy_name)) {
                        wp_insert_term($value, $taxonomy_name);
                    }
                }
            }
            
            $created_attributes[] = $taxonomy_name;
        }
        
        return $created_attributes;
    }
}