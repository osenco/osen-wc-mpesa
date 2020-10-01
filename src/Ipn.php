<?php

namespace Osen\Woocommerce;

use Osen\Woocommerce\Mpesa\C2B;
use Osen\Woocommerce\Mpesa\STK;

class Ipn
{
    private $mpesa;

    public function __construct()
    {
        $this->mpesa = get_option('woocommerce_mpesa_settings', []);

        add_action('rest_api_init', function () {
            register_rest_route('wc/v3', 'lipwa/request', array(
                'methods'             => 'POST',
                'callback'            => array(
                    $this, 'authenticate',
                ),
                'permission_callback' => function () {
                    return true; //current_user_can('manage_options');
                },
            ));
        });
    }

    public function request(\WP_REST_Request $request)
    {
        $_POST    = $request->get_params();
        $order_id = $_POST['order'];
        $order    = new \WC_Order($order_id);
        $total    = $order->get_total();
        $phone    = $order->get_billing_phone();

        return (new STK)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');
    }

    public function validate(\WP_REST_Request $request)
    {
        $_POST = $request->get_params();
        return (new STK)->validate();
    }

    public function confirm(\WP_REST_Request $request)
    {
        $_POST    = $request->get_params();
        $response = json_decode(file_get_contents('php://input'), true);

        if (!$response || empty($response)) {
            return [
                'Error' => 'No response data received',
            ];
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
        if ($post !== false) {
            wp_update_post(
                array(
                    'post_content' => file_get_contents('php://input'), 'ID' => $post,
                )
            );
        } else {
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
                $order->update_status((isset($this->mpesa['completion']) ? $this->mpesa['completion'] : 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
                update_post_meta($post, '_order_status', 'complete');

                $headers = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
                wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . 'with receipt Number ' . $mpesaReceiptNumber . '.', $headers);
            } elseif ($ipn_balance < 0) {
                $currency = get_woocommerce_currency();
                $order->update_status((isset($this->mpesa['completion']) ? $this->mpesa['completion'] : 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
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

        return (new STK)->confirm();
    }

    public function reconcile(\WP_REST_Request $request)
    {
        $_POST    = $request->get_params();
        $response = json_decode(file_get_contents('php://input'), true);

        if (!isset($response['Body'])) {
            exit(wp_send_json(['Error' => 'No response data received']));
        }

        $resultCode        = $response['Body']['stkCallback']['ResultCode'];
        $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
        $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];

        $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
        wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);

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

                    $order->update_status((isset($this->mpesa['completion']) ? $this->mpesa['completion'] : 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));

                    $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n";
                    wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);
                } elseif ($ipn_balance < 0) {
                    $currency = get_woocommerce_currency();
                    $order->update_status((isset($this->mpesa['completion']) ? $this->mpesa['completion'] : 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
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

            return (new STK)->reconcile();
        } else {
            return (new STK)->reconcile(function () {
                return false;
            });
        }
    }

    public function result(\WP_REST_Request $request)
    {
        $_POST    = $request->get_params();
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

        return (new STK)->validate();
    }

    public function register(\WP_REST_Request $request)
    {
        $_POST = $request->get_params();

        return (new C2B)->register(function ($response) {
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
                    [
                        'mpesa-urls-registered' => $message,
                        'reg-state'             => $state,
                    ],
                    wp_get_referer()
                )
            ));
        });
    }

    public function timeout(\WP_REST_Request $request)
    {
        $_POST    = $request->get_params();
        $response = json_decode(file_get_contents('php://input'), true);

        if (!isset($response['Body'])) {
            return [
                'Error' => 'No response data received'
            ];
        }

        $resultCode        = $response['Body']['stkCallback']['ResultCode'];
        $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
        $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];
        $checkoutRequestID = $response['Body']['stkCallback']['CheckoutRequestID'];

        $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
        wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);
        update_post_meta($post, '_order_status', 'pending');

        $order_id = get_post_meta($post, '_order_id', true);
        if (wc_get_order($order_id)) {
            $order = new \WC_Order($order_id);

            $order->update_status('pending');
            $order->add_order_note(__("MPesa Payment Timed Out", 'woocommerce'));
        }

        return (new STK)->timeout();
    }

    public function status(\WP_REST_Request $request)
    {
        $_POST       = $request->get_params();
        $transaction = $_POST['transaction'];
        return (new STK)->status($transaction);
    }

    public function receipt()
    {
        $response = array('receipt' => '');

        if (!empty($_GET['order'])) {
            $post     = wc_mpesa_post_id_by_meta_key_and_value('_order_id', $_GET['order']);
            $response = array(
                'receipt' => get_post_meta($post, '_receipt', true),
            );
        }

        return $response;
    }
}
