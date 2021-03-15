<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

use Osen\Woocommerce\Mpesa\C2B;
use Osen\Woocommerce\Mpesa\STK;

class Utilities
{
    public function __construct()
    {
        //add_filter('manage_edit-shop_order_columns', 'wcmpesa_new_order_column');
        add_action('manage_shop_order_custom_column', array($this, 'shop_order_payments_table_column_content'), 10);

        add_filter('manage_edit-shop_order_columns', array($this, 'wc_mpesa_order_column'), 100);
        add_action('manage_shop_order_posts_custom_column', array($this, 'wc_mpesa_order_list_column_content'), 10, 1);

        add_filter('woocommerce_email_attachments', array($this, 'woocommerce_emails_attach_downloadables'), 10, 3);
    }

    public function wcmpesa_new_order_column($columns)
    {
        $columns['mpesa'] = 'Reinitiate Mpesa';
        return $columns;
    }

    /**
     * Render custom column content within edit.php table on event post types.
     *
     * @access public
     * @param string $column The name of the column being acted upon
     * @return void
     */
    public function shop_order_payments_table_column_content($column_id, $post_id)
    {
        $order_id = get_post_meta($post_id, '_order_id', true);
        switch ($column_id) {

            case 'mpesa':
                $statuses = array(
                    "processing" => "This Order Is Processing",
                    "on-hold"    => "This Order Is On Hold",
                    "complete"   => "This Order Is Complete",
                    "cancelled"  => "This Order Is Cancelled",
                    "refunded"   => "This Order Is Refunded",
                    "failed"     => "This Order Failed",
                );

                echo ($value = get_post_meta($post_id, '_order_status', true))
                    ? '<a href="' . admin_url('post.php?post=' . esc_attr(sanitize_text_field($order_id)) . '&action=edit">' . esc_attr($statuses[$value]) . '</a>')
                    : '<a href="' . admin_url('post.php?post=' . esc_attr(sanitize_text_field($order_id)) . '&action=edit"') . '>Set Status</a>';
                break;
        }
    }

    // Add a custom column before "actions" last column
    public function wc_mpesa_order_column($columns)
    {
        $ordered_columns = array();

        foreach ($columns as $key => $column) {
            $ordered_columns[$key] = $column;
            if ('order_date' == $key) {
                $ordered_columns['transaction_id'] = __('Receipt', 'woocommerce');
            }
        }

        return $ordered_columns;
    }

    public function wc_mpesa_order_list_column_content($column)
    {
        global $post;
        $the_order = wc_get_order($post->ID);

        if ('transaction_id' === $column) {
            echo $the_order->get_transaction_id() ?? 'N/A';
        }
    }

    public function woocommerce_emails_attach_downloadables($attachments, $status, $order)
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
                foreach ($order->get_items() as $item_id => $item) {
                    foreach ($order->get_item_downloads($item) as $download) {
                        $attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
                    }
                }
            }
        }

        return $attachments;
    }
}
