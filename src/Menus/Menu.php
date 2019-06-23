<?php
namespace Osen\Menus;

class Menu
{

    public static function init(Type $var = null)
    {
        add_action('admin_menu', [new self, 'wc_mpesa_menu']);
    }
    /**
     * @package MPesa For WooCommerce
     * @subpackage Plugin Functions
     * @author Mauko Maunde < hi@mauko.co.ke >
     * @since 0.18.01
     */
    public static function wc_mpesa_menu()
    {
        if((get_option('woocommerce_mpesa_settings')["enable_b2c"] == 'yes')){
            add_submenu_page(
                'edit.php?post_type=mpesaipn', 
                __('Disbursements', 'woocommerce'), 
                __('Disbursements', 'woocommerce'), 
                'manage_options',
                'wc_mpesa_b2c', 
                'wc_mpesa_menu_b2c' 
            );
        }

        add_submenu_page(
            'edit.php?post_type=mpesaipn', 
            __('About this Plugin', 'woocommerce'), 
            __('About Plugin', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_about', 
            [new self, 'wc_mpesa_menu_about'] 
        );

        add_submenu_page(
            'edit.php?post_type=mpesaipn', 
            __('Configuration', 'woocommerce'), 
            __('Configuration', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_preferences', 
            [new self, 'wc_mpesa_menu_settings' ]
        );

        if((get_option('woocommerce_mpesa_settings')["enable_b2c"] == 'yes')){
            add_submenu_page(
                'edit.php?post_type=mpesaipn', 
                __('MPesa B2C Preferences', 'woocommerce'), 
                __('Configure B2C', 'woocommerce'), 
                'manage_options',
                'wc_mpesa_b2c_preferences', 
                [new self, 'wc_mpesa_b2c_settings' ]
            );

            add_submenu_page(
                'edit.php?post_type=mpesaipn', 
                __('Withdraw to Mpesa', 'woocommerce'), 
                __('Withdraw', 'woocommerce'), 
                'manage_options', 
                'wcmpesab2cw', 
                [new self, 'wcmpesab2cw_options_page_html']
            );
        }

        add_submenu_page(
            'edit.php?post_type=mpesaipn', 
            __('Analytics', 'woocommerce'),
            __('MPesa Analytics', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_analytics', 
            [new self, 'wc_mpesa_menu_analytics'] 
        );
    }

    public static function wc_mpesa_menu_about()
    { ?>
        <div class="wrap">
            <h1>About MPesa for WooCommerce</h1>

            <h3>The Plugin</h3>
            <article>
                <p>This plugin builds on the work of <a href="https://github.com/moshthepitt/woocommerce-lipa-na-mmpesa">Kelvin Jayanoris</a>, the <a href="https://osen.co.ke" target="_blank">Osen Concepts </a> developers and others to provide a simple plug-n-play implementation for integrating MPesa Payments into online stores built with WooCommerce and WordPress.</p>
            </article>

            <h4>Version <?php echo WCM_VER; ?> introduces many changes:</h4>
            <?php $logs = file_get_contents(dirname(WCM_PLUGIN_FILE).'/CHANGELOG');
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
            <h4>Get in touch with us either via email (<a href="mail-to:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254204404993">+254204404993</a>)</h4>
        </div><?php
    }

    public static function wc_mpesa_menu_analytics()
    {
        $payments = array();
        $months = array();
        $monthly = array();
        foreach (get_posts(['post_type' => 'mpesaipn']) as $post) {
            for ($i=1; $i < 13 ; $i++) { 
                if (date('Y', strtotime($post->post_date)) == date('Y')) {
                    $months[$i][] = (int)get_post_meta( $post->ID, '_amount', true );

                    foreach ($months as $month => $values) {
                        $payments[date('m', strtotime(date('Y-'.$i)))] = array_sum($values);
                    }

                    array_push($monthly, date('m', strtotime($post->post_date)));
                }
            }

            $ms = array_keys($payments);
            $ps = array_values($payments);
        }
        ?>
        <div class="wrap">
            <?php wp_enqueue_style('c3', plugins_url('assets/c3/c3.min.css', __FILE__)); ?>
            <h1 class="wp-heading">Payments Analytics</h1>
            <h4>Analytics of payments received via MPESA for the year <?php echo date('Y'); ?></h4>
            <br>
            <div id="chart-bar" style="height: 500px"></div>
            <?php wp_enqueue_script('c3', plugins_url('assets/c3/c3.bundle.js', __FILE__)); ?>
            <script type="text/javascript">
                jQuery(function(){
                    "use strict";
                    var chart = c3.generate({
                        bindto: '#chart-bar', // id of chart wrapper
                        data: {
                            type: 'bar',
                            columns: [
                                // each columns data
                                <?php echo json_encode(array_merge(['data1'], $ps)); ?>,
                            ],
                            colors: {
                                'data1': '#0073aa', // blue
                            },
                            names: {
                                // name of each serie
                                'data1': 'KSH',   
                            }
                        },
                        axis: {
                            x: {
                                type: 'category',
                                // name of each category
                                categories: <?php echo json_encode(array_unique($ms)); ?>
                            },
                        },
                        legend: {
                            show: true, //hide legend
                        },
                        padding: {
                            bottom: 20,
                            top: 0
                        },
                    });
                });
            </script>
        </div>
        <?php
    }

    public static function wc_mpesa_menu_settings()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa'));
    }

    public static function wc_mpesa_menu_b2c()
    {
        wp_redirect(admin_url('edit.php?post_type=b2c_payment'));
    }
}
