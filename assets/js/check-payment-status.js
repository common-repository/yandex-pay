/**
 * Check payment status class
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 02.09.2021, CreativeMotion
 * @version 1.0
 */

(function($) {
	'use strict';
	$(document).ready(function() {
		class YandexPayCheckPaymentStatus {
			constructor() {
				this.request({
					order_id: $('#woo-yandex-pay-order-id').val()
				}, function(response) {
					$('.woo-yandex-pay-panding-message').hide();
					$('.woo-yandex-pay-paid-message').show();
					console.log(response);
				});
			}

			request(data, successCallback) {
				var self = this;

				$.ajax(this.getParam('webhook_url'), {
					type: 'post',
					dataType: 'json',
					data: data,
					success: function(response) {
						if( !response || !response.success ) {
							if( response.data ) {
								console.log(response.data.message);
								//alert('Error: [' + response.data.message + ']');
							} else {
								console.log(response);
							}
							return;
						}

						if( "paid" === response.data.status || "fulfilled" === response.data.status ) {
							successCallback(response);
						} else if( "unpaid" === response.data.status ) {
							setTimeout(function() {
								self.request(data, successCallback);
							}, 3000);
						} else if( "cancelled" === response.data.status ) {
							// canceled
						}

					},
					error: function(xhr, ajaxOptions, thrownError) {
						console.log(xhr.status);
						console.log(xhr.responseText);
						console.log(thrownError);
					}
				});
			}

			getParam(name) {
				if( undefined !== window.yandex_pay_params ) {
					return window.yandex_pay_params[name] || null;
				}

				new Error("Yandex pay params is not defined!");

				return {};
			}
		}

		new YandexPayCheckPaymentStatus();
	});

})(jQuery);
