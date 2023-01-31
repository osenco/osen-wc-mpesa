<?php

/**
 * @package M-Pesa For WooCommerce
 * @subpackage Plugin Functions
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.18.01
 */

namespace Osen\Woocommerce\Admin;

use Osen\Woocommerce\Mpesa\STK;

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
            array($this, "wc_mpesa_menu_about")
        );

        add_submenu_page(
            null,
            __("How to Go Live", "woocommerce"),
            __("How to Go Live", "woocommerce"),
            "manage_options",
            "wc_mpesa_go_live",
            array($this, "wc_mpesa_menu_go_live")
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

            <h3>Development & Contribution</h3>
            <article>
                <p>
                    To help improve and support our effort to make such solutions as this one, you can start by contributing
                    here:
                </p>
                <div style="padding-left: 20px;">
                    <li><a href="https://github.com/osenco/osen-wc-mpesa">This Plugin's Github Repo</a></li>
                    <li><a href="https://github.com/osenco/mpesa">M-Pesa PHP SDK</a></li>
                    <li><a href="https://github.com/osenco/osen-oc-mpesa">M-Pesa For Open Cart</a></li>
                    <li><a href="https://github.com/osenco/osen-presta-mpesa">M-Pesa For PrestaShop</a></li>
                </div>
            </article>

            <h3>Contact</h3>
            <h4>Get in touch with us either via email (<a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254705459494">+254705459494</a>)</h4>

            <img src="<?php echo plugins_url("osen-wc-mpesa/assets/wcmpesa.png"); ?>" width="100px">
        </div>
    <?php
    }

    public function wc_mpesa_menu_go_live()
    {
        $mpesa            = new STK();
        $validation_url   = home_url('wc-api/lipwa?action=validate&sign=' . $mpesa->signature);
        $confirmation_url = home_url('wc-api/lipwa?action=confirm&sign=' . $mpesa->signature);
        $setting_url      = esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=mpesa')); ?>

        <div class="wrap">
            <h1>How to Go Live</h1>
            <h3>Pre-requisites</h3>
            <article>
                <p>Ensure you have access to:</p>
                <ol>
                    <li>
                        The <a href="https://org.ke.m-pesa.com/" target="_blank">M-Pesa Web Portal</a> either as a Business
                        Manager or Administrator.
                    </li>
                    <li>The Safaricom <a href="https://developer.safaricom.co.ke" target="_blank">Developer portal(Daraja)</a>
                    </li>
                </ol>
            </article>

            <h3>Buy Goods(Till Number) Considerations</h3>
            <article>
                <p>
                    If you are using a Till Number instead of a Paybill, you will need the Store Number that you will use to go
                    Live.
                </p>
                <p>
                    You will also need to create another user under the Store Number that you will use on Daraja.
                </p>

                <p>To create a Business Manager profile:</p>
                <ol>
                    <li>
                        Log in to <a href="https://org.ke.m-pesa.com/" target="_blank">M-Pesa Web Portal</a>, As the Business Administrator through HO (Head Office)
                    </li>
                    <li>Click Browse organization, click children to view the child stores,</li>
                    <li>Select more to view the selected store details, Select operators then click add operators.</li>
                    <li>Enter operator details and assign <b>Business Manager</b> role</li>
                    <li>Use <b>Web Operator</b> Rule Profile and <b>Web</b> access channel</li>
                </ol>
            </article>

            <h3>Going Live</h3>
            <article>
                <p>
                    The process is as follows:
                <ol>
                    <li>Login to your Daraja account and navigate to Go Live</li>
                    <li>Select 'Shortcode' as your validation method</li>
                    <li>Enter your Paybill(or Store Number if using Buy Goods)</li>
                    <li>
                        Enter the username of a user with the Business Manager role (use the one created above if using Store Number)
                    </li>
                    <li>Click Enter and wait for the One Time Password</li>
                    <li>Enter the OTP and wait for the confirmation</li>
                    <li>
                        Switch accounts to your newly created company and copy the app credentials displayed and paste in the
                        <a href="<?php echo $setting_url; ?>">plugin settings</a>
                    </li>
                    <li>
                        If you do not receive the online passkey in your email,
                        write to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a> and request for the same.
                    </li>
                </ol>
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
                    M-Pesa - the owner of the shortcode needs to make this request for activation. This can be done by sending an
                    email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on
                    the <a href="https://developer.safaricom.co.ke/">developer portal</a>.
                </p>
                <p>
                    The plugin uses the following URLs for this purpose:
                <ol>
                    <li>
                        Validation URL: <a href="<?php echo esc_url($validation_url); ?>"><?php echo esc_url($validation_url); ?></a>
                    </li>
                    <li>
                        Confirmation URL: <a href="<?php echo esc_url($confirmation_url); ?>"><?php echo esc_url($confirmation_url); ?></a>
                    </li>
                </ol>
                </p>
            </article>

            <h3>Production URLs</h3>
            <article>
                <p>After going live on Daraja you will receive an email from Safaricom with production URLs.</p>
                <p>You don't need to take any action on this as the plugin will automatically update the URLs when live.</p>
            </article>

            <h3>Contact</h3>
            <h4>
                Incase you are unable to do this on your own, you can get in touch with us either via email (<a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a href="tel:+254705459494">+254705459494</a>)
            </h4>

            <img src="<?php echo plugins_url("osen-wc-mpesa/assets/wcmpesa.png"); ?>" width="100px">
        </div>
<?php
    }

    public function wc_mpesa_menu_analytics()
    {
        $payments = array();
        $orders   = wc_get_orders(array(
            'numberposts'    => -1,
            'status'         => 'wc-completed',
            'payment_method' => 'mpesa',
        ));

        for ($i = 1; $i < 13; $i++) {
            $payments[$i] = 0;
        }

        foreach ($orders as $order) {
            $year  = $order->get_date_created()->format('Y');
            $month = $order->get_date_created()->format('n');

            if ($year === date('Y')) {
                $payments[$month] += $order->get_total();
            }
        }

        $ms         = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $ps         = array_values($payments);
        $cols       = wp_json_encode(array_merge(["data1"], $ps));
        $cols2      = wp_json_encode(array_merge(["data1"], $ps));
        $categories = wp_json_encode($ms);
        $currency   = get_woocommerce_currency();

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
                                data1: "Amount in ' . $currency . '",
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
}
