<?php
/**
 * Класс описывающий логику для шлюза МТС банка
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

class RBS_Mtsbank extends RBS {

	protected $id = "mtsbank";
	protected $api_url = 'https://oplata.mtsbank.ru/payment/';
	protected $sandbox_api_url = "https://web.rbsuat.com/mtsbank/";
	protected $login;
	protected $password;

	public function __construct( array $options = [] ) {
		parent::__construct( $options );

		$this->login    = $this->get_setting( 'mtsbank_login' );
		$this->password = $this->get_setting( 'mtsbank_password' );
	}
}