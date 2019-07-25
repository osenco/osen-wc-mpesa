<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

function wc_mpesa_post_id_by_meta_key_and_value($key, $value) {
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `".$wpdb->postmeta."` WHERE meta_key='".$key."' AND meta_value='".$value."'");
    if (is_array($meta) && !empty($meta) && isset($meta[0])) {
        $meta = $meta[0];
    }

    if (is_object($meta)) {
        return $meta->post_id;
    } else {
        return false;
    }
}

add_action( 'woocommerce_thankyou_mpesa', 'wc_mpesa_add_content_thankyou_mpesa' );
function wc_mpesa_add_content_thankyou_mpesa($order_id) { 
	$mpesa = get_option('woocommerce_mpesa_settings');
	$idtype = Osen\Mpesa\C2B::$type ;
	if(wc_get_order($order_id)){
		$order = new WC_Order($order_id);
		$total = $order->get_total();
		$reference = 'ORDER#'.$order_id;
	}

	$type = ($idtype == 4) ? 'Pay Bill' : 'Buy Goods and Services';
	
	if(isset($mpesa['enable_c2b']) && $mpesa['enable_c2b'] == 'yes'): ?>
	<section class="woocommerce-order-details">

		<h2 class="woocommerce-order-details__title"></h2>

		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

			<thead>
				<tr>
					<th class="woocommerce-table__product-name product-name">
						<?php _e('Missed the STK Prompt? Pay Manually Via M-PESA') ?></th>
				</tr>
			</thead>

			<tbody>
				<tr class="woocommerce-table__line-item order_item">

					<td class="woocommerce-table__product-name product-name">

						<ol>
							<li>Select <b>Lipa na M-PESA</b>.</li>
							<li>Select <b><?php echo $type; ?></b>.</li>
							<?php if ($idtype== 4): ?>
							<li>Enter <b><?php echo $reference; ?></b> as Account no.</li>
							<li>Enter <b><?php echo $mpesa['shortcode']; ?></b> as business no.</li>
							<?php else: ?>
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

	</section>
	<?php endif;
}

add_action('init', 'wc_mpesa_rewrite_add_rewrites');
function wc_mpesa_rewrite_add_rewrites()
{
    add_rewrite_rule('wcpesa/([^/]*)/?', 'index.php?wcpesa=$matches[1]', 'top');
}

add_filter('query_vars', 'wc_mpesa_rewrite_add_var');
function wc_mpesa_rewrite_add_var($vars)
{
    $vars[] = 'wcpesa';
    return $vars;
}

