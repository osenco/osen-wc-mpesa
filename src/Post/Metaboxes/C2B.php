<?php
namespace Osen\Post\Metaboxes;
/**
 * @package MPesa For WooCommerce
 * @subpackage Metaboxes
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */
class C2B{

    public static function init(Type $var = null)
    {
        add_action('add_meta_boxes', [new self, 'mpesa_mb_sm']);
        add_action('save_post', [new self, 'mpesaipn_save_meta']);
    }

    public static function mpesa_mb_sm() 
    {
        add_meta_box('c2b-payment-customer_details', 'Customer Details', [new self, 'customer_details'], [ 'mpesaipn', 'b2c_payment'], 'normal', 'high');
        add_meta_box('c2b-payment-order_details', 'Order Details', [new self, 'order_details'], [ 'mpesaipn', 'b2c_payment'], 'normal', 'high');
        add_meta_box('c2b-payment-payment_details', 'Payment Details', [new self, 'payment_details'], [ 'mpesaipn', 'b2c_payment'], 'side', 'high');
        //add_meta_box('c2b-payment-payment_status', 'Incase MPesa timed out', [new self, 'mpesa_status'], [ 'mpesaipn', 'shop_order' ], 'side', 'low');
        add_meta_box('woocommerce-order-notes', 'Payment Order Notes', [new self, 'order_notes'],'mpesaipn', 'normal', 'default');
        add_meta_box('c2b-payment-payment_create', 'Paid For Via MPesa?', [new self, 'mpesa_payment'], 'shop_order', 'side', 'low');
    }

    public static function mpesa_payment($post)
    {
        echo '<table class="form-table" >
            <tr valign="top" >
                <td>
                    You can manually register a payment via MPesa after saving this order.
                </td>
            </tr>
            <tr valign="top" >
                <td>
                    <a href="'.admin_url('post-new.php?post_type=mpesaipn&order='.$post->ID.'').'" class="button">Add New MPesa Payment</a>
                </td>
            </tr>
        </table>';
    }

    public static function mpesa_status($post)
    {
        $status = ($value = get_post_meta($post->ID, '_order_status', true)) ? $value : 'complete';
        $request = ($value = get_post_meta($post->ID, '_request', true)) ? $value : 0;

        $statuses = array(
            "processing"    => "This Order Is Processing",
            "on-hold"       => "This Order Is On Hold",
            "complete"      => "This Order Is Complete",
            "cancelled"     => "This Order Is Cancelled",
            "refunded"      => "This Order Is Refunded",
            "failed"        => "This Order Failed"
        );

        echo '<table class="form-table" >
            <tr valign="top" >
                <td>
                    <small id="mpesaipn_status_result">'.$statuses[$status].'</small>
                </td>
            </tr>
            <tr valign="top" >
                <td>
                    <button type="submit" id="mpesaipn_status" name="mpesaipn_status" class="button button-large">Check Payment Status</button>
                    <script>
                        jQuery(document).ready(function($){
                            $("#mpesaipn_status").click(function(e){
                                e.preventDefault();
                                $.post("'.admin_url("admin-ajax.php").'", [request: '.$request.'], function(data){
                                    $("#mpesaipn_status_result").html(data);
                                });
                            });
                        });
                    </script>
                </td>
            </tr>
        </table>';
    }

    public static function customer_details($post)
    {
        $customer   = get_post_meta($post->ID, '_customer', true);
        $phone      = get_post_meta($post->ID, '_phone', true);
        if(isset($_GET['order'])){
            $order 		= new \WC_Order($_GET['order']);
            $total 		= wc_format_decimal($order->get_total(), 2);
            $phone 		= $order->get_billing_phone();
            $first_name = $order->get_billing_first_name();
            $last_name 	= $order->get_billing_last_name();
            $customer 	= "{$first_name} {$last_name}";
        }

        // Remove the plus sign before the customer's phone number
        if (substr($phone, 0,1) == "+") {
            $phone = str_replace("+", "", $phone);
        }

        echo '<table class="form-table" >
            <tr valign="top" >
                <th scope="row" >Full Names</th>
                <td><input type="text" name="customer" value="'. esc_attr($customer) .' " class="regular-text" / > </td>
            </tr>
            <tr valign="top" >
                <th scope="row">Phone Number</th>
                <td><input type="text" name="phone" value="'. esc_attr($phone) .' " class="regular-text" / >
                <input type="hidden" name="ipnmb">
                <input type="hidden" name="post_title", value="Manual">
                </td>
            </tr>
        </table>';
    }

