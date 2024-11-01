<?php
/**
 * CКласс описывающий логику для шлюза Альфа банка
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

class RBS_Alfabank extends RBS {


	protected $id = "alfabank";
	protected $api_url = 'https://pay.alfabank.ru/payment/';
	protected $sandbox_api_url = "https://web.rbsuat.com/ab/";
	protected $login;
	protected $password;

	public function __construct( array $options = [] ) {
		parent::__construct( $options );

		$this->login    = $this->get_setting( 'alfabank_login' );
		$this->password = $this->get_setting( 'alfabank_password' );
	}
}