<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

use Osen\Woocommerce\Mpesa\C2B;
use Osen\Woocommerce\Mpesa\STK;

function wc_mpesa_post_id_by_meta_key_and_value($key, $value)
{
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='" . $key . "' AND meta_value='" . $value . "'");
    if (is_array($meta) && !empty($meta) && isset($meta[0])) {
        $meta = $meta[0];
    }

    if (is_object($meta)) {
        return $meta->post_id;
    } else {
        return false;
    }
}

/**
 * Register our gateway with woocommerce
 */
add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = 'WC_MPESA_Gateway';
    return $gateways;
}, 9);

add_action('plugins_loaded', function () {
    if (class_exists('WC_Payment_Gateway')) {

        /**
         * @class WC_Gateway_MPesa
         * @extends WC_Payment_Gateway
         */
        class WC_MPESA_Gateway extends WC_Payment_Gateway
        {
            public $sign;
            public $debug           = false;
            public $enable_c2b      = false;
            public $enable_reversal = false;

            /**
             * Constructor for the gateway.
             */
            public function __construct()
            {
                $this->id           = 'mpesa';
                $this->icon         = apply_filters('woocommerce_mpesa_icon', plugins_url('assets/mpesa.png', __FILE__));
                $this->method_title = __('Lipa Na M-Pesa', 'woocommerce');
                $this->has_fields   = true;

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables.
                $this->title              = $this->get_option('title');
                $this->description        = $this->get_option('description');
                $this->instructions       = $this->get_option('instructions');
                $this->enable_for_methods = $this->get_option('enable_for_methods', array());
                $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';
                $this->sign               = $this->get_option('signature', md5(rand(12, 999)));
                $this->enable_reversal    = $this->get_option('enable_reversal', 'no') === 'yes';
                $this->enable_c2b         = $this->get_option('enable_c2b', 'no') === 'yes';
                $this->enable_bonga       = $this->get_option('enable_bonga', 'no') === 'yes';
                $this->debug              = $this->get_option('debug', 'no') === 'yes';
                $this->shortcode          = $this->get_option('shortcode');
                $this->type               = $this->get_option('type', 4);
                $this->env                = $this->get_option('env', 'sandbox');

                $test_cred = ($this->env === 'sandbox')
                    ? '<li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank" >get sandbox test credentials here</a>.</li>'
                    : '';
                $register = isset($_GET['mpesa-urls-registered']) ? '<div class="updated ' . ($_GET['reg-state'] ?? 'notice') . ' is-dismissible">
                                        <p>' . $_GET['mpesa-urls-registered'] . '</p>
                                    </div>' : '';

                $this->method_description = $register . (($this->env === 'live') ? __('Receive payments via Safaricom M-PESA', 'woocommerce') : __('<h4 style="color: red;">IMPORTANT!</h4>' . '<li>Please <a href="https://developer.safaricom.co.ke/" target="_blank" >create an app on Daraja</a> if you haven\'t. If yoou already have a production app, fill in the app\'s consumer key and secret below.</li><li>Ensure you have access to the <a href="https://org.ke.m-pesa.com/">MPesa Web Portal</a>. You\'ll need this to go LIVE.</li><li>For security purposes, and for the MPesa Instant Payment Notification to work seamlessly, ensure your site is running over https(with valid SSL).</li>' . $test_cred) . '<li>We have a <a target="_blank" href="https://wcmpesa.co.ke/going-live">nice tutorial</a> here on migrating from Sandbox(test) environment, to Production(live) environment.<br> We offer the service  at a fiat fee of KSh 4000. Call <a href="tel:+254204404993">+254204404993</a> or email <a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a> if you need help.</li>');

                add_action('woocommerce_thankyou_mpesa', array($this, 'thankyou_page'));
                add_action('woocommerce_thankyou_mpesa', array($this, 'request_body'));
                add_action('woocommerce_thankyou_mpesa', array($this, 'validate_payment'));

                add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);
                add_action('woocommerce_email_before_order_table', array($this, 'email_mpesa_receipt'), 10, 4);

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                add_action('woocommerce_api_lipwa', array($this, 'webhook'));
                add_action('woocommerce_api_lipwa_receipt', array($this, 'get_transaction_id'));

                $statuses = $this->get_option('statuses', array());
                foreach ((array) $statuses as $status) {
                    $status_array = explode('-', $status);
                    $status       = array_pop($status_array);

                    add_action("woocommerce_order_status_{$status}", array($this, 'process_mpesa_reversal'), 1);
                }
            }

            /**
             * Initialise Gateway Settings Form Fields.
             */
            public function init_form_fields()
            {
                $this->sign       = $this->get_option('signature', md5(rand(12, 999)));
                $this->debug      = $this->get_option('debug', 'no') === 'yes';
                $this->enable_c2b = $this->get_option('enable_c2b', 'no') === 'yes';

                $shipping_methods = array();
                foreach (WC()->shipping()->load_shipping_methods() as $method) {
                    $shipping_methods[$method->id] = $method->get_method_title();
                }

                $this->form_fields = array(
                    'enabled'            => array(
                        'title'       => __('Enable/Disable', 'woocommerce'),
                        'label'       => __('Enable ' . $this->method_title, 'woocommerce'),
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'yes',
                    ),
                    'title'              => array(
                        'title'       => __('Method Title', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Payment method name that the customer will see on your checkout.', 'woocommerce'),
                        'default'     => __('Lipa Na MPesa', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'env'                => array(
                        'title'       => __('Environment', 'woocommerce'),
                        'type'        => 'select',
                        'options'     => array(
                            'sandbox' => __('Sandbox', 'woocommerce'),
                            'live'    => __('Live', 'woocommerce'),
                        ),
                        'description' => __('MPesa Environment', 'woocommerce'),
                        'desc_tip'    => true,
                        'class'       => 'select2 wc-enhanced-select',
                    ),
                    'idtype'             => array(
                        'title'       => __('Identifier Type', 'woocommerce'),
                        'type'        => 'select',
                        'options'     => array(
                            /**1 => __('MSISDN', 'woocommerce'),*/
                            4 => __('Paybill Number', 'woocommerce'),
                            2 => __('Till Number', 'woocommerce'),
                        ),
                        'description' => __('MPesa Identifier Type', 'woocommerce'),
                        'desc_tip'    => true,
                        'class'       => 'select2 wc-enhanced-select',
                    ),
                    'headoffice'         => array(
                        'title'       => __('Store Number', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Your Store Number. Use "Online Shortcode" in Sandbox', 'woocommerce'),
                        'default'     => __('174379', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'shortcode'          => array(
                        'title'       => __('Business Shortcode', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Your MPesa Business Till/Paybill Number. Use "Online Shortcode" in Sandbox', 'woocommerce'),
                        'default'     => __('174379', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'key'                => array(
                        'title'       => __('App Consumer Key', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Your App Consumer Key From Safaricom Daraja.', 'woocommerce'),
                        'default'     => __('9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'secret'             => array(
                        'title'       => __('App Consumer Secret', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Your App Consumer Secret From Safaricom Daraja.', 'woocommerce'),
                        'default'     => __('bclwIPkcRqw61yUt', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'passkey'            => array(
                        'title'       => __('Online Pass Key', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Used to create a password for use when making a Lipa Na M-Pesa Online Payment API call.', 'woocommerce'),
                        'default'     => __('bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', 'woocommerce'),
                        'desc_tip'    => true,
                        'class'       => 'wide-input',
                        'css'         => 'min-width: 45%;',
                    ),
                    'signature'          => array(
                        'title'       => __('Encryption Signature', 'woocommerce'),
                        'type'        => 'password',
                        'description' => __('Random string for Callback Endpoint Encryption Signature', 'woocommerce'),
                        'default'     => $this->sign,
                        'desc_tip'    => true,
                    ),
                    'resend'             => array(
                        'title'       => __('Resend STK Button Text', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Text description for resend STK prompt button', 'woocommerce'),
                        'default'     => __('Resend STK Push', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'description'        => array(
                        'title'       => __('Method Description', 'woocommerce'),
                        'type'        => 'textarea',
                        'description' => __('Payment method description that the customer will see during checkout.', 'woocommerce'),
                        'default'     => __("Cross-check your details before pressing the button below.\nYour phone number MUST be registered with MPesa(and ON) for this to work.\nYou will get a pop-up on your phone asking you to confirm the payment.\nEnter your service (MPesa) PIN to proceed.\nIn case you don't see the pop up on your phone, please upgrade your SIM card by dialing *234*1*6#.\nYou will receive a confirmation message shortly thereafter.", 'woocommerce'),
                        'desc_tip'    => true,
                        'css'         => 'height:150px',
                    ),
                    'instructions'       => array(
                        'title'       => __('Instructions', 'woocommerce'),
                        'type'        => 'textarea',
                        'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
                        'default'     => __('Thank you for shopping with us.', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'completion'         => array(
                        'title'       => __('Order Status on Payment', 'woocommerce'),
                        'type'        => 'select',
                        'options'     => array(
                            'completed'  => __('Mark order as completed', 'woocommerce'),
                            'on-hold'    => __('Mark order as on hold', 'woocommerce'),
                            'processing' => __('Mark order as processing', 'woocommerce'),
                        ),
                        'description' => __('What status to set the order after Mpesa payment has been received', 'woocommerce'),
                        'desc_tip'    => true,
                        'class'       => 'select2 wc-enhanced-select',
                    ),
                    'enable_for_methods' => array(
                        'title'             => __('Enable for shipping methods', 'woocommerce'),
                        'type'              => 'multiselect',
                        'class'             => 'wc-enhanced-select',
                        'css'               => 'width: 400px;',
                        'default'           => '',
                        'description'       => __('If MPesa is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
                        'options'           => $shipping_methods,
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select shipping methods', 'woocommerce'),
                        ),
                    ),
                    'enable_for_virtual' => array(
                        'title'   => __('Accept for virtual orders', 'woocommerce'),
                        'label'   => __('Accept MPesa if the order is virtual', 'woocommerce'),
                        'type'    => 'checkbox',
                        'default' => 'yes',
                    ),
                    'debug'              => array(
                        'title'       => __('Debug Mode', 'woocommerce'),
                        'label'       => __('Check to enable debug mode and show request body', 'woocommerce'),
                        'type'        => 'checkbox',
                        'default'     => 'no',
                        'description' => $this->debug ? '<small>Use the following URLs: <ul>
                        <li>Validation URL for C2B: <a href="' . home_url('wc-api/lipwa?action=validate&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=validate&sign=' . $this->sign) . '</a></li>
                        <li>Confirmation URL for C2B: <a href="' . home_url('wc-api/lipwa?action=confirm&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=confirm&sign=' . $this->sign) . '</a></li>
                        <li>Reconciliation URL for STK Push: <a href="' . home_url('wc-api/lipwa?action=reconcile&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=reconcile&sign=' . $this->sign) . '</a></li>
                        </ul></small>' : __('Show Request Body(to send to Daraja team on request).</small> ', 'woocommerce'),
                    ),
                    'c2b_section'        => array(
                        'title'       => __('M-Pesa Manual Payments', 'woocommerce'),
                        'description' => __('Enable C2B API(Offline Payments and Lipa Na Bonga Points)', 'woocommerce'),
                        'type'        => 'title',
                    ),
                    'enable_c2b'         => array(
                        'title'       => __('Enable Manual Payments', 'woocommerce'),
                        'label'       => __('Enable C2B API(Offline Payments)', 'woocommerce'),
                        'type'        => 'checkbox',
                        'description' => '<small>This requires C2B Validation, which is an optional feature that needs to be activated on M-Pesa. <br>Request for activation by sending an email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on the <a href="https://developer.safaricom.co.ke/">developer portal.</a><br><br> <a class="button button-secondary" href="' . home_url('wc-api/lipwa?action=register') . '">Once enabled, click here to register confirmation & validation URLs</a><br><i>Kindly note that if this is disabled, the user can still resend an STK push if the first one fails.</i></small>',
                        'default'     => 'no',
                    ),
                    'enable_bonga'       => array(
                        'title'       => __('Bonga Points', 'woocommerce'),
                        'label'       => __('Enable Lipa Na Bonga Points', 'woocommerce'),
                        'type'        => 'checkbox',
                        'description' => $this->enable_c2b ? '<small>This requires C2B Validation, which is an optional feature that needs to be activated on M-Pesa. <br>Request for activation by sending an email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on the <a href="https://developer.safaricom.co.ke/">developer portal.</a></small>' : '',
                        'default'     => 'no',
                        'desc_tip'    => true,
                    ),
                    'reversal_section'   => array(
                        'title'       => __('M-Pesa Transaction Reversal', 'woocommerce'),
                        'description' => __('Enable reversal API(On status change)', 'woocommerce'),
                        'type'        => 'title',
                    ),
                    'enable_reversal'    => array(
                        'title'       => __('Reversals', 'woocommerce'),
                        'label'       => __('Enable Reversal on Status change', 'woocommerce'),
                        'type'        => 'checkbox',
                        'description' => $this->enable_reversal ? '<small>This requires a user with Transaction Reversal Change</small>' : '',
                        'default'     => 'no',
                        'desc_tip'    => true,
                    ),
                    'initiator'          => array(
                        'title'       => __('Initiator Username', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Username for user with Reversal Role.', 'woocommerce'),
                        'default'     => __('test', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'password'           => array(
                        'title'       => __('Initiator Password', 'woocommerce'),
                        'type'        => 'password',
                        'description' => __('Password for user with Reversal Role.', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    'statuses'           => array(
                        'title'             => __('Order Statuses', 'woocommerce'),
                        'type'              => 'multiselect',
                        'options'           => wc_get_order_statuses(),
                        'placeholder'       => __('Select statuses', 'woocommerce'),
                        'description'       => __('Status changes for which to reverse transactions.', 'woocommerce'),
                        'desc_tip'          => true,
                        'class'             => 'select2 wc-enhanced-select',
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select order statuses to reverse', 'woocommerce'),
                        ),
                    ),
                );
            }

            /**
             * Check If The Gateway Is Available For Use.
             *
             * @return bool
             */
            public function is_available()
            {
                $order          = null;
                $needs_shipping = false;

                if (WC()->cart && WC()->cart->needs_shipping()) {
                    $needs_shipping = true;
                } elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
                    $order_id = absint(get_query_var('order-pay'));
                    $order    = wc_get_order($order_id);

                    if (0 < sizeof($order->get_items())) {
                        foreach ($order->get_items() as $item) {
                            $_product = wc_get_product($item['product_id']);
                            if ($_product && $_product->needs_shipping()) {
                                $needs_shipping = true;
                                break;
                            }
                        }
                    }
                }

                $needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

                // Virtual order, with virtual disabled
                if (!$this->enable_for_virtual && !$needs_shipping) {
                    return false;
                }

                // Only apply if all packages are being shipped via chosen method, or order is virtual.
                if (!empty($this->enable_for_methods) && $needs_shipping) {
                    $chosen_shipping_methods = array();

                    if (is_object($order)) {
                        $chosen_shipping_methods = array_unique(array_map('wc_get_string_before_colon', $order->get_shipping_methods()));
                    } elseif ($chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods')) {
                        $chosen_shipping_methods = array_unique(array_map('wc_get_string_before_colon', $chosen_shipping_methods_session));
                    }

                    if (0 < count(array_diff($chosen_shipping_methods, $this->enable_for_methods))) {
                        return false;
                    }
                }

                return parent::is_available();
            }

            /**
             * 
             */
            public function payment_fields()
            {
                if ($description = $this->get_description()) {
                    echo wpautop(wptexturize($description));
                }
                echo '<div id="custom_input"><br>
                    <p class="form-row form-row-wide">
                        <label for="mobile" class="form-label">' . __("Confirm M-PESA Number", "woocommerce") . ' </label>
                        <input type="text" class="form-control" name="billing_mpesa_phone" id="billing_mpesa_phone" />
                    </p>
                </div>';
            }

            /**
             * 
             */
            public function validate_fields()
            {
                if (empty($_POST['billing_mpesa_phone'])) {
                    wc_add_notice('M-PESA phone number is required!', 'error');
                    return false;
                }

                return true;
            }

            /**
             * Check for current vendor ID
             *
             * @param WC_Order $order
             * @return int|null
             */
            function check_vendor(WC_Order $order)
            {
                $vendor_id = null;
                $items     = $order->get_items('line_item');

                if (function_exists('wcfm_get_vendor_id_by_post') && !empty($items)) {
                    foreach ($items as $item) {
                        $line_item  = new WC_Order_Item_Product($item);
                        $product_id = $line_item->get_product_id();
                        $vendor_id  = wcfm_get_vendor_id_by_post($product_id);
                    }
                }

                if (class_exists('WC_Product_Vendors_Utils')) {
                    foreach ($items as $item) {
                        $line_item  = new WC_Order_Item_Product($item);
                        $product_id = $line_item->get_product_id();
                        $vendor_id  = WC_Product_Vendors_Utils::get_vendor_id_from_product($product_id);
                    }
                }

                return $vendor_id;
            }

            /**
             * Process the payment and return the result.
             *
             * @param int $order_id
             * @return array
             */
            public function process_payment($order_id)
            {
                $order     = new \WC_Order($order_id);
                $total     = $order->get_total();
                $phone     = sanitize_text_field($_POST['billing_mpesa_phone'] ?? $order->get_billing_phone());
                $sign      = get_bloginfo('name');
                $vendor_id = $this->check_vendor($order);

                if ($this->debug) {
                    $result = (new STK($vendor_id))
                        ->authorize(get_transient('mpesa_token'))
                        ->request($phone, $total, $order_id, $sign . ' Purchase', 'WCMPesa', true);
                    $message = wp_json_encode($result['requested']);
                    WC()->session->set('mpesa_request', $message);
                } else {
                    $result = (new STK($vendor_id))
                        ->authorize(get_transient('mpesa_token'))
                        ->request($phone, $total, $order_id, $sign . ' Purchase', 'WCMPesa');
                }

                if ($result) {
                    if (isset($result['errorCode'])) {
                        wc_add_notice(__("(MPesa Error) {$result['errorCode']}: {$result['errorMessage']}.", 'woocommerce'), 'error');

                        if ($this->debug && WC()->session->get('mpesa_request')) {
                            wc_add_notice(__('Request: ' . WC()->session->get('mpesa_request'), 'woocommerce'), 'error');
                        }

                        return array(
                            'result'   => 'fail',
                            'redirect' => '',
                        );
                    }

                    if (isset($result['MerchantRequestID'])) {
                        update_post_meta($order_id, 'mpesa_request_id', $result['MerchantRequestID']);
                        $order->add_order_note(
                            __("Awaiting MPesa confirmation of payment from {$phone} for request {$result['MerchantRequestID']}.", 'woocommerce')
                        );
                        $order->add_order_note(
                            __("Your order #{$order_id} has been received and is awaiting MPesa confirmation. Request ID: {$result['MerchantRequestID']}.", 'woocommerce'),
                            true
                        );

                        /**
                         * Remove contents from cart
                         */
                        WC()->cart->empty_cart();

                        // Return thankyou redirect
                        return array(
                            'result'   => 'success',
                            'redirect' => $this->get_return_url($order),
                        );
                    }
                } else {
                    wc_add_notice(__('Failed! Could not connect to Daraja', 'woocommerce'), 'error');

                    return array(
                        'result'   => 'fail',
                        'redirect' => '',
                    );
                }
            }

            /**
             * Validate the payment on thank you page.
             *
             * @param int $order_id
             * @return array
             */
            public function validate_payment($order_id)
            {
                if (wc_get_order($order_id)) {
                    $order = new \WC_Order($order_id);
                    $total = $order->get_total();
                    $mpesa = new STK($this->check_vendor($order));
                    $type  = ($mpesa->type === 4) ? 'Pay Bill' : 'Buy Goods and Services';

                    echo
                    '<section class="woocommerce-order-details" id="resend_stk">
                        <input type="hidden" id="current_order" value="' . $order_id . '">
                        <input type="hidden" id="payment_method" value="' . $order->get_payment_method() . '">
                        <p class="checking" id="mpesa_receipt">Confirming receipt, please wait</p>
                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <tbody>
                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-name product-name">
                                        <form action="' . home_url("wc-api/lipwa?action=request") . '" method="POST" id="renitiate-mpesa-form">
                                            <input type="hidden" name="order" value="' . $order_id . '">
                                            <button id="renitiate-mpesa-button" class="button alt" type="submit">' . ($this->settings['resend'] ?? 'Resend STK Push') . '</button>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>';

                    if ($this->settings['enable_c2b']) {
                        echo
                        '<section class="woocommerce-order-details" id="missed_stk">
                            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                                <thead>
                                    <tr>
                                        <th class="woocommerce-table__product-name product-name">
                                            ' . __("STK Push didn't work? Pay Manually Via M-PESA", "woocommerce") . '
                                        </th>'
                            . ($this->settings['enable_bonga'] ?
                                '<th>&nbsp;</th>' : '') . '
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr class="woocommerce-table__line-item order_item">
                                        <td class="woocommerce-table__product-name product-name">
                                            <ol>
                                                <li>Select <b>Lipa na M-PESA</b>.</li>
                                                <li>Select <b>' . $type . '</b>.</li>
                                                ' . (($mpesa->type === 4) ? "<li>Enter <b>{$mpesa->shortcode}</b> as business no.</li><li>Enter <b>{$order_id}</b> as Account no.</li>" : "<li>Enter <b>{$mpesa->shortcode}</b> as till no.</li>") . '
                                                <li>Enter Amount <b>' . round($total) . '</b>.</li>
                                                <li>Enter your M-PESA PIN</li>
                                                <li>Confirm your details and press OK.</li>
                                                <li>Wait for a confirmation message from M-PESA.</li>
                                            </ol>
                                        </td>'
                            . ($this->settings['enable_bonga'] ?
                                '<td class="woocommerce-table__product-name product-name">
                                            <ol>
                                                <li>Dial *236# and select <b>Lipa na Bonga Points</b>.</li>
                                                <li>Select <b>' . $type . '</b>.</li>
                                                ' . (($mpesa->type === 4) ? "<li>Enter <b>{$mpesa->shortcode}</b> as business no.</li><li>Enter <b>{$order_id}</b> as Account no.</li>" : "<li>Enter <b>{$mpesa->shortcode}</b> as till no.</li>") . '
                                                <li>Enter Amount <b>' . round($total) . '</b>.</li>
                                                <li>Enter your M-PESA PIN</li>
                                                <li>Confirm your details and press OK.</li>
                                                <li>Wait for a confirmation message from M-PESA.</li>
                                            </ol>
                                        </td>' : '') . '
                                    </tr>
                                </tbody>
                            </table>
                        </section>';
                    }
                }
            }

            /**
             * @since 1.20.79
             */
            public function request_body()
            {
                if ($this->debug) {
                    echo '
                    <section class="woocommerce-order-details" id="mpesa_request">
                    <p>Mpesa request body</p>
                        <code>' . WC()->session->get('mpesa_request') . '</code>
                    </section>';
                }
            }

            /**
             * Add content to the WC completed email.
             *
             * @since 3.0.0
             * @access public
             * @param \WC_Order $order
             * @param bool $sent_to_admin
             * @param bool $plain_text
             * @param \WC_Email $email
             */
            function email_mpesa_receipt($order, $sent_to_admin = false, $plain_text = false, $email)
            {
                if ($email->id === 'customer_completed_order' && $order->get_transaction_id() && $order->get_payment_method() === 'mpesa') {
                    $receipt = $order->get_transaction_id();
                    echo '<dl>
                        <dt>Payment received via MPesa</dt>
                        <dd>Transaction ID: ' . $receipt . '</dd>
                    </dl>';
                }
            }

            /**
             * Process webhook information such as IPN
             * 
             * @since 2.3.1
             */
            public function webhook()
            {
                $action = $_GET['action'] ?? 'validate';

                switch ($action) {
                    case "request":
                        $order_id  = sanitize_text_field($_POST['order']);
                        $order     = new \WC_Order($order_id);
                        $vendor_id = $this->check_vendor($order);
                        $total     = $order->get_total();
                        $phone     = $order->get_billing_phone();
                        $mpesa     = new STK($vendor_id);

                        $result = $mpesa->authorize(get_transient('mpesa_token'))
                            ->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');

                        if (isset($result['MerchantRequestID'])) {
                            update_post_meta($order_id, 'mpesa_request_id', $result['MerchantRequestID']);
                        }

                        wp_send_json($result);
                        break;
                    case "validate":
                        wp_send_json((new STK)->validate());
                        break;

                    case "reconcile":
                        $mpesa = new STK();
                        $sign  = sanitize_text_field($_GET['sign']);

                        wp_send_json($mpesa->reconcile(function ($response) use ($sign, $mpesa) {
                            if (isset($sign) && $sign === $this->get_option('signature')) {
                                if (isset($response['Body'])) {
                                    $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                                    $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                                    $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];
                                    $order_id          = $_GET['order'] ?? wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $merchantRequestID);

                                    if (wc_get_order($order_id)) {
                                        $order     = new \WC_Order($order_id);
                                        $FirstName = $order->get_billing_first_name();

                                        if ($order->get_status() === 'completed') {
                                            return;
                                        }

                                        if (isset($response['Body']['stkCallback']['CallbackMetadata'])) {
                                            $parsed = array();
                                            foreach ($response['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
                                                $parsed[$item['Name']] = $item['Value'];
                                            }

                                            $order->update_status(
                                                $this->get_option('completion', 'completed'),
                                                __("Full MPesa Payment Received From {$parsed['PhoneNumber']}. Receipt Number {$parsed['MpesaReceiptNumber']}.")
                                            );
                                            $order->add_order_note("Hello {$FirstName}, Your M-PESA payment has been recieved successfully, with receipt number {$parsed['MpesaReceiptNumber']}.", true);
                                            $order->set_transaction_id($parsed['MpesaReceiptNumber']);
                                            $order->save();

                                            do_action('send_to_external_api', $order, $parsed, $this->settings);
                                        } else {
                                            $order->update_status(
                                                'on-hold',
                                                __("(MPesa Error) {$resultCode}: {$resultDesc}.")
                                            );
                                        }

                                        return true;
                                    }
                                }
                            }

                            return false;
                        }));
                        break;

                    case "confirm":
                        wp_send_json((new STK)->confirm(function ($response = array()) {
                            if (empty($response)) {
                                wp_send_json(
                                    ['Error' => 'No response data received']
                                );
                            }

                            $MpesaReceiptNumber = $response['TransID'];
                            $TransactionDate    = $response['TransTime'];
                            $Amount             = (int) $response['TransAmount'];
                            $BillRefNumber      = $response['BillRefNumber'];
                            $PhoneNumber        = $response['MSISDN'];
                            $FirstName          = $response['FirstName'];
                            $MiddleName         = $response['MiddleName'];
                            $LastName           = $response['LastName'];
                            $parsed             = compact("Amount", "MpesaReceiptNumber", "TransactionDate", "PhoneNumber");
                            $order_id           = $BillRefNumber ?? wc_mpesa_post_id_by_meta_key_and_value('mpesa_reference', $BillRefNumber);

                            if (wc_get_order($order_id)) {
                                $order       = new \WC_Order($order_id);
                                $total       = round($order->get_total());
                                $ipn_balance = $total - round($Amount);

                                if ($order->get_status() === 'completed') {
                                    return;
                                }

                                if ($ipn_balance === 0) {
                                    $order->update_status(
                                        $this->get_option('completion', 'completed'),
                                        __("Full MPesa Payment Received From {$PhoneNumber}. Receipt Number {$MpesaReceiptNumber}")
                                    );
                                    $order->add_order_note("Hello {$FirstName}, Your M-PESA payment has been recieved successfully, with receipt number {$parsed['MpesaReceiptNumber']}.", true);
                                    $order->set_transaction_id($MpesaReceiptNumber);
                                    $order->save();

                                    do_action('send_to_external_api', $order, $parsed, $this->settings);

                                    return true;
                                } elseif ($ipn_balance < 0) {
                                    $currency = get_woocommerce_currency();
                                    $order->update_status(
                                        $this->get_option('completion', 'completed'),
                                        __("{$PhoneNumber} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$MpesaReceiptNumber}")
                                    );
                                    $order->add_order_note("Hello {$FirstName}, Your M-PESA payment has been recieved successfully, with receipt number {$parsed['MpesaReceiptNumber']}.", true);
                                    $order->set_transaction_id($MpesaReceiptNumber);
                                    $order->save();

                                    do_action('send_to_external_api', $order, $parsed, $this->settings);

                                    return true;
                                } else {
                                    $order->update_status(
                                        'on-hold',
                                        __("MPesa Payment from {$PhoneNumber} Incomplete")
                                    );
                                }
                            }

                            return false;
                        }));
                        break;

                    case "register":
                        (new C2B)->register(function ($response) {
                            $status = isset($response['ResponseDescription']) ? 'success' : 'fail';
                            if ($status === 'fail') {
                                $message = isset($response['errorMessage']) ? $response['errorMessage'] : 'Could not register M-PESA URLs, try again later.';
                                $state   = 'error';
                            } else {
                                $message = isset($response['ResponseDescription']) ? $response['ResponseDescription'] : 'M-PESA URL registered successfully. You will now receive C2B Payment Notifications.';
                                $state   = 'success';
                            }

                            exit(wp_redirect(
                                add_query_arg(
                                    array(
                                        'mpesa-urls-registered' => $message,
                                        'reg-state'             => $state,
                                    ),
                                    wp_get_referer()
                                )
                            ));
                        });

                        break;

                    case "status":
                        $transaction = sanitize_text_field($_POST['transaction']);
                        wp_send_json((new STK)->status($transaction));
                        break;

                    case "result":
                        $response = json_decode(file_get_contents('php://input'), true);

                        $result = $response['Result'];

                        $ResultType               = $result['ResultType'];
                        $ResultCode               = $result['ResultCode'];
                        $ResultDesc               = $result['ResultDesc'];
                        $OriginatorConversationID = $result['OriginatorConversationID'];
                        $TransactionID            = $result['TransactionID'];

                        $ResultParameters = $result['ResultParameters'];
                        $ResultParameter  = $ResultParameters['ResultParameters']['ResultParameter'];

                        $ReceiptNo         = $ResultParameter[0]['Value'];
                        $ConversationID    = $ResultParameter[0]['Value'];
                        $FinalisedTime     = $ResultParameter[0]['Value'];
                        $Amount            = $ResultParameter[0]['Value'];
                        $TransactionStatus = $ResultParameter[0]['Value'];
                        $ReasonType        = $ResultParameter[0]['Value'];
                        $TransactionReason = $ResultParameter[0]['Value'];
                        $DebitPartyCharges = $ResultParameter[0]['Value'];
                        $DebitAccountType  = $ResultParameter[0]['Value'];
                        $InitiatedTime     = $ResultParameter[0]['Value'];
                        $CreditPartyName   = $ResultParameter[0]['Value'];
                        $DebitPartyName    = $ResultParameter[0]['Value'];

                        $ReferenceData = $result['ReferenceData'];
                        $ReferenceItem = $ReferenceData['ReferenceItem'];
                        $Occasion      = $ReferenceItem[0]['Value'];

                        $order_id = wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $OriginatorConversationID);
                        $order    = new \WC_Order($order_id);

                        if (wc_get_order($order_id)) {
                            $order->update_status('refunded', __($ResultDesc, 'woocommerce'));
                            $order->set_transaction_id($TransactionID);
                            $order->save();
                        } else {
                            $order->update_status('processing', __("{$ResultCode}: {$ResultDesc}", 'woocommerce'));
                        }

                        wp_send_json((new STK)->validate());
                        break;

                    case "timeout":
                        $response = json_decode(file_get_contents('php://input'), true);

                        if (!isset($response['Body'])) {
                            exit(wp_send_json(['Error' => 'No response data received']));
                        }

                        $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                        $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                        $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];

                        $order_id = wc_mpesa_post_id_by_meta_key_and_value('mpesa_request_id', $merchantRequestID);
                        if (wc_get_order($order_id)) {
                            $order = new \WC_Order($order_id);

                            $order->update_status(
                                'pending',
                                __("MPesa Payment Timed Out", 'woocommerce')
                            );
                        }

                        wp_send_json((new STK)->timeout());
                        break;
                    default:
                        wp_send_json((new C2B)->register());
                }
            }

            /**
             * Get order's Transaction ID via AJAX
             * 
             * @since 2.3.1
             */
            public function get_transaction_id()
            {
                $response = array('receipt' => '');

                if (!empty($_GET['order'])) {
                    $order_id = sanitize_text_field($_GET['order']);
                    $order    = wc_get_order(esc_attr($order_id));
                    $notes    = wc_get_order_notes(array(
                        'post_id' => $order_id,
                        'number'  => 1,
                    ));

                    $response = array(
                        'receipt' => $order->get_transaction_id(),
                        'note'    => $notes[0],
                    );
                }

                exit(wp_send_json($response));
            }

            /**
             * Output for the order received page.
             */
            public function thankyou_page()
            {
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions));
                }
            }

            /**
             * Change payment complete order status to completed for MPESA orders.
             *
             * @since  3.1.0
             * @param  string         $status Current order status.
             * @param  int            $order_id Order ID.
             * @param  WC_Order|false $order Order object.
             * @return string
             */
            public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
            {
                if ($order && 'mpesa' === $order->get_payment_method()) {
                    $status = $this->get_option('completion', 'completed');
                }

                return $status;
            }

            /**
             * Process Mpesa transaction reversals on slected statuses
             *
             * @since 3.0.0
             * @param int $order_id
             */
            function process_mpesa_reversal($order_id)
            {
                $order       = wc_get_order($order_id);
                $transaction = $order->get_transaction_id();
                $total       = $order->get_total();
                $phone       = $order->get_billing_phone();
                $amount      = round($total);
                $method      = $order->get_payment_method();

                if ($method === 'mpesa') {
                    $response = (new C2B)
                        ->authorize(get_transient('mpesa_token'))
                        ->reverse($transaction, $amount, $phone);

                    if (isset($response['OriginatorConversationID'])) {
                        update_post_meta($order_id, 'mpesa_request_id', $response['OriginatorConversationID']);
                        $order->update_status('refunded');
                    } elseif (isset($response['errorCode'])) {
                        $order->update_status('failed', $response['errorMessage']);
                    }
                }
            }
        }
    }
}, 11);
