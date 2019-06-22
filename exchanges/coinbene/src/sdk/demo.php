<?php
/**
 * Created by PhpStorm.
 * User: xiong.wei
 * Date: 2018/6/23
 * Time: 上午12:56
 *
 * Installation / 安装
 * composer require php-curl-class/php-curl-class
 *
 */


include './manbi_sdk.php';

$manbi = new manbi('28ef66701fb4cb9f6b19178ece182d19', 'ddd033a62c9c419ba42c29cef72cabc5');

// Get Order book
// 获取挂单
// print_r($manbi->orderbook('btcusdt', 200));

// Get latest market trades
// 获取成交记录
// print_r($manbi->trades('btcusdt',1));

// Get ticker
// 获取最新价(Ticker)
// print_r($manbi->tickers('btcusdt'));

// Get account balance
// 查询账户余额
print_r($manbi->balance());
exit();

// Get open orders
//获取当前委托
print_r($manbi->get_open_orders('btcusdt'));

// Cancel the order
// 取消订单
print_r($manbi->cancel('201806222312115410016271'));

// Place an order
// 下单
$price = 1; // Price 价格
$amount = 10; // Amount 数量
print_r($manbi->orders('btcusdt',"buy-limit", $price, $amount));
