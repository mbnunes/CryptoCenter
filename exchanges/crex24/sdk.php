<?php

/**
 * Crex24 API
 */
class Crex24Sdk {
    private $baseUrl = 'https://api.crex24.com/CryptoExchangeService/';
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
            $params['Nonce'] = time();

            $sign = base64_encode(hash_hmac('sha512', json_encode($params), base64_decode($this->apiSecret), true));

            $headers = [
                'Content-Type: application/json',
                "UserKey: " . $this->apiKey,
                "Sign: " . $sign,
            ];

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
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
            $ret = json_decode($result);
            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getTicker($market) {
        return $this->call("BotPublic/ReturnTicker", ['request' => "[NamePairs={$market}]"]);
    }

    public function getOrderBook($market) {
        return $this->call("BotPublic/ReturnOrderBook", ["request" => "[PairName={$market}]"]);
    }

    public function getTickers() {
        return $this->call('BotPublic/ReturnTicker');
    }

    public function tradeStatus($currency) {
        return $this->call("BotPublic/ReturnCurrencies", ["request" => "[Names={$currency}]"]);
    }

    public function getCurrencies() {
        return $this->call("BotPublic/ReturnCurrencies");
    }

    public function balances() {
        $params = array(
            'NeedNull' => false,
        );
        return $this->call('BotTrade/ReturnBalances', $params, true);
    }

    public function balance($currency) {
        $params = array(
            'Names'    => (is_array($currency) ? $currency : [$currency]),
            'NeedNull' => true,
        );
        return $this->call('BotTrade/ReturnBalances', $params, true);
    }

    public function getDepositAddress($currency) {
        $params = array(
            'Currency' => $currency,
        );
        return $this->call('BotTrade/ReturnDepositAddress', $params, true);
    }

    public function buy($market, $amount, $price) {
        $params = array(
            'Pair' => $market,
            'Course'   => $price,
            'Volume'  => $amount,
        );
        return $this->call('BotTrade/Buy', $params, true);
    }

    public function sell($market, $amount, $price) {
        $params = array(
            'Pair' => $market,
            'Course'   => $price,
            'Volume'  => $amount,
        );
        return $this->call('BotTrade/Sell', $params, true);
    }

    public function orderStatus($orderNumber) {
        $params = array(
            'OrderId' => $orderNumber,
        );
        return $this->call('BotTrade/ReturnOrderStatus', $params, true);
    }

    public function cancelOrder($orderNumber) {
        $params = array(
            'OrderId' => $order_number,
        );
        return $this->call('BotTrade/CancelOrder', $params, true);
    }

    public function withdraw($currency, $quantity, $address, $paymentid = null) {
        $params = array(
            'Currency' => $currency,
            'Sum' => $quantity,
            'Address' => $address,
            'Message' => $paymentid,
        );
        return $this->call('BotTrade/Withdraw', $params, true);
    }
}