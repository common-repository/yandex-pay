/**
 *
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

(function($) {
	'use strict';
	$(document).ready(function() {

		// Название парметра с доменами
		const DOMAINS_PARAM_NAME = "domains";

		// Разделитель доменов в URL
		const DOMAINS_SEPARATOR = ",";

		// Название параметра с названием мерчанта
		const SITE_NAME_PARAM_NAME = "name";

		// Урл формы для получения merchant-id
		// Боевой УРЛ будет позже
		const YAPAY_CONSOLE_URL = "https://console.pay.yandex.ru/cms/onboard";

		// Параметры сообщения с данными
		const YAPAY_MESSAGE_SOURCE = "yandex-pay";
		const YAPAY_MESSAGE_TYPE = "merchant-data";
		const YAPAY_ERROR_TYPE = "error";

		const getWindowFeatures = (formSize) => {
			const {screen} = window;
			const [width, height] = formSize;

			// NB: Если экран маленький, то не отдаем параметры
			if( screen.width < width || screen.height < height ) {
				return undefined;
			}

			// TODO: Центрировать не по экрану, а по открытому браузеру
			const left = (screen.width - width) >> 1;
			const top = (screen.height - height) >> 1;

			return [
				"scrollbars=yes",
				"resizable=yes",
				"status=no",
				"location=no",
				"toolbar=no",
				"menubar=no",
				`width=${width}`,
				`height=${height}`,
				`left=${left}`,
				`top=${top}`
			].join(",");
		};

		$('#woocommerce_yandex-pay_payment_gateway').change(function() {
			$('.yandex-pay-gateway-settings-group').add('.yandex-pay-payment-gateway-description')
				.addClass('hidden');
			$("#yandex-pay-" + $(this).val() + "-settings").add("#yandex-pay-" + $(this).val() + "-description")
				.removeClass('hidden');
		});
		$('#woocommerce_yandex-pay_payment_gateway').change();

		// Generate Merchant ID

		$('#wc-ypay-get-merchant-id-button').click(function(e) {
			e.preventDefault();
			getMerchantData();
		});

		// Формируем УРЛ с данными
		function getPopupUrl() {
			const url = new URL(YAPAY_CONSOLE_URL);

			url.searchParams.append(DOMAINS_PARAM_NAME, window.location.origin);
			url.searchParams.append(SITE_NAME_PARAM_NAME, $('#woocommerce_yandex-pay_merchant_name').val());

			return url.href;
		}

		// Открываем Окно и ждем получения merchant-id
		function getMerchantData() {
			const popup = window.open(
				getPopupUrl(),
				"*",
				getWindowFeatures([960, 705])
			);

			window.addEventListener("message", function(event) {
				const data = toObject(event.data);

				if( data.source === YAPAY_MESSAGE_SOURCE ) {
					if( data.type === YAPAY_MESSAGE_TYPE ) {
						showResult({
							merchantId: data.merchant_id,
							merchantName: data.merchant_name
						});

						popup.close();
					} else if( data.type === YAPAY_ERROR_TYPE ) {
						console.log(data.error);

						popup.close();
					}
				}
			});
		}

		function showResult(data) {
			$('#woocommerce_yandex-pay_merchant_id').val(data.merchantId);
		}

		function toObject(_data) {
			try {
				const data = typeof _data === "string" ? JSON.parse(_data) : _data;

				return typeof data === "object" && data !== null ? data : {};
			}
			catch( err ) {
				return {};
			}
		}

	});
})(jQuery);
