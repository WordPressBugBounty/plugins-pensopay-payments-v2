(function ($) {
    'use strict';

    // Hide specified payment methods field unless it needs to be visible
    $(document).ready(function () {
        if ($('#pensopay_payments_gateway_methods').val() !== 'specified') {
            $('#pensopay_payments_gateway_methods_specified').closest('tr').hide();
        }
    });

    // Show specified payment methods field if customer selected as specified
    $('#pensopay_payments_gateway_methods').on('change', function (el) {
        if ($('#pensopay_payments_gateway_methods').val() === 'specified') {
            $('#pensopay_payments_gateway_methods_specified').closest('tr').show();
        } else {
            $('#pensopay_payments_gateway_methods_specified').closest('tr').hide();
        }
    });
})(jQuery);