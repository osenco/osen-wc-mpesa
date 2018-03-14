<?php
/**
 * Plugin Name: MPesa For WooCommerce
 * Plugin URI: https://wc-mpesa.mauko.co.ke/
 * Description: This plugin extends WordPress and WooCommerce functionality to integrate MPesa for making and receiving online payments.
 * Author: Mauko Maunde < hi@mauko.co.ke >
 * Version: 0.18.01
 * Author URI: https://mauko.co.ke/
 *
 * Requires at least: 4.4
 * Tested up to: 4.9.2
 * @todo Add uninstall script - delete all payments?
 * @todo Consider adding KopoKopo /Pesapal support ?????	
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ){
	exit;
}

if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	return;
}

define( 'MPESA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPESA_INC_DIR', MPESA_DIR.'includes/' );
define( 'WC_MPESA_VERSION', '0.18.01' );

register_activation_hook( __FILE__, 'wc_mpesa_install' );
register_uninstall_hook( __FILE__, 'wc_mpesa_uninstall' );

add_action( 'plugins_loaded', 'wc_mpesa_gateway_init', 11 );

add_action( 'init', 'wc_mpesa_confirm' );
add_action( 'init', 'wc_mpesa_validate' );
add_action( 'init', 'wc_mpesa_reconcile' );
add_action( 'init', 'wc_mpesa_timeout' );
add_action( 'init', 'wc_mpesa_register' );

add_filter( 'woocommerce_states', 'mpesa_ke_woocommerce_counties' );
add_filter( 'woocommerce_payment_gateways', 'wc_mpesa_add_to_gateways' );
add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), 'mpesa_action_links' );
add_filter( 'plugin_row_meta', 'mpesa_row_meta', 10, 2 );

// Admin Menus
require_once( MPESA_INC_DIR.'menu.php' );

//Payments Post Type
require_once( MPESA_INC_DIR.'payments.php' );

function get_post_id_by_meta_key_and_value($key, $value) {
    global $wpdb;
    $meta = $wpdb->get_results("SELECT * FROM `".$wpdb->postmeta."` WHERE meta_key='".$key."' AND meta_value='".$value."'");
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
 * Installation hook callback creates plugin settings
 */
function wc_mpesa_install()
{
	update_option( 'wc_mpesa_version', WC_MPESA_VERSION );
	update_option( 'wc_mpesa_urls_reg', 0 );
}

/**
 * Uninstallation hook callback deletes plugin settings
 */
function wc_mpesa_uninstall()
{
	delete_option( 'wc_mpesa_version' );
	delete_option( 'wc_mpesa_urls_reg' );
}

function register_urls_notice()
{
	echo '<div class="notification">You need to register your confirmation and validation endpoints to work.</div>';
}

function mpesa_action_links( $links )
{
	return array_merge( $links, [ '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mpesa' ).'">&nbsp;Preferences</a>' ] );
} 

function mpesa_row_meta( $links, $file )
{
	$plugin = plugin_basename( __FILE__ );

	if ( $plugin == $file ) {
		$row_meta = array( 
			'github'    => '<a href="' . esc_url( 'https://github.com/ModoPesa/wc-mpesa/' ) . '" target="_blank" aria-label="' . esc_attr__( 'Contribute on Github', 'woocommerce' ) . '">' . esc_html__( 'Github', 'woocommerce' ) . '</a>',
			'apidocs' => '<a href="' . esc_url( 'https://developer.safaricom.co.ke/docs/' ) . '" target="_blank" aria-label="' . esc_attr__( 'MPesa API Docs ( Daraja )', 'woocommerce' ) . '">' . esc_html__( 'API docs', 'woocommerce' ) . '</a>',
			'pro' => '<a href="' . esc_url( 'https://wc-mpesa.mauko.co.ke/pro/' ) . '" target="_blank" aria-label="' . esc_attr__( 'Get Pro Version', 'woocommerce' ) . '">' . esc_html__( 'Get pro', 'woocommerce' ) . '</a>'
		 );

		return array_merge( $links, $row_meta );
	}

	return ( array ) $links;
}

/**
 * Add Kenyan counties to list of woocommerce states
 */
