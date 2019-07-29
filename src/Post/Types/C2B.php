<?php
namespace Osen\Post\Types;
/**
 * @package MPesa For WooCommerce
 * @subpackage Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

class C2B
{
    function __construct()
    {

    }

    public static function init()
    {
        add_action('init', [new self, 'mpesaipn_post_type'], 0);
        add_filter('manage_mpesaipn_posts_columns', [new self, 'filter_mpesaipn_table_columns']);
        add_action('manage_mpesaipn_posts_custom_column',[new self, 'mpesaipn_table_column_content'], 10, 2);
        add_filter('manage_edit-mpesaipn_sortable_columns', [new self, 'mpesaipn_columns_sortable']);
    }

    // Register Custom Post - Payments
    public static function mpesaipn_post_type() {

        $labels = array(
            'name'                  => _x('Payments', 'Payment General Name', 'woocommerce'),
            'singular_name'         => _x('Payment', 'Payment Singular Name', 'woocommerce'),
            'menu_name'             => __('M-PESA', 'woocommerce'),
            'name_admin_bar'        => __('Payment', 'woocommerce'),
            'archives'              => __('Payment Archives', 'woocommerce'),
            'attributes'            => __('Payment Attributes', 'woocommerce'),
            'parent_item_colon'     => __('Parent Payment:', 'woocommerce'),
            'all_items'             => __('Payments', 'woocommerce'),
            'add_new_item'          => __('Record New Manual Payment', 'woocommerce'),
            'add_new'               => __('Record Payment', 'woocommerce'),
            'new_item'              => __('New Manual C2B', 'woocommerce'),
            'edit_item'             => __('Edit Payment', 'woocommerce'),
            'update_item'           => __('Update Payment', 'woocommerce'),
            'view_item'             => __('View Payment', 'woocommerce'),
            'view_items'            => __('View Payments', 'woocommerce'),
            'search_items'          => __('Search Payments', 'woocommerce'),
            'not_found'             => __('Not found', 'woocommerce'),
            'not_found_in_trash'    => __('Not found in Trash', 'woocommerce'),
            'items_list'            => __('Payments list', 'woocommerce'),
            'items_list_navigation' => __('Payments list navigation', 'woocommerce'),
            'filter_items_list'     => __('Filter payments list', 'woocommerce'),
        );

        $args = array(
            'label'                 => __('Payment', 'woocommerce'),
            'description'           => __('Payment Description', 'woocommerce'),
            'labels'                => $labels,
            'supports'              => (get_option('woocommerce_mpesa_settings')["env"] == 'live') ? array() : array('editor'),
            'taxonomies'            => array(),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => true,
            'capability_type'       => 'page',
            'menu_icon'             => 'dashicons-money',
            'rewrite'               => false,
            'show_in_rest'          => true,
            // 'menu_icon'             => apply_filters('woocommerce_mpesa_icon', plugins_url('mpesa.png', __FILE__)),
        );

        register_post_type('mpesaipn', $args);
    }

    /**
     * A filter to add custom columns and remove built-in
     * columns from the edit.php screen.
     * 
     * @access public
     * @param array $columns The existing columns
     * @return array $filtered_columns The filtered columns
     */
    public static function filter_mpesaipn_table_columns($columns)
    {
        $columns['title']       = "Type";
        $columns['customer']    = "Customer";
        $columns['amount']      = "Amount";
        $columns['reference']   = "Reference";
        // $columns['request']     = "Request";
        $columns['receipt']     = "Receipt";
        $columns['status']      = "Status";
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
    public static function mpesaipn_table_column_content($column_id, $post_id)
    {
        $order_id = get_post_meta($post_id, '_order_id', true);
        switch ($column_id) {
            case 'customer':
                echo ($value = get_post_meta($post_id, '_customer', true)) ? $value : "N/A";
                break;

            case 'amount':
                echo ($value = get_post_meta($post_id, '_amount', true)) ? round($value) : "0";
                break;

            case 'request':
                echo ($value = get_post_meta($post_id, '_request_id', true)) ? $value : "N/A";
                break;

            case 'receipt':
                echo ($value = get_post_meta($post_id, '_receipt', true)) ? $value : "N/A";
                break;

            case 'reference':
                echo ($value = get_post_meta($post_id, '_reference', true)) ? $value : "ORDER#{$order_id}";
                break;

            case 'status':
                $statuses = array(
                    "processing"    => "This Order Is Processing",
                    "on-hold"       => "This Order Is On Hold",
                    "complete"      => "This Order Is Complete",
                    "cancelled"     => "This Order Is Cancelled",
                    "refunded"      => "This Order Is Refunded",
                    "failed"        => "This Order Failed"
                );

                echo ($value = get_post_meta($post_id, '_order_status', true)) 
                    ? '<a href="'.admin_url('post.php?post='.esc_attr(trim($order_id)).'&action=edit">'.esc_attr($statuses[$value]).'</a>') 
                    : '<a href="'.admin_url('post.php?post='.esc_attr(trim($order_id)).'&action=edit"').'>Set Status</a>';
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
    public static function mpesaipn_columns_sortable($columns) 
    {
        $columns['title']       = "Type";
        $columns['customer']    = "Customer";
        $columns['reference']   = "Reference";
        $columns['receipt']     = "Receipt";
        $columns['status']      = "Status";
        return $columns;
    }   
}