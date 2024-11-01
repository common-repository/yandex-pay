=== Yandex pay ===
Contributors: yandexpay
Tags: yandexpay, woocomerce, payment
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 1.1.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Официальный модуль Yandex Pay

== Description ==

Что вы получаете
Yandex Pay — сервис для быстрой и безопасной оплаты покупок. С появлением Yandex
Pay на вашем сайте миллионы пользователей Яндекса смогут быстрее оплачивать у вас
заказы.

= Почему это выгодно =
При оплате через Yandex Pay не нужно вводить данные сохранённой в Яндексе
банковской карты. А чем проще процесс покупки — тем больше заказов и выше
конверсия. А значит, и ваша прибыль.

Яндекс хранит и передаёт платёжные данные в зашифрованном виде.

Оплата происходит прямо на сайте с помощью модуля Yandex Pay. Кнопка оплаты
вынесена на сайт и привлекает внимание клиентов.

= Платежные агрегаторы =
С модулем Yandex Pay работают известные платёжные агрегаторы:
– Payture
– RBK.Money
– Best2Pay

Список пополняется

= Как подключить =
1. Убедитесь, что вы работаете с одним из поддерживаемых платежных агрегаторов
из списка. Если вы не нашли свой, вам необходимо заключить договор с одним из
них.
2. Установите модуль Yandex Pay
3. Активируйте модуль в кабинете администратора, в разделе Платежные системы
4. Используйте тестовый MerchantID и тестовое название магазина из документа
(пункт 4) для настройки и тестирования платежного модуля Yandex Pay на вашем
сайте
5. Для получения тестовых данных платежного шлюза, вам необходимо обратиться к
вашему платежному агрегатору, они выдадут все данные для тестирования. Как
правило это: идентификатор продавца и специальные ключи для тестирования.

Используйте тестовые данные от агрегатора, вставьте в соответсвующуие поля в
модуле, затем можно протестировать работу кнопки на сайте. Сделайте скриншоты как выглядит кнопка на вашем сайте.
Отправьте заявку в Яндекс через форму, приложите все скриншоты и укажите
домен сайта. Мы постараемся ответить вам в течение 3-х часов и пришлем ваш уникальный
MerchantID, который нужно использовать для боевых платежей. После того как вы протестируете Yandex Pay на сайте, вам необходимо запросить
реальные данные у вашего платежного агрегатора и заменить все тестовые
данные боевыми в настройках модуля.

Все, Yandex Pay настроен!

== Installation ==

= Minimum Requirements =

* PHP 7.2 or greater is recommended
* MySQL 5.6 or greater is recommended

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handles the file transfer, and you won’t need to leave your web browser. To do an automatic install of WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type “Yandex Pay,” then click “Search Plugins.” Once you’ve found us,  you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by! Click “Install Now,” and WordPress will take it from there.

= Manual installation =

Manual installation method requires downloading the WooCommerce plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation).

= Updating =

Automatic updates should work smoothly, but we still recommend you back up your site.

If you encounter issues with the shop/category pages after an update, flush the permalinks by going to WordPress > Settings > Permalinks and hitting “Save.” That should return things to normal.

= Sample data =

WooCommerce comes with some sample data you can use to see how products look; import sample_products.xml via the [WordPress importer](https://wordpress.org/plugins/wordpress-importer/). You can also use the core [CSV importer](https://docs.woocommerce.com/document/product-csv-importer-exporter/?utm_source=wp%20org%20repo%20listing&utm_content=3.6) or our [CSV Import Suite extension](https://woocommerce.com/products/product-csv-import-suite/?utm_source=wp%20org%20repo%20listing&utm_content=3.6) to import sample_products.csv

== Changelog ==
= 1.1.4 2022-07-12 =
* Ипсравлена ошибка при оплате через шлюзы РБС (МТС банк) и РБС (Россельхоз банк)

= 1.1.3 2022-07-15 =
* Исправлена ошибка в 1 click checkout. При оформлении заказа выпадала ошибка "Не заполнено обязательное поле дом, квартира".
* Улучшена совметимость с Wordpress 6.0

= 1.1.2 2022-04-11 =
* Исправлена ошибка при оплате через Payture
* Добавлены описание в интерфейсе к настройкам плагина
* Улучшено логирование Payture

= 1.1.1 2022-04-11 =
* Добавлено два новых платежных шлюза RBS МТС банк и RBS Россельхоз банк

= 1.1.0 2022-03-15 =
* Добавлен one click checkout для быстрого оформления покупок
* Обновлен интерфейс плагина
* Добавлен новый шлюз РБС (Альфа банк эквайринг)

= 1.0.0 2021-12-01 =
* Плагин загружен в репозиторий