function mpesa_ke_woocommerce_counties( $counties ) 
{
	$counties['KE'] = array( 
		'BAR' => __( 'Baringo', 'woocommerce' ),
		'BMT' => __( 'Bomet', 'woocommerce' ),
		'BGM' => __( 'Bungoma', 'woocommerce' ),
		'BSA' => __( 'Busia', 'woocommerce' ),
		'EGM' => __( 'Elgeyo-Marakwet', 'woocommerce' ),
		'EBU' => __( 'Embu', 'woocommerce' ),
		'GSA' => __( 'Garissa', 'woocommerce' ),
		'HMA' => __( 'Homa Bay', 'woocommerce' ),
		'ISL' => __( 'Isiolo', 'woocommerce' ),
		'KAJ' => __( 'Kajiado', 'woocommerce' ),
		'KAK' => __( 'Kakamega', 'woocommerce' ),
		'KCO' => __( 'Kericho', 'woocommerce' ),
		'KBU' => __( 'Kiambu', 'woocommerce' ),
		'KLF' => __( 'Kilifi', 'woocommerce' ),
		'KIR' => __( 'Kirinyaga', 'woocommerce' ),
		'KSI' => __( 'Kisii', 'woocommerce' ),
		'KIS' => __( 'Kisumu', 'woocommerce' ),
		'KTU' => __( 'Kitui', 'woocommerce' ),
		'KLE' => __( 'Kwale', 'woocommerce' ),
		'LKP' => __( 'Laikipia', 'woocommerce' ),
		'LAU' => __( 'Lamu', 'woocommerce' ),
		'MCS' => __( 'Machakos', 'woocommerce' ),
		'MUE' => __( 'Makueni', 'woocommerce' ),
		'MDA' => __( 'Mandera', 'woocommerce' ),
		'MAR' => __( 'Marsabit', 'woocommerce' ),
		'MRU' => __( 'Meru', 'woocommerce' ),
		'MIG' => __( 'Migori', 'woocommerce' ),
		'MBA' => __( 'Mombasa', 'woocommerce' ),
		'MRA' => __( 'Muranga', 'woocommerce' ),
		'NBO' => __( 'Nairobi', 'woocommerce' ),
		'NKU' => __( 'Nakuru', 'woocommerce' ),
		'NDI' => __( 'Nandi', 'woocommerce' ),
		'NRK' => __( 'Narok', 'woocommerce' ),
		'NYI' => __( 'Nyamira', 'woocommerce' ),
		'NDR' => __( 'Nyandarua', 'woocommerce' ),
		'NER' => __( 'Nyeri', 'woocommerce' ),
		'SMB' => __( 'Samburu', 'woocommerce' ),
		'SYA' => __( 'Siaya', 'woocommerce' ),
		'TVT' => __( 'Taita Taveta', 'woocommerce' ),
		'TAN' => __( 'Tana River', 'woocommerce' ),
		'TNT' => __( 'Tharaka-Nithi', 'woocommerce' ),
		'TRN' => __( 'Trans-Nzoia', 'woocommerce' ),
		'TUR' => __( 'Turkana', 'woocommerce' ),
		'USG' => __( 'Uasin Gishu', 'woocommerce' ),
		'VHG' => __( 'Vihiga', 'woocommerce' ),
		'WJR' => __( 'Wajir', 'woocommerce' ),
		'PKT' => __( 'West Pokot', 'woocommerce' )
	 );

	return $counties;
}

/*
 * Register our gateway with woocommerce
 */
function wc_mpesa_add_to_gateways( $gateways )
{
	$gateways[] = 'WC_Gateway_MPESA';
	return $gateways;
}

