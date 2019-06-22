<?php

/**
 * Braziliex API
 */
class BraziliexSdk {
    private $baseUrl = 'https://braziliex.com/api/v1/';
    private $apiKey;
    private $apiSecret;

    public function __construct($apiKey, $apiSecret) {
        $this->apiKey    = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function call($method, $params = array(), $apiKey = false) {
        $uri = $this->baseUrl . $method;

        if (!empty($params) && !$apiKey) {
            $uri .= '?' . http_build_query($params);
        }

        $ch      = curl_init($uri);
        $headers = [];

        if ($apiKey == true) {
            $params['nonce'] = time();

            $sign = hash_hmac('sha512', http_build_query($params, '', "&"), $this->apiSecret);

            $headers = [
                "Key: " . $this->apiKey,
                "Sign: " . $sign,
            ];

            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; PHP/' . phpversion() . ')');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        try {
            $ret = json_decode($result, true);
            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getCurrencies() {
        return $this->call('public/currencies');
    }

    public function getTicker($market) {
        return $this->call("public/ticker/{$market}");
    }

    public function getOrderBook($market) {
        return $this->call("public/orderbook/{$market}");
    }

    public function getTickers() {
        return $this->call('public/ticker');
    }

    public function tradehistory($market) {
        return $this->call("public/tradehistory/{$market}");
    }

    public function balances() {
        $params = array(
            'command' => "balance",
        );
        return $this->call('private', $params, true);
    }

    public function balancesComplete() {
        $params = array(
            'command' => "complete_balance",
        );
        return $this->call('private', $params, true);
    }

    public function openOrders($market) {
        $params = array(
            'command' => "open_orders",
            'market'  => $market,
        );
        return $this->call('private', $params, true);
    }

    public function tradehistoryPrivate($market) {
        $params = array(
            'command' => "trade_history",
            'market'  => $market,
        );
        return $this->call('private', $params, true);
    }

    public function getDepositAddress($currency) {
        $params = array(
            'command'  => "deposit_address",
            'currency' => strtolower($currency),
        );
        return $this->call('private', $params, true);
    }

    public function buy($market, $amount, $price) {
        $params = array(
            'command' => "buy",
            'amount'  => $amount,
            'price'   => $price,
            'market'  => $market,
        );
        return $this->call('private', $params, true);
    }

    public function sell($market, $amount, $price) {
        $params = array(
            'command' => "sell",
            'amount'  => $amount,
            'price'   => $price,
            'market'  => $market,
        );
        return $this->call('private', $params, true);
    }

    public function orderStatus($market, $orderNumber) {
        $params = array(
            'market'       => $market,
            'order_number' => $order_number,
        );
        return $this->call('private', $params, true);
    }

    public function cancelOrder($market, $orderNumber) {
        $params = array(
            'command'      => "cancel_order",
            'order_number' => $order_number,
            'market'       => $market,
        );
        return $this->call('private', $params, true);
    }
}