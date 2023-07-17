# api process steps 

# api authentication
all api requests must contain the 'api-key' in the header

## validate a product

#when validating a product

request:
 endpoint: https://pay.teljoy.johnson.org.za/api/product
 type: POST
 payload: 

```
  {
			"name": "AEG BSK77412XM 60CM 72L OVEN WITH STEAMCRISP",
			"description": "",
			"short_description": "",
			"brand": "string",
			"merchant_product_id":"58476",
			"quantity": "1",
			"vendor": {
			  "vendor_id": "string",
			  "url": "string",
			  "name": "string"
			},
			"images": [
			  "https://atomictest.co.za/wp-content/uploads/2022/04/BSK77412XM-150x150.jpg" 
			],
			"url": "string",
			"price": "1.99",
			"sku": "BSK77412XM",
			"barcodes": [
			  ""
			],
			"categories": [
				  {
					"id": "745",
					"name": "Electric Ovens",
					"url": "string"
				  },
				  {
					"id": "744",
					"name": "Ovens",
					"url": "string"
				  }],
			"properties": [
			  {
				"key": "string",
				"value": "string"
			  }
			]
  }
```
## Request cart
request:
 endpoint: https://pay.teljoy.johnson.org.za/api/payment/create
 type: POST
 payload: 

```
{
					"customer": {
						"first_name":  "jacques",
						"last_name":  "artgraven",
						"email":  "artgraven@outlook.com",
						"mobile": "0796318807"
					},
					"shipping_address": {
						"type": "residential",
						"building": "33a homestead ",
						"street": "",
						"suburb": "edenburg",
						"city": "edenburg",
						"province": "GP",
						"country": "ZA",
						"postal_code": "2128",
						"confirmed": false
					},
					"billing_address": {
						"type": "residential",
						"building": "33a homestead ",
						"street": "",
						"suburb": "edenburg",
						"city": "edenburg",
						"province": "",
						"country": "ZA",
						"postal_code": "2128",
						"confirmed": false
					},
					"products": [{
							"name":"some test product1",
							"sku":"1364-456-212",
							"quantity":"4",
							"price":"1600.00",
							"description": "string",
							"brand": "string",
							"merchant_product_id":"27",
							"vendor": {
								"vendor_id": "string",
								"url": "string",
								"name": "string"
							},
							"images": [
								"url" 
							],
							"barcodes": [
								"string"
							],
							"categories": [ 
								{
								"id": "string",
								"name": "string",
								"url": "string"
								}
							],
							"properties": [
								{
								"key": "string",
								"value": "string"
								}
							]
						},{
							"name":"test product2",
							"sku":"",
							"quantity":"1",
							"price":"45.00",
							"description": "string",
							"brand": "string",
							"merchant_product_id":"12",
							"vendor": {
								"vendor_id": "string",
								"url": "string",
								"name": "string"
							},
							"images": [
								"url" 
							],
							"barcodes": [
								"string"
							],
							"categories": [ 
								{
								"id": "string",
								"name": "string",
								"url": "string"
								}
							],
							"properties": [
								{
								"key": "string",
								"value": "string"
								}
							]
						}],
					"redirects": {
						"order_id": "34",
						"trust_value": "hash",
						"trust_seed": "64bit encoded string of the product array",
						"success_redirect_url": "url",
						"failure_redirect_url": "url",
						"final_amount": 6445.00,
						"tax_amount": 0,
						"shipping_amount":0, 
						"discount": "0"
					}
					
					}
```

### Redirect 
the above call will return a redirect url that we can use to direct the client over


## verify transaction status

request:
  endpoint: https://pay.teljoy.johnson.org.za/api/status/{{transaction-id})
  type: GET

## fetch merchant settings

request: 
  endpoint: https://pay.teljoy.johnson.org.za/api/merchant
  type: GET
  response: 
```
{
    "name": "MASONS TEST",
    "activationPeriodExpiry": 600,
    "completionPeriodExpiry": 86400
}
```

## update payment/cart status from woocommerce

request:
  endpoint: https://pay.teljoy.johnson.org.za/api/status/{{transaction-id}}
  type: POST
  payload:
```
{
    "id": "{{cart-id}}",
    "status": "cancelled",
    "trust_value": "123",
    "trust_seed": "123",
    "alt_trust_value": "123",
    "apiKey": "{{mechant-api-key}}"
}
```

