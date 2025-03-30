<?php
/**
 * Trendyol WooCommerce - Batch İstekleri Şablonu
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap trendyol-wc-batch-requests">
    <h1><?php echo esc_html__('Trendyol İşlem Durumları', 'trendyol-woocommerce'); ?></h1>
    
    <div class="trendyol-wc-batch-instructions">
        <p>
            <?php echo esc_html__('Bu sayfada Trendyol\'a yapılan toplu ürün ve stok/fiyat işlemlerinin durumunu takip edebilirsiniz.', 'trendyol-woocommerce'); ?>
            <?php echo esc_html__('İşlem detaylarını görmek için "Durumu Kontrol Et" butonuna tıklayabilirsiniz.', 'trendyol-woocommerce'); ?>
        </p>
    </div>
    
    <?php if (empty($batch_requests_paged)) : ?>
        <div class="trendyol-wc-no-batches">
            <p><?php echo esc_html__('Henüz kaydedilmiş işlem bulunmuyor.', 'trendyol-woocommerce'); ?></p>
        </div>
    <?php else : ?>
        <table class="trendyol-wc-batch-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php echo esc_html__('Batch ID', 'trendyol-woocommerce'); ?></th>
                    <th style="width: 20%;"><?php echo esc_html__('İşlem Tipi', 'trendyol-woocommerce'); ?></th>
                    <th style="width: 15%;"><?php echo esc_html__('Durum', 'trendyol-woocommerce'); ?></th>
                    <th style="width: 20%;"><?php echo esc_html__('Tarih', 'trendyol-woocommerce'); ?></th>
                    <th style="width: 20%;"><?php echo esc_html__('İşlemler', 'trendyol-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batch_requests_paged as $request) : 
                    $date_format = get_option('date_format') . ' ' . get_option('time_format');
                    $date = date_i18n($date_format, $request['date']);
                    
                    // Durum sınıfını belirle
                    $status_class = '';
                    switch ($request['status']) {
                        case 'COMPLETED':
                            $status_class = 'status-success';
                            break;
                        case 'PROCESSING':
                            $status_class = 'status-processing';
                            break;
                        case 'FAILED':
                            $status_class = 'status-failed';
                            break;
                        default:
                            $status_class = 'status-unknown';
                    }
                ?>
                <tr data-batch-id="<?php echo esc_attr($request['id']); ?>">
                    <td class="batch-id"><?php echo esc_html($request['id']); ?></td>
                    <td><?php echo esc_html($request['type']); ?></td>
                    <td class="batch-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($request['status']); ?></td>
                    <td><?php echo esc_html($date); ?></td>
                    <td>
                        <button class="button check-batch-status" data-batch-id="<?php echo esc_attr($request['id']); ?>">
                            <?php echo esc_html__('Durumu Kontrol Et', 'trendyol-woocommerce'); ?>
                        </button>
                        <button class="button delete-batch-request" data-batch-id="<?php echo esc_attr($request['id']); ?>">
                            <?php echo esc_html__('Sil', 'trendyol-woocommerce'); ?>
                        </button>
                    </td>
                </tr>
                <tr class="batch-details-row" style="display: none;">
                    <td colspan="5" class="batch-details-cell"></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Sayfalama
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            $page_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $current_page
            ));
            
            if ($page_links) {
                echo '<span class="pagination-links">' . $page_links . '</span>';
            }
            
            echo '</div></div>';
        }
        ?>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Batch durumunu kontrol et
                $('.trendyol-wc-batch-requests').on('click', '.check-batch-status', function() {
                    var $button = $(this);
                    var batchId = $button.data('batch-id');
                    var $row = $button.closest('tr');
                    var $detailsRow = $row.next('.batch-details-row');
                    var $detailsCell = $detailsRow.find('.batch-details-cell');
                    
                    // Detay satırını aç/kapat
                    if ($detailsRow.is(':visible')) {
                        $detailsRow.hide();
                        return;
                    }
                    
                    // Buton metnini değiştir
                    var originalText = $button.text();
                    $button.text('<?php echo esc_js(__('Kontrol ediliyor...', 'trendyol-woocommerce')); ?>').prop('disabled', true);
                    
                    // AJAX isteği ile batch durumunu kontrol et
                    $.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'trendyol_check_batch_status',
                            nonce: '<?php echo wp_create_nonce('trendyol-batch-nonce'); ?>',
                            batch_id: batchId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Durum hücresini güncelle
                                $row.find('.batch-status')
                                    .text(response.data.status)
                                    .removeClass('status-success status-processing status-failed status-unknown')
                                    .addClass(getStatusClass(response.data.status));
                                
                                // Detay içeriğini oluştur
                                var detailsHtml = createDetailsHtml(response.data);
                                $detailsCell.html(detailsHtml);
                                $detailsRow.show();
                            } else {
                                alert(response.data.message);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('İstek işlenirken bir hata oluştu.', 'trendyol-woocommerce')); ?>');
                        },
                        complete: function() {
                            $button.text(originalText).prop('disabled', false);
                        }
                    });
                });
                
                // Batch isteğini sil
                $('.trendyol-wc-batch-requests').on('click', '.delete-batch-request', function() {
                    var $button = $(this);
                    var batchId = $button.data('batch-id');
                    
                    if (!confirm('<?php echo esc_js(__('Bu işlem kaydını silmek istediğinizden emin misiniz?', 'trendyol-woocommerce')); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js(__('Siliniyor...', 'trendyol-woocommerce')); ?>');
                    
                    $.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'trendyol_delete_batch_request',
                            nonce: '<?php echo wp_create_nonce('trendyol-batch-nonce'); ?>',
                            batch_id: batchId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Satırları sil
                                $button.closest('tr').next('.batch-details-row').remove();
                                $button.closest('tr').remove();
                                
                                // Hiç satır kalmadıysa mesaj göster
                                if ($('.trendyol-wc-batch-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            } else {
                                alert(response.data.message);
                                $button.prop('disabled', false).text('<?php echo esc_js(__('Sil', 'trendyol-woocommerce')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('İstek işlenirken bir hata oluştu.', 'trendyol-woocommerce')); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js(__('Sil', 'trendyol-woocommerce')); ?>');
                        }
                    });
                });
                
                // Durum sınıfını al
                function getStatusClass(status) {
                    switch (status) {
                        case 'COMPLETED':
                            return 'status-success';
                        case 'PROCESSING':
                            return 'status-processing';
                        case 'FAILED':
                            return 'status-failed';
                        default:
                            return 'status-unknown';
                    }
                }
                
                // Detay HTML'ini oluştur
                function createDetailsHtml(data) {
                    var creationDate = new Date(parseInt(data.creationDate)).toLocaleString();
                    var lastModDate = new Date(parseInt(data.lastModification)).toLocaleString();
                    
                    var html = '<div class="trendyol-batch-details">';
                    
                    // Batch istatistikleri
                    html += '<div class="trendyol-batch-stats">';
                    html += '<div><strong><?php echo esc_js(__('İşlem Tipi:', 'trendyol-woocommerce')); ?></strong> ' + data.batchRequestType + '</div>';
                    html += '<div><strong><?php echo esc_js(__('Toplam Ürün:', 'trendyol-woocommerce')); ?></strong> ' + data.totalItems + '</div>';
                    html += '<div><strong><?php echo esc_js(__('Başarılı:', 'trendyol-woocommerce')); ?></strong> ' + data.successItems + '</div>';
                    html += '<div><strong><?php echo esc_js(__('Başarısız:', 'trendyol-woocommerce')); ?></strong> ' + data.failedItems + '</div>';
                    html += '</div>';
                    
                    html += '<div><strong><?php echo esc_js(__('Oluşturma Tarihi:', 'trendyol-woocommerce')); ?></strong> ' + creationDate + '</div>';
                    html += '<div><strong><?php echo esc_js(__('Son Güncelleme:', 'trendyol-woocommerce')); ?></strong> ' + lastModDate + '</div>';
                    
                    // Hata nedenleri
                    if (data.errorReasons && data.errorReasons.length > 0) {
                        html += '<div class="trendyol-batch-errors">';
                        html += '<h4><?php echo esc_js(__('Hata Nedenleri:', 'trendyol-woocommerce')); ?></h4>';
                        html += '<ul>';
                        
                        for (var i = 0; i < data.errorReasons.length; i++) {
                            html += '<li>' + data.errorReasons[i] + '</li>';
                        }
                        
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    return html;
                }
            });
        </script>
    <?php endif; ?>
</div>
