<?php
/**
 * Refund class
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 02.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay;

use WBCR\Delivery\Yandex\Client;
use WBCR\Delivery\Yandex\Helper;
use WBCR\Delivery\Yandex\Yandex;

defined( 'ABSPATH' ) || exit;

class Yandex_Delivery_Integration {

	use Helpers;

	private $token;

	private $shop_id;

	private $warehouse_id;

	private $adjust_delivery_date;

	public function __construct() {
		return;

		require_once WDYD_PLUGIN_DIR . '/libs/delivery/base/class-delivery.php';
		require_once WDYD_PLUGIN_DIR . '/libs/delivery/base/class-checkout.php';
		require_once WDYD_PLUGIN_DIR . '/libs/delivery/base/class-helper.php';
		require_once WDYD_PLUGIN_DIR . '/libs/delivery/base/class-order.php';
		require_once WDYD_PLUGIN_DIR . '/libs/delivery/base//class-checkout-ajax.php';
		require_once WDYD_PLUGIN_DIR . '/includes/Yandex/class-helper.php';
		require_once WDYD_PLUGIN_DIR . '/includes/Yandex/class-yandex.php';
		require_once WDYD_PLUGIN_DIR . '/includes/Yandex/class-client.php';

		//$shipping_methods = WC()->shipping()->get_shipping_methods();

		$this->token                = $this->get_setting( 'api_key', Yandex::SHIPPING_DELIVERY_ID );
		$this->shop_id              = $this->get_setting( 'shop_id', Yandex::SHIPPING_DELIVERY_ID );
		$this->warehouse_id         = $this->get_setting( 'warehouse_id', Yandex::SHIPPING_DELIVERY_ID );
		$this->adjust_delivery_date = $this->get_setting( 'adjust_delivery_date', Yandex::SHIPPING_DELIVERY_ID );

		$this->client = new Client( [
			'api_key'      => $this->token,
			'shop_id'      => $this->shop_id,
			'warehouse_id' => $this->warehouse_id
		] );

		$dff = $this->get_delivery_options();
		$dd  = "";
	}

	public function get_delivery_options() {
		$orderSizes = Helper::getOrderSizes();

		$params = [
			'senderId'     => (int) $this->shop_id,
			'to'           => [
				'geoId'    => 14,
				'location' => 'Россия, Тверь',
			],
			'dimensions'   => [
				'length' => (float) $orderSizes['length'],
				'height' => (float) $orderSizes['height'],
				'width'  => (float) $orderSizes['width'],
				'weight' => (float) $orderSizes['weight'],
			],
			//'deliveryType' => 'PICKUP',
			'shipment'     => [
				'date'              => date( 'Y-m-d', strtotime( '+' . ( (int) $this->adjust_delivery_date ) ) ),
				'warehouseId'       => $this->warehouse_id,
				'includeNonDefault' => false,
			],
			'cost'         => [
				"assessedValue"             => 250,
				"itemsSum"                  => 250,
				"manualDeliveryForCustomer" => 0,
				"fullyPrepaid"              => true
			]
		];

		$response = $this->client->request( 'PUT', 'delivery-options', [], $params );

		/*foreach ( $response as $optionKey => $option ) {
			if ( isset( $option['cost'] ) ) {
				foreach ( $option['cost'] as $costKey => $_cost ) {
					$response[ $optionKey ]['cost'][ $costKey ] = round( $_cost );
				}
			}
		}*/

		return $response;
	}
}

add_action( 'template_redirect', function () {
	new \YandexPay\Yandex_Delivery_Integration();
} );

