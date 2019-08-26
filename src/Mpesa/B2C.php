<?php
namespace Osen\Mpesa;

/**
 * @package M-PESA For WooCommerce
 * @subpackage M-PESA B2C Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.19.8
 * @since 0.18.01
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
    public static $result;
    public static $timeout;

    public function __construct()
    {}

    public static function set($config)
    {
        foreach ($config as $key => $value) {
            self::$$key = $value;
        }
    }

    /**
     * Function to generate access token
     * @return string/mixed
     */
    public static function token()
    {
        $endpoint = (self::$env == 'live')
        ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode(self::$appkey . ':' . self::$appsecret);
        $response    = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $credentials,
                ),
            )
        );

        return is_wp_error($response)
        ? 'Invalid'
        : json_decode($response['body'])->access_token;
    }

    /**
     * Function to process response data for validation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public static function validate($callback = null)
    {
        if (is_null($callback) || empty($callback)) {
            return array(
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            );
        } else {
            if (!call_user_func_array($callback, array($data))) {
                return array(
                    'ResultCode' => 1,
                    'ResultDesc' => 'Failed',
                );
            } else {
                return array(
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                );
            }
        }
    }

    /**
     * Function to process response data for confirmation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public static function confirm($callback = null, $data = [])
    {
        if (is_null($callback) || empty($callback)) {
            return array(
                'ResultCode'        => 0,
                'ResultDesc'        => 'Success',
                'ThirdPartyTransID' => isset($data['transID']) ? $data['transID'] : 0,
            );
        } else {
            if (!call_user_func_array($callback, array($data))) {
                return array(
                    'ResultCode'        => 1,
                    'ResultDesc'        => 'Failed',
                    'ThirdPartyTransID' => isset($data['transID']) ? $data['transID'] : 0,
                );
            } else {
                return array(
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'Success',
                    'ThirdPartyTransID' => isset($data['transID']) ? $data['transID'] : 0,
                );
            }
        }
    }

    /**
     *
     */
    public static function request($phone, $amount, $reference, $trxdesc = '', $remark = '')
    {
        $phone    = str_replace("+", "", $phone);
        $phone    = preg_replace('/^0/', '254', $phone);
        $token    = self::token();
        $endpoint = (self::$env == 'live')
        ? 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
        $timestamp = date('YmdHis');
        $env       = self::$env;
        $plaintext = self::$password;
        $publicKey = file_get_contents(__DIR__ . '/cert/' . self::$env . '/cert.cer');

        openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        $password = base64_encode($encrypted);

        $curl_post_data = array(
            'InitiatorName'      => self::$username,
            'SecurityCredential' => $password,
            'CommandID'          => (self::$type == 4) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
            'Amount'             => round($amount),
            'PartyA'             => self::$shortcode,
            'PartyB'             => $phone,
            'Remarks'            => $remark,
            'QueueTimeOutURL'    => self::$timeout,
            'ResultURL'          => self::$reconcile,
            'Occasion'           => $reference,
        );

        $data_string = json_encode($curl_post_data);

        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . self::token(),
                ),
                'body'    => $data_string,
            )
        );
        return is_wp_error($response)
        ? array('errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja')
        : json_decode($response['body'], true);
    }

    /**
     *
     */
    public static function reconcile($callback, $data)
    {
        $response = is_null($data) ? json_decode(file_get_contents('php://input'), true) : $data;

        return is_null($callback)
        ? array('resultCode' => 0, 'resultDesc' => 'Success')
        : call_user_func_array($callback, array($response));
    }
}
