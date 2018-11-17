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

define( 'WCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCM_MAIN', WCM_DIR.'mpesa/' );
define( 'WCM_FUNC', WCM_DIR.'inc/' );
define( 'WCM_WC', WCM_DIR.'wc/' );
define( 'WCM_VER', '1.9.0' );

require_once ( WCM_FUNC.'payments.php' );
require_once ( WCM_FUNC.'metaboxes.php' );
require_once ( WCM_MAIN.'c2b.php' );
require_once ( WCM_FUNC.'menu.php' );
require_once ( WCM_WC.'woocommerce.php' );
require_once ( WCM_FUNC.'functions.php' );