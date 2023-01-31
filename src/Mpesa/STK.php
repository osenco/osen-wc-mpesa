<?php

namespace Osen\Woocommerce\Mpesa;

/**
 * @package M-Pesa For WooCommerce
 * @subpackage C2B Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 2.0.0
 * @since 0.18.01
 */

/**
 *
 */
class STK
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
     * @param int | Identifier Type   | 1(MSISDN)/2(Till)/4(Paybill)
     */
    public $type = 4;

    /**
     * @param string  | Timeout URI   | lipia/reconcile
     */
    public $initiator;

    /**
     * @param string  | Timeout URI   | lipia/reconcile
     */
    public $password;

    /**
     * @param string  | Account Reference  | defaults to 'order_id'
     */
    public $reference = '';

    /**
     * @param string  | Encryption Signature
     */
    public $signature;

    /**
     * @param string  | generated/Stored Token
     */
    public $token;

    /**
     * @param string  | Base API URL
     */
    private $url = 'https://api.safaricom.co.ke';

    /**
     * @param array $config - Key-value pairs of settings
     */
    public function __construct()
    {
        $config = apply_filters('wc_mpesa_settings', array(
            'env'        => 'sandbox',
            'appkey'     => '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
            'appsecret'  => 'bclwIPkcRqw61yUt',
            'headoffice' => 174379,
            'shortcode'  => 174379,
            'initiator'  => 'test',
            'password'   => 'lipia',
            'type'       => 4,
            'passkey'    => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            'reference'  => '',
            'signature'  => md5(rand(12, 999)),
        ));

        if ($config['env'] === 'sandbox') {
            $this->url = 'https://sandbox.safaricom.co.ke';
        }

        if ((int) $config['type'] === 4) {
            $config['headoffice'] = $config['shortcode'];
        }

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Function to generate access token
     *
     * @return STK
     */
    public function authorize($token = null)
    {
        if (is_null($token) || !$token) {
            $credentials = base64_encode($this->appkey . ':' . $this->appsecret);
            $response    = wp_remote_get(
                $this->url . '/oauth/v1/generate?grant_type=client_credentials',
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . $credentials,
                    ),
                )
            );

            $return      = is_wp_error($response) ? 'null' : json_decode($response['body']);
            $this->token = isset($return->access_token) ? $return->access_token : '';
            set_transient('mpesa_token', $this->token, 60 * 55);
        } else {
            $this->token = $token;
        }

        return $this;
    }

    /**
     * Function to process response data for validation
     *
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public function validate($callback = null, $data = [])
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
     *
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return array
     */
    public function confirm($callback = null, $data = [])
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
     * Function to process request for payment
     *
     * @param string|int $phone     - Phone Number to send STK Prompt Request to
     * @param int|float $amount    - Amount of money to charge
     * @param string|int $reference - Account to show in STK Prompt
     * @param string $trx_desc   - Transaction Description(optional)
     * @param string $remark    - Remarks about transaction(optional)
     *
     * @return array
     */
    public function request($phone, $amount, $reference, $trx_desc = 'WooCommerce Payment', $remark = 'WooCommerce Payment', $request = null)
    {
        $phone     = '254' . substr($phone, -9);
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->headoffice . $this->passkey . $timestamp);
        $post_data = array(
            'BusinessShortCode' => $this->headoffice,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => ($this->type == 4) ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline',
            'Amount'            => round($amount),
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => add_query_arg(
                array(
                    'action' => 'reconcile',
                    'sign'   => $this->signature,
                    'order'  => $reference,
                ),
                home_url("wc-api/lipwa")
            ),
            'AccountReference'  => empty($this->reference) ? $reference : $this->reference,
            'TransactionDesc'   => $trx_desc,
            'Remark'            => $remark,
        );

        $data_string = wp_json_encode($post_data);
        $response    = wp_remote_post(
            $this->url . '/mpesa/stkpush/v1/processrequest',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ),
                'body'    => $data_string,
            )
        );

        if (is_wp_error($response)) {
            return array('errorCode' => 500, 'errorMessage' => $response->get_error_message());
        } else {
            $body = json_decode($response['body'], true);
            return is_null($request)
                ? $body
                : array_merge($body, ['requested' => $post_data]);
        }
    }

    /**
     * Function to process response data for reconciliation
     *
     * @param callable $callback - Optional callable function to process the response - must return boolean
     * @return bool/array
     */
    public function reconcile($callback = null, $data = null)
    {
        $response = is_null($data) ? json_decode(file_get_contents('php://input'), true) : $data;

        return is_null($callback)
            ? array('resultCode' => 0, 'resultDesc' => 'Reconciliation successful')
            : (call_user_func_array($callback, array($response)) ? array('resultCode' => 0, 'resultDesc' => 'Reconciliation successful')
                : array('resultCode' => 1, 'resultDesc' => 'Reconciliation failed'));
    }

    /**
     * Function to process response data if system times out
     *
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

    public function status($transaction, $command = 'TransactionStatusQuery', $remarks = 'Transaction Status Query', $occasion = '')
    {
        $env       = $this->env;
        $plain_text = $this->password;
        $public_key = file_get_contents(__DIR__ . "/cert/{$env}/cert.cer");

        openssl_public_encrypt($plain_text, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);

        $password  = base64_encode($encrypted);
        $post_data = array(
            'Initiator'          => $this->initiator,
            'SecurityCredential' => $password,
            'CommandID'          => $command,
            'TransactionID'      => $transaction,
            'PartyA'             => $this->shortcode,
            'IdentifierType'     => $this->type,
            'ResultURL'          => home_url('wc-api/lipwa?action=result'),
            'QueueTimeOutURL'    => home_url('wc-api/lipwa?action=timeout'),
            'Remarks'            => $remarks,
            'Occasion'           => $occasion,
        );

        $data_string = wp_json_encode($post_data);
        $response    = wp_remote_post(
            $this->url . '/mpesa/transactionstatus/v1/query',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ),
                'body'    => $data_string,
                'timeout' => 500
            )
        );

        return is_wp_error($response)
            ? array('errorCode' => 1, 'errorMessage' => $response->get_error_message())
            : json_decode($response['body'], true);
    }
}
