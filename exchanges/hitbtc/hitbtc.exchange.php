<?php

require_once __DIR__ . "/sdk/vendor/autoload.php";

class Hitbtc implements Exchange {

    private $caches = [];
    private $public_key;
    private $key_secret;
    private $sandbox = false;
    public $privateApi = null;
    public $publicApi = null;

    private function loadApi() {
        if (!empty($this->public_key) && !empty($this->key_secret)) {
            $this->privateApi = new \Hitbtc\ProtectedClient($this->public_key, $this->key_secret, $this->sandbox);
            $this->publicApi = new \Hitbtc\PublicClient($this->sandbox);
        }
    }

    public function setSandbox() {
        $this->sandbox = true;
    }

    public function getCurrencies(): getCurrenciesReturn {
        if (isset($this->caches['currencies'])) {
            return $this->caches['currencies'];
        } else {
            $tmp = $this->publicApi->getCurrencies();

            $return = new getCurrenciesReturn();

            foreach ($tmp as $currency) {
                $row = new currencyReturn();
                $row->shortName = $currency['id'];
                $row->fullName = $currency['fullName'];
                $return->currencies[] = $row;
            }

            $this->caches['currencies'] = $return;
        }

        return $return;
    }

    public function cancelOrder(String $orderId): cancelOrderReturn {
        $internalID = explode('-', $orderId)[0];
        $order = $this->callApiV2("/order/$internalID", [], 'DELETE');
        $return = new cancelOrderReturn();
        $return->id = (!empty($order['id']) ? $orderId : null);
        $return->status = $this->getOrderStatus($order);
        return $return;
    }

    private function getOrderStatus($order) {
        $orders_status = [
            "new" => OrderStatus::UNFILLED,
            "partiallyFilled" => OrderStatus::PARTIALFILLED,
            "filled" => OrderStatus::FILLED,
            "suspended" => OrderStatus::CANCELED,
            "expired" => OrderStatus::CANCELED,
        ];
        if (array_key_exists($order['status'], $orders_status)) {
            return $orders_status[$order['status']];
        } elseif ($order['status'] == 'canceled' && $order['cumQuantity'] < $order['quantity']) {
            return OrderStatus::PARTIALCANCELED;
        } else {
            return OrderStatus::CANCELED;
        }
    }

    public function checkOrder(String $orderId): checkOrderReturn {
        $internalID = explode('-', $orderId)[0];
        $order = $this->callApiV2("/order/$internalID");
        $return = new checkOrderReturn();
        $return->id = (!empty($order['id']) ? $orderId : null);
        $return->pair = $this->caches['orders'][$orderId]->pair;
        $return->type = strtoupper($order['side']);
        $return->amount = $order['quantity'];
        $return->filled = $order['cumQuantity'];
        $return->price = $order['price'];
        $return->status = $this->getOrderStatus($order);
        return $return;
    }

    public function doWithdraw(String $currency, String $address, float $amount, String $comment = ""): doWithdrawReturn {
        $data = [
            'currency' => $currency,
            'amount' => $amount,
            'address' => $address,
            'paymentId' => $comment,
        ];
        $withdraw = $this->callApiV2('/account/crypto/withdraw', $data, "POST");
        $fee = $this->getWithdrawFee($currency);

        $return = new doWithdrawReturn();
        $return->id = $withdraw['id'];
        $return->currency = $currency;
        $return->fee = $fee->fee;
        $return->address = $address;
        $return->amount = $amount;
        $return->comment = $comment;
        return $return;
    }

    public function getBalance(String $currency): getBalanceReturn {
        $balances = $this->privateApi->getBalanceTrading();
        foreach ($balances as $balance) {
            if ($balance->getCurrency() == $currency) {
                $return = new getBalanceReturn();
                $return->currency = $balance->getCurrency();
                $return->avaible = $balance->getAvailable();
                $return->locked = $balance->getReserved();
                $return->total = $balance->getReserved() + $balance->getAvailable();
                break;
            } else {
                continue;
            }
        }
        return $return;
    }

    public function getDepositAddress(String $currency): getDepositAddressReturn {
        $address = $this->privateApi->getPaymentAddress($currency);
        $return = new getDepositAddressReturn();
        $return->currecy = $currency;
        $return->address = $address;
        $return->identification;
        return $return;
    }

