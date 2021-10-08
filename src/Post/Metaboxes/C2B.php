<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage C2B Metaboxes
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @version 2.0.0
 * @since 0.18.01
 */

namespace Osen\Woocommerce\Post\Metaboxes;

class C2B
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'mpesa_mb_sm']);
        add_action('save_post', array($this, 'save_order'), 10, 3);
    }

    public function mpesa_mb_sm()
    {
        add_meta_box('wc_mpesa_mb_payment_status', 'Incase MPesa timed out', [$this, 'mpesa_status'], ['shop_order'], 'side', 'low');
        add_meta_box('wc_mpesa_mb_payment_create', 'Paid For Via MPesa?', [$this, 'mpesa_payment'], 'shop_order', 'side', 'low');
    }

    public function mpesa_payment($post)
    {
        $order   = new \WC_Order($post);
        $receipt = $order->get_transaction_id();
        $status  = $order->get_status();

        echo '<table class="form-table" >
        <tr valign="top" >
            <td>
                <label scope="rows" >
                    ' . ($receipt ? "" : "Enter ") . 'Transaction ID:
                </label>
                <input class="input-text" type="text" name="receipt" value="' . esc_attr($receipt) . ' " class="regular-text" / >
             </td>
        </tr>
        <tr valign="top" >
            <td>
                <label>
                    <input type="checkbox" name="full_amount" value="yes" ' . ($status === 'completed' ? 'checked' : '') . ' />
                    Full Amount Paid
                </label>
            </td>
        </tr>
        </table>';
    }

    public function mpesa_status($post)
    {
        $order    = new \WC_Order($post);
        $request  = \get_post_meta($order->get_ID(), 'mpesa_request_id', true);
        $status   = $order->get_status();
        $statuses = wc_get_order_statuses();

        echo '<table class="form-table" >
            <tr valign="top" >
                <td>
                    <small id="mpesaipn_status_result">This order is ' . $statuses["wc-$status"] . '</small>
                </td>
            </tr>
            <tr valign="top" >
                <td>
                    ' . (($status === 'completed')
            ? '<button id="mpesaipn_status" name="mpesaipn_status" class="button button-large">Check Payment Status</button>
                    <script>
                        jQuery(document).ready(function($){
                            $("#mpesaipn_status").click(function(e){
                                e.preventDefault();
                                $.post("' . admin_url("admin-ajax.php") . '", {request: ' . $request . '}, function(data){
                                    $("#mpesaipn_status_result").html(data);
                                });
                            });
                        });
                    </script>'
            : '<button id="mpesaipn_reinitiate" name="mpesaipn_reinitiate" class="button button-large">Reinitiate Prompt</button>
                    <script>
                        jQuery(document).ready(function($){
                            $("#mpesaipn_reinitiate").click(function(e){
                                e.preventDefault();
                                $.post("' . home_url("wc-api/lipwa?action=request") . '", {order: ' . $order->get_ID() . '}, function(data){
                                if(data.errorCode){
                                    $("#mpesaipn_status_result").html("("+data.errorCode+") "+data.errorMessage);
                                } else{
                                    $("#mpesaipn_status_result").html("STK Resent. Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>");
                                }
                            });
                            });
                        });
                    </script>') . '
                </td>
            </tr>
        </table>';
    }

    public function save_order(int $post_id, \WP_Post $post, bool $update): void
    {
        $order = wc_get_order($post_id);

        if ($order) {
            $transaction_id = $order->get_transaction_id();

            if ($update && !$transaction_id && isset($_POST['receipt'])) {
                if (isset($_POST['full_amount'])) {
                    $order->payment_complete(sanitize_text_field($_POST['receipt']));
                    $order->add_order_note("Full MPesa payment received. Transaction ID {$_POST['receipt']}");
                } else {
                    $order->set_transaction_id(sanitize_text_field($_POST['receipt']));
                    $order->add_order_note("Mpesa payment received. Transaction ID {$_POST['receipt']}");
                    $order->save();
                }
            }
        }
    }
}
