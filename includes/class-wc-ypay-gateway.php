<?php
/**
 * Yandex Pay Gateway
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 24.08.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay;

use function Clue\StreamFilter\fun;

defined( 'ABSPATH' ) || exit;

class WC_Gateway extends \WC_Payment_Gateway {

	use Helpers;

	public function __construct() {

		$this->id                 = YANDEX_PAY_PLUGIN_GATEWAY_ID;
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = true;
		$this->method_title       = 'Yandex Pay';
		$this->method_description = 'Allows you to pay for goods using the Yandex Pay button.';

		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = [
			'products'
		];

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = 'yes' === $this->get_option( 'testmode' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options'
		] );

		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
		// Payment listener/API hook
		add_action( 'woocommerce_api_' . $this->id . '_callback', [ $this, 'callback_handler' ] );

		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'admin_enqueue_scripts', function () {
			if ( isset( $_GET['page'] ) && "wc-settings" === $_GET['page'] && isset( $_GET['tab'] ) && "checkout" == $_GET['tab'] ) {
				if ( isset( $_GET['section'] ) && $this->id == $_GET['section'] ) {
					wp_enqueue_script( 'woocommerce_yandex_pay_checkout_settings', YANDEX_PAY_PLUGIN_URL . '/assets/js/admin/checkout-settings.js', [
						'jquery'
					] );
					add_thickbox();
				}
			}
		} );

		// Сбрасываем настройку "Разрешить оплату при получении", если модуль Woocommerce "Оплата при получении"
		// деактивирован
		add_action( 'update_option_woocommerce_cod_settings', function ( $old_value, $new_value ) {
			if ( ! empty( $new_value ) && "no" === $new_value['enabled'] ) {
				$settings = get_option( "woocommerce_" . YANDEX_PAY_PLUGIN_GATEWAY_ID . "_settings" );
				if ( ! empty( $settings ) && is_array( $settings ) ) {
					unset( $settings['one_click_checkout_cod'] );
				}
				update_option( "woocommerce_" . YANDEX_PAY_PLUGIN_GATEWAY_ID . "_settings", $settings );
			}

			return $new_value;
		}, 10, 2 );
	}

	/**
	 * Return handler for Hosted Payments.
	 * e.g. ?wc-api=yandex-pay_callback
	 */
	public function callback_handler() {
		if ( isset( $_REQUEST['gateway'] ) ) {
			$payment_gateway = sanitize_text_field( $_REQUEST['gateway'] );
			$gateway         = $this->get_payment_gateway( $payment_gateway );
			$gateway->payment_callback();
		}
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page( $order_id ) {
		/** @var \WC_Order $order */
		$order = wc_get_order( $order_id );

		$payment_gateway_name = $order->get_meta( '_ypay_payment_gateway' );

		if ( empty( $payment_gateway_name ) ) {
			$payment_gateway_name = $this->get_option( 'payment_gateway' );
		}

		$gateway = $this->get_payment_gateway( $payment_gateway_name );
		$gateway->thankyou_page( $order );
	}

	public function init_form_fields() {


		$this->form_fields = [
			'enabled'       => [
				'title'       => 'Включить/Выключить',
				'label'       => 'Включить платежный шлюз Yandex Pay.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'         => [
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'Yandex Pay',
				'desc_tip'    => true,
			],
			'description'   => [
				'title'       => 'Описание шлюза оплаты',
				'type'        => 'textarea',
				'description' => 'Позволяет установить описание шлюза, которое пользователь видит во время оформления заказа.',
				'default'     => 'Платите кредитной картой через наш крутой платежный шлюз.',
			],
			'testmode'      => [
				'title'       => 'Режим разработки',
				'label'       => 'Включить режим разработки',
				'type'        => 'checkbox',
				'description' => 'Переведите платежный шлюз в тестовый режим, используя тестовые API-ключи.',
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'merchant_id'   => [
				'title'       => 'Merchant ID',
				'type'        => 'text',
				'default'     => 'bbb9c171-2fab-45e6-b1f8-6212980aa9bb',
				'description' => 'Нажмите <a href="#" id="wc-ypay-get-merchant-id-button" class="button">Получить Merchant ID</a>. После принятия оферты Merchant ID будет заполнен автоматически в это поле.',
			],
			'merchant_name' => [
				'title'   => 'Название магазина',
				'type'    => 'text',
				'default' => get_bloginfo( 'name' )
			],
			'button_style'  => [
				'title'   => 'Стиль кнопки',
				'type'    => 'select',
				'options' => [
					'black'  => 'Черная',
					'white'  => 'Белая',
					'border' => 'С границей'
				]
			],

			'payment_gateway_header' => [
				'title'       => __( 'Настройки платежных систем', 'wc-payture' ),
				'type'        => 'title',
				'description' => '',
			],

			'payment_gateway'          => [
				'title'   => 'Выберите платежную систему',
				'type'    => 'select',
				'options' => [
					'payture'  => 'Payture',
					'rbkmoney' => 'RBKmoney',
					'best2pay' => 'Best2pay',
					'alfabank' => 'RBS (Альфа банк)',
					'mtsbank'  => 'RBS (МТС банк)',
					'rshb'     => 'RBS (Россельхоз банк)'
				],
				'description' => '
<span id="yandex-pay-payture-description" class="yandex-pay-payment-gateway-description hidden">
Для текущих клиентов. Для заполнения формы сверху «Настройка обработчика ПС» понадобится API-ключ для авторизации и пароль для API. Чтобы их получить обратитесь к своему менеджеру или напишите на почту <a href="mailto:business@payture.com">business@payture.com</a>.

Для новых клиентов. Заполните заявку <a href="https://payture.com/services/yandex-pay/?utm_source=pay&utm_medium=payture&utm_campaign=wordpress" target="_blank">на сайте</a> или напишите на почту <a href="mailto:business@payture.com">business@payture.com</a>. Вы получите скидку!! в размере 25%!! на услуги провайдера, о которой вам расскажет менеджер. Время ответа в рабочие дни составляет 3-4 часа.
</span>
<span id="yandex-pay-rbkmoney-description" class="yandex-pay-payment-gateway-description hidden">
Для заполнения формы сверху «Настройка обработчика ПС» понадобится ключ магазина и публичный ключ. Для заполнения этих полей напишите на почту <a href="mailto: support@rbkmoney.com">support@rbkmoney.com</a>. В теле письма укажите наименование CMS "Wordpress", название модуля "Yandex.Pay" и кратко опишите суть вопроса.
</span>
<span id="yandex-pay-best2pay-description" class="yandex-pay-payment-gateway-description hidden">
Для текущих клиентов. 
Для заполнения формы «Настройка обработчика ПС» понадобится уникальный идентификатор учетной записи и пароль для формирования электронной подписи. Если вы их не знаете, отправьте запрос на почту <a href="mailto:helpline@best2pay.net">helpline@best2pay.net</a>. Сервис гарантирует 4 часа на ответ, 48 часов на решение.

Для новых клиентов.
<a href="http://best2pay.net/?utm_source=pay&utm_medium=best2pay&utm_campaign=wordpress#main_form_container" target="_blank">Заполните форму</a>. С вами свяжется менеджер сервиса.
</span>
<span id="yandex-pay-alfabank-description" class="yandex-pay-payment-gateway-description hidden">
Для текущих клиентов
Заполните в форме внизу страницы «Настройка обработчика ПС» свой логин учетной записи, который вы получали при подключении в письме от банка, а также пароль. Тема письма от банка: «Реквизиты для подключения к продуктивной среде (Интернет-эквайринг Альфа-Банк) [login]».

Для новых клиентов
<a href="https://alfabank.ru/sme/payservice/internet-acquiring/?utm_source=pay&utm_medium=alfa&utm_campaign=wordpress#form" target="_blank">Заполните форму</a>. С вами свяжется менеджер банка.
</span>
<span id="yandex-pay-mtsbank-description" class="yandex-pay-payment-gateway-description hidden">
Для текущих клиентов
Заполните в форме внизу страницы «Настройка обработчика ПС» свой логин учетной записи, который вы получили от банка при подключении. Также потребуется пароль, который вы установили самостоятельно.

Для новых клиентов
<a href="http://www.mtsbank.ru/malomu-biznesu/torgovye-resheniya/internet-acquiring/?utm_source=pay&utm_medium=mtsb&utm_campaign=wordpress" target="_blank">Заполните форму</a>. С вами свяжется менеджер банка. Прием заявки в работу — 1 рабочий день. Подключение при подаче полного комплекта документов и готовности сайта — 2-4 дня.
</span>
<span id="yandex-pay-rshb-description" class="yandex-pay-payment-gateway-description hidden">
Для текущих клиентов:
Заполните в форме внизу страницы «Настройка обработчика ПС» свой логин учетной записи, а также пароль, который поступил вам на электронный адрес указанный в одной из анкет при заключении договора.
Для восстановления данных необходимо обратиться к своему менеджеру в филиале обслуживания договора или на напишите на почту <a href="mailto:ecomm@rshb.ru">ecomm@rshb.ru</a>.

Для новых клиентов:
Обратитесь в офис банк или к своему менеджеру. Также можно оставить заявку <a href="http://www.rshb.ru/smallbusiness/acquiring/internet/?utm_source=pay&utm_medium=rshb&utm_campaign=wordpress" target="_blank">на сайте</a>. В заявке укажите, что планируете принимать платежи через модуль Wordpress с кнопкой Yandex Pay.
</span>
'
			],
			'start_form_group_payture' => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-payture-settings',
			],
			'payture_merchant_host'    => [
				'title'       => 'Payture Merchant Host',
				'type'        => 'text',
				'default'     => '',
				'description' => 'В поле вставляем только имя хоста <strong>service</strong>.payture.com без домена <strong>.payture.com</strong>',
			],
			'payture_merchant_key'     => [
				'title'       => 'Payture Merchant Key',
				'type'        => 'text',
				'default'     => 'YandexPayWooCommerceTest3DS',
				'description' => '',
			],

			'payture_gateway_merchant_id' => [
				'title'   => 'Payture Gateway MerchantId',
				'type'    => 'text',
				'default' => '123654',
			],
			'start_form_group_rbkmoney'   => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-rbkmoney-settings'
			],
			'rbkmoney_shop_id'            => [
				'title'       => 'RBK Money Shop ID',
				'type'        => 'text',
				'default'     => '',
				'description' => '',

			],

			'rbkmoney_private_key'         => [
				'title'       => 'RBK Money Private Key',
				'type'        => 'text',
				'default'     => '',
				'description' => '',

			],
			'rbkmoney_gateway_merchant_id' => [
				'title'   => 'RBK Money Gateway MerchantId',
				'type'    => 'text',
				'default' => 'test-gateway-merchant-id',
			],
			'start_form_group_best2pay'    => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-best2pay-settings'
			],
			'best2pay_sector'              => [
				'title'       => 'Best2pay Sector ID',
				'type'        => 'text',
				'default'     => '3026',
				'description' => '',
			],
			'best2pay_password'            => [
				'title'       => 'Best2pay Password',
				'type'        => 'text',
				'default'     => 'test',
				'description' => '',
			],
			'best2pay_gateway_merchant_id' => [
				'title'   => 'Best2pay Gateway MerchantId',
				'type'    => 'text',
				'default' => 'yatest',
			],
			'start_form_group_alfabank'    => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-alfabank-settings'
			],
			'alfabank_login'               => [
				'title'   => 'RBS Login',
				'type'    => 'text',
				'default' => 'yandex_test-api'
			],
			'alfabank_password'            => [
				'title'   => 'RBS Password',
				'type'    => 'text',
				'default' => 'yandex_test*?1'
			],
			'start_form_group_mtsbank'     => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-mtsbank-settings'
			],
			'mtsbank_login'                => [
				'title'   => 'RBS Login',
				'type'    => 'text',
				'default' => 'YP_test-api'
			],
			'mtsbank_password'             => [
				'title'   => 'RBS Password',
				'type'    => 'text',
				'default' => 'YP_test'
			],
			'start_form_group_rshb'        => [
				'type' => 'start_form_group',
				'id'   => 'yandex-pay-rshb-settings'
			],
			'rshb_login'                   => [
				'title'   => 'RBS Login',
				'type'    => 'text',
				'default' => 'yandex-test-api'
			],
			'rshb_password'                => [
				'title'   => 'RBS Password',
				'type'    => 'text',
				'default' => 'yandex-test'
			],
			'one_click_checkout_header'    => [
				'title'       => __( 'Покупка одним нажатием', 'wc-payture' ),
				'type'        => 'title',
				'description' => '',
			],

			'one_click_checkout_cod' => [
				'title'       => 'Оплата при получении',
				'label'       => 'Разрешить пользователю выбирать способ оплаты при получении',
				'type'        => 'checkbox',
				'description' => 'Эта опция доступна при условии того, что вы включили в Woocommerce способ оплаты "Оплата при доставке".',
				'default'     => 'no',
				'disabled'    => ! $this->is_enabled_cod_gateway(),
				'desc_tip'    => true,
			],

			'one_click_checkout_in_cart'              => [
				'title'       => 'Покупка одним нажатием в корзине',
				'label'       => 'Включить покупку одним нажатием в корзине. В корзине появится кнопка, которая позволит оформить заказ без необходимости переходить к форме оформления заказа.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'cart_button_padding'                     => [
				'title'       => 'Отступы для кнопки на странице корзины',
				'type'        => 'padding',
				'description' => __( 'Введите значение в пикселях.' ),
				'default'     => '0'
			],
			'button_fullwidth_in_cart'                => [
				'title'       => 'Кнопка на всю ширину контейнера в корзине',
				'label'       => 'Нажмите галочку, чтобы сделать размер кнопки на всю ширину контейнера.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'one_click_checkout_in_product_card'      => [
				'title'       => 'Покупка одним нажатием на странице товара',
				'label'       => 'Включить покупку одним нажатием на странице товара. В корзине появится кнопка, которая позволит оформить заказ без необходимости переходить к форме оформления заказа.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'product_button_padding'                  => [
				'title'       => 'Отступы для кнопки на странице товара',
				'type'        => 'padding',
				'description' => __( 'Введите значение в пикселях.' ),
				'default'     => '0'
			],
			'button_fullwidth_in_product_card'        => [
				'title'       => 'Кнопка на всю ширину контейнера на странице товара',
				'label'       => 'Нажмите галочку, чтобы сделать размер кнопки на всю ширину контейнера.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'one_click_checkout_in_checkout'          => [
				'title'       => 'Покупка одним нажатием на странице оформления заказа',
				'label'       => 'Включить покупку одним нажатием на странице оформления заказа. В корзине появится кнопка, которая позволит оформить заказ без необходимости переходить к форме оформления заказа.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'checkout_button_padding'                 => [
				'title'       => 'Отступы для кнопки на странице оформления заказа',
				'type'        => 'padding',
				'description' => __( 'Введите значение в пикселях.' ),
				'default'     => '0'
			],
			'button_fullwidth_in_product_in_checkout' => [
				'title'       => 'Кнопка на всю ширину контейнера на странице оформления заказа',
				'label'       => 'Нажмите галочку, чтобы сделать размер кнопки на всю ширину контейнера.',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			/*'product_condition'           => array(
				'type'        => 'multicheck',
				'title'       => __( 'Product Condition:', 'your-plugin' ),
				'description' => __( 'Check All That Apply:', 'your-plugin' ),
				'required'    => false,
				'clear'       => true,
				'options'     => array(
					'lightbulb_out'   => __( 'Lightbulb is Out', 'your-plugin' ),
					'not_turn_on'     => __( 'Will Not Turn On', 'your-plugin' ),
					'fan_not_running' => __( 'Fan Stopped Running', 'your-plugin' ),
					'strange_noise'   => __( 'Strange Noise', 'your-plugin' ),
					'not_catching'    => __( 'Not Catching Insectsn', 'your-plugin' ),
					'csr_other'       => __( 'Other', 'your-plugin' ),
				),

			),*/
		];
	}

	/*public function generate_multicheck_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'id'                => '',
			'title'             => '',
			'disabled'          => false,
			'label_class'       => [],
			'class'             => [],
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'multicheck',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => [],
			'options'           => []
		);

		$data = wp_parse_args( $data, $defaults );

		$field_html = '<fieldset>';

		if ( isset( $data['label'] ) ) {
			$field_html .= '<legend>' . $data['title'] . '</legend>';
		}

		if ( ! empty( $data['options'] ) ) {
			foreach ( $data['options'] as $option_key => $option_text ) {
				$field_html .= '<input type="checkbox" class="input-multicheck ' . esc_attr( implode( ' ', $data['class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $data['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
				$field_html .= '<label for="' . esc_attr( $data['id'] ) . '_' . esc_attr( $option_key ) . '" class="multicheck ' . implode( ' ', $data['label_class'] ) . '">' . $option_text . '</label>';
			}
		}

		$field_html .= $this->get_description_html( $data ); // WPCS: XSS ok.;

		$field_html .= '</fieldset>';

		$container_class = esc_attr( implode( ' ', $data['class'] ) );
		$container_id    = esc_attr( $data['id'] ) . '_field';

		$after = ! empty( $data['clear'] ) ? '<div class="clear"></div>' : '';

		$field_container = '<p class="form-row %1$s" id="%2$s">%3$s</p>';

		$field = sprintf( $field_container, $container_class, $container_id, $field_html ) . $after;

		return $field;
	}*/

	/**
	 * Get a field's posted and validated value.
	 *
	 * @param string $key Field key.
	 * @param array $field Field array.
	 * @param array $post_data Posted data.
	 *
	 * @return string
	 */
	public function get_field_value( $key, $field, $post_data = [] ) {


		$type      = $this->get_field_type( $field );
		$field_key = $this->get_field_key( $key );
		$post_data = empty( $post_data ) ? $_POST : $post_data; // WPCS: CSRF ok, input var ok.
		$value     = isset( $post_data[ $field_key ] ) ? $post_data[ $field_key ] : null;

		if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value );
		}

		// Look for a validate_FIELDID_field method for special handling.
		if ( is_callable( [ $this, 'validate_' . $key . '_field' ] ) ) {
			return $this->{'validate_' . $key . '_field'}( $key, $value );
		}

		// Look for a validate_FIELDTYPE_field method.
		if ( is_callable( [ $this, 'validate_' . $type . '_field' ] ) ) {
			return $this->{'validate_' . $type . '_field'}( $key, $value );
		}

		// Fallback to text.
		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Start form group HTML.
	 *
	 * @param string $key Field key.
	 * @param array $data Field data.
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function generate_padding_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$defaults = [
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => 'width: 50px',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => []
		];

		$data = wp_parse_args( $data, $defaults );

		$value = $this->get_option( $key );

		if ( ! is_array( $value ) || ! isset( $value['top'] ) ) {
			$value = [
				'top'    => 0,
				'left'   => 0,
				'right'  => 0,
				'bottom' => 0
			];
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php
				echo esc_attr( $field_key ); ?>"><?php
					echo wp_kses_post( $data['title'] ); ?><?php
					echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php
							echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
					<?php
					_e( 'Сверху:' ); ?>
					<input type="number" class="input-text regular-input <?php
					echo esc_attr( $data['class'] ); ?>" type="<?php
					echo esc_attr( $data['type'] ); ?>" name="<?php
					echo esc_attr( $field_key ); ?>[top]" id="<?php
					echo esc_attr( $field_key ); ?>-top" style="<?php
					echo esc_attr( $data['css'] ); ?>" value="<?php
					echo esc_attr( $value['top'] ); ?>" placeholder="<?php
					echo esc_attr( $data['placeholder'] ); ?>" <?php
					disabled( $data['disabled'], true ); ?> <?php
					echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php
					_e( 'Слева:' ); ?>
					<input type="number" class="input-text regular-input <?php
					echo esc_attr( $data['class'] ); ?>" type="<?php
					echo esc_attr( $data['type'] ); ?>" name="<?php
					echo esc_attr( $field_key ); ?>[left]" id="<?php
					echo esc_attr( $field_key ); ?>-left" style="<?php
					echo esc_attr( $data['css'] ); ?>" value="<?php
					echo esc_attr( $value['left'] ); ?>" placeholder="<?php
					echo esc_attr( $data['placeholder'] ); ?>" <?php
					disabled( $data['disabled'], true ); ?> <?php
					echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php
					_e( 'Справа:' ); ?>
					<input type="number" class="input-text regular-input <?php
					echo esc_attr( $data['class'] ); ?>" type="<?php
					echo esc_attr( $data['type'] ); ?>" name="<?php
					echo esc_attr( $field_key ); ?>[right]" id="<?php
					echo esc_attr( $field_key ); ?>-right" style="<?php
					echo esc_attr( $data['css'] ); ?>" value="<?php
					echo esc_attr( $value['right'] ); ?>" placeholder="<?php
					echo esc_attr( $data['placeholder'] ); ?>" <?php
					disabled( $data['disabled'], true ); ?> <?php
					echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php
					_e( 'Снизу:' ); ?>
					<input type="number" class="input-text regular-input <?php
					echo esc_attr( $data['class'] ); ?>" type="<?php
					echo esc_attr( $data['type'] ); ?>" name="<?php
					echo esc_attr( $field_key ); ?>[bottom]" id="<?php
					echo esc_attr( $field_key ); ?>-bottom" style="<?php
					echo esc_attr( $data['css'] ); ?>" value="<?php
					echo esc_attr( $value['bottom'] ); ?>" placeholder="<?php
					echo esc_attr( $data['placeholder'] ); ?>" <?php
					disabled( $data['disabled'], true ); ?> <?php
					echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php
					echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function validate_padding_field( $key, $value ) {
		return array_map( function ( $v ) {
			return (int) $v;
		}, $value );
	}

	/**
	 * Start form group HTML.
	 *
	 * @param string $key Field key.
	 * @param array $data Field data.
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function generate_start_form_group_html( $key, $data ) {
		$defaults = [
			'id' => '',
		];

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		</table>
	<table class="form-table yandex-pay-gateway-settings-group hidden" id="<?php
	echo esc_attr( $data['id'] ); ?>">
		<?php

		return ob_get_clean();
	}

	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it
	 */
	public function payment_fields() {

		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ( $this->testmode ) {
				$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="https://yandex.ru/dev/yandex-pay/doc/tutorial/testing-integration/index.html" target="_blank">documentation</a>.';
				$this->description = trim( $this->description );
			}
			else {
				if ( ! is_ssl() ) {
					$this->description = '<span style="color:red">Your site does not support ssl, Yandex pay payment gateway cannot be used.</span>';
				}
			}

			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
		}
	}

	/*
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
	 */
	public function payment_scripts() {


		// if our payment gateway is disabled, we do not have to enqueue JS too
		if ( 'no' === $this->enabled ) {
			return;
		}

		// no reason to enqueue JavaScript if API keys are not set
		/*if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
			return;
		}*/

		// do not work with card detailes without SSL unless your website is in a test mode
		if ( ! $this->testmode && ! is_ssl() ) {
			return;
		}

		if ( is_checkout() || isset( $_GET['pay_for_order'] ) ) {
			wp_register_script( 'woocommerce_yandex_pay', YANDEX_PAY_PLUGIN_URL . '/assets/js/checkout.min.js', [
				'jquery'
			], YANDEX_PAY_PLUGIN_VERSION );

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

			$gateway = $this->get_option( 'payment_gateway' );

			if ( 'alfabank' === $gateway || 'mtsbank' === $gateway || 'rshb' === $gateway ) {
				$gateway_merchant_id = str_replace( '-api', "", $this->get_option( "{$gateway}_login" ) );

				if('mtsbank' === $gateway || 'rshb' === $gateway) {
					$gateway = 'rbs';
				}
			}
			else {
				$gateway_merchant_id = $this->get_option( "{$gateway}_gateway_merchant_id" );
			}

			wp_localize_script( 'woocommerce_yandex_pay', 'yandex_pay_params', [
				'test_mode'           => $this->testmode ? 1 : 0,
				'current_user_id'     => get_current_user_id(),
				'merchant_id'         => $this->get_option( 'merchant_id' ),
				'merchant_name'       => $this->get_option( 'merchant_name' ),
				'gateway'             => $gateway,
				'gateway_merchant_id' => $gateway_merchant_id,
				'button_style'        => $this->get_option( 'button_style' ),
				'order'               => $order
			] );

			wp_enqueue_script( 'woocommerce_yandex_pay' );
		}

		if ( is_order_received_page() && "rbkmoney" === $this->get_option( 'payment_gateway' ) ) {
			wp_register_script( 'woocommerce_yandex_pay_check_payment_status', YANDEX_PAY_PLUGIN_URL . '/assets/js/check-payment-status.js', [
				'jquery'
			], YANDEX_PAY_PLUGIN_VERSION );
			wp_localize_script( 'woocommerce_yandex_pay_check_payment_status', 'yandex_pay_params', [
				'webhook_url' => site_url( "?wc-api=" . $this->id . '_callback&gateway=rbkmoney' )
			] );
			wp_enqueue_script( 'woocommerce_yandex_pay_check_payment_status' );
		}
	}

	/*
	 * We're processing the payments here, everything about it is in Step 5
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		if ( ! $this->testmode && ! is_ssl() ) {
			$this->log( 'error', 'Your site does not support ssl, Yandex pay payment gateway cannot be used.' );
			wc_add_notice( 'Your site does not support ssl, Yandex pay payment gateway cannot be used.', 'error' );

			return false;
		}

		// we need it to get any order detailes
		$order = wc_get_order( $order_id );

		$payment_data  = json_decode( stripslashes( $_REQUEST['woo_yandex_pay_payment_data'] ), ARRAY_A );
		$payment_data  = array_map( 'sanitize_text_field', $payment_data );
		$temp_order_id = sanitize_text_field( $_REQUEST['woo_yandex_pay_order_id'] );

		if ( empty( $payment_data['token'] ) ) {
			$this->log( 'error', 'The payment cannot be made using the Yandex Pay payment method. Wrong payment data type.' );
			wc_add_notice( 'The payment cannot be made using the Yandex Pay payment method. Wrong payment data type.', 'error' );
		}

		$gateway = $this->get_payment_gateway();

		return $gateway->payment( $payment_data, $temp_order_id, $order );
	}
}