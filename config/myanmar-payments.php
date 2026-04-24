<?php

declare(strict_types=1);

return [
    'default' => env('MM_PAYMENT_PROVIDER', 'kbzpay'),

    'providers' => [
        '2c2p' => [
            'merchant_id' => env('TWOC2P_MERCHANT_ID', ''),
            'secret_key' => env('TWOC2P_SECRET_KEY', ''),
            'merchant_private_key' => env('TWOC2P_MERCHANT_PRIVATE_KEY', ''),
            'two_c2p_public_key' => env('TWOC2P_PUBLIC_KEY', ''),
            'key_id' => env('TWOC2P_KEY_ID', ''),
            'locale' => env('TWOC2P_LOCALE', 'en'),
            'payment_description' => env('TWOC2P_PAYMENT_DESCRIPTION', 'Payment'),
            'maintenance_version' => env('TWOC2P_MAINTENANCE_VERSION', '4.3'),
            'notifyURL' => env('TWOC2P_REFUND_NOTIFY_URL', ''),
            'idempotencyID' => env('TWOC2P_REFUND_IDEMPOTENCY_ID', ''),
            'endpoints' => [
                'payment_token' => env('TWOC2P_PAYMENT_TOKEN_URL', 'https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken'),
                'transaction_status' => env('TWOC2P_TRANSACTION_STATUS_URL', 'https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus'),
                'refund' => env('TWOC2P_REFUND_URL', 'https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action'),
            ],
            'timeout' => (int) env('TWOC2P_TIMEOUT', 30),
        ],
        'wavemoney' => [
            'merchant_id' => env('WAVEMONEY_MERCHANT_ID', ''),
            'secret_key' => env('WAVEMONEY_SECRET_KEY', ''),
            'merchant_name' => env('WAVEMONEY_MERCHANT_NAME', ''),
            'payment_description' => env('WAVEMONEY_PAYMENT_DESCRIPTION', 'Payment'),
            'time_to_live_in_seconds' => (int) env('WAVEMONEY_TTL_SECONDS', 600),
            'endpoints' => [
                'payment' => env('WAVEMONEY_PAYMENT_URL', 'https://testpayments.wavemoney.io:8107/payment'),
                'authenticate' => env('WAVEMONEY_AUTHENTICATE_URL', 'https://testpayments.wavemoney.io/authenticate'),
            ],
            'timeout' => (int) env('WAVEMONEY_TIMEOUT', 30),
        ],
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
