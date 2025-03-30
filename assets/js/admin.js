/**
 * Trendyol WooCommerce Admin JavaScript
 */

(function($) {
    'use strict';

    // Yüklendiğinde
    $(document).ready(function() {
        // Senkronizasyon işlemlerinde onay
        $('.trendyol-wc-sync-confirm').on('click', function(e) {
            if (!confirm(trendyol_wc_params.i18n.confirm_sync)) {
                e.preventDefault();
                return false;
            }
        });

        // AJAX ürün senkronizasyonu
        $('#trendyol-wc-sync-products-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(trendyol_wc_params.i18n.syncing);
            $button.prop('disabled', true);
            
            // Parametreleri al
            var direction = $('#sync_direction').val();
            var limit = $('#sync_limit').val();
            var skipExisting = $('#skip_existing').is(':checked') ? 1 : 0;
            
            // AJAX isteği
            $.ajax({
                type: 'POST',
                url: trendyol_wc_params.ajax_url,
                data: {
                    action: 'trendyol_sync_products',
                    nonce: trendyol_wc_params.nonce,
                    direction: direction,
                    limit: limit,
                    skip_existing: skipExisting
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data, 'success');
                    } else {
                        showMessage(response.data, 'error');
                    }
                    
                    $button.text(originalText);
                    $button.prop('disabled', false);
                },
                error: function() {
                    showMessage(trendyol_wc_params.i18n.sync_error, 'error');
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        });

        // AJAX sipariş senkronizasyonu
        $('#trendyol-wc-sync-orders-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(trendyol_wc_params.i18n.syncing);
            $button.prop('disabled', true);
            
            // Parametreleri al
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            
            // AJAX isteği
            $.ajax({
                type: 'POST',
                url: trendyol_wc_params.ajax_url,
                data: {
                    action: 'trendyol_sync_orders',
                    nonce: trendyol_wc_params.nonce,
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data, 'success');
                    } else {
                        showMessage(response.data, 'error');
                    }
                    
                    $button.text(originalText);
                    $button.prop('disabled', false);
                },
                error: function() {
                    showMessage(trendyol_wc_params.i18n.sync_error, 'error');
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Durum güncelleme modal gösterimi
        $('.trendyol-wc-show-update-status').on('click', function() {
            var orderId = $(this).data('id');
            $('#trendyol-update-status-' + orderId).show();
        });

        // Kargo bilgisi güncelleme modal gösterimi
        $('.trendyol-wc-show-update-tracking').on('click', function() {
            var orderId = $(this).data('id');
            $('#trendyol-update-tracking-' + orderId).show();
        });

        // Sipariş iptal modal gösterimi
        $('.trendyol-wc-show-cancel-order').on('click', function() {
            var orderId = $(this).data('id');
            $('#trendyol-cancel-order-' + orderId).show();
        });

        // Modal kapatma
        $('.trendyol-wc-close-modal').on('click', function() {
            $(this).closest('.trendyol-wc-modal-form').hide();
        });
        
        // Marka arama
        $('#trendyol-brand-search').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $("#trendyol-brands-table tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
        
        // Varsayılan marka seçiminde isim güncelleme
        $("#trendyol_default_brand_id").change(function() {
            var selectedOption = $(this).find("option:selected");
            var brandName = selectedOption.data("name") || "";
            $("#trendyol_default_brand").val(brandName);
        });
        
        // Log içeriğini kopyalama
        $('#trendyol-wc-copy-log').on('click', function() {
            var tempElem = document.createElement('textarea');
            tempElem.value = $('#trendyol-wc-log-content').text();
            document.body.appendChild(tempElem);
            
            tempElem.select();
            document.execCommand('copy');
            document.body.removeChild(tempElem);
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Kopyalandı!');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        });
    });

    // Mesaj gösterme fonksiyonu
    function showMessage(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Önceki mesajlar varsa kaldır
        $('.notice').remove();
        
        // Yeni mesajı ekle
        $('.wrap').prepend($notice);
        
        // WordPress admin notice kapatma düğmesi ekle
        setTimeout(function() {
            if (typeof wp !== 'undefined' && typeof wp.a11y !== 'undefined') {
                // WordPress 5.3+ için
                $notice.find('button.notice-dismiss').remove();
                wp.a11y.speak(message);
            }
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Bu bildirimi kapat.</span></button>');
            
            // Kapatma düğmesi olay dinleyicisi
            $notice.find('.notice-dismiss').on('click', function() {
                $(this).closest('.notice').remove();
            });
        }, 10);
        
        // Otomatik kapat
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

})(jQuery);