<?php

namespace Osen\Woocommerce\Post\Types;

/**
 * @package MPesa For WooCommerce
 * @subpackage Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

class B2C
{
    public function init()
    {
        add_action('init', [$this, 'b2c_payments_post_type'], 0);
        add_filter('manage_b2c_payment_posts_columns', [$this, 'filter_b2c_payments_table_columns']);
        add_filter('manage_edit-b2c_payment_sortable_columns', [$this, 'b2c_payments_columns_sortable']);

        //add_action('manage_posts_custom_column',[$this, 'b2c_payments_table_column_content'], 10, 2);
    }

    // Register Custom Post - Disbursement/Refunds
    public function b2c_payments_post_type()
    {

        $labels = array(
            'name'                  => _x('Business-Customer Payments/Refunds', 'Disbursement/Refund General Name', 'woocommerce'),
            'singular_name'         => _x('Disbursement/Refund', 'Disbursement/Refund Singular Name', 'woocommerce'),
            'menu_name'             => __('MPesa B2C', 'woocommerce'),
            'name_admin_bar'        => __('Disbursement/Refund', 'woocommerce'),
            'archives'              => __('Payment/Refund Archives', 'woocommerce'),
            'attributes'            => __('Payment/Refund Attributes', 'woocommerce'),
            'parent_item_colon'     => __('Parent Payment/Refund:', 'woocommerce'),
            'all_items'             => __('Disbursement/Refunds', 'woocommerce'),
            'add_new_item'          => __('Make New Payment/Refund', 'woocommerce'),
            'add_new'               => __('Make Payment/Refund', 'woocommerce'),
            'new_item'              => __('New Payment/Refund', 'woocommerce'),
            'edit_item'             => __('Edit Payment/Refund', 'woocommerce'),
            'update_item'           => __('Update Payment/Refund', 'woocommerce'),
            'view_item'             => __('View Payment/Refund', 'woocommerce'),
            'view_items'            => __('View Payment/Refunds', 'woocommerce'),
            'search_items'          => __('Search Payment/Refunds', 'woocommerce'),
            'not_found'             => __('Not found', 'woocommerce'),
            'not_found_in_trash'    => __('Not found in Trash', 'woocommerce'),
            'items_list'            => __('Payment/Refunds list', 'woocommerce'),
            'items_list_navigation' => __('Payment/Refunds list navigation', 'woocommerce'),
            'filter_items_list'     => __('Filter payments list', 'woocommerce'),
        );

        $supports = (get_option('woocommerce_mpesa_settings')["env"] == 'live') ? array('revisions') : array('revisions', 'editor');

        $args = array(
            'label'               => __('Disbursement/Refund', 'woocommerce'),
            'description'         => __('Disbursement/Refund Description', 'woocommerce'),
            'labels'              => $labels,
            'supports'            => $supports,
            'taxonomies'          => array(),
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_rest'        => true,
            'show_in_menu'        => false,
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'capability_type'     => 'page',
            'rewrite'             => false,
        );

        register_post_type('b2c_payment', $args);
    }

    /**
     * A filter to add custom columns and remove built-in
     * columns from the edit.php screen.
     *
     * @access public
     * @param array $columns The existing columns
     * @return array $filtered_columns The filtered columns
     */
    public function filter_b2c_payments_table_columns($columns)
    {
        $columns['title']    = "Type";
        $columns['customer'] = "Customer";
        $columns['amount']   = "Amount";
        $columns['paid']     = "Paid";
        $columns['balance']  = "Balance";
        $columns['request']  = "Request";
        $columns['receipt']  = "Receipt";
        $columns['status']   = "Status";

        unset($columns['date']);
        return $columns;
    }

    /**
     * Render custom column content within edit.php table on event post types.
     *
     * @access public
     * @param string $column The name of the column being acted upon
     * @return void
     */
    public function b2c_payments_table_column_content($column_id, $post_id)
    {
        $order_id = get_post_meta($post_id, '_order_id', true);
        switch ($column_id) {
            case 'customer':
                echo ($value = get_post_meta($post_id, '_customer', true)) ? $value : "N/A";
                break;

            case 'amount':
                echo ($value = get_post_meta($post_id, '_amount', true)) ? $value : "0";
                break;

            case 'paid':
                echo ($value = get_post_meta($post_id, '_paid', true)) ? $value : "0";
                break;

            case 'request':
                echo ($value = get_post_meta($post_id, '_request_id', true)) ? $value : "N/A";
                break;

            case 'receipt':
                echo ($value = get_post_meta($post_id, '_receipt', true)) ? $value : "N/A";
                break;

            case 'balance':
                echo ($value = get_post_meta($post_id, '_balance', true)) ? $value : "0";
                break;

            case 'status':
                $statuses = array(
                    "processing" => "This Order Is Processing",
                    "on-hold"    => "This Order Is On Hold",
                    "complete"   => "This Order Is Complete",
                    "cancelled"  => "This Order Is Cancelled",
                    "refunded"   => "This Order Is Refunded",
                    "failed"     => "This Order Failed",
                );

                echo ($value = get_post_meta($post_id, '_order_status', true)) ? '<a href="' . admin_url('post.php?post=' . esc_attr(trim($order_id)) . '&action=edit">' . esc_attr($statuses[$value]) . '</a>') : '<a href="' . admin_url('post.php?post=' . esc_attr(trim($order_id)) . '&action=edit"') . '>Set Status</a>';
                break;
        }
    }

    /**
     * Make custom columns sortable.
     *
     * @access public
     * @param array $columns The original columns
     * @return array $columns The filtered columns
     */
    public function b2c_payments_columns_sortable($columns)
    {
        $columns['title']    = "Type";
        $columns['customer'] = "Customer";
        $columns['paid']     = "Paid";
        $columns['balance']  = "Balance";
        $columns['receipt']  = "Receipt";
        $columns['status']   = "Status";

        return $columns;
    }
}
