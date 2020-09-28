<?php

namespace Osen\Woocommerce\Mpesa;

/**
 * @package MPesa For WooCommerce
 * @subpackage B2C Library
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 2.0.0
 * @since 0.18.01
 */

/**
 *
 */
class B2C
{
	public $env = 'sandbox';
	public $username;
	public $password;
	public $appkey;
	public $appsecret;
	public $passkey;
	public $shortcode;
	public $headoffice;
	public $type = 4;
	public $validate;
	public $confirm;
	public $reconcile;
	public $timeout;

	public function set($config)
	{
		$b2c    = get_option('wc_b2c_settings', []);
		$config =
			array(
				'env'        => $b2c['env'] ?? 'sandbox',
				'appkey'     => $b2c['key'] ?? 'bclwIPkcRqw61yUt',
				'appsecret'  => $b2c['secret'] ?? '9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG',
				'headoffice' => $b2c['headoffice'] ?? '174379',
				'shortcode'  => $b2c['shortcode'] ?? '174379',
				'type'       => $b2c['idtype'] ?? 4,
				'username'   => $b2c['username'] ?? '',
				'password'   => $b2c['password'] ?? '',
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

		return is_wp_error($response)
			? 'Invalid'
			: json_decode($response['body'])->access_token;
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
	public function confirm($callback = null, $data = [])
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
	public function request($phone, $amount, $reference, $trxdesc = '', $remark = '')
	{
		$phone     = str_replace("+", "", $phone);
		$phone     = preg_replace('/^0/', '254', $phone);
		$token     = $this->token();
		$endpoint  = ($this->env == 'live')
			? 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest'
			: 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
		$timestamp = date('YmdHis');
		$env       = $this->env;
		$plaintext = $this->password;
		$publicKey = file_get_contents('cert/' . $env . '/cert.cer');

		openssl_public_encrypt($plaintext, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

		$password = base64_encode($encrypted);

		$curl_post_data = array(
			'InitiatorName'      => $this->username,
			'SecurityCredential' => $password,
			'CommandID'          => ($this->type == 4) ? 'CustomerPayBillOnline' : 'BuyGoodsOnline',
			'Amount'             => round($amount),
			'PartyA'             => $this->shortcode,
			'PartyB'             => $phone,
			'Remarks'            => $remark,
			'QueueTimeOutURL'    => $this->timeout,
			'ResultURL'          => $this->reconcile,
			'Occasion'           => $reference,
		);

		$data_string = json_encode($curl_post_data);

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
		return is_wp_error($response)
			? array('errorCode' => 1, 'errorMessage' => 'Could not connect to Daraja')
			: json_decode($response['body'], true);
	}

	/**
	 *
	 */
	public function reconcile($callback, $data)
	{
		$response = is_null($data) ? json_decode(file_get_contents('php://input'), true) : $data;

		return is_null($callback)
			? array('resultCode' => 0, 'resultDesc' => 'Success')
			: call_user_func_array($callback, array($response));
	}
}
