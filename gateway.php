<?php

/**
 * @package MPesa For WooCommerce
 * @subpackage WooCommerce Mpesa Gateway
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 0.18.01
 */

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
            /**
             * Constructor for the gateway.
             */
            public function __construct()
            {
                $this->id           = 'mpesa';
                $this->icon         = apply_filters('woocommerce_mpesa_icon', plugins_url('assets/mpesa.png', __FILE__));
                $this->method_title = __('Lipa Na MPesa', 'woocommerce');

                // Load settings
                $this->init_form_fields();
                $this->init_settings();

                $env          = $this->get_option('env', 'sandbox');
                $test_cred = ($env == 'sandbox')
                    ? '<li>You can <a href="https://developer.safaricom.co.ke/test_credentials" target="_blank" >get sandbox test credentials here</a>.</li>'
                    : '';
                $color    = isset($_GET['reg-state']) ? $_GET['reg-state'] : 'black';
                $register = isset($_GET['mpesa-urls-registered']) ? "<div style='color: {$color}'>{$_GET['mpesa-urls-registered']}</div>" : '';

                $this->method_description = $register . (($env == 'live') ? __('Receive payments via Safaricom M-PESA', 'woocommerce') : __('<h4 style="color: red;">IMPORTANT!</h4>' . '<li>Please <a href="https://developer.safaricom.co.ke/" target="_blank" >create an app on Daraja</a> if you haven\'t. If yoou already have a production app, fill in the app\'s consumer key and secret below.</li><li>Ensure you have access to the <a href="https://org.ke.m-pesa.com/">MPesa Web Portal</a>. You\'ll need this for when you go LIVE.</li><li>For security purposes, and for the MPesa Instant Payment Notification to work, ensure your site is running over https(SSL).</li>' . $test_cred) . '<li>We have a <a target="_blank" href="https://wc-mpesa.osen.co.ke/going-live">nice tutorial</a> here on migrating from Sandbox(test) environment, to Production(live) environment.<br> We offer the service  at a fiat fee of KSh 4000. Call <a href="tel:+254204404993">+254204404993</a> or email <a href="mailto:hi@osen.co.ke">hi@osen.co.ke</a> if you need help.</li>');
                $this->has_fields         = false;

                // Get settings
                $this->title              = $this->get_option('title');
                $this->description        = $this->get_option('description');
                $this->instructions       = $this->get_option('instructions');
                $this->enable_for_methods = $this->get_option('enable_for_methods', array());
                $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes' ? true : false;
                $this->debug              = $this->get_option('debug', 'no') === 'yes' ? true : false;

                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
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
                        'title'       => __('Store Number/Paybill', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Store Number (for Till) or Paybill Number. Use "Online Shortcode" in Sandbox', 'woocommerce'),
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
                        'type'        => 'textarea',
                        'description' => __('Used to create a password for use when making a Lipa Na M-Pesa Online Payment API call.', 'woocommerce'),
                        'default'     => __('bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', 'woocommerce'),
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
                        'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                        'default'     => __("Cross-check your details above before pressing the button below.\nYour phone number MUST be registered with MPesa(and ON) for this to work.\nYou will get a pop-up on your phone asking you to confirm the payment.\nEnter your service (MPesa) PIN to proceed.\nIn case you don't see the pop up on your phone, please upgrade your SIM card by dialing *234*1*6#.\nYou will receive a confirmation message shortly thereafter.", 'woocommerce'),
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
                        'description' => '<small>This requires C2B Validation, which is an optional feature that needs to be activated on M-Pesa. <br>Request for activation by sending an email to <a href="mailto:apisupport@safaricom.co.ke">apisupport@safaricom.co.ke</a>, or through a chat on the <a href="https://developer.safaricom.co.ke/">developer portal.</a><br><br> <a class="button button-secondary" href="' . home_url('lipwa/register/') . '">Once enabled, click here to register confirmation & validation URLs</a><br><i>Kindly note that if this is disabled, the user can still resend an STK push if the first one fails.</i></small>',
                        'default'     => 'no',
                    ),
                    'debug'              => array(
                        'title'       => __('Debug Mode', 'woocommerce'),
                        'label'       => __('Check to enable debug mode and show request body', 'woocommerce'),
                        'type'        => 'checkbox',
                        'default'     => 'no',
                        'description' => '<small>' . __('Show Request Body(to send to Daraja team on request). Use the following URLs: <ul>
                        <li>Validation URL for C2B: <a href="' . home_url('lipwa/validate') . '">' . home_url('lipwa/validate') . '</a></li>
                        <li>Confirmation URL for C2B: <a href="' . home_url('lipwa/confirm') . '">' . home_url('lipwa/confirm') . '</a></li>
                        <li>Reconciliation URL for STK Push: <a href="' . home_url('lipwa/reconcile') . '">' . home_url('lipwa/reconcile') . '</a></li>
                        </ul>', 'woocommerce') . '<small>',
                    ),
                    'signature'             => array(
                        'title'       => __('Encryption Signature', 'woocommerce'),
                        'type'        => 'text',
                        'description' => __('Callback Endpoint Encryption Signature', 'woocommerce'),
                        'default'     => md5(random_bytes(12)),
                        'desc_tip'    => true,
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
                $phone      = $order->get_billing_phone();
                $first_name = $order->get_billing_first_name();
                $last_name  = $order->get_billing_last_name();
                $c2b        = get_option('woocommerce_mpesa_settings');

                if (($c2b['debug'] ?? 'no') == 'yes') {
                    $result  = (new STK)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa', true);
                    $message = json_encode($result['requested']);
                    WC()->session->set('mpesa_request', $message);
                } else {
                    $result = (new STK)->request($phone, $total, $order_id, get_bloginfo('name') . ' Purchase', 'WCMPesa');
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
                        /**
                         * Temporarily set status as "on-hold", incase the MPesa API times out before processing our request
                         */
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
                                'post_title'   => 'Checkout',
                                'post_status'  => 'publish',
                                'post_type'    => 'mpesaipn',
                                'post_author'  => is_user_logged_in() ? get_current_user_id() : $this->get_option('accountant'),
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
             * Output for the order received page.
             */
            public function receipt_page()
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
