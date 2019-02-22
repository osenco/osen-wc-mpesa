<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

add_action( 'plugins_loaded', 'wc_mpesa_config', 11 );
function wc_mpesa_config() 
{
	$mpesa = new \WC_MPESA_Gateway();
	\MpesaC2BWP::set(
		array(
			'env' 			=> $mpesa->get_option( 'env' ),
			'business' 		=> $mpesa->get_option( 'business' ),
			'appkey' 		=> $mpesa->get_option( 'key' ),
			'appsecret' 	=> $mpesa->get_option( 'secret' ),
			'headoffice' 	=> $mpesa->get_option( 'headoffice', '174379' ),
			'shortcode' 	=> $mpesa->get_option( 'shortcode', '174379' ),
			'type'	 		=> $mpesa->get_option( 'idtype', 4 ),
			'validate' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/validate/action/0/base/c2b/',
			'confirm' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/confirm/action/0/base/c2b/',
			'reconcile' 	=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/reconcile/action/wc_mpesa_reconcile/base/c2b/',
			'timeout' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/timeout/action/wc_mpesa_timeout/base/c2b/'
		)
	);

	//b2c
	$b2c = get_option( 'b2c_wcmpesa_options' );
	\MpesaB2C::set(
		array(
			'env' 			=> $b2c['env'],
			'appkey' 		=> $b2c['appkey'],
			'appsecret' 	=> $b2c['appsecret'],
			'shortcode' 	=> $b2c['shortcode'],
			'type'	 		=> $b2c['type'],
			'username'	 	=> $b2c['username'],
			'password'	 	=> $b2c['password'],
			'validate' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/validate/action/0/base/b2c/',
			'confirm' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/confirm/action/0/base/b2c/',
			'reconcile' 	=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/reconcile/action/wc_mpesa_reconcile/base/b2c/',
			'timeout' 		=> rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/timeout/action/wc_mpesa_timeout/base/b2c/'
		)
	);
}

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

	if( isset( $response['stkCallback']['CallbackMetadata'] ) ){
		$amount 						= $response['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
		$mpesaReceiptNumber 			= $response['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
		$balance 						= $response['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
		$transactionDate 				= $response['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
		$phone 							= $response['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

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
        array(
        	'wcmpesa/([\w+]*)' => 'index.php?wcmpesa=$matches[1]', 
        	'wcmpesa/([\w+]*)/action/([\w+]*)' => 'index.php?wcmpesa=$matches[1]&action=$matches[2]', 
        	'wcmpesa/([\w+]*)/action/([\w+]*)/base/([\w+]*)' => 'index.php?wcmpesa=$matches[1]&action=$matches[2]&base=$matches[3]'
        ),
        $wp_rewrite->rules
    );
} );

add_filter( 'query_vars', function( $query_vars ) {
    $query_vars[] = 'wcmpesa';
    $query_vars[] = 'action';
    $query_vars[] = 'base';
    return $query_vars;
} );

add_action( 'template_redirect', function() {
    $route 			= get_query_var( 'wcmpesa' );
    $action 		= get_query_var( 'action', null );
    $api 			= get_query_var( 'base', 'c2b' );

    if ( !empty( $route ) ) {
		$response 	= json_decode( file_get_contents( 'php://input' ), true );
		$data 		= isset( $response['Body'] ) ? $response['Body'] : array();
    	$action 	= ( $action == '0' ) ? null : $action;

    	wp_send_json( 
    		call_user_func_array( 
      			array( 
      				'Mpesa'.strtoupper( $api ),
      				$route
      			), 
      			array( 
      				$action, 
      				$data 
      			) 
      		)
    	);

        die;
    }
} );