<?php

/**
 * @package M-Pesa For WooCommerce
 * @subpackage C2B Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 2.0.0
 * @since 0.18.01
 */

namespace Osen\Woocommerce\Mpesa;

class C2B
{
	/**
	 * @param string $env
	 * Environment in use - live/sandbox
	 */
	public $env = 'sandbox';

	/**
	 * @param string $appkey
	 * Daraja App Consumer Key   | lipia/validate
	 */
	public $appkey;

	/**
	 * @param string $appsecret
	 * Daraja App Consumer Secret   | lipia/validate
	 */
	public $appsecret;

	/**
	 * @param string $passkey
	 * Online Passkey | lipia/validate
	 */
	public $passkey;

	/**
	 * @param string $headoffice
	 * Head Office Shortcode | 123456
	 */
	public $headoffice;

	/**
	 * @param string $shortcode
	 * Business Paybill/Till | 123456
	 */
	public $shortcode;

	/**
	 * @param int $type
	 * Identifier Type   | 1(MSISDN)/2(Till)/4(Paybill)
	 */
	public $type = 4;

	/**
	 * @param string $timeout
	 * Timeout URI   | lipia/reconcile
	 */
	public $timeout;

	/**
	 * @param string $initiator
	 * Timeout URI   | lipia/reconcile
	 */
	public $initiator;

	/**
	 * @param string  $password
	 * Org password | lipia/reconcile
	 */
	public $password;

	/**
	 * @param string $signature
	 * Encryption Signature
	 */
	public $signature;

	/**
	 * @param string $token
	 * Generated/Stored Token
	 */
	public $token;

	/**
	 * @param string  $url
	 * Base API URL
	 */
	private $url = 'https://api.safaricom.co.ke';

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
	 * @return C2B
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
	 *
	 * @param callable $callback - Callback function to process data returned
	 * @return bool/array
	 */
	public function register($callback = null)
	{
		$post_data = array(
			'ShortCode'       => $this->headoffice,
			'ResponseType'    => 'Completed',
			'ConfirmationURL' => home_url("wc-api/lipwa?action=confirm&sign={$this->signature}"),
			'ValidationURL'   => home_url("wc-api/lipwa?action=validate&sign={$this->signature}"),
		);

		$data_string = wp_json_encode($post_data);
		$response    = wp_remote_post(
			$this->url . '/mpesa/c2b/v1/registerurl',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->token,
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
	 *
	 * @param string $phone     - Phone Number to send STK Prompt Request to
	 * @param string $amount    - Amount of money to charge
	 * @param string $reference - Account to show in STK Prompt
	 * @param string $trx_desc   - Transaction Description(optional)
	 * @param string $remark    - Remarks about transaction(optional)
	 * @return array
	 */
	public function request($phone, $amount, $reference, $trx_desc = 'WooCommerce Payment', $remark = 'WooCommerce Payment')
	{
		$phone     = '254' . substr($phone, -9);
		$timestamp = date('YmdHis');
		$password  = base64_encode($this->headoffice . $this->passkey . $timestamp);
		$post_data = array(
			'BusinessShortCode' => $this->headoffice,
			'Password'          => $password,
			'Timestamp'         => $timestamp,
			'TransactionType'   => ($this->type == 4) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
			'Amount'            => round($amount),
			'PartyA'            => $phone,
			'PartyB'            => $this->shortcode,
			'PhoneNumber'       => $phone,
			'CallBackURL'       => home_url("wc-api/lipwa?action=confirm&sign={$this->signature}"),
			'AccountReference'  => $reference,
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
		return is_wp_error($response)
			? array('errorCode' => 1, 'errorMessage' => $response->get_error_message())
			: json_decode($response['body'], true);
	}

	/**
	 * Function to process response data for reconciliation
	 *
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
			: (call_user_func_array($callback, array($response)) ? array('resultCode' => 0, 'resultDesc' => 'Reconciliation successful')
				: array('resultCode' => 1, 'resultDesc' => 'Reconciliation failed'));
	}

	/**
	 * Reverse a Transaction
	 *
	 * @param string $transaction
	 * @param int $amount
	 * @param string|int $receiver
	 * @param string $receiver_type
	 * @param string $remarks
	 * @param string $occassion
	 *
	 * @return array Result
	 */
	public function reverse($transaction, $amount, $receiver = "", $receiver_type = 3, $remarks = "Reversal", $occasion = "Transaction Reversal", $callback = null)
	{
		$phone      = '254' . substr($receiver, -9);
		$env        = $this->env;
		$plain_text = $this->password;
		$public_key = file_get_contents(__DIR__ . "/cert/{$env}/cert.cer");

		openssl_public_encrypt($plain_text, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);

		$password  = base64_encode($encrypted);
		$post_data = array(
			"CommandID"              => "TransactionReversal",
			"Initiator"              => $this->initiator,
			"SecurityCredential"     => $password,
			"TransactionID"          => $transaction,
			"Amount"                 => $amount,
			"ReceiverParty"          => $phone,
			"RecieverIdentifierType" => $receiver_type,
			"ResultURL"              => home_url("wc-api/lipwa?action=result&sign={$this->signature}"),
			"QueueTimeOutURL"        => home_url("wc-api/lipwa?action=timeout&sign={$this->signature}"),
			"Remarks"                => $remarks,
			"Occasion"               => $occasion,
		);

		$data_string = wp_json_encode($post_data);
		$response    = wp_remote_post(
			$this->url . '/mpesa/reversal/v1/request',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->token,
				),
				'body'    => $data_string,
			)
		);

		$result = is_wp_error($response)
			? array('errorCode' => 1, 'errorMessage' => $response->get_error_message())
			: json_decode($response['body'], true);

		return is_null($callback)
			? $result
			: $callback($result);
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
}
