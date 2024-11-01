<?php
/**
 * Plugin Name: Yandex Pay
 * Plugin URI: https://cm-wp.com
 * Description: An eCommerce toolkit that helps you sell anything. Beautifully.
 * Version: 1.1.4
 * Author: ООО «Яндекс»
 * Text Domain: woocommerce-yandex-pay-gateway
 * Domain Path: /i18n/languages/
 * Requires at least: 5.5
 * Requires PHP: 7.0
 *
 * @package WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'YANDEX_PAY_PLUGIN_DIR' ) ) {
	define( 'YANDEX_PAY_PLUGIN_DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'YANDEX_PAY_PLUGIN_BASE' ) ) {
	define( 'YANDEX_PAY_PLUGIN_BASE', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'YANDEX_PAY_PLUGIN_URL' ) ) {
	define( 'YANDEX_PAY_PLUGIN_URL', plugins_url( null, __FILE__ ) );
}

if ( ! defined( 'YANDEX_PAY_PLUGIN_VERSION' ) ) {
	define( 'YANDEX_PAY_PLUGIN_VERSION', "1.1.3" );
}

if ( ! defined( 'YANDEX_PAY_PLUGIN_GATEWAY_ID' ) ) {
	define( 'YANDEX_PAY_PLUGIN_GATEWAY_ID', "yandex-pay" );
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
	$gateways[] = '\YandexPay\WC_Gateway'; // your class name is here

	return $gateways;
} );

add_action( 'template_redirect', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( is_checkout() && ! is_wc_endpoint_url() ) {
		// HERE define the default payment gateway ID
		WC()->session->set( 'chosen_payment_method', YANDEX_PAY_PLUGIN_GATEWAY_ID );
	}
} );

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Add submenu to Yandex pay settings page in the Woocommerce menu
	add_action( 'admin_menu', function () {
		add_submenu_page( 'woocommerce', __( 'Настройки Yandex Pay', 'yandex-go-delivery' ), __( 'Yandex Pay', 'yandex-go-delivery' ), 'manage_woocommerce', 'admin.php?page=wc-settings&tab=checkout&section=' . YANDEX_PAY_PLUGIN_GATEWAY_ID );
	} );

	require_once YANDEX_PAY_PLUGIN_DIR . '/includes/class-wc-ypay-helpers-trait.php';
	require_once YANDEX_PAY_PLUGIN_DIR . '/includes/class-wc-ypay-gateway.php';
	require_once YANDEX_PAY_PLUGIN_DIR . '/includes/class-wc-ypay-refund.php';
	require_once YANDEX_PAY_PLUGIN_DIR . '/includes/class-wc-one-click-checkout.php';
	require_once YANDEX_PAY_PLUGIN_DIR . '/includes/class-wc-yandex-delivery-integration.php';
} );
