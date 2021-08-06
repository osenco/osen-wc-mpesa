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
add_filter('woocommerce_payment_gateways', 'wc_mpesa_add_to_gateways');
function wc_mpesa_add_to_gateways($gateways)
{
    $gateways[] = 'WC_MPESA_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'wc_mpesa_gateway_init', 11);
function wc_mpesa_gateway_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        /**
         * @class WC_Gateway_MPesa
         * @extends WC_Payment_Gateway
         */
        class WC_MPESA_Gateway extends WC_Payment_Gateway
        {
            public $sign;
            public $token;

            /**
             * Constructor for the gateway.
             */
            public function __construct()
            {
                $this->id           = 'mpesa';
                $this->icon         = apply_filters('woocommerce_mpesa_icon', plugins_url('assets/mpesa.png', __FILE__));
                $this->method_title = __('Lipa Na M-Pesa', 'woocommerce');

                $this->has_fields = true;
                $this->sign       = $this->get_option('signature', md5(random_bytes(12)));
                $this->enable_c2b = $this->get_option('enable_c2b', 'no') === 'yes';
                $this->debug      = $this->get_option('debug', 'no') === 'yes';

                // Load settings
                $this->init_form_fields();
                $this->init_settings();

                $this->token      = get_transient('mpesa_token') ?? null;

                $this->shortcode = $this->get_option('shortcode');
                $this->type      = $this->get_option('type');
                $this->env       = $this->get_option('env', 'sandbox');
                $test_cred       = ($this->env == 'sandbox')
                    ? '<li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank" >get sandbox test credentials here</a>.</li>'
                    : '';
                $color    = isset($_GET['reg-state']) ? $_GET['reg-state'] : 'black';
                $register = isset($_GET['mpesa-urls-registered']) ? "<div style='color: {$color}'>{$_GET['mpesa-urls-registered']}</div>" : '';

                $this->method_description = $register . (($this->env == 'live') ? __('Receive payments via Safaricom M-PESA', 'woocommerce') : __('<h4 style="color: red;">IMPORTANT!</h4>' . '<li>Please <a href="https://developer.safaricom.co.ke/" target="_blank" >create an app on Daraja</a> if you haven\'t. If yoou already have a production app, fill in the app\'s consumer key and secret below.</li><li>Ensure you have access to the <a href="https://org.ke.m-pesa.com/">MPesa Web Portal</a>. You\'ll need this to go LIVE.</li><li>For security purposes, and for the MPesa Instant Payment Notification to work seamlessly, ensure your site is running over https(with valid SSL).</li>' . $test_cred) . '<li>We have a <a target="_blank" href="https://wcmpesa.co.ke/going-live">nice tutorial</a> here on migrating from Sandbox(test) environment, to Production(live) environment.<br> We offer the service  at a fiat fee of KSh 4000. Call <a href="tel:+254204404993">+254204404993</a> or email <a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a> if you need help.</li>');
                $this->has_fields         = false;

                // Get settings
                $this->title              = $this->get_option('title');
                $this->description        = $this->get_option('description');
                $this->instructions       = $this->get_option('instructions');
                $this->enable_for_methods = $this->get_option('enable_for_methods', array());
                $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

                add_action('woocommerce_thankyou_mpesa', array($this, 'thankyou_page'));
                add_action('woocommerce_thankyou_mpesa', array($this, 'request_body'));
                add_action('woocommerce_thankyou_mpesa', array($this, 'validate_payment'));

                add_action('woocommerce_receipt_' . $this->id, array($this, 'thankyou_page'));
                add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                add_action('woocommerce_api_lipwa', array($this, 'webhook'));
                add_action('woocommerce_api_lipwa_receipt', array($this, 'get_receipt'));
            }

            /**
             * Initialise Gateway Settings Form Fields.
             */
            public function init_form_fields()
            {
                $shipping_methods = array();

                foreach (WC()->shipping()->load_shipping_methods() as $method) {
                    $shipping_methods[$method->id] = $method->get_method_title();
                }

                $users = array();
                foreach (get_users(['role__not_in' => ['subscriber']]) as $user) {
                    $users[$user->ID] = esc_html($user->display_name);
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
                    ),
                    'instructions'       => array(
                        'title'       => __('Instructions', 'woocommerce'),
                        'type'        => 'textarea',
                        'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
                        'default'     => __('Thank you for buying from us. Your order will be processed once we confirm your payment.', 'woocommerce'),
                        'desc_tip'    => true,
                    ),
                    // 'account' => array(
                    //     'title'       => __('Account Name', 'woocommerce'),
                    //     'type'        => 'text',
                    //     'description' => __('Account Name to show to customer in STK Push.', 'woocommerce'),
                    //     'default'     => __('WC', 'woocommerce'),
                    //     'desc_tip'    => true,
                    // ),
                    'accountant'         => array(
                        'title'       => __('Accountant', 'woocommerce'),
                        'type'        => 'select',
                        'options'     => $users,
                        'description' => __('WordPress user to assign authorship of payments generated by this plugin', 'woocommerce'),
                        'default'     => __('1', 'woocommerce'),
                        'desc_tip'    => true,
                        'class'       => 'wc-enhanced-select',
                    ),
                    'completion'         => array(
                        'title'       => __('Order Status on Payment', 'woocommerce'),
                        'type'        => 'select',
                        'options'     => array(
                            'completed'  => __('Mark order as completed', 'woocommerce'),
                            'processing' => __('Mark order as processing', 'woocommerce'),
                        ),
                        'description' => __('What status to set the order after Mpesa payment has been received', 'woocommerce'),
                        'desc_tip'    => true,
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
                    'signature'          => array(
                        'title'       => __('Encryption Signature', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Callback Endpoint Encryption Signature', 'woocommerce'),
                        'default'     => $this->sign,
                        'desc_tip'    => true,
                    ),
                    'enable_for_virtual' => array(
                        'title'   => __('Accept for virtual orders', 'woocommerce'),
                        'label'   => __('Accept MPesa if the order is virtual', 'woocommerce'),
                        'type'    => 'checkbox',
                        'default' => 'yes',
                    ),
                    'enable_c2b'         => array(
                        'title'       => __('Manual Payments', 'woocommerce'),
                        'label'       => __('Enable C2B API(Offline Payments)', 'woocommerce'),
                        'type'        => 'checkbox',
                        'description' => $this->enable_c2b ? '<small>This requires C2B Validation, which is an optional feature that needs to be activated on M-Pesa. <br>Request for activation by sending an email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on the <a href="https://developer.safaricom.co.ke/">developer portal.</a><br><br> <a class="button button-secondary" href="' . home_url('wc-api/lipwa?action=register/') . '">Once enabled, click here to register confirmation & validation URLs</a><br><i>Kindly note that if this is disabled, the user can still resend an STK push if the first one fails.</i></small>' : '',
                        'default'     => 'no',
                    ),
                    'debug'              => array(
                        'title'       => __('Debug Mode', 'woocommerce'),
                        'label'       => __('Check to enable debug mode and show request body', 'woocommerce'),
                        'type'        => 'checkbox',
                        'default'     => 'no',
                        'description' => $this->debug ? '<small>' . __('Show Request Body(to send to Daraja team on request). Use the following URLs: <ul>
                        <li>Validation URL for C2B: <a href="' . home_url('wc-api/lipwa?action=validate&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=validate&sign=' . $this->sign) . '</a></li>
                        <li>Confirmation URL for C2B: <a href="' . home_url('wc-api/lipwa?action=confirm&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=confirm&sign=' . $this->sign) . '</a></li>
                        <li>Reconciliation URL for STK Push: <a href="' . home_url('wc-api/lipwa?action=reconcile&sign=' . $this->sign) . '">' . home_url('wc-api/lipwa?action=reconcile&sign=' . $this->sign) . '</a></li>
                        </ul>', 'woocommerce') . '<small>' : '',
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

                // Test if shipping is needed first
                if (WC()->cart && WC()->cart->needs_shipping()) {
                    $needs_shipping = true;
                } elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
                    $order_id = absint(get_query_var('order-pay'));
                    $order    = wc_get_order($order_id);

                    // Test if order needs shipping.
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

            public function validate_fields()
            {
                if (empty($_POST['billing_mpesa_phone'])) {
                    wc_add_notice(' M-PESA phone number is required!', 'error');
                    return false;
                }

                return true;
            }

            /**
             * Process the payment and return the result.
             *
             * @param int $order_id
             * @return array
             */
            public function process_payment($order_id)
            {
                $order      = new \WC_Order($order_id);
                $total      = $order->get_total();
                $phone      = sanitize_text_field($_POST['billing_mpesa_phone']); //$order->get_billing_phone();
                $first_name = $order->get_billing_first_name();
                $last_name  = $order->get_billing_last_name();
                $c2b        = get_option('woocommerce_mpesa_settings');

                if (($c2b['debug'] ?? 'no') == 'yes') {
                    $result  = (new STK)->authorize($this->token)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa', true);
                    $message = json_encode($result['requested']);
                    WC()->session->set('mpesa_request', $message);
                } else {
                    $result = (new STK)->authorize($this->token)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');
                }

                if ($result) {
                    $request_id = $result['MerchantRequestID'];

                    if (isset($result['errorCode'])) {
                        $error_message = 'MPesa Error ' . $result['errorCode'] . ': ' . $result['errorMessage'];
                        $order->update_status('failed', __($error_message, 'woocommerce'));
                        wc_add_notice(__('Failed! ', 'woocommerce') . $error_message, 'error');
                        if (($c2b['debug'] ?? 'no') == 'yes' && WC()->session->get('mpesa_request')) {
                            wc_add_notice(__('Request: ', 'woocommerce') . WC()->session->get('mpesa_request'), 'error');
                        }

                        return array(
                            'result'   => 'fail',
                            'redirect' => '',
                        );
                    } else {
                        $order->add_order_note(__("Awaiting MPesa confirmation of payment from {$phone} for request {$request_id}.", 'woocommerce'));

                        /**
                         * Reduce stock levels
                         */
                        wc_reduce_stock_levels($order_id);

                        /**
                         * Remove contents from cart
                         */
                        WC()->cart->empty_cart();

                        // Insert the payment into the database
                        $post_id = wp_insert_post(
                            array(
                                'post_title'  => 'Checkout',
                                'post_status' => 'publish',
                                'post_type'   => 'mpesaipn',
                                'post_author' => is_user_logged_in() ? get_current_user_id() : $this->get_option('accountant'),
                            )
                        );

                        update_post_meta($post_id, '_customer', "{$first_name} {$last_name}");
                        update_post_meta($post_id, '_phone', $phone);
                        update_post_meta($post_id, '_order_id', $order_id);
                        update_post_meta($post_id, '_request_id', $request_id);
                        update_post_meta($post_id, '_amount', $total);
                        update_post_meta($post_id, '_paid', 0);
                        update_post_meta($post_id, '_reference', $order_id);
                        update_post_meta($post_id, '_receipt', 'N/A');
                        update_post_meta($post_id, '_order_status', 'on-hold');

                        $this->instructions .= "<p>Awaiting MPesa confirmation of payment from {$phone} for request {$request_id}. Check your phone for the STK Prompt.</p>";

                        // Return thankyou redirect
                        return array(
                            'result'   => 'success',
                            'redirect' => $this->get_return_url($order),
                        );
                    }
                } else {
                    $error_message = __('Could not connect to Daraja', 'woocommerce');

                    $order->update_status('failed', $error_message);
                    wc_add_notice(__('Failed! ', 'woocommerce') . $error_message, 'error');

                    return array(
                        'result'   => 'fail',
                        'redirect' => '',
                    );
                }
            }

            public function validate_payment($order_id)
            {
                if (wc_get_order($order_id)) {
                    $order     = new \WC_Order($order_id);
                    $total     = $order->get_total();
                    $reference = $order_id;
                }

                $type = ($this->type == 4) ? 'Pay Bill' : 'Buy Goods and Services';

                echo
                '<section class="woocommerce-order-details" id="resend_stk">
                    <input type="hidden" id="current_order" value="' . $order_id . '">
                    <input type="hidden" id="payment_method" value="' . $order->get_payment_method() . '">
                    <p class="saving" id="mpesa_receipt">Confirming receipt, please wait</p>
                    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                        <tbody>
                            <tr class="woocommerce-table__line-item order_item">
                                <td class="woocommerce-table__product-name product-name">
                                    <form action="' . home_url("wc-api/lipwa?action=request") . '" method="POST" id="renitiate-form">
                                        <input type="hidden" name="order" value="' . $order_id . '">
                                        <button id="renitiate-button" class="button alt" type="submit">' . ($this->resend ?? 'Resend STK Push') . '</button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>';

                if ($this->enable_c2b) {
                    echo
                    '<section class="woocommerce-order-details" id="missed_stk">
                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <thead>
                                <tr>
                                    <th class="woocommerce-table__product-name product-name">
                                        ' . __("STK Push didn\'t work? Pay Manually Via M-PESA", "woocommerce") . '
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-name product-name">
                                        <ol>
                                            <li>Select <b>Lipa na M-PESA</b>.</li>
                                            <li>Select <b>' . $type . '</b>.</li>
                                            ' . (($this->type == 4) ? "<li>Enter <b>{$this->shortcode}</b> as business no.</li><li>Enter <b>{$reference}</b> as Account no.</li>" : "<li>Enter <b>{$this->shortcode}</b> as till no.</li>") . '
                                            <li>Enter Amount <b>' . round($total) . '</b>.</li>
                                            <li>Enter your M-PESA PIN</li>
                                            <li>Confirm your details and press OK.</li>
                                            <li>Wait for a confirmation message from M-PESA.</li>
                                        </ol>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>';
                }
            }

            /**
             * @since 1.20.79
             */
            public function request_body($order_id)
            {

                if ($this->debug) {
                    echo '
                    <section class="woocommerce-order-details" id="mpesa_request">
                    <p>Mpesa request body</p>
                        <code>' . WC()->session->get('mpesa_request') . '</code>
                    </section>';
                }
            }

            public function webhook()
            {
                $action  = $_GET['action'] ?? 'validate';
                $headers = array('From: ' . get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>' . "\r\n");

                switch ($action) {
                    case "request":
                        $order_id = sanitize_text_field($_POST['order']);

                        //return $this->process_payment($order_id);
                        $order      = new \WC_Order($order_id);
                        $total      = $order->get_total();
                        $phone      = $order->get_billing_phone();
                        $first_name = $order->get_billing_first_name();
                        $last_name  = $order->get_billing_last_name();
                        $result     = (new STK)->authorize($this->token)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');
                        $post       = wc_mpesa_post_id_by_meta_key_and_value('_order_id', $order_id);

                        if ($post !== false) {
                            $request_id = $result['MerchantRequestID'];
                            update_post_meta($post, '_request_id', $request_id);
                        }

                        wp_send_json($result);
                        break;
                    case "validate":
                        exit(wp_send_json(
                            (new STK)->validate()
                        ));
                        break;

                    case "confirm":
                        $response = json_decode(file_get_contents('php://input'), true);

                        if (!$response || empty($response)) {
                            exit(wp_send_json(
                                ['Error' => 'No response data received']
                            ));
                        }

                        $mpesaReceiptNumber = $response['TransID'];
                        $transactionDate    = $response['TransTime'];
                        $amount             = $response['TransAmount'];
                        $BillRefNumber      = $response['BillRefNumber'];
                        $phone              = $response['MSISDN'];
                        $FirstName          = $response['FirstName'];
                        $MiddleName         = $response['MiddleName'];
                        $LastName           = $response['LastName'];

                        $post = wc_mpesa_post_id_by_meta_key_and_value('_reference', $BillRefNumber);
                        if (!$post) {
                            $post_id = wp_insert_post(
                                array(
                                    'post_title'  => 'C2B',
                                    'post_status' => 'publish',
                                    'post_type'   => 'mpesaipn',
                                    'post_author' => 1,
                                )
                            );

                            update_post_meta($post_id, '_customer', "{$FirstName} {$MiddleName} {$LastName}");
                            update_post_meta($post_id, '_phone', $phone);
                            update_post_meta($post_id, '_amount', $amount);
                            update_post_meta($post_id, '_receipt', $mpesaReceiptNumber);
                            update_post_meta($post_id, '_order_status', 'processing');
                        }

                        $order_id        = get_post_meta($post, '_order_id', true);
                        $amount_due      = get_post_meta($post, '_amount', true);
                        $before_ipn_paid = get_post_meta($post, '_paid', true);

                        if (wc_get_order($order_id)) {
                            $order    = new \WC_Order($order_id);
                            $customer = "{$FirstName} {$MiddleName} {$LastName}";
                        } else {
                            $customer = "MPesa Customer";
                        }

                        $after_ipn_paid = round($before_ipn_paid) + round($amount);
                        $ipn_balance    = $after_ipn_paid - $amount_due;

                        if (wc_get_order($order_id)) {
                            $order = new \WC_Order($order_id);

                            if ($ipn_balance == 0) {
                                update_post_meta($post, '_order_status', 'complete');
                                update_post_meta($post, '_receipt', $mpesaReceiptNumber);

                                $order->update_status($this->get_option('completion', 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
                                $order->set_transaction_id($mpesaReceiptNumber);
                                $order->save();

                                wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);
                            } elseif ($ipn_balance < 0) {
                                $currency = get_woocommerce_currency();
                                $order->update_status($this->get_option('completion', 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
                                $order->set_transaction_id($mpesaReceiptNumber);
                                $order->save();

                                wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);

                                update_post_meta($post, '_order_status', 'complete');
                                update_post_meta($post, '_receipt', $mpesaReceiptNumber);
                            } else {
                                $order->update_status('on-hold');
                                $order->add_order_note(__("MPesa Payment from {$phone} Incomplete"));
                                update_post_meta($post, '_order_status', 'on-hold');
                            }
                        }

                        update_post_meta($post, '_paid', $after_ipn_paid);
                        update_post_meta($post, '_amount', $amount_due);
                        update_post_meta($post, '_balance', $ipn_balance);
                        update_post_meta($post, '_phone', $phone);
                        update_post_meta($post, '_customer', $customer);
                        update_post_meta($post, '_order_id', $order_id);
                        update_post_meta($post, '_receipt', $mpesaReceiptNumber);

                        exit(wp_send_json((new STK)->confirm()));
                        break;

                    case "register":
                        (new C2B)->register(function ($response) {
                            $status = isset($response['ResponseDescription']) ? 'success' : 'fail';
                            if ($status == 'fail') {
                                $message = isset($response['errorMessage']) ? $response['errorMessage'] : 'Could not register M-PESA URLs, try again later.';
                                $state   = 'red';
                            } else {
                                $message = isset($response['ResponseDescription']) ? $response['ResponseDescription'] : 'M-PESA URL registered successfully. You will now receive C2B Payment Notifications.';
                                $state   = 'green';
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

                    case "reconcile":
                        $response = json_decode(file_get_contents('php://input'), true);

                        if (!isset($_GET['sign'])) {
                            exit(wp_send_json(['Error' => 'No Signature Supplied']));
                        }
                        $sign = sanitize_text_field($_GET['sign']);

                        if ($sign !== $this->get_option('signature')) {
                            exit(wp_send_json(['Error' => 'Invalid Signature Supplied']));
                        }

                        if (!isset($response['Body'])) {
                            exit(wp_send_json(['Error' => 'No response data received']));
                        }

                        $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                        $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                        $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];

                        $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
                        //wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);

                        $order_id        = get_post_meta($post, '_order_id', true);
                        $amount_due      = get_post_meta($post, '_amount', true);
                        $before_ipn_paid = get_post_meta($post, '_paid', true);

                        if (wc_get_order($order_id)) {
                            $order      = new \WC_Order($order_id);
                            $first_name = $order->get_billing_first_name();
                            $last_name  = $order->get_billing_last_name();
                            $customer   = "{$first_name} {$last_name}";

                            if (isset($response['Body']['stkCallback']['CallbackMetadata'])) {
                                $amount             = $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
                                $mpesaReceiptNumber = $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
                                $balance            = $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
                                $transactionDate    = $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
                                $phone              = $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
                                $after_ipn_paid     = round($before_ipn_paid) + round($amount);
                                $ipn_balance        = $after_ipn_paid - $amount_due;

                                if ($ipn_balance == 0) {
                                    update_post_meta($post, '_order_status', 'complete');
                                    update_post_meta($post, '_receipt', $mpesaReceiptNumber);

                                    $order->update_status($this->get_option('completion', 'completed'), __("Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}"));
                                    $order->set_transaction_id($mpesaReceiptNumber);
                                    $order->save();

                                    wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);
                                } elseif ($ipn_balance < 0) {
                                    $currency = get_woocommerce_currency();
                                    $order->update_status($this->get_option('completion', 'completed'), __("{$phone} has overpayed by {$currency} {$ipn_balance}. Receipt Number {$mpesaReceiptNumber}"));
                                    $order->set_transaction_id($mpesaReceiptNumber);
                                    $order->save();

                                    wp_mail($order->get_billing_email(), 'Your Mpesa payment', 'We acknowledge receipt of your payment via MPesa of KSh. ' . $amount . ' on ' . $transactionDate . '. Receipt number ' . $mpesaReceiptNumber, $headers);

                                    update_post_meta($post, '_order_status', 'complete');
                                    update_post_meta($post, '_receipt', $mpesaReceiptNumber);
                                } else {
                                    $order->update_status('on-hold');
                                    $order->add_order_note(__("MPesa Payment from {$phone} Incomplete"));
                                    update_post_meta($post, '_order_status', 'on-hold');
                                }

                                update_post_meta($post, '_paid', $after_ipn_paid);
                                update_post_meta($post, '_amount', $amount_due);
                                update_post_meta($post, '_balance', $ipn_balance);
                                update_post_meta($post, '_phone', $phone);
                                update_post_meta($post, '_customer', $customer);
                                update_post_meta($post, '_order_id', $order_id);
                                update_post_meta($post, '_receipt', $mpesaReceiptNumber);
                            } else {
                                $order->update_status('on-hold');
                                update_post_meta($post, '_receipt', 'fail');
                                $order->add_order_note(__("MPesa Error {$resultCode}: {$resultDesc}"));
                            }

                            exit(wp_send_json((new STK)->reconcile()));
                        } else {
                            exit(wp_send_json((new STK)->reconcile(function () {
                                return false;
                            })));
                        }
                        break;

                    case "status":
                        $transaction = sanitize_text_field($_POST['transaction']);
                        exit(wp_send_json((new STK)->status($transaction)));
                        break;

                    case "result":
                        $response = json_decode(file_get_contents('php://input'), true);

                        $result = $response['Result'];

                        $ResultType               = $result['ResultType'];
                        $ResultCode               = $result['ResultType'];
                        $ResultDesc               = $result['ResultType'];
                        $OriginatorConversationID = $result['ResultType'];
                        $ConversationID           = $result['ResultType'];
                        $TransactionID            = $result['ResultType'];
                        $ResultParameters         = $result['ResultType'];

                        $ResultParameter = $result['ResultType'];

                        $ReceiptNo                = $ResultParameter[0]['Value'];
                        $ConversationID           = $ResultParameter[0]['Value'];
                        $FinalisedTime            = $ResultParameter[0]['Value'];
                        $Amount                   = $ResultParameter[0]['Value'];
                        $ReceiptNo                = $ResultParameter[0]['Value'];
                        $TransactionStatus        = $ResultParameter[0]['Value'];
                        $ReasonType               = $ResultParameter[0]['Value'];
                        $TransactionReason        = $ResultParameter[0]['Value'];
                        $DebitPartyCharges        = $ResultParameter[0]['Value'];
                        $DebitAccountType         = $ResultParameter[0]['Value'];
                        $InitiatedTime            = $ResultParameter[0]['Value'];
                        $OriginatorConversationID = $ResultParameter[0]['Value'];
                        $CreditPartyName          = $ResultParameter[0]['Value'];
                        $DebitPartyName           = $ResultParameter[0]['Value'];

                        $ReferenceData = $result['ReferenceData'];
                        $ReferenceItem = $ReferenceData['ReferenceItem'];
                        $Occasion      = $ReferenceItem[0]['Value'];
                        exit(wp_send_json((new STK)->validate()));
                        break;

                    case "timeout":
                        $response = json_decode(file_get_contents('php://input'), true);

                        if (!isset($response['Body'])) {
                            exit(wp_send_json(['Error' => 'No response data received']));
                        }

                        $resultCode        = $response['Body']['stkCallback']['ResultCode'];
                        $resultDesc        = $response['Body']['stkCallback']['ResultDesc'];
                        $merchantRequestID = $response['Body']['stkCallback']['MerchantRequestID'];
                        $checkoutRequestID = $response['Body']['stkCallback']['CheckoutRequestID'];

                        $post = wc_mpesa_post_id_by_meta_key_and_value('_request_id', $merchantRequestID);
                        //wp_update_post(['post_content' => file_get_contents('php://input'), 'ID' => $post]);
                        update_post_meta($post, '_order_status', 'pending');

                        $order_id = get_post_meta($post, '_order_id', true);
                        if (wc_get_order($order_id)) {
                            $order = new \WC_Order($order_id);

                            $order->update_status('pending');
                            $order->add_order_note(__("MPesa Payment Timed Out", 'woocommerce'));
                        }

                        exit(wp_send_json((new STK)->timeout()));
                        break;
                    default:
                        exit(wp_send_json((new C2B)->register()));
                }
            }

            public function get_receipt()
            {
                $response = array('receipt' => '');

                if (!empty($_GET['order'])) {
                    $order_id = sanitize_text_field($_GET['order']);
                    $order    = wc_get_order(esc_attr($order_id));

                    $notes = wc_get_order_notes(array(
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
             * Add content to the WC emails.
             *
             * @access public
             * @param \WC_Order $order
             * @param bool $sent_to_admin
             * @param bool $plain_text
             */
            public function email_instructions($order, $sent_to_admin, $plain_text = false)
            {
                if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
                    echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
                }
            }
        }
    }
}
