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

			jQuery('body').on('updated_checkout', function() {
				self.refreshButton();
			});

			jQuery(document).on('payment_method_selected', function() {
				self.refreshButton();
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

			paymentData.order = self.getParam('order');

			paymentData.paymentMethods = [
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

			YaPay.createPayment(paymentData,
				{
					agent: {
						name: "CMS-WooCommerce",
						version: "1.0"
					}
				})
				.then(function(payment) {

					// Remove meta fields
					$('#woo-yandex-pay-payment-data').remove();
					$('#woo-yandex-pay-order-id').remove();
					var checkout_form = $('form.checkout');
					checkout_form.append('<input type="hidden" id="woo-yandex-pay-payment-data" name="woo_yandex_pay_payment_data" value="">');
					checkout_form.append('<input type="hidden" id="woo-yandex-pay-order-id" name="woo_yandex_pay_order_id" value="">');

					$('#place_order').hide();

					if( !$('#woo-yandex-pay-button-contanier').length ) {
						$('#place_order').after($('<div>').attr('id', "woo-yandex-pay-button-contanier"));
					} else {
						$('#woo-yandex-pay-button-contanier').html('');
					}

					var button_style = YaPay.ButtonTheme.Black;

					switch( self.getParam('button_style') ) {
						case "white":
							button_style = YaPay.ButtonTheme.White
							break;
						case "border":
							button_style = YaPay.ButtonTheme.WhiteOutlined
							break;
					}

					var container = document.querySelector('#woo-yandex-pay-button-contanier');
					self.button = payment.createButton({
						type: YaPay.ButtonType.Pay,
						theme: button_style,
						width: YaPay.ButtonWidth.Auto,
					});

					self.button.mount(container);

					self.button.on(YaPay.ButtonEventType.Click, function() {
						let validate = true;
						$("#billing_first_name,#billing_last_name,#billing_address_1,#billing_city,#billing_state,#billing_postcode,#billing_phone,#billing_email").each(function() {
							if( "" === $(this).val() ) {
								validate = false;
							}
						});

						if( !validate ) {
							$('#place_order').click();
							return false;
						}

						payment.checkout();
					});

					payment.on(YaPay.PaymentEventType.Process, function onPaymentProcess(event) {
						$('#woo-yandex-pay-payment-data').val(JSON.stringify(event));
						$('#woo-yandex-pay-order-id').val(self.getParam('order')['id']);

						console.log(event);

						$('#place_order').click();

						payment.complete(YaPay.CompleteReason.Success);

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

		removeButton() {

			// Remove old yandex pay button
			this.button.unmount(document.querySelector('#woo-yandex-pay-button-contanier'));

			// Clear property
			this.button = null;

			// Remove meta fields
			$('#woo-yandex-pay-payment-data').remove();
			$('#woo-yandex-pay-order-id').remove();
		}

		refreshButton() {
			var self = this;

			if( $('#payment_method_yandex-pay').is(":checked") ) {
				this.loadSdk().then(function() {
					if( self.button ) {
						self.removeButton();
					}
					self.createButton();
				});
			} else {
				if( self.button ) {
					self.removeButton();
					if( !$('#place_order').is(":visible") ) {
						$('#place_order').show();
					}
				}
			}

		}

		getParam(name) {
			if( undefined !== window.yandex_pay_params ) {
				return window.yandex_pay_params[name] || null;
			}

			new Error("Yandex pay params is not defined!");

			return {};
		}
	}

	new Yandex_Pay();
});


