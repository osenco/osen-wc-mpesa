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
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html

 * Copyright 2019  Osen Concepts 

 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USAv
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ){
	exit;
}

define( 'WCM_VER', '1.19.0' );
if ( ! defined( 'WCM_PLUGIN_FILE' ) ) {
	define( 'WCM_PLUGIN_FILE', __FILE__ );
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	exit('Please install WooCommerce for this extension to work');
}

register_activation_hook(__FILE__, 'wc_mpesa_activation_check');
function wc_mpesa_activation_check() 
{
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ){
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }

}

add_action( 'activated_plugin', 'wc_mpesa_detect_plugin_activation', 10, 2 );
function wc_mpesa_detect_plugin_activation( $plugin, $network_activation ) {
    if( $plugin == 'osen-wc-mpesa/osen-wc-mpesa.php' ){
        exit( wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ) ) );
    }
}

add_action( 'deactivated_plugin', 'wc_mpesa_detect_woocommerce_deactivation', 10, 2 );
function wc_mpesa_detect_woocommerce_deactivation( $plugin, $network_activation )
{
    if ( $plugin == 'woocommerce/woocommerce.php' ){
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), 'mpesa_action_links' );
function mpesa_action_links( $links )
{
	return array_merge( 
		$links, 
		array( 
			'<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ).'">&nbsp;Setup C2B</a>', 
			'<a href="'.admin_url( 'edit.php?post_type=mpesaipn&page=wc_mpesa_b2c_preferences' ).'">&nbsp;Setup B2C</a>' 
		)
	);
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

/**
 * Load Mpesa Functions
 */
foreach ( glob( plugin_dir_path( __FILE__) . 'mpesa/*.php' ) as $filename ) {
	require_once $filename;
}

/**
 * Load WooCommerce Functions
 */
foreach ( glob( plugin_dir_path( __FILE__) . 'wc/*.php' ) as $filename ) {
	require_once $filename;
}

/**
 * Load Custom Plugin Functions
 */
foreach ( glob( plugin_dir_path( __FILE__) . 'inc/*.php' ) as $filename ) {
	require_once $filename;
}