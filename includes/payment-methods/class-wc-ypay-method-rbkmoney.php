<?php
/**
 * Class Rbk Money
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

use Http\Client\Exception;

class Rbkmoney extends Base {

	protected $id = "rbkmoney";

	const API_URL = 'https://api.rbk.money/v2/';

	/**
	 * Create invoice settings
	 */
	const CREATE_INVOICE_TEMPLATE_DUE_DATE = 'Y-m-d\TH:i:s\Z';
	const CREATE_INVOICE_DUE_DATE = '+1 days';

	/**
	 * HTTP status code
	 */
	const HTTP_CODE_CREATED_NUMBER = 201;
	const HTTP_CODE_OK_NUMBER = 200;

	/**
	 * Constants for Callback
	 */
	const SIGNATURE = 'HTTP_CONTENT_SIGNATURE';
	const SIGNATURE_PATTERN = "/alg=(\S+);\sdigest=/";

	const EVENT_TYPE = 'eventType';

	// EVENT TYPE INVOICE
	const EVENT_TYPE_INVOICE_CREATED = 'InvoiceCreated';
	const EVENT_TYPE_INVOICE_PAID = 'InvoicePaid';
	const EVENT_TYPE_INVOICE_CANCELLED = 'InvoiceCancelled';
	const EVENT_TYPE_INVOICE_FULFILLED = 'InvoiceFulfilled';

	// EVENT TYPE PAYMENT
	const EVENT_TYPE_PAYMENT_STARTED = 'PaymentStarted';
	const EVENT_TYPE_PAYMENT_PROCESSED = 'PaymentProcessed';
	const EVENT_TYPE_PAYMENT_CAPTURED = 'PaymentCaptured';
	const EVENT_TYPE_PAYMENT_CANCELLED = 'PaymentCancelled';
	const EVENT_TYPE_PAYMENT_FAILED = 'PaymentFailed';

	/**
	 * @param string $invoice_id
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function check_payment_status( $invoice_id ) {
		$response = $this->request( "processing/invoices/{$invoice_id}", $this->get_headers(), [], 'GET' );

		return $response['status'];
	}

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
			$invoice    = $this->create_invoice( $order );
			$invoice_id = $invoice['invoice']['id'];

			$payment = $this->create_payment( $payment_data, $invoice, $order );

			if ( isset( $payment['id'] ) ) {
				$order->update_meta_data( '_ypay_rbkmoney_payment_id', $payment['id'] );
				$order->update_meta_data( '_ypay_rbkmoney_invoice_id', $invoice_id );
			}

			// todo Доработать 3D secure
			//$invoice_events = $this->get_invoice_events( $invoice );
			$invoice_status = $this->check_payment_status( $invoice_id );

			if ( 'paid' === $invoice_status ) {
				$order->add_order_note( 'Hey, your order is paid! Thank you!', true );

				$this->payment_complete( $order );
			} else {
				// Mark as pending
				$order->update_status( 'pending', _x( 'Order received (unpaid)', 'Check payment method', 'woocommerce' ) );

				$this->payment_hold( $order );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		} catch( \Exception $e ) {
			throw new \Exception( $e->getMessage() );
		}
	}

	public function payment_callback() {
		// check payment
		$order_id = (int) $_POST['order_id'];

		/** @var \WC_Abstract_Order $order */
		$order = wc_get_order( $order_id );

		$status = $order->get_status();

		if ( "processing" !== $status ) {
			$invoice_id = $order->get_meta( '_ypay_rbkmoney_invoice_id' );
			if ( empty( $invoice_id ) ) {
				wp_send_json_error( [ 'code' => 'invalid_invoice_id', 'message' => 'Invalid invoice id.' ] );

				return;
			}

			try {
				$gateway        = $this->get_payment_gateway();
				$payment_status = $gateway->check_payment_status( $invoice_id );

				if ( "paid" === $payment_status ) {
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
					$order->payment_complete();
					//$order->delete_meta_data( '_ypay_rbkmoney_invoice_id' );
					$order->save();
					wp_send_json_success( [ 'status' => 'paid' ] );
				}
			} catch( \Exception $e ) {
				wp_send_json_error( [ 'code' => 'http_error', 'message' => $e->getMessage() ] );
			}

			wp_send_json_success( [ 'status' => 'unpaid' ] );
		}

		wp_send_json_success( [ 'status' => 'paid' ] );
	}

	/**
	 * @param \WC_Order $order
	 */
	public function refund( $order, $refund_amount ) {
		$invoice_id = $order->get_meta( '_ypay_rbkmoney_invoice_id' );
		$payment_id = $order->get_meta( '_ypay_rbkmoney_payment_id' );

		$data = [
			"amount"   => (int)$this->normalize_amount( $refund_amount ),
			"currency" => $order->get_currency()
		];

		$response = $this->request( "processing/invoices/{$invoice_id}/payments/{$payment_id}/refunds", $this->get_headers(), $data );

		if ( isset( $response['id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param array $payment_data
	 * @param array $invoice
	 * @param \WC_Order $order
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	protected function create_payment( array $payment_data, array $invoice, $order ) {
		$invoice_id       = $invoice['invoice']['id'];
		$payment_resource = $this->create_payment_resource( $payment_data, $invoice );

		// Get the Customer billing email
		$billing_email = $order->get_billing_email();

		// Get the Customer billing phone
		$billing_phone = $order->get_billing_phone();

		$data = [
			'flow'  => [
				'type' => 'PaymentFlowInstant'
			],
			'payer' => [
				'payerType'        => 'PaymentResourcePayer',
				'paymentToolToken' => $payment_resource['paymentToolToken'],
				'paymentSession'   => $payment_resource['paymentSession'],
				'contactInfo'      => [
					'email'       => $billing_email,
					'phoneNumber' => $billing_phone
				]
			]
		];

		return $this->request( "processing/invoices/{$invoice_id}/payments", $this->get_headers(), $data );
	}

	/**
	 * @param array $payment_data
	 * @param array $invoice
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function create_payment_resource( array $payment_data, array $invoice ) {
		$access_token = $invoice['invoiceAccessToken']['payload'];

		$data = [
			'paymentTool' => [
				'paymentToolType'   => 'TokenizedCardData',
				'provider'          => 'YandexPay',
				'gatewayMerchantID' => $this->get_setting( "rbkmoney_gateway_merchant_id" ),
				'paymentToken'      => $payment_data
			],
			'clientInfo'  => [
				'fingerprint' => $_SERVER['HTTP_USER_AGENT'],
				'ip'          => $this->get_ip()
			]
		];

		$data = $this->request( "processing/payment-resources", $this->get_headers( $access_token ), $data );

		if ( ! empty( $data['paymentToolToken'] ) && ! empty( $data['paymentSession'] ) ) {
			return [
				'paymentToolToken' => $data['paymentToolToken'],
				'paymentSession'   => $data['paymentSession'],
			];
		}

		throw new \Exception( "Request to the RBK API {processing/payment-resources} failed due to an unknown error." );
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	protected function create_invoice( $order ) {
		$shop_id = $this->get_setting( 'rbkmoney_shop_id' );
		$data    = [
			'shopID'      => $shop_id,
			'amount'      => $this->prepare_amount( $order->get_total() ),
			'metadata'    => $this->prepare_metadata( $order ),
			'dueDate'     => $this->prepare_due_date(),
			'currency'    => $order->get_currency(),
			'product'     => __( 'Order № ', 'woocommerce' ) . $order->get_id() . '',
			'cart'        => $this->prepare_cart( $order ),
			'description' => '',
		];

		return $this->request( 'processing/invoices', $this->get_headers(), $data );
	}

	protected function get_invoice_events( $invoice ) {

		$invoice_id   = $invoice['invoice']['id'];
		$access_token = $invoice['invoiceAccessToken']['payload'];

		$data = [
			'limit' => 99
		];

		return $this->request( "processing/invoices/{$invoice_id}/events", $this->get_headers( $access_token ), $data, 'GET' );
	}

	protected function prepare_api_url( $path = '', $query_params = [] ) {
		$url = rtrim( static::API_URL, '/' ) . '/' . $path;
		if ( ! empty( $query_params ) ) {
			$url .= '?' . http_build_query( $query_params );
		}

		return $url;
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	protected function prepare_metadata( $order ) {
		global $wp_version, $woocomerce;

		return [
			'cms'          => 'wordpress',
			'plugin'       => 'woocomerce-yandex-pay-gateway',
			'wordpress'    => $wp_version,
			'woo_commerce' => $order->get_version(),
			'order_id'     => $order->get_id(),
		];
	}

	/**
	 * @param string $url
	 * @param array $headers
	 * @param array $data
	 * @param string $method
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	private function request( $url, array $headers = [], array $data = [], $method = "POST" ) {

		if ( empty( $url ) ) {
			throw new \Exception( 'Unable to make a request to RBK Money API. Require url!' );
		}

		$request_data = [
			'headers'     => $headers,
			'body'        => json_encode( $data ),
			'method'      => $method,
			'data_format' => 'body',
		];

		if ( "GET" === $method ) {
			$request_data['body'] = $data;
			unset( $request_data['data_format'] );
			unset( $headers["Accept"] );
			$request_data['headers'] = $headers;
		}

		$response = wp_remote_request( $this->prepare_api_url( $url ), $request_data );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$data = [];
		if ( ! empty( $response ) ) {
			$data = @json_decode( wp_remote_retrieve_body( $response ), ARRAY_A );
		}
		$http_code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $http_code, [ static::HTTP_CODE_CREATED_NUMBER, static::HTTP_CODE_OK_NUMBER ] ) ) {
			$error_code    = ! empty( $data['code'] ) ? $data['code'] : 0;
			$error_message = ! empty( $data['message'] ) ? $data['message'] : 'Unknown error!';
			throw new \Exception( "Request to RBK Money API failed. Url: {$url}  Error code: {$error_code}. Error message: {$error_message}" );
		}

		return $data;
	}

	protected function get_headers( $access_token = null ) {
		$private_key = ! empty( $access_token ) ? $access_token : $this->get_setting( 'rbkmoney_private_key' );

		$headers["X-Request-ID"]  = uniqid();
		$headers["Authorization"] = "Bearer " . trim( $private_key );
		$headers["Content-type"]  = "application/json; charset=utf-8";
		$headers["Accept"]        = "application/json";

		return $headers;
	}

	/**
	 * @param $amount
	 *
	 * @return int
	 */
	protected function prepare_amount( $amount ) {
		$amount = number_format( $amount, 2, ".", "" );

		return (int) str_replace( '.', "", $amount );
	}

	/**
	 * @return false|string
	 */
	protected function prepare_due_date() {
		return date( static::CREATE_INVOICE_TEMPLATE_DUE_DATE, strtotime( static::CREATE_INVOICE_DUE_DATE ) );
	}

	/**
	 * Prepare cart
	 *
	 * @param $order
	 *
	 * @return array
	 */
	protected function prepare_cart( $order ) {
		$items = $this->prepare_items_for_cart( $order );

		return $items;
	}

	/**
	 * Prepare items for cart
	 *
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	protected function prepare_items_for_cart( $order ) {
		$lines = array();
		$items = $order->get_items();

		foreach ( $items as $product ) {
			$item             = array();
			$item['product']  = $product['name'];
			$item['quantity'] = (int) $product['qty'];

			$amount = ( $product['line_total'] / $product['qty'] ) + ( $product['line_tax'] / $product['qty'] );
			$amount = round( $amount, 2 );
			if ( $amount <= 0 ) {
				continue;
			}
			$item['price'] = $this->prepare_amount( $amount );

			$tax         = $product['line_tax'] / $product['line_total'] * 100;
			$product_tax = (int) $tax;

			if ( ! empty( $product_tax ) ) {

				$tax_rate = $this->get_tax_rate( $product_tax );
				if ( $tax_rate != null ) {
					$tax_mode        = array(
						'type' => 'InvoiceLineTaxVAT',
						'rate' => $tax_rate,
					);
					$item['taxMode'] = $tax_mode;
				}
			}
			$lines[] = $item;
		}

		return $lines;
	}

	/**
	 * Get tax rate
	 *
	 * @param int $rate
	 *
	 * @return null|string
	 */
	protected function get_tax_rate( $rate ) {
		switch ( $rate ) {
			// VAT check at the rate 0%;
			case 0:
				return '0%';
				break;
			// VAT check at the rate 10%;
			case 10:
				return '10%';
				break;
			// VAT check at the rate 18%;
			case 18:
				return '18%';
				break;
			default: # — without VAT;
				return null;
				break;
		}
	}

	/**
	 * Get the IP address
	 *
	 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
	 */
	protected function get_ip() {
		//Get the IP of the person registering
		$ip = $_SERVER['REMOTE_ADDR'];

		// If there's forwarding going on...
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$http_x_headers = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip             = $http_x_headers[0];
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * @param \WC_Order $order
	 */
	public function thankyou_page( $order ) {
		$status = $order->get_status();
		if ( "processing" !== $status ):
			?>
			<h2 class="woocommerce-order-details__title"><?php _e( 'Payment Status', 'woocommerce-yandex-pay-gateway' ) ?></h2>
			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
				
				<li class="woocommerce-order-overview__order order">
					
					<div class="woo-yandex-pay-paid-message" style="display: none;">
						<h3 style="color:green;"><?php _e( 'Your order has been successfully paid!', 'woocommerce-yandex-pay-gateway' ) ?></h3>
					</div>
					
					<div class="woo-yandex-pay-panding-message">
						<div style="display:inline-block;width:70px;">
							<img src="<?php echo YANDEX_PAY_PLUGIN_URL; ?>/assets/img/loader-transparent.gif" alt="">
						</div>
						<div style="display:inline-block;vertical-align: top;line-height:3;">
							<h3><?php _e( 'Please wait... We check the payment!', 'woocommerce-yandex-pay-gateway' ) ?></h3>
						</div>
					</div>
					
					<input type="hidden" id="woo-yandex-pay-order-id" value="<?php echo esc_attr( $order->get_id() ); ?>">
				</li>
			</ul>
		<?php
		endif;
	}
}