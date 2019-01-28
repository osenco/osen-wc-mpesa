<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage C2B Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.10
 * @since 0.18.01
 */

/* Setup CORS */
header('Access-Control-Allow-Origin: *');

/**
 * 
 */
class MpesaC2B
{
  public static $env = 'sandbox';
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

// $token_response = wp_remote_get( $url, array('headers' => array('Authorization' => 'Basic ' . $credentials))); 
// $token_array = json_decode($token_response['body'], true );
// return isset( $token_array->access_token ) ? $token_array->access_token : ''; 

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

    // $response = wp_remote_post( 
    //   $endpoint, 
    //   array(
    //     'headers' => array(
    //       'Content-Type' => 'application/json', 
    //       'Authorization' => 'Bearer ' . self::token()
    //     ), 
    //     'body'    => $data_string
    //   )
    // );
    // return !is_wp_error( $response ) ? json_decode( $response['body'], true ) : array( 'errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja' );
  }

  /**
   * 
   */          
  public static function reconcile( $callback, $data )
  {
    $response = is_null( $data ) ? json_decode( file_get_contents( 'php://input' ), true ) : $data;
    
    return is_null( $callback ) ? array( 'resultCode' => 0, 'resultDesc' => 'Success' ) : call_user_func_array( $callback, array( $response ) );
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

    // $response = wp_remote_post( 
    //   $endpoint, 
    //   array(
    //     'headers' => array(
    //       'Content-Type' => 'application/json', 
    //       'Authorization' => 'Bearer ' . self::token()
    //     ), 
    //     'body'    => $data_string
    //   )
    // );
    // return !is_wp_error( $response ) ? json_decode( $response['body'], true ) : array( 'errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja' );
  }
}

### WRAPPER FUNCTIONS
/**
 * Wrapper function to process response data for reconcilliation
 * @param Array $configuration - Key-value pairs of settings
 *   KEY        |   TYPE    |   DESCRIPTION         | POSSIBLE VALUES
 *  env         |   string  | Environment in use    | live/sandbox
 *  parent      |   number  | Head Office Shortcode | 123456
 *  shortcode   |   number  | Business Paybill/Till | 123456
 *  type        |   integer | Identifier Type       | 1(MSISDN)/2(Till)/4(Paybill)
 *  validate    |   string  | Validation URI        | lipia/validate
 *  confirm     |   string  | Confirmation URI      | lipia/confirm
 *  reconcile   |   string  | Reconciliation URI    | lipia/reconcile
 * @return bool
 */ 
function c2b_config( $config = array() )
{
  return MpesaC2B::set( $config );
}

/**
 * Wrapper function to process response data for validation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */ 
function c2b_validate( $callback = null, $data = null )
{
  return MpesaC2B::validate( $callback, $data );
}

/**
 * Wrapper function to process response data for confirmation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */ 
function c2b_confirm( $callback = null, $data = null  )
{
  return MpesaC2B::confirm( $callback, $data );
}

/**
 * Wrapper function to process request for payment
 * @param String $phone     - Phone Number to send STK Prompt Request to
 * @param String $amount    - Amount of money to charge
 * @param String $reference - Account to show in STK Prompt
 * @param String $trxdesc   - Transaction Description(optional)
 * @param String $remark    - Remarks about transaction(optional)
 * @return array
 */ 
function c2b_request( $phone, $amount, $reference, $trxdesc = 'Mpesa Transaction', $remark = 'Mpesa Transaction' )
{
  return MpesaC2B::request( $phone, $amount, $reference, $trxdesc, $remark );
}

/**
 * Wrapper function to process response data for reconcilliation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */          
function c2b_reconcile( $callback = null, $data = null )
{
  return MpesaC2B::reconcile( $callback, $data );
}

/**
 * Wrapper function to process response data for reconcilliation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */          
function c2b_timeout( $callback = null, $data = null )
{
  return MpesaC2B::timeout( $callback, $data );
}

/**
 * Wrapper function to register URLs
 * @return array
 */
function c2b_register( $env = 'sandbox' )
{
  return MpesaC2B::register( $env );
}