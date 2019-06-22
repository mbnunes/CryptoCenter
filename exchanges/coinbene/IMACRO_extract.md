## How to retrive info from coinbene since they are evil and has no endpoint to collet withdraw fees/min amounts.

iMacro has a script limti of 50 lines, so this script has just a few coins listed (use at your own).

*Required to be logged at your account and have all 16 tabs opened before*

```iMacro
VERSION BUILD=1005 RECORDER=CR
TAB T=1
URL GOTO=https://www.coinbene.com/#/withdraw?asset=USDT
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=USDT&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=2
URL GOTO=https://www.coinbene.com/#/withdraw?asset=BTC
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=BTC&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=3
URL GOTO=https://www.coinbene.com/#/withdraw?asset=ETH
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=ETH&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=4
URL GOTO=https://www.coinbene.com/#/withdraw?asset=CONI
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=CONI&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=5
URL GOTO=https://www.coinbene.com/#/withdraw?asset=ETN
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=ETN&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=6
URL GOTO=https://www.coinbene.com/#/withdraw?asset=SMART
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=SMART&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=7
URL GOTO=https://www.coinbene.com/#/withdraw?asset=EOS
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=EOS&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=8
URL GOTO=https://www.coinbene.com/#/withdraw?asset=LTC
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=LTC&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=9
URL GOTO=https://www.coinbene.com/#/withdraw?asset=XRP
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=XRP&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=10
URL GOTO=https://www.coinbene.com/#/withdraw?asset=EMT
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=EMT&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=11
URL GOTO=https://www.coinbene.com/#/withdraw?asset=SWTC
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=SWTC&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=12
URL GOTO=https://www.coinbene.com/#/withdraw?asset=MOAC
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=MOAC&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=13
URL GOTO=https://www.coinbene.com/#/withdraw?asset=OMG
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=OMG&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=14
URL GOTO=https://www.coinbene.com/#/withdraw?asset=CMT
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=CMT&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=15
URL GOTO=https://www.coinbene.com/#/withdraw?asset=BCHABC
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=BCHABC&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
TAB T=16
URL GOTO=https://www.coinbene.com/#/withdraw?asset=BCHSV
URL GOTO=javascript:{$.get("http://localhost/savedata.php?coin=BCHSV&amount="+$(".min-withdraw").text()+"&fees="+parseFloat($(".address.withdrawal-amount<SP>.fees").html()));}
```


PHP File to save the received data
```php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');  
$COIN = (isset($_REQUEST['coin']) ? $_REQUEST['coin'] : "XXX");
file_put_contents("./tmp/{$COIN}.txt", json_encode($_REQUEST), FILE_APPEND);
chmod("./tmp/{$COIN}.txt", 0777);
``` 


PHP File to process the data into usable JSON
```php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$files = glob("./*.txt");

$fim = [];

foreach ($files as $key => $value) {
	$info = json_decode(file_get_contents($value), true);

	$tmp = explode(";", str_replace('Minimum withdrawal amount: ', '', $info['amount']));
	$min = str_replace(" {$info['coin']}", "", $tmp[0]);
	$info['amount'] = $min;

	$fim[$info['coin']] = [
		'min' => $min,
		'fee' => $info['fees']
	];
}

file_put_contents("final_json.json", json_encode($fim));
```