    public function getMarket(String $pair): getMarketReturn {
        if (isset($this->caches['symbol'][$pair])) {
            $symbol = $this->caches['symbol'][$pair];
        } else {
            $symbol = $this->publicApi->getSymbol(str_replace("/", '', $pair));
            $this->caches['symbol'][$pair] = $symbol;
        }

        $return = new getMarketReturn();
        $return->pair = "{$symbol['baseCurrency']}/{$symbol['quoteCurrency']}";
        $return->ticker = $symbol['id'];
        $return->min_amount = $symbol['tickSize'];
        $return->ask_fee = $symbol['provideLiquidityRate'] * 100;
        $return->bid_fee = $symbol['takeLiquidityRate'] * 100;
        $return->quantityIncrement = $symbol['quantityIncrement'];
        $return->tickSize = $symbol['tickSize'];
        return $return;
    }

    public function getMarkets(): getMarketsReturn {
        if (isset($this->caches['markets'])) {
            $markets = $this->caches['markets'];
        } else {
            $symbols = $this->publicApi->getSymbols();

            $markets = [];
            foreach ($symbols as $symbol) {
                $markets[] = "{$symbol['baseCurrency']}/{$symbol['quoteCurrency']}";
            }
            $this->caches['markets'] = $markets;
        }
        $return = new getMarketsReturn();
        $return->markets = $markets;
        return $return;
    }

