<?php

namespace Osen\Woocommerce\Admin;

/**
 * @package M-Pesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */
class Menu
{
    public function __construct()
    {
        add_action("admin_menu", array($this, "wc_mpesa_menu"), 55);
    }

    public function wc_mpesa_menu()
    {
        add_submenu_page(
            null,
            __("About M-Pesa for WooCommerce", "woocommerce"),
            __("About M-Pesa for WooCommerce", "woocommerce"),
            "manage_options",
            "wc_mpesa_about",
            array($this, "wc_mpesa_menu_about"),
        );

        add_submenu_page(
            'woocommerce',
            __("M-Pesa Configuration", "woocommerce"),
            __("Configure M-Pesa", "woocommerce"),
            "manage_options",
            "wc_mpesa_preferences",
            array($this, "wc_mpesa_menu_settings")
        );

        add_submenu_page(
            'woocommerce',
            __("M-Pesa Analytics", "woocommerce"),
            __("M-Pesa Analytics", "woocommerce"),
            "manage_options",
            "wc_mpesa_analytics",
            array($this, "wc_mpesa_menu_analytics")
        );
    }

    public function wc_mpesa_menu_about()
    { ?>
        <div class="wrap">
            <h1>About M-Pesa for WooCommerce</h1>

            <h3>The Plugin</h3>
            <article>
                <p>
                    This plugin seeks to provide a simple plug-n-play solution for integrating M-Pesa Payments into online
                    stores built with WooCommerce and WordPress.
                </p>
            </article>

            <h3>Pre-requisites</h3>
            <article>
                <ol>
                    <li>
                        Please <a href="https://developer.safaricom.co.ke/" target="_blank">create an app on Daraja</a> if you
                        haven"t. Fill in the app"s consumer key and secret below.
                    </li>
                    <li>
                        Ensure you have access to the <a href="https://ke.mpesa.org">M-Pesa Web Portal</a>. You"ll need this for
                        when you go LIVE.
                    </li>
                    <li>
                        For security purposes, and for the M-Pesa Instant Payment Notification to work, ensure your site is
                        running over https(SSL).
                    </li>
                    <li>
                        You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank">get sandbox test
                            credentials here</a>.
                    </li>
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
                    We have made a <a href="https://wcmpesa.co.ke/going-live">tutorial here</a> to walk you through the
                    process. We however have a team ready on call to assist you in this are, at a flat fee of KSh 4000 one-off,
                    should you find it difficult.
                </p>
            </article>

            <h3>C2B Confirmation and Validation URLs</h3>
            <article>
                <p>
                    Whenever M-Pesa receives a transaction on your shortcode, a validation request is sent to the validation URL
                    registered above. M-Pesa completes or cancels the transaction depending on the validation response it
                    receives.
                </p>
                <p>
                    These URLs must be HTTPS in production. Validation is an optional feature that needs to be activated on
                    M-Pesa, the owner of the shortcode needs to make this request for activation. This can be done by sending an
                    email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on
                    the <a href="https://developer.safaricom.co.ke/">developer portal</a>.
                </p>
            </article>

            <h3>Development & Contribution</h3>
            <article>
                <p>
                    To help improve and support our effort to make such solutions as this one, you can start by contributing here:
                </p>
                <div style="padding-left: 20px;">
                    <li><a href="https://github.com/osenco/osen-wc-mpesa">This Plugin's Github Repo</a></li>
                    <li><a href="https://github.com/osenco/mpesa">M-Pesa PHP SDK</a></li>
                    <li><a href="https://github.com/osenco/osen-oc-mpesa">M-Pesa For Open Cart</a></li>
                    <li><a href="https://github.com/osenco/osen-presta-mpesa">M-Pesa For PrestaShop</a></li>
                </div>
            </article>

            <h3>Contact</h3>
            <h4>Get in touch with us either via email (<a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254204404993">+254204404993</a>)</h4>

            <img src="<?php echo plugins_url("osen-wc-mpesa/assets/wcmpesa.png"); ?>" width="100px">
        </div>
<?php
    }

    public function wc_mpesa_menu_analytics()
    {
        $payments = array();
        $orders   = wc_get_orders(array(
            'numberposts' => -1,
            'status'      => 'wc-completed',
        ));

        for ($i = 1; $i < 13; $i++) {
            $payments[$i] = 0;
        }

        foreach ($orders as $order) {
            $method = $order->get_payment_method();
            $year   = $order->get_date_created()->format('Y');
            $month  = $order->get_date_created()->format('n');

            if ($year === date('Y') && $method === 'mpesa') {
                $payments[$month] += $order->get_total();
            }
        }

        $ms         = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $ps         = array_values($payments);
        $cols       = wp_json_encode(array_merge(["data1"], $ps));
        $cols2       = wp_json_encode(array_merge(["data1"], $ps));
        $categories = wp_json_encode($ms);

        echo
        '<div class="wrap">
        <h1 class="wp-heading">Payments Analytics</h1>
        <h3>Total monthly payments received via M-Pesa for the year ' . date('Y') . '</h3>
        <br>
        <div id="chart-bar" style="height: 500px"></div>
            <script type="text/javascript">
                jQuery(function() {
                    "use strict";
                    var chart = c3.generate({
                        bindto: "#chart-bar",
                        data: {
                            type: "bar",
                            // types: {
                            //     data1: "bar"
                            //     data2: "spline"
                            // },
                            columns: [
                                ' . $cols . ' ,
                                // ' . $cols2 . ' ,
                            ],
                            colors: {
                                data1: "#0073aa", // blue
                                // data2: "#f39c12", // orange
                            },
                            names: {
                                data1: "Amount in KSh",
                                // data2: "Orders",
                            }
                        },
                        axis: {
                            x: {
                                type: "category",
                                categories: ' . $categories . '
                            },
                        },
                        legend: {
                            show: true,
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
