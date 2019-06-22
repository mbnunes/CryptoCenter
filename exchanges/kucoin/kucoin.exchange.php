<?php

require_once __DIR__ . "/sdk/vendor/autoload.php";

use KuCoin\SDK\Api as KuCoinApi;
use KuCoin\SDK\Auth as KuCoinAuth;
use KuCoin\SDK\PrivateApi\Account as KuCoinAccount;
use KuCoin\SDK\PrivateApi\Deposit as KuCoinDeposit;
use KuCoin\SDK\PrivateApi\Fill as KuCoinFill;
use KuCoin\SDK\PrivateApi\Order as KuCoinOrder;
use KuCoin\SDK\PrivateApi\Withdrawal as KuCoinWithdrawal;
use KuCoin\SDK\PublicApi\Currency as KuCoinCurrency;
use KuCoin\SDK\PublicApi\Symbol as KuCoinSymbol;

class Kucoin implements Exchange {

    private $public_key;
    private $key_secret;
    private $passphrase;
    private $cache = [];
    private $sandbox = false;
    // Kucoin variables
    private $auth;
    public $account;
    private $deposit;
    private $fill;
    private $order;
    private $withdrawal;
    private $currency;
    private $symbols;

    public function __construct() {
        $this->currency = new KuCoinCurrency();
        $this->symbols = new KuCoinSymbol();
    }

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret) && !empty($this->passphrase)) {
            $this->auth = new KuCoinAuth($this->public_key, $this->key_secret, $this->passphrase);
            $this->account = new KuCoinAccount($this->auth);
            $this->deposit = new KuCoinDeposit($this->auth);
            $this->fill = new KuCoinFill($this->auth);
            $this->order = new KuCoinOrder($this->auth);
            $this->withdraw = new KuCoinWithdrawal($this->auth);
        }
    }

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
        $this->loadApi();
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
        $this->loadApi();
    }

    public function setPassphrase(String $passphrase): void {
        $this->passphrase = $passphrase;
        $this->loadApi();
    }

    public function setSandbox() {
        KuCoinApi::setBaseUri('https://openapi-sandbox.kucoin.com');
    }

    public function getCurrencies(): getCurrenciesReturn {
        $return = new getCurrenciesReturn();
        foreach ($this->currency->getList() as $market) {
            $row = new currencyReturn();
            $row->shortName = $market['currency'];
            $row->fullName = $market['fullName'];
            $return->currencies[] = $row;
        }
        return $return;
    }

    public function getBalance(String $currency, $accountType = "trade"): getBalanceReturn {
        if (isset($this->caches['balances'])) {
            $balances = $this->caches['balances'];
        } else {
            $balances = $this->account->getList();
        }

        $return = new getBalanceReturn();
        $return->currency = $currency;

        $found = false;

        foreach ($balances as $value) {
            if ($value['type'] == $accountType && strtoupper($value['currency']) == strtoupper($currency)) {
                $return->avaible = $value['available'];
                $return->locked = $value['holds'];
                $return->total = $value['balance'];
                $found = true;
            }
        }

        return $return;
    }

    public function getOrderBook(String $pair, Int $maxResults = 20): getOrderBookReturn {
        $market = $this->getMarket($pair);

        $orderBook = $this->symbols->getAggregatedPartOrderBook($market->ticker, $maxResults);

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;

        foreach ($orderBook['bids'] as $key => $bid) {
            $order = new Order();
            $order->amount = $bid[1];
            $order->price = $bid[0];
            $return->bids[] = $order;
        }

        foreach ($orderBook['asks'] as $key => $ask) {
            $order = new Order();
            $order->amount = $ask[1];
            $order->price = $ask[0];
            $return->asks[] = $order;
        }

        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $withdraw = $this->withdraw->apply([
            'currency' => $currency,
            'address' => $address,
            'amount' => $amount,
            'memo' => $comment,
        ]);
        $fee = $this->getWithdrawFee($currency);

        $return = new doWithdrawReturn();
        $return->id = $withdraw['withdrawalId'];
        $return->currency = $currency;
        $return->fee = $fee->fee;
        $return->address = $address;
        $return->amount = $amount;
        $return->comment = $comment;

        return new doWithdrawReturn();
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $address = $this->deposit->getAddress($currency);
        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = $address['address'];
        $return->identification = $address['memo'];
        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $markets = $this->symbols->getList();
        }

        $ret = [];
        $tmp = [];
        if (!empty($markets)) {
            foreach ($markets as $symbol) {
                $pair = "{$symbol['baseCurrency']}/{$symbol['quoteCurrency']}";
                $ret[] = $pair;
                $tmp[$pair] = $symbol;
            }
        }

        $return = new getMarketsReturn();
        $return->markets = $ret;

        if (!isset($this->caches['markets'])) {
            $this->caches['markets'] = $tmp;
        }

        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        if (!isset($this->caches['markets'])) {
            $this->getMarkets();
        }

        if (!isset($this->caches['markets'][$pair])) {
            throw new CryptoCenterException("Market {$pair} not found", 100, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair]);
        }

        $market = $this->caches['markets'][$pair];

        $return = new getMarketReturn();
        $return->pair = $pair;
        $return->ticker = $market['symbol'];

        $return->ask_fee = 0.1; // Fixed trade fee
        $return->bid_fee = 0.1; // Fixed trade fee

        $return->min_amount = $market['baseMinSize'];

        return $return;
    }

    public function getPairFee(String $pair): getPairFeeReturn {
        $market = $this->getMarket($pair);

        $return = new getPairFeeReturn();
        $return->pair = $pair;
        $return->ask_fee = $market->ask_fee;
        $return->bid_fee = $market->bid_fee;

        return $return;
    }

    public function placeOrder(String $pair, String $type, float $amount, float $price): placeOrderReturn {
        if (!in_array(strtoupper($type), ['BUY', 'SELL'])) {
            throw new CryptoCenterException("Wrong order type", 102, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price]);
        }

        $typeFinal = (strtoupper($type) == "BUY" ? 'buy' : "sell");

        $market = $this->getMarket($pair);

        $data = [
            'clientOid' => uniqid(),
            'side' => $typeFinal,
            'symbol' => $market->ticker,
            'type' => 'limit',
            'price' => $price,
            'size' => $amount,
        ];

        $order = $this->order->create($data);

        $return = new placeOrderReturn();
        $return->id = $order['orderId'];
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $cancel = $this->order->cancel($orderId);

        $return = new cancelOrderReturn();
        $return->id = $orderId;
        $return->status = true;

        return $return;
    }

    public function getOrderStatus($orderDetails = []) {
        if (!$orderDetails['isActive']) {
            return ($orderDetails['size'] == $orderDetails['dealSize'] ? OrderStatus::FILLED : OrderStatus::CANCELLED);
        }

        if ($orderDetails['dealSize'] > 0 && $orderDetails['dealSize'] < $orderDetails['size']) {
            return OrderStatus::PARTIALFILLED;
        }

        if ($orderDetails['dealSize'] > 0 && $orderDetails['dealSize'] == $orderDetails['size']) {
            return OrderStatus::FILLED;
        }

        return OrderStatus::UNFILLED;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        if (empty($this->caches['markets'])) {
            $this->getMarkets();
        }

        $info = $this->order->getDetail($orderId);
        $status = $this->getOrderStatus($info);

        if (!isset($this->caches['markets'])) {
            $this->getMarkets();
        }

        $returnPair = false;
        foreach ($this->caches['markets'] as $pair => $data) {
            if (!$returnPair && $data['symbol'] == $info['symbol']) {
                $returnPair = $pair;
            }
        }

        $return = new checkOrderReturn();
        $return->id = $orderId;
        $return->pair = $returnPair;
        $return->type = ($info['side'] == "buy" ? "BUY" : "SELL");
        $return->amount = $info['size'];
        $return->filled = $info['dealSize'];
        $return->price = $info['price'];
        $return->status = $status;

        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $quote = $this->withdraw->getQuotas($currency);

        $return = new getWithdrawFeeReturn();
        $return->min = $quote['withdrawMinSize'];
        $return->fee = $quote['withdrawMinFee'];

        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $return = new getTradeStatusReturn();

        if (!$withdraw = $this->getWithdrawStatus($currency)->status) {
            return $return;
        }

        if (!$deposit = $this->getDepositStatus($currency)->status) {
            return $return;
        }

        if ($currency == "BTC") {
            $toCoin = "USDT";
        } else if ($currency == "USDT") {
            $toCoin = "USDT";
            $currency = "BTC";
        } else {
            $toCoin = "BTC";
        }

        $orderBook = $this->getOrderBook("{$currency}/{$toCoin}");

        if (count($orderBook->asks) && count($orderBook->bids)) {
            $return->status = true;
        }

        return $return;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $info = $this->currency->getDetail($currency);

        $return = new getWithdrawStatusReturn();
        $return->status = $info['isWithdrawEnabled'];

        return $return;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $info = $this->currency->getDetail($currency);

        $return = new getDepositStatusReturn();
        $return->status = $info['isDepositEnabled'];

        return $return;
    }

    public function getTradeAccountId(String $currency) {
        $account = $this->account->getList(['currency' => $currency, 'type' => 'trade']);

        if (empty($account)) {
            $account = $this->account->create("trade", $currency);

            if (!empty($account['id'])) {
                $tradeAccountId = $account['id'];
            }
        } else {
            $tradeAccountId = $account[0]['id'];
        }

        return $tradeAccountId;
    }

    public function getBankAccountId(String $currency) {
        $account = $this->account->getList(['currency' => $currency, 'type' => 'main']);

        if (empty($account)) {
            $account = $this->account->create("main", $currency);

            if (!empty($account['id'])) {
                $mainAccountId = $account['id'];
            }
        } else {
            $mainAccountId = $account[0]['id'];
        }

        return $mainAccountId;
    }

    public function sendToBankAccount(String $currency, float $amount): sendToBankAccountReturn {
        $mainAccountId = $this->getBankAccountId($currency);
        $tradeAccountId = $this->getTradeAccountId($currency);

        $return = new sendToBankAccountReturn();

        try {
            if ("2019-08-28 59:59:59" > date("Y-m-d H:i:s")) {
                $transfer = $this->account->innerTransfer(uniqid(), $tradeAccountId, $mainAccountId, $amount);
            } else {
                $transfer = $this->account->innerTransferV2(uniqid(), $currency, "trade", "main", $amount);
            }

            $return->id = $transfer['orderId'];
        } catch (Exception $e) {
            $return->status = false;
        }

        return $return;
    }

    public function sendToTradeAccount(String $currency, float $amount): sendToTradeAccountReturn {
        $mainAccountId = $this->getBankAccountId($currency);
        $tradeAccountId = $this->getTradeAccountId($currency);

        $return = new sendToTradeAccountReturn();

        try {
            if ("2019-08-28 59:59:59" > date("Y-m-d H:i:s")) {
                $transfer = $this->account->innerTransfer(uniqid(), $mainAccountId, $tradeAccountId, $amount);
            } else {
                $transfer = $this->account->innerTransferV2(uniqid(), $currency, "main", "trade", $amount);
            }

            $return->id = $transfer['orderId'];
        } catch (Exception $e) {
            $return->status = false;
        }


        return $return;
    }

}
