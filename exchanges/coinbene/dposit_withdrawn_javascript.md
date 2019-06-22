```javascript
console.clear();

function _date() {
    var date = new Date();
    var aaaa = date.getFullYear();
    var gg = date.getDate();
    var mm = (date.getMonth() + 1);

    if (gg < 10)
        gg = "0" + gg;

    if (mm < 10)
        mm = "0" + mm;

    var cur_day = aaaa + "-" + mm + "-" + gg;

    var hours = date.getHours()
    var minutes = date.getMinutes()
    var seconds = date.getSeconds();

    if (hours < 10)
        hours = "0" + hours;

    if (minutes < 10)
        minutes = "0" + minutes;

    if (seconds < 10)
        seconds = "0" + seconds;

    return cur_day + " " + hours + ":" + minutes + ":" + seconds;
}

function _xpath(STR_XPATH) {
    var xresult = document.evaluate(STR_XPATH, document, null, XPathResult.ANY_TYPE, null);
    var xnodes = [];
    var xres;
    while (xres = xresult.iterateNext()) {
        xnodes.push(xres);
    }

    return xnodes;
}

var select = $(_xpath('//*[@id="recharge-btc"]/div[1]/div[1]/div[1]/div/input'));
select.click();

var result = {
	"time": _date()
};

setTimeout(function() {
	var coins = $(_xpath('/html/body/div[3]/div[1]/div[1]/ul/li'));

	if (coins.length <= 0) {
		select.click();
		var coins = $(_xpath('/html/body/div[3]/div[1]/div[1]/ul/li'));
	}

	$.each(coins, function(k, list) {
		var coin = $(list).text();

		var tmp = coin.split("(");
		var tmp2 = tmp[1].split(")");
		var abbr = tmp2[0];

		if (typeof result[abbr] == "undefined") {
			result[abbr] = {
				depositStatus: false,
				withdrawStatus: false
			}
		}

		result[abbr]['depositStatus'] = (coin.indexOf("Suspend") != -1 ? false : true);
	});

	window.location.hash = "#/withdraw";

	setTimeout(function() {
		var select = $(_xyzNoiz('//*[@id="withdraw"]/div/div[1]/form/div[1]/div/div[1]/input')).click();

		setTimeout(function() {
			var coins = $(_xyzNoiz('/html/body/div[3]/div[1]/div[1]/ul/li'));

			if (coins.length <= 0) {
				select.click();
				var coins = $(_xyzNoiz('/html/body/div[3]/div[1]/div[1]/ul/li'));
			}

			$.each(coins, function(k, list) {
				var coin = $(list).text();

				var tmp = coin.split("(");
				var tmp2 = tmp[1].split(")");
				var abbr = tmp2[0];

				if (typeof result[abbr] == "undefined") {
					result[abbr] = {
						depositStatus: false,
						withdrawStatus: false
					}
				}

				result[abbr]['withdrawStatus'] = (coin.indexOf("Suspend") != -1 ? false : true);
			});

			console.clear();
			console.log(JSON.stringify(result));
		}, 1000);
	}, 1000);
}, 1500);
```