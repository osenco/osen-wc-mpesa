<?php
namespace Osen\Menus;

/**
 * @package M-PESA For WooCommerce
 * @subpackage Admin Menus
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.19.8
 * @since 0.18.01
 */

class Menu
{
    public static function init(Type $var = null)
    {
        add_action('admin_menu', [new self, 'wc_mpesa_menu']);
    }
    
    /**
     * @package M-PESA For WooCommerce
     * @subpackage Plugin Functions
     * @author Mauko Maunde < hi@mauko.co.ke >
     * @since 0.18.01
     */
    public static function wc_mpesa_menu()
    {
        $mpesa = get_option('woocommerce_mpesa_settings');
        if(isset($mpesa["enable_b2c"]) && ($mpesa["enable_b2c"] == 'yes')){
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
            __('Configuration', 'woocommerce'), 
            __('Configuration', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_preferences', 
            [new self, 'wc_mpesa_menu_settings' ]
        );

        add_submenu_page(
            'edit.php?post_type=mpesaipn', 
            __('About this Plugin', 'woocommerce'), 
            __('About Plugin', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_about', 
            [new self, 'wc_mpesa_menu_about'] 
        );

        if(isset($mpesa["enable_b2c"]) && ($mpesa["enable_b2c"] == 'yes')){
            add_submenu_page(
                'edit.php?post_type=mpesaipn', 
                __('M-PESA B2C Preferences', 'woocommerce'), 
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
            __('M-PESA Analytics', 'woocommerce'),
            __('Analytics', 'woocommerce'), 
            'manage_options',
            'wc_mpesa_analytics', 
            [new self, 'wc_mpesa_menu_analytics'] 
        );

        if(isset($mpesa["env"]) && ($mpesa["env"] == 'sandbox')){
            add_submenu_page(
                'edit.php?post_type=mpesaipn', 
                __('Going Live', 'woocommerce'), 
                __('Going Live', 'woocommerce'), 
                'manage_options',
                'wc_mpesa_live', 
                [new self, 'wc_mpesa_menu_live'] 
            );
        }

    }

    public static function wc_mpesa_menu_about()
    { ?>
        <div class="wrap">
            <h1>About M-PESA for WooCommerce</h1>

            <h3>The Plugin</h3>
            <article>
                <p>This plugin seeks to provide a simple plug-n-play implementation for integrating M-PESA Payments into online
                    stores built with WooCommerce and WordPress.</p>
            </article>

            <h3>Development & Contribution</h3>
            <article>
                <p>To help improve and support our effort to make such solutions as this one, you can start by contributing
                    here:</p>
                <ol>
                    <li><a href="https://github.com/osenco/osen-wc-mpesa" target="_blank">This Plugin's Github Repo</a></li>
                    <li><a href="https://github.com/osenco/mpesa" target="_blank">M-PESA PHP SDK</a></li>
                    <li><a href="https://github.com/osenco/osen-oc-mpesa" target="_blank">M-PESA For Open Cart</a></li>
                    <li><a href="https://github.com/osenco/osen-ci-mpesa" target="_blank">M-PESA For CodeIgniter</a></li>
                    <li><a href="https://github.com/osenco/osen-presta-mpesa" target="_blank">M-PESA For PrestaShop</a></li>
                </ol>
            </article>

            <h3>Contact</h3>
            <h3>Get in touch with us either via email (<a href="mail-to:hi@osen.co.ke">hi@osen.co.ke</a>) or via phone(<a
                    href="tel:+254204404993">+254204404993</a>)</h3>

            <img src="<?php echo plugins_url('osen-wc-mpesa/inc/mpesa.png'); ?>">
        </div><?php
    }

    public static function wc_mpesa_menu_live()
    { ?>
        <div class="wrap" style="background: white; padding: 2%;">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <article>
                <p>To go live you need to prove ownership or authorization to use an M-PESA shortcode. When a request to verify
                    ownership or authorization to use an M-PESA shortcode is received, an OTP - one time password - is sent to
                    the user with a Business Manager and Business Administrator role.</p>
                <p>Please <a href="https://developer.safaricom.co.ke/" target="_blank">create an account on Daraja</a> if you
                    haven't.</p>
            </article>

            <h3>Upload test cases</h3>
            <article>
                <p>Login to <a href="https://developer.safaricom.co.ke/" target="_blank">Daraja</a> and from the menu bar, click
                    on ‘Go Live’</p>

                <img src="<?php echo plugins_url('osen-wc-mpesa/assets/live/go_live.png'); ?>" width="80%">

                <p>This plugin has been tested on several websites, and is preconfigured to work without much fuss. It is,
                    however, imperative to run your own tests to ensure your server meets the requirements.</p>

                <p>For convenience, we have provided a pre-filled <a
                        href="<?php echo plugins_url('osen-wc-mpesa/assets/testcases.xlsx'); ?>" download="testcases.xlsx"
                        title="Click to download testcases.xlsx">testcases.xlsx</a> file here that you can use. Select it and
                    click ‘Upload’.</p>

                <img src="<?php echo plugins_url('osen-wc-mpesa/assets/live/go_live_step_1.png'); ?>" width="90%">
                <p>Once uploaded click on the check box as consent to the ‘Terms and Conditions’. Click on ‘Next’.</p>
            </article>

            <h3>Shortcode Verification</h3>
            <article>
                <p>To verify an M-PESA Shortcode - input your ‘organisation short code’, ‘organisation name’ and ‘M-PESA User
                    Name’.</p>

                <p>The ‘M-PESA User Name’ is user name used on the M-PESA Web Portal for the Business Administrator or Business
                    Manager or Business Operator roles within the organisation in M-PESA.</p>

                <img src="<?php echo plugins_url('osen-wc-mpesa/assets/live/go_live_step_2b.png'); ?>" width="90%">
                <p>Click ‘Verify’</p>
            </article>

            <h3>OTP Confirmation</h3>
            <article>
                <p>During the verification stage you will be required to receive an OTP so as to verify you are the owner of the
                    paybill.</p>
                <p>The OTP will be sent to MSISDN belonging to the M-PESA Business Administrator or Manager or Operator.</p>

                <img src="<?php echo plugins_url('osen-wc-mpesa/assets/live/go_live_step_3.png'); ?>" width="90%">

                <p>The OTP will expire after three minutes. If an OTP is not submitted for confirmation within three minutes you
                    can request a new OTP by clicking ‘Resend OTP’. All OTPs will be sent via SMS. The M- Pesa Business Manager
                    or Business Operator will need to log on to the M-PESA Web portal and update their profiles with their
                    current MSISDNs.</p>

                <p>The developer will select the API Products they will be integrating to, select only the API Products for
                    which you have filled test case scenarios for in the filled test cases Excel spreadsheet uploaded at step 1.
                    The API products displayed will depend on the M-PESA product type configured on M-PESA for your shortcode.
                </p>

                <p>Click ‘Submit’ once you have selected the API Product(s) and input the OTP.</p>
            </article>

            <h3>Production app details</h3>
            <article>
                <img src="<?php echo plugins_url('osen-wc-mpesa/assets/live/go_live_step_4.png'); ?>" width="90%">
                <p>If the process is successful, a new app will be created for you. Note its name/ID</p>
            </article>

            <h3>Getting the online passkey</h3>
            <article>
                <p>Email <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a> or initiate a chat on the <a
                        href="https://developer.safaricom.co.ke/">developer portal</a>, requesting for the online passkey for
                    your app. Remember to send your app ID and shortcode as well.</p>
            </article>

            <h3>C2B Confirmation and Validation URLs</h3>
            <article>
                <p>Whenever M-PESA receives a transaction on your shortcode, a validation request is sent to the validation URL
                    registered above. M-PESA completes or cancels the transaction depending on the validation response it
                    receives.</p>
                <p>These URLs must be HTTPS in production. Validation is an optional feature that needs to be activated on
                    M-PESA, the owner of the shortcode needs to make this request for activation. This can be done by sending an
                    email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on
                    the <a href="https://developer.safaricom.co.ke/">developer portal</a>.</p>
                <p>Once validation has been activated, <a class="button button-secondary"
                        href="<?php echo home_url('lnmo/register/'); ?>">Click here to register confirmation & validation
                        URLs</a></p>
            </article>
        </div><?php
    }

    public static function wc_mpesa_menu_analytics()
    {
        $payments = array();
        $months = array();
        $monthly = array();
        $posts = array();

        foreach (get_posts(['post_type' => 'mpesaipn']) as $post) {
            $post_date = strtotime($post->post_date);
            for ($i=1; $i <= 12 ; $i++) { 
                if (date('Y', $post_date) == date('Y')) {
                    if(date('m', $post_date) == $i){
                        $months[$i][] = (int)get_post_meta( $post->ID, '_amount', true );
                    } else {
                        $months[$i][] = 0;
                    }
                }
            }

            array_push($monthly, date('m', $post_date));

        }

        foreach ($months as $month => $values) {
            $payments[$month] = array_sum($values);
        }

        $ms = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $ps = array_values($payments); ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php wp_enqueue_style('c3', plugins_url('osen-wc-mpesa/assets/c3/c3.min.css')); ?>
            <h3><?php _e('Total monthly payments received via M-PESA for the year'); ?> <?php echo date('Y'); ?></h3>
            <br>
            <div id="chart-bar" style="height: 500px"></div>
            <?php wp_enqueue_script('c3', plugins_url('osen-wc-mpesa/assets/c3/c3.bundle.js')); ?>
            <script type="text/javascript">
            jQuery(function() {
                "use strict";
                var chart = c3.generate({
                    bindto: '#chart-bar', // id of chart wrapper
                    data: {
                        type: 'line',
                        columns: [
                            // each columns data
                            <?php echo json_encode(array_merge(['data1'], $ps)); ?>,
                        ],
                        colors: {
                            'data1': '#04a4cc', // blue
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

    public static function wc_mpesa_b2c_settings()
    {
        wp_redirect(admin_url('admin.php?post_type=b2c_payment&page=wcmpesab2c'));
    }

    public static function wc_mpesa_menu_b2c()
    {
        wp_redirect(admin_url('edit.php?post_type=b2c_payment'));
    }
}