add_action('template_redirect', 'wc_mpesa_process_ipn');
function wc_mpesa_process_ipn()
{
    if(get_query_var('wcpesa'))
    {
    	header("Access-Control-Allow-Origin: *");
    	header("Content-Type: Application/json");
    	
        $action = get_query_var('wcpesa', 'something_ominous');
        
        switch($action){
            case "validate":
                exit(wp_send_json(Osen\Mpesa\STK::validate()));
                break;
				
            case "confirm":
                $response 			= json_decode(file_get_contents('php://input'), true);

				if(!$response || empty($response)){
					exit(wp_send_json(['Error' => 'No response data received']));
				}

				$TransactionType    = $response['TransactionType'];
				$mpesaReceiptNumber = $response['TransID'];
				$transactionDate    = $response['TransTime'];
				$amount        		= $response['TransAmount'];
				$BusinessShortCode  = $response['BusinessShortCode'];
				$BillRefNumber      = $response['BillRefNumber'];
				$InvoiceNumber      = $response['InvoiceNumber'];
				$OrgAccountBalance  = $response['OrgAccountBalance'];
				$ThirdPartyTransID  = $response['ThirdPartyTransID'];
				$phone             	= $response['MSISDN'];
				$FirstName          = $response['FirstName'];
				$MiddleName         = $response['MiddleName'];
				$LastName           = $response['LastName'];

				$post = wc_mpesa_post_id_by_meta_key_and_value('_reference', $BillRefNumber);
				wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);

				$order_id 			= get_post_meta($post, '_order_id', true);
				$amount_due 		=  get_post_meta($post, '_amount', true);
				$before_ipn_paid 	= get_post_meta($post, '_paid', true);

				if(wc_get_order($order_id)){
					$order 			= new WC_Order($order_id);
					$customer 		= "{$FirstName} {$MiddleName} {$LastName}";
				} else {
					$customer 		= "MPesa Customer";
				}

				$after_ipn_paid 	= round($before_ipn_paid)+round($amount);
				$ipn_balance 		= $after_ipn_paid-$amount_due;

				if(wc_get_order($order_id)){
					$order = new WC_Order($order_id);
					
					if ($ipn_balance == 0) {
						$mpesa = new WC_Gateway_MPESA();
						$order->update_status('complete');
						$order->payment_complete();
						$order->add_order_note(__("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
						update_post_meta($post, '_order_status', 'complete');

						$headers = 'From: '.get_bloginfo('name').' <'.get_bloginifo('admin_email').'>' . "\r\n";
						wp_mail($order["billing_address"], 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. '.$amount.' on '.$transactionDate.'with receipt Number '.$mpesaReceiptNumber.'.', $headers);
					} elseif ($ipn_balance < 0) {
						$currency = get_woocommerce_currency();
						$order->payment_complete();
						$order->add_order_note(__("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
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
				
				exit(wp_send_json(Osen\Mpesa\STK::confirm()));
                break;

            case "register":
				exit(wp_send_json(Osen\Mpesa\C2B::register()));
                break;
				
            case "reconcile":
                $response = json_decode(file_get_contents('php://input'), true);

				if(! isset($response['Body'])){
					exit(wp_send_json(['Error' => 'No response data received']));
				}

				$resultCode 						= $response['Body']['stkCallback']['ResultCode'];
				$resultDesc 						= $response['Body']['stkCallback']['ResultDesc'];
				$merchantRequestID 					= $response['Body']['stkCallback']['MerchantRequestID'];
				$checkoutRequestID 					= $response['Body']['stkCallback']['CheckoutRequestID'];

				$post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
				wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);

				$order_id 							= get_post_meta($post, '_order_id', true);
				$amount_due 						=  get_post_meta($post, '_amount', true);
				$before_ipn_paid 					= get_post_meta($post, '_paid', true);

				if(wc_get_order($order_id)){
					$order 							= new WC_Order($order_id);
					$first_name 					= $order->get_billing_first_name();
					$last_name 						= $order->get_billing_last_name();
					$customer 						= "{$first_name} {$last_name}";
				} else {
					$customer 						= "MPesa Customer";
				}

				if(isset($response['Body']['stkCallback']['CallbackMetadata'])){
					$amount 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
					$mpesaReceiptNumber 			= $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
					$balance 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
					$transactionDate 				= $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
					$phone 							= $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

					$after_ipn_paid = round($before_ipn_paid)+round($amount);
					$ipn_balance = $after_ipn_paid-$amount_due;

					if(wc_get_order($order_id)){
						$order = new WC_Order($order_id);
						
						if ($ipn_balance == 0) {
							$mpesa = new WC_Gateway_MPESA();
							$order->update_status('complete');
							$order->payment_complete();
							$order->add_order_note(__("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
							update_post_meta($post, '_order_status', 'complete');

							$headers = 'From: '.get_bloginfo('name').' <'.get_bloginifo('admin_email').'>' . "\r\n";
							wp_mail($order["billing_address"], 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. '.$amount.' on '.$transactionDate.'.', $headers);
						} elseif ($ipn_balance < 0) {
							$currency = get_woocommerce_currency();
							$order->payment_complete();
							$order->add_order_note(__("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
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
				} else {
					if(wc_get_order($order_id)){
						$order = new WC_Order($order_id);
						$order->update_status('on-hold');
						$order->add_order_note(__("MPesa Error {$resultCode}: {$resultDesc}"));
					}
				}
				
				exit(wp_send_json(Osen\Mpesa\STK::reconcile()));
                break;
				
            case "status":
				$transaction = $_POST['transaction'];
                exit(wp_send_json(Osen\Mpesa\STK::status($transaction)));
                break;
				
            case "result":
				$response = json_decode(file_get_contents('php://input'), true);

				$result = $response['Result'];

				$ResultType = $result['ResultType'];
				$ResultCode = $result['ResultType'];
				$ResultDesc = $result['ResultType'];
				$OriginatorConversationID = $result['ResultType'];
				$ConversationID = $result['ResultType'];
				$TransactionID = $result['ResultType'];
				$ResultParameters = $result['ResultType'];

				$ResultParameter = $result['ResultType'];

				$ReceiptNo = $ResultParameter[0]['Value'];
				$ConversationID = $ResultParameter[0]['Value'];
				$FinalisedTime = $ResultParameter[0]['Value'];
				$Amount = $ResultParameter[0]['Value'];
				$ReceiptNo = $ResultParameter[0]['Value'];
				$TransactionStatus = $ResultParameter[0]['Value'];
				$ReasonType = $ResultParameter[0]['Value'];
				$TransactionReason = $ResultParameter[0]['Value'];
				$DebitPartyCharges = $ResultParameter[0]['Value'];
				$DebitAccountType = $ResultParameter[0]['Value'];
				$InitiatedTime = $ResultParameter[0]['Value'];
				$OriginatorConversationID = $ResultParameter[0]['Value'];
				$CreditPartyName = $ResultParameter[0]['Value'];
				$DebitPartyName = $ResultParameter[0]['Value'];

				$ReferenceData = $result['ReferenceData'];
				$ReferenceItem = $ReferenceData['ReferenceItem'];
				$Occasion = $ReferenceItem[0]['Value'];
                exit(wp_send_json(Osen\Mpesa\STK::validate()));
                break;

			case "timeout":
				$response = json_decode(file_get_contents('php://input'), true);

				if(! isset($response['Body'])){
					exit(wp_send_json(['Error' => 'No response data received']));
				}
				
				$resultCode 					= $response['Body']['stkCallback']['ResultCode'];
				$resultDesc 					= $response['Body']['stkCallback']['ResultDesc'];
				$merchantRequestID 				= $response['Body']['stkCallback']['MerchantRequestID'];
				$checkoutRequestID 				= $response['Body']['stkCallback']['CheckoutRequestID'];

				$post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
				wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);
				update_post_meta($post, '_order_status', 'pending');

				$order_id = get_post_meta($post, '_order_id', true);
				if(wc_get_order($order_id)){
					$order = new WC_Order($order_id);
					
					$order->update_status('pending');
					$order->add_order_note(__("MPesa Payment Timed Out", 'woocommerce'));
				}

				exit(wp_send_json(Osen\Mpesa\STK::timeout()));
				break;
            default:
				exit(wp_send_json(Osen\Mpesa\C2B::register()));
        }
    }
}