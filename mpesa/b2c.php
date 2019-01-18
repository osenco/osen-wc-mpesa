<?php
/**
 * @package MPesa For WooCommerce
 * @subpackage B2C Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.10
 * @since 0.18.01
 */

/* Setup CORS */
header('Access-Control-Allow-Origin: *');

/**
 * 
 */
class MpesaB2C
{
  public static $env = 'sandbox';
  public static $appkey;
  public static $appsecret;
  public static $passkey;
  public static $shortcode;
  public static $username;
  public static $password;
  public static $type = 4;
  public static $validate;
  public static $confirm;
  public static $reconcile;
  public static $instructions;

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
    
    return json_decode( $curl_response )->access_token;
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
        'ThirdPartyTransID'       => $data['transID']
       );
    } else {
        if ( !call_user_func_array( $callback, array( $data ) ) ) {
          return array( 
            'ResponseCode'        => 1, 
            'ResponseDesc'        => 'Failed',
            'ThirdPartyTransID'   => $data['transID']
           );
        } else {
          return array( 
            'ResponseCode'            => 0, 
            'ResponseDesc'            => 'Success',
            'ThirdPartyTransID'       => $data['transID']
           );
        }
    }
  }

  /**
   * 
   */
  public static function confirm( $callback, $data )
  {
    if( is_null( $callback) ){
      return array( 
        'ResponseCode'            => 0, 
        'ResponseDesc'            => 'Success',
        'ThirdPartyTransID'       => $data['transID']
       );
    } else {
      if ( !call_user_func_array( $callback, array( $data ) ) ) {
        return array( 
          'ResponseCode'        => 1, 
          'ResponseDesc'        => 'Failed',
          'ThirdPartyTransID'   => $data['transID']
         );
      } else {
      return array( 
        'ResponseCode'            => 0, 
        'ResponseDesc'            => 'Success',
        'ThirdPartyTransID'       => $data['transID']
       );
      }
    }
  }

  /**
   * 
   */
  public static function request( $phone, $amount, $reference, $trxdesc, $remark )
  {
    $phone      = str_replace( "+", "", $phone );
    $phone      = preg_replace('/^0/', '254', $phone);
    $token      = self::token();
    $endpoint   = ( self::$env == 'live' ) ? 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' : 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
    $timestamp  = date( 'YmdHis' );
    $env        = self::$env;
    $plaintext  = self::$password;
    $publicKey  = file_get_contents( '../cert/'.$env.'/cert.cr' );

    openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

    $password    = base64_encode($encrypted);

    $curl        = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $endpoint );
    curl_setopt( $curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '. $token )); //setting custom header

    $curl_post_data = array(
      'InitiatorName'       => self::$username,
      'SecurityCredential'  => $password,
      'CommandID'           => ( self::$type == 4 ) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
      'Amount'              => round( $amount ),
      'PartyA'              => self::$shortcode,
      'PartyB'              => $phone,
      'Remarks'             => $remark,
      'QueueTimeOutURL'     => self::$timeout,
      'ResultURL'           => self::$reconcile,
      'Occasion'            => $reference
    );

    $data_string = json_encode($curl_post_data);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

    $requested = curl_exec($curl);

    if ( !$requested ) {
      $response = array( 'errorMessage' => 'Could Not Connect To Daraja' );
    } else {
      $response = json_decode( $requested, true );
    }

    return $response;
  }

  /**
   * 
   */          
  public static function reconcile( $callback = null, $data = null )
  {
    $response = is_null( $data ) ? json_decode( file_get_contents( 'php://input' ), true ) : $data;
    if( !isset( $response['Body'] ) ){
      return false;
    }

    $payment = $response['Body'];
    if( !isset( $payment['CallbackMetadata'] ) ){
      $data = null;
      return false;
    }

    $data = array(
      'receipt' => $payment['CallbackMetadata']['Item'][1]['Value'],
      'amount'  => $payment['CallbackMetadata']['Item'][0]['Value']
    );
    
    return is_null( $callback ) ? true : call_user_func_array( $callback, $payment );
  }

  /**
   * 
   */
  public static function register()
  {
    $endpoint = ( self::$env == 'live' ) ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl' : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
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

    return json_decode( curl_exec( $curl ), true );
  }
}

### WRAPPER FUNCTIONS
/**
 * Wrapper function to process response data for reconcilliation
 * @param Array $config - Key-value pairs of settings
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
function b2c_config( $config = array() )
{
  return MpesaB2C::set( $config );
}

/**
 * Wrapper function to process response data for validation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */ 
function b2c_validate( $callback = null, $data = null )
{
  return MpesaB2C::validate( $callback, $data );
}

/**
 * Wrapper function to process response data for confirmation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */ 
function b2c_confirm( $callback = null, $data = null  )
{
  return MpesaB2C::confirm( $callback, $data );
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
function b2c_request( $phone, $amount, $reference, $trxdesc = 'Mpesa Transaction', $remark = ' Mpesa Transaction' )
{
  return MpesaB2C::request( $phone, $amount, $reference, $trxdesc, $remark );
}

/**
 * Wrapper function to process response data for reconcilliation
 * @param String $callback - Optional callback function to process the response
 * @return bool
 */          
function b2c_reconcile( $callback = null, $data = null )
{
  return MpesaB2C::reconcile( $callback, $data );
}

/**
 * Wrapper function to register URLs
 * @return array
 */
function b2c_register()
{
  return MpesaB2C::register();
}
