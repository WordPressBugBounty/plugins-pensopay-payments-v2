(function ($) {
    'use strict';

    PensopayPaymentsV2.prototype.init = function () {
        // Add event handlers
        this.actionBox.on('click', '[data-action]', $.proxy(this.callAction, this));
        let previousSelectValue = this.orderStatusSelect.val();
        this.orderStatusSelect.hover(function () {
            if (this.value) {
                previousSelectValue = this.value;
            }
        }).change(function () {
            let newValue = this.value;
            // if (newValue === 'wc-refunded' && pensopayBackend.is_pensopay) {
            //     let answer = window.confirm(pensopayBackend.refund_warning);
            //     if (!answer) {
            //         this.value = previousSelectValue;
            //     }
            // }
        });
    };

    PensopayPaymentsV2.prototype.callAction = function (e) {
        e.preventDefault();
        let target = $(e.target);
        let action = target.attr('data-action');

        if (typeof this[action] !== 'undefined') {
            let message = target.attr('data-confirm') || 'Are you sure you want to continue?';
            if (confirm(message)) {
                this[action]();
            }
        }
    };

    PensopayPaymentsV2.prototype.capture = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'capture',
            _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    // noinspection JSUnusedGlobalSymbols
    PensopayPaymentsV2.prototype.captureAmount = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'capture',
            pensopay_amount: $('#pensopay-balance__amount-field').val(),
            _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    PensopayPaymentsV2.prototype.cancel = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'cancel', _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    PensopayPaymentsV2.prototype.refund = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'refund', _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    PensopayPaymentsV2.prototype.split_capture = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'splitcapture',
            pensopay_amount: parseFloat($('#pensopay_split_amount').val()),
            finalize: 0,
            _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    PensopayPaymentsV2.prototype.split_finalize = function () {
        // noinspection JSUnresolvedReference
        let request = this.request({
            pensopay_payments_v2_ajax_action: 'splitcapture',
            pensopay_amount: parseFloat($('#pensopay_split_amount').val()),
            finalize: 1,
            _wpnonce: pensopayBackend.nonce.pensopay_payments_v2_ajax_action
        });
    };

    PensopayPaymentsV2.prototype.request = function (dataObject) {
        // noinspection JSUnresolvedReference
        let that = this;
        return $.ajax({
            type: 'POST', url: ajaxurl, dataType: 'json', data: $.extend({}, {
                action: 'pensopay_transaction_actions',
                order_id: woocommerce_admin_meta_boxes.post_id,
                _wpnonce: pensopayBackend.nonce.action
            }, dataObject), beforeSend: $.proxy(this.showLoader, this, true), success: function () {
                $.get(window.location.href, function (data) {
                    let newData = $(data).find('#' + that.actionBox.attr('id') + ' .inside').html();
                    that.actionBox.find('.inside').html(newData);
                    that.showLoader(false);
                    location.reload();
                });
            }, error: function (jqXHR, textStatus, errorThrown) {
                alert(jqXHR.responseText);
                that.showLoader(false);
            }
        });
    };

    PensopayPaymentsV2.prototype.showLoader = function (e, show) {
        if (show) {
            this.actionBox.append(this.loaderBox);
        } else {
            this.actionBox.find(this.loaderBox).remove();
        }
    };

    // DOM ready
    $(function () {
        new PensopayPaymentsV2().init();

        function ppv2InsertAjaxResponseMessage(response) {
            if (response.hasOwnProperty('status') && response.status === 'success') {
                let message = $('<div id="message" class="updated"><p>' + response.message + '</p></div>');
                message.hide();
                message.insertBefore($('#ppv2_wiki'));
                message.fadeIn('fast', function () {
                    setTimeout(function () {
                        message.fadeOut('fast', function () {
                            message.remove();
                        });
                    }, 5000);
                });
            }
        }

        let emptyLogsButton = $('#ppv2_logs_clear');
        emptyLogsButton.on('click', function (e) {
            e.preventDefault();
            emptyLogsButton.prop('disabled', true);
            $.getJSON(ajaxurl, {action: 'pensopay_empty_logs'}, function (response) {
                ppv2InsertAjaxResponseMessage(response);
                emptyLogsButton.prop('disabled', false);
            });
        });

        let flushCacheButton = $('#ppv2_flush_cache');
        flushCacheButton.on('click', function (e) {
            e.preventDefault();
            flushCacheButton.prop('disabled', true);
            $.getJSON(ajaxurl, {action: 'pensopay_flush_cache'}, function (response) {
                ppv2InsertAjaxResponseMessage(response);
                flushCacheButton.prop('disabled', false);
            });
        });
    });

    function PensopayPaymentsV2() {
        this.actionBox = $('#pensopay-payments-v2-payment-actions');
        this.loaderBox = $('<div class="loader"></div>');
        this.orderStatusSelect = $('#order_status');
    }
})(jQuery);