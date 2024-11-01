<?php
/**
 * Class Best2pay
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 04.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

use PHPMailer\PHPMailer\Exception;
use YandexPay\Helpers;

abstract class Base {

	protected $id;

	use Helpers;

	protected $testmode;

	/**
	 * @param array $options
	 * @param bool $testmode
	 */
	public function __construct( array $options = [] ) {
		$this->testmode = $this->is_testmode();
		$this->options  = $options;

		if ( empty( $this->id ) ) {
			throw new Exception( 'You must set the gateway ID.' );
		}
	}

	/**
	 * @param string $name
	 *
	 * @return mixed|null
	 */
	public function get_option( $name ) {
		return ! empty( $this->options[ $name ] ) ? $this->options[ $name ] : null;
	}

	public function get_callback_url( $params ) {
		return add_query_arg( $params, site_url( "?wc-api=" . YANDEX_PAY_PLUGIN_GATEWAY_ID . '_callback&gateway=' . $this->id ) );
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param \WC_Order|null $order Order object.
	 *
	 * @return string
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	/**
	 * @param $payment
	 * @param \WC_Order $order
	 * @param string $temp_order_id
	 */
	protected function payment_complete( $order ) {
		global $woocommerce;

		$order->update_meta_data( '_ypay_payment_gateway', $this->id );
		//$order->update_meta_data( '_ypay_temp_order_id', $temp_order_id );

		$order->add_order_note( 'Hey, your order is paid! Thank you!', true );

		$order->payment_complete();
		wc_reduce_stock_levels( $order->get_id() );
		$woocommerce->cart->empty_cart();
	}

	/**
	 * @param $payment
	 * @param \WC_Order $order
	 * @param string $temp_order_id
	 */
	protected function payment_hold( $order ) {
		global $woocommerce;

		$order->update_status( 'on-hold' );

		$order->update_meta_data( '_ypay_payment_gateway', $this->id );
		//$order->update_meta_data( '_ypay_temp_order_id', $temp_order_id );

		$order->add_order_note( 'The order has not been paid yet, we are waiting for confirmation of payment from the payment provider!', true );
		$order->save();
	}

	/**
	 * @param array $payment_data
	 * @param string $temp_order_id
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	abstract public function payment( array $payment_data, $temp_order_id, $order );

	/**
	 * @return mixed
	 */
	abstract public function payment_callback();

	/**
	 * @param \WC_Order $order
	 */
	abstract public function refund( $order, $refund_amount );

	/**
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	abstract public function thankyou_page( $order );
}