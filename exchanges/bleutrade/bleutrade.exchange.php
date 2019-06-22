<?php

class Bleutrade implements Exchange {

    private $public_key;
    private $key_secret;
    private $caches = [];

    public function __construct() {
        
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
    }

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
    }

    public function getCurrencies(): getCurrenciesReturn {
        if (isset($this->caches['currencies'])) {
            return $this->caches['currencies'];
        } else {
            $tmp = $this->publicApiCallV3("getassets");

            $return = new getCurrenciesReturn();

            foreach ($tmp as $currency) {
                $row = new currencyReturn();
                $row->shortName = $currency->Asset;
                $row->fullName = $currency->AssetLong;
                $return->currencies[] = $row;
            }

            $this->caches['currencies'] = $return;
        }

        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $markets = $this->publicApiCall('public/getmarkets');
        }

        $ret = [];
        $tmp = [];

        foreach ($markets as $symbol) {
            $pair = "{$symbol->MarketCurrency}/{$symbol->BaseCurrency}";
            $ret[] = $pair;
            $tmp[$pair] = $symbol;
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
        $return->ticker = $market->MarketName;
        $return->ask_fee = 0.25; // Fixed value
        $return->bid_fee = 0.25; // Fixed value
        $return->min_amount = $market->MinTradeSize;

        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $balances = $this->privateApiCall("account/getbalance?currency={$currency}");

        $return = new getBalanceReturn();
        $return->currency = $currency;
        $return->avaible = $balances->Available;
        $return->locked = $balances->Pending;
        $return->total = $balances->Balance;

        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 10): getOrderBookReturn {
        $market = $this->getMarket($pair);

        $orderBook = $this->publicApiCall("public/getorderbook?market={$market->ticker}&type=ALL&depth={$max_results}");

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;

        foreach ($orderBook->buy as $key => $bid) {
            $order = new Order();
            $order->amount = $bid->Quantity;
            $order->price = $bid->Rate;
            $return->bids[] = $order;
        }

        foreach ($orderBook->sell as $key => $ask) {
            $order = new Order();
            $order->amount = $ask->Quantity;
            $order->price = $ask->Rate;
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
            throw new CryptoCenterException("Wrong order type", 102, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price]);
        }

        $typeFinal = (strtoupper($type) == "BUY" ? 'buylimit' : "selllimit");

        $market = $this->getMarket($pair);

        $order = $this->privateApiCall("market/{$typeFinal}?market={$market->ticker}&rate={$price}&quantity={$amount}");
        print_r($order);

        $return = new placeOrderReturn();
        $return->id = $order->orderid;
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $cancel = $this->privateApiCall("market/cancel?orderid={$orderId}");

        $return = new cancelOrderReturn();
        $return->id = $orderId;
        $return->status = true;

        return $return;
    }

    public function getOrderStatus($orderDetails = []) {
        if ($orderDetails->Status != "OPEN") {
            return ($orderDetails->QuantityRemaining == $orderDetails->Quantity ? OrderStatus::FILLED : OrderStatus::CANCELLED);
        }

        if ($orderDetails->QuantityRemaining > 0 && $orderDetails->QuantityRemaining < $orderDetails->Quantity) {
            return OrderStatus::PARTIALFILLED;
        }

        if ($orderDetails->QuantityRemaining > 0 && $orderDetails->QuantityRemaining == $orderDetails->Quantity) {
            return OrderStatus::FILLED;
        }

        return OrderStatus::UNFILLED;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $info = $this->privateApiCall("account/getorder?orderid={$orderId}");
        $status = $this->getOrderStatus($info);

        if (!isset($this->caches['markets'])) {
            $this->getMarkets();
        }

        $returnPair = false;

        foreach ($this->caches['markets'] as $pair => $data) {
            if (!$returnPair && $data->MarketName == $info->Exchange) {
                $returnPair = $pair;
            }
        }

        $return = new checkOrderReturn();
        $return->id = $orderId;
        $return->pair = $returnPair;
        $return->type = $info->Type;
        $return->amount = $info->Quantity;
        $return->filled = $info->Quantity - $info->QuantityRemaining;
        $return->price = $info->Price;
        $return->status = $status;

        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();

        $xxx = $this->publicApiCallV3("getassets");

        $found = false;
        foreach ($xxx as $key => $value) {
            if ($value->Asset == $currency) {
                $return->min = $value->WithdrawTxFee;
                $return->fee = $value->WithdrawTxFee;
                $found = true;
            }
        }

        if (!$found) {
            throw new CryptoCenterException("Coin {$currency} not avaible to trade", 300, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'currency' => $currency]);
        }

        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $withdraw = $this->privateApiCall("account/withdraw?currency={$currency}&quantity={$amount}&address={$address}");
        $fee = $this->getWithdrawFee($currency);

        $return = new doWithdrawReturn();
        $return->id = null; // API Doesn't return Withdraw id, but why?
        $return->currency = $currency;
        $return->fee = $fee->fee;
        $return->address = $address;
        $return->amount = $amount;
        $return->comment = $comment;

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $tmp = $this->privateApiCall("account/getbalance?currency={$currency}");
        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = $tmp->CryptoAddress;
        $return->identification = null;
        return $return;
    }

    public function doStatusCache(String $currency) {
        if (!isset($this->caches['status'])) {
            $this->caches['status'] = [];
        }

        $info = $this->privateApiCall("account/getbalance?currency={$currency}");

        $statusTrade = new getTradeStatusReturn();
        $statusTrade->status = ($info->IsActive == "true");

        $statusWithdraw = new getWithdrawStatusReturn();
        $statusWithdraw->status = ($info->AllowWithdraw == "true");

        $statusDeposit = new getDepositStatusReturn();
        $statusDeposit->status = ($info->AllowDeposit == "true");

        $this->caches['status'][$currency] = [];
        $this->caches['status'][$currency]['trade'] = $statusTrade;
        $this->caches['status'][$currency]['withdraw'] = $statusWithdraw;
        $this->caches['status'][$currency]['deposit'] = $statusDeposit;
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

    public function publicApiCall($query) {
        $base_url = 'https://bleutrade.com/api/v2/';
        $uri = $base_url . $query;
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $execResult = curl_exec($ch);
        $result = json_decode($execResult);

        if ($result->success != "true") {
            throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'query' => $query, 'response' => $result]);
        }

        return $result->result;
    }

    public function publicApiCallV3($query) {
        $base_url = 'https://bleutrade.com/api/v3/public/';
        $uri = $base_url . $query;
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $execResult = curl_exec($ch);
        $result = json_decode($execResult);

        if ($result->success != "true") {
            throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'query' => $query, 'response' => $result]);
        }

        return $result->result;
    }

    public function privateApiCall($query) {
        $nonce = time();
        $base_url = "https://bleutrade.com/api/v2/";
        $uri = $base_url . $query . '&apikey=' . $this->public_key . '&nonce=' . $nonce;
        $sign = hash_hmac('sha512', $uri, $this->key_secret);
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //stop curl from echoing to screen
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:' . $sign));
        $execResult = curl_exec($ch);
        $result = json_decode($execResult);

        if ($result->success != "true") {
            throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'query' => $query, 'response' => $result]);
        }

        sleep(1);

        return $result->result;
    }

}
