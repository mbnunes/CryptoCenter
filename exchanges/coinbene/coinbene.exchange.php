<?php

class Coinbene implements Exchange {

    private $public_key;
    private $key_secret;
    private $api;
    private $caches = [];

    public function __construct() {
        require_once __DIR__ . "/src/sdk/manbi_sdk.php";
    }

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->api = new manBi($this->public_key, $this->key_secret);
        }
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
        $this->loadApi();
    }

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
        $this->loadApi();
    }

    public function getCurrencies(): getCurrenciesReturn {
        $return = new getCurrenciesReturn();
        foreach ($this->getMarkets()->markets as $market) {
            $ticker = explode("/", $market);
            $row = new currencyReturn();
            $row->shortName = $ticker[0];
            $row->fullName = $ticker[0];
            $return->currencies[] = $row;
        }
        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $markets = $this->api->markets();

            if ($markets->status != "ok") {
                throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__]);
            }
        }

        $ret = [];
        $tmp = [];
        if (!empty($markets->symbol)) {
            foreach ($markets->symbol as $symbol) {
                $pair = "{$symbol->baseAsset}/{$symbol->quoteAsset}";
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
            throw new ProjetoException("Market {$pair} not found", 100, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair]);
        }

        $market = $this->caches['markets'][$pair];

        $return = new getMarketReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;
        $return->ask_fee = $market->takerFee * 100;
        $return->bid_fee = $market->makerFee * 100;
        $return->min_amount = $market->minQuantity;

        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        if (isset($this->caches['balances'])) {
            $balances = $this->caches['balances'];
        } else {
            $balances = $this->api->balance();

            if ($balances->status != "ok") {
                throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__]);
            }
        }

        $return = new getBalanceReturn();
        $return->currency = $currency;

        $found = false;

        foreach ($balances->balance as $value) {
            if (strtoupper($value->asset) == strtoupper($currency)) {
                $return->avaible = $value->available;
                $return->locked = $value->reserved;
                $return->total = $value->total;
                $found = true;
            }
        }

        if (!$found) {
            throw new ProjetoException("Coin balance not found", 101, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'currency' => $currency]);
        }

        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $market = $this->getMarket($pair);
        $orderBook = $this->api->orderbook($market->ticker, $max_results);

        if ($orderBook->status != "ok") {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'currency' => $currency]);
        }

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;

        foreach ($orderBook->orderbook->bids as $key => $bid) {
            $order = new Order();
            $order->amount = $bid->quantity;
            $order->price = $bid->price;
            $return->bids[] = $order;
        }

        foreach ($orderBook->orderbook->asks as $key => $ask) {
            $order = new Order();
            $order->amount = $ask->quantity;
            $order->price = $ask->price;
            $return->asks[] = $order;
        }

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
            throw new ProjetoException("Wrong order type", 102, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price]);
        }

        $typeFinal = (strtoupper($type) == "BUY" ? 'buy-limit' : "sell-limit");

        $market = $this->getMarket($pair);

        $order = $this->api->orders($market->ticker, $typeFinal, number_format($price, 8, ".", ""), $amount);

        if ($order->status != "ok") {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price, 'response' => $order]);
        }

        $return = new placeOrderReturn();
        $return->id = $order->orderid;
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $cancel = $this->api->cancel($orderId);

        if ($cancel->status != "ok") {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'orderId' => $orderId, 'response' => $info]);
        }

        $return = new cancelOrderReturn();
        $return->id = $orderId;
        $return->status = true;

        return $return;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $info = $this->api->orderInfo($orderId);

        if ($info->status != "ok") {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'orderId' => $orderId, 'response' => $info]);
        }

        $status = [
            'filled' => OrderStatus::FILLED,
            'unfilled' => OrderStatus::UNFILLED,
            'partialFilled' => OrderStatus::PARTIALFILLED,
            'canceled' => OrderStatus::CANCELLED,
            'partialCanceled' => OrderStatus::PARTIALCANCELLED,
        ];

        if (empty($this->caches['markets'])) {
            $this->getMarkets();
        }

        $returnPair = false;
        foreach ($this->caches['markets'] as $pair => $data) {
            if (!$returnPair && $data->ticker == $info->order->symbol) {
                $returnPair = $pair;
            }
        }

        $return = new checkOrderReturn();
        $return->id = $orderId;
        $return->pair = $returnPair;
        $return->type = ($info->order->type == "buy-limit" ? "BUY" : "SELL");
        $return->amount = $info->order->orderquantity;
        $return->filled = $info->order->filledquantity;
        $return->price = $info->order->price;
        $return->status = $status[$info->order->orderstatus];

        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        if (empty($this->caches['fees'])) {
            $this->caches['fees'] = json_decode(file_get_contents(__DIR__ . "/fees.json"), true);
        }

        $return = new getWithdrawFeeReturn();

        if (isset($this->caches['fees'][$currency])) {
            $return->min = $this->caches['fees'][$currency]['min'];
            $return->fee = $this->caches['fees'][$currency]['fee'];
        } else {
            throw new ProjetoException("Coin {$currency} not avaible to trade", 300, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'currency' => $currency]);
        }

        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $withdraw = $this->api->withdrawApply($amount, $currency, $address, $comment);
        $fee = $this->getWithdrawFee($currency);

        if ($withdraw->status != "ok") {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'currency' => $currency, 'address' => $address, 'amount' => $amount, "comment" => $comment, 'response' => $withdraw]);
        }

        $return = new doWithdrawReturn();
        $return->id = $withdraw->withdrawId;
        $return->currency = $currency;
        $return->fee = $fee->fee;
        $return->address = $address;
        $return->amount = $amount;
        $return->comment = $comment;

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = null;
        $return->identification = null;
        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $return = new getTradeStatusReturn();
        $return->status = true;
        return $return;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $return = new getWithdrawStatusReturn();
        $return->status = true;
        return $return;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $return = new getDepositStatusReturn();
        $return->status = true;
        return $return;
    }

    public function sendToBankAccount(String $currency, float $amount): sendToBankAccountReturn {
        $return = new sendToBankAccountReturn();
        return $return;
    }

    public function sendToTradeAccount(String $currency, float $amount): sendToTradeAccountReturn {
        $return = new sendToTradeAccountReturn();
        return $return;
    }

}
