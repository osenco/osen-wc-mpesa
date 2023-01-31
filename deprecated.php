<?php

/**
 * @package M-Pesa For WooCommerce
 * @subpackage Deprecated functionality
 * @version 1.0.0
 * @since 3.0.0
 * @author  Osen Concepts http://osen.co.ke
 * This file containtains functionality that is no longer used and will be removed in the future.
 */

use Osen\Woocommerce\Mpesa\C2B;
use Osen\Woocommerce\Mpesa\STK;

/**
 * Check for current vendor ID
 *
 * @param WC_Order $order
 * @return int|null
 */
function wc_depecrated_check_vendor(WC_Order $order)
{
	$vendor_id   = null;
	$order_items = $order->get_items('line_item');

	if (function_exists('wcfm_get_vendor_id_by_post') && !empty($order_items)) {
		foreach ($order_items as $item) {
			$line_item  = new WC_Order_Item_Product($item);
			$product_id = $line_item->get_product_id();
			$vendor_id  = wcfm_get_vendor_id_by_post($product_id);
		}
	}

	if (class_exists('WC_Product_Vendors_Utils')) {
		foreach ($order_items as $item) {
			$line_item  = new WC_Order_Item_Product($item);
			$product_id = $line_item->get_product_id();
			$vendor_id  = call_user_func_array(
				['WC_Product_Vendors_Utils', 'get_vendor_id_from_product'],
				[$product_id]
			);
		}
	}

	return $vendor_id;
}

