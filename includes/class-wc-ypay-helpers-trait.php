<?php
/**
 * Base trait
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 02.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helpers {

	public function get_setting( $name, $namespase = YANDEX_PAY_PLUGIN_GATEWAY_ID ) {
		$value = get_option( "woocommerce_" . $namespase . "_settings" );

		if ( empty( $value ) || ! is_array( $value ) || ! isset( $value[ $name ] ) ) {
			return null;
		}

		return $value[ $name ];
	}

	/**
	 * @return bool
	 */
	public function is_testmode() {
		return 'yes' === $this->get_setting( 'testmode' );
	}

	/**
	 * @param int|string $amount
	 *
	 * @return int|string
	 */
	public function normalize_amount( $amount ) {
		$amount = number_format( $amount, 2, ".", "" );

		return str_replace( '.', "", $amount );
	}

	/**
	 * @param string $name
	 *
	 * @return Gateway\Best2pay|Gateway\Payture|Gateway\Rbkmoney|null
	 */
	public function get_payment_gateway( $name = null ) {
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-base-abstract.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-payture.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-rbkmoney.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-best2pay.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-rbs.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-rbs-alfabank.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-rbs-mtsbank.php";
		require_once YANDEX_PAY_PLUGIN_DIR . "/includes/payment-methods/class-wc-ypay-method-rbs-rshb.php";

		$active_gateway = empty( $name ) ? $this->get_setting( 'payment_gateway' ) : $name;

		switch ( $active_gateway ) {
			case 'payture':
				return new \YandexPay\Gateway\Payture( [
					'payture_merchant_password' => "123"
				] );
				break;
			case 'rbkmoney':
				return new \YandexPay\Gateway\Rbkmoney();
				break;
			case 'best2pay':
				return new \YandexPay\Gateway\Best2pay();
				break;
			case 'alfabank':
				return new \YandexPay\Gateway\RBS_Alfabank();
				break;
			case 'rshb':
				return new \YandexPay\Gateway\RBS_Rshbank();
				break;
			case 'mtsbank':
				return new \YandexPay\Gateway\RBS_Mtsbank();
				break;
		}

		return null;
	}

	/**
	 * Метод позволяет проверить включен ли шлюз оплаты при доставке или нет
	 * @return bool
	 */
	public function is_enabled_cod_gateway() {
		$settings = get_option( 'woocommerce_cod_settings', [] );

		if ( ! empty( $settings ) && is_array( $settings ) ) {
			return "yes" === $settings['enabled'];
		}

		return false;
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	public function log( $type, $message ) {
		$logger = wc_get_logger();

		// The `log` method accepts any valid level as its first argument.
		$logger->log( $type, $message, [ 'source' => 'yandex-pay' ] );
	}
}
