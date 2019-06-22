<?php

class Tradesatoshi implements Exchange {

    private $public_key;
    private $key_secret;
    private $api;
    private $caches = [];

    public function __construct() {
        require_once __DIR__ . "/sdk/tradesatoshi.class.php";
    }

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->api = new tradesatoshi_sdk($this->public_key, $this->key_secret);
        }
    }

    public function getCurrencies(): getCurrenciesReturn {
        if (isset($this->caches['currencies'])) {
            return $this->caches['currencies'];
        } else {
            $info = $this->api->GetCurrencies();

            $return = new getCurrenciesReturn();

            foreach ($info->result as $currency) {
                $row = new currencyReturn();
                $row->shortName = $currency->currency;
                $row->fullName = $currency->currencyLong;
                $return->currencies[] = $row;
            }

            $this->caches['currencies'] = $return;
        }

        return $return;
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
        $this->loadApi();
    }

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
        $this->loadApi();
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $markets = $this->api->GetMarketSummaries();

            if (!$markets->success) {
                throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => $markets->message]);
            }
        }

        $ret = [];
        $tmp = [];
        if (!empty($markets->result)) {
            foreach ($markets->result as $symbol) {
                $tmpMarket = explode("_", $symbol->market);

                $pair = "{$tmpMarket[0]}/{$tmpMarket[1]}";
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
        $return->ticker = $market->market;
        $return->ask_fee = 0.1; // Fixed value
        $return->bid_fee = 0.1; // Fixed value
        $return->min_amount = -1; // No minimum found

        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $return = new getBalanceReturn();
        $return->currency = $currency;

        $balance = $this->api->GetBalance($currency);

        if (!empty($balance->message) && $balance->message == "Somthing went wrong, please try again later.") {
            return $return;
        }

        if (!$balance->success) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => $balance->message]);
        }

        if (empty($balance->result)) {
            throw new ProjetoException("API Error", 101, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => "Currency not found"]);
        }

        $return->avaible = $balance->result->available;
        $return->locked = $balance->result->heldForTrades;
        $return->total = $balance->result->total;

        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $market = $this->getMarket($pair);
        $orderBook = $this->api->GetOrderBook($market->ticker, 'both', $max_results);

        if (!$orderBook->success) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => $balance->message]);
        }

        if (empty($orderBook->result)) {
            throw new ProjetoException("API Error", 101, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => "Pair orderbook not found"]);
        }

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;

        foreach ($orderBook->result->buy as $key => $bid) {
            $order = new Order();
            $order->amount = $bid->quantity;
            $order->price = $bid->rate;
            $return->bids[] = $order;
        }

        foreach ($orderBook->result->sell as $key => $ask) {
            $order = new Order();
            $order->amount = $ask->quantity;
            $order->price = $ask->rate;
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

        $typeFinal = (strtoupper($type) == "BUY" ? 'Buy' : "Sell");

        $market = $this->getMarket($pair);

        $order = $this->api->SubmitOrder($market->ticker, $typeFinal, $amount, $price);

        if (!$order->success || empty($order->result)) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price, 'response' => $order]);
        }

        $return = new placeOrderReturn();
        $return->id = $order->result->OrderId;
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $cancel = $this->api->CancelOrder("Single", $orderId);

        if (!$cancel->success || empty($cancel->result)) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'response' => $cancel]);
        }

        $return = new cancelOrderReturn();
        $return->id = $orderId;
        $return->status = true;

        return $return;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $info = $this->api->GetOrder($orderId);

        if (!$info->success || empty($info->result)) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'response' => $info]);
        }

        $status = [
            'Filled' => OrderStatus::FILLED,
            'unfilled' => OrderStatus::UNFILLED,
            'Partial' => OrderStatus::PARTIALFILLED,
            'canceled' => OrderStatus::CANCELLED,
            'partialCanceled' => OrderStatus::PARTIALCANCELLED,
        ];

        if (empty($this->caches['markets'])) {
            $this->getMarkets();
        }

        $returnPair = false;
        foreach ($this->caches['markets'] as $pair => $data) {
            if (!$returnPair && $data->ticker == $info->result->Market) {
                $returnPair = $pair;
            }
        }

        $return = new checkOrderReturn();
        $return->id = $orderId;
        $return->pair = $returnPair;
        $return->type = ($info->result->type == "Buy" ? "BUY" : "SELL");
        $return->amount = $info->result->Amount;
        $return->filled = $info->result->Amount - $info->result->Remaining;
        $return->price = $info->result->Rate;
        $return->status = $status[$info->result->orderstatus];

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
        $withdraw = $this->api->SubmitWithdraw($currency, $address, $amount);
        $fee = $this->getWithdrawFee($currency);

        if (!$withdraw->success) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => $withdraw->message]);
        }

        if (empty($withdraw->result)) {
            throw new ProjetoException("API Error", 101, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => "Withdraw error"]);
        }

        $return = new doWithdrawReturn();
        $return->id = $withdraw->WithdrawalId;
        $return->currency = $currency;
        $return->address = $address;
        $return->amount = $amount;
        $return->fee = $fee->fee;
        $return->comment = $comment;

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $wallet = $this->api->GetBalance($currency);

        if (!$wallet->success) {
            throw new ProjetoException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => $wallet->message]);
        }

        if (empty($wallet->result)) {
            throw new ProjetoException("API Error", 101, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'message' => "Currency not found"]);
        }

        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = $wallet->result->address;
        $return->identification = $wallet->result->paymentId;
        return $return;
    }

    public function doStatusCache(String $currency) {
        if (!isset($this->caches['status'])) {
            $this->caches['status'] = [];
        }

        $info = $this->api->GetCurrencies();

        foreach ($info->result as $key => $value) {
            $statusTrade = new getTradeStatusReturn();
            $statusTrade->status = (int) $value->isTipEnabled;

            $statusWithdraw = new getWithdrawStatusReturn();
            $statusWithdraw->status = ($value->status == "OK");

            $statusDeposit = new getDepositStatusReturn();
            $statusDeposit->status = ($value->status == "OK");

            $this->caches['status'][$value->currency] = [];
            $this->caches['status'][$value->currency]['trade'] = $statusTrade;
            $this->caches['status'][$value->currency]['withdraw'] = $statusWithdraw;
            $this->caches['status'][$value->currency]['deposit'] = $statusDeposit;
        }
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        if (isset($this->caches['status']) && isset($this->caches['status'][$currency])) {
            return $this->caches['status'][$currency]['trade'];
        }

        $this->doStatusCache($currency);

        return $this->caches['status'][$currency]['trade'];
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        if (isset($this->caches['status']) && isset($this->caches['status'][$currency])) {
            return $this->caches['status'][$currency]['withdraw'];
        }

        $this->doStatusCache($currency);

        return $this->caches['status'][$currency]['withdraw'];
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        if (isset($this->caches['status']) && isset($this->caches['status'][$currency])) {
            return $this->caches['status'][$currency]['deposit'];
        }

        $this->doStatusCache($currency);

        return $this->caches['status'][$currency]['deposit'];
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
