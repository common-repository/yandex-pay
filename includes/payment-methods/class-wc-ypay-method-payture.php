<?php
/**
 * Class Payture
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

use YandexPay\Helpers;

class Payture extends Base {

	protected $id = "payture";

	/**
	 * @return string
	 */
	public function get_api_url( $endpoint ) {
		$remote_host = $this->testmode ? "sandbox3" : $this->get_setting( "payture_merchant_host" );

		return "https://{$remote_host}.payture.com/api/{$endpoint}";
	}

	public function check_payment_status( $invoice_id ) {

		// nothing
	}

	/**
	 * @param array $payment_data
	 * @param string $temp_order_id
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public function payment( array $payment_data, $temp_order_id, $order ) {

		$token       = $payment_data['token'];
		$order_total = number_format( $order->get_total(), 2, ".", "" );

		$params = [
			'timeout' => 10,
			'headers' => [
				'Content-type: application/x-www-form-urlencoded'
			],
			'body'    => [
				'Key'      => $this->get_setting( 'payture_merchant_key' ),
				'PayToken' => $token,
				'OrderId'  => $order->get_id(),
				'Checksum' => true,
				'Amount'   => str_replace( '.', "", $order_total )
			]
		];

		$response = wp_remote_post( $this->get_api_url( "MobilePay" ), $params );

		$this->log( 'info', '[Payture Gateway] Request to: ' . $this->get_api_url( "MobilePay" ) );
		$this->log( 'info', '[Payture Gateway] Params: ' . var_export( $params, true ) );
		$this->log( 'info', '[Payture Gateway] Response: ' . var_export( $response, true ) );

		$error_base_text = "[Payture Gateway] The payment cannot be made using the Yandex Pay payment method.";

		if ( ! is_wp_error( $response ) ) {

			$xml = simplexml_load_string( $response['body'] );

			if ( ! $xml ) {
				$this->log( 'error', $error_base_text . 'Unknown error on the side of payment provider Payture. Please try again.' );
				wc_add_notice( $error_base_text . 'Unknown error on the side of payment provider Payture. Please try again.', 'error' );

				return [
					'result' => 'error'
				];
			}

			$attrs      = (array) $xml->attributes();
			$attributes = $attrs['@attributes'];

			if ( ! empty( $attributes['Success'] ) ) {
				if ( "False" === $attributes['Success'] ) {
					$error_code = ! empty( $attributes['ErrCode'] ) ? $attributes['ErrCode'] : '';

					$this->log( 'error', sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ) );
					wc_add_notice( sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ), 'error' );

					return [
						'result' => 'error'
					];
				}
				else if ( "3DS" === $attributes['Success'] ) {

					$callback_url = urlencode( site_url( "?wc-api=" . YANDEX_PAY_PLUGIN_GATEWAY_ID . '_callback&gateway=payture&action=3ds&order_id=' . $order->get_id() ) );

					$redirect_url = site_url( "?wc-api=" . YANDEX_PAY_PLUGIN_GATEWAY_ID . '_callback&gateway=payture&action=3ds_form' );
					$redirect_url .= "&TermUrl={$callback_url}&MD=" . $attributes['ThreeDSKey'] . "&PaReq=" . base64_encode( $attributes['PaReq'] ) . "&ACSUrl=" . urlencode( $attributes['ACSUrl'] );

					$this->log( 'info', "[Payture Gateway] 3DS:" . $redirect_url );
					$this->log( 'info', "[Payture Gateway] Redirect to:" . $redirect_url );

					return [
						'result'   => 'success',
						'redirect' => $redirect_url
					];
				}

				$this->payment_complete( $order );

				$this->log( 'info', "[Payture Gateway] Payment complete! Redirect to " . $this->get_return_url( $order ) );

				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				];
			}
		}

		$this->log( 'error', $error_base_text . 'Payment request to Payture failed! Error message: ' . $response->get_error_message() );
		wc_add_notice( $error_base_text . 'Payment request to Payture failed: ' . $response->get_error_message(), 'error' );

		exit;
	}

	/**
	 * @param int $order_id
	 * @param string $pares
	 */
	protected function pay_3ds( $order_id, $pares ) {

		$order = wc_get_order( $order_id );

		$params = [
			'headers' => [
				'Content-type: application/x-www-form-urlencoded'
			],
			'body'    => [
				'Key'     => $this->get_setting( 'payture_merchant_key' ),
				'OrderId' => $order_id,
				'PaRes'   => $pares
			]
		];

		$response = wp_remote_post( $this->get_api_url( "Pay3DS" ), $params );

		$this->log( 'info', '[Payture Gateway] Request to: ' . $this->get_api_url( "Pay3DS" ) );
		$this->log( 'info', '[Payture Gateway] Params: ' . var_export( $params, true ) );
		$this->log( 'info', '[Payture Gateway] Response: ' . var_export( $response, true ) );

		if ( ! is_wp_error( $response ) ) {
			$error_base_text = "[Payture Gateway] The payment cannot be made using the Yandex Pay payment method.";
			$xml             = simplexml_load_string( $response['body'] );

			if ( ! $xml ) {
				$error_code = ! empty( $attributes['ErrCode'] ) ? $attributes['ErrCode'] : '';

				$this->log( 'error', sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ) );
				wc_add_notice( sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ), 'error' );

				wp_safe_redirect( $order->get_checkout_payment_url() );
				exit;
			}

			$attrs      = (array) $xml->attributes();
			$attributes = $attrs['@attributes'];

			if ( ! empty( $attributes['Success'] ) ) {
				if ( "False" === $attributes['Success'] ) {
					$error_code = ! empty( $attributes['ErrCode'] ) ? $attributes['ErrCode'] : '';

					$this->log( 'error', sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ) );
					wc_add_notice( sprintf( $error_base_text . 'Payture payment provider returned an error. Error code: %s', $error_code ), 'error' );

					wp_safe_redirect( $order->get_checkout_payment_url() );
					exit;
				}

				$this->payment_complete( $order );

				$this->log( 'info', "[Payture Gateway] Payment complete! Redirect to " . $this->get_return_url( $order ) );

				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}
		}

		$this->log( 'error', '3DS payment request via Payture failed! Error message:' . $response->get_error_message() );
	}

	public function payment_callback() {
		if ( isset( $_REQUEST['action'] ) && "3ds_form" === $_REQUEST['action'] ) {
			$acsurl     = sanitize_text_field( urldecode( $_REQUEST['ACSUrl'] ) );
			$termurl    = sanitize_text_field( urldecode( $_REQUEST['TermUrl'] ) );
			$threedskey = sanitize_text_field( $_REQUEST['MD'] );
			$pareq      = sanitize_text_field( $_REQUEST['PaReq'] );
			?>
			<body onload="document.form.submit()">
			<form name="form" action="<?php
			echo esc_url( $acsurl ); ?>" method="post">
				<input type="hidden" name="TermUrl" value="<?php
				echo esc_url( $termurl ); ?>">
				<input type="hidden" name="MD" value="<?php
				echo esc_attr( $threedskey ); ?>">
				<input type="hidden" name="PaReq" value="<?php
				echo esc_attr( base64_decode( $pareq ) ); ?>">
			</form>
			</body>
			<?php
			exit;
		}

		if ( isset( $_REQUEST['action'] ) && "3ds" === $_REQUEST['action'] ) {
			$order_id = sanitize_text_field( $_REQUEST['order_id'] );
			$pares    = sanitize_text_field( $_REQUEST['PaRes'] );

			$this->pay_3ds( $order_id, $pares );
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function refund( $order, $refund_amount ) {
		$params = [
			'headers' => [
				'Content-type: application/x-www-form-urlencoded'
			],
			'body'    => [
				'Key'      => $this->get_setting( 'payture_merchant_key' ),
				'Password' => $this->get_setting( 'payture_merchant_password' ),
				'OrderId'  => $order->get_id(),
				'Amount'   => $this->normalize_amount( $refund_amount )
			]
		];

		$response = wp_remote_post( $this->get_api_url( "Refund" ), $params );

		if ( ! is_wp_error( $response ) ) {
			$xml = simplexml_load_string( $response['body'] );

			if ( ! $xml ) {
				return false;
			}

			$attrs      = (array) $xml->attributes();
			$attributes = $attrs['@attributes'];

			if ( ! empty( $attributes['Success'] ) ) {
				if ( "False" === $attributes['Success'] ) {

					$this->log( 'error', 'Refund request via Payture failed!' );
					$this->log( 'info', 'Request to: ' . $this->get_api_url( "Refund" ) );
					$this->log( 'info', var_export( $params, true ) );

					return false;
				}

				return true;
			}
		}

		$this->log( 'error', 'Refund request via Payture failed! Error message:' . $response->get_error_message() );
		$this->log( 'info', 'Request to: ' . $this->get_api_url( "Refund" ) );
		$this->log( 'info', var_export( $params, true ) );

		return false;
	}

	/**
	 * @param \WC_Order $order
	 */
	public function thankyou_page( $order ) {

	}
}