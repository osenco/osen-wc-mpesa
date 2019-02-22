<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

add_action( 'admin_menu', 'wc_mpesa_menu' );
function wc_mpesa_menu()
{
    add_submenu_page( 
        'edit.php?post_type=c2b_payment', 
        'B2C Payments', 
        'B2C Payments', 
        'manage_options',
        'wc_mpesa_b2c', 
        'wc_mpesa_menu_b2c' 
    );

    add_submenu_page( 
        'edit.php?post_type=c2b_payment', 
        'About this Plugin', 
        'About Plugin', 
        'manage_options',
        'wc_mpesa_about', 
        'wc_mpesa_menu_about' 
    );

    add_submenu_page( 
        'edit.php?post_type=c2b_payment', 
        'MPesa C2B Preferences', 
        'Configure C2B', 
        'manage_options',
        'wc_mpesa_preferences', 
        'wc_mpesa_menu_settings' 
    );

    add_submenu_page( 
        'edit.php?post_type=c2b_payment', 
        'MPesa B2C Preferences', 
        'Configure B2C', 
        'manage_options',
        'wc_mpesa_b2c_preferences', 
        'wc_mpesa_b2c_settings' 
    );
}

function wc_mpesa_menu_about()
{ ?>
    <div class="wrap">
        <h1>About MPesa for WooCommerce</h1>

        <h3>The Plugin</h3>
        <article>
            <p>This plugin builds on the work of <a href="https://github.com/moshthepitt/woocommerce-lipa-na-mmpesa">Kelvin Jayanoris</a>, the <a href="https://osen.co.ke">Osen Concepts </a> developers and others to provide a simple plug-n-play implementation for integrating MPesa Payments into online stores built with WooCommerce and WordPress.</p>
        </article>

        <h4>Version <?php echo WCM_VER; ?> introduces many changes:</h4>
        <?php $logs = file_get_contents( dirname( WCM_PLUGIN_FILE ).'/CHANGELOG');
        $logs = explode("\n", $logs); ?>
        <ol>
            <?php foreach ($logs as $log): ?>
                <li><?php echo $log; ?></li>
            <?php endforeach; ?>
        </ol>

        <h3>Integration(Going Live)</h3>
        <article>
            <p>
                While we have made all efforts to ensure this plugin works out of the box - with minimum configuration required - the service provider requires that the user go through a certain ardous process to migrate from sandbox(test) environment to production.
            </p> 
            <p>
                We have made a <a href="https://wc-mpesa.osen.co.ke/going-live">tutorial here</a> to walk you through the process. We however have a team ready on call to assist you in this are, at a fiat fee of KSh 4000 one-off, should you find it difficult.
            </p>
        </article>

        <h3>Development</h3>
        <article>
            <p>To help improve and support our effort to make such solutions as this one, you can start by contributing here:</p>
            <div style="padding-left: 20px;">
                <li><a href="https://github.com/osenco/osen-wc-mpesa">This Plugin's Github Repo</a></li>
                <li><a href="https://github.com/osenco/osen-mpesa-php">MPesa PHP SDK</a></li>
                <li><a href="https://github.com/osenco/osen-laravel-mpesa">MPesa For Laravel</a></li>
                <li><a href="https://github.com/osenco/osen-oc-mpesa">MPesa For Open Cart</a></li>
                <li><a href="https://github.com/osenco/osen-presta-mpesa">MPesa For PrestaShop</a></li>
            </div>
        </article>

        <h3>Contact</h3>
        <h4>Get in touch with us either via email ( <a href="mail-to:hi@mauko.co.ke">hi@mauko.co.ke</a> ) or via phone( <a href="tel:+254204404993">+254204404993</a> )</h4>
    </div><?php
}

function wc_mpesa_menu_settings()
{
    wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ) );
}

function wc_mpesa_menu_b2c()
{
    wp_redirect( admin_url( 'edit.php?post_type=b2c_payment' ) );
}