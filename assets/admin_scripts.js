jQuery(document).ready(function ($) {
    const s = $('#woocommerce_mpesa_idtype').val()

    if (Number(s) == 4) {
        $('#woocommerce_mpesa_headoffice').closest('tr').hide()
    } else {
        $('#woocommerce_mpesa_headoffice').closest('tr').show()
    }

    $('#woocommerce_mpesa_idtype').change(function (e) {
        const v = $(this).val()

        $('#woocommerce_mpesa_headoffice').closest('tr').toggle()
    });
});