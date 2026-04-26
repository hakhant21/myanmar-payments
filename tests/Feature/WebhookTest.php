<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Illuminate\Support\Facades\Http;

function makeWebhookGateway(): array
{
    $config = [
        'app_id' => 'TEST_APP_ID',
        'merchant_code' => 'TEST_MERCH',
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
            'mmqr' => 'https://api.test/mmqr',
        ],
        'versions' => ['precreate' => '1.0', 'queryorder' => '3.0', 'refund' => '1.0', 'mmqr' => '1.0'],
        'timeout' => 10,
    ];

    $http = new HttpClient;
    $client = new KBZPayClient($http, $config);
    $mapper = new KBZPayMapper;
    $signature = new KBZPaySignature;

    return [new KBZPayGateway($client, $mapper, $signature, $config), $signature, $config];
}

describe('KBZPay Webhook / Callback handling', function (): void {
    it('verifies a valid KBZ payment webhook with Request envelope', function (): void {
        [$gateway, $signature, $config] = makeWebhookGateway();

        $fields = [
            'appid' => 'TEST_APP_ID',
            'merch_code' => 'TEST_MERCH',
            'merch_order_id' => 'ORD_WEBHOOK_001',
            'trade_status' => 'PAY_SUCCESS',
            'total_amount' => '5000',
            'trans_currency' => 'MMK',
            'sign_type' => 'SHA256',
            'timestamp' => '1700000000',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = $sign;

        $payload = new CallbackPayload(
            payload: ['Request' => $fields],
            signature: $sign,
            timestamp: 1700000000,
        );

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('rejects webhook with mismatched signature', function (): void {
        [$gateway] = makeWebhookGateway();

        $fields = [
            'appid' => 'TEST_APP_ID',
            'merch_order_id' => 'ORD_WEBHOOK_002',
            'trade_status' => 'PAY_SUCCESS',
            'sign' => 'COMPLETELY_WRONG_SIGNATURE',
            'sign_type' => 'SHA256',
        ];

        $payload = new CallbackPayload(
            payload: ['Request' => $fields],
            signature: 'COMPLETELY_WRONG_SIGNATURE',
        );

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('rejects webhook with tampered trade_status field', function (): void {
        [$gateway, $signature, $config] = makeWebhookGateway();

        $originalFields = [
            'merch_order_id' => 'ORD_TAMPER',
            'trade_status' => 'WAIT_PAY',
            'total_amount' => '1000',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($originalFields, $config['secret']);

        // Attacker tampers the status to PAY_SUCCESS
        $tamperedFields = $originalFields;
        $tamperedFields['trade_status'] = 'PAY_SUCCESS';
        $tamperedFields['sign'] = $sign;

        $payload = new CallbackPayload(
            payload: ['Request' => $tamperedFields],
            signature: $sign,
        );

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('rejects webhook when secret is different', function (): void {
        $config = [
            'app_id' => 'APP',
            'merchant_code' => 'MERCH',
            'secret' => 'real_secret',
            'trade_type' => 'APP',
            'notify_url' => 'https://example.test/callback',
            'sub_type' => '',
            'sub_identifier_type' => '',
            'sub_identifier' => '',
            'endpoints' => [],
            'versions' => [],
            'timeout' => 10,
        ];

        $http = new HttpClient;
        $client = new KBZPayClient($http, $config);
        $mapper = new KBZPayMapper;
        $signature = new KBZPaySignature;
        $gateway = new KBZPayGateway($client, $mapper, $signature, $config);

        // Sign with attacker's secret
        $fields = ['merch_order_id' => 'ORD_BAD', 'trade_status' => 'PAY_SUCCESS'];
        $fakeSign = $signature->sign($fields, 'attacker_secret');
        $fields['sign'] = $fakeSign;

        $payload = new CallbackPayload(
            payload: ['Request' => $fields],
            signature: $fakeSign,
        );

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('handles webhook with flat payload (no Request envelope)', function (): void {
        [$gateway, $signature, $config] = makeWebhookGateway();

        $fields = [
            'merch_order_id' => 'ORD_FLAT',
            'trade_status' => 'PAY_SUCCESS',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = $sign;

        $payload = new CallbackPayload(
            payload: $fields,
            signature: $sign,
        );

        // Flat payload (no 'Request' key) should still verify correctly
        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('verifies a MMQR payment webhook callback', function (): void {
        [$gateway, $signature, $config] = makeWebhookGateway();

        $fields = [
            'appid' => 'TEST_APP_ID',
            'merch_code' => 'TEST_MERCH',
            'merch_order_id' => 'MMQR_WEBHOOK_001',
            'trade_status' => 'PAY_SUCCESS',
            'trade_type' => 'MMQR',
            'total_amount' => '3000',
            'trans_currency' => 'MMK',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = $sign;

        $payload = new CallbackPayload(
            payload: ['Request' => $fields],
            signature: $sign,
        );

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('returns false for completely empty payload', function (): void {
        [$gateway] = makeWebhookGateway();

        $payload = new CallbackPayload(payload: [], signature: '');
        expect($gateway->verifyCallback($payload))->toBeFalse();
    });
});

describe('KBZPay end-to-end with HTTP fake', function (): void {
    it('precreate returns prepay_id on success response', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'code' => '0',
                    'msg' => 'success',
                    'prepay_id' => 'PRE_E2E_001',
                    'merch_order_id' => 'ORD_E2E_001',
                    'trade_status' => 'WAIT_PAY',
                    'sign' => 'FAKE_SIGN',
                    'sign_type' => 'SHA256',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $http = new HttpClient;
        $client = new KBZPayClient($http, $config);
        $mapper = new KBZPayMapper;
        $signature = new KBZPaySignature;
        $gateway = new KBZPayGateway($client, $mapper, $signature, $config);

        $request = new PaymentRequest(
            merchantReference: 'ORD_E2E_001',
            amount: 5000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        );

        $response = $gateway->createPayment($request);

        expect($response->transactionId)->toBe('ORD_E2E_001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->paymentUrl)->toBe('PRE_E2E_001');
    });

    it('queryOrder returns SUCCESS status', function (): void {
        Http::fake([
            'https://api.test/queryorder' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'merch_order_id' => 'ORD_Q001',
                    'trade_status' => 'PAY_SUCCESS',
                    'sign' => 'FAKE_SIGN',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $response = $gateway->queryStatus('ORD_Q001');
        expect($response->status)->toBe(PaymentStatus::SUCCESS);
    });

    it('refund returns REFUNDED status', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response([
                'Response' => [
                    'result' => 'SUCCESS',
                    'refund_order_id' => 'REF_E2E_001',
                    'refund_status' => 'REFUND_SUCCESS',
                    'sign' => 'FAKE_SIGN',
                ],
            ], 200),
        ]);

        $config = config('myanmar-payments.providers.kbzpay');
        $gateway = new KBZPayGateway(
            new KBZPayClient(new HttpClient, $config),
            new KBZPayMapper,
            new KBZPaySignature,
            $config,
        );

        $response = $gateway->refund(new RefundRequest(
            transactionId: 'ORD_E2E_001',
            amount: 5000,
            reason: 'Test refund',
        ));

        expect($response->status)->toBe(PaymentStatus::REFUNDED)
            ->and($response->refundId)->toBe('REF_E2E_001');
    });
});
