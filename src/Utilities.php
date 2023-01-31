<?php

/**
 * @package M-Pesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce;

class Utilities
{
    public function __construct()
    {
        add_action('manage_shop_order_posts_custom_column', array($this, 'wc_mpesa_order_list_column_content'), 10, 1);
        add_filter('woocommerce_email_attachments', array($this, 'woocommerce_emails_attach_downloadables'), 10, 3);
        add_filter('manage_edit-shop_order_columns', array($this, 'wc_mpesa_order_column'), 100);
        add_filter('woocommerce_account_orders_columns', array($this, 'add_transaction_id_column'), 10, 1);
        add_action('woocommerce_my_account_my_orders_column_receipt', array($this, 'add_transaction_id_column_row'));
        add_action('woocommerce_order_details_after_order_table_items', array($this, 'show_transaction_id'), 10, 1);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'admin_show_transaction_id'), 10, 1);
    }

    /**
     * @param \WC_Order $order
     */
    public function show_transaction_id(\WC_Order $order)
    {
        if (is_admin() && $order->get_payment_method() === 'mpesa') {
            echo '<tfoot>
                <tr>
                    <th scope="row">' . __('Transaction ID', 'woocommerce') . ':</th>
                    <td><span class="woocommerce-Price-amount amount">' . $order->get_transaction_id() . '</td>
                </tr>
                <tr>
                    <th scope="row">' . __('Paying Phone', 'woocommerce') . ':</th>
                    <td>' . $order->get_meta('mpesa_phone', 'woocommerce') . '</td>
                </tr>
            </tfoot>';
        }
    }

    /**
     * @param \WC_Order $order
     */
    function admin_show_transaction_id(\WC_Order $order)
    {
        if ($order->get_payment_method() === 'mpesa') {
            echo '<p class="form-field form-field-wide">
                    <strong>' . __('Transaction ID', 'woocommerce') . ':</strong><br>
                    <span class="woocommerce-Price-amount amount">' . $order->get_transaction_id() . '</span>
                </p>
                <p class="form-field form-field-wide">
                    <strong>' . __('Paying Phone', 'woocommerce') . ':</strong><br>
                    <a href="tel:' . $order->get_meta('mpesa_phone', 'woocommerce') . '">' . $order->get_meta('mpesa_phone', 'woocommerce') . '</a>
                </p>';
        }
    }

    /**
     * Add a custom column before "actions" last column
     *
     * @param array $columns
     */
    public function add_transaction_id_column(array $columns)
    {
        $new_columns = array();

        foreach ($columns as $key => $name) {
            $new_columns[$key] = $name;

            // add transaction ID after order total column
            if ('order-total' === $key) {
                $new_columns['receipt'] = __('Transaction ID', 'woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * @param \WC_Order $order
     */
    public function add_transaction_id_column_row(\WC_Order $order)
    {
        // Example with a custom field
        if ($value = $order->get_transaction_id()) {
            echo esc_html($value);
        }
    }

    /**
     * Add a custom column before "actions" last column
     *
     * @param array $columns
     */
    public function wc_mpesa_order_column(array $columns)
    {
        $ordered_columns = array();

        foreach ($columns as $key => $column) {
            $ordered_columns[$key] = $column;

            if ('order_date' === $key) {
                $ordered_columns['transaction_id'] = __('Transaction ID', 'woocommerce');
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

    public function woocommerce_emails_attach_downloadables(array $attachments, $status, $order)
    {
        if (is_object($order) || isset($status) || !empty($order)) {
            if (is_a($order, 'WC_Order') && method_exists($order, 'has_downloadable_item')) {
                if ($order->has_downloadable_item()) {

                    $allowed_statuses = array('customer_invoice', 'customer_completed_order');
                    if (isset($status) && in_array($status, $allowed_statuses)) {
                        foreach ($order->get_items() as $item) {
                            foreach ($order->get_item_downloads($item) as $download) {
                                $attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
                            }
                        }
                    }
                }
            }
        }

        return $attachments;
    }
}
