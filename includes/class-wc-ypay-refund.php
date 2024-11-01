<?php
/**
 * Refund class
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 02.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay;

defined( 'ABSPATH' ) || exit;

class Refund {

	use Helpers;

	public function __construct() {
		add_action( 'woocommerce_order_refunded', [ $this, 'refund' ], 10, 2 );
	}

	public function refund( $order_id, $refund_id ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();

		if ( $payment_method === YANDEX_PAY_PLUGIN_GATEWAY_ID ) {
			$payment_gateway = $order->get_meta( '_ypay_payment_gateway' );

			if ( empty( $payment_gateway ) ) {
				return;
			}

			$order_refunds = $order->get_refunds();

			foreach ( $order_refunds as $refund ) {
				if ( $refund_id === $refund->id ) {
					$ammount = $refund->data['amount'];

					$gateway = $this->get_payment_gateway( $payment_gateway );
					$gateway->refund( $order, $ammount );
				}
			}

			$ddd = '';
		}
	}
}

new \YandexPay\Refund();