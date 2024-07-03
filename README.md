# 2C2P Myanmar One Stop Payment Service Integration Package

[![Test Suite](https://github.com/hakhant21/myanmar-payments/actions/workflows/main.yml/badge.svg?branch=master&event=push)](https://github.com/hakhant21/myanmar-payments/actions/workflows/main.yml)

## Installation
```bash

composer require hakhant/payments

```
### Usage 

```php
use Hak\Payments\Facades\Gateway;

$gateway = Gateway::create('2c2p');

$paymentUrl = $gateway->createPayment(
    int $amount, 
    string $invoiceNo, 
    string $currencyCode, 
    string $nonceStr, // Nonce random string 
    string $frontendReturnUrl, 
    string $backendReturnUrl, 
    string $paymentDescription, 
    array $userDefined = []
);

// will return redirect payment url 
// Example Output: "https://sandbox-pgw-ui.2c2p.com/payment/4.3/#/token/kSAops9Zwhos8hSTSeLTUfpHWx5Z92B%2bH%2boP1feNEaIJJzV7xpt1Zj8xSRgE%3d" 

```

### Publish config file

```bash

php artisan vendor:publish --provider="Hak\Payments\GatewayServiceProvider" --tag="gateway"

```

#### You can get config variables from developer.2c2p.com 
  * MERCHANT_ID // JT02 
  * SECRET_KEY // SHA256 key
  * CURRENCY_CODE // MMK

