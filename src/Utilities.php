<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

use Osen\Woocommerce\Mpesa\C2B;
use Osen\Woocommerce\Mpesa\STK;

class Utilities
{
    public function __construct()
    {
        add_action('init', array($this, 'wc_mpesa_rewrite_add_rewrites'));
        add_filter('query_vars', array($this, 'wc_mpesa_rewrite_add_var'));
        add_action('template_redirect', array($this, 'wc_mpesa_process_ipn'));

        //add_filter('manage_edit-shop_order_columns', 'wcmpesa_new_order_column');
        add_action('manage_shop_order_custom_column', array($this, 'shop_order_payments_table_column_content'), 10);
        add_filter('woocommerce_email_attachments', array($this, 'woocommerce_emails_attach_downloadables'), 10, 3);
        add_action('woocommerce_thankyou_mpesa', array($this, 'request_body'));
        add_action('woocommerce_thankyou_mpesa', array($this, 'validate_payment'));
    }

    public function validate_payment($order_id)
    {
        $mpesa  = get_option('woocommerce_mpesa_settings');
        $idtype = (new C2B)->type;
        $url    = home_url('?pesaipn&order=');

        if (wc_get_order($order_id)) {
            $order     = new \WC_Order($order_id);
            $total     = $order->get_total();
            $reference = $order_id;
        }

        $type = ($idtype == 4) ? 'Pay Bill' : 'Buy Goods and Services'; ?>
        <style>
            @keyframes wave {
                0%,
                60%,
                100% {
                    transform: initial;
                }

                30% {
                    transform: translateY(-15px);
                }
            }

            @keyframes blink {
                0% {
                    opacity: .2;
                }

                20% {
                    opacity: 1;
                }

                100% {
                    opacity: .2;
                }
            }

            .saving span {
                animation: blink 1.4s linear infinite;
                animation-fill-mode: both;
            }

            .saving span:nth-child(2) {
                animation-delay: .2s;
            }

            .saving span:nth-child(3) {
                animation-delay: .4s;
            }
        </style>
        <section class="woocommerce-order-details" id="resend_stk">
            <input type="hidden" id="current_order" value="<?php echo $order_id; ?>">
            <input type="hidden" id="payment_method" value="<?php echo $order->get_payment_method(); ?>">
            <p class="saving" id="mpesa_receipt">Confirming receipt, please wait</p>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                <tbody>
                    <tr class="woocommerce-table__line-item order_item">
                        <td class="woocommerce-table__product-name product-name">
                            <form action="<?php echo home_url("lipwa/request"); ?>" method="POST" id="renitiate-form">
                                <input type="hidden" name="order" value="<?php echo $order_id; ?>">
                                <button id="renitiate-button" class="button alt" type="submit"><?php echo $mpesa['resend'] ?? 'Resend STK Push'; ?></button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <?php if (isset($mpesa['enable_c2b']) && $mpesa['enable_c2b'] == 'yes') : ?>
            <section class="woocommerce-order-details" id="missed_stk">
                <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                    <thead>
                        <tr>
                            <th class="woocommerce-table__product-name product-name">
                                <?php _e("STK Push didn't work? Pay Manually Via M-PESA"); ?>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="woocommerce-table__line-item order_item">
                            <td class="woocommerce-table__product-name product-name">
                                <ol>
                                    <li>Select <b>Lipa na M-PESA</b>.</li>
                                    <li>Select <b><?php echo $type; ?></b>.</li>
                                    <?php if ($idtype == 4) : ?>
                                        <li>Enter <b><?php echo $mpesa['shortcode']; ?></b> as business no.</li>
                                        <li>Enter <b><?php echo $reference; ?></b> as Account no.</li>
                                    <?php else : ?>
                                        <li>Enter <b><?php echo $mpesa['shortcode']; ?></b> as till no.</li>
                                    <?php endif; ?>
                                    <li>Enter Amount <b><?php echo round($total); ?></b>.</li>
                                    <li>Enter your M-PESA PIN</li>
                                    <li>Confirm your details and press OK.</li>
                                    <li>Wait for a confirmation message from M-PESA.</li>
                                </ol>

                            </td>
                        </tr>
                    </tbody>
                </table>
            </section><?php endif;

            echo <<<JS
            <script id="pesaipn-checker">
                jQuery(document).ready(function($) {
                    $('#renitiate-form').submit(function(e){
                        e.preventDefault();

                        var form = $(this);

                        $.post(form.attr('action'), form.serialize(), function(response){
                            $("#mpesa_receipt")
                            .html('STK Resent. Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>');
                        });
                    });

                    var checker = setInterval(() => {
                        if ($("#payment_method").length && $("#payment_method").val() !== 'mpesa') {
                            clearInterval(checker);
                        }

                        if ($("#current_order").length) {
                            var order = $("#current_order").val();
                            if (order.length) {
                                $.get("{$url}" + order, [], function(data) {
                                    if (data.receipt == '' || data.receipt == 'N/A') {
                                        $("#mpesa_receipt").html(
                                            'Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>'
                                        );
                                    } else {
                                        if ($("#mpesa-receipt-overview").length) {} else {
                                            $(".woocommerce-order-overview").append(
                                                '<li id="mpesa-receipt-overview" class="woocommerce-order-overview__payment-method method">Receipt number: <strong>' +
                                                data.receipt +
                                                '</strong></li>'
                                            );
                                        }

                                        if ($("#mpesa-receipt-table-row").length) {} else {
                                            $(".woocommerce-table--order-details > tfoot")
                                                .find('tr:last-child')
                                                .prev()
                                                .after(
                                                    '<tr id="mpesa-receipt-table-row"><th scope="row">Receipt number:</th><td>' +
                                                    data.receipt +
                                                    '</td></tr>'
                                                );
                                        }

                                        $("#mpesa_receipt").html(
                                            'Payment confirmed. Receipt number: <b>' +
                                            data.receipt +
                                            '</b>'
                                        );

                                        $("#missed_stk").hide();
                                        $("#resend_stk").hide();
                                        $("#mpesa_request").hide();
                                        clearInterval(checker);
                                        return false;
                                    }
                                });
                            }
                        }
                    },
                    3000);
                });
            </script>
JS;
    }

