<?php

namespace Osen\Woocommerce\Mpesa;

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
	public $results;

	/**
	 * @param string  | Timeout URI   | lipia/reconcile
	 */
	public $timeout;

	/**
	 * @param string  | Timeout URI   | lipia/reconcile
	 */
	public $username;

	/**
	 * @param string  | Timeout URI   | lipia/reconcile
	 */
	public $credentials;

	/**
	 * @param array $config - Key-value pairs of settings
	 */
	public function __construct()
	{
		$c2b = get_option('woocommerce_mpesa_settings');
		$config = array(
        'env'        => isset($c2b['env']) ? $c2b['env'] : 'sandbox',
        'appkey'     => isset($c2b['key']) ? $c2b['key'] : 'bclwIPkcRqw61yUt',
        'appsecret'  => isset($c2b['secret']) ? $c2b['secret'] : '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
        'headoffice' => isset($c2b['headoffice']) ? $c2b['headoffice'] : '174379',
        'shortcode'  => isset($c2b['shortcode']) ? $c2b['shortcode'] : '174379',
        'type'       => isset($c2b['idtype']) ? $c2b['idtype'] : 4,
        'passkey'    => isset($c2b['passkey']) ? $c2b['passkey'] : 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'validate'   => home_url('lipwa/validate/'),
        'confirm'    => home_url('lipwa/confirm/'),
        'reconcile'  => home_url('lipwa/reconcile/'),
        'timeout'    => home_url('lipwa/timeout/')
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

		$return = is_wp_error($response) ? 'null' : json_decode($response['body']);

		return is_null($return) ? '' : (isset($return->access_token) ? $return->access_token : '');
	}

	/**
	 * Function to process response data for validation
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
	 * @param string $phone     - Phone Number to send STK Prompt Request to
	 * @param string $amount    - Amount of money to charge
	 * @param string $reference - Account to show in STK Prompt
	 * @param string $trxdesc   - Transaction Description(optional)
	 * @param string $remark    - Remarks about transaction(optional)
	 * @return array
	 */
	public function request($phone, $amount, $reference, $trxdesc = 'WooCommerce Payment', $remark = 'WooCommerce Payment', $request = null)
	{
		$phone = preg_replace('/^0/', '254', str_replace("+", "", $phone));

		$endpoint = ($this->env == 'live')
			? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
			: 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

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

		if(is_wp_error($response)){
			return array('errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja');
		} else {
			$body = json_decode($response['body'], true);
			return is_null($request) 
				? $body 
				: array_merge($body, ['requested' => $post_data]);
		}
	}

	/**
	 * Function to process response data for reconciliation
	 * @param callable $callback - Optional callable function to process the response - must return boolean
	 * @return bool/array
	 */
	public function reconcile($callback = null, $data = null)
	{
		$response = is_null($data) ? json_decode(file_get_contents('php://input'), true) : $data;

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

	public function status($transaction, $command = 'TransactionStatusQuery', $remarks = 'Transaction Status Query', $occasion = '')
	{
		$token    = $this->token();
		$endpoint = ($this->env == 'live')
			? 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
			: 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query';

		$post_data = array(
			'Initiator'          => $this->username,
			'SecurityCredential' => $this->credentials,
			'CommandID'          => $command,
			'TransactionID'      => $transaction,
			'PartyA'             => $this->shortcode,
			'IdentifierType'     => $this->type,
			'ResultURL'          => $this->results,
			'QueueTimeOutURL'    => $this->timeout,
			'Remarks'            => $remarks,
			'Occasion'           => $occasion,
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
			? array('errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja')
			: json_decode($response['body'], true);
	}
}