add_action('init', function () {
	add_rewrite_rule('lipwa/([^/]*)/?', 'index.php?lipwa=$matches[1]', 'top');
	add_rewrite_rule('wcpesa/([^/]*)/?', 'index.php?wcpesa=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
		array_push($vars, 'lipwa');
		array_push($vars, 'wcpesa');
		return $vars;
}, 10, 1 );

add_action( 'template_redirect', function () {
	if (get_query_var('lipwa') || get_query_var('wcpesa')) {
		header("Access-Control-Allow-Origin: *");
		header("Content-Type: Application/json");

		$action   = get_query_var('lipwa', 'something_ominous');
		$settings = get_option('woocommerce_mpesa_settings');

		if (get_query_var('wcpesa')) {
			$action = get_query_var('wcpesa', 'something_ominous');
		}

		switch ($action) {
			case "request":
				$order_id  = sanitize_text_field($_POST['order']);
				$order     = new \WC_Order($order_id);
				$vendor_id = wc_depecrated_check_vendor($order);
				$total     = $order->get_total();
				$phone    = get_post_meta($order_id, 'mpesa_phone', true) ?? $order->get_billing_phone();
				$mpesa     = new STK($vendor_id);
				$result    = $mpesa->authorize(get_transient('mpesa_token'))
					->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');

				if (isset($result['MerchantRequestID'])) {
					update_post_meta($order_id, 'mpesa_request_id', $result['MerchantRequestID']);
				}

				wp_send_json($result);
				break;
			case "validate":
				wp_send_json((new STK)->validate());
				break;

			case "reconcile":
				$mpesa = new STK();

				wp_send_json($mpesa->reconcile(function ($response) use ($settings) {
					if (isset($response['Body'])) {
						$resultCode        = $response['Body']['stkCallback']['ResultCode'];
						$resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
						$merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];
						$order_id          = wc_clean($_GET['order'] ?? wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $merchantRequestID));

						if (wc_get_order($order_id)) {
							$order = new \WC_Order($order_id);

							if ($order->get_status() === 'completed') {
								return;
							}

							if (isset($response['Body']['stkCallback']['CallbackMetadata'])) {
								$parsed = array();
								foreach ($response['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
									$parsed[$item['Name']] = $item['Value'];
								}

								$order->set_transaction_id($parsed['MpesaReceiptNumber']);
								$order->update_status(
									($settings['completion'] ?? 'completed'),
									__("Full M-Pesa Payment Received From {$parsed['PhoneNumber']}. Transaction ID {$parsed['MpesaReceiptNumber']}.")
								);
								$order->save();

								do_action('send_to_external_api', $order, $parsed, $this->settings);
							} else {
								$order->update_status(
									'on-hold',
									__("(M-Pesa Error) {$resultCode}: {$resultDesc}.")
								);
							}

							return true;
						}
					}

					return false;
				}));
				break;

			case "confirm":
				wp_send_json((new STK)->confirm(function ($response = array()) {
					if (empty($response)) {
						wp_send_json(
							['Error' => 'No response data received']
						);
					}

					$MpesaReceiptNumber = $response['TransID'];
					$TransactionDate    = $response['TransTime'];
					$Amount             = (int) $response['TransAmount'];
					$BillRefNumber      = $response['BillRefNumber'];
					$PhoneNumber        = $response['MSISDN'];
					$FirstName          = $response['FirstName'];
					$MiddleName         = $response['MiddleName'];
					$LastName           = $response['LastName'];
					$parsed             = compact("Amount", "MpesaReceiptNumber", "TransactionDate", "PhoneNumber");
					$order_id           = $BillRefNumber ?? wc_mpesa_post_id_by_meta_key_and_value('mpesa_reference', $BillRefNumber);

					if (wc_get_order($order_id)) {
						$order       = new \WC_Order($order_id);
						$total       = round($order->get_total());
						$ipn_balance = $total - round($Amount);

						if ($order->get_status() === 'completed') {
							return;
						}

						if ($ipn_balance === 0) {
							$order->update_status(
								($settings['completion'] ?? 'completed'),
								__("Full M-Pesa Payment Received From {$PhoneNumber}. Transaction ID {$MpesaReceiptNumber}")
							);
							$order->set_transaction_id($MpesaReceiptNumber);
							$order->save();

							do_action('send_to_external_api', $order, $parsed, $this->settings);

							return true;
						} elseif ($ipn_balance < 0) {
							$currency = get_woocommerce_currency();
							$order->update_status(
								($settings['completion'] ?? 'completed'),
								__("{$PhoneNumber} has overpayed by {$currency} {$ipn_balance}. Transaction ID {$MpesaReceiptNumber}")
							);
							$order->set_transaction_id($MpesaReceiptNumber);
							$order->save();

							do_action('send_to_external_api', $order, $parsed, $this->settings);

							return true;
						} else {
							$order->update_status(
								'on-hold',
								__("M-Pesa Payment from {$PhoneNumber} Incomplete")
							);
						}
					}

					return false;
				}));
				break;

			case "register":
				(new C2B)->register(function ($response) {
					$status = isset($response['ResponseDescription']) ? 'success' : 'fail';
					if ($status === 'fail') {
						$message = isset($response['errorMessage']) ? $response['errorMessage'] : 'Could not register M-PESA URLs, try again later.';
						$state   = 'error';
					} else {
						$message = isset($response['ResponseDescription']) ? $response['ResponseDescription'] : 'M-PESA URL registered successfully. You will now receive C2B Payment Notifications.';
						$state   = 'success';
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

			case "status":
				$transaction = sanitize_text_field($_POST['transaction']);
				wp_send_json((new STK)->status($transaction));
				break;

			case "result":
				$response = json_decode(file_get_contents('php://input'), true);

				$result                   = $response['Result'];
				$ResultType               = $result['ResultType'];
				$ResultCode               = $result['ResultCode'];
				$ResultDesc               = $result['ResultDesc'];
				$OriginatorConversationID = $result['OriginatorConversationID'];
				$TransactionID            = $result['TransactionID'];

				$ResultParameters = $result['ResultParameters'];
				$ResultParameter  = $ResultParameters['ResultParameters']['ResultParameter'];

				$ReceiptNo         = $ResultParameter[0]['Value'];
				$ConversationID    = $ResultParameter[0]['Value'];
				$FinalisedTime     = $ResultParameter[0]['Value'];
				$Amount            = $ResultParameter[0]['Value'];
				$TransactionStatus = $ResultParameter[0]['Value'];
				$ReasonType        = $ResultParameter[0]['Value'];
				$TransactionReason = $ResultParameter[0]['Value'];
				$DebitPartyCharges = $ResultParameter[0]['Value'];
				$DebitAccountType  = $ResultParameter[0]['Value'];
				$InitiatedTime     = $ResultParameter[0]['Value'];
				$CreditPartyName   = $ResultParameter[0]['Value'];
				$DebitPartyName    = $ResultParameter[0]['Value'];

				$ReferenceData = $result['ReferenceData'];
				$ReferenceItem = $ReferenceData['ReferenceItem'];
				$Occasion      = $ReferenceItem[0]['Value'];

				$order_id = wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $OriginatorConversationID);
				$order    = new \WC_Order($order_id);

				if (wc_get_order($order_id)) {
					$order->update_status('refunded', __($ResultDesc, 'woocommerce'));
					$order->set_transaction_id($TransactionID);
					$order->save();
				} else {
					$order->update_status('processing', __("{$ResultCode}: {$ResultDesc}", 'woocommerce'));
				}

				wp_send_json((new STK)->validate());
				break;

			case "timeout":
				$response = json_decode(file_get_contents('php://input'), true);

				if (!isset($response['Body'])) {
					exit(wp_send_json(['Error' => 'No response data received']));
				}

				$resultCode        = $response['Body']['stkCallback']['ResultCode'];
				$resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
				$merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];

				$order_id = wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $merchantRequestID);
				if (wc_get_order($order_id)) {
					$order = new \WC_Order($order_id);

					$order->update_status(
						'pending',
						__("M-Pesa Payment Timed Out", 'woocommerce')
					);
				}

				wp_send_json((new STK)->timeout());
				break;
			default:
				wp_send_json((new C2B)->register());
		}
	}
});
