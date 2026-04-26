<?php

declare(strict_types=1);

namespace Hakhant\Payments\Tests\Support;

final class ProviderConfig
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function aya(array $overrides = []): array
    {
        return array_replace_recursive([
            'basic_token' => 'BASE64_BASIC',
            'phone' => '09999999999',
            'password' => '123456',
            'service_code' => 'SERVICE_CODE',
            'time_limit' => 2,
            'endpoints' => [
                'login' => 'https://opensandbox.test/login',
                'push_payment' => 'https://opensandbox.test/requestPushPayment',
                'push_payment_v2' => 'https://opensandbox.test/v2/requestPushPayment',
                'query_payment' => 'https://opensandbox.test/checkRequestPayment',
                'qr_payment' => 'https://opensandbox.test/requestQRPayment',
                'refund_payment' => 'https://opensandbox.test/refundPayment',
            ],
            'timeout' => 10,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function kbzpay(array $overrides = []): array
    {
        return array_replace_recursive([
            'merchant_code' => 'TEST_MERCH',
            'merchant_id' => 'TEST_MERCH',
            'app_id' => 'TEST_APP_ID',
            'secret' => 'TEST_SECRET',
            'trade_type' => 'APP',
            'notify_url' => 'https://example.test/callback',
            'sub_type' => '',
            'sub_identifier_type' => '',
            'sub_identifier' => '',
            'endpoints' => [
                'precreate' => 'https://api.test/precreate',
                'queryorder' => 'https://api.test/queryorder',
                'refund' => 'https://api.test/refund',
                'mmqr' => 'https://api.test/precreate',
            ],
            'versions' => [
                'precreate' => '1.0',
                'queryorder' => '3.0',
                'refund' => '1.0',
                'mmqr' => '1.0',
            ],
            'timeout' => 10,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function twoC2p(array $overrides = []): array
    {
        return array_replace_recursive([
            'merchant_id' => 'JT01',
            'secret_key' => '0123456789abcdef0123456789abcdef',
            'merchant_private_key' => 'merchant-private',
            'two_c2p_public_key' => '2c2p-public',
            'locale' => 'en',
            'payment_description' => 'Payment',
            'maintenance_version' => '4.3',
            'endpoints' => [
                'payment_token' => 'https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken',
                'transaction_status' => 'https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus',
                'refund' => 'https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action',
            ],
            'timeout' => 10,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function waveMoney(array $overrides = []): array
    {
        return array_replace_recursive([
            'merchant_id' => 'WAVE_MERCHANT',
            'secret_key' => 'wave_secret',
            'merchant_name' => 'Wave Merchant',
            'payment_description' => 'Payment',
            'time_to_live_in_seconds' => 600,
            'endpoints' => [
                'payment' => 'https://testpayments.wavemoney.io:8107/payment',
                'authenticate' => 'https://testpayments.wavemoney.io/authenticate',
            ],
            'timeout' => 10,
        ], $overrides);
    }
}
