<?php

namespace Osen\Woocommerce\Mpesa;

/**
 * @package MPesa For WooCommerce
 * @subpackage C2B Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 2.0.0
 * @since 0.18.01
 */

/**
 *
 */
class C2B
{
    /**
     * @param string  | Environment in use    | live/sandbox
     */
    public $env = 'sandbox';

    /**
     * @param string | Daraja App Consumer Key   | lipia/validate
     */
    public $appkey;

    /**
     * @param string | Daraja App Consumer Secret   | lipia/validate
     */
    public $appsecret;

    /**
     * @param string | Online Passkey | lipia/validate
     */
    public $passkey;

    /**
     * @param string  | Head Office Shortcode | 123456
     */
    public $headoffice;

    /**
     * @param string  | Business Paybill/Till | 123456
     */
    public $shortcode;

    /**
     * @param integer | Identifier Type   | 1(MSISDN)/2(Till)/4(Paybill)
     */
    public $type = 4;

    /**
     * @param string | Validation URI   | lipia/validate
     */
    public $validate;

    /**
     * @param string  | Confirmation URI  | lipia/confirm
     */
    public $confirm;

    /**
     * @param string  | Reconciliation URI  | lipia/reconcile
     */
    public $reconcile;

    /**
     * @param string  | Timeout URI   | lipia/reconcile
     */
    public $timeout;
    /**
     * @param array $config - Key-value pairs of settings
     */
    public function __construct()
    {
        $c2b = get_option('woocommerce_mpesa_settings');
        $config = array(
            'env'        => $c2b['env'] ?? 'sandbox',
            'appkey'     => $c2b['key'] ?? 'bclwIPkcRqw61yUt',
            'appsecret'  => $c2b['secret'] ?? '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
            'headoffice' => $c2b['headoffice'] ?? '174379',
            'shortcode'  => $c2b['shortcode'] ?? '174379',
            'type'       => $c2b['idtype'] ?? 4,
            'passkey'    => $c2b['passkey'] ?? 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            'validate'   => home_url('lipwa/validate/'),
            'confirm'    => home_url('lipwa/confirm/'),
            'reconcile'  => home_url('lipwa/reconcile/'),
            'timeout'    => home_url('lipwa/timeout/'),
        );

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Function to generate access token
     * @return string/mixed
     */
    public function token()
    {
        $endpoint = ($this->env == 'live')
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->appkey . ':' . $this->appsecret);
        $response    = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $credentials,
                ),
            )
        );

        $return = is_wp_error($response)
            ? 'null'
            : json_decode($response['body']);

        return is_null($return)
            ? '' : (isset($return->access_token)
                ? $return->access_token : '');
    }

    /**
     * Function to process response data for validation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public function validate($callback, $data)
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
    public function confirm($callback, $data)
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
     * Function to register validation and confirmation URLs
     * @param string $env - Environment for which to register URLs
     * @return bool/array
     */
    public function register($callback = null)
    {
        $endpoint = ($this->env == 'live')
            ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl'
            : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

        $post_data = array(
            'ShortCode'       => $this->headoffice,
            'ResponseType'    => 'Cancelled',
            'ConfirmationURL' => $this->confirm,
            'ValidationURL'   => $this->validate,
        );
        $data_string = json_encode($post_data);

        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token(),
                ),
                'body'    => $data_string,
            )
        );
        $result = is_wp_error($response)
            ? array('errorCode' => 1, 'errorMessage' => $response->get_error_message())
            : json_decode($response['body'], true);

        return is_null($callback)
            ? $result
            : call_user_func($callback, $result);
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
    public function request($phone, $amount, $reference, $trxdesc = 'WooCommerce Payment', $remark = 'WooCommerce Payment')
    {
        $phone     = preg_replace('/^0/', '254', str_replace("+", "", $phone));
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->headoffice . $this->passkey . $timestamp);
        $endpoint  = ($this->env == 'live')
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $post_data = array(
            'BusinessShortCode' => $this->headoffice,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => ($this->type == 4) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
            'Amount'            => round($amount),
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->reconcile,
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
                    'Authorization' => 'Bearer ' . $this->token(),
                ),
                'body'    => $data_string,
            )
        );
        return is_wp_error($response)
            ? array('errorCode' => 1, 'errorMessage' => $response->get_error_message())
            : json_decode($response['body'], true);
    }

    /**
     * Function to process response data for reconciliation
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return bool/array
     */
    public function reconcile($args)
    {
        $callback = isset($args[0]) ? $args[0] : 'wc_mpesa_reconcile';
        $data     = isset($args[1]) ? $args[1] : null;

        if (is_null($data)) {
            $response = json_decode(file_get_contents('php://input'), true);
            $response = isset($response['Body']) ? $response['Body'] : array();
        } else {
            $response = $data;
        }

        return is_null($callback)
            ? array('resultCode' => 0, 'resultDesc' => 'Reconciliation successful')
            : call_user_func_array($callback, array($response));
    }

    /**
     * Function to process response data if system times out
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return bool/array
     */
    public function timeout($callback = null, $data = null)
    {
        if (is_null($data)) {
            $response = json_decode(file_get_contents('php://input'), true);
            $response = isset($response['Body']) ? $response['Body'] : array();
        } else {
            $response = $data;
        }

        if (is_null($callback)) {
            return true;
        } else {
            return call_user_func_array($callback, array($response));
        }
    }
}
