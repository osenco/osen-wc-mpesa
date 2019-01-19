<?php
/**
 * @package Mpesa For WooCommerce
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.10
 *
 * Plugin Name: MPesa For WooCommerce
 * Plugin URI: https://wc-mpesa.osen.co.ke/
 * Description: This plugin extends WordPress and WooCommerce functionality to integrate <cite>Mpesa</cite> for making and receiving online payments.
 * Author: Osen Concepts Kenya < hi@osen.co.ke >
 * Version: 1.10.0
 * Author URI: https://osen.co.ke/
 *
 * Requires at least: 4.4
 * Tested up to: 4.9.5
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ){
	exit;
}

if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	exit('Please install WooCommerce for this extension to work');
}

register_activation_hook(__FILE__, 'wc_mpesa_activation_check');
function wc_mpesa_activation_check() {
    if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ){
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

function wc_mpesa_detect_woocommerce_deactivation( $plugin, $network_activation ) {
    if ( $plugin == "woocommerce/woocommerce.php" ){
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}
add_action( 'deactivated_plugin', 'wc_mpesa_detect_woocommerce_deactivation', 10, 2 );

add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), 'mpesa_action_links' );
function mpesa_action_links( $links )
{
	return array_merge( $links, [ '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ).'">&nbsp;C2B</a>', '<a href="'.admin_url( 'edit.php?post_type=c2b_payment&page=wc_mpesa_b2c_preferences' ).'">&nbsp;B2C</a>' ] );
} 

add_filter( 'plugin_row_meta', 'mpesa_row_meta', 10, 2 );
function mpesa_row_meta( $links, $file )
{
	$plugin = plugin_basename( __FILE__ );

	if ( $plugin == $file ) {
		$row_meta = array( 
			'github'    => '<a href="' . esc_url( 'https://github.com/osenco/osen-wc-mpesa/' ) . '" target="_blank" aria-label="' . esc_attr__( 'Contribute on Github', 'woocommerce' ) . '">' . esc_html__( 'Github', 'woocommerce' ) . '</a>',
			'apidocs' => '<a href="' . esc_url( 'https://developer.safaricom.co.ke/docs/' ) . '" target="_blank" aria-label="' . esc_attr__( 'MPesa API Docs ( Daraja )', 'woocommerce' ) . '">' . esc_html__( 'API docs', 'woocommerce' ) . '</a>'
		 );

		return array_merge( $links, $row_meta );
	}

	return ( array ) $links;
}

define( 'WCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCM_MAIN', WCM_DIR.'mpesa/' );
define( 'WCM_FUNC', WCM_DIR.'inc/' );
define( 'WCM_WC', WCM_DIR.'wc/' );
define( 'WCM_VER', '1.9.0' );

require_once ( WCM_FUNC.'c2b-payments.php' );
require_once ( WCM_FUNC.'b2c-payments.php' );
require_once ( WCM_FUNC.'metaboxes.php' );
require_once ( WCM_MAIN.'c2b.php' );
require_once ( WCM_MAIN.'b2c.php' );
require_once ( WCM_FUNC.'b2c-settings.php' );
require_once ( WCM_FUNC.'menu.php' );
require_once ( WCM_WC.'woocommerce.php' );
require_once ( WCM_FUNC.'functions.php' );