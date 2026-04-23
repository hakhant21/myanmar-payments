<?php

declare(strict_types=1);

return [
    'default' => env('MM_PAYMENT_PROVIDER', 'kbzpay'),

    'providers' => [
        'kbzpay' => [
            'merchant_code' => env('KBZPAY_MERCH_CODE', ''),
            'merchant_id' => env('KBZPAY_MERCHANT_ID', ''),
            'app_id' => env('KBZPAY_APP_ID', ''),
            'secret' => env('KBZPAY_SECRET', ''),
            'public_key' => env('KBZPAY_PUBLIC_KEY', ''),
            'trade_type' => env('KBZPAY_TRADE_TYPE', 'APP'),
            'notify_url' => env('KBZPAY_NOTIFY_URL', ''),
            'sub_type' => env('KBZPAY_SUB_TYPE', ''),
            'sub_identifier_type' => env('KBZPAY_SUB_IDENTIFIER_TYPE', ''),
            'sub_identifier' => env('KBZPAY_SUB_IDENTIFIER', ''),
            'endpoints' => [
                'precreate' => env('KBZPAY_PRECREATE_URL', 'https://api.kbzpay.com/payment/gateway/precreate'),
                'queryorder' => env('KBZPAY_QUERYORDER_URL', 'https://api.kbzpay.com/payment/gateway/queryorder'),
                'refund' => env('KBZPAY_REFUND_URL', 'https://api.kbzpay.com:8008/payment/gateway/refund'),
                'mmqr' => env('KBZPAY_MMQR_URL', 'https://api.kbzpay.com/payment/gateway/mmqrprecreate'),
            ],
            'versions' => [
                'precreate' => env('KBZPAY_PRECREATE_VERSION', '1.0'),
                'queryorder' => env('KBZPAY_QUERYORDER_VERSION', '3.0'),
                'refund' => env('KBZPAY_REFUND_VERSION', '1.0'),
                'mmqr' => env('KBZPAY_MMQR_VERSION', '1.0'),
            ],
            'timeout' => (int) env('KBZPAY_TIMEOUT', 30),
        ],
    ],

    'callback' => [
        'timestamp_tolerance_seconds' => (int) env('MM_PAYMENT_CALLBACK_TOLERANCE', 300),
    ],
];
