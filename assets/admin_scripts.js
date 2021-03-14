jQuery(document).ready(function($) {
    $('#woocommerce_mpesa_idtype').change(function(e){
        const s = this.val()

        if (Number(s) == 4) {
          $('#woocommerce_mpesa_headoffice').closest('tr').hide()  
        } else {
            $('#woocommerce_mpesa_headoffice').closest('tr').show()  
        }
    });
});