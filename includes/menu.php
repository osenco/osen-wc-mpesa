<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Menus
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

add_action( 'admin_menu', 'mpesa_transactions_menu' );

function mpesa_transactions_menu()
{
    //create custom top-level menu
    add_menu_page(
        'MPesa Payments',
        'MPesa',
        'manage_options',
        'mpesa',
        'mpesa_transactions_menu_transactions',
        'dashicons-money',
        58
    );

    add_submenu_page( 
        'mpesa', 
        'About this Plugin', 
        'About', 
        'manage_options',
        'mpesa_about', 
        'mpesa_transactions_menu_about' 
    );

    add_submenu_page( 
        'mpesa', 
        'MPesa APIs Utility', 
        'Utility',
        'manage_options',
        'mpesa_utility', 
        'mpesa_transactions_menu_utility' 
    );

    add_submenu_page( 
        'mpesa', 
        'MPesa Payments Analytics', 
        'Analytics', 
        'manage_options',
        'mpesa_analytics', 
        'mpesa_transactions_menu_analytics' 
    );

    add_submenu_page( 
        'mpesa', 
        'MPesa Preferences', 
        'Configure', 
        'manage_options',
        'mpesa_preferences', 
        'mpesa_transactions_menu_pref' 
    );
}

function mpesa_transactions_menu_about()
{ ?>
    <div class="wrap">
        <h1>About MPesa for WooCommerce</h1>

        <h3>The Plugin</h3>
        <article>
            <p>This plugin builds on the work of <a href="https://github.com/moshthepitt/woocommerce-lipa-na-mmpesa">Kelvin Jayanoris</a>, <a href="https://github.com/ModoPesa/wc-mmpesa">myself</a> and others to provide a unified solution for receiving Mobile payments in Kenya, using the top telcos. Only Safaricom MPesa, Airtel Money and Equitel Money are supported for now.</p>
        </article>

        <h3>Development</h3>
        <article>
            <p>I hope to develop this further into a full-fledged free, simple, direct and secure ecommerce payments system. You can help by contributing here:</p>
            <li><a href="https://github.com/ModoPesa/wc-mpesa">This Plugin</a></li>
            <li><a href="https://github.com/ModoPesa/mmpesa-php">MPesa PHP SDK</a></li>
            <li><a href="https://github.com/ModoPesa/wc-mmpesa">MPesa For WooCommerce(IPN)</a></li>
            <li><a href="https://github.com/ModoPesa/wc-equitel">Equitel For WooCommerce(IPN)</a></li>
        </article>

        <h3>Pro Version</h3>
        <article>
            <p>While this plugin is free - because some of us are FOSS apostles - there will be a pro version. That is not to say this version doesn't have the requisite features - it does, really. The Pro version is for those who want more - analytics, 24/7 support, maintenance, the whole enchilada.</p>
        </article>

        <h4>Get in touch with me ( <a href="https://mauko.co.ke/">Mauko</a> ) either via email ( <a href="mail-to:hi@mauko.co.ke">hi@mauko.co.ke</a> ) or via phone( <a href="tel:+254204404993">+254204404993</a> )</h4>
    </div><?php
}

function mpesa_transactions_menu_transactions()
{
	wp_redirect( admin_url( 'edit.php?post_type=mpesaipn' ) );
}

function mpesa_transactions_menu_pref()
{
    wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ) );
}
