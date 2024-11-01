<?php
/**
 * Класс описыващий логику для шлюза Россельхозбанка
 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
 * @copyright (c) 03.09.2021, CreativeMotion
 * @version 1.0
 */

namespace YandexPay\Gateway;

class RBS_Rshbank extends RBS {

	protected $id = "rshb";
	protected $api_url = "https://rshb.rbsgate.com/payment/";
	protected $sandbox_api_url = "https://web.rbsuat.com/rshb/payment/";
	protected $login;
	protected $password;

	public function __construct( array $options = [] ) {
		parent::__construct( $options );

		$this->login    = $this->get_setting( 'rshb_login' );
		$this->password = $this->get_setting( 'rshb_password' );
	}
}