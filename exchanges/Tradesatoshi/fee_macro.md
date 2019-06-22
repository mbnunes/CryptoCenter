## Fee funcion

```javascript
var fees = {};
$("#table-FeesStructure tbody tr").each(function(){
	var coin = $(this).find("td").eq(0).text().replace(/[^a-zA-Z]/g, "").trim();
	var fee = $(this).find("td").eq(2).text();
	if (coin != "") {
		fees[coin] = {
			min: fee,
			fee: fee
		}
	}
});

console.log(JSON.stringify(fees));
```