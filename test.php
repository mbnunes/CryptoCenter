<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
 * Error Codes
 *
 * 100 = Market not found
 * 101 = Coin balance
 * 102 = Method parameters wrong
 * 200 = API Error
 * 300 = Coin not avaible to trade
 * 400 = Exchange integration error
 */

require_once "projeto.class.php";

try {
	$tmp = new Projeto();

	// Coinbene
	// $exchange = $tmp->getExchange("coinbene");
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// KuCoin
	// $exchange = $tmp->getExchange("kucoin");
	// $exchange->setPassphrase("KUCOIN-API-PASSPHRASE");

	// Real
	// $exchange->setPublicKey('API-KEY');
	// $exchange->setKeySecret('API-SECRET');

	// Sandbox
	// $exchange->setPublicKey('API-KEY');
	// $exchange->setKeySecret('API-SECRET');
	// $exchange->setSandbox();

	// BleuTrade
	// $exchange = $tmp->getExchange("bleutrade");
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// HitBTC
	// $exchange = $tmp->getExchange('hitbtc');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// Liquid
	// $exchange = $tmp->getExchange('liquid');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// TradeSatoshi
	// $exchange = $tmp->getExchange('tradesatoshi');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// BitTrex
	// $exchange = $tmp->getExchange('bittrex');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// NovaExchange
	// $exchange = $tmp->getExchange('novaexchange');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// Brasiliex
	// $exchange = $tmp->getExchange('braziliex');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// Crex24
	// $exchange = $tmp->getExchange('crex24');
	// $exchange->setPublicKey("API-KEY");
	// $exchange->setKeySecret("API-SECRET");

	// $currencies = $exchange->getCurrencies()->currencies;
	// print_r($currencies);

	// $markets = $exchange->getMarkets()->markets;
	// print_r($markets);

	// $market = $exchange->getMarket("LTC/BTC");
	// print_r($market);

	// $market = $exchange->getMarket("LTC/BTC");
	// print_r($market);

	// $orderBook = $exchange->getOrderBook("LTC/BTC");
	// print_r($orderBook);

	// $fees = $exchange->getPairFee("LTC/BTC");
	// print_r($fees);

	// $withdrawFee = $exchange->getWithdrawFee("LTC");
	// print_r($withdrawFee);

	// $address = $exchange->getDepositAddress("LTC");
	// print_r($address);

	// $getTradeStatus = $exchange->getTradeStatus("LTC");
	// echo "getTradeStatus: " . (int) $getTradeStatus->status . "\n";

	// $getWithdrawStatus = $exchange->getWithdrawStatus("LTC");
	// echo "getWithdrawStatus: " . (int) $getWithdrawStatus->status . "\n";

	// $getDepositStatus = $exchange->getDepositStatus("LTC");
	// echo "getDepositStatus: " . (int) $getDepositStatus->status . "\n";

	// $order = $exchange->placeOrder("LTC/BTC", "BUY", 64, 0.00002400);
	// print_r($order);

	// $order = new StdClass();
	// $order->id = rand(11111111,99999999);

	// $check = $exchange->checkOrder($order->id);
	// // print_r($check);

	// $cancel = $exchange->cancelOrder((String) $order->id);
	// print_r($cancel);

	// $withdraw = $exchange->doWithdraw("LTC", "ADDRESS", (string) $balance->avaible, "Testwithdraw");
	// print_r($withdraw);
} catch (ProjetoException $e) {
	echo "Error: " . $e->getMessage() . "\n";
	echo "Code: " . $e->getCode() . "\n";
	echo "extraData: " . json_encode($e->getExtraData()) . "\n";
} catch (Exception $e) {
	echo "Error: " . $e->getMessage() . "\n";
	echo "Code: " . $e->getCode() . "\n";
}