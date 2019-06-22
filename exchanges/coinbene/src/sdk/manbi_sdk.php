<?php
// From: https://github.com/Coinbene/API-Demo-Php
/**
 * Created by PhpStorm.
 * User: xiong.wei
 * Date: 2018/6/18
 * Time: 下午1:04
 */

include __DIR__ . '/../vendor/autoload.php';

class manBi {

    const MANBI_API = 'http://api.coinbene.com/';

    public function __construct($access_key, $secret_key) {
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
        $this->curl       = new \Curl\Curl();
        $this->curl->setHeader('content-type', "application/json;charset=utf-8");
    }

    public function tickers($symbol) {

        $data['symbol'] = $symbol;
        $tickers        = $this->curl->get(self::MANBI_API . 'v1/market/ticker', $data);
        return $tickers;
    }

    public function orderbook($symbol, $depth = 200) {
        return $this->curl->get(self::MANBI_API . 'v1/market/orderbook', [
            'symbol' => $symbol,
            'depth'  => $depth,
        ]);
    }

    public function trades($symbol, $size = 300) {
        return $this->curl->get(self::MANBI_API . 'v1/market/trades', [
            'symbol' => $symbol,
            'size'   => $size,
        ]);
    }

    public function markets() {
        $markets = $this->curl->get(self::MANBI_API . 'v1/market/symbol');
        return $markets;
    }
    public function balance($account = 'exchange') {
        $data = [
            'account' => $account,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/trade/balance', $this->build_params($data));
        return $orders;
    }

    public function orders($symbol, $type, $price, $quantity) {
        $data = [
            'symbol'   => $symbol,
            'type'     => $type,
            'price'    => $price,
            'quantity' => $quantity,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/trade/order/place', $this->build_params($data));
        return $orders;
    }

    public function get_open_orders($symbol) {
        $data = [
            'symbol' => $symbol,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/trade/order/open-orders', $this->build_params($data));
        return $orders;
    }

    public function cancel($orderid) {
        $data = [
            'orderid' => $orderid,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/trade/order/cancel', $this->build_params($data));
        return $orders;
    }

    public function orderInfo($orderid) {
        $data = [
            'orderid' => $orderid,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/trade/order/info', $this->build_params($data));
        return $orders;
    }

    public function withdrawApply($amount, $asset, $address, $tag = "") {
        $data = [
            'amount' => $amount,
            'asset' => $asset,
            'address' => $address,
            'tag' => $tag,
        ];
        $orders = $this->curl->post(self::MANBI_API . 'v1/withdraw/apply', $this->build_params($data));
        return $orders;
    }

    private function build_params($data) {
        $data['apiid']     = $this->access_key;
        $data['secret']    = $this->secret_key;
        $data['timestamp'] = $this->get_millisecond();
        ksort($data);
        $data['sign'] = $this->create_sig(http_build_query($data));
        unset($data['secret']);
        return $data;
    }

    // Generate signature
    // 生成签名
    private function create_sig($param) {
        $signature = md5(strtoupper($param));
        return $signature;
    }

    private function get_millisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float) sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }
}