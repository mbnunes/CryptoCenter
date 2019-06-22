<?php
class tradesatoshi_sdk {
	private $API_PUBLIC_KEY;
	private $API_SECRET_KEY;

	public function __construct($API_PUBLIC_KEY, $API_SECRET_KEY) {
		$this->API_PUBLIC_KEY = $API_PUBLIC_KEY;
		$this->API_SECRET_KEY = $API_SECRET_KEY;
	}

	public function api_query($ENDPOINT, array $REQ = array()) {
		$API_PUBLIC_KEY = $this->API_PUBLIC_KEY; // Your Public Api Key
		$API_SECRET_KEY = $this->API_SECRET_KEY; // Your Private Api Key

		$PUBLIC_API  = array('GetCurrencies', 'GetCurrency', 'GetTicker', 'GetMarketHistory', 'GetMarketSummary', 'GetMarketSummaries', 'GetOrderBook');
		$PRIVATE_API = array('GetBalance', 'GetBalances', 'GetOrder', 'GetOrders', 'SubmitOrder', 'CancelOrder', 'GetTradeHistory', 'GenerateAddress', 'SubmitWithdraw', 'GetDeposits', 'GetWithdrawals', 'SubmitTransfer');

		// Init curl
		static $ch = null;
		$ch        = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; TradeSatoshi API PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');

		// remove those 2 line to secure after test.
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		// PUBLIC or PRIVATE API
		if (in_array($ENDPOINT, $PUBLIC_API)) {
			$URL = "https://tradesatoshi.com/api/public/" . strtolower($ENDPOINT);
			if ($REQ) {
				$URL .= '?' . http_build_query($REQ, '', '&');
			}

			curl_setopt($ch, CURLOPT_URL, $URL);
		} elseif (in_array($ENDPOINT, $PRIVATE_API)) {
			$URL       = "https://tradesatoshi.com/api/private/" . strtolower($ENDPOINT);
			$mt        = explode(' ', microtime());
			$NONCE     = $mt[1] . substr($mt[0], 2, 6);
			$REQ       = json_encode($REQ);
			$SIGNATURE = $API_PUBLIC_KEY . 'POST' . strtolower(urlencode($URL)) . $NONCE . base64_encode($REQ);
			$HMAC_SIGN = base64_encode(hash_hmac('sha512', $SIGNATURE, base64_decode($API_SECRET_KEY), true));
			$HEADER    = 'Basic ' . $API_PUBLIC_KEY . ':' . $HMAC_SIGN . ':' . $NONCE;
			$HEADERS   = array("Content-Type: application/json; charset=utf-8", "Authorization: $HEADER");

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

	/* ---------- Public Functions ---------- */
	public function GetCurrencies() {
		return $this->api_query("GetCurrencies");
	}

	public function GetCurrency($Symbol) {
		return $this->api_query("GetCurrency", ['Symbol' => $Symbol]);
	}

	public function GetTicker($market = false) {
		if ($market === false) {
			return (object) array("success" => false, "message" => 'GetTicker: market name e.g. "LTC_BTC" (required)');
		} else {
			return $this->api_query("GetTicker", array('market' => $market));
		}
	}

	public function GetMarketHistory($market = false, $count = 20) {
		if ($market === false) {
			return (object) array("success" => false, "message" => 'GetMarketHistory: market name e.g. "LTC_BTC" (required)');
		} else {
			return $this->api_query("GetMarketHistory", array('market' => $market, 'count' => $count));
		}
	}

	public function GetMarketSummary($market = false) {
		if ($market === false) {
			return (object) array("success" => false, "message" => 'GetMarketSummary: market name e.g. "LTC_BTC" (required)');
		} else {
			return $this->api_query("GetMarketSummary", array('Market' => $market));
		}
	}

	public function GetMarketSummaries() {
		return $this->api_query("GetMarketSummaries");
	}

	public function GetOrderBook($market = false, $type = 'both', $depth = 20) {
		if ($market === false) {
			return (object) array("success" => false, "message" => 'GetOrderBook: market name e.g. "LTC_BTC" (required)');
		} else {
			return $this->api_query("GetOrderBook", array('Market' => $market, 'Type' => $type, 'Depth' => $depth));
		}
	}

	/* ---------- Privates Functions ---------- */

	/*	GetBalance
	Currency: The currency of the balance to return e.g. 'BTC' (required)
	 */
	public function GetBalance($currency = false) {
		if ($currency === false) {
			return (object) array("success" => false, "message" => 'GetBalance: currency name e.g. "BTC" (required)');
		} else {
			return $this->api_query("GetBalance", array('Currency' => $currency));
		}
	}

	public function GetBalances() {
		return $this->api_query("GetBalances");
	}

	/*	GetOrder
	OrderId: The order to return (required)
	 */
	public function GetOrder($orderid = false) {
		if ($orderid === false) {
			return (object) array("success" => false, "message" => 'GetOrder: OrderId required!');
		} else {
			return $this->api_query("GetOrder", array('OrderId' => $orderid));
		}
	}

	/*	GetOrders
	Market: The market name e.g. 'LTC_BTC' (optional, default: 'all')
	Count: The maximum count of records to return (optional, deefault: 20)
	 */
	public function GetOrders($market = "all", $count = 20) {
		return $this->api_query("GetOrders", array('Market' => $market, 'Count' => $count));
	}

	/*	SubmitOrder
	Market: The market name e.g. 'LTC_BTC' (required)
	Type: The order type name e.g. 'Buy', 'Sell' (required)
	Amount: The amount to buy/sell (required)
	Price: The price to buy/sell for (required)
	 */
	public function SubmitOrder($market = false, $type = false, $amount = false, $price = false) {
		if ($market === false || $type === false || $amount === false || $price === false) {
			return (object) array("success" => false, "message" => 'SubmitOrder: Market,Type,Amount,Price are required!');
		} else {
			return $this->api_query("SubmitOrder", array('Market' => $market, 'Type' => $type, 'Amount' => $amount, 'Price' => $price));
		}
	}

	/*	CancelOrder
	Type: The cancel type, options: 'Single','Market','MarketBuys','MarketSells','AllBuys','AllSells','All'(required)
	OrderId: The order to cancel(required if cancel type 'Single')
	Market: The order to cancel(required if cancel type 'Market','MarketBuys','MarketSells')
	 */
	public function CancelOrder($type = false, $orderid = false, $market = false) {
		if ($type === false) {
			return (object) array("success" => false, "message" => 'CancelOrder: Type and/or OrderId and/or Market are required!');
		} else {
			return $this->api_query("CancelOrder", array('Type' => $type, 'OrderId' => $orderid, 'Market' => $market));
		}
	}

	/*	GetTradeHistory
	Market: The market name e.g. 'LTC_BTC' (optional, default: 'all')
	Count: The maximum count of records to return (optional, default: 20)
	 */
	public function GetTradeHistory($market = 'all', $count = 20) {
		return $this->api_query("GetTradeHistory", array('Market' => $market, 'Count' => $count));
	}

	/*	GenerateAddress
	Currency: The currency to generate address for e.g. 'BTC' (required)
	 */
	public function GenerateAddress($currency = false) {
		if ($currency === false) {
			return (object) array("success" => false, "message" => 'GenerateAddress: The currency to generate address for e.g. "BTC" (required)');
		} else {
			return $this->api_query("GenerateAddress", array('Currency' => $currency));
		}
	}

	/*	SubmitWithdraw
	Currency: The currency name e.g. 'BTC' (required)
	Address: The receiving address (required)
	Amount: The amount to withdraw (required)
	 */
	public function SubmitWithdraw($currency = false, $address = false, $amount = false) {
		if ($currency === false || $address === false || $amount === false) {
			return (object) array("success" => false, "message" => 'SubmitWithdraw: Currency,Address,Amount are required');
		} else {
			return $this->api_query('SubmitWithdraw', array('Currency' => $currency, 'Address' => $address, 'Amount' => $amount));
		}
	}

	/*	GetDeposits
	Currency: The currency name e.g. 'BTC' (optional, default: 'all')
	Count: The maximum count of records to return (optional, default: 20)
	 */
	public function GetDeposits($currency = 'all', $count = 20) {
		return $this->api_query('GetDeposits', array('Currency' => $currency, 'Count' => $count));
	}

	/*	GetWithdrawals
	Currency: The currency name e.g. 'BTC' (optional, default: 'all')
	Count: The maximum count of records to return (optional, default: 20)
	 */
	public function GetWithdrawals($currency = 'all', $count = 20) {
		return $this->api_query('GetWithdrawals', array('Currency' => $currency, 'Count' => $count));
	}

	/*	SubmitTransfer (require Transfer Enabled with API checked)
	Currency: The currency name e.g. 'BTC' (required)
	Username: The TradeSatoshi username of the person to transfer the funds to. (required)
	Amount: The amount of coin to transfer e.g. 251.00000000 (required)
	 */
	public function SubmitTransfer($currency = false, $username = '', $amount = 0.00000000) {
		if ($currency === false || empty($username) || $amount < 0.00000001) {
			return (object) array("success" => false, "message" => 'Currency, Username and Amount should be filled');
		} else {
			return $this->api_query('SubmitTransfer', array('Currency' => $currency, 'Username' => $username, 'Amount' => $amount));
		}
	}
}