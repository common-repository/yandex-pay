<?php
/**
 * Refund class
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 02.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay;

defined( 'ABSPATH' ) || exit;

class One_Click_Checkout {

	use Helpers;

	private $is_enable_in_cart;
	private $is_enable_in_product_card;

	public function __construct() {
		$this->is_enable_in_cart         = "yes" === $this->get_setting( 'one_click_checkout_in_cart' );
		$this->is_enable_in_product_card = "yes" === $this->get_setting( 'one_click_checkout_in_product_card' );
		$this->is_enable_in_checkout     = "yes" === $this->get_setting( 'one_click_checkout_in_checkout' );

		if ( $this->is_enable_in_product_card ) {
			add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'yandex_pay_button_contanier' ] );
		}
		if ( $this->is_enable_in_cart ) {
			add_action( 'woocommerce_proceed_to_checkout', [ $this, 'yandex_pay_button_contanier' ] );
		}
		if ( $this->is_enable_in_checkout ) {
			add_action( 'woocommerce_checkout_billing', [ $this, 'yandex_pay_button_contanier' ] );
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_head', [ $this, 'print_styles' ] );

		if ( $this->is_enable_in_cart || $this->is_enable_in_product_card ) {
			add_action( 'wc_ajax_yandexpay-add-to-cart', function () {
				if ( ! isset( $_POST['product_id'] ) ) {
					return;
				}

				include_once WC_ABSPATH . 'includes/class-wc-ajax.php';

				$product_id = (int) $_POST['product_id'];

				WC()->cart->empty_cart();

				add_action( 'woocommerce_ajax_added_to_cart', function () use ( $product_id ) {
					$order = $this->get_order();
					wp_send_json( $order );
				} );

				\WC_AJAX::add_to_cart();
			} );
			add_action( 'wc_ajax_yandexpay-get-shipping-methods', function () {
				$shipping_address = $_REQUEST['shipping_address'];
				wp_send_json( $this->shipping( $shipping_address ) );
			} );
		}
	}

	public function is_enabled_button() {
		return (is_cart() && $this->is_enable_in_cart ) || (is_product() && $this->is_enable_in_product_card) || (is_checkout() && $this->is_enable_in_checkout );
	}

	public function enqueue_scripts() {
		if ( $this->is_enabled_button() ) {
			wp_register_script( 'woocommerce_yandex_pay_one_click_checkout', YANDEX_PAY_PLUGIN_URL . '/assets/js/one-click-checkout.js', [
				'jquery'
			] );

			$gateway = $this->get_setting( 'payment_gateway' );

			if ( 'alfabank' === $gateway || 'mtsbank' === $gateway || 'rshb' === $gateway ) {
				$gateway_merchant_id = str_replace( '-api', "", $this->get_setting( "{$gateway}_login" ) );

				if ( 'mtsbank' === $gateway || 'rshb' === $gateway ) {
					$gateway = 'rbs';
				}
			}
			else {
				$gateway_merchant_id = $this->get_setting( "{$gateway}_gateway_merchant_id" );
			}

			$button_width = "Auto";

			if ( ( is_cart() && "yes" === $this->get_setting( 'button_fullwidth_in_cart' ) ) || ( is_checkout() && "yes" === $this->get_setting( 'button_fullwidth_in_product_in_checkout' ) ) || ( is_product() && "yes" === $this->get_setting( 'button_fullwidth_in_product_card' ) ) ) {
				$button_width = "Max";
			}

			wp_localize_script( 'woocommerce_yandex_pay_one_click_checkout', 'yandex_pay_checkout_params', [
				'test_mode'           => (int) $this->is_testmode(),
				'checkout_nonce'      => wp_create_nonce( 'woocommerce-process_checkout' ),
				'current_user_id'     => get_current_user_id(),
				'merchant_id'         => $this->get_setting( 'merchant_id' ),
				'merchant_name'       => $this->get_setting( 'merchant_name' ),
				'gateway'             => $gateway,
				'gateway_merchant_id' => $gateway_merchant_id,
				'button_style'        => $this->get_setting( 'button_style' ),
				'button_width'        => $button_width,
				'is_cod_enabled'      => (int) ( "yes" === $this->get_setting( 'one_click_checkout_cod' ) && $this->is_enabled_cod_gateway() ),
				'order'               => $this->get_order()
			] );

			wp_enqueue_script( 'woocommerce_yandex_pay_one_click_checkout' );
		}
	}

	public function print_styles() {
		if ( $this->is_enabled_button() ) {
			$cart_button_padding     = $this->get_setting( 'cart_button_padding' );
			$product_button_padding  = $this->get_setting( 'product_button_padding' );
			$checkout_button_padding = $this->get_setting( 'checkout_button_padding' );

			?>
			<style>
				<?php if(!empty($cart_button_padding) && is_array($cart_button_padding)): ?>
                #woo-ypay-checkout-payment-button-cart {
                    padding: <?php echo (int)$cart_button_padding['top']; ?>px <?php echo (int)$cart_button_padding['right']; ?>px <?php echo (int)$cart_button_padding['bottom']; ?>px <?php echo (int)$cart_button_padding['left']; ?>px;
                }

				<?php endif; ?>
				<?php if(!empty($product_button_padding) && is_array($product_button_padding)): ?>
                #woo-ypay-checkout-payment-button-product {
                    padding: <?php echo (int)$product_button_padding['top']; ?>px <?php echo (int)$product_button_padding['right']; ?>px <?php echo (int)$product_button_padding['bottom']; ?>px <?php echo (int)$product_button_padding['left']; ?>px;
                }

				<?php endif; ?>
				<?php if(!empty($checkout_button_padding) && is_array($checkout_button_padding)): ?>
                #woo-ypay-checkout-payment-button-checkout {
                    padding: <?php echo (int)$checkout_button_padding['top']; ?>px <?php echo (int)$checkout_button_padding['right']; ?>px <?php echo (int)$checkout_button_padding['bottom']; ?>px <?php echo (int)$product_button_padding['left']; ?>px;
                }

				<?php endif; ?>
			</style>
			<?php
		}
	}

	public function shipping( $shipping_address ) {

		global $woocommerce;

		$active_methods = [];

		// Fake product number to get a filled card....
		//$woocommerce->cart->add_to_cart('1');

		$shipping_packages = $this->get_shipping_packages( $shipping_address );

		WC()->shipping->calculate_shipping( $shipping_packages );
		$shipping_methods = WC()->shipping->packages;

		foreach ( $shipping_methods[0]['rates'] as $id => $shipping_method ) {
			$params = [
				'id'       => $id,
				'category' => $shipping_method->method_id,
				'provider' => $shipping_method->method_id,
				'label'    => $shipping_method->label,
				'amount'   => number_format( $shipping_method->cost, 2, '.', '' )
			];

			if ( "yandex-go-delivery" === $id ) {
				$yandex_go_settings = get_option( "woocommerce_yandex-go-delivery_settings" );
				if ( ! empty( $yandex_go_settings ) && isset( $yandex_go_settings['payment_method_label'] ) && "express_delivery" === $yandex_go_settings['payment_method_label'] ) {
					$params['provider'] = 'YANDEX';
					$params['category'] = 'express';
				}
			}

			$active_methods[] = $params;
		}

		return $active_methods;
	}


	public function get_shipping_packages( $shipping_address ) {

		if ( empty( $shipping_address['country'] ) ) {
			return [];
		}

		$contents      = WC()->cart->cart_contents;
		$contents_cost = array_sum( wp_list_pluck( $contents, 'line_total' ) );

		// Packages array for storing 'carts'
		$packages                                = [];
		$packages[0]['contents']                 = $contents;
		$packages[0]['contents_cost']            = $contents_cost;
		$packages[0]['applied_coupons']          = WC()->session->applied_coupon;
		$packages[0]['destination']['country']   = $shipping_address['country'];
		$packages[0]['destination']['state']     = $shipping_address['state'];
		$packages[0]['destination']['postcode']  = $shipping_address['postcode'];
		$packages[0]['destination']['city']      = $shipping_address['city'];
		$packages[0]['destination']['address']   = $shipping_address['address'];
		$packages[0]['destination']['address_2'] = '';

		return apply_filters( 'woocommerce_cart_shipping_packages', $packages );
	}

	public function get_order() {
		$order       = [
			'id'    => wp_generate_uuid4(),
			'items' => []
		];
		$order_total = 0;
		$cart        = WC()->cart->get_cart();
		foreach ( $cart as $cart_item ) {
			/** @var \WC_Product_Simple $product */
			$product = $cart_item['data'];
			if ( ! empty( $product ) ) {
				$order['items'][] = [
					'label'  => $product->get_title(),
					'amount' => (string) number_format( $cart_item['line_total'], 2, ".", "" )
				];

				$order_total += (float) $cart_item['line_total'];
			}
		}

		$order['total']['amount'] = (string) number_format( $order_total, 2, ".", "" );

		return $order;
	}

	public function yandex_pay_button_contanier() {
		global $product;

		$product_attrs = is_product() && ! empty( $product ) ? 'data-product-id="' . esc_attr( $product->get_id() ) . '"' : '';
		if ( is_product() ) {
			$id = 'product';
		}
		else if ( is_checkout() ) {
			$id = 'checkout';
		}
		else if ( is_cart() ) {
			$id = 'cart';
		}

		?>
		<div id="woo-ypay-checkout-payment-button-<?php
		echo $id; ?>" class="" <?php
		echo $product_attrs; ?>></div>
		<?php
	}
}

new \YandexPay\One_Click_Checkout();