    public function wc_mpesa_rewrite_add_rewrites()
    {
        add_rewrite_rule('lipwa/([^/]*)/?', 'index.php?lipwa=$matches[1]', 'top');
    }

    public function wc_mpesa_rewrite_add_var($vars)
    {
        array_push($vars, 'lipwa');
        return $vars;
    }

    public function wc_mpesa_process_ipn()
    {
        $mpesa = get_option('woocommerce_mpesa_settings', []);

        if (get_query_var('lipwa')) {
            header("Access-Control-Allow-Origin: *");
            header("Content-Type: Application/json");

            $action = get_query_var('lipwa', 'something_ominous');

            switch ($action) {
                case "request":
                    $order_id   = sanitize_text_field($_POST['order']);
                    $order      = new \WC_Order($order_id);
                    $total      = $order->get_total();
                    $phone      = $order->get_billing_phone();
                    $first_name = $order->get_billing_first_name();
                    $last_name  = $order->get_billing_last_name();
                    $result     = (new STK)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');

                    wp_send_json($result);
                    break;
                case "validate":
                    exit(wp_send_json(
                        (new STK)->validate()
                    ));
                    break;

                case "confirm":
                    $response = json_decode(file_get_contents('php://input'), true);

                    if (!$response || empty($response)) {
                        exit(wp_send_json(
                            ['Error' => 'No response data received']
                        ));
                    }

                    $mpesaReceiptNumber = $response['TransID'];
                    $transactionDate    = $response['TransTime'];
                    $amount             = $response['TransAmount'];
                    $BillRefNumber      = $response['BillRefNumber'];
                    $phone              = $response['MSISDN'];
                    $FirstName          = $response['FirstName'];
                    $MiddleName         = $response['MiddleName'];
                    $LastName           = $response['LastName'];

                    $post = wc_mpesa_post_id_by_meta_key_and_value('_reference', $BillRefNumber);
                    if (!$post) {
                        $post_id = wp_insert_post(
                            array(
                                'post_title'   => 'C2B',
                                'post_content' => "Response: " . json_encode($response),
                                'post_status'  => 'publish',
                                'post_type'    => 'mpesaipn',
                                'post_author'  => 1,
                            )
                        );

                        update_post_meta($post_id, '_customer', "{$FirstName} {$MiddleName} {$LastName}");
                        update_post_meta($post_id, '_phone', $phone);
                        update_post_meta($post_id, '_amount', $amount);
                        update_post_meta($post_id, '_receipt', $mpesaReceiptNumber);
                        update_post_meta($post_id, '_order_status', 'processing');
                    }

                    $order_id        = get_post_meta($post, '_order_id', true);
                    $amount_due      = get_post_meta($post, '_amount', true);
                    $before_ipn_paid = get_post_meta($post, '_paid', true);

                    if (wc_get_order($order_id)) {
                        $order    = new \WC_Order($order_id);
                        $customer = "{$FirstName} {$MiddleName} {$LastName}";
                    } else {
                        $customer = "MPesa Customer";
                    }

                    $after_ipn_paid = round($before_ipn_paid) + round($amount);
                    $ipn_balance    = $after_ipn_paid - $amount_due;

                    if (wc_get_order($order_id)) {
                        $order = new \WC_Order($order_id);

                        if ($ipn_balance == 0) {
                            $order->update_status((isset($mpesa['completion']) ? $mpesa['completion'] : 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
                            update_post_meta($post, '_order_status', 'complete');

                            $headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
                            wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . 'with receipt Number ' . $mpesaReceiptNumber . '.', $headers);
                        } elseif ($ipn_balance < 0) {
                            $currency = get_woocommerce_currency();
                            $order->update_status((isset($mpesa['completion']) ? $mpesa['completion'] : 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
                            update_post_meta($post, '_order_status', 'complete');
                        } else {
                            $order->update_status('on-hold');
                            $order->add_order_note(__("MPesa Payment from {$phone} Incomplete"));
                            update_post_meta($post, '_order_status', 'on-hold');
                        }
                    }

                    update_post_meta($post, '_paid', $after_ipn_paid);
                    update_post_meta($post, '_amount', $amount_due);
                    update_post_meta($post, '_balance', $ipn_balance);
                    update_post_meta($post, '_phone', $phone);
                    update_post_meta($post, '_customer', $customer);
                    update_post_meta($post, '_order_id', $order_id);
                    update_post_meta($post, '_receipt', $mpesaReceiptNumber);

                    exit(wp_send_json((new STK)->confirm()));
                    break;

                case "register":
                    (new C2B)->register(function ($response) {
                        $status = isset($response['ResponseDescription']) ? 'success' : 'fail';
                        if ($status == 'fail') {
                            $message = isset($response['errorMessage']) ? $response['errorMessage'] : 'Could not register M-PESA URLs, try again later.';
                            $state   = 'red';
                        } else {
                            $message = isset($response['ResponseDescription']) ? $response['ResponseDescription'] : 'M-PESA URL registered successfully. You will now receive C2B Payment Notifications.';
                            $state   = 'green';
                        }

                        exit(wp_redirect(
                            add_query_arg(
                                array(
                                    'mpesa-urls-registered' => $message,
                                    'reg-state'             => $state,
                                ),
                                wp_get_referer()
                            )
                        ));
                    });

                    break;

                case "reconcile":
                    $response = json_decode(file_get_contents('php://input'), true);

                    if (!isset($response['Body'])) {
                        exit(wp_send_json(['Error' => 'No response data received']));
                    }

                    $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                    $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                    $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];

                    $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
                    //wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);

                    $order_id        = get_post_meta($post, '_order_id', true);
                    $amount_due      = get_post_meta($post, '_amount', true);
                    $before_ipn_paid = get_post_meta($post, '_paid', true);

                    if (wc_get_order($order_id)) {
                        $order      = new \WC_Order($order_id);
                        $first_name = $order->get_billing_first_name();
                        $last_name  = $order->get_billing_last_name();
                        $customer   = "{$first_name} {$last_name}";

                        if (isset($response['Body']['stkCallback']['CallbackMetadata'])) {
                            $amount             = $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
                            $mpesaReceiptNumber = $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
                            $balance            = $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
                            $transactionDate    = $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
                            $phone              = $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
                            $after_ipn_paid     = round($before_ipn_paid) + round($amount);
                            $ipn_balance        = $after_ipn_paid - $amount_due;

                            if ($ipn_balance == 0) {
                                update_post_meta($post, '_order_status', 'complete');
                                update_post_meta($post, '_receipt', $mpesaReceiptNumber);

                                $order->update_status((isset($mpesa['completion']) ? $mpesa['completion'] : 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));

                                $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
                                wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);
                            } elseif ($ipn_balance < 0) {
                                $currency = get_woocommerce_currency();
                                $order->update_status((isset($mpesa['completion']) ? $mpesa['completion'] : 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
                                update_post_meta($post, '_order_status', 'complete');
                                update_post_meta($post, '_receipt', $mpesaReceiptNumber);
                            } else {
                                $order->update_status('on-hold');
                                $order->add_order_note(__("MPesa Payment from {$phone} Incomplete"));
                                update_post_meta($post, '_order_status', 'on-hold');
                            }

                            update_post_meta($post, '_paid', $after_ipn_paid);
                            update_post_meta($post, '_amount', $amount_due);
                            update_post_meta($post, '_balance', $ipn_balance);
                            update_post_meta($post, '_phone', $phone);
                            update_post_meta($post, '_customer', $customer);
                            update_post_meta($post, '_order_id', $order_id);
                            update_post_meta($post, '_receipt', $mpesaReceiptNumber);
                        } else {
                            $order->update_status('on-hold');
                            $order->add_order_note(__("MPesa Error {$resultCode}: {$resultDesc}"));
                        }

                        exit(wp_send_json((new STK)->reconcile()));
                    } else {
                        exit(wp_send_json((new STK)->reconcile(function () {
                            return false;
                        })));
                    }
                    break;

                case "status":
                    $transaction = sanitize_text_field($_POST['transaction']);
                    exit(wp_send_json((new STK)->status($transaction)));
                    break;

                case "result":
                    $response = json_decode(file_get_contents('php://input'), true);

                    $result = $response['Result'];

                    $ResultType               = $result['ResultType'];
                    $ResultCode               = $result['ResultType'];
                    $ResultDesc               = $result['ResultType'];
                    $OriginatorConversationID = $result['ResultType'];
                    $ConversationID           = $result['ResultType'];
                    $TransactionID            = $result['ResultType'];
                    $ResultParameters         = $result['ResultType'];

                    $ResultParameter = $result['ResultType'];

                    $ReceiptNo                = $ResultParameter[0]['Value'];
                    $ConversationID           = $ResultParameter[0]['Value'];
                    $FinalisedTime            = $ResultParameter[0]['Value'];
                    $Amount                   = $ResultParameter[0]['Value'];
                    $ReceiptNo                = $ResultParameter[0]['Value'];
                    $TransactionStatus        = $ResultParameter[0]['Value'];
                    $ReasonType               = $ResultParameter[0]['Value'];
                    $TransactionReason        = $ResultParameter[0]['Value'];
                    $DebitPartyCharges        = $ResultParameter[0]['Value'];
                    $DebitAccountType         = $ResultParameter[0]['Value'];
                    $InitiatedTime            = $ResultParameter[0]['Value'];
                    $OriginatorConversationID = $ResultParameter[0]['Value'];
                    $CreditPartyName          = $ResultParameter[0]['Value'];
                    $DebitPartyName           = $ResultParameter[0]['Value'];

                    $ReferenceData = $result['ReferenceData'];
                    $ReferenceItem = $ReferenceData['ReferenceItem'];
                    $Occasion      = $ReferenceItem[0]['Value'];
                    exit(wp_send_json((new STK)->validate()));
                    break;

                case "timeout":
                    $response = json_decode(file_get_contents('php://input'), true);

                    if (!isset($response['Body'])) {
                        exit(wp_send_json(['Error' => 'No response data received']));
                    }

                    $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                    $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                    $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];
                    $checkoutRequestID = $response['Body']['stkCallback']['CheckoutRequestID'];

                    $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
                    //wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);
                    update_post_meta($post, '_order_status', 'pending');

                    $order_id = get_post_meta($post, '_order_id', true);
                    if (wc_get_order($order_id)) {
                        $order = new \WC_Order($order_id);

                        $order->update_status('pending');
                        $order->add_order_note(__("MPesa Payment Timed Out", 'woocommerce'));
                    }

                    exit(wp_send_json((new STK)->timeout()));
                    break;
                default:
                    exit(wp_send_json((new C2B)->register()));
            }
        }

        if (isset($_GET['pesaipn'])) {
            $response = array('receipt' => '');

            if (!empty($_GET['order'])) {
                $post     = wc_mpesa_post_id_by_meta_key_and_value('_order_id', esc_attr(sanitize_text_field($_GET['order'])));
                $response = array(
                    'receipt' => get_post_meta($post, '_receipt', true),
                );
            }

            exit(wp_send_json($response));
        }
    }

    public function wcmpesa_new_order_column($columns)
    {
        $columns['mpesa'] = 'Reinitiate Mpesa';
        return $columns;
    }

    /**
     * Render custom column content within edit.php table on event post types.
     *
     * @access public
     * @param string $column The name of the column being acted upon
     * @return void
     */
    public function shop_order_payments_table_column_content($column_id, $post_id)
    {
        $order_id = get_post_meta($post_id, '_order_id', true);
        switch ($column_id) {

            case 'mpesa':
                $statuses = array(
                    "processing" => "This Order Is Processing",
                    "on-hold"    => "This Order Is On Hold",
                    "complete"   => "This Order Is Complete",
                    "cancelled"  => "This Order Is Cancelled",
                    "refunded"   => "This Order Is Refunded",
                    "failed"     => "This Order Failed",
                );

                echo ($value = get_post_meta($post_id, '_order_status', true))
                    ? '<a href="' . admin_url('post.php?post=' . esc_attr(sanitize_text_field($order_id)) . '&action=edit">' . esc_attr($statuses[$value]) . '</a>')
                    : '<a href="' . admin_url('post.php?post=' . esc_attr(sanitize_text_field($order_id)) . '&action=edit"') . '>Set Status</a>';
                break;
        }
    }

    public function woocommerce_emails_attach_downloadables($attachments, $status, $order)
    {
        if (!is_object($order) || !isset($status)) {
            return $attachments;
        }
        if (empty($order)) {
            return $attachments;
        }
        if (method_exists($order, 'has_downloadable_item')) {
            if (!$order->has_downloadable_item()) {
                return $attachments;
            }
            $allowed_statuses = array('customer_invoice', 'customer_completed_order');
            if (isset($status) && in_array($status, $allowed_statuses)) {
                foreach ($order->get_items() as $item_id => $item) {
                    foreach ($order->get_item_downloads($item) as $download) {
                        $attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
                    }
                }
            }
        }

        return $attachments;
    }

    /**
     * @since 1.20.79
     */
    public function request_body($order_id)
    {
        $c2b = get_option('woocommerce_mpesa_settings');

        if (($c2b['debug'] ?? 'no') == 'yes') {
            echo '
            <section class="woocommerce-order-details" id="mpesa_request">
            <p>Mpesa request body</p>
                <code>' . WC()->session->get('mpesa_request') . '</code>
            </section>';
        }
    }
}
