<?php
/**
 * Class Best2pay
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 04.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

use PHPMailer\PHPMailer\Exception;

class Best2pay extends Base {

	protected $id = "best2pay";

	public function __construct( array $options = [] ) {
		parent::__construct( $options );

		$this->sector   = $this->get_setting( 'best2pay_sector' );
		$this->password = $this->get_setting( 'best2pay_password' );
	}

	/**
	 * @return mixed|void
	 */
	public function payment_callback() {
		if ( isset( $_REQUEST['action'] ) ) {
			$b2p_order_id     = intval( $_REQUEST["id"] );
			$b2p_operation_id = intval( $_REQUEST["operation"] );
			$order_id         = intval( $_REQUEST['reference'] );
			$order            = wc_get_order( $order_id );
			$get_checkout_url = apply_filters( 'woocommerce_get_checkout_url', wc_get_checkout_url() );
			$log_details      = "Data:\nb2p_order_id:" . $b2p_order_id . "\nb2p_operation_id:" . $b2p_operation_id . "\norder_id:" . $order_id;

			if ( ! $b2p_order_id ) {
				wc_add_notice( __( "The order wasn't paid. Best2pay Order ID is not passed!", 'woocommerce-yandex-pay-gateway' ), 'error' );

				$this->log( 'error', __( "The order wasn't paid. Best2pay Order ID is not passed!", 'woocommerce-yandex-pay-gateway' ) );

				wp_redirect( $get_checkout_url );
				exit();
			}

			if ( "payment_fail" === $_REQUEST['action'] ) {
				$order->update_status( 'failed', __( "The order wasn't paid.", 'woocommerce-yandex-pay-gateway' ) );
				wc_add_notice( __( "The order wasn't paid.", 'woocommerce-yandex-pay-gateway' ), 'error' );

				$this->log( 'error', __( "The order wasn't paid.", 'woocommerce-yandex-pay-gateway' ) . $log_details );
				$this->log( 'error', __( var_export( $_REQUEST, true ), 'woocommerce-yandex-pay-gateway' ) . $log_details );

				wp_redirect( $get_checkout_url );
				exit();
			}

			if ( "payment_complete" === $_REQUEST['action'] ) {
				if ( ! $b2p_operation_id ) {
					if ( $order ) {
						$order->update_status( 'failed', __( "The order wasn't paid.", 'woocommerce-yandex-pay-gateway' ) );
					}

					wc_add_notice( __( "The order wasn't paid. Operation ID is invalid!", 'woocommerce-yandex-pay-gateway' ), 'error' );

					$this->log( 'error', __( "The order wasn't paid. Operation ID is invalid!", 'woocommerce-yandex-pay-gateway' ) . $log_details );

					wp_redirect( $get_checkout_url );
					exit();
				}

				sleep( 2 );

				if ( ! $this->check_payment_status( $b2p_order_id, $b2p_operation_id ) ) {
					wc_add_notice( __( "The order wasn't paid. Payment status is ", 'woocommerce-yandex-pay-gateway' ), 'error' );
					wp_safe_redirect( $get_checkout_url );
					exit();
				}

				$order->update_meta_data( '_ypay_best2pay_order_id', $b2p_order_id );
				$this->payment_complete( $order );

				wp_safe_redirect( $this->get_return_url( $order ) );
				exit();
			}
		}
	}

	/**
	 * @param \WC_Order $order
	 */
	public function refund( $order, $refund_amount ) {
		$refund_amount = $refund_amount * 100;
		$b2p_order_id  = $order->get_meta( '_ypay_best2pay_order_id' );
		$currency      = $this->get_currency_code( $order );
		$signature     = base64_encode( md5( $this->sector . $b2p_order_id . $refund_amount . $currency . $this->password ) );

		$args = [
			'headers' => [
				"Content-type" => "application/x-www-form-urlencoded"
			],
			'body'    => [
				'sector'    => $this->sector,
				'id'        => $b2p_order_id,
				'amount'    => $refund_amount,
				'currency'  => $currency,
				'signature' => $signature
			]
		];

		$response = wp_remote_post( $this->get_api_url( 'Reverse' ), $args );

		if ( ! is_wp_error( $response ) ) {
			$xml = wp_remote_retrieve_body( $response );

			if ( ! $xml ) {
				return false;
			}

			$xml      = simplexml_load_string( $xml );
			$response = @json_decode( json_encode( $xml ) );

			// check payment state
			if ( ( $response->type != 'REVERSE' ) || $response->state != 'APPROVED' ) {
				return false;
			}

			// check server signature
			$tmp_response = json_decode( json_encode( $response ), true );
			unset( $tmp_response["signature"] );
			unset( $tmp_response["ofd_state"] );

			$signature = base64_encode( md5( implode( '', $tmp_response ) . $this->password ) );

			if ( $signature !== $response->signature ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * @param \WC_Order $order
	 */
	public function thankyou_page( $order ) {

	}

	/**
	 * @param string $token
	 * @param string $temp_order_id
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public function payment( array $payment_data, $temp_order_id, $order ) {

		$currency  = $this->get_currency_code( $order );
		$signature = base64_encode( md5( $this->sector . intval( $this->normalize_amount( $order->get_total() ) ) . $currency . $this->password ) );

		$items           = $order->get_items();
		$fiscalPositions = '';
		$fiscalAmount    = 0;
		$KKT             = true;

		if ( $KKT ) {
			foreach ( $items as $item_id => $item ) {
				$item_data       = $item->get_data();
				$fiscalPositions .= $item_data['quantity'] . ';';
				$elementPrice    = $item_data['total'] / $item_data['quantity'];
				$elementPrice    = $elementPrice * 100;
				$fiscalPositions .= $elementPrice . ';';
				$fiscalPositions .= ( $item_data['total_tax'] ) ? : 6 . ';';   // tax
				$fiscalPositions .= str_ireplace( [ ';', '|' ], '', $item_data['name'] ) . '|';

				$fiscalAmount += $item_data['quantity'] * $elementPrice;
			}
			if ( $order->get_shipping_total() ) {
				$fiscalPositions .= '1;' . $this->normalize_amount( $order->get_shipping_total() ) . ';6;Доставка|';
				$fiscalAmount    += $this->normalize_amount( $order->get_shipping_total() );
			}
			$fiscalDiff = abs( $fiscalAmount - intval( $this->normalize_amount( $order->get_total() ) ) );
			if ( $fiscalDiff ) {
				$fiscalPositions .= '1;' . $fiscalDiff . ';6;Скидка;14|';
			}
			$fiscalPositions = substr( $fiscalPositions, 0, - 1 );
		}

		$args         = [
			'body' => [
				'sector'           => $this->sector,
				'reference'        => $order->get_id(),
				'amount'           => intval( $this->normalize_amount( $order->get_total() ) ),
				'fiscal_positions' => $fiscalPositions,
				'description'      => sprintf( __( 'Order #%s', 'woocommerce-yandex-pay-gateway' ), ltrim( $order->get_order_number(), '#' ) ),
				'email'            => $order->get_billing_email(),
				'notify_customer'  => 0,
				'currency'         => $currency,
				'mode'             => 1,
				'url'              => $this->get_payment_complete_url(),
				'failurl'          => $this->get_payment_failurl_url(),
				'signature'        => $signature
			]
		];
		$remote_post  = wp_remote_post( $this->get_api_url( 'Register' ), $args );
		$remote_post  = ( isset( $remote_post['body'] ) ) ? $remote_post['body'] : $remote_post;
		$b2p_order_id = ( $remote_post ) ? $remote_post : null;

		$this->payment_hold( $order );

		$signature = base64_encode( md5( $this->sector . $b2p_order_id . $this->password ) );

		$yandex_cryptogram = $payment_data['token'];
		$redirect_url      = $this->get_api_url( "Purchase" ) . "?action=pay&sector={$this->sector}&id={$b2p_order_id}&signature={$signature}&yandexCryptogram={$yandex_cryptogram}";

		$this->log( 'info', $redirect_url );

		return [
			'result'   => 'success',
			'redirect' => $redirect_url
		];
	}

	/**
	 * @return string
	 */
	protected function get_api_url( $endpoint ) {
		return "https://" . ( $this->testmode ? "test" : "pay" ) . ".best2pay.net/webapi/{$endpoint}";
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

	/**
	 * @param int $b2p_order_id
	 * @param int $b2p_operation_id
	 *
	 * @return bool
	 */
	protected function check_payment_status( $b2p_order_id, $b2p_operation_id ) {
		// check payment operation state
		$signature = base64_encode( md5( $this->sector . $b2p_order_id . $b2p_operation_id . $this->password ) );

		$args = [
			'headers' => [
				"Content-type" => "application/x-www-form-urlencoded"
			],
			'body'    => [
				'sector'    => $this->sector,
				'id'        => $b2p_order_id,
				'operation' => $b2p_operation_id,
				'signature' => $signature
			]
		];

		$response = wp_remote_post( $this->get_api_url( 'Operation' ), $args );

		if ( ! is_wp_error( $response ) ) {
			$xml = wp_remote_retrieve_body( $response );
			if ( ! $xml ) {
				return false;
			}
			$xml      = simplexml_load_string( $xml );
			$response = @json_decode( json_encode( $xml ) );

			// check payment state
			if ( ( $response->type != 'PURCHASE' && $response->type != 'EPAYMENT' ) || $response->state != 'APPROVED' ) {
				return false;
			}

			// check server signature
			$tmp_response = json_decode( json_encode( $response ), true );
			unset( $tmp_response["signature"] );
			unset( $tmp_response["ofd_state"] );

			$signature = base64_encode( md5( implode( '', $tmp_response ) . $this->password ) );

			if ( $signature !== $response->signature ) {
				//$order->update_status( 'fail', $response->message );

				return false;
			}

			return true;
		}

		return false;
	}

	protected function get_currency_code( $order ) {
		switch ( $order->get_currency() ) {
			case 'EUR':
				$currency = '978';
				break;
			case 'USD':
				$currency = '840';
				break;
			default:
				$currency = '643';
				break;
		}

		return $currency;
	}
}