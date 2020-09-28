<?php

namespace Osen\Woocommerce\Menus;

class Menu
{

    public function __construct()
    {
        add_action("admin_menu", [$this, "wc_mpesa_menu"]);
    }
    /**
     * @package M-Pesa For WooCommerce
     * @subpackage Plugin Functions
     * @author Mauko Maunde < hi@mauko.co.ke >
     * @since 0.18.01
     */
    public function wc_mpesa_menu()
    {
        // if((get_option("woocommerce_mpesa_settings")["enable_b2c"] == "yes")){
        //     add_submenu_page(
        //         "edit.php?post_type=mpesaipn", 
        //         __("Disbursements", "woocommerce"), 
        //         __("Disbursements", "woocommerce"), 
        //         "manage_options",
        //         "wc_mpesa_b2c", 
        //         "wc_mpesa_menu_b2c" 
        //     );
        // }

        add_submenu_page(
            "edit.php?post_type=mpesaipn",
            __("About this Plugin", "woocommerce"),
            __("About Plugin", "woocommerce"),
            "manage_options",
            "wc_mpesa_about",
            [$this, "wc_mpesa_menu_about"]
        );

        add_submenu_page(
            "edit.php?post_type=mpesaipn",
            __("Configuration", "woocommerce"),
            __("Configuration", "woocommerce"),
            "manage_options",
            "wc_mpesa_preferences",
            [$this, "wc_mpesa_menu_settings"]
        );

        // if((get_option("woocommerce_mpesa_settings")["enable_b2c"] == "yes")){
        //     add_submenu_page(
        //         "edit.php?post_type=mpesaipn", 
        //         __("M-Pesa B2C Preferences", "woocommerce"), 
        //         __("Configure B2C", "woocommerce"), 
        //         "manage_options",
        //         "wc_mpesa_b2c_preferences", 
        //         [$this, "wc_mpesa_b2c_settings" ]
        //     );

        //     add_submenu_page(
        //         "edit.php?post_type=mpesaipn", 
        //         __("Withdraw to Mpesa", "woocommerce"), 
        //         __("Withdraw", "woocommerce"), 
        //         "manage_options", 
        //         "wcmpesab2cw", 
        //         [$this, "wcmpesab2cw_options_page_html"]
        //     );
        // }

        add_submenu_page(
            "edit.php?post_type=mpesaipn",
            __("Analytics", "woocommerce"),
            __("M-Pesa Analytics", "woocommerce"),
            "manage_options",
            "wc_mpesa_analytics",
            [$this, "wc_mpesa_menu_analytics"]
        );
    }

