<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

function get_post_id_by_meta_key_and_value( $key, $value ) {
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

function wc_mpesa_reconcile( $response ){
	if( empty( $response ) ){
    	return array(
    		'errorCode' 	=> 1,
    		'errorMessage' 	=> 'Empty reconciliation response data' 
    	);
    }

    $resultCode 						= $response['stkCallback']['ResultCode'];
	$resultDesc 						= $response['stkCallback']['ResultDesc'];
	$merchantRequestID 					= $response['stkCallback']['MerchantRequestID'];
	$checkoutRequestID 					= $response['stkCallback']['CheckoutRequestID'];

	$post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );
	wp_update_post( [ 'post_content' => file_get_contents( 'php://input' ), 'ID' => $post ] );

    $order_id 							= get_post_meta( $post, '_order_id', true );
	$amount_due 						=  get_post_meta( $post, '_amount', true );
	$before_ipn_paid 					= get_post_meta( $post, '_paid', true );

	if( wc_get_order( $order_id ) ){
		$order 							= new WC_Order( $order_id );
		$first_name 					= $order->get_billing_first_name();
		$last_name 						= $order->get_billing_last_name();
		$customer 						= "{$first_name} {$last_name}";
	} else {
		$customer 						= "MPesa Customer";
	}

	if( isset( $response['Body']['stkCallback']['CallbackMetadata'] ) ){
		$amount 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
		$mpesaReceiptNumber 			= $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
		$balance 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
		$transactionDate 				= $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
		$phone 							= $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

		$after_ipn_paid = round($before_ipn_paid)+round($amount);
		$ipn_balance = $after_ipn_paid-$amount_due;

	    if( wc_get_order( $order_id ) ){
	    	$order = new WC_Order( $order_id );
	    	
	    	if ( $ipn_balance == 0 ) {
				$mpesa = new WC_MPESA_Gateway();
	            $order->update_status( $mpesa->get_option('completion') );
	        	$order->add_order_note( __( "Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}" ) );
				update_post_meta( $post, '_order_status', 'complete' );
	        } elseif ( $ipn_balance < 0 ) {
	        	$currency = get_woocommerce_currency();
	        	$order->payment_complete();
	            $order->add_order_note( __( "{$phone} has overpayed by {$currency} {$balance}. Receipt Number {$mpesaReceiptNumber}" ) );
				update_post_meta( $post, '_order_status', 'complete' );
	        } else {
	            $order->update_status( 'on-hold' );
	            $order->add_order_note( __( "MPesa Payment from {$phone} Incomplete" ) );
				update_post_meta( $post, '_order_status', 'on-hold' );
	        }
	    }

		update_post_meta( $post, '_paid', $after_ipn_paid );
		update_post_meta( $post, '_amount', $amount_due );
		update_post_meta( $post, '_balance', $balance );
		update_post_meta( $post, '_phone', $phone );
		update_post_meta( $post, '_customer', $customer );
		update_post_meta( $post, '_order_id', $order_id );
		update_post_meta( $post, '_receipt', $mpesaReceiptNumber );
	} else {
	    if( wc_get_order( $order_id ) ){
	    	$order = new WC_Order( $order_id );
	        $order->update_status( 'on-hold' );
	        $order->add_order_note( __( "MPesa Error {$resultCode}: {$resultDesc}" ) );
	    }
	}
}

function wc_mpesa_timeout( $response )
{
	if( empty( $response ) ){
    	return array(
    		'errorCode' 	=> 1,
    		'errorMessage' 	=> 'Empty timeout response data' 
    	);
    }
 	
 	$resultCode 					= $response['stkCallback']['ResultCode'];
	$resultDesc 					= $response['stkCallback']['ResultDesc'];
	$merchantRequestID 				= $response['stkCallback']['MerchantRequestID'];
	$checkoutRequestID 				= $response['stkCallback']['CheckoutRequestID'];

	$post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );
	wp_update_post( [ 'post_content' => file_get_contents( 'php://input' ), 'ID' => $post ] );
	update_post_meta( $post, '_order_status', 'pending' );

    $order_id = get_post_meta( $post, '_order_id', true );
    if( wc_get_order( $order_id ) ){
    	$order = new WC_Order( $order_id );
    	
        $order->update_status( 'pending' );
        $order->add_order_note( __( "MPesa Payment Timed Out", 'woocommerce' ) );
    }
}

add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ) {
    $wp_rewrite->rules = array_merge(
        ['wcmpesa/([\w+]*)/action/([\w+]*)' => 'index.php?wcmpesa=$matches[1]&action=$matches[2]'],
        $wp_rewrite->rules
    );
} );

add_filter( 'query_vars', function( $query_vars ) {
    $query_vars[] = 'wcmpesa';
    $query_vars[] = 'action';
    return $query_vars;
} );

add_action( 'template_redirect', function() {
    $route = get_query_var( 'wcmpesa' );
    $action = get_query_var( 'action' );

    if ( $route ) {
		$response 	= json_decode( file_get_contents( 'php://input' ), true );
		$data 		= isset( $response['Body'] ) ? $response['Body'] : array();

    	switch ( $route ) {
    		case 'confirm':
    			$data['transID'] = $action;
    			wp_send_json( c2b_confirm( null, $data ) );
    			break;

    		case 'validate':
    			$data['transID'] = $action;
    			wp_send_json( c2b_validate( null, $data ) );
    			break;

    		case 'register':
    			wp_send_json( c2b_register( $action ) );
    			break;

			case 'reconcile':
				wp_send_json( c2b_reconcile( $action, $data ) );
				break;

    		case 'timeout':
    			wp_send_json( c2b_timeout( $action, $data ) );
    			break;
    		
    		default:
    			$data['transID'] = $action;
    			wp_send_json( c2b_validate( null, $data ) );
    			break;
    	}
        
        die;
    }
} );