jQuery(document).ready(function ($) {
    $('#woocommerce_mpesa_signature').closest('tr').hide()
    const s = $('#woocommerce_mpesa_idtype').val()

    if (Number(s) == 4) {
        $('#woocommerce_mpesa_headoffice').closest('tr').hide()
    } else {
        $('#woocommerce_mpesa_headoffice').closest('tr').show()
    }

    $('#woocommerce_mpesa_idtype').change(function () {
        $('#woocommerce_mpesa_headoffice').closest('tr').toggle()
    });

    if ($('#woocommerce_mpesa_enable_c2b').is(':checked')) {
        $('#woocommerce_mpesa_enable_bonga').closest('tr').show()
    } else {
        $('#woocommerce_mpesa_enable_bonga').closest('tr').hide()
    }

    $('#woocommerce_mpesa_enable_c2b').change(function () {
        $('#woocommerce_mpesa_enable_bonga').closest('tr').toggle()
    });

    if ($('#woocommerce_mpesa_enable_reversal').is(':checked')) {
        $('#woocommerce_mpesa_initiator').closest('tr').show()
        $('#woocommerce_mpesa_password').closest('tr').show()
        $('#woocommerce_mpesa_statuses').closest('tr').show()
    } else {
        $('#woocommerce_mpesa_initiator').closest('tr').hide()
        $('#woocommerce_mpesa_password').closest('tr').hide()
        $('#woocommerce_mpesa_statuses').closest('tr').hide()
    }

    $('#woocommerce_mpesa_enable_reversal').change(function () {
        $('#woocommerce_mpesa_initiator').closest('tr').toggle()
        $('#woocommerce_mpesa_password').closest('tr').toggle()
        $('#woocommerce_mpesa_statuses').closest('tr').toggle()
    });
});