    public function wc_mpesa_menu_about()
    { ?>
        <div class="wrap">
            <h1>About M-Pesa for WooCommerce</h1>

            <h3>The Plugin</h3>
            <article>
                <p>This plugin seeks to provide a simple plug-n-play implementation for integrating M-Pesa Payments into online
                    stores built with WooCommerce and WordPress.</p>
            </article>

            <h3>Pre-requisites</h3>
            <article>
                <ol>
                    <li>Please <a href="https://developer.safaricom.co.ke/" target="_blank">create an app on Daraja</a> if you
                        haven"t. Fill in the app"s consumer key and secret below.</li>
                    <li>Ensure you have access to the <a href="https://ke.mpesa.org">M-Pesa Web Portal</a>. You"ll need this for
                        when you go LIVE.</li>
                    <li>For security purposes, and for the M-Pesa Instant Payment Notification to work, ensure your site is
                        running over https(SSL).</li>
                    <li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank">get sandbox test
                            credentials here</a>.</li>
                </ol>
            </article>

            <h3>Integration(Going Live)</h3>
            <article>
                <p>
                    While we have made all efforts to ensure this plugin works out of the box - with minimum configuration
                    required - the service provider requires that the user go through a certain ardous process to migrate from
                    sandbox(test) environment to production.
                </p>
                <p>
                    We have made a <a href="https://wc-mpesa.osen.co.ke/going-live">tutorial here</a> to walk you through the
                    process. We however have a team ready on call to assist you in this are, at a fiat fee of KSh 4000 one-off,
                    should you find it difficult.
                </p>
            </article>

            <h3>C2B Confirmation and Validation URLs</h3>
            <article>
                <p>Whenever M-Pesa receives a transaction on your shortcode, a validation request is sent to the validation URL
                    registered above. M-Pesa completes or cancels the transaction depending on the validation response it
                    receives.</p>
                <p>These URLs must be HTTPS in production. Validation is an optional feature that needs to be activated on
                    M-Pesa, the owner of the shortcode needs to make this request for activation. This can be done by sending an
                    email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on
                    the <a href="https://developer.safaricom.co.ke/">developer portal</a>.</p>
            </article>

            <h3>Development & Contribution</h3>
            <article>
                <p>To help improve and support our effort to make such solutions as this one, you can start by contributing
                    here:</p>
                <div style="padding-left: 20px;">
                    <li><a href="https://github.com/osenco/osen-wc-mpesa">This Plugin"s Github Repo</a></li>
                    <li><a href="https://github.com/osenco/mpesa">M-Pesa PHP SDK</a></li>
                    <li><a href="https://github.com/osenco/osen-oc-mpesa">M-Pesa For Open Cart</a></li>
                    <li><a href="https://github.com/osenco/osen-presta-mpesa">M-Pesa For PrestaShop</a></li>
                </div>
            </article>

            <h3>Contact</h3>
            <h4>Get in touch with us either via email (<a href="mail-to:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254204404993">+254204404993</a>)</h4>

            <img src="<?php echo plugins_url("osen-wc-mpesa/inc/mpesa.png"); ?>">
        </div><?php
            }

            public function wc_mpesa_menu_analytics()
            {
                $payments = array();
                $months = array();
                $monthly = array();

                foreach (get_posts(["post_type" => "mpesaipn"]) as $post) {
                    \setup_postdata($post);
                    for ($i = 1; $i <= 12; $i++) {
                        if (date("Y", strtotime($post->post_date)) == date("Y")) {
                            if (date("m", strtotime($post->post_date)) == $i) {
                                $months[$i][] = (int) get_post_meta($post->ID, "_amount", true);
                            } else {
                                $months[$i][] = 0;
                            }
                        }
                    }

                    array_push($monthly, date("m", strtotime($post->post_date)));
                }

                foreach ($months as $month => $values) {
                    $payments[$month] = array_sum($values);
                }

                $ms = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                $ps = array_values($payments);

                wp_enqueue_style("c3", plugins_url("assets/c3/c3.min.css", __FILE__));
                wp_enqueue_script("c3", plugins_url("assets/c3/c3.bundle.js", __FILE__));

                $date = date("Y");
                $cols = json_encode(array_merge(["data1"], $ps));
                $categories = json_encode(array_unique($ms));

                echo '<div class="wrap">
        <h1 class="wp-heading">Payments Analytics</h1>
        <h4>Total monthly payments received via MPESA for the year ' . $date . '</h4>
    <br>
    <div id="chart-bar" style="height: 500px"></div>
    <script type="text/javascript">
    jQuery(function() {
        "use strict";
        var chart = c3.generate({
            bindto: "#chart-bar", // id of chart wrapper
            data: {
                type: "bar",
                columns: [
                    // each columns data
                    ' . $cols . ' ,
                ],
                colors: {
                    "data1": "#0073aa", // blue
                },
                names: {
                    // name of each serie
                    "data1": "KSH",
                }
            },
            axis: {
                x: {
                    type: "category",
                    // name of each category
                    categories: ' . $categories . '
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
    </div>';
            }

            public function wc_mpesa_menu_settings()
            {
                wp_redirect(admin_url("admin.php?page=wc-settings&tab=checkout&section=mpesa"));
            }

            public function wc_mpesa_b2c_settings()
            {
                wp_redirect(admin_url("admin.php?post_type=b2c_payment&page=wcmpesab2c"));
            }

            public function wc_mpesa_menu_b2c()
            {
                wp_redirect(admin_url("edit.php?post_type=b2c_payment"));
            }
        }
