<?php

/**
 * @package Mpesa for WooCommerce
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 3.0.0
 *
 * Plugin Name: Osen WC Mpesa
 * Plugin URI: https://wcmpesa.co.ke/
 * Description: This plugin extends WordPress and WooCommerce functionality to integrate <cite>Mpesa</cite> for making and receiving online payments.
 * Author: Osen Concepts Kenya < hi@osen.co.ke >
 * Version: 3.0.0
 * Author URI: https://osen.co.ke/
 *
 * Requires at least: 4.6
 * Tested up to: 5.8.1
 *
 * WC requires at least: 3.5.0
 * WC tested up to: 5.1
 *
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html

 * Copyright 2021  Osen Concepts

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
if (!defined('ABSPATH')) {
	exit;
}

define('WCM_VER', '2.3.6');
if (!defined('WCM_PLUGIN_FILE')) {
	define('WCM_PLUGIN_FILE', __FILE__);
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

register_activation_hook(__FILE__, function () {
	set_transient('wc-mpesa-activation-notice', true, 5);
	
	if (!is_plugin_active('woocommerce/woocommerce.php')) {
		deactivate_plugins('osen-wc-mpesa/osen-wc-mpesa.php');

		add_action('admin_notices', function () {
			$class   = 'notice notice-error is-dismissible';
			$message = __('Please Install/Activate WooCommerce for this extension to work..', 'woocommerce');

			printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
		});
	}

	flush_rewrite_rules();
	exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')));
});

/**
 * Check transient and show notice only once - delete transient immediately after
 */
add_action('admin_notices', function () {
	if (get_transient('wc-mpesa-activation-notice')) {
		$class         = 'updated notice is-dismissible';
		$message       = 'Thank you for installing the M-Pesa for WooCommerce plugin! <strong>You are awesome</strong>!';
		$about_message = 'About M-Pesa for WooCommerce';
		$about_url     = admin_url('admin.php?page=wc_mpesa_about');
		$live_message  = 'How to Go Live';
		$live_url      = admin_url('admin.php?page=wc_mpesa_go_live');
		$btn_class     = 'button button-primary';

		printf(
			'<div class="%1$s"><p>%2$s</p><p><a class="button" href="%3$s">%4$s</a><a class="%5$s" href="%6$s">%7$s</a></p></div>',
			esc_attr($class),
			esc_html($message),
			esc_attr($about_url),
			esc_html($about_message),
			esc_attr($btn_class),
			esc_attr($live_url),
			esc_html($live_message)
		);

		delete_transient('wc-mpesa-activation-notice');
	}
});

add_action('wp_enqueue_scripts', function () {
	if (is_checkout()) {
		wp_enqueue_style("wc-mpesa-3-0", plugins_url("assets/styles.css", __FILE__));

		wp_enqueue_script('jquery');
		wp_enqueue_script("wc-mpesa-3-0", plugins_url("assets/scripts.js", __FILE__), array("jquery"), false, true);
		wp_add_inline_script("wc-mpesa-3-0", 'var MPESA_RECEIPT_URL = "' . home_url('wc-api/lipwa_receipt') . '"', 'before');
	}
});

add_action('admin_enqueue_scripts', function () {
	wp_enqueue_style("c3", plugins_url("assets/c3/c3.min.css", __FILE__));
	wp_enqueue_script("c3", plugins_url("assets/c3/c3.bundle.js", __FILE__), array("jquery"));
	wp_enqueue_script("wc-mpesa-settings", plugins_url("assets/admin_scripts.js", __FILE__), array("jquery"), false, true);
});

/**
 * Initialize all plugin features and utilities
 */
new Osen\Woocommerce\Initialize;
new Osen\Woocommerce\Utilities;

/**
 * Initialize metaboxes for C2B API
 */
new Osen\Woocommerce\Post\Metaboxes\C2B;

/**
 * Initialize our admin menus (submenus under WooCommerce)
 */
new Osen\Woocommerce\Admin\Menu;
