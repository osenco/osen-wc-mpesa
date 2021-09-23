<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

class Initialize
{
    public function __construct()
    {
        register_activation_hook('osen-wc-mpesa/osen-wc-mpesa.php', array($this, 'wc_mpesa_activation_check'));
        add_filter('plugin_row_meta', array($this, 'mpesa_row_meta'), 10, 2);
        add_action('init', array($this, 'wc_mpesa_flush_rewrite_rules_maybe'), 20);
        add_action('activated_plugin', array($this, 'wc_mpesa_detect_plugin_activation'), 10, 2);
        add_action('deactivated_plugin', array($this, 'wc_mpesa_detect_woocommerce_deactivation'), 10, 2);
        add_filter('plugin_action_links_osen-wc-mpesa/osen-wc-mpesa.php', array($this, 'mpesa_action_links'));
        add_action('wp_enqueue_scripts', array($this, 'osen_wc_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'osen_admin_scripts'));
    }

    public function wc_mpesa_activation_check()
    {
        if (!get_option('wc_mpesa_flush_rewrite_rules_flag')) {
            add_option('wc_mpesa_flush_rewrite_rules_flag', true);
        }

        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            deactivate_plugins('osen-wc-mpesa/osen-wc-mpesa.php');

            add_action('admin_notices', function () {
                $class   = 'notice notice-error is-dismissible';
                $message = __('Please Install/Activate WooCommerce for this extension to work..', 'woocommerce');

                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            });
        }
    }

    public function wc_mpesa_flush_rewrite_rules_maybe()
    {
        if (get_option('wc_mpesa_flush_rewrite_rules_flag')) {
            flush_rewrite_rules();
            delete_option('wc_mpesa_flush_rewrite_rules_flag');
        }
    }

    public function wc_mpesa_detect_plugin_activation($plugin, $network_activation)
    {
        if ($plugin == 'osen-wc-mpesa/osen-wc-mpesa.php') {
            flush_rewrite_rules();
            exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')));
        }
    }

    public function wc_mpesa_detect_woocommerce_deactivation($plugin, $network_activation)
    {
        if ($plugin == 'woocommerce/woocommerce.php') {
            deactivate_plugins('osen-wc-mpesa/osen-wc-mpesa.php');
        }
    }

    public function mpesa_action_links($links)
    {
        return array_merge(
            $links,
            array(
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa') . '">&nbsp;STK & C2B Setup</a>',
                '<a href="' . admin_url('admin.php?page=wc_mpesa_about') . '">&nbsp;About</a>',
            )
        );
    }

    public function mpesa_row_meta($links, $file)
    {
        $plugin = 'osen-wc-mpesa/osen-wc-mpesa.php';

        if ($plugin === $file) {
            $row_meta = array(
                'github'  => '<a href="' . esc_url('https://github.com/osenco/osen-wc-mpesa/') . '" target="_blank" aria-label="' . esc_attr__('Contribute on Github', 'woocommerce') . '">' . esc_html__('Github', 'woocommerce') . '</a>',
                'apidocs' => '<a href="' . esc_url('https://developer.safaricom.co.ke/docs/') . '" target="_blank" aria-label="' . esc_attr__('MPesa API Docs (Daraja)', 'woocommerce') . '">' . esc_html__('API docs', 'woocommerce') . '</a>',
            );

            return array_merge($links, $row_meta);
        }

        return (array) $links;
    }

    function js_home_url()
    {
        echo '<script type="text/javascript">
        var MPESA_HOME_URL = "' . \home_url('') . '"
        </script>';
    }

    public function osen_wc_scripts()
    {
        if (is_checkout()) {
            wp_enqueue_style("wc-mpesa-3-0", plugins_url("osen-wc-mpesa/assets/styles.css"));
            wp_enqueue_script('jquery');
            wp_enqueue_script("wc-mpesa-3-0", plugins_url("osen-wc-mpesa/assets/scripts.js"), array("jquery"), time(), true);
            wp_add_inline_script("wc-mpesa-3-0", 'var MPESA_RECEIPT_URL = "' . home_url('wc-api/lipwa_receipt') . '"', 'before');
        }
    }

    public function osen_admin_scripts()
    {
        wp_enqueue_style("c3", plugins_url("osen-wc-mpesa/assets/c3/c3.min.css", "jquery"));
        wp_enqueue_script("c3", plugins_url("osen-wc-mpesa/assets/c3/c3.bundle.js", "jquery"));
        wp_enqueue_script("wc-mpesa-settings", plugins_url("osen-wc-mpesa/assets/admin_scripts.js", "jquery"));
    }
}
