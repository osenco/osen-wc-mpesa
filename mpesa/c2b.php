<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage C2B Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.10
 * @since 0.18.01
 */

/**
 * 
 */
if ( !class_exists( 'MpesaC2B' ) ) {
  class MpesaC2B
  {
    public static $env = 'sandbox';
    public static $configs;
    public static $business;
    public static $appkey;
    public static $appsecret;
    public static $passkey;
    public static $shortcode;
    public static $headoffice;
    public static $type = 4;
    public static $validate;
    public static $confirm;
    public static $reconcile;
    public static $timeout;

    function __construct()
    {
      /**
       * Setup CORS 
       */
      header('Access-Control-Allow-Origin: *');
    }

    /**  
     * @param Array $config - Key-value pairs of settings
     *   KEY        |   TYPE    |   DESCRIPTION         | POSSIBLE VALUES
     *  env         |   string  | Environment in use    | live/sandbox
     *  parent      |   number  | Head Office Shortcode | 123456
     *  shortcode   |   number  | Business Paybill/Till | 123456
     *  type        |   integer | Identifier Type       | 1(MSISDN)/2(Till)/4(Paybill)
     *  validate    |   string  | Validation URI        | lipia/validate
     *  confirm     |   string  | Confirmation URI      | lipia/confirm
     *  reconcile   |   string  | Reconciliation URI    | lipia/reconcile
     */
    public static function set( $config )
    {
      foreach ( $config as $key => $value ) {
        self::$$key = $value;
        self::$configs[$key] = $value;
      }
    }

    /**
     * 
     */
    public static function token()
    {
      $endpoint = ( self::$env == 'live' ) ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

      $credentials = base64_encode( self::$appkey.':'.self::$appsecret );
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_URL, $endpoint );
      curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Basic '.$credentials ) );
      curl_setopt( $curl, CURLOPT_HEADER, false );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
      curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
      $curl_response = curl_exec( $curl );

      $data = json_decode( $curl_response );
      
      return $data->access_token ?? '';

    }

    /**
     * 
     */
    public static function validate( $callback, $data )
    {
      if( is_null( $callback) ){
        return array( 
          'ResponseCode'            => 0, 
          'ResponseDesc'            => 'Success',
          'ThirdPartyTransID'       => $data['transID'] ?? 0
         );
      } else {
          if ( !call_user_func_array( $callback, array( $data ) ) ) {
            return array( 
              'ResponseCode'        => 1, 
              'ResponseDesc'        => 'Failed',
              'ThirdPartyTransID'   => $data['transID'] ?? 0
             );
          } else {
            return array( 
              'ResponseCode'        => 0, 
              'ResponseDesc'        => 'Success',
              'ThirdPartyTransID'   => $data['transID'] ?? 0
             );
          }
      }
    }

    /**
     * 
     */
    public static function timeout( $callback = null, $data = null )
    {
      if( is_null( $callback ) ){
        return true;
      } else {
        return call_user_func_array( $callback, array( $data ) );
      }
    }

    /**
     * 
     */
    public static function confirm( $callback, $data )
    {
      if( is_null( $callback) ){
        return array( 
          'ResponseCode'          => 0, 
          'ResponseDesc'          => 'Success',
          'ThirdPartyTransID'     => $data['transID'] ?? 0
         );
      } else {
        if ( !call_user_func_array( $callback, array( $data ) ) ) {
          return array( 
            'ResponseCode'        => 1, 
            'ResponseDesc'        => 'Failed',
            'ThirdPartyTransID'   => $data['transID'] ?? 0
           );
        } else {
          return array( 
            'ResponseCode'        => 0, 
            'ResponseDesc'        => 'Success',
            'ThirdPartyTransID'   => $data['transID'] ?? 0
           );
        }
      }
    }

    /**
     * 
     */
    public static function request( $phone, $amount, $reference, $trxdesc = '', $remark = '' )
    {
      $phone      = str_replace( "+", "", $phone );
      $phone      = preg_replace( '/^0/', '254', $phone );
      $token      = self::token();

      $endpoint   = ( self::$env == 'live' ) ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest' : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

      $timestamp  = date( 'YmdHis' );
      $password   = base64_encode( self::$headoffice.self::$passkey.$timestamp );
      $curl       = curl_init();
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
        'BusinessShortCode'   => self::$headoffice,
        'Password'            => $password,
        'Timestamp'           => $timestamp,
        'TransactionType'     => ( self::$type == 4 ) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
        'Amount'              => round( $total ),
        'PartyA'              => $phone,
        'PartyB'              => self::$shortcode,
        'PhoneNumber'         => $phone,
        'CallBackURL'         => self::$reconcile,
        'AccountReference'    => $reference,
        'TransactionDesc'     => 'WooCommerce Payment For '.$order_id,
        'Remark'              => 'WooCommerce Payment Via MPesa'
      );

      $data_string = json_encode( $curl_post_data );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string );
      curl_setopt( $curl, CURLOPT_HEADER, false );


      $response = curl_exec( $curl );
      return curl_exec( $curl ) ? json_decode( $response, true ) : array( 'errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja' );
    }

    /**
     * 
     */          
    public static function reconcile( $callback, $data )
    {
      $response = is_null( $data ) ? json_decode( file_get_contents( 'php://input' ), true ) : $data;
      
      return is_null( $callback ) ? array( 'resultCode' => 0, 'resultDesc' => 'Reconciliation successful', 'config' => self::$configs ) : call_user_func_array( $callback, array( $response ) );
    }

    /**
     * 
     */
    public static function register( $env = 'sandbox' )
    {
      $endpoint = ( $env == 'live' ) ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl' : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_URL, $endpoint );
      curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json','Authorization:Bearer '.self::token() ) );
          
      $curl_post_data = array( 
        'ShortCode'         => self::$shortcode,
        'ResponseType'      => 'Cancelled',
        'ConfirmationURL'   => self::$confirm,
        'ValidationURL'     => self::$validate
      );
      $data_string = json_encode( $curl_post_data );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string );
      curl_setopt( $curl, CURLOPT_HEADER, false );

      $response = curl_exec( $curl );
      return curl_exec( $curl ) ? json_decode( $response, true ) : array( 'errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja' );
    }
  }
}