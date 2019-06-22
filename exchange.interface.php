<?php

interface Exchange {
	public function setPublicKey(String $key): void;
	public function setKeySecret(String $secret): void;
	public function getCurrencies(): getCurrenciesReturn;
	public function getMarkets(): getMarketsReturn;
	public function getMarket(String $pair): getMarketReturn;
	public function getBalance(String $currency): getBalanceReturn;
	public function getOrderBook(String $pair, int $max_results): getOrderBookReturn;
	public function getPairFee(String $pair): getPairFeeReturn;
	public function placeOrder(String $pair, String $type, float $amount, float $price): placeOrderReturn;
	public function cancelOrder(String $orderId): cancelOrderReturn;
	public function checkOrder(String $orderId): checkOrderReturn;
	public function getWithdrawFee(String $currency): getWithdrawFeeReturn;
	public function getTradeStatus(String $currency): getTradeStatusReturn;
	public function getWithdrawStatus(String $currency): getWithdrawStatusReturn;
	public function getDepositStatus(String $currency): getDepositStatusReturn;
	public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn;
	public function getDepositAddress(String $currency): getDepositAddressReturn;
	public function sendToBankAccount(String $currency, float $amount): sendToBankAccountReturn;
	public function sendToTradeAccount(String $currency, float $amount): sendToTradeAccountReturn;
}