function wc_mpesa_gateway_init() 
{
	/**
	 * @class WC_Gateway_MPesa
	 * @extends WC_Payment_Gateway
	 */
	class WC_Gateway_MPESA extends WC_Payment_Gateway 
	{
		public $mpesa_name;
		public $mpesa_shortcode;
		public $mpesa_type;
		public $mpesa_key;
		public $mpesa_secret;
		public $mpesa_username;
		public $mpesa_password;
		public $mpesa_passkey;
		public $mpesa_callback_url;
		public $mpesa_timeout_url;
		public $mpesa_result_url;
		public $mpesa_confirmation_url;
		public $mpesa_validation_url;

		public $mpesa_live = 'no';
		public $mpesa_credential;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() 
		{
			$env = get_option( 'woocommerce_mpesa_settings' )["live"] == 'yes' ? 'live' : 'sandbox';
			$reg_notice = '<a href="'.home_url( '/?mpesa_ipn_register='.$env ).'" target="_blank">Click here to register confirmation & validation URLs</a>. You only need to do this once for sandbox and once when you go live.';
			$test_cred = ( $env == 'sandbox' ) ? '<li>You can <a href="https://developer.safaricom.co.ke/test/" target="_blank" >generate sandbox test credentials here</a>.</li>' : '';
			//$reg_notice = has_valid_licence() ? '' : $reg_notice;

			$this->id                 		= 'mpesa';
			$this->icon               		= apply_filters( 'woocommerce_mpesa_icon', plugins_url( 'mpesa.png', __FILE__ ) );
			$this->method_title       		= __( 'Lipa Na MPesa', 'woocommerce' );
			$this->method_description 		= __( '<h4 style="color: red;">IMPORTANT!</h4><li>Please <a href="https://developer.safaricom.co.ke/" target="_blank" >create an app on Daraja</a> if you haven\'t. Fill in the app\'s consumer key and secret below.</li><li>For security purposes, and for the MPesa Instant Payment Notification to work, ensure your site is running over https(SSL).</li>
				<li>'.$reg_notice.'</li>'.$test_cred );
			$this->has_fields         		= false;

			// Load settings
			$this->init_form_fields();
			$this->init_settings();

			// Get settings
			$this->title              		= $this->get_option( 'title' );
			$this->description        		= $this->get_option( 'description' );
			$this->instructions       		= $this->get_option( 'instructions' );
			$this->enable_for_methods 		= $this->get_option( 'enable_for_methods', array() );
			$this->enable_for_virtual 		= $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;

			$this->mpesa_name 				= $this->get_option( 'business' );
			$this->mpesa_shortcode 			= $this->get_option( 'shortcode' );
			$this->mpesa_type 				= $this->get_option( 'idtype' );
			$this->mpesa_key 				= $this->get_option( 'key' );
			$this->mpesa_secret 			= $this->get_option( 'secret' );
			$this->mpesa_username 			= $this->get_option( 'username' );
			$this->mpesa_password 			= $this->get_option( 'password' );
			$this->mpesa_passkey 			= $this->get_option( 'passkey' );
			$this->mpesa_callback_url 		= rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/?mpesa_ipn_listener=reconcile';
			$this->mpesa_timeout_url 		= rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/?mpesa_ipn_listener=timeout';
			$this->mpesa_result_url 		= rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/?mpesa_ipn_listener=reconcile';
			$this->mpesa_confirmation_url 	= rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/?mpesa_ipn_listener=confirm';
			$this->mpesa_validation_url 	= rtrim( home_url(), '/').':'.$_SERVER['SERVER_PORT'].'/?mpesa_ipn_listener=validate';

			$this->mpesa_live = $this->get_option( 'live' );
			$this->mpesa_credential = ( $this->get_option( 'credentials' ) == "" ) ? null : $this->get_option( 'credentials' );

			$this->mpesa_codes = array(
				0	=> 'Success',
				1	=> 'Insufficient Funds',
				2	=> 'Less Than Minimum Transaction Value',
				3	=> 'More Than Maximum Transaction Value',
				4	=> 'Would Exceed Daily Transfer Limit',
				5	=> 'Would Exceed Minimum Balance',
				6	=> 'Unresolved Primary Party',
				7	=> 'Unresolved Receiver Party',
				8	=> 'Would Exceed Maxiumum Balance',
				11	=> 'Debit Account Invalid',
				12	=> 'Credit Account Invalid',
				13	=> 'Unresolved Debit Account',
				14	=> 'Unresolved Credit Account',
				15	=> 'Duplicate Detected',
				17	=> 'Internal Failure',
				20	=> 'Unresolved Initiator',
				26	=> 'Traffic blocking condition in place'
			);

			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields()
		{
			$shipping_methods = array();

			foreach ( WC()->shipping()->load_shipping_methods() as $method ){
				$shipping_methods[ $method->id ] = $method->get_method_title();
			}

			$this->form_fields = array( 
				'enabled' => array( 
					'title'       => __( 'Enable/Disable', 'woocommerce' ),
					'label'       => __( 'Enable '.$this->method_title, 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				 ),
				'live' => array( 
					'title'       => __( 'Environment', 'woocommerce' ),
					'label'       => __( 'Live ( Leave Unchecked for Sandbox )', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				 ),
				'title' => array( 
					'title'       => __( 'Method Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Payment method name that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Lipa Na MPesa', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'idtype' => array( 
					'title'       => __( 'Identifier Type', 'woocommerce' ),
					'type'        => 'select',
					'options' => array( 
				      	1 => __( 'Shortcode', 'woocommerce' ),
				     	2 => __( 'Till Number', 'woocommerce' ),
				      	4 => __( 'MSISDN', 'woocommerce' )
				    ),
					'description' => __( 'MPesa Identifier Type', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'shortcode' => array( 
					'title'       => __( 'MPesa Shortcode', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Your MPesa Business Till/Paybill Number.', 'woocommerce' ),
					'default'     => __( 'MPesa Till/Paybill Number', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'key' => array( 
					'title'       => __( 'App Consumer Key', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Your App Consumer Key From Safaricom Daraja.', 'woocommerce' ),
					'default'     => __( 'bnWPihAdtqRFZiJumUtEfI2lnEmQG09d', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'secret' => array( 
					'title'       => __( 'App Consumer Secret', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Your App Consumer Secret From Safaricom Daraja.', 'woocommerce' ),
					'default'     => __( 'VAdWE9ns8jGoImZW', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'username' => array( 
					'title'       => __( 'MPesa Portal Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Your user name used on the MPesa Web Portal for the Business Administrator or Business Manager or Business Operator roles within the organisation in MPesa. Use Initiator Name in Sandbox.', 'woocommerce' ),
					'default'     => __( 'MPesa Portal Username', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'password' => array( 
					'title'       => __( 'M-Pesa Portal Password', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Your user password used on the M-Pesa Web Portal for the Business Administrator or Business Manager or Business Operator roles within the organisation in M-Pesa. Required for B2B, B2C Transactions.', 'woocommerce' ),
					'default'     => __( 'M-Pesa Portal Password', 'woocommerce' ),
					'desc_tip'    => true
				 ),
				'passkey' => array( 
					'title'       => __( 'Online Pass Key', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Used to create a password for use when making a Lipa Na M-Pesa Online Payment API call.', 'woocommerce' ),
					'default'     => __( 'MIIGkzCCBXugAwIBAgIKXfBp5gAAAD+hNjANBgkqhkiG9w0BAQsFADBbMRMwEQYK', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'credentials' => array( 
					'title'       => __( 'Security Credentials', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Used in invoking an API that requires a security credential in its request parameters.', 'woocommerce' ),
					'default'     => __( '', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'description' => array( 
					'title'       => __( 'Method Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Cross-check your details above before pressing the button below.
Your phone number MUST be registered with MPesa( and ON ) for this to work.
You will get a pop-up on your phone asking you to confirm the payment.
Enter your service ( MPesa ) PIN to proceed.
You will receive a confirmation message shortly thereafter.', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'instructions' => array( 
					'title'       => __( 'Instructions', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
					'default'     => __( 'Thank you for buying from us. You will receive a confirmation message from MPesa shortly.', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'enable_for_methods' => array( 
					'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
					'type'              => 'multiselect',
					'class'             => 'wc-enhanced-select',
					'css'               => 'width: 400px;',
					'default'           => '',
					'description'       => __( 'If MPesa is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
					'options'           => $shipping_methods,
					'desc_tip'          => true,
					'custom_attributes' => array( 
						'data-placeholder' => __( 'Select shipping methods', 'woocommerce' ),
					 ),
				 ),
				'enable_for_virtual' => array( 
					'title'             => __( 'Accept for virtual orders', 'woocommerce' ),
					'label'             => __( 'Accept MPesa if the order is virtual', 'woocommerce' ),
					'type'              => 'checkbox',
					'default'           => 'yes',
				 ),
				'account' => array( 
					'title'       => __( 'Account Name', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Account Name to show to customer in STK Push.', 'woocommerce' ),
					'default'     => __( 'WC', 'woocommerce' ),
					'desc_tip'    => true,
				 ),
				'accountant' => array( 
					'title'       => __( 'Accountant', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'ID of WordPress user to assign authorship of payments generated by this plugin', 'woocommerce' ),
					'default'     => __( '1', 'woocommerce' ),
					'desc_tip'    => true,
				 )
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
			if ( WC()->cart && WC()->cart->needs_shipping() ) {
				$needs_shipping = true;
			} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
				$order_id = absint( get_query_var( 'order-pay' ) );
				$order    = wc_get_order( $order_id );

				// Test if order needs shipping.
				if ( 0 < sizeof( $order->get_items() ) ) {
					foreach ( $order->get_items() as $item ) {
						$_product = $item->get_product();
						if ( $_product && $_product->needs_shipping() ) {
							$needs_shipping = true;
							break;
						}
					}
				}
			}

			$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

			// Virtual order, with virtual disabled
			if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
				return false;
			}

			// Only apply if all packages are being shipped via chosen method, or order is virtual.
			if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
				$chosen_shipping_methods = array();

				if ( is_object( $order ) ) {
					$chosen_shipping_methods = array_unique( array_map( 'wc_get_string_before_colon', $order->get_shipping_methods() ) );
				} elseif ( $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' ) ) {
					$chosen_shipping_methods = array_unique( array_map( 'wc_get_string_before_colon', $chosen_shipping_methods_session ) );
				}

				if ( 0 < count( array_diff( $chosen_shipping_methods, $this->enable_for_methods ) ) ) {
					return false;
				}
			}

			return parent::is_available();
		}

		/**
		 * Allow transaction to proceed
		 * @todo Get WC transaction ID
		 */
		public function proceed( $transID = 0 )
		{
			return array( 
			  'ResponseCode'  => 0, 
			  'ResponseDesc'  => 'Success',
			  'ThirdPartyTransID'	=> $transID
			 );
		}

		public function reject( $transID = 0 )
		{
			return array( 
			  'ResponseCode'  		=> 1, 
			  'ResponseDesc'  		=> 'Failed',
			  'ThirdPartyTransID'	=> $transID
			 );
		}

		public function authenticate()
		{
		    $endpoint = ( $this->mpesa_live == 'yes' ) ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

			$credentials = base64_encode( $this->mpesa_key.':'.$this->mpesa_secret );

	        $curl = curl_init();
	        curl_setopt( $curl, CURLOPT_URL, $endpoint );
	        curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Basic '.$credentials ) );
	        curl_setopt( $curl, CURLOPT_HEADER, false );
	        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
	        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
	        $curl_response = curl_exec( $curl );
	        
			return json_decode( $curl_response )->access_token;
	    }

	    /**
	     * Register confirmation and validation endpoints
	     */
	    public function register_urls()
	    {
			$token = $this->authenticate();

			$endpoint = ( $this->mpesa_live == 'yes' ) ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl' : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

			$curl = curl_init();
	        curl_setopt( $curl, CURLOPT_URL, $endpoint );
	        curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json','Authorization:Bearer '.$token ) );
				
			$curl_post_data = array( 
	            'ShortCode' 		=> $this->mpesa_shortcode,
				'ResponseType' 		=> 'Cancelled',
				'ConfirmationURL' 	=> $this->mpesa_confirmation_url,
				'ValidationURL' 	=> $this->mpesa_validation_url
	        );

			$data_string = json_encode( $curl_post_data );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string );
			curl_setopt( $curl, CURLOPT_HEADER, false );
			$content = curl_exec( $curl );
			if ( $content ) {
				$status = json_decode( $content )->ResponseDescription;
			} else {
				$status = "Sorry could not connect to Daraja. Check your configuration and try again.";
			}
			return array( 'Registration status' => $status );
	    }

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id )
		{
			$order = new WC_Order( $order_id );
			$total = $order->get_total();
			$phone = $order->get_billing_phone();
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();

			// Remove the plus sign before the customer's phone number if present
			if ( substr( $phone, 0,1 ) == "+" ) {
				$phone = str_replace( "+", "", $phone );
			}
			// Correct phone number format
			if ( substr( $phone, 0,1 ) == "0" ) {
				$phone = preg_replace('/^0/', '254', $phone);
			}

			$token = $this->authenticate();

			$endpoint = ( $this->mpesa_live == 'yes' ) ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest' : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

			$timestamp = date( 'YmdHis' );
	        $password = base64_encode( $this->mpesa_shortcode.$this->mpesa_passkey.$timestamp );
	        $curl = curl_init();
	        curl_setopt( $curl, CURLOPT_URL, $endpoint );
	        curl_setopt( 
	        	$curl, 
	        	CURLOPT_HTTPHEADER, 
	        	array( 
	        		'Content-Type:application/json',
	        		'Authorization:Bearer '.$token 
	        	) 
	        );

	        $curl_post_data = array( 
	            'BusinessShortCode' => $this->mpesa_shortcode,
	            'Password' 			=> $password,
	            'Timestamp' 		=> $timestamp,
	            'TransactionType' 	=> 'CustomerPayBillOnline',
	            'Amount' 			=> str_replace( '.00', '', $total ),
	            'PartyA' 			=> $phone,
	            'PartyB' 			=> $this->mpesa_shortcode,
	            'PhoneNumber' 		=> $phone,
	            'CallBackURL' 		=> $this->mpesa_callback_url,
	            'AccountReference' 	=> ( $this->get_option( 'account' ) == 'WC' ) ? 'WC'.$order_id : $this->get_option( 'account' ),
	            'TransactionDesc' 	=> 'WooCommerce Payment',
	            'Remark'			=> 'WooCommerce Payment'
	        );

	        $data_string = json_encode( $curl_post_data );
	        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	        curl_setopt( $curl, CURLOPT_POST, true );
	        curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string );
	        curl_setopt( $curl, CURLOPT_HEADER, false );
	        $content = curl_exec( $curl );
			$result = json_decode( $content );
			
			$request_id = $result->MerchantRequestID;

			if( ! $content ){
				$error_message = 'Could not connect to MPesa to process payment. Please try again';
				$order->update_status( 'failed', __( 'Could not connect to MPesa to process payment.', 'woocommerce' ) );
				wc_add_notice( __( 'Failed! ', 'woothemes' ) . $error_message, 'error' );
				return; 
			} elseif ( isset( $result->errorCode ) ) {
				$error_message = 'MPesa Error '.$result->errorCode.': '.$result->errorMessage;
				$order->update_status( 'failed', __( $error_message, 'woocommerce' ) );
				wc_add_notice( __( 'Failed! ', 'woothemes' ) . $error_message, 'error' );
				return;
			} else {
				/**
				 * Temporarily set status as "on-hold", incase the MPesa API times out before processing our request
				 */
				$order->update_status( 'on-hold', __( 'Awaiting MPesa confirmation of payment from '.$phone.'.', 'woocommerce' ) );

				// Reduce stock levels
				wc_reduce_stock_levels( $order_id );

				// Remove cart
				WC()->cart->empty_cart(); 

				$author = is_user_logged_in() ? get_current_user_id() : $this->get_option( 'accountant' );
	 
				// Insert the payment into the database
				$post_id = wp_insert_post( 
					array( 
		    			'post_title' 	=> 'Checkout',
						'post_content'	=> "Response: ".$content."\nToken: ".$token,
						'post_status'	=> 'publish',
						'post_type'		=> 'mpesaipn',
						'post_author'	=> $author,
				 	) 
				);

				update_post_meta( $post_id, '_customer', "{$first_name} {$last_name}" );
				update_post_meta( $post_id, '_phone', $phone );
				update_post_meta( $post_id, '_order_id', $order_id );
				update_post_meta( $post_id, '_request_id', $request_id );
				update_post_meta( $post_id, '_amount', $total );
				update_post_meta( $post_id, '_paid', $total-$total );
				update_post_meta( $post_id, '_balance', $total );
				update_post_meta( $post_id, '_receipt', '' );
				update_post_meta( $post_id, '_order_status', 'on-hold' );

				// Return thankyou redirect
				return array( 
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order ),
				 );
			}
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page()
		{
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}

		/**
		 * Change payment complete order status to completed for MPesa orders.
		 *
		 * @since  3.1.0
		 * @param  string $status
		 * @param  int $order_id
		 * @param  WC_Order $order
		 * @return string
		 */
		public function change_payment_complete_order_status( $status, $order_id = 0, $order = false )
		{
			if ( $order && 'mpesa' === $order->get_payment_method() ) {
				$status = 'completed';
			}
			return $status;
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false )
		{
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	}
}

/**
 * Register Validation and Confirmation URLs
 * Outputs registration status
 */
function wc_mpesa_register()
{
	header( "Access-Control-Allow-Origin: *" );
	header( 'Content-Type:Application/json' );
	if ( ! isset( $_GET['mpesa_ipn_register'] ) ){ return; }
    
	$mpesa = new WC_Gateway_MPESA();
	wp_send_json( $mpesa->register_urls() );
}

/**
 * 
 */
function wc_mpesa_confirm()
{
	if ( ! isset( $_GET['mpesa_ipn_listener'] ) ) return;
    if ( $_GET['mpesa_ipn_listener'] !== 'confirm' ) return;

	$response = json_decode( file_get_contents( 'php://input' ), true );

	if( ! isset( $response['Body'] ) ){
    	return;
    }

 //    $resultCode 					= $response['Body']['stkCallback']['ResultCode'];
	// $resultDesc 					= $response['Body']['stkCallback']['ResultDesc'];
	// $merchantRequestID 				= $response['Body']['stkCallback']['MerchantRequestID'];
	// $checkoutRequestID 				= $response['Body']['stkCallback']['CheckoutRequestID'];

	// $post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );

	// $total = get_post_meta( $post, '_amount', true );
	// $order = get_post_meta( $post, '_order_id', true );
    
	// $mpesa = new WC_Gateway_MPESA();

	// if( isset( $response['Body']['stkCallback']['CallbackMetadata'] ) ){
	// 	$amount 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
	// 	$mpesaReceiptNumber 			= $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
	// 	$balance 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
	// 	$utilityAccountAvailableFunds 	= $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
	// 	$transactionDate 				= $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
	// 	$phone 							= $response['Body']['stkCallback']['CallbackMetadata']['Item'][5]['Value'];

	// 	$status = ( $amount == $total ) ? $mpesa->proceed( $order ) : $mpesa->reject( $order );
	// } else {
	// 	$status = $mpesa->reject( $order );
	// }

	header( "Access-Control-Allow-Origin: *" );
	header( 'Content-Type:Application/json' );
	//wp_send_json( $status );
	wp_send_json( $mpesa->proceed() );
}

/**
 * 
 */
function wc_mpesa_validate()
{
	if ( ! isset( $_GET['mpesa_ipn_listener'] ) ){ return; }
    if ( $_GET['mpesa_ipn_listener'] !== 'validate' ){ return; }

	$response = json_decode( file_get_contents( 'php://input' ), true );

	if( ! isset( $response['Body'] ) ){
    	return;
    }

 //    $resultCode 					= $response['Body']['stkCallback']['ResultCode'];
	// $resultDesc 					= $response['Body']['stkCallback']['ResultDesc'];
	// $merchantRequestID 				= $response['Body']['stkCallback']['MerchantRequestID'];
	// $checkoutRequestID 				= $response['Body']['stkCallback']['CheckoutRequestID'];

	// $post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );

	// $total = get_post_meta( $post, '_amount', true );
	// $order = get_post_meta( $post, '_order_id', true );
    
	// $mpesa = new WC_Gateway_MPESA();

	// if( isset( $response['Body']['stkCallback']['CallbackMetadata'] ) ){
	// 	$amount 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
	// 	$mpesaReceiptNumber 			= $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
	// 	$balance 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
	// 	$utilityAccountAvailableFunds 	= $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
	// 	$transactionDate 				= $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
	// 	$phone 							= $response['Body']['stkCallback']['CallbackMetadata']['Item'][5]['Value'];

	// 	$status = ( $amount == $total ) ? $mpesa->proceed( $order ) : $mpesa->reject( $order );
	// } else {
	// 	$status = $mpesa->reject( $order );
	// }

	header( "Access-Control-Allow-Origin: *" );
	header( 'Content-Type:Application/json' );
	//wp_send_json( $status );
	wp_send_json( $mpesa->proceed() );
}

/**
 * 
 */
// function status()
// {
// 	if ( ! isset( $_GET['mpesa_ipn_listener'] ) ){ return; }
//     if ( $_GET['mpesa_ipn_listener'] !== 'status' ){ return; }

//     $response = json_decode( file_get_contents( 'php://input' ), true );

//     $resultCode 				= $response['Result']['ResultCode'];
//     $resultDesc 				= $response['Result']['ResultDesc'];
//     $originatorConversationID 	= $response['Result']['OriginatorConversationID'];
//     $conversationID 			= $response['Result']['ConversationID'];
//     $transactionID 				= $response['Result']['TransactionID'];
//     $receiptNo 					= $response['Result']['ResultParameters']['ResultParameter'][0]['Value'];
//     $conversationID 			= $response['Result']['ResultParameters']['ResultParameter'][1]['Value'];
//     $finalisedTime 				= $response['Result']['ResultParameters']['ResultParameter'][2]['Value'];
//     $amount 					= $response['Result']['ResultParameters']['ResultParameter'][3]['Value'];
//     $transactionStatus 			= $response['Result']['ResultParameters']['ResultParameter'][4]['Value'];
//     $reasonType 				= $response['Result']['ResultParameters']['ResultParameter'][5]['Value'];
//     $transactionReason			= $response['Result']['ResultParameters']['ResultParameter'][6]['Value'];
//     $debitPartyCharges 			= $response['Result']['ResultParameters']['ResultParameter'][7]['Value'];
//     $debitAccountType 			= $response['Result']['ResultParameters']['ResultParameter'][8]['Value'];
//     $initiatedTime 				= $response['Result']['ResultParameters']['ResultParameter'][9]['Value'];
//     $originatorConversationID 	= $response['Result']['ResultParameters']['ResultParameter'][10]['Value'];
//     $creditPartyName 			= $response['Result']['ResultParameters']['ResultParameter'][11]['Value'];
//     $debitPartyName 			= $response['Result']['ResultParameters']['ResultParameter'][12]['Value'];

//     $post = $transactionID;
//     $status = ( $resultCode == 0 ) ? 'on-hold' : 'processing';
// 	update_post_meta( $post, '_order_status', $status );
// 	update_post_meta( $post, '_amount', $amount );

//     $order_id = get_post_meta( $post, '_order_id', true );
//     if( wc_get_order( $order_id ) ){
//     	$order = new WC_Order( $order_id );
    	
//         $order->update_status( $status );
//         $order->add_order_note( __( $resultDesc, 'woocommerce' ) );
//     }
// }
	
/**
 * 
 */
function wc_mpesa_reconcile()
{
	if ( ! isset( $_GET['mpesa_ipn_listener'] ) ){ return; }
    if ( $_GET['mpesa_ipn_listener'] !== 'reconcile' ){ return; }

    $response = json_decode( file_get_contents( 'php://input' ), true );

    if( ! isset( $response['Body'] ) ){
    	return;
    }

    $resultCode 						= $response['Body']['stkCallback']['ResultCode'];
	$resultDesc 						= $response['Body']['stkCallback']['ResultDesc'];
	$merchantRequestID 					= $response['Body']['stkCallback']['MerchantRequestID'];
	$checkoutRequestID 					= $response['Body']['stkCallback']['CheckoutRequestID'];

	$post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );
	wp_update_post( [ 'post_content' => file_get_contents( 'php://input' ), 'ID' => $post ] );

    $order_id 							= get_post_meta( $post, '_order_id', true );
	$amount_due 						=  get_post_meta( $post, '_amount', true );
	$before_ipn_paid 					= get_post_meta( $post, '_paid', true );

	if( wc_get_order( $order_id ) ){
		$order 							= new WC_Order( $order_id );
		$first_name 					= $order->get_billing_first_name();
		$last_name 						= $order->get_billing_last_name();
		$customer 						= "{$first_name} {$last_name}";
	} else {
		$customer 						= "MPesa Customer";
	}

	if( isset( $response['Body']['stkCallback']['CallbackMetadata'] ) ){
		$amount 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
		$mpesaReceiptNumber 			= $response['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
		$balance 						= $response['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];
		$transactionDate 				= $response['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
		$phone 							= $response['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

		$after_ipn_paid = $before_ipn_paid+$amount;
		$ipn_balance = $after_ipn_paid-$amount_due;

	    if( wc_get_order( $order_id ) ){
	    	$order = new WC_Order( $order_id );
	    	
	    	if ( $ipn_balance == 0 ) {
	    		$order->payment_complete();
	        	$order->add_order_note( __( "Full MPesa Payment Received From {$phone}. Receipt Number {$mpesaReceiptNumber}" ) );
				update_post_meta( $post, '_order_status', 'complete' );
	        } elseif ( $ipn_balance < 0 ) {
	        	$currency = get_woocommerce_currency();
	        	$order->payment_complete();
	            $order->add_order_note( __( "{$phone} has overpayed by {$currency} {$balance}. Receipt Number {$mpesaReceiptNumber}" ) );
				update_post_meta( $post, '_order_status', 'complete' );
	        } else {
	            $order->update_status( 'on-hold' );
	            $order->add_order_note( __( "MPesa Payment from {$phone} Incomplete" ) );
				update_post_meta( $post, '_order_status', 'on-hold' );
	        }
	    }

		update_post_meta( $post, '_paid', $after_ipn_paid );
		update_post_meta( $post, '_amount', $amount_due );
		update_post_meta( $post, '_balance', $balance );
		update_post_meta( $post, '_phone', $phone );
		update_post_meta( $post, '_customer', $customer );
		update_post_meta( $post, '_order_id', $order_id );
		update_post_meta( $post, '_receipt', $mpesaReceiptNumber );
	} else {
	    if( wc_get_order( $order_id ) ){
	    	$order = new WC_Order( $order_id );
	        $order->update_status( 'on-hold' );
	        $order->add_order_note( __( "MPesa Error {$resultCode}: {$resultDesc}" ) );
	    }
	}
}

/**
 * 
 */
function wc_mpesa_timeout()
{
	if ( ! isset( $_GET['mpesa_ipn_listener'] ) ){ return; }
    if ( $_GET['mpesa_ipn_listener'] !== 'timeout' ){ return; }

    $response = json_decode( file_get_contents( 'php://input' ), true );

    if( ! isset( $response['Body'] ) ){
    	return;
    }
 	
 	$resultCode 					= $response['Body']['stkCallback']['ResultCode'];
	$resultDesc 					= $response['Body']['stkCallback']['ResultDesc'];
	$merchantRequestID 				= $response['Body']['stkCallback']['MerchantRequestID'];
	$checkoutRequestID 				= $response['Body']['stkCallback']['CheckoutRequestID'];

	$post = get_post_id_by_meta_key_and_value( '_request_id', $merchantRequestID );
	wp_update_post( [ 'post_content' => file_get_contents( 'php://input' ), 'ID' => $post ] );
	update_post_meta( $post, '_order_status', 'pending' );

    $order_id = get_post_meta( $post, '_order_id', true );
    if( wc_get_order( $order_id ) ){
    	$order = new WC_Order( $order_id );
    	
        $order->update_status( 'pending' );
        $order->add_order_note( __( "MPesa Payment Timed Out", 'woocommerce' ) );
    }
}
