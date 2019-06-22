
# PHP SDK for KuCoin API
> The detailed document [https://docs.kucoin.com](https://docs.kucoin.com), in order to receive the latest API change notifications, please `Watch` this repository.

[![Latest Version](https://img.shields.io/github/release/Kucoin/kucoin-php-sdk.svg)](https://github.com/Kucoin/kucoin-php-sdk/releases)
[![PHP Version](https://img.shields.io/packagist/php-v/kucoin/kucoin-php-sdk.svg?color=green)](https://secure.php.net)
[![Build Status](https://travis-ci.org/Kucoin/kucoin-php-sdk.svg?branch=master)](https://travis-ci.org/Kucoin/kucoin-php-sdk)
[![Total Downloads](https://poser.pugx.org/kucoin/kucoin-php-sdk/downloads)](https://packagist.org/packages/kucoin/kucoin-php-sdk)
[![License](https://poser.pugx.org/kucoin/kucoin-php-sdk/license)](LICENSE)
<!-- [![Packagist](https://img.shields.io/packagist/dt/Kucoin/kucoin-php-sdk.svg)](https://packagist.org/packages/kucoin/kucoin-php-sdk) -->
<!-- [![License](https://img.shields.io/packagist/l/kucoin/kucoin-php-sdk.svg)](LICENSE) -->

## Requirements

| Dependency | Requirement |
| -------- | -------- |
| [PHP](https://secure.php.net/manual/en/install.php) | `>=5.5.0` `PHP7+ is better` |
| [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) | `~6.0` |

## Install
> Install package via [Composer](https://getcomposer.org/).

```shell
composer require "kucoin/kucoin-php-sdk:~1.1.0"
```

## Usage

### Choose environment

| Environment | BaseUri |
| -------- | -------- |
| *Production* `DEFAULT` | https://openapi-v2.kucoin.com |
| *Sandbox* | https://openapi-sandbox.kucoin.com |

```php
// Switch to the sandbox environment
KuCoinApi::setBaseUri('https://openapi-sandbox.kucoin.com');
```

### Examples
> See the [test case](tests) for more examples.

#### Example of API `without` authentication

```php
use KuCoin\SDK\PublicApi\Time;

$api = new Time();
$timestamp = $api->timestamp();
var_dump($timestamp);
```

#### Example of API `with` authentication

```php
use KuCoin\SDK\Auth;
use KuCoin\SDK\PrivateApi\Account;
use KuCoin\SDK\Exceptions\HttpException;
use KuCoin\SDK\Exceptions\BusinessException;

$auth = new Auth('key', 'secret', 'passphrase');
$api = new Account($auth);

try {
    $result = $api->getList(['type' => 'main']);
    var_dump($result);
} catch (HttpException $e) {
    var_dump($e->getMessage());
} catch (BusinessException $e) {
    var_dump($e->getMessage());
}
```

#### Example of WebSocket feed

```php
use KuCoin\SDK\Auth;
use KuCoin\SDK\PrivateApi\WebSocketFeed;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;

$auth = null;
// Need to pass the Auth parameter when subscribing to a private channel($api->subscribePrivateChannel()).
// $auth = new Auth('key', 'secret', 'passphrase');
$api = new WebSocketFeed($auth);

$query = ['connectId' => uniqid('', true)];
$channels = [
    ['topic' => '/market/ticker:KCS-BTC'], // Subscribe multiple channels
    ['topic' => '/market/ticker:ETH-BTC'],
];

$api->subscribePublicChannels($query, $channels, function (array $message, WebSocket $ws, LoopInterface $loop) use ($api) {
    var_dump($message);

    // Unsubscribe the channel
    // $ws->send(json_encode($api->createUnsubscribeMessage('/market/ticker:ETH-BTC')));

    // Stop loop
    // $loop->stop();
}, function ($code, $reason) {
    echo "OnClose: {$code} {$reason}\n";
});
```

#### ⚡️Coroutine HTTP client for asynchronous IO
> See the [benchmark](examples/BenchmarkCoroutine.php), almost `20x` faster than `curl`.

```bash
pecl install swoole
composer require swlib/saber
```

```php
use KuCoin\SDK\PublicApi\Time;
use KuCoin\SDK\Http\SwooleHttp;

// Require PHP 7.1+ and Swoole 2.1.2+
// Require running in cli mode
go(function () {
    $api = new Time(null, new SwooleHttp));
    $timestamp = $api->timestamp();
    var_dump($timestamp);
});
```

### API list

<details>
<summary>KuCoin\SDK\PrivateApi\Account</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\Account::create() | YES | https://docs.kucoin.com/#create-an-account |
| KuCoin\SDK\PrivateApi\Account::getList() | YES | https://docs.kucoin.com/#list-accounts |
| KuCoin\SDK\PrivateApi\Account::getDetail() | YES | https://docs.kucoin.com/#get-an-account |
| KuCoin\SDK\PrivateApi\Account::getLedgers() | YES | https://docs.kucoin.com/#get-account-ledgers |
| KuCoin\SDK\PrivateApi\Account::getHolds() | YES | https://docs.kucoin.com/#get-holds |
| KuCoin\SDK\PrivateApi\Account::innerTransfer() | YES | https://docs.kucoin.com/#inner-transfer |
| KuCoin\SDK\PrivateApi\Account::getSubAccountUsers() | YES | https://docs.kucoin.com/#get-user-info-of-all-sub-accounts |
| KuCoin\SDK\PrivateApi\Account::getSubAccountDetail() | YES | https://docs.kucoin.com/#get-account-balance-of-a-sub-account |
| KuCoin\SDK\PrivateApi\Account::getSubAccountList() | YES | https://docs.kucoin.com/#get-the-aggregated-balance-of-all-sub-accounts-of-the-current-user |
| KuCoin\SDK\PrivateApi\Account::subTransfer() | YES | https://docs.kucoin.com/#transfer-between-master-account-and-sub-account |

</details>

<details>
<summary>KuCoin\SDK\PrivateApi\Deposit</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\Deposit::createAddress() | YES | https://docs.kucoin.com/#create-deposit-address |
| KuCoin\SDK\PrivateApi\Deposit::getAddress() | YES | https://docs.kucoin.com/#get-deposit-address |
| KuCoin\SDK\PrivateApi\Deposit::getDeposits() | YES | https://docs.kucoin.com/#get-deposit-list |
| KuCoin\SDK\PrivateApi\Deposit::getV1Deposits() | YES | https://docs.kucoin.com/#get-v1-historical-deposits-list |

</details>

<details>
<summary>KuCoin\SDK\PrivateApi\Fill</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\Fill::getList() | YES | https://docs.kucoin.com/#list-fills |
| KuCoin\SDK\PrivateApi\Fill::getRecentList() | YES | https://docs.kucoin.com/#recent-fills |

</details>

<details>
<summary>KuCoin\SDK\PrivateApi\Order</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\Order::create() | YES | https://docs.kucoin.com/#place-a-new-order |
| KuCoin\SDK\PrivateApi\Order::cancel() | YES | https://docs.kucoin.com/#cancel-an-order |
| KuCoin\SDK\PrivateApi\Order::cancelAll() | YES | https://docs.kucoin.com/#cancel-all-orders |
| KuCoin\SDK\PrivateApi\Order::getList() | YES | https://docs.kucoin.com/#list-orders |
| KuCoin\SDK\PrivateApi\Order::getV1List() | YES | https://docs.kucoin.com/#get-v1-historical-orders-list |
| KuCoin\SDK\PrivateApi\Order::getDetail() | YES | https://docs.kucoin.com/#get-an-order |
| KuCoin\SDK\PrivateApi\Order::getRecentList() | YES | https://docs.kucoin.com/#recent-orders |

</details>

<details>
<summary>KuCoin\SDK\PrivateApi\WebSocketFeed</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\WebSocketFeed::getPublicServer() | NO | https://docs.kucoin.com/#apply-connect-token |
| KuCoin\SDK\PrivateApi\WebSocketFeed::getPrivateServer() | YES | https://docs.kucoin.com/#apply-connect-token |
| KuCoin\SDK\PrivateApi\WebSocketFeed::subscribePublicChannel() | NO | https://docs.kucoin.com/#public-channels |
| KuCoin\SDK\PrivateApi\WebSocketFeed::subscribePublicChannels() | NO | https://docs.kucoin.com/#public-channels |
| KuCoin\SDK\PrivateApi\WebSocketFeed::subscribePrivateChannel() | YES | https://docs.kucoin.com/#private-channels |
| KuCoin\SDK\PrivateApi\WebSocketFeed::subscribePrivateChannels() | YES | https://docs.kucoin.com/#private-channels |

</details>

<details>
<summary>KuCoin\SDK\PrivateApi\Withdrawal</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PrivateApi\Withdrawal::getQuotas() | YES | https://docs.kucoin.com/#get-withdrawal-quotas |
| KuCoin\SDK\PrivateApi\Withdrawal::getList() | YES | https://docs.kucoin.com/#get-withdrawals-list |
| KuCoin\SDK\PrivateApi\Withdrawal::getV1List() | YES | https://docs.kucoin.com/#get-v1-historical-withdrawals-list |
| KuCoin\SDK\PrivateApi\Withdrawal::apply() | YES | https://docs.kucoin.com/#apply-withdraw |
| KuCoin\SDK\PrivateApi\Withdrawal::cancel() | YES | https://docs.kucoin.com/#cancel-withdrawal |

</details>

<details>
<summary>KuCoin\SDK\PublicApi\Currency</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PublicApi\Currency::getList() | NO | https://docs.kucoin.com/#get-currencies |
| KuCoin\SDK\PublicApi\Currency::getDetail() | NO | https://docs.kucoin.com/#get-currency-detail |
| KuCoin\SDK\PublicApi\Currency::getPrices() | NO | https://docs.kucoin.com/#get-fiat-price |

</details>

<details>
<summary>KuCoin\SDK\PublicApi\Symbol</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PublicApi\Symbol::getList() | NO | https://docs.kucoin.com/#get-symbols-list |
| KuCoin\SDK\PublicApi\Symbol::getTicker() | NO | https://docs.kucoin.com/#get-ticker |
| KuCoin\SDK\PublicApi\Symbol::getAllTickers() | NO | https://docs.kucoin.com/#get-all-tickers |
| KuCoin\SDK\PublicApi\Symbol::getAggregatedPartOrderBook() | NO | https://docs.kucoin.com/#get-part-order-book-aggregated |
| KuCoin\SDK\PublicApi\Symbol::getAggregatedFullOrderBook() | NO | https://docs.kucoin.com/#get-full-order-book-aggregated |
| KuCoin\SDK\PublicApi\Symbol::getAtomicFullOrderBook() | NO | https://docs.kucoin.com/#get-full-order-book-atomic |
| KuCoin\SDK\PublicApi\Symbol::getTradeHistories() | NO | https://docs.kucoin.com/#get-trade-histories |
| KuCoin\SDK\PublicApi\Symbol::getKLines() | NO | https://docs.kucoin.com/#get-klines |
| KuCoin\SDK\PublicApi\Symbol::get24HStats() | NO | https://docs.kucoin.com/#get-24hr-stats |
| KuCoin\SDK\PublicApi\Symbol::getMarkets() | NO | https://docs.kucoin.com/#get-market-list |

</details>

<details>
<summary>KuCoin\SDK\PublicApi\Time</summary>

| API | Authentication | Description |
| -------- | -------- | -------- |
| KuCoin\SDK\PublicApi\Time::timestamp() | NO | https://docs.kucoin.com/#server-time |

</details>

## Run tests
> Modify your API key in `phpunit.xml` first.

```shell
composer test
```

## License

[MIT](LICENSE)
