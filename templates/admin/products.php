<?php
/**
 * Trendyol WooCommerce - Products Template
 *
 * Ürünler sayfası şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap trendyol-wc-products">
    <h1><?php echo esc_html__('Trendyol Ürün Yönetimi', 'trendyol-woocommerce'); ?></h1>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Trendyol'dan Toplu Ürün Aktarımı -->
    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header">
            <h2><?php echo esc_html__('Trendyol\'dan Toplu Ürün Aktarımı', 'trendyol-woocommerce'); ?></h2>
        </div>
        <div class="trendyol-wc-card-body">
            <form id="trendyol-bulk-import-form">
                <div class="trendyol-wc-form-row">
                    <label for="bulk_import_size"><?php echo esc_html__('Sayfa Başına Ürün:', 'trendyol-woocommerce'); ?></label>
                    <input type="number" id="bulk_import_size" name="bulk_import_size" value="10" min="1" max="100" class="small-text">
                </div>
                <div class="trendyol-wc-form-row">
                    <label for="bulk_import_skip_existing">
                        <input type="checkbox" id="bulk_import_skip_existing" name="bulk_import_skip_existing" value="1">
                        <?php echo esc_html__('Mevcut ürünleri atla', 'trendyol-woocommerce'); ?>
                    </label>
                </div>
                <div class="trendyol-wc-form-actions">
                    <button type="button" id="start-bulk-import" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo esc_html__('Toplu Ürün Aktarımını Başlat', 'trendyol-woocommerce'); ?>
                    </button>
                </div>
            </form>
            
            <div id="bulk-import-progress" style="display:none; margin-top: 15px;">
                <div class="progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 4px; overflow: hidden;">
                    <div id="bulk-import-progress-bar" style="height: 100%; width: 0%; background-color: #0073aa; transition: width 0.3s;"></div>
                </div>
                <p id="bulk-import-status"><?php echo esc_html__('Hazırlanıyor...', 'trendyol-woocommerce'); ?></p>
            </div>
            
            <div id="bulk-import-results" style="display:none; margin-top: 15px;">
                <h3><?php echo esc_html__('Aktarım Sonuçları', 'trendyol-woocommerce'); ?></h3>
                <div id="bulk-import-summary"></div>
                <div id="bulk-import-errors" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Toplu Ürün Aktarımı
        $('#start-bulk-import').on('click', function() {
            var size = $('#bulk_import_size').val();
            var skipExisting = $('#bulk_import_skip_existing').is(':checked') ? 1 : 0;
            
            // İlerleme çubuğunu göster
            $('#bulk-import-progress').show();
            $('#bulk-import-results').hide();
            $('#bulk-import-progress-bar').css('width', '0%');
            $('#bulk-import-status').text('<?php echo esc_js(__('Ürünler aktarılıyor...', 'trendyol-woocommerce')); ?>');
            
            // İşlemi başlat
            bulkImportProducts(0, size, skipExisting, {
                imported: 0,
                updated: 0,
                skipped: 0,
                errors: []
            });
        });
        
        function bulkImportProducts(page, size, skipExisting, totals) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'trendyol_bulk_import_products',
                    nonce: trendyol_wc_params.nonce,
                    page: page,
                    size: size,
                    skip_existing: skipExisting
                },
                success: function(response) {
                    if (response.success) {
                        // Sayaçları güncelle
                        totals.imported += response.data.imported;
                        totals.updated += response.data.updated;
                        totals.skipped += response.data.skipped;
                        
                        // Hataları ekle
                        if (response.data.errors && response.data.errors.length > 0) {
                            totals.errors = totals.errors.concat(response.data.errors);
                        }
                        
                        // İlerleme çubuğunu güncelle
                        var currentPage = page + 1;
                        var totalPages = response.data.total_pages || 1;
                        var progress = Math.min(Math.round((currentPage / totalPages) * 100), 100);
                        
                        $('#bulk-import-progress-bar').css('width', progress + '%');
                        $('#bulk-import-status').text('<?php echo esc_js(__('Sayfa', 'trendyol-woocommerce')); ?> ' + currentPage + '/' + totalPages + ' - ' + 
                                                     '<?php echo esc_js(__('Aktarıldı:', 'trendyol-woocommerce')); ?> ' + totals.imported + ', ' + 
                                                     '<?php echo esc_js(__('Güncellendi:', 'trendyol-woocommerce')); ?> ' + totals.updated + ', ' + 
                                                     '<?php echo esc_js(__('Atlandı:', 'trendyol-woocommerce')); ?> ' + totals.skipped);
                        
                        // Daha fazla sayfa varsa devam et
                        if (page < totalPages - 1) {
                            bulkImportProducts(page + 1, size, skipExisting, totals);
                        } else {
                            // İşlem tamamlandı
                            $('#bulk-import-status').text('<?php echo esc_js(__('İşlem tamamlandı!', 'trendyol-woocommerce')); ?>');
                            
                            // Sonuçları göster
                            $('#bulk-import-summary').html(
                                '<p><strong><?php echo esc_js(__('Toplam Aktarılan:', 'trendyol-woocommerce')); ?></strong> ' + totals.imported + '</p>' +
                                '<p><strong><?php echo esc_js(__('Toplam Güncellenen:', 'trendyol-woocommerce')); ?></strong> ' + totals.updated + '</p>' +
                                '<p><strong><?php echo esc_js(__('Toplam Atlanan:', 'trendyol-woocommerce')); ?></strong> ' + totals.skipped + '</p>'
                            );
                            
                            // Hataları göster
                            if (totals.errors.length > 0) {
                                var errorHtml = '<h4><?php echo esc_js(__('Hatalar:', 'trendyol-woocommerce')); ?></h4><ul>';
                                for (var i = 0; i < totals.errors.length; i++) {
                                    errorHtml += '<li>' + totals.errors[i] + '</li>';
                                }
                                errorHtml += '</ul>';
                                $('#bulk-import-errors').html(errorHtml);
                            }
                            
                            $('#bulk-import-results').show();
                        }
                    } else {
                        // Hata durumu
                        $('#bulk-import-status').text('<?php echo esc_js(__('Hata oluştu!', 'trendyol-woocommerce')); ?>');
                        $('#bulk-import-errors').html('<p class="error">' + (response.data.message || '<?php echo esc_js(__('Beklenmeyen bir hata oluştu.', 'trendyol-woocommerce')); ?>') + '</p>');
                        $('#bulk-import-results').show();
                    }
                },
                error: function() {
                    // AJAX hatası
                    $('#bulk-import-status').text('<?php echo esc_js(__('Bağlantı hatası!', 'trendyol-woocommerce')); ?>');
                    $('#bulk-import-errors').html('<p class="error"><?php echo esc_js(__('Sunucu ile bağlantı kurulamadı. Lütfen tekrar deneyin.', 'trendyol-woocommerce')); ?></p>');
                    $('#bulk-import-results').show();
                }
            });
        }
    });
    </script>
    <div class="trendyol-wc-card">
        <div class="trendyol-wc-card-header nav-tab-wrapper">
            <a href="#trendyol-products" class="nav-tab nav-tab-active"><?php echo esc_html__('Trendyol Ürünleri', 'trendyol-woocommerce'); ?></a>
            <a href="#woocommerce-products" class="nav-tab"><?php echo esc_html__('WooCommerce Ürünleri', 'trendyol-woocommerce'); ?></a>
        </div>
        <div class="trendyol-wc-card-body">
            <!-- Trendyol Ürünleri Tab -->
            <div id="trendyol-products" class="trendyol-product-tab">
                <div class="trendyol-wc-search-box">
                    <input type="text" id="trendyol-product-search" placeholder="<?php echo esc_attr__('Ürün ara...', 'trendyol-woocommerce'); ?>" class="regular-text">
                    <button type="button" id="clear-trendyol-cache" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span> <?php echo esc_html__('Önbelleği Temizle', 'trendyol-woocommerce'); ?>
                    </button>
                    <span class="spinner" id="trendyol-products-spinner" style="float:none;"></span>
                </div>
                
                <div class="tablenav top">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><span id="trendyol-products-count">0</span> <?php echo esc_html__('ürün', 'trendyol-woocommerce'); ?></span>
                        <span class="pagination-links">
                            <a class="first-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('İlk sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">«</span></a>
                            <a class="prev-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Önceki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">‹</span></a>
                            <span class="paging-input">
                                <label for="trendyol-current-page" class="screen-reader-text"><?php echo esc_html__('Mevcut sayfa', 'trendyol-woocommerce'); ?></label>
                                <input class="current-page" id="trendyol-current-page" type="text" name="paged" value="1" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> / <span class="total-pages" id="trendyol-total-pages">1</span></span>
                            </span>
                            <a class="next-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Sonraki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">›</span></a>
                            <a class="last-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Son sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">»</span></a>
                        </span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped trendyol-wc-products-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Ürün', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Barkod', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol ID', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Fiyat', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Stok', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="trendyol-products-list">
                        <tr>
                            <td colspan="6"><?php echo esc_html__('Ürünler yükleniyor...', 'trendyol-woocommerce'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><span id="trendyol-products-count-bottom">0</span> <?php echo esc_html__('ürün', 'trendyol-woocommerce'); ?></span>
                        <span class="pagination-links">
                            <a class="first-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('İlk sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">«</span></a>
                            <a class="prev-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Önceki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">‹</span></a>
                            <span class="paging-input">
                                <label for="trendyol-current-page-bottom" class="screen-reader-text"><?php echo esc_html__('Mevcut sayfa', 'trendyol-woocommerce'); ?></label>
                                <input class="current-page" id="trendyol-current-page-bottom" type="text" name="paged" value="1" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> / <span class="total-pages" id="trendyol-total-pages-bottom">1</span></span>
                            </span>
                            <a class="next-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Sonraki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">›</span></a>
                            <a class="last-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Son sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">»</span></a>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- WooCommerce Ürünleri Tab -->
            <div id="woocommerce-products" class="trendyol-product-tab" style="display:none;">
                <div class="trendyol-wc-search-box">
                    <input type="text" id="wc-product-search" placeholder="<?php echo esc_attr__('Ürün ara...', 'trendyol-woocommerce'); ?>" class="regular-text">
                    <span class="spinner" id="wc-products-spinner" style="float:none;"></span>
                </div>
                
                <div class="tablenav top">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><span id="wc-products-count">0</span> <?php echo esc_html__('ürün', 'trendyol-woocommerce'); ?></span>
                        <span class="pagination-links">
                            <a class="first-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('İlk sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">«</span></a>
                            <a class="prev-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Önceki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">‹</span></a>
                            <span class="paging-input">
                                <label for="wc-current-page" class="screen-reader-text"><?php echo esc_html__('Mevcut sayfa', 'trendyol-woocommerce'); ?></label>
                                <input class="current-page" id="wc-current-page" type="text" name="paged" value="1" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> / <span class="total-pages" id="wc-total-pages">1</span></span>
                            </span>
                            <a class="next-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Sonraki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">›</span></a>
                            <a class="last-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Son sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">»</span></a>
                        </span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped trendyol-wc-products-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Ürün', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('SKU', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Trendyol ID', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Fiyat', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Stok', 'trendyol-woocommerce'); ?></th>
                            <th><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wc-products-list">
                        <tr>
                            <td colspan="6"><?php echo esc_html__('Ürünler yükleniyor...', 'trendyol-woocommerce'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><span id="wc-products-count-bottom">0</span> <?php echo esc_html__('ürün', 'trendyol-woocommerce'); ?></span>
                        <span class="pagination-links">
                            <a class="first-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('İlk sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">«</span></a>
                            <a class="prev-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Önceki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">‹</span></a>
                            <span class="paging-input">
                                <label for="wc-current-page-bottom" class="screen-reader-text"><?php echo esc_html__('Mevcut sayfa', 'trendyol-woocommerce'); ?></label>
                                <input class="current-page" id="wc-current-page-bottom" type="text" name="paged" value="1" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> / <span class="total-pages" id="wc-total-pages-bottom">1</span></span>
                            </span>
                            <a class="next-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Sonraki sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">›</span></a>
                            <a class="last-page button" href="#"><span class="screen-reader-text"><?php echo esc_html__('Son sayfa', 'trendyol-woocommerce'); ?></span><span aria-hidden="true">»</span></a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ürün Eşleştirme Modal -->
    <div id="trendyol-match-product-modal" class="trendyol-modal" style="display:none;">
        <div class="trendyol-modal-content">
            <span class="trendyol-modal-close">&times;</span>
            <h3><?php echo esc_html__('Ürün Eşleştirme', 'trendyol-woocommerce'); ?></h3>
            <div class="trendyol-modal-body">
                <div class="trendyol-wc-search-box">
                    <input type="text" id="match-product-search" placeholder="<?php echo esc_attr__('Eşleştirilecek ürünü ara...', 'trendyol-woocommerce'); ?>" class="regular-text">
                </div>
                <div class="trendyol-match-product-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Ürün', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('SKU/Barkod', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('Fiyat', 'trendyol-woocommerce'); ?></th>
                                <th><?php echo esc_html__('İşlem', 'trendyol-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="match-products-list">
                            <tr>
                                <td colspan="4"><?php echo esc_html__('Arama yapmak için yukarıdaki kutuyu kullanın.', 'trendyol-woocommerce'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tab ve Modal Stilleri */
