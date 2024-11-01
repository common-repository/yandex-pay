/**
 *
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 23.08.2021, CreativeMotion
 * @version 1.0
 */
jQuery(document).ready(function($) {

	class Yandex_Pay {
		sdkLoaded = false;
		buttonContanier;
		button;

		constructor() {
			let self = this;

			this.loadSdk().then(function() {
				self.createButton();
			});

			jQuery('body').on('updated_shipping_method', function() {
				self.loadSdk().then(function() {
					self.createButton();
				});
			});
		}

		loadSdk() {
			var self = this;

			return new Promise(function(resolve, reject) {
				if( !$('#yandex-pay').length ) {
					var script = document.createElement("script");
					script.type = "text/javascript";
					script.src = "https://pay.yandex.ru/sdk/v1/pay.js";

					script.setAttribute("id", "yandex-pay");
					script.setAttribute("onload", "wooYandexPayLoadedSkd()");
					script.setAttribute("async", true);

					document.body.appendChild(script);

					window.wooYandexPayLoadedSkd = function() {
						self.sdkLoaded = true;
						resolve();
					}
				} else {
					self.sdkLoaded = true;
					resolve();
				}
			})

		}

		createButton() {
			var self = this;

			let paymentData = {};
			paymentData.env = "1" === self.getParam('test_mode')
			                  ? YaPay.PaymentEnv.Sandbox
			                  : YaPay.PaymentEnv.Production;
			paymentData.version = 2;
			paymentData.countryCode = YaPay.CountryCode.Ru;
			paymentData.currencyCode = YaPay.CurrencyCode.Rub;

			paymentData.merchant = {
				id: self.getParam('merchant_id'),
				name: self.getParam('merchant_name')
			};

			paymentData.requiredFields = {
				// Запрашиваем контакт плательщика
				billingContact: {
					email: true,
				},
				// Запрашиваем контакт получателя
				shippingContact: {
					name: true,
					email: true,
					phone: true,
				},
				// Запрашиваем данные по курьерской доставке
				shippingTypes: {
					direct: true,
				},
			};

			paymentData.order = self.getParam('order');

			var paymentMethods = [
				{
					type: YaPay.PaymentMethodType.Card,
					gateway: self.getParam("gateway"),
					gatewayMerchantId: self.getParam('gateway_merchant_id'),
					allowedAuthMethods: [YaPay.AllowedAuthMethod.PanOnly],
					allowedCardNetworks: [
						YaPay.AllowedCardNetwork.Visa,
						YaPay.AllowedCardNetwork.Mastercard,
						YaPay.AllowedCardNetwork.Mir,
						YaPay.AllowedCardNetwork.Maestro,
						YaPay.AllowedCardNetwork.VisaElectron
					]
				}
			];

			if( "1" === self.getParam('is_cod_enabled') ) {
				paymentMethods.push(
					{
						type: YaPay.PaymentMethodType.Cash,
					}
				);
			}
			paymentData.paymentMethods = paymentMethods;

			YaPay.createPayment(paymentData)
				.then(function(payment) {

					var button_style = YaPay.ButtonTheme.Black,
						button_width = YaPay.ButtonWidth.Auto;

					switch( self.getParam('button_style') ) {
						case "white":
							button_style = YaPay.ButtonTheme.White
							break;
						case "border":
							button_style = YaPay.ButtonTheme.WhiteOutlined
							break;
					}

					if( "Max" === self.getParam('button_width') ) {
						button_width = YaPay.ButtonWidth.Max;
					}

					var container = $('#woo-ypay-checkout-payment-button-cart, #woo-ypay-checkout-payment-button-product, #woo-ypay-checkout-payment-button-checkout');
					self.button = payment.createButton({
						type: YaPay.ButtonType.Checkout,
						theme: button_style,
						width: button_width,
					});

					self.button.mount(container[0]);

					self.button.on(YaPay.ButtonEventType.Click, function() {
						if( undefined !== container.data('product-id') ) {
							$.ajax({
								type: 'POST',
								url: woocommerce_params.wc_ajax_url.toString().replace('%%endpoint%%', 'yandexpay-add-to-cart'),
								contentType: "application/x-www-form-urlencoded; charset=UTF-8",
								enctype: 'multipart/form-data',
								data: {
									'product_id': container.data('product-id'),
									'quantity': 1,
								},
								success: function(response) {
									if( !response ) {
										return;
									}

									if( response.error && response.product_url ) {
										new Error("Failed to add item to cart!")
										return;
									}

									paymentData.order = response;
									payment.update(paymentData);
									payment.checkout();
								},
								dataType: 'json'
							});

							return;
						}

						payment.checkout();
					});

					payment.on(YaPay.PaymentEventType.Process, function onPaymentProcess(event) {
						$('.woocommerce-cart-form, div.cart_totals').addClass('processing').block({
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6
							}
						});

						self.checkout(event);

						payment.complete(YaPay.CompleteReason.Success);
					});

					payment.on(YaPay.PaymentEventType.Change, function onPaymentChange(event) {
						function getNextPaymentData(event) {
							return new Promise((resolve) => {

								if( event.shippingAddress ) {
									$.ajax({
										type: 'POST',
										url: woocommerce_params.wc_ajax_url.toString().replace('%%endpoint%%', 'yandexpay-get-shipping-methods'),
										contentType: "application/x-www-form-urlencoded; charset=UTF-8",
										enctype: 'multipart/form-data',
										data: {
											'shipping_address': {
												'country': 'RU',
												'state': event.shippingAddress.locality,
												'postcode': event.shippingAddress.zip,
												'city': event.shippingAddress.locality,
												'address': event.shippingAddress.street + ' дом. ' + event.shippingAddress.building + ' кв. ' + event.shippingAddress.room,
											}
										},
										success: function(response) {
											let paymentData = {
												order: self.getParam('order'),
												shippingOptions: response,
											};
											resolve(paymentData);
										},
										dataType: 'json'
									});

								}

								if( event.shippingOption ) {
									const {order} = paymentData;

									if( !$.isEmptyObject(order.items) ) {
										for( var i in order.items ) {
											if( 'SHIPPING' === order.items[i]['type'] ) {
												delete order.items[i];
												order.items.length--;
											}
										}
									}

									order.items.push({
										type: 'SHIPPING',
										label: event.shippingOption.label,
										amount: event.shippingOption.amount,
									});

									resolve({
										order: {
											id: order.id,
											items: order.items,
											total: {
												amount: (Number(order.total.amount) + Number(event.shippingOption.amount)).toFixed(2),
											},
										}
									});
								}
							});
						}

						// Обновляем платеж
						getNextPaymentData(event).then((updateData) => {
							payment.update(updateData);
						});
					});

					payment.on(YaPay.PaymentEventType.Error, function onPaymentError(event) {
						console.log(event);
						console.log(YaPay.CompleteReason);
						console.log(YaPay.CompleteReason.Error);

						payment.complete(YaPay.CompleteReason.Error);
					});
				})
				.catch(function(err) {
					console.log(err);
				});
		}

		checkout(event) {

			var self = this, shippingMethods = [];

			if( "0" === self.getParam('is_cod_enabled') && "CASH" === event.paymentMethodInfo.type ) {
				new Error('Unsupported payment method. Payment gateway "Cash on delivery" is not connected!');
			}

			shippingMethods.push(event.shippingMethodInfo.shippingOption.id);

			$.ajax({
				type: 'POST',
				url: woocommerce_params.wc_ajax_url.toString().replace('%%endpoint%%', 'checkout'),
				contentType: "application/x-www-form-urlencoded; charset=UTF-8",
				enctype: 'multipart/form-data',
				data: {
					'woocommerce-process-checkout-nonce': self.getParam("checkout_nonce"),
					'terms': 0,
					'createaccount': 0,
					'payment_method': ("CASH" === event.paymentMethodInfo.type ? 'cod' : 'yandex-pay'),
					'shipping_method': shippingMethods,
					'ship_to_different_address': 0,
					//'woocommerce_checkout_update_totals': false,
					'billing_first_name': event.shippingContact.firstName,
					'billing_last_name': event.shippingContact.lastName,
					'billing_company': '',
					'billing_country': 'RU',
					'billing_address_1': event.shippingMethodInfo.shippingAddress.street + ' дом. ' + event.shippingMethodInfo.shippingAddress.building + ' кв. ' + event.shippingMethodInfo.shippingAddress.room,
					'billing_address_2': '',
					'billing_house': event.shippingMethodInfo.shippingAddress.building,
					'billing_flat': event.shippingMethodInfo.shippingAddress.room,
					'billing_city': event.shippingMethodInfo.shippingAddress.locality,
					'billing_state': event.shippingMethodInfo.shippingAddress.locality,
					'billing_postcode': event.shippingMethodInfo.shippingAddress.zip,
					'billing_phone': event.shippingContact.phone,
					'billing_email': event.shippingContact.email,
					'order_comments': '',
					//--
					'shipping_first_name': event.shippingContact.firstName,
					'shipping_last_name': event.shippingContact.lastName,
					'shipping_company': '',
					'shipping_country': 'RU',
					'shipping_address_1': event.shippingMethodInfo.shippingAddress.street + ' дом. ' + event.shippingMethodInfo.shippingAddress.building + ' кв. ' + event.shippingMethodInfo.shippingAddress.room,
					'shipping_address_2': '',
					'shipping_city': event.shippingMethodInfo.shippingAddress.locality,
					'shipping_state': event.shippingMethodInfo.shippingAddress.locality,
					'shipping_postcode': event.shippingMethodInfo.shippingAddress.zip,

					'woo_yandex_pay_payment_data': JSON.stringify(event),
					'woo_yandex_pay_order_id': self.getParam('order')['id']
				},
				success: function(response) {
					if( response ) {
						if( "failure" === response.result ) {
							console.log(response);
							if( response.messages ) {
								alert($(response.messages).text());
							} else {
								alert("One click checkout failed payment. Please contact plugin support!");
							}
							return false;
						}

						if( "success" === response.result && response.redirect ) {
							window.location.href = response.redirect;
							return false;
						}

						return false;
					}

					alert("One click checkout failed payment. Please contact plugin support!");
				},
				error: function(error) {
					console.log(error); // For testing (to be removed)
					alert(error);
				}
			});
		}

		getParam(name) {
			if( undefined !== window.yandex_pay_checkout_params ) {
				return window.yandex_pay_checkout_params[name] || null;
			}

			new Error("Yandex pay params is not defined!");

			return {};
		}
	}

	new Yandex_Pay();
});