    public static function order_details($post)
    {
        $order      = ($value = get_post_meta($post->ID, '_order_id', true)) ? $value : $post->ID;
        $order      = isset($_GET['order']) ? $_GET['order'] : $order;
        $amount     = get_post_meta($post->ID, '_amount', true);
        $paid       = get_post_meta($post->ID, '_paid', true);
        $balance    = get_post_meta($post->ID, '_balance', true);

        if(isset($_GET['order'])){
            $order_details  = new \WC_Order($_GET['order']);
            $amount         = wc_format_decimal($order_details->get_total(), 2);
            $phone          = $order_details->get_billing_phone();
            $first_name     = $order_details->get_billing_first_name();
            $last_name      = $order_details->get_billing_last_name();
            $customer       = "{$first_name} {$last_name}";
        }

        $new = wc_get_order($order) ? '' : ' <a href="'.admin_url('post-new.php?post_type=shop_order').'" class="button">Add New Manual Order</a>';

        echo '<table class="form-table" >
            <tr valign="top" >
                <th scope="row" >Order ID</th>
                <td>
                    <input type="text" name="order_id" value="'. esc_attr($order) .' " class="regular-text" />'.$new.'
                </td>
            </tr>
            <tr valign="top" >
                <th scope="row">Order Amount</th>
                <td><input type="text" name="amount" value="'. esc_attr(round($amount)) .' " class="regular-text" /> </td>
            </tr>
        </table>';
    }

    public static function order_notes($post)
    {
        echo '<table class="form-table" >
            <tr valign="top" >
                <th scope="row" >Add Order Note</th>
                <td>
                    <textarea id="add_order_note" name="order_note" class="large-text"></textarea>
                </td>
            </tr>
        </table>';
    }

    public static function payment_details($post)
    {
        $status     = ($value = get_post_meta($post->ID, '_order_status', true)) ? $value : 'complete';
        $request    = get_post_meta($post->ID, '_request_id', true);
        $receipt    = get_post_meta($post->ID, '_receipt', true);

        $statuses   = array(
            "processing"    => "This Order Is Processing",
            "on-hold"       => "This Order Is On Hold",
            "complete"      => "This Order Is Complete",
            "cancelled"     => "This Order Is Cancelled",
            "refunded"      => "This Order Is Refunded",
            "failed"        => "This Order Failed"
        ); ?>
        <p>Add here the MPesa confirmation code received and set the appropriate order status for <b>Request ID: <?php echo $request; ?></b>.</p>
        <?php echo '<p>MPesa Receipt Number <input type="text" name="receipt" value="'. esc_attr($receipt) .' " /></p>'; ?>
        <p>Set Order(Payment) Status
            <select name="status">
                <option class="postbox" value="<?php echo esc_attr($status); ?>"><?php echo esc_attr($statuses[$status]); ?></option>
                <?php  unset($statuses[$status]); foreach($statuses as $ostatus => $label): ?>
                    <option class="postbox" value="<?php echo esc_attr($ostatus); ?>"><?php echo esc_attr($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="save_meta">
        </p><?php
    }

    public static function mpesaipn_save_meta($post_id) 
    {
        if (isset($_POST['save_meta'])) {
            $customer       = trim($_POST['customer']);
            $phone          = trim($_POST['phone']);
            $order_id       = trim($_POST['order_id']);
            $order_status   = trim($_POST['status']);
            $order_note     = trim($_POST['order_note']);

            $amount         = trim($_POST['amount']);
            $paid           = trim($_POST['paid']);

            $receipt = trim($_POST['receipt']);

            update_post_meta($post_id, '_customer', strip_tags($customer));
            update_post_meta($post_id, '_phone', strip_tags($phone));
            update_post_meta($post_id, '_order_id', strip_tags($order_id));
            update_post_meta($post_id, '_amount', strip_tags($amount));
            update_post_meta($post_id, '_paid', strip_tags($paid));
            update_post_meta($post_id, '_balance', strip_tags($amount-$paid));
            update_post_meta($post_id, '_receipt', strip_tags($receipt));
            update_post_meta($post_id, '_order_status', strip_tags($order_status));

            if(wc_get_order($order_id !== false)){
                $order = new \WC_Order($order_id);
                $order->update_status(strip_tags($order_status));

                if($order_note !== ""){
                    $order->add_order_note(__(strip_tags($order_note)));	
                }
            }
        }
    }
}