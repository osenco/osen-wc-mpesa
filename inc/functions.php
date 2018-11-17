<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

function wc_mpesa_reconcile( $response ){
	if( empty( $response ) ){
    	return;
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
    	return;
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

add_action( 'init', function() {
    add_rewrite_rule( '^/wcmpesa_register/?([^/]*)/?', 'index.php?wcmpesa_register=1', 'top' );
    add_rewrite_rule( '^/wcmpesa_confirm/?([^/]*)/?', 'index.php?wcmpesa_confirm=1', 'top' );
    add_rewrite_rule( '^/wcmpesa_validate/?([^/]*)/?', 'index.php?wcmpesa_validate=1', 'top' );
    add_rewrite_rule( '^/wcmpesa_reconcile/?([^/]*)/?', 'index.php?wcmpesa_reconcile=1', 'top' );
    add_rewrite_rule( '^/wcmpesa_timeout/?([^/]*)/?', 'index.php?wcmpesa_timeout=1', 'top' );
} );

add_filter( 'query_vars', function( $query_vars ) {
    $query_vars[] = 'wcmpesa_register';
    $query_vars[] = 'wcmpesa_confirm';
    $query_vars[] = 'wcmpesa_validate';
    $query_vars[] = 'wcmpesa_reconcile';
    $query_vars[] = 'wc_mpesa_timeout';

    return $query_vars;
} );

add_action( 'wp', function() {

    if ( get_query_var( 'wcmpesa_register' ) ){
		header( "Access-Control-Allow-Origin: *" );
		header( 'Content-Type:Application/json' );
		wp_send_json( c2b_register() );
    } elseif ( get_query_var( 'wcmpesa_confirm' ) ){
		header( "Access-Control-Allow-Origin: *" );
		header( 'Content-Type:Application/json' );
    	$response = json_decode( file_get_contents( 'php://input' ), true );
    	$data = isset( $response['Body'] ) ? $response['Body'] : '';
    
    	wp_send_json( c2b_confirm( null, $data ) );
    } elseif ( get_query_var( 'wcmpesa_validate' ) ){
		header( "Access-Control-Allow-Origin: *" );
		header( 'Content-Type:Application/json' );
		$response = json_decode( file_get_contents( 'php://input' ), true );
		$data = isset( $response['Body'] ) ? $response['Body'] : '';

		wp_send_json( c2b_validate( null, $data ) );
	} elseif ( get_query_var( 'wcmpesa_reconcile' ) ){
		header( "Access-Control-Allow-Origin: *" );
		header( 'Content-Type:Application/json' );
	    $response = json_decode( file_get_contents( 'php://input' ), true );		    
	    $data = isset( $response['Body'] ) ? $response['Body'] : '';

	    c2b_reconcile( 'wc_mpesa_reconcile', $data );
	} elseif ( get_query_var( 'wcmpesa_timeout' ) ){
		header( "Access-Control-Allow-Origin: *" );
		header( 'Content-Type:Application/json' );
	    $response = json_decode( file_get_contents( 'php://input' ), true );
	    $data = isset( $response['Body'] ) ? $response['Body'] : '';

	    c2b_timeout( 'wc_mpesa_timeout', $data );
	}
} );
