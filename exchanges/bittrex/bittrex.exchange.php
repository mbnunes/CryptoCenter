<?php

include_once 'sdk.php';

class Bittrex implements Exchange {

    private $public_key, $key_secret, $caches;

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->api = new BittrexSdk($this->public_key, $this->key_secret);
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

    private function pairToApi($pair) {
        return implode("-", array_reverse(explode('/', $pair)));
    }

    private function pairToArbiBot($pair) {
        return implode("/", array_reverse(explode('-', $pair)));
    }

    public function getCurrencies(): getCurrenciesReturn {
        $return = new getCurrenciesReturn();
        foreach ($this->api->getCurrencies() as $currency) {
            $row = new currencyReturn();
            $row->shortName = $currency->Currency;
            $row->fullName = $currency->CurrencyLong;
            $return->currencies[] = $row;
        }
        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        $tmp = [];
        $return = new getMarketsReturn();
        if (empty($this->caches['markets'])) {
            foreach ($this->api->getMarkets() as $market) {
                $tmp[] = $this->pairToArbiBot($market->MarketName);
            }
            $this->caches['markets'] = $tmp;
        } else {
            $tmp = $this->caches['markets'];
        }
        $return->markets = $tmp;
        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        $return = new getMarketReturn();
        if (empty($this->caches['marketsSummary'][$pair])) {
            $market = $this->api->getMarketSummary($this->pairtoApi($pair));
            $this->caches['marketsSummary'][$pair] = $market;
        } else {
            $market = $this->caches['marketsSummary'][$pair];
        }
        $return->pair = $pair;
        $return->ticker = $market[0]->MarketName;
        $return->ask_fee = 0.25;
        $return->bid_fee = 0.25;
        $return->min_amount = -1;
        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $return = new getBalanceReturn();
        $balance = $this->api->getBalance($currency);
        $return->currency = $currency;
        $return->avaible = (float) $balance->Available;
        $return->locked = (float) $balance->Balance - $balance->Available;
        $return->total = (float) $balance->Balance;
        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $book = $this->api->getOrderBook($this->pairtoApi($pair), 'both');
        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $this->getMarket($pair)->ticker;

        foreach ($book->buy as $key => $bid) {
            if ($key >= $max_results) {
                continue;
            }
            $order = new Order();
            $order->amount = $bid->Quantity;
            $order->price = $bid->Rate;
            $return->bids[] = $order;
        }

        foreach ($book->sell as $key => $ask) {
            if ($key >= $max_results) {
                continue;
            }
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
        $return = new placeOrderReturn();
        $callFunction = strtolower($type) . "Limit";
        $order = $this->api->{$callFunction}($this->pairToApi($pair), $amount, $price);

        if (!empty($order->uuid)) {
            $return->id = $order->uuid;
            $return->pair = $pair;
            $return->type = $type;
            $return->amount = $amount;
            $return->price = $price;
        }
        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $return = new cancelOrderReturn();
        $order = $this->api->cancel($orderId);
        if (!empty($order->uuid)) {
            $return->id = $order->uuid;
            $return->status;
        }
        return $return;
    }

    private function getOrderStatus($orderDetails) {
        if ($orderDetails->Closed) {
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
        $return = new checkOrderReturn();
        $order = $this->api->getOrder($orderId);

        if (!empty($order) && !empty($order->OrderUuid)) {
            $return->id = $order->OrderUuid;
            $return->pair = $this->pairToArbiBot($order->Exchange);
            $return->type = str_replace("LIMIT_", '', $order->OrderType);
            $return->amount = $order->Quantity;
            $return->filled = $order->Quantity - $order->QuantityRemaining;
            $return->price = $order->Price;
            $return->status = $this->getOrderStatus($order);
        }

        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();
        if (empty($this->caches['currencies'])) {
            foreach ($this->api->getCurrencies() as $key => $value) {
                $this->caches['currencies'][$value->Currency] = $value;
            }
        }
        $return->min = $this->caches['currencies'][$currency]->TxFee;
        $return->fee = $this->caches['currencies'][$currency]->TxFee;
        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
//        gambiarra
        return new getTradeStatusReturn();
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
//        gambiarra
        return new getWithdrawStatusReturn();
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
//        gambiarra
        return new getDepositStatusReturn();
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = null): doWithdrawReturn {
        $return = new doWithdrawReturn();
        $withdraw = $this->api->withdraw($currency, $amount, $address, $comment);
        $fee = $this->getWithdrawFee($currency);

        if (!empty($withdraw->uuid)) {
            $return->id = $withdraw->uuid;
            $return->currency = $currency;
            $return->fee = $fee->fee;
            $return->address = $address;
            $return->amount = $amount;
            $return->comment = $comment;
        }

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $return = new getDepositAddressReturn();
        $address = $this->api->getDepositAddress($currency);
        $return->currecy = $address->Currency;
        $return->address = $address->Address;
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
