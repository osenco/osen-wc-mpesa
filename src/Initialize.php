<?php

/**
 * @package M-Pesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

class Initialize
{
    public function __construct()
    {
        add_filter('plugin_row_meta', array($this, 'mpesa_row_meta'), 10, 2);
        add_action('init', array($this, 'wc_mpesa_maybe_flush_rewrite_rules'), 20);
        add_action('deactivated_plugin', array($this, 'wc_mpesa_detect_woocommerce_deactivation'), 10, 2);
        add_filter('plugin_action_links_osen-wc-mpesa/osen-wc-mpesa.php', array($this, 'mpesa_action_links'));
    }

    public function wc_mpesa_maybe_flush_rewrite_rules()
    {
        if (get_option('wc_mpesa_flush_rewrite_rules_flag')) {
            flush_rewrite_rules();
            delete_option('wc_mpesa_flush_rewrite_rules_flag');
        }
    }

    public function wc_mpesa_detect_woocommerce_deactivation($plugin, $network_activation)
    {
        if ($plugin == 'woocommerce/woocommerce.php') {
            deactivate_plugins('osen-wc-mpesa/osen-wc-mpesa.php');
        }
    }

    /**
     * @param array $links
     * @return array
     */
    public function mpesa_action_links($links)
    {
        return array_merge(
            $links,
            array(
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa') . '">&nbsp;Configuration</a>',
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
                'apidocs' => '<a href="' . esc_url('https://developer.safaricom.co.ke/docs/') . '" target="_blank" aria-label="' . esc_attr__('M-Pesa API Docs (Daraja)', 'woocommerce') . '">' . esc_html__('API docs', 'woocommerce') . '</a>',
            );

            return array_merge($links, $row_meta);
        }

        return (array) $links;
    }
}
