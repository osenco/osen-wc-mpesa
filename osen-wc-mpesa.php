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
if (!defined('ABSPATH')){
	exit;
}

define('WCM_VER', '1.19.0');
if (! defined('WCM_PLUGIN_FILE')) {
	define('WCM_PLUGIN_FILE', __FILE__);
}

add_action('wp', function (){
	if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
		deactivate_plugins(plugin_basename(__FILE__));
	}
});

register_activation_hook(__FILE__, 'wc_mpesa_activation_check');
function wc_mpesa_activation_check() 
{

	if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
		deactivate_plugins(plugin_basename(__FILE__));
		exit('Please Install/Activate WooCommerce for the MPesa extension to work');
	}

    if (! is_plugin_active('woocommerce/woocommerce.php')){
        deactivate_plugins(plugin_basename(__FILE__));
    }

}

add_action('activated_plugin', 'wc_mpesa_detect_plugin_activation', 10, 2);
function wc_mpesa_detect_plugin_activation($plugin, $network_activation) {
    if($plugin == 'osen-wc-mpesa/osen-wc-mpesa.php'){
        exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')));
    }
}

add_action('deactivated_plugin', 'wc_mpesa_detect_woocommerce_deactivation', 10, 2);
function wc_mpesa_detect_woocommerce_deactivation($plugin, $network_activation)
{
    if ($plugin == 'woocommerce/woocommerce.php'){
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'mpesa_action_links');
function mpesa_action_links($links)
{
	return array_merge(
		$links, 
		array(
			'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa').'">&nbsp;Setup C2B</a>', 
			'<a href="'.admin_url('edit.php?post_type=mpesaipn&page=wc_mpesa_b2c_preferences').'">&nbsp;Setup B2C</a>' 
		)
	);
} 

add_filter('plugin_row_meta', 'mpesa_row_meta', 10, 2);
function mpesa_row_meta($links, $file)
{
	$plugin = plugin_basename(__FILE__);

	if ($plugin == $file) {
		$row_meta = array(
			'github'    => '<a href="' . esc_url('https://github.com/osenco/osen-wc-mpesa/') . '" target="_blank" aria-label="' . esc_attr__('Contribute on Github', 'woocommerce') . '">' . esc_html__('Github', 'woocommerce') . '</a>',
			'apidocs' => '<a href="' . esc_url('https://developer.safaricom.co.ke/docs/') . '" target="_blank" aria-label="' . esc_attr__('MPesa API Docs (Daraja)', 'woocommerce') . '">' . esc_html__('API docs', 'woocommerce') . '</a>'
		);

		return array_merge($links, $row_meta);
	}

	return (array) $links;
}

spl_autoload_register(function ($class)
{
	if (substr($class, 0, 4) == 'Osen') {
		$class = str_replace('Osen\\', '', $class);
		$file = str_replace('\\', '/', $class);

		require_once plugin_dir_path(__FILE__)."src/{$file}.php";
	}
});

/**
 * Initialize all our custom post types
 */
Osen\Post\Types\C2B::init();
Osen\Post\Types\B2C::init();

/**
 * Initialize our admin menus
 */
Osen\Menus\Menu::init();

/**
 * Initialize settings pages for B2C API
 */
Osen\Settings\B2C::init();
Osen\Settings\Withdraw::init();

/**
 * Initialize metaboxes for C2B API
 */
Osen\Post\Metaboxes\C2B::init();

// Stk
$c2b = get_option('woocommerce_mpesa_settings');
Osen\Mpesa\STK::set(
	array(
		'env' 			=> isset($c2b['env']) ? $c2b['env'] : 'sandbox',
		'appkey' 		=> isset($c2b['key']) ? $c2b['key'] : '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
		'appsecret' 	=> isset($c2b['secret']) ? $c2b['secret'] : '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
		'headoffice' 	=> isset($c2b['headoffice']) ? $c2b['headoffice'] : '174379',
		'shortcode' 	=> isset($c2b['shortcode']) ? $c2b['shortcode'] : '174379',
		'type'	 		=> isset($c2b['idtype']) ? $c2b['idtype'] : 4,
		'passkey'	 	=> isset($c2b['passkey']) ? $c2b['passkey'] : 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
		'validate' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/validate/action/0/base/c2b/',
		'confirm' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/confirm/action/0/base/c2b/',
		'reconcile' 	=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/reconcile/action/wc_mpesa_reconcile/base/c2b/',
		'timeout' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/timeout/action/wc_mpesa_timeout/base/c2b/'
	)
);

// c2b
Osen\Mpesa\C2B::set(
	array(
		'env' 			=> isset($c2b['env']) ? $c2b['env'] : 'sandbox',
		'appkey' 		=> isset($c2b['key']) ? $c2b['key'] : '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
		'appsecret' 	=> isset($c2b['secret']) ? $c2b['secret'] : '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
		'headoffice' 	=> isset($c2b['headoffice']) ? $c2b['headoffice'] : '174379',
		'shortcode' 	=> isset($c2b['shortcode']) ? $c2b['shortcode'] : '174379',
		'type'	 		=> isset($c2b['idtype']) ? $c2b['idtype'] : 4,
		'passkey'	 	=> isset($c2b['passkey']) ? $c2b['passkey'] : 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
		'validate' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/validate/action/0/base/c2b/',
		'confirm' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/confirm/action/0/base/c2b/',
		'reconcile' 	=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/reconcile/action/wc_mpesa_reconcile/base/c2b/',
		'timeout' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/timeout/action/wc_mpesa_timeout/base/c2b/'
	)
);

//b2c
$b2c = get_option('b2c_wcmpesa_options');
Osen\Mpesa\B2C::set(
	array(
		'env' 			=> isset($b2c['env']) ? $b2c['env'] : 'sandbox',
		'appkey' 		=> isset($b2c['key']) ? $b2c['key'] : '',
		'appsecret' 	=> isset($b2c['secret']) ? $b2c['secret'] : '',
		'headoffice' 	=> isset($b2c['headoffice']) ? $b2c['headoffice'] : '',
		'shortcode' 	=> isset($b2c['shortcode']) ? $b2c['shortcode'] : '',
		'type'	 		=> isset($b2c['idtype']) ? $b2c['idtype'] : 4,
		'passkey'	 	=> isset($b2c['passkey']) ? $b2c['passkey'] : '',
		'username'	 	=> isset($b2c['username']) ? $b2c['username'] : '',
		'password'	 	=> isset($b2c['password']) ? $b2c['password'] : '',
		'validate' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/validate/action/0/base/b2c/',
		'confirm' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/confirm/action/0/base/b2c/',
		'reconcile' 	=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/reconcile/action/wc_mpesa_reconcile/base/b2c/',
		'timeout' 		=> rtrim(home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/wcmpesa/timeout/action/wc_mpesa_timeout/base/b2c/'
	)
);

/**
 * Load Custom Plugin Functions
 */
foreach (glob(plugin_dir_path(__FILE__) . 'inc/*.php') as $filename) {
	require_once $filename;
}
