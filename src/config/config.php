<?php 


return [
    "2c2p" => [
        "merchant_id" => env('2C2P_MMK_MERCHANT_ID', 'JT02'),
        "secret_key" => env('2C2P_MMK_SECRET_KEY', '72B8F060B3B923E580411200068A764610F61034AE729AB9EF20CAFF93AFA1B9'),
        "currency_code" => env('2C2P_MMK_CURRENCY_CODE', 'MMK'),
        "base_url" => env('2C2P_BASE_URL', 'https://sandbox-pgw.2c2p.com/payment/4.3/'),
        'payment_channel' => ['MPU'],
    ]
];