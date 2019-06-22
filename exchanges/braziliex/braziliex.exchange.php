<?php

include_once 'sdk.php';

class Braziliex implements Exchange {

    private $public_key, $key_secret, $caches;

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->api = new BraziliexSdk($this->public_key, $this->key_secret);
        }
    }

    public function getCurrencies(): getCurrenciesReturn {
        $return = new getCurrenciesReturn();
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

    private function pairToApi($pair) {
        return strtolower(implode("_", explode('/', $pair)));
    }

    private function apiToProjeto($pair) {
        return strtoupper(implode("/", explode('_', $pair)));
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $return = $this->caches['markets'];
        } else {
            $tmp = [];
            $return = new getMarketsReturn();
            foreach ($this->api->getTickers() as $ticker => $market) {
                if ($market['active']) {
                    $tmp[] = $this->apiToProjeto($ticker);
                }
            }
            $return->markets = $tmp;
            $this->caches['markets'] = $return;
        }
        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        $market = $this->api->getTicker($this->pairToApi($pair));

        $return = new getMarketReturn();
        $return->pair = $pair;
        $return->ticker = $market['market'];
        $return->ask_fee = 0.5;
        $return->bid_fee = 0.5;
        $return->min_amount = -1;
        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $balanceTmp = $this->api->balances();

        if (isset($balanceTmp['balance']) && isset($balanceTmp['balance'][strtolower($currency)])) {
            $balance = $balanceTmp['balance'][strtolower($currency)];
        } else {
            $balance = 0.0;
        }

        $return = new getBalanceReturn();
        $return->currency = strtoupper($currency);
        $return->avaible = (float) $balance;
        $return->locked = (float) 0;
        $return->total = (float) $balance;
        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $book = $this->api->getOrderBook($this->pairtoApi($pair));

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $this->getMarket($pair)->ticker;

        foreach ($book['bids'] as $key => $bid) {
            if ($key >= $max_results) {
                continue;
            }
            $order = new Order();
            $order->amount = $bid['amount'];
            $order->price = $bid['price'];
            $return->bids[] = $order;
        }

        foreach ($book['asks'] as $key => $ask) {
            if ($key >= $max_results) {
                continue;
            }
            $order = new Order();
            $order->amount = $ask['amount'];
            $order->price = $ask['price'];
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

        $callFunction = strtolower($type);
        $order = $this->api->{$callFunction}($this->pairToApi($pair), $amount, $price);

        if (!empty($order['order_number'])) {
            $return->id = $order['order_number'];
            $return->pair = $pair;
            $return->type = $type;
            $return->amount = $amount;
            $return->price = $price;
        }
        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        return new cancelOrderReturn();
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        return new checkOrderReturn();
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $tmp = $this->api->getCurrencies();

        $return = new getWithdrawFeeReturn();

        if (isset($tmp[strtolower($currency)])) {
            $return->min = $tmp[strtolower($currency)]['MinWithdrawal'];
            $return->fee = $tmp[strtolower($currency)]['txWithdrawalFee'];
        } else {
            $return->min = -1;
            $return->fee = -1;
        }

        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $tmp = $this->api->getCurrencies();

        $return = new getTradeStatusReturn();

        if (isset($tmp[strtolower($currency)])) {
            $return->status = $tmp[strtolower($currency)]['active'];
        }

        return $return;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $tmp = $this->api->getCurrencies();

        $return = new getWithdrawStatusReturn();

        if (isset($tmp[strtolower($currency)])) {
            $return->status = $tmp[strtolower($currency)]['active'];
        }

        return $return;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $tmp = $this->api->getCurrencies();

        $return = new getDepositStatusReturn();

        if (isset($tmp[strtolower($currency)])) {
            $return->status = $tmp[strtolower($currency)]['active'];
        }

        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = null): doWithdrawReturn {
        $return = new doWithdrawReturn();
        // $withdraw = $this->api->withdraw($currency, $amount, $address, $comment);
        // $fee = $this->getWithdrawFee($currency);
        // if (!empty($withdraw->uuid)) {
        //     $return->id = $withdraw->uuid;
        //     $return->currency = $currency;
        //     $return->fee = $fee->fee;
        //     $return->address = $address;
        //     $return->amount = $amount;
        //     $return->comment = $comment;
        // }

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $address = $this->api->getDepositAddress($currency);

        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = $address['deposit_address'];
        $return->identification = $address['payment_id'];
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
