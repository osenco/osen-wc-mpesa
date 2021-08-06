jQuery(document).ready(function ($) {
    $("#billing_mpesa_phone").val($('#billing_phone').val())

    $('#billing_phone').keyup(function (e) {
        $("#billing_mpesa_phone").val($(this).val())
    });

    $('#renitiate-form').submit(function (e) {
        e.preventDefault();

        var form = $(this);

        $.post(form.attr('action'), form.serialize(), function () {
            $("#mpesa_receipt")
                .html('STK Resent. Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>');
        });
    });

    var checker = setInterval(() => {
        if (!$("#payment_method").length || $("#payment_method").val() !== 'mpesa') {
            clearInterval(checker);
        }

        if ($("#current_order").length) {
            var order = $("#current_order").val();

            if (order.length) {
                $.get(`/wc-api/lipwa_receipt?order=${order}`, [], function (data) {
                    if (data.receipt == '' || data.receipt == 'N/A') {
                        $("#mpesa_receipt").html(
                            'Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>'
                        );
                    } else if (data.receipt == 'fail') {
                        $("#mpesa_receipt").html(
                            `<b>${data.note?.content}</b>`
                        );
                    } else {
                        if (!$("#mpesa-receipt-overview").length) {
                            $(".woocommerce-order-overview").append(
                                `<li id="mpesa-receipt-overview" class="woocommerce-order-overview__payment-method method">Receipt number: <strong>${data.receipt}</strong></li>`
                            );
                        }

                        if (!$("#mpesa-receipt-table-row").length) {
                            $(".woocommerce-table--order-details > tfoot")
                                .find('tr:last-child')
                                .prev()
                                .after(
                                    `<tr id="mpesa-receipt-table-row"><th scope="row">Receipt number:</th><td>${data.receipt}</td></tr>`
                                );
                        }

                        $("#mpesa_receipt").html(
                            `Payment confirmed. Receipt number: <b>${data.receipt}</b>`
                        );

                        $("#missed_stk").hide();
                        $("#renitiate-button").hide();
                        $("#mpesa_request").hide();
                        location.reload()

                        clearInterval(checker);

                        return false;
                    }
                });
            }
        }
    }, 10000);
});
