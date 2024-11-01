<?php
/**
 * Class Rbk Money
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

use PHPMailer\PHPMailer\Exception;

class RBS extends Base {

	protected $id;
	protected $api_url;
	protected $sandbox_api_url;
	protected $login;
	protected $password;

	public $currency_codes = [
		'USD' => '840',
		'UAH' => '980',
		'RUB' => '810',
		'RON' => '946',
		'KZT' => '398',
		'KGS' => '417',
		'JPY' => '392',
		'GBR' => '826',
		'EUR' => '978',
		'CNY' => '156',
		'BYR' => '974',
		'BYN' => '933'
	];

	/**
	 * @param array $payment_data
	 * @param string $temp_order_id
	 * @param \WC_Order $order
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function payment( array $payment_data, $temp_order_id, $order ) {
		try {
			$login    = $this->login;
			$password = $this->password;

			$cart        = WC()->cart->get_cart();
			$order_total = 0;
			foreach ( $cart as $cart_item ) {
				/** @var \WC_Product_Simple $product */
				$product = $cart_item['data'];
				if ( ! empty( $product ) ) {
					$order_total += (float) $cart_item['line_total'];
				}
			}

			$amount = $order_total * 100;

			if ( ! $this->testmode ) {
				$action_adr = $this->api_url;
			}
			else {
				$action_adr = $this->sandbox_api_url;
			}

			$order_data = $order->get_data();

			$language = substr( get_bloginfo( "language" ), 0, 2 );
			//fix Gate bug locale2country
			switch ( $language ) {
				case  ( 'uk' ):
					$language = 'ua';
					break;
				case ( 'be' ):
					$language = 'by';
					break;
			}

			$currency = $this->currency_codes[ get_woocommerce_currency() ];

			// prepare args array
			$args = [
				'userName'    => $login,
				'password'    => $password,
				'orderNumber' => $order->get_id() . '_' . time(),
				'amount'      => $amount,
				'language'    => $language,
				'returnUrl'   => $this->get_payment_complete_url(),
				'failUrl'     => $this->get_payment_failurl_url(),
				//'currency'    => $this->currency_codes[ get_woocommerce_currency() ],
				'jsonParams'  => @json_encode( [
					'CMS:'             => 'Wordpress ' . get_bloginfo( 'version' ),
					'Module-Version: ' => YANDEX_PAY_PLUGIN_VERSION,
					'email'            => $order_data['billing']['email'],
					'phone'            => $order_data['billing']['phone'],
				] ),
			];

			$request_data = [
				'headers' => [
					'CMS'            => 'Wordpress ' . get_bloginfo( 'version' ),
					'Module-Version' => YANDEX_PAY_PLUGIN_VERSION
				],
				'body'    => $args,
				'method'  => 'POST'
			];

			$response = wp_remote_request( $action_adr . 'rest/registerPreAuth.do', $request_data );

			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message() );
			}

			if ( ! empty( $response ) ) {
				$data = @json_decode( wp_remote_retrieve_body( $response ), ARRAY_A );

				$order->update_meta_data( '_ypay_rbs_' . $this->id . '_order_id', $data['orderId'] );

				$params2 = [
					'username'     => $login,
					'password'     => $password,
					'orderId'      => $data['orderId'],
					'paymentToken' => $payment_data['token']
				];

				$response = wp_remote_request( $action_adr . 'yandex/payment.do', [
					'headers'     => [
						'CMS'            => 'Wordpress ' . get_bloginfo( 'version' ),
						'Module-Version' => YANDEX_PAY_PLUGIN_VERSION,
						"Content-type"   => "application/json; charset=utf-8",
						"Accept"         => "application/json"
					],
					'body'        => @json_encode( $params2 ),
					'method'      => 'POST',
					'data_format' => 'body'
				] );

				if ( is_wp_error( $response ) ) {
					throw new \Exception( $response->get_error_message() );
				}

				$daaaata = @json_decode( wp_remote_retrieve_body( $response ), ARRAY_A );

				if ( ! isset( $daaaata['success'] ) || ( isset( $daaaata['errorCode'] ) && 0 !== $daaaata['errorCode'] ) ) {
					if ( isset( $data['errorMessage'] ) ) {
						throw new \Exception( __( 'The payment.do request failed with an error: ', 'woocommerce-yandex-pay-gateway' ) . $data['errorMessage'] );
					}
					else {
						throw new \Exception( __( 'The payment.do request failed with an error: Unknown error.', 'woocommerce-yandex-pay-gateway' ) );
					}
				}

				$redirect_url = '';
				if ( isset( $daaaata['data']['acsUrl'] ) ) {
					$redirect_url = $action_adr . "acsRedirect.do?orderId=" . $data['orderId'] . '&woocommerce_order_id=' . $order->get_id();
					// Mark as pending
					$order->update_status( 'pending', _x( 'Order received (unpaid)', 'Check payment method', 'woocommerce' ) );
					$this->payment_hold( $order );
				}
				else if ( isset( $daaaata['data']['redirectUrl'] ) ) {
					$redirect_url = $daaaata['data']['redirectUrl'];
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
					$this->payment_complete( $order );
				}

				return [
					'result'   => 'success',
					'redirect' => $redirect_url
				];
			}

			return [
				'result'   => 'error',
				'redirect' => ''
			];
		} catch( \Exception $e ) {
			throw new \Exception( $e->getMessage() );
		}
	}

	public function payment_callback() {
		global $wpdb;

		$get_checkout_url = apply_filters( 'woocommerce_get_checkout_url', wc_get_checkout_url() );
		$action_adr       = ! $this->testmode ? $this->api_url : $this->sandbox_api_url;

		// check payment
		$order_id = sanitize_key( $_GET['orderId'] );

		$params   = [
			'userName' => $this->login,
			'password' => $this->password,
			'orderId'  => $order_id,
			'amount'   => 0
		];
		$response = wp_remote_request( $action_adr . 'rest/deposit.do', [
			'headers' => [
				'CMS'            => 'Wordpress ' . get_bloginfo( 'version' ),
				'Module-Version' => YANDEX_PAY_PLUGIN_VERSION,
			],
			'body'    => $params,
			'method'  => 'POST'
		] );

		if ( is_wp_error( $response ) ) {
			wc_add_notice( __( 'The deposit.do request failed with an error: ', 'woocommerce-yandex-pay-gateway' ), $response->get_error_message(), 'error' );
			wp_redirect( $get_checkout_url );
			exit();
		}

		$data = @json_decode( wp_remote_retrieve_body( $response ), ARRAY_A );

		if ( isset( $data['errorCode'] ) && 0 !== (int) $data['errorCode'] ) {
			wc_add_notice( __( 'The deposit.do request failed with an error: ', 'woocommerce-yandex-pay-gateway' ) . $data['errorMessage'], 'error' );
			wp_redirect( $get_checkout_url );
			exit();
		}

		$wc_order_id = $wpdb->get_var( $wpdb->prepare( "SELECT 
			post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key='%s' 
			AND meta_value='%s'", "_ypay_rbs_{$this->id}_order_id", $order_id ) );

		if ( $wc_order_id ) {
			$order = wc_get_order( $wc_order_id );
			$this->payment_complete( $order );
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit();
		}

		wc_add_notice( __( 'Unable to complete payment. Unknown error!', 'woocommerce-yandex-pay-gateway' ), $response->get_error_message(), 'error' );
		wp_redirect( $get_checkout_url );
		exit();
	}

	public function refund( $order, $refund_amount ) {
		//$refund_amount = $refund_amount * 100;
		$action_adr   = ! $this->testmode ? $this->api_url : $this->sandbox_api_url;
		$rbs_order_id = $order->get_meta( "_ypay_rbs_{$this->id}_order_id" );

		$params   = [
			'userName' => $this->login,
			'password' => $this->password,
			'orderId'  => $rbs_order_id,
			'amount'   => (float) $refund_amount,
			//'currency' => 810
		];
		$response = wp_remote_request( $action_adr . 'rest/refund.do', [
			'headers' => [
				'CMS'            => 'Wordpress ' . get_bloginfo( 'version' ),
				'Module-Version' => YANDEX_PAY_PLUGIN_VERSION,
			],
			'body'    => $params,
			'method'  => 'POST'
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = @json_decode( wp_remote_retrieve_body( $response ), ARRAY_A );

		if ( isset( $data['errorCode'] ) && 0 !== (int) $data['errorCode'] ) {
			throw new Exception( __( 'The deposit.do request failed with an error: ', 'woocommerce-yandex-pay-gateway' ) . $data['errorMessage'] );
		}

		return true;
	}

	public function thankyou_page( $order ) {

	}

	/**
	 * @return string
	 */
	protected function get_payment_complete_url() {
		return $this->get_callback_url( [
			'action' => 'payment_complete'
		] );
	}

	/**
	 * @return string
	 */
	protected function get_payment_failurl_url() {
		return $this->get_callback_url( [
			'action' => 'payment_fail'
		] );
	}
}