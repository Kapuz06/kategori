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
     * Trendyol kategorilerini veritabanına kaydet
     *
     * @return array|WP_Error Sonuç veya hata
     */
    public function sync_categories_to_database() {
        global $wpdb;

        // Trendyol'dan kategorileri getir
        $response = $this->get_categories();
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $categories = isset($response['categories']) ? $response['categories'] : array();
        
        if (empty($categories)) {
            return new WP_Error('no_categories', __('Trendyol\'dan kategori alınamadı.', 'trendyol-woocommerce'));
        }
        
        // Kategori tablosunu oluştur veya güncelle
        $this->create_categories_table();
        
        // Tüm kategorileri ekle
        $table_name = $wpdb->prefix . 'trendyol_categories';
        $inserted = 0;
        $updated = 0;
        
        foreach ($categories as $category) {
            $category_id = isset($category['id']) ? $category['id'] : 0;
            $category_name = isset($category['name']) ? $category['name'] : '';
            $parent_id = isset($category['parentId']) ? $category['parentId'] : 0;
            $level = isset($category['level']) ? $category['level'] : 0;
            
            if (empty($category_id) || empty($category_name)) {
                continue;
            }
            
            // Mevcut kategori kontrolü
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE id = %d",
                    $category_id
                )
            );
            
            if ($existing) {
                // Güncelle
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'name' => $category_name,
                        'parent_id' => $parent_id,
                        'level' => $level,
                        'last_updated' => current_time('mysql')
                    ),
                    array('id' => $category_id),
                    array('%s', '%d', '%d', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $updated++;
                }
            } else {
                // Yeni ekle
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'id' => $category_id,
                        'name' => $category_name,
                        'parent_id' => $parent_id,
                        'level' => $level,
                        'last_updated' => current_time('mysql')
                    ),
                    array('%d', '%s', '%d', '%d', '%s')
                );
                
                if ($result !== false) {
                    $inserted++;
                }
            }
        }
        
        // Son güncelleme zamanını kaydet
        update_option('trendyol_categories_last_sync', current_time('mysql'));
        
        return array(
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($categories)
        );
    }
    
    /**
     * Trendyol kategorileri veritabanı tablosunu oluştur
     */
    public function create_categories_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendyol_categories';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            parent_id BIGINT(20) NOT NULL DEFAULT 0,
            level INT(11) NOT NULL DEFAULT 0,
            last_updated DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY name (name),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Kategori öznitelik eşleştirme tablosunu oluştur
        $attributes_table = $wpdb->prefix . 'trendyol_category_attributes';
        
        $sql = "CREATE TABLE IF NOT EXISTS $attributes_table (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            category_id BIGINT(20) NOT NULL,
            trendyol_attribute_id BIGINT(20) NOT NULL,
            trendyol_attribute_name VARCHAR(255) NOT NULL,
            wc_attribute_id VARCHAR(255) DEFAULT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            last_updated DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_attribute (category_id, trendyol_attribute_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Kategori eşleştirme tablosunu oluştur
        $mappings_table = $wpdb->prefix . 'trendyol_category_mappings';
        
        $sql = "CREATE TABLE IF NOT EXISTS $mappings_table (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            trendyol_category_id BIGINT(20) NOT NULL,
            wc_category_id BIGINT(20) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY wc_category_id (wc_category_id),
            KEY trendyol_category_id (trendyol_category_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Kategori arama
     *
     * @param string $search_term Arama terimi
     * @param int $limit Maksimum sonuç sayısı
     * @return array Bulunan kategoriler
     */
    public function search_categories_from_database($search_term, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendyol_categories';
        
        // Arama terimini hazırla
        $search_pattern = '%' . $wpdb->esc_like($search_term) . '%';
        
        // Veritabanında ara
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, parent_id, level FROM $table_name 
                WHERE name LIKE %s OR id = %s 
                ORDER BY level ASC, name ASC LIMIT %d",
                $search_pattern,
                $search_term, // Direk kategori ID araması için
                $limit
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Kategori niteliklerini getir ve veritabanına kaydet
     *
     * @param int $category_id Kategori ID
     * @return array|WP_Error Kategori nitelikleri veya hata
     */
    public function get_and_save_category_attributes($category_id) {
        global $wpdb;
        
        // Kategori niteliklerini API'dan getir
        $response = $this->get_category_attributes($category_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // API yanıt yapısını kontrol et ve uyarla
        $category_attributes = array();
        
        if (isset($response['categoryAttributes'])) {
            $category_attributes = $response['categoryAttributes'];
        } elseif (isset($response['attributes'])) {
            $category_attributes = $response['attributes'];
        }
        
        if (empty($category_attributes)) {
            return array();
        }
        
        // Veritabanı tablosuna kaydet
        $table_name = $wpdb->prefix . 'trendyol_category_attributes';
        $inserted = 0;
        $updated = 0;
        
        foreach ($category_attributes as $attribute) {
            // Temel öznitelik bilgilerini çıkar
            $attr_id = isset($attribute['attribute']['id']) ? $attribute['attribute']['id'] : 0;
            $attr_name = isset($attribute['attribute']['name']) ? $attribute['attribute']['name'] : '';
            $is_required = isset($attribute['required']) ? (int)$attribute['required'] : 0;
            
            if (empty($attr_id) || empty($attr_name)) {
                continue;
            }
            
            // Mevcut öznitelik kontrolü
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE category_id = %d AND trendyol_attribute_id = %d",
                    $category_id, $attr_id
                )
            );
            
            if ($existing) {
                // Güncelle
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'trendyol_attribute_name' => $attr_name,
                        'is_required' => $is_required,
                        'last_updated' => current_time('mysql')
                    ),
                    array(
                        'category_id' => $category_id,
                        'trendyol_attribute_id' => $attr_id
                    ),
                    array('%s', '%d', '%s'),
                    array('%d', '%d')
                );
                
                if ($result !== false) {
                    $updated++;
                }
            } else {
                // Yeni ekle
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'category_id' => $category_id,
                        'trendyol_attribute_id' => $attr_id,
                        'trendyol_attribute_name' => $attr_name,
                        'is_required' => $is_required,
                        'last_updated' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%d', '%s')
                );
                
                if ($result !== false) {
                    $inserted++;
                }
            }
        }
        
        // Öznitelik değerlerini JSON olarak ayrıca saklayalım
        update_option('trendyol_category_' . $category_id . '_attributes', $response);
        
        return array(
            'attributes' => $category_attributes,
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => count($category_attributes)
        );
    }
    
    /**
     * Kategori nitelikleri için WooCommerce nitelik eşleştirmesini kaydet
     *
     * @param int $category_id Trendyol Kategori ID
     * @param int $trendyol_attribute_id Trendyol Nitelik ID
     * @param string $wc_attribute_id WooCommerce Nitelik ID
     * @return bool İşlem başarılı mı
     */
    public function save_attribute_mapping($category_id, $trendyol_attribute_id, $wc_attribute_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trendyol_category_attributes';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'wc_attribute_id' => $wc_attribute_id,
                'last_updated' => current_time('mysql')
            ),
            array(
                'category_id' => $category_id,
                'trendyol_attribute_id' => $trendyol_attribute_id
            ),
            array('%s', '%s'),
            array('%d', '%d')
        );
        
        return ($result !== false);
    }
    
    /**
     * Kategori eşleştirmelerini kaydet
     *
     * @param int $trendyol_category_id Trendyol Kategori ID
     * @param array $wc_category_ids WooCommerce Kategori ID'leri
     * @return array İşlem sonucu
     */
    public function save_category_mappings($trendyol_category_id, $wc_category_ids) {
        global $wpdb;
        
        $mappings_table = $wpdb->prefix . 'trendyol_category_mappings';
        
        // Önce bu Trendyol kategorisi için tüm mevcut eşlemeleri kaldır
        $wpdb->delete(
            $mappings_table,
            array('trendyol_category_id' => $trendyol_category_id),
            array('%d')
        );
        
        $inserted = 0;
        
        // Yeni eşlemeleri ekle
        foreach ($wc_category_ids as $wc_category_id) {
            if (empty($wc_category_id)) {
                continue;
            }
            
            $result = $wpdb->insert(
                $mappings_table,
                array(
                    'trendyol_category_id' => $trendyol_category_id,
                    'wc_category_id' => $wc_category_id
                ),
                array('%d', '%d')
            );
            
            if ($result !== false) {
                // Trendyol kategori ID'sini meta veri olarak kaydet
                update_term_meta($wc_category_id, '_trendyol_category_id', $trendyol_category_id);
                $inserted++;
            }
        }
        
        // Kategori eşleşmelerini eski format için de güncelle (geriye uyumluluk)
        $this->update_legacy_category_mappings();
        
        return array(
            'inserted' => $inserted,
            'total' => count($wc_category_ids)
        );
    }
    
    /**
     * Eski format kategori eşleşmelerini güncelle (geriye uyumluluk)
     *
     * @return bool İşlem başarılı mı
     */
    public function update_legacy_category_mappings() {
        global $wpdb;
        
        $mappings_table = $wpdb->prefix . 'trendyol_category_mappings';
        
        // Yeni format eşleşmeleri al
        $mappings = $wpdb->get_results(
            "SELECT trendyol_category_id, wc_category_id FROM $mappings_table",
            ARRAY_A
        );
        
        // Eski format eşleşme dizisini oluştur
        $legacy_mappings = array();
        foreach ($mappings as $mapping) {
            $legacy_mappings[$mapping['wc_category_id']] = $mapping['trendyol_category_id'];
        }
        
        // Eski format eşleşmeleri kaydet
        update_option('trendyol_wc_category_mappings', $legacy_mappings);
        
        return true;
    }
    
    /**
     * Kategori eşleştirmelerini getir
     *
     * @param int $trendyol_category_id Trendyol Kategori ID (opsiyonel)
     * @return array Eşleştirmeler
     */
    public function get_category_mappings($trendyol_category_id = null) {
        global $wpdb;
        
        $mappings_table = $wpdb->prefix . 'trendyol_category_mappings';
        
        if ($trendyol_category_id) {
            // Belirli bir Trendyol kategorisi için eşleşmeleri getir
            $mappings = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT wc_category_id FROM $mappings_table WHERE trendyol_category_id = %d",
                    $trendyol_category_id
                ),
                ARRAY_A
            );
            
            $wc_category_ids = array();
            foreach ($mappings as $mapping) {
                $wc_category_ids[] = $mapping['wc_category_id'];
            }
            
            return $wc_category_ids;
        } else {
            // Tüm eşleşmeleri getir
            return $wpdb->get_results(
                "SELECT * FROM $mappings_table ORDER BY trendyol_category_id ASC",
                ARRAY_A
            );
        }
    }
    
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
        
    /**
     * Kategori özelliklerini getir
     *
     * @param int $category_id Kategori ID
     * @return array|WP_Error Kategori özellikleri veya hata
     */
    public function get_category_attributes($category_id) {
        // Yeni endpoint'i kullan
        return $this->get("integration/product/product-categories/{$category_id}/attributes");
    }
}
