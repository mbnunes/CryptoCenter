<?php

include_once 'sdk.php';

class Crex24 implements Exchange {

    private $public_key, $key_secret, $caches;

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->api = new Crex24Sdk($this->public_key, $this->key_secret);
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
        return strtoupper(implode("_", array_reverse(explode('/', $pair))));
    }

    private function apiToProjeto($pair) {
        return strtoupper(implode("/", array_reverse(explode('_', $pair))));
    }

    public function getCurrencies(): getCurrenciesReturn {
        if (isset($this->caches['currencies'])) {
            return $this->caches['currencies'];
        } else {
            $tmp = $this->api->getCurrencies();

            $return = new getCurrenciesReturn();

            foreach ($tmp->Currencies as $currency) {
                $row = new currencyReturn();
                $row->shortName = $currency->ShortName;
                $row->fullName = $currency->Name;
                $return->currencies[] = $row;
            }

            $this->caches['currencies'] = $return;
        }

        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        $tmp = [];
        $return = new getMarketsReturn();
        if (empty($this->caches['getMarkets'])) {
            $ret = $this->api->getTickers();
            foreach ($ret->Tickers as $ticker => $market) {
                $tmp[] = $this->apiToProjeto($market->PairName);
            }
            $this->caches['getMarkets'] = $tmp;
        } else {
            $tmp = $this->caches['getMarkets'];
        }
        $return->markets = $tmp;
        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        $return = new getMarketReturn();
        $return->pair = $pair;
        $return->ticker = $this->pairToApi($pair);
        $return->ask_fee = 0.1;
        $return->bid_fee = 0.1;
        $return->min_amount = -1;
        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $balanceTmp = $this->api->balance($currency);

        $return = new getBalanceReturn();
        $return->currency = strtoupper($currency);
        $return->avaible = (float) $balanceTmp->Balances[0]->AvailableBalances;
        $return->locked = (float) $balanceTmp->Balances[0]->InOrderBalances;
        $return->total = (float) $balanceTmp->Balances[0]->AvailableBalances + $balanceTmp->Balances[0]->InOrderBalances;
        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $book = $this->api->getOrderBook($this->pairtoApi($pair));

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $this->getMarket($pair)->ticker;

        foreach ($book->BuyOrders as $key => $bid) {
            if ($key >= $max_results) {
                continue;
            }
            $order = new Order();
            $order->amount = $bid->CoinCount;
            $order->price = $bid->CoinPrice;
            $return->bids[] = $order;
        }

        foreach ($book->SellOrders as $key => $ask) {
            if ($key >= $max_results) {
                continue;
            }
            $order = new Order();
            $order->amount = $ask->CoinCount;
            $order->price = $ask->CoinPrice;
            $return->asks[] = $order;
        }

        return $return;
    }

    public function getPairFee(String $pair): getPairFeeReturn {
        $market = $this->getMarket($pair);

        $return = new getPairFeeReturn();
        $return->pair = $pair;
        $return->ask_fee = 0.1;
        $return->bid_fee = 0.1;

        return $return;
    }

    public function placeOrder(String $pair, String $type, float $amount, float $price): placeOrderReturn {
        $return = new placeOrderReturn();

        $callFunction = strtolower($type);
        $order = $this->api->{$callFunction}($this->pairToApi($pair), $amount, $price);

        if (empty($order->Error)) {
            $return->id = $order->Id;
            $return->pair = $pair;
            $return->type = $type;
            $return->amount = $amount;
            $return->price = $price;
        }
        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $tmp = $this->api->cancelOrder($orderId);
        $ret = new cancelOrderReturn();
        $ret->id = $orderId;

        if (!empty($tmp->Success) && $tmp->Success) {
            $ret->status = true;
        }

        return $ret;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $ret = new checkOrderReturn();

        $tmp = $this->api->orderStatus($orderId);

        if (empty($tmp->Error)) {
            $filled = 0;

            foreach ($tmp->Trades as $trade) {
                $filles += $trade->CoinCount;
            }

            $ret->id = $orderId;
            $ret->pair = $this->apiToProjeto($tmp->CurrentOrder->PairName);
            $ret->type = ($tmp->CurrentOrder->IsSell ? "SELL" : "BUY");
            $ret->amount = $tmp->CurrentOrder->CoinCount;
            $ret->filled = $filled;
            $ret->price = $tmp->CurrentOrder->CoinPrice;
            $ret->status = $tmp->CurrentOrder->IsCloseRequired;
        }

        return $ret;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();
        $tmp = $this->api->tradeStatus($currency);
        $return->min = $tmp->Currencies[0]->MinimalWithdraw;
        $return->fee = $tmp->Currencies[0]->TxFee;
        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $tmp = $this->api->tradeStatus($currency);
        $return = new getTradeStatusReturn();
        $return->status = ($tmp->Currencies[0]->Disabled || $tmp->Currencies[0]->Delisted || $tmp->Currencies[0]->Frozen ? false : true);
        return $return;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $return = new getWithdrawStatusReturn();
        $tmp = $this->api->tradeStatus($currency);
        $return->status = ($tmp->Currencies[0]->Disabled || $tmp->Currencies[0]->Delisted || $tmp->Currencies[0]->Frozen ? false : true);
        return $return;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $address = $this->api->getDepositAddress($currency);

        $return = new getDepositStatusReturn();

        if (empty($address->Error) && empty($address->Message)) {
            $return->status = true;
        }

        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = null): doWithdrawReturn {
        $return = new doWithdrawReturn();

        $withdraw = $this->api->withdraw($currency, $amount, $address, $comment);

        if (!empty($withdraw->OutId)) {
            $fee = $this->getWithdrawFee($currency);
            $return->id = $withdraw->OutId;
            $return->currency = $currency;
            $return->fee = $fee->fee;
            $return->address = $address;
            $return->amount = $amount;
            $return->comment = $comment;
        }

        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $address = $this->api->getDepositAddress($currency);

        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
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
