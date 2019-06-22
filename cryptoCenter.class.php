<?php

class CryptoCenter {
	private $exchanges = [];

	public function __construct() {
		require_once __DIR__ . '/exceptions.class.php';
		require_once __DIR__ . '/orderStatus.enum.php';
		$returns = glob(__DIR__ . "/persists/*.return.php");
		foreach ($returns as $file) {
			require_once $file;
		}

		require_once 'exchange.interface.php';
		$exchanges = glob(__DIR__ . "/exchanges/*/*.exchange.php");

		foreach ($exchanges as $file) {
			$tmp      = explode("/", $file);
			$exchange = ucfirst(explode(".", $tmp[count($tmp) - 1])[0]);

			require_once $file;
			$this->exchanges[strtolower($exchange)] = new $exchange();

			if (!property_exists($this->exchanges[strtolower($exchange)], "public_key")) {
				throw new CryptoCenterException("Exchange {$exchange} has no private property \$public_key", 400, null, ['exchange' => $exchange]);
			}

			if (!property_exists($this->exchanges[strtolower($exchange)], "key_secret")) {
				throw new CryptoCenterException("Exchange {$exchange} has no private property \$key_secret", 400, null, ['exchange' => $exchange]);
			}
		}
	}

	public function getExchange(String $exchangeName): Exchange {
		if (empty($this->exchanges[strtolower($exchangeName)])) {
			die("Exchange {$exchangeName} not found");
		}

		return $this->exchanges[strtolower($exchangeName)];
	}
}