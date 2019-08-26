<?php
namespace Osen\Mpesa;

/**
 * @package M-PESA For WooCommerce
 * @subpackage M-PESA STK Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.19.8
 * @since 0.18.01
 */

class STK
{
    /**
     * @param string  | Environment in use    | live/sandbox
     */
    public static $env = 'sandbox';

    /**
     * @param string | Daraja App Consumer Key   | lnmo/validate
     */
    public static $appkey;

    public static $username;
    public static $password;

    /**
     * @param string | Daraja App Consumer Secret   | lnmo/validate
     */
    public static $appsecret;

    /**
     * @param string | Online Passkey | lnmo/validate
     */
    public static $passkey;

    /**
     * @param string  | Head Office Shortcode | 123456
     */
    public static $headoffice;

    /**
     * @param string  | Business Paybill/Till | 123456
     */
    public static $shortcode;

    /**
     * @param integer | Identifier Type   | 1(MSISDN)/2(Till)/4(Paybill)
     */
    public static $type = 4;

    /**
     * @param string | Validation URI   | lnmo/validate
     */
    public static $validate;

    /**
     * @param string  | Confirmation URI  | lnmo/confirm
     */
    public static $confirm;

    /**
     * @param string  | Reconciliation URI  | lnmo/reconcile
     */
    public static $reconcile;

    /**
     * @param string  | Timeout URI   | lnmo/reconcile
     */
    public static $result;

    /**
     * @param string  | Timeout URI   | lnmo/reconcile
     */
    public static $timeout;

    public function __construct()
    {}

    /**
     * @param array $config - Key-value pairs of settings
     */
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
    public static function validate($callback = null, $data = [])
    {
        $data = empty($data) ? json_decode(file_get_contents('php://input'), true) : $data;
        if (is_null($callback)) {
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
     * Function to process response data for confirmation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public static function confirm($callback = null, $data = [])
    {
        if (is_null($callback)) {
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
     * Function to process request for payment
     * @param string $phone     - Phone Number to send STK Prompt Request to
     * @param string $amount    - Amount of money to charge
     * @param string $reference - Account to show in STK Prompt
     * @param string $trxdesc   - Transaction Description(optional)
     * @param string $remark    - Remarks about transaction(optional)
     * @return array
     */
    public static function request($phone, $amount, $reference, $trxdesc = 'WooCommerce Payment', $remark = 'WooCommerce Payment')
    {
        $phone = preg_replace('/^0/', '254', str_replace("+", "", $phone));

        $endpoint = (self::$env == 'live')
        ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = date('YmdHis');
        $password  = base64_encode(self::$headoffice . self::$passkey . $timestamp);

        $post_data = array(
            'BusinessShortCode' => self::$headoffice,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => (self::$type == 4) ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline',
            'Amount'            => round($amount),
            'PartyA'            => $phone,
            'PartyB'            => self::$shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => self::$reconcile,
            'AccountReference'  => $reference,
            'TransactionDesc'   => $trxdesc,
            'Remark'            => $remark,
        );

        $data_string = json_encode($post_data);
        $response    = wp_remote_post(
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
        ? array('errorCode' => 500, 'errorMessage' => 'Could not connect to Daraja')
        : json_decode($response['body'], true);
    }

    /**
     * Function to process response data for reconciliation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return bool/array
     */
    public static function reconcile($callback = null, $data = [])
    {
        $data = empty($data) ? json_decode(file_get_contents('php://input'), true) : $data;
        if (is_null($callback)) {
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
     * Function to process response data if system times out
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return bool/array
     */
    public static function timeout($callback = null, $data = [])
    {
        $data = empty($data) ? json_decode(file_get_contents('php://input'), true) : $data;
        if (is_null($callback)) {
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

    public static function status($transaction, $command = 'TransactionStatusQuery', $remarks = 'Transaction Status Query', $occasion = '')
    {
        $endpoint = (self::$env == 'live')
        ? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
        : 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

        $token     = self::token();
        $plaintext = self::$password;
        $publicKey = file_get_contents(__DIR__ . '/cert/' . self::$env . '/cert.cer');

        openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        $password = base64_encode($encrypted);

        $post_data = array(
            'Initiator'          => self::$username,
            'SecurityCredential' => $password,
            'CommandID'          => $command,
            'TransactionID'      => $transaction,
            'PartyA'             => self::$shortcode,
            'IdentifierType'     => self::$type,
            'ResultURL'          => self::$result,
            'QueueTimeOutURL'    => self::$timeout,
            'Remarks'            => $remarks,
            'Occasion'           => $occasion,
        );

        $data_string = json_encode($post_data);
        $response    = wp_remote_post(
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
        ? array('errorCode' => 500, 'errorMessage' => 'Could not connect to Daraja')
        : json_decode($response['body'], true);
    }
}