.trendyol-product-tab {
    padding: 15px 0;
}

.trendyol-wc-card-header.nav-tab-wrapper {
    margin-bottom: 0;
    padding-bottom: 0;
}

.trendyol-wc-search-box {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
}

.trendyol-wc-search-box .spinner {
    margin-left: 10px;
    visibility: hidden;
}

.trendyol-wc-search-box .spinner.is-active {
    visibility: visible;
}

/* Modal Stilleri */
.trendyol-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.trendyol-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.trendyol-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.trendyol-modal-close:hover,
.trendyol-modal-close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

.trendyol-modal-body {
    max-height: 500px;
    overflow-y: auto;
}

.trendyol-match-product-list {
    margin-top: 15px;
}

/* Buton stilleri */
.trendyol-action-button {
    margin-right: 5px !important;
}

/* Sayfalama Stilleri */
.tablenav-pages .current-page {
    width: 30px;
    text-align: center;
}
</style>

<script>
// products.php dosyasındaki mevcut JavaScript kodunun yerine:
jQuery(document).ready(function($) {
    // Trendyol ürünlerini saklayacak değişken
    var trendyolProducts = [];
    var filteredProducts = [];
    var currentPage = 1;
    var productsPerPage = 10;
    var totalPages = 1;
    
    // Tab Geçişleri
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        
        // Tab butonlarını güncelle
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Tab içeriklerini güncelle
        $('.trendyol-product-tab').hide();
        $($(this).attr('href')).show();
        
        // İlk yükleme
        if ($(this).attr('href') === '#trendyol-products' && $('#trendyol-products-list tr').length <= 1) {
            loadAllTrendyolProducts();
        } else if ($(this).attr('href') === '#woocommerce-products' && $('#wc-products-list tr').length <= 1) {
            loadWooCommerceProducts(1);
        }
    });
    
    // Tüm Trendyol ürünlerini yükle ve yerel depolama alanına kaydet
    function loadAllTrendyolProducts() {
        $('#trendyol-products-spinner').addClass('is-active');
        $('#trendyol-products-list').html('<tr><td colspan="6">Tüm ürünler yükleniyor, bu işlem biraz zaman alabilir...</td></tr>');
        
        // Yerel depolamada ürünler var mı kontrol et
        var storedProducts = localStorage.getItem('trendyolProducts');
        var lastUpdate = localStorage.getItem('trendyolProductsLastUpdate');
        
        // Son güncelleme 1 saat içindeyse, yerel depolamadan al
        if (storedProducts && lastUpdate && (new Date().getTime() - parseInt(lastUpdate) < 3600000)) {
            trendyolProducts = JSON.parse(storedProducts);
            filteredProducts = [...trendyolProducts];
            $('#trendyol-products-spinner').removeClass('is-active');
            updatePaginationInfo();
            displayTrendyolProducts();
            return;
        }
        
        // Tüm ürünleri almak için recursive fonksiyon
        function fetchAllProducts(page = 1, allProducts = []) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'trendyol_get_products',
                    nonce: trendyol_wc_params.nonce,
                    page: page,
                    size: 100
                },
                success: function(response) {
                    if (response.success) {
                        var products = response.data.products || [];
                        
                        // Her ürünün is_matched özelliğini API'den aldığımızdan emin olalım
                        products.forEach(function(product) {
                            // Eğer API bu özelliği göndermiyorsa, varsayılan olarak false yapmalıyız
                            if (typeof product.is_matched === 'undefined') {
                                product.is_matched = false;
                            }
                        });
                        
                        allProducts = allProducts.concat(products);
                        
                        // İlerleme durumunu göster
                        var progressMsg = 'Sayfa ' + page + '/' + response.data.total_pages + ' yükleniyor (' + allProducts.length + ' ürün)...';
                        $('#trendyol-products-list').html('<tr><td colspan="6">' + progressMsg + '</td></tr>');
                        
                        // Daha fazla sayfa varsa, sonraki sayfayı al
                        if (page < response.data.total_pages && page < 10) { // Maximum 10 sayfa ile sınırla (1000 ürün)
                            fetchAllProducts(page + 1, allProducts);
                        } else {
                            // Tüm ürünler alındı, kaydet ve göster
                            trendyolProducts = allProducts;
                            filteredProducts = [...trendyolProducts];
                            
                            // Yerel depolamaya kaydet
                            try {
                                localStorage.setItem('trendyolProducts', JSON.stringify(trendyolProducts));
                                localStorage.setItem('trendyolProductsLastUpdate', new Date().getTime().toString());
                            } catch (e) {
                                console.warn('Ürünler yerel depolamaya kaydedilemedi:', e);
                            }
                            
                            $('#trendyol-products-spinner').removeClass('is-active');
                            updatePaginationInfo();
                            displayTrendyolProducts();
                        }
                    } else {
                        $('#trendyol-products-spinner').removeClass('is-active');
                        $('#trendyol-products-list').html('<tr><td colspan="6">' + response.data + '</td></tr>');
                    }
                },
                error: function() {
                    $('#trendyol-products-spinner').removeClass('is-active');
                    $('#trendyol-products-list').html('<tr><td colspan="6">' + trendyol_wc_params.i18n.ajax_error + '</td></tr>');
                }
            });
        }
        
        // İlk sayfadan başla
        fetchAllProducts();
    }
    
    // Trendyol ürünlerini listele
    function displayTrendyolProducts() {
        var startIndex = (currentPage - 1) * productsPerPage;
        var endIndex = startIndex + productsPerPage;
        var displayProducts = filteredProducts.slice(startIndex, endIndex);
        
        $('#trendyol-products-list').html('');
        
        if (displayProducts.length === 0) {
            $('#trendyol-products-list').html('<tr><td colspan="6">' + trendyol_wc_params.i18n.no_products + '</td></tr>');
            return;
        }
        
        $.each(displayProducts, function(index, product) {
            var matchButtonHtml = '';
            if (product.is_matched === true) {
                // Eğer eşleştirilmişse, "Eşlendi" metni göster
                matchButtonHtml = '<button type="button" class="button trendyol-action-button" disabled>' +
                    '<span class="dashicons dashicons-yes"></span> ' + trendyol_wc_params.i18n.matched +
                '</button>';
            } else {
                // Eşleştirilmemişse, "Eşle" butonu göster - Burada ürün ID'sini kullanıyoruz (barcode değil)
                matchButtonHtml = '<button type="button" class="button trendyol-action-button trendyol-match-button" data-product-id="' + product.id + '" data-source="trendyol">' +
                    '<span class="dashicons dashicons-admin-links"></span> ' + trendyol_wc_params.i18n.match +
                '</button>';
            }
            var row = '<tr>' +
                '<td>' + product.title + '</td>' +
                '<td>' + product.barcode + '</td>' +
                '<td>' + product.id + '</td>' +
                '<td>' + formatPrice(product.salePrice) + '</td>' +
                '<td>' + product.quantity + '</td>' +
                '<td>' +
                    // Burada da import butonunda ürün ID'sini kullanıyoruz
                    '<button type="button" class="button trendyol-action-button trendyol-import-button" data-product-id="' + product.id + '" data-source="trendyol">' +
                        '<span class="dashicons dashicons-upload"></span> ' + trendyol_wc_params.i18n.import +
                    '</button>' +
                    matchButtonHtml +
                '</td>' +
            '</tr>';
            
            $('#trendyol-products-list').append(row);
        });
    }
    
    // Arama sonuçlarını filtrele
    function filterTrendyolProducts(searchTerm) {
        if (!searchTerm || searchTerm.trim() === '') {
            filteredProducts = [...trendyolProducts];
        } else {
            searchTerm = searchTerm.toLowerCase();
            filteredProducts = trendyolProducts.filter(function(product) {
                return (
                    (product.title && product.title.toLowerCase().includes(searchTerm)) ||
                    (product.barcode && product.barcode.toLowerCase().includes(searchTerm)) ||
                    (product.id && product.id.toString().includes(searchTerm))
                );
            });
        }
        
        currentPage = 1; // Arama yapıldığında ilk sayfaya dön
        updatePaginationInfo();
        displayTrendyolProducts();
    }
    
    // Sayfalama bilgilerini güncelle
    function updatePaginationInfo() {
        totalPages = Math.ceil(filteredProducts.length / productsPerPage);
        if (totalPages === 0) totalPages = 1;
        
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        
        var prefix = '#trendyol';
        $(prefix + '-current-page, ' + prefix + '-current-page-bottom').val(currentPage);
        $(prefix + '-total-pages, ' + prefix + '-total-pages-bottom').text(totalPages);
        $(prefix + '-products-count, ' + prefix + '-products-count-bottom').text(filteredProducts.length);
    }
    
    // Fiyat formatla
    function formatPrice(price) {
        return parseFloat(price).toFixed(2) + ' ₺';
    }
    
    // Trendyol Arama - hızlı arama için yerel filtreleme kullan
    var trendyolSearchTimer;
    $('#trendyol-product-search').on('keyup', function() {
        clearTimeout(trendyolSearchTimer);
        trendyolSearchTimer = setTimeout(function() {
            filterTrendyolProducts($('#trendyol-product-search').val());
        }, 300);
    });
    
    // Yerel veri önbelleğini temizle
    $('#clear-trendyol-cache').on('click', function() {
        localStorage.removeItem('trendyolProducts');
        localStorage.removeItem('trendyolProductsLastUpdate');
        loadAllTrendyolProducts();
    });
    
    // WooCommerce Arama - bu kısmı değiştirmedik
    var wcSearchTimer;
    $('#wc-product-search').on('keyup', function() {
        clearTimeout(wcSearchTimer);
        wcSearchTimer = setTimeout(function() {
            loadWooCommerceProducts(1, $('#wc-product-search').val());
        }, 500);
    });
    
    // WooCommerce Ürünlerini Yükle - bu kısmı değiştirmedik
    function loadWooCommerceProducts(page, search = '') {
        $('#wc-products-spinner').addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_get_wc_products',
                nonce: trendyol_wc_params.nonce,
                page: page,
                search: search
            },
            success: function(response) {
                $('#wc-products-spinner').removeClass('is-active');
                
                if (response.success) {
                    // Tablo içeriğini güncelle
                    $('#wc-products-list').html('');
                    
                    if (response.data.products.length === 0) {
                        $('#wc-products-list').html('<tr><td colspan="6">' + trendyol_wc_params.i18n.no_products + '</td></tr>');
                    } else {
                        $.each(response.data.products, function(index, product) {
                            var trendyolId = product.trendyol_id ? product.trendyol_id : '-';
                            var matchButtonHtml = '';
                            if (product.is_matched) {
                                // Eğer eşleştirilmişse, "Eşlendi" metni göster
                                matchButtonHtml = '<button type="button" class="button trendyol-action-button" disabled>' +
                                    '<span class="dashicons dashicons-yes"></span> ' + trendyol_wc_params.i18n.matched +
                                '</button>';
                            } else {
                                // Eşleştirilmemişse, "Eşle" butonu göster
                                matchButtonHtml = '<button type="button" class="button trendyol-action-button trendyol-match-button" data-product-id="' + product.id + '" data-source="woocommerce">' +
                                    '<span class="dashicons dashicons-admin-links"></span> ' + trendyol_wc_params.i18n.match +
                                '</button>';
                            }
                            var row = '<tr>' +
                                '<td><a href="' + product.edit_link + '">' + product.name + '</a></td>' +
                                '<td>' + product.sku + '</td>' +
                                '<td>' + trendyolId + '</td>' +
                                '<td>' + formatPrice(product.price) + '</td>' +
                                '<td>' + product.stock_quantity + '</td>' +
                                '<td>' +
                                    '<button type="button" class="button trendyol-action-button trendyol-export-button" data-product-id="' + product.id + '" data-source="woocommerce">' +
                                        '<span class="dashicons dashicons-download"></span> ' + trendyol_wc_params.i18n.export +
                                    '</button>' +
                                    matchButtonHtml +
                                '</td>' +
                            '</tr>';
                            
                            $('#wc-products-list').append(row);
                        });
                    }
                    
                    // Sayfalama bilgilerini güncelle - WooCommerce için
                    var prefix = '#wc';
                    $(prefix + '-current-page, ' + prefix + '-current-page-bottom').val(response.data.current_page);
                    $(prefix + '-total-pages, ' + prefix + '-total-pages-bottom').text(response.data.total_pages);
                    $(prefix + '-products-count, ' + prefix + '-products-count-bottom').text(response.data.total_products);
                } else {
                    $('#wc-products-list').html('<tr><td colspan="6">' + response.data + '</td></tr>');
                }
            },
            error: function() {
                $('#wc-products-spinner').removeClass('is-active');
                $('#wc-products-list').html('<tr><td colspan="6">' + trendyol_wc_params.i18n.ajax_error + '</td></tr>');
            }
        });
    }
    
    // Trendyol Sayfalama İşlemleri - Yerel veri ile çalışacak şekilde uyarlandı
    $(document).on('click', '#trendyol-products .first-page', function(e) {
        e.preventDefault();
        currentPage = 1;
        updatePaginationInfo();
        displayTrendyolProducts();
    });
    
    $(document).on('click', '#trendyol-products .prev-page', function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            updatePaginationInfo();
            displayTrendyolProducts();
        }
    });
    
    $(document).on('click', '#trendyol-products .next-page', function(e) {
        e.preventDefault();
        if (currentPage < totalPages) {
            currentPage++;
            updatePaginationInfo();
            displayTrendyolProducts();
        }
    });
    
    $(document).on('click', '#trendyol-products .last-page', function(e) {
        e.preventDefault();
        currentPage = totalPages;
        updatePaginationInfo();
        displayTrendyolProducts();
    });
    
    // Sayfa girişi - Trendyol - Yerel veri için uyarlandı
    $('#trendyol-current-page, #trendyol-current-page-bottom').on('keydown', function(e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            
            var page = parseInt($(this).val());
            
            if (page > 0 && page <= totalPages) {
                currentPage = page;
                updatePaginationInfo();
                displayTrendyolProducts();
            } else {
                $(this).val(currentPage);
            }
        }
    });
    
    // Sayfalama İşlemleri - WooCommerce - Bu kısmı değiştirmedik
    $(document).on('click', '#woocommerce-products .first-page', function(e) {
        e.preventDefault();
        loadWooCommerceProducts(1, $('#wc-product-search').val());
    });
    
    $(document).on('click', '#woocommerce-products .prev-page', function(e) {
        e.preventDefault();
        var currentPage = parseInt($('#wc-current-page').val());
        if (currentPage > 1) {
            loadWooCommerceProducts(currentPage - 1, $('#wc-product-search').val());
        }
    });
    
    $(document).on('click', '#woocommerce-products .next-page', function(e) {
        e.preventDefault();
        var currentPage = parseInt($('#wc-current-page').val());
        var totalPages = parseInt($('#wc-total-pages').text());
        if (currentPage < totalPages) {
            loadWooCommerceProducts(currentPage + 1, $('#wc-product-search').val());
        }
    });
    
    $(document).on('click', '#woocommerce-products .last-page', function(e) {
        e.preventDefault();
        var totalPages = parseInt($('#wc-total-pages').text());
        loadWooCommerceProducts(totalPages, $('#wc-product-search').val());
    });
    
    // Sayfa girişi - WooCommerce - Bu kısmı değiştirmedik
    $('#wc-current-page, #wc-current-page-bottom').on('keydown', function(e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            
            var page = parseInt($(this).val());
            var totalPages = parseInt($('#wc-total-pages').text());
            
            if (page > 0 && page <= totalPages) {
                loadWooCommerceProducts(page, $('#wc-product-search').val());
            } else {
                $(this).val($('#wc-current-page').val());
            }
        }
    });
    
    // Trendyol ürünü import/aktar butonunu güncelle
    $(document).on('click', '.trendyol-import-button', function() {
        var productId = $(this).data('product-id');
        var button = $(this);
        
        button.prop('disabled', true);
        button.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');
        
        // Önbellekteki ürünler arasından istenen ürünü bul
        var productData = null;
        for (var i = 0; i < trendyolProducts.length; i++) {
            if (trendyolProducts[i].id == productId) {
                productData = trendyolProducts[i];
                break;
            }
        }
        
        if (!productData) {
            button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
            alert('Ürün verisi önbellekte bulunamadı!');
            setTimeout(function() {
                button.html('<span class="dashicons dashicons-upload"></span> ' + trendyol_wc_params.i18n.import);
                button.prop('disabled', false);
            }, 2000);
            return;
        }
        
        // Önbellekteki veriyi göndermeden önce konsola yazdır (Debug için)
        console.log("Önbellekten gönderilecek ürün verisi:", productData);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_import_cached_product',
                nonce: trendyol_wc_params.nonce,
                cached_product: JSON.stringify(productData)
            },
            success: function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes"></span> ' + trendyol_wc_params.i18n.imported);
                    setTimeout(function() {
                        button.html('<span class="dashicons dashicons-upload"></span> ' + trendyol_wc_params.i18n.import);
                        button.prop('disabled', false);
                    }, 2000);
                } else {
                    button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                    alert(response.data);
                    setTimeout(function() {
                        button.html('<span class="dashicons dashicons-upload"></span> ' + trendyol_wc_params.i18n.import);
                        button.prop('disabled', false);
                    }, 2000);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX hatası:", error);
                button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                alert(trendyol_wc_params.i18n.ajax_error);
                setTimeout(function() {
                    button.html('<span class="dashicons dashicons-upload"></span> ' + trendyol_wc_params.i18n.import);
                    button.prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // Aktarma İşlemi - WooCommerce'den Trendyol'a
    $(document).on('click', '.trendyol-export-button', function() {
        var productId = $(this).data('product-id');
        var button = $(this);
        
        button.prop('disabled', true);
        button.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_export_product',
                nonce: trendyol_wc_params.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes"></span> ' + trendyol_wc_params.i18n.exported);
                    setTimeout(function() {
                        button.html('<span class="dashicons dashicons-download"></span> ' + trendyol_wc_params.i18n.export);
                        button.prop('disabled', false);
                    }, 2000);
                } else {
                    button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                    setTimeout(function() {
                        button.html('<span class="dashicons dashicons-download"></span> ' + trendyol_wc_params.i18n.export);
                        button.prop('disabled', false);
                    }, 2000);
                    
                    alert(response.data);
                }
            },
            error: function() {
                button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                setTimeout(function() {
                    button.html('<span class="dashicons dashicons-download"></span> ' + trendyol_wc_params.i18n.export);
                    button.prop('disabled', false);
                }, 2000);
                
                alert(trendyol_wc_params.i18n.ajax_error);
            }
        });
    });
    
    // Eşleştirme Modalı
    var matchSourceId = '';
    var matchSourceType = '';
    
    $(document).on('click', '.trendyol-match-button', function() {
        matchSourceId = $(this).data('product-id');
        matchSourceType = $(this).data('source');
        
        // Modalı aç
        $('#trendyol-match-product-modal').show();
        $('#match-product-search').val('').focus();
        $('#match-products-list').html('<tr><td colspan="4">' + trendyol_wc_params.i18n.search_to_match + '</td></tr>');
    });
    
    // Modal Kapatma
    $('.trendyol-modal-close').on('click', function() {
        $('#trendyol-match-product-modal').hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('.trendyol-modal')) {
            $('.trendyol-modal').hide();
        }
    });
    
    // Eşleştirilecek Ürün Arama
    var matchSearchTimer;
    $('#match-product-search').on('keyup', function() {
        clearTimeout(matchSearchTimer);
        
        var searchTerm = $(this).val();
        
        if (searchTerm.length < 3) {
            $('#match-products-list').html('<tr><td colspan="4">' + trendyol_wc_params.i18n.search_min_chars + '</td></tr>');
            return;
        }
        
        $('#match-products-list').html('<tr><td colspan="4"><span class="spinner is-active" style="float:none;"></span> ' + trendyol_wc_params.i18n.searching + '</td></tr>');
        
        matchSearchTimer = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: matchSourceType === 'trendyol' ? 'trendyol_search_wc_products' : 'trendyol_search_trendyol_products',
                    nonce: trendyol_wc_params.nonce,
                    search: searchTerm
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.products.length === 0) {
                            $('#match-products-list').html('<tr><td colspan="4">' + trendyol_wc_params.i18n.no_match_found + '</td></tr>');
                        } else {
                            $('#match-products-list').html('');
                            
                            $.each(response.data.products, function(index, product) {
                                var row = '<tr>' +
                                    '<td>' + product.name + '</td>' +
                                    '<td>' + (product.sku || product.barcode) + '</td>' +
                                    '<td>' + formatPrice(product.price || product.salePrice) + '</td>' +
                                    '<td>' +
                                        '<button type="button" class="button trendyol-match-product-button" data-product-id="' + product.id + '">' +
                                            trendyol_wc_params.i18n.match +
                                        '</button>' +
                                    '</td>' +
                                '</tr>';
                                
                                $('#match-products-list').append(row);
                            });
                        }
                    } else {
                        $('#match-products-list').html('<tr><td colspan="4">' + response.data + '</td></tr>');
                    }
                },
                error: function() {
                    $('#match-products-list').html('<tr><td colspan="4">' + trendyol_wc_params.i18n.ajax_error + '</td></tr>');
                }
            });
        }, 500);
    });
    
    // Ürün Eşleştirme İşlemi
    $(document).on('click', '.trendyol-match-product-button', function() {
        var targetId = $(this).data('product-id');
        var button = $(this);
        
        button.prop('disabled', true);
        button.html('<span class="spinner is-active" style="float:none;margin:0;"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'trendyol_match_products',
                nonce: trendyol_wc_params.nonce,
                source_id: matchSourceId,
                source_type: matchSourceType,
                target_id: targetId
            },
            success: function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes"></span> ' + trendyol_wc_params.i18n.matched);
                    
                    setTimeout(function() {
                        $('#trendyol-match-product-modal').hide();
                        
                        // Eşleştirilmiş olarak ürün durumunu güncelle
                        if (matchSourceType === 'trendyol') {
                            // Trendyol ürünlerini güncelle - burada barcode yerine ID kullanıyoruz
                            for (var i = 0; i < trendyolProducts.length; i++) {
                                if (trendyolProducts[i].id == matchSourceId) { // == kullanıyoruz çünkü matchSourceId string olabilir
                                    trendyolProducts[i].is_matched = true;
                                    break;
                                }
                            }
                            
                            // Filtrelenmiş ürünleri de güncelle
                            for (var i = 0; i < filteredProducts.length; i++) {
                                if (filteredProducts[i].id == matchSourceId) {
                                    filteredProducts[i].is_matched = true;
                                    break;
                                }
                            }
                            
                            // Yerel depolamayı güncelle
                            try {
                                localStorage.setItem('trendyolProducts', JSON.stringify(trendyolProducts));
                            } catch (e) {
                                console.warn('Ürünler yerel depolamaya kaydedilemedi:', e);
                            }
                            
                            // Listeyi güncelle
                            displayTrendyolProducts();
                        } else {
                            // WooCommerce ürünlerini güncelle
                            loadWooCommerceProducts(parseInt($('#wc-current-page').val()), $('#wc-product-search').val());
                        }
                    }, 1000);
                } else {
                    button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                    setTimeout(function() {
                        button.html(trendyol_wc_params.i18n.match);
                        button.prop('disabled', false);
                    }, 2000);
                    
                    alert(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX hatası:", error);
                button.html('<span class="dashicons dashicons-no"></span> ' + trendyol_wc_params.i18n.error);
                setTimeout(function() {
                    button.html(trendyol_wc_params.i18n.match);
                    button.prop('disabled', false);
                }, 2000);
                
                alert(trendyol_wc_params.i18n.ajax_error);
            }
        });
    });
    
    // İlk yükleme - sayfa yüklendiğinde otomatik olarak Trendyol ürünlerini yükle
    loadAllTrendyolProducts();
});
</script>