<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

use WC_Orders_Tracking;

class Utilities
{
    public function __construct()
    {
        add_action('manage_shop_order_posts_custom_column', array($this, 'wc_mpesa_order_list_column_content'), 10, 1);
        add_filter('woocommerce_email_attachments', array($this, 'woocommerce_emails_attach_downloadables'), 10, 3);
        add_filter('manage_edit-shop_order_columns', array($this, 'wc_mpesa_order_column'), 100);
    }

    /**
     * Add a custom column before "actions" last column
     * 
     * @param array $columns
     */
    public function wc_mpesa_order_column($columns)
    {
        $ordered_columns = array();

        foreach ($columns as $key => $column) {
            $ordered_columns[$key] = $column;
            if ('order_date' === $key) {
                $ordered_columns['transaction_id'] = __('Receipt', 'woocommerce');
            }
        }

        return $ordered_columns;
    }

    /**
     * Column
     * 
     * @param string $column
     */
    public function wc_mpesa_order_list_column_content($column)
    {
        global $post;
        $the_order = wc_get_order($post->ID);

        if ('transaction_id' === $column) {
            echo $the_order->get_transaction_id() ?? 'N/A';
        }
    }

    public function woocommerce_emails_attach_downloadables($attachments, $status, \WC_Order $order)
    {
        if (!is_object($order) || !isset($status)) {
            return $attachments;
        }

        if (empty($order)) {
            return $attachments;
        }

        if (method_exists($order, 'has_downloadable_item')) {
            if (!$order->has_downloadable_item()) {
                return $attachments;
            }

            $allowed_statuses = array('customer_invoice', 'customer_completed_order');
            if (isset($status) && in_array($status, $allowed_statuses)) {
                foreach ($order->get_items() as $item) {
                    foreach ($order->get_item_downloads($item) as $download) {
                        $attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
                    }
                }
            }
        }

        return $attachments;
    }
}