    public function getOrderBook(String $pair, int $max_results = 50): getOrderBookReturn {
        $ticker = str_replace("/", '', $pair);
        $orderBook = $this->publicApi->getOderBook($ticker);
        $return = new getOrderBookReturn();

        foreach ($orderBook['bids'] as $key => $bid) {
            if ($key <= $max_results - 1) {
                $order = new Order();
                $order->price = $bid[0];
                $order->amount = $bid[1];
                $return->bids[] = $order;
            }
        }
        foreach ($orderBook['asks'] as $key => $ask) {
            if ($key <= $max_results - 1) {
                $order = new Order();
                $order->price = $ask[0];
                $order->amount = $ask[1];
                $return->asks[] = $order;
            }
        }

        $return->pair = $pair;
        $return->ticker = $ticker;
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

    public function getWithdrawFee(String $currency): getWithdrawFeeReturn {
        $return = new getWithdrawFeeReturn();
        $call = $this->publicApi->getCurrency($currency);
        if (!empty($call) && is_array($call)) {
            $return->min = $call['payoutFee'];
            $return->fee = $call['payoutFee'];
        }
        return $return;
    }

    public function round_up($number, $precision = 2) {
        $fig = (int) str_pad('1', $precision, '0');
        return (ceil($number * $fig) / $fig);
    }

    public function round_down($number, $precision = 2) {
        $fig = (int) str_pad('1', $precision, '0');
        return (floor($number * $fig) / $fig);
    }

    public function placeOrder(String $pair, String $type, float $amount, float $price): placeOrderReturn {
        if (!in_array(strtoupper($type), ['BUY', 'SELL'])) {
            throw new ProjetoException("Wrong order type", 102, null, ['exchange' => __CLASS__, 'method' => __FUNCTION__, 'pair' => $pair, 'type' => $type, 'amount' => $amount, "price" => $price]);
        }

        $ticker = str_replace("/", '', $pair);
        $internalID = uniqid();

        $market = $this->getMarket($pair);

        if ($market->tickSize < 1) {
            $decimals = strlen($market->tickSize) - 2;
        } else {
            $decimals = 0;
        }

        if ($market->quantityIncrement >= 1) {
            $amount = $amount - ($amount % $market->quantityIncrement);
        }

        $safeAmount = ($this->round_down($amount, $decimals) / $market->quantityIncrement);

        $orderX = new \Hitbtc\Model\NewOrder();
        $orderX->setClientOrderId($internalID);
        $orderX->setPrice($price);
        $orderX->setQuantity($safeAmount);
        $orderX->setSide(strtolower($type));
        $orderX->setSymbol($ticker);
        $orderX->setTimeInForce("IOC");

        $order = $this->privateApi->newOrder($orderX);

        $return = new placeOrderReturn();
        $return->id = (!empty($order->getClientOrderId()) ? $order->getClientOrderId() . "-" . $order->getOrderId() : null);
        $return->pair = $pair;
        $return->type = $type;
        $return->amount = $amount;
        $return->price = $price;
        $this->caches['orders'][$return->id] = $return;
        return $return;
    }

    public function getTradeStatus(String $currency): getTradeStatusReturn {
        $tmp = $this->getCoinStatus();

        if (!isset($tmp[$currency])) {
            return new getTradeStatusReturn();
        }

        $info = $tmp[$currency];

        $statusTrade = new getTradeStatusReturn();
        $statusTrade->status = $info->trading;

        $statusWithdraw = new getWithdrawStatusReturn();
        $statusWithdraw->status = $info->withdraws;

        $statusDeposit = new getDepositStatusReturn();
        $statusDeposit->status = $info->deposits;

        return $statusTrade;
    }

    public function getWithdrawStatus(String $currency): getWithdrawStatusReturn {
        $tmp = $this->getCoinStatus();

        if (!isset($tmp[$currency])) {
            return new getWithdrawStatusReturn();
        }

        $info = $tmp[$currency];

        $statusTrade = new getTradeStatusReturn();
        $statusTrade->status = $info->trading;

        $statusWithdraw = new getWithdrawStatusReturn();
        $statusWithdraw->status = $info->withdraws;

        $statusDeposit = new getDepositStatusReturn();
        $statusDeposit->status = $info->deposits;

        return $statusWithdraw;
    }

    public function getDepositStatus(String $currency): getDepositStatusReturn {
        $tmp = $this->getCoinStatus();

        if (!isset($tmp[$currency])) {
            return new getDepositStatusReturn();
        }

        $info = $tmp[$currency];

        $statusTrade = new getTradeStatusReturn();
        $statusTrade->status = $info->trading;

        $statusWithdraw = new getWithdrawStatusReturn();
        $statusWithdraw->status = $info->withdraws;

        $statusDeposit = new getDepositStatusReturn();
        $statusDeposit->status = $info->deposits;

        return $statusDeposit;
    }

    public function setKeySecret(String $secret): void {
        $this->key_secret = $secret;
        $this->loadApi();
    }

    public function setPublicKey(String $key): void {
        $this->public_key = $key;
        $this->loadApi();
    }

    public function callApiV2($endpoint, $data = [], $method = "GET") {
        if ($this->sandbox) {
            $host = "https://api.hitbtc.com/api/2";
        } else {
            $host = "https://api.hitbtc.com/api/2";
        }

        $headers = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $host . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->public_key:$this->key_secret");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $return = curl_exec($ch);
        curl_close($ch);
        return json_decode($return, true);
    }

    // Get hitbtc status
    private function getCoinStatus() {
        if (isset($this->caches['coinStatusData'])) {
            return $this->caches['coinStatusData'];
        }

        $html = file_get_contents("https://hitbtc.com/system-monitor");

        // Clean table
        $html = explode("</table>", "<table" . explode("<table", $html)[1])[0] . "</table>";

        // Remove thead
        $html = "<table>" . explode("</thead>", explode("<thead", $html)[1])[1];

        // Extract data
        $DOM = new DOMDocument;
        $DOM->loadHTML($html);
        $items = $DOM->getElementsByTagName('tr');

        $data = [];

        $count = 0;
        foreach ($items as $node) {
            if (count($node->childNodes) > 2) {
                $data[$count] = [];
                foreach ($node->childNodes as $element) {
                    $tmp = trim(str_replace(["\n", "\r", "\t"], ["", "", ""], $element->nodeValue));
                    if (!empty($tmp)) {
                        $data[$count][] = $tmp;
                    }
                }
                $count++;
            }
        }

        // Clean data
        $finalData = [];
        foreach ($data as $key => $value) {
            $tmp = new StdClass();
            $tmp->currency = $value[0];
            $tmp->deposits = (strstr(strtolower($value[1]), "online") ? true : false);
            $tmp->transfers = (strstr(strtolower($value[4]), "online") ? true : false);
            $tmp->trading = (strstr(strtolower($value[5]), "online") ? true : false);
            $tmp->withdraws = (strstr(strtolower($value[6]), "online") ? true : false);

            $finalData[$tmp->currency] = $tmp;
        }

        $this->caches['coinStatusData'] = $finalData;

        return $finalData;
    }

    private function sendToAccount(String $currency, float $amount, String $direction) {
        try {
            $transfer = $this->callApiV2("/account/transfer", [
                "currency" => $currency,
                "amount" => $amount,
                "type" => $direction,
                    ], "POST");
            if (empty($transfer["id"])) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function sendToBankAccount(String $currency, float $amount): sendToBankAccountReturn {
        $return = new sendToBankAccountReturn();
        $return->status = $this->sendToAccount($currency, $amount, "exchangeToBank");
        return $return;
    }

    public function sendToTradeAccount(String $currency, float $amount): sendToTradeAccountReturn {
        $return = new sendToTradeAccountReturn();
        $return->status = $this->sendToAccount($currency, $amount, "bankToExchange");
        return $return;
    }

}
