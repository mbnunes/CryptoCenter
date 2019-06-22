<?php

require_once __DIR__ . "/vendor/autoload.php";

use \Firebase\JWT\JWT;

class Liquid implements Exchange {

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
        $return = new getCurrenciesReturn();
        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $markets = $this->publicApiCall('products');
        }

        $ret = [];
        $tmp = [];

        foreach ($markets as $symbol) {
            $pair = "{$symbol->base_currency}/{$symbol->quoted_currency}";
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
        $return->ticker = $market->id;
        $return->ask_fee = $market->taker_fee;
        $return->bid_fee = $market->maker_fee;
        $return->min_amount = 0;

        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $balances = $this->privateApiCall("accounts/balance");

        $return = new getBalanceReturn();
        $return->currency = $currency;

        $found = false;

        foreach ($balances as $value) {
            if (strtoupper($value->currency) == strtoupper($currency)) {
                $return->avaible = $value->balance;
                $return->locked = 0.0;
                $return->total = $value->balance;
                $found = true;
            }
        }

        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 10): getOrderBookReturn {
        $market = $this->getMarket($pair);

        $orderBook = $this->publicApiCall("/products/{$market->ticker}/price_levels");

        $return = new getOrderBookReturn();
        $return->pair = $pair;
        $return->ticker = $market->ticker;

        foreach ($orderBook->buy_price_levels as $key => $bid) {
            $order = new Order();
            $order->amount = $bid[1];
            $order->price = $bid[0];
            $return->bids[] = $order;
        }

        foreach ($orderBook->sell_price_levels as $key => $ask) {
            $order = new Order();
            $order->amount = $ask[1];
            $order->price = $ask[0];
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

        $typeFinal = (strtoupper($type) == "BUY" ? 'buy' : "sell");

        $market = $this->getMarket($pair);

        // $order = $this->api->orders($market->ticker, $typeFinal, number_format($price, 8, ".", ""), $amount);
        $order = $this->privateApiCall("orders", [
            "order" => [
                "order_type" => "limit",
                "product_id" => $market->ticker,
                "side" => $typeFinal,
                "quantity" => $amount,
                "price" => $price,
            ],
                ], "POST");

        $return = new placeOrderReturn();
        $return->id = $order->id;
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $cancel = $this->privateApiCall("orders/{$orderId}/cancel", [], "PUT");

        $return = new cancelOrderReturn();
        $return->id = $orderId;
        $return->status = true;

        return $return;
    }

    public function getOrderStatus($orderDetails = []) {
        if ($orderDetails->status == "cancelled") {
            return ($orderDetails->filled_quantity > 0 ? OrderStatus::PARTIALCANCELLED : OrderStatus::CANCELLED);
        }

        if ($orderDetails->status == "filled") {
            return OrderStatus::FILLED;
        }

        if ($orderDetails->status == "partially_filled") {
            return OrderStatus::PARTIALFILLED;
        }

        return OrderStatus::UNFILLED;
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $info = $this->privateApiCall("/orders/{$orderId}");

        $status = $this->getOrderStatus($info);

        if (!isset($this->caches['markets'])) {
            $this->getMarkets();
        }

        $returnPair = false;

        foreach ($this->caches['markets'] as $pair => $data) {
            if (!$returnPair && $data->id == $info->product_id) {
                $returnPair = $pair;
            }
        }

        $return = new checkOrderReturn();
        $return->id = $orderId;
        $return->pair = $returnPair;
        $return->type = strtoupper($info->side);
        $return->amount = $info->quantity;
        $return->filled = $info->filled_quantity;
        $return->price = $info->price;
        $return->status = $status;

        return $return;
    }

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();
        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $return = new doWithdrawReturn();
        $fee = $this->getWithdrawFee($currency);
        $return->id = null; // API Doesn't return Withdraw id, but why?
        $return->currency = $currency;
        $return->address = $address;
        $return->fee = $fee->fee;
        $return->amount = $amount;
        $return->comment = $comment;
        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $tmp = $this->privateApiCall("crypto_accounts");

        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->identification = null;

        foreach ($tmp as $key => $value) {
            if ($value->currency == $currency) {
                $return->address = $value->address;
            }
        }
        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        return new getTradeStatusReturn();
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        return new getWithdrawStatusReturn();
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        return new getDepositStatusReturn();
    }

    public function sendToBankAccount(String $currency, float $amount): sendToBankAccountReturn {
        $return = new sendToBankAccountReturn();
        return $return;
    }

    public function sendToTradeAccount(String $currency, float $amount): sendToTradeAccountReturn {
        $return = new sendToTradeAccountReturn();
        return $return;
    }

    public function publicApiCall($path, $data = []) {
        $base_url = 'https://api.liquid.com/';

        $token = array(
            "path" => $path,
            "nonce" => time(),
            "token_id" => $this->public_key,
        );

        $headers = [
            'X-Quoine-API-Version: 2',
            'Content-Type: application/json',
        ];

        try {
            $ch = curl_init($base_url . $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $execResult = curl_exec($ch);
            $result = json_decode($execResult);
        } catch (Exeption $e) {
            throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'path' => $path]);
        }

        return $result;
    }

    public function privateApiCall($path, $data = [], $type = "GET") {
        $base_url = 'https://api.liquid.com/';

        if (!empty($data)) {
            $path .= "?" . http_build_query($data);
        }

        $token = array(
            "path" => "/{$path}",
            "nonce" => (int) time() * 1000,
            "token_id" => (int) $this->public_key,
        );

        $jwt = JWT::encode($token, $this->key_secret);

        $headers = [
            'X-Quoine-API-Version: 2',
            'X-Quoine-Auth: ' . $jwt,
            'Content-Type: application/json',
        ];

        try {
            $ch = curl_init($base_url . $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if (!empty($data) || in_array($type, ["POST", "PUT"])) {
                if ($type != "POST") {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                } else {
                    curl_setopt($ch, CURLOPT_POST, 1);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $execResult = curl_exec($ch);
            $info = curl_getinfo($ch);

            $result = json_decode($execResult);

            if (!empty($result->error) || (!empty($result->message) && in_array($result->message, ['Order not found'])) || strstr($execResult, "<html")) {
                throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'path' => $path, 'data' => $data, 'type' => $type, 'response' => $result]);
            }
        } catch (Exeption $e) {
            throw new CryptoCenterException("API error", 200, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'path' => $path]);
        }

        return $result;
    }

}
