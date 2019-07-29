<?php
namespace Osen\Mpesa;

/**
 * @package MPesa For WooCommerce
 * @subpackage B2C Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.10
 * @since 0.18.01
 */

/**
 * 
 */
class B2C
{
  public static $env = 'sandbox';
  public static $username;
  public static $password;
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
  {}

  public static function set($config)
  {
    foreach ($config as $key => $value) {
      self::$$key = $value;
    }
  }

  /**
   * 
   */
  public static function token()
  {
    $endpoint = (self::$env == 'live') ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials' : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $credentials = base64_encode(self::$appkey.':'.self::$appsecret);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $endpoint);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials));
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_response = curl_exec($curl);

    $data = json_decode($curl_response);
    
    return $data->access_token ?? '';
  }

  /**
   * 
   */
  public static function validate($callback, $data)
  {
    if(is_null($callback)){
      return array(
        'ResponseCode'            => 0, 
        'ResponseDesc'            => 'Success',
        'ThirdPartyTransID'       => $data['transID'] ?? 0
      );
    } else {
        if (!call_user_func_array($callback, array($data))) {
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
  public static function timeout($callback = null, $data = null)
  {
    if(is_null($callback)){
      return true;
    } else {
      return call_user_func_array($callback, array($data));
    }
  }

  /**
   * 
   */
  public static function confirm($callback, $data)
  {
    if(is_null($callback)){
      return array(
        'ResponseCode'          => 0, 
        'ResponseDesc'          => 'Success',
        'ThirdPartyTransID'     => $data['transID'] ?? 0
      );
    } else {
      if (!call_user_func_array($callback, array($data))) {
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
  public static function request($phone, $amount, $reference, $trxdesc = '', $remark = '')
  {
    $phone      = str_replace("+", "", $phone);
    $phone      = preg_replace('/^0/', '254', $phone);
    $token      = self::token();
    $endpoint   = (self::$env == 'live') ? 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' : 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
    $timestamp  = date('YmdHis');
    $env        = self::$env;
    $plaintext  = self::$password;
    $publicKey  = file_get_contents('cert/'.$env.'/cert.cr');

    openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

    $password    = base64_encode($encrypted);

    $curl_post_data = array(
      'InitiatorName'       => self::$username,
      'SecurityCredential'  => $password,
      'CommandID'           => (self::$type == 4) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
      'Amount'              => round($amount),
      'PartyA'              => self::$shortcode,
      'PartyB'              => $phone,
      'Remarks'             => $remark,
      'QueueTimeOutURL'     => self::$timeout,
      'ResultURL'           => self::$reconcile,
      'Occasion'            => $reference
   );

    $data_string = json_encode($curl_post_data);

    $response = wp_remote_post(
      $endpoint, 
      array(
        'headers' => array(
          'Content-Type' => 'application/json', 
          'Authorization' => 'Bearer ' . self::token()
       ), 
        'body'    => $data_string
     )
   );
    return is_wp_error($response) ? array('errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja') : json_decode($response['body'], true);
  }

  /**
   * 
   */          
  public static function reconcile($callback, $data)
  {
    $response = is_null($data) ? json_decode(file_get_contents('php://input'), true) : $data;
    
    return is_null($callback) ? array('resultCode' => 0, 'resultDesc' => 'Success') : call_user_func_array($callback, array($response));
  }
}