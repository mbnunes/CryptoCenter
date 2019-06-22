<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Novaexchange implements Exchange {

    private $public_key;
    private $key_secret;
    private $cache = [];

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $return = new cancelOrderReturn();
        $cancel = $this->api_query("cancelorder/{$orderId}");
        if ($cancel->status == "ok") {
            $return->id = $orderId;
            $return->status = 1;
        }
        return $return;
    }

    public function getCurrencies(): getCurrenciesReturn {
        $return = new getCurrenciesReturn();
        return $return;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $return = new checkOrderReturn();
        $orders = $this->api_query("tradehistory");
        if ($orders->status == "success") {
            foreach ($orders->items as $order) {
                if ($order->orig_orderid != $orderId) {
                    continue;
                }
                $return->id = $order->orig_orderid;
                $return->pair = "{$order->fromcurrency}/{$order->tocurrency}";
                $return->type = $order->tradetype;
                $return->amount = $order->fromamount;
                $return->filled = $order->fromamount;
                $return->price = $order->price;
                $return->status = 1;
            }
        }


        if (empty($return->id)) {
            $orders = $this->api_query("myopenorders");
            if ($orders->status == "success") {
                foreach ($orders->items as $order) {
                    if ($order->orderid != $orderId) {
                        continue;
                    }
                    $return->id = $order->orderid;
                    $return->pair = "{$order->fromcurrency}/{$order->tocurrency}";
                    $return->type = $order->ordertype;
                    $return->amount = $order->fromamount;
                    $return->filled = 0;
                    $return->price = $order->price;
                    $return->status = 0;
                }
            }
        }
        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $return = new doWithdrawReturn();
        $data = [
            "currency" => $currency,
            "amount" => $amount,
            "address" => $address
        ];
        $withdraw = $this->api_query("withdraw/$currency", $data);
        if ($withdraw->status == "success") {
            $return->id = null;
            $return->currency = $currency;
            $return->address = $withdraw->address;
            $return->amount = $withdraw->amount;
            $return->fee = $withdraw->tx_fee;
            $return->comment = null;
        }
        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $return = new getBalanceReturn();
        $balance = $this->api_query("getbalance/{$currency}");
        if ($balance->status == "success") {
            $return->currency = $balance->balances[0]->currency;
            $return->avaible = $balance->balances[0]->amount;
            $return->locked = $balance->balances[0]->amount_trades;
            $return->total = $balance->balances[0]->amount_total;
        }
        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $return = new getDepositAddressReturn();
        if (empty($this->cache['getdepositaddress'][$currency])) {
            $addr = $this->api_query("getdepositaddress/{$currency}");
            if ($addr->status == "success") {
                $return->currecy = $currency;
                $return->address = $addr->address;
                $return->identification = null;
                $this->cache['getdepositaddress'][$currency] = $return;
            }
        } else {
            $return = $this->cache['getdepositaddress'][$currency];
        }
        return $return;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $return = new getDepositStatusReturn();
        $status = $this->cacheWalletStatus($currency);
        if ($status->status == "success") {
            $return->status = $status->coininfo->wallet_deposit;
        }
        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        $return = new getMarketReturn();
        if (empty($this->cache['getMarket'][$pair])) {
            $ticker = $this->pairToApi($pair);
            $info = $this->api_query("market/info/{$ticker}");
            if ($info->status == 'success') {
                $return->pair = $pair;
                $return->ticker = $ticker;
                $return->ask_fee = 0.2;
                $return->bid_fee = 0.2;
                $return->min_amount = -1;
                $this->cache['getMarket'][$pair] = $return;
            }
        } else {
            $return = $this->cache['getMarket'][$pair];
        }
        return $return;
    }

    private function pairToApi($pair) {
        $pair = explode("/", strtoupper($pair));
        return "{$pair[1]}_{$pair[0]}";
    }

    public function getMarkets(): getMarketsReturn {
        $return = new getMarketsReturn();
        if (empty($this->cache['markets'])) {
            $mkts = $this->api_query("markets");
            foreach ($mkts->markets as $value) {
                $return->markets[] = "{$value->currency}/{$value->basecurrency}";
            }
            $this->cache['markets'] = $return->markets;
        } else {
            $return->markets = $this->cache['markets'];
        }
        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 20): getOrderBookReturn {
        $return = new getOrderBookReturn();
        $ticker = $this->pairToApi($pair);
        $book = $this->api_query("market/openorders/{$ticker}/BOTH");
        if ($book->status == "success") {
            foreach ($book->sellorders as $key => $ask) {
                if ($key >= $max_results) {
                    continue;
                }
                $order = new Order();
                $order->amount = $ask->amount;
                $order->price = $ask->price;
                $return->asks[] = $order;
            }
            foreach ($book->buyorders as $key => $bid) {
                if ($key >= $max_results) {
                    continue;
                }
                $order = new Order();
                $order->amount = $bid->amount;
                $order->price = $bid->price;
                $return->bids[] = $order;
            }
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

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $return = new getTradeStatusReturn();
        $wallet = $this->cacheWalletStatus($currency);
        if ($wallet->status == "success") {
            if ($wallet->coininfo->wallet_deposit && $wallet->coininfo->wallet_withdrawal) {
                if ($currency == "BTC") {
                    $toCoin = "DOGE";
                } else if ($currency == "DOGE") {
                    $toCoin = "DOGE";
                    $currency = "BTC";
                } else {
                    $toCoin = "BTC";
                }
                $orderBook = $this->getOrderBook("{$toCoin}/{$currency}");
                if (count($orderBook->asks) && count($orderBook->bids)) {
                    $return->status = true;
                }
            }
        }
        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();
        $wallet = $this->cacheWalletStatus($currency);
        if ($wallet->status == "success") {
            $return->min = ($wallet->coininfo->tx_fee + $wallet->coininfo->wd_fee);
            $return->fee = ($wallet->coininfo->tx_fee + $wallet->coininfo->wd_fee);
        }
        return $return;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $return = new getWithdrawStatusReturn();
        $status = $this->cacheWalletStatus($currency);
        if ($status->status == "success") {
            $return->status = !$status->coininfo->wallet_status;
        }
        return $return;
    }

    public function cacheWalletStatus($currency) {
        if (empty($this->cache["walletstatus"][$currency])) {
            $wallet = $this->api_query("walletstatus/{$currency}");
            if ($wallet->status == "success") {
                $this->cache["walletstatus"][$currency] = $wallet;
            }
        } else {
            $wallet = $this->cache["walletstatus"][$currency];
        }
        return $wallet;
    }

    public function placeOrder(String $pair, String $type, float $amount, float $price): placeOrderReturn {
        $return = new placeOrderReturn();
        $data = [
            'tradebase' => 0,
            'tradetype' => strtoupper($type),
            'tradeprice' => $price,
            'tradeamount' => $amount
        ];
        $trade = $this->api_query("trade/" . $this->pairToApi($pair), $data);
        if ($trade->status == "success") {
            $return->id = $trade->tradeitems[0]->orderid;
            $return->pair = $pair;
            $return->type = $type;
            $return->amount = $amount;
            $return->price = $price;
        }
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

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
    }

    public function api_query($ENDPOINT, array $REQ = array()) {
//        $PUBLIC_API = array("markets", "market/info", "market/orderhistory", "market/openorders");
//        $PRIVATE_API = array("getbalances", "getbalance", "getdeposits", "getwithdrawals", "getnewdepositaddress", "getdepositaddress", "myopenorders", "myopenorders_market", "cancelorder", "withdraw", "trade", "tradehistory", "getdeposithistory", "getwithdrawalhistory", "walletstatus");
        // Init curl
        static $ch = null;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // remove those 2 line to secure after test.
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // PUBLIC or PRIVATE API
        if (substr(explode("/", $ENDPOINT)[0], 0, 6) == 'market') {
            $URL = "https://novaexchange.com/remote/v2/" . ($ENDPOINT) . "/";
            if ($REQ) {
                $URL .= '?' . http_build_query($REQ, '', '&');
            }

            curl_setopt($ch, CURLOPT_URL, $URL);
        } else {
            $mt = explode(' ', microtime());
            $NONCE = $mt[1] . substr($mt[0], 2, 6);

            $URL = "https://novaexchange.com/remote/v2/private/" . $ENDPOINT . "/?nonce=" . $NONCE;
            $REQ['apikey'] = $this->public_key;
            $REQ['signature'] = base64_encode(hash_hmac('sha512', $URL, $this->key_secret, true));
            $REQ = http_build_query($REQ);
            $HEADERS = array("Content-Type: application/x-www-form-urlencoded");

            curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADERS);
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $REQ);
        }

        // run the query
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('Could not get reply: ' . curl_error($ch));
        }

        return json_decode($res);
    }

}
