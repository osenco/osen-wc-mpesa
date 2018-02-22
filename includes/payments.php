<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

add_action( 'init', 'mpesa_payments_post_type', 0 );

// Register Custom Post - MPesa Payments
function mpesa_payments_post_type() {

    $labels = array(
        'name'                  => _x( 'MPesa Payments', 'MPesa Payment General Name', 'woocommerce' ),
        'singular_name'         => _x( 'MPesa Payment', 'MPesa Payment Singular Name', 'woocommerce' ),
        'menu_name'             => __( 'MPesa Payments', 'woocommerce' ),
        'name_admin_bar'        => __( 'MPesa Payment', 'woocommerce' ),
        'archives'              => __( 'Payment Archives', 'woocommerce' ),
        'attributes'            => __( 'Payment Attributes', 'woocommerce' ),
        'parent_item_colon'     => __( 'Parent Payment:', 'woocommerce' ),
        'all_items'             => __( 'MPesa Payments', 'woocommerce' ),
        'add_new_item'          => __( 'Add New Payment', 'woocommerce' ),
        'add_new'               => __( 'Add Payment', 'woocommerce' ),
        'new_item'              => __( 'New Payment', 'woocommerce' ),
        'edit_item'             => __( 'Edit Payment', 'woocommerce' ),
        'update_item'           => __( 'Update Payment', 'woocommerce' ),
        'view_item'             => __( 'View Payment', 'woocommerce' ),
        'view_items'            => __( 'View Payments', 'woocommerce' ),
        'search_items'          => __( 'Search Payments', 'woocommerce' ),
        'not_found'             => __( 'Not found', 'woocommerce' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'woocommerce' ),
        'items_list'            => __( 'Payments list', 'woocommerce' ),
        'items_list_navigation' => __( 'Payments list navigation', 'woocommerce' ),
        'filter_items_list'     => __( 'Filter payments list', 'woocommerce' ),
    );
    
    $args = array(
        'label'                 => __( 'MPesa Payment', 'woocommerce' ),
        'description'           => __( 'MPesa Payment Description', 'woocommerce' ),
        'labels'                => $labels,
        'supports'              => array( 'revisions' ),
        'taxonomies'            => array(),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => false,
        'show_in_menu'          => false,
        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => false,
        'can_export'            => false,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'page',
        'rewrite'               => false,
    );

    register_post_type( 'mpesaipn', $args );
}
