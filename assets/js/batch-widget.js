jQuery(document).ready(function($) {
    var $batchList = $('#trendyol-batch-list');
    
    // Batch durumunu kontrol et
    $batchList.on('click', '.check-batch-status', function() {
        var $button = $(this);
        var batchId = $button.data('batch-id');
        var $row = $button.closest('tr');
        
        // Daha önce eklenmiş detay paneli varsa kaldır
        if ($row.next('.batch-details-row').length) {
            $row.next('.batch-details-row').remove();
            return;
        }
        
        // Buton metnini değiştir
        var originalText = $button.text();
        $button.text(trendyol_batch.checking).prop('disabled', true);
        
        // AJAX isteği ile batch durumunu kontrol et
        $.ajax({
            type: 'POST',
            url: trendyol_batch.ajax_url,
            data: {
                action: 'trendyol_check_batch_status',
                nonce: trendyol_batch.nonce,
                batch_id: batchId
            },
            success: function(response) {
                if (response.success) {
                    // Durum hücresini güncelle
                    $row.find('.batch-status')
                        .text(response.data.status)
                        .removeClass('status-success status-processing status-failed status-unknown')
                        .addClass(response.data.statusClass);
                    
                    // Detay satırı oluştur
                    var detailsHtml = createDetailsHtml(response.data, batchId);
                    $row.after('<tr class="batch-details-row"><td colspan="5">' + detailsHtml + '</td></tr>');
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('İstek işlenirken bir hata oluştu.');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Detay HTML'ini oluştur
    function createDetailsHtml(data, batchId) {
        var creationDate = new Date(parseInt(data.creationDate)).toLocaleString();
        var lastModDate = new Date(parseInt(data.lastModification)).toLocaleString();
        
        var html = '<div class="trendyol-batch-details">';
        
        // Batch istatistikleri
        html += '<div class="trendyol-batch-stats">';
        html += '<div><strong>İşlem Tipi:</strong> ' + data.batchRequestType + '</div>';
        html += '<div><strong>Toplam Ürün:</strong> ' + data.totalItems + '</div>';
        html += '<div><strong>Başarılı:</strong> ' + data.successItems + '</div>';
        html += '<div><strong>Başarısız:</strong> ' + data.failedItems + '</div>';
        html += '</div>';
        
        html += '<div><strong>Oluşturma Tarihi:</strong> ' + creationDate + '</div>';
        html += '<div><strong>Son Güncelleme:</strong> ' + lastModDate + '</div>';
        
        // Hata nedenleri
        if (data.errorReasons && data.errorReasons.length > 0) {
            html += '<div class="trendyol-batch-errors">';
            html += '<h4>Hata Nedenleri:</h4>';
            html += '<ul>';
            
            $.each(data.errorReasons, function(index, reason) {
                html += '<li>' + reason + '</li>';
            });
            
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }
    
    // Yenile butonuna tıklama
    $('#trendyol-refresh-batch').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true);
        
        $batchList.html('<div class="trendyol-loading">' + trendyol_batch.checking + '</div>');
        
        // Sayfa yenileme
        location.reload();
    });
});
