<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function gatewayConfig(array $overrides = []): array
{
    return array_replace_recursive([
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

function buildGateway(array $config = []): array
{
    $finalConfig = gatewayConfig($config);
    $gateway = new KBZPayGateway(
        new KBZPayClient(new HttpClient, $finalConfig),
        new KBZPayMapper,
        new KBZPaySignature,
        $finalConfig,
    );

    return [$gateway, $finalConfig];
}

describe('KBZPayGateway::createPayment()', function (): void {
    it('creates payment and returns PaymentResponse', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response([
                'Response' => [
                    'merch_order_id' => 'ORD001',
                    'prepay_id' => 'PRE001',
                    'trade_status' => 'WAIT_PAY',
                    'result' => 'SUCCESS',
                ],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $request = new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        );

        $response = $gateway->createPayment($request);

        expect($response)->toBeInstanceOf(PaymentResponse::class)
            ->and($response->provider)->toBe('kbzpay')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->transactionId)->toBe('ORD001');
    });

    it('sends expected biz_content fields', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response([
                'Response' => ['merch_order_id' => 'ORD999', 'trade_status' => 'WAIT_PAY'],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD999',
            amount: 2500,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: ['title' => 'My Product'],
        ));

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];
            $biz = $payload['biz_content'] ?? [];

            return ($biz['appid'] ?? null) === 'TEST_APP_ID'
                && ($biz['merch_code'] ?? null) === 'TEST_MERCH'
                && ($biz['merch_order_id'] ?? null) === 'ORD999'
                && ($biz['total_amount'] ?? null) === '2500'
                && ($biz['trans_currency'] ?? null) === 'MMK'
                && ($biz['title'] ?? null) === 'My Product'
                && ($payload['notify_url'] ?? null) === 'https://example.test/callback';
        });
    });

    it('uses the request callbackUrl instead of config notify_url', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response([
                'Response' => ['merch_order_id' => 'ORD-CB-001', 'trade_status' => 'WAIT_PAY'],
            ], 200),
        ]);

        [$gateway] = buildGateway(['notify_url' => 'https://example.test/config-callback']);

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD-CB-001',
            amount: 2500,
            currency: 'MMK',
            callbackUrl: 'https://example.test/request-callback',
            redirectUrl: 'https://example.test/return',
        ));

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];

            return ($payload['notify_url'] ?? null) === 'https://example.test/request-callback';
        });
    });
});

describe('KBZPayGateway::queryStatus()', function (): void {
    it('queries order status and maps response', function (): void {
        Http::fake([
            'https://api.test/queryorder' => Http::response([
                'Response' => ['merch_order_id' => 'ORD_QUERY', 'trade_status' => 'PAY_SUCCESS'],
            ], 200),
        ]);

        [$gateway] = buildGateway();
        $response = $gateway->queryStatus('ORD_QUERY');

        expect($response)->toBeInstanceOf(PaymentResponse::class)
            ->and($response->status)->toBe(PaymentStatus::SUCCESS);
    });
});

describe('KBZPayGateway::refund()', function (): void {
    it('initiates refund and maps response', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response([
                'Response' => ['refund_order_id' => 'REF001', 'refund_status' => 'REFUND_SUCCESS'],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $response = $gateway->refund(new RefundRequest(
            transactionId: 'ORD001',
            amount: 500,
            reason: 'Customer request',
        ));

        expect($response)->toBeInstanceOf(RefundResponse::class)
            ->and($response->status)->toBe(PaymentStatus::REFUNDED)
            ->and($response->refundId)->toBe('REF001');
    });

    it('uses metadata refund_request_no instead of human reason text', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response([
                'Response' => ['refund_order_id' => 'REF002', 'refund_status' => 'REFUND_SUCCESS'],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $gateway->refund(new RefundRequest(
            transactionId: 'ORD002',
            amount: 500,
            reason: 'Customer requested cancellation',
            metadata: ['refund_request_no' => 'REFUND-REQ-002'],
        ));

        Http::assertSent(function (Request $request): bool {
            $biz = (($request->data())['Request'] ?? [])['biz_content'] ?? [];

            return ($biz['refund_request_no'] ?? null) === 'REFUND-REQ-002'
                && ($biz['refund_reason'] ?? null) === 'Customer requested cancellation';
        });
    });

    it('falls back to transaction based refund_request_no when metadata is absent', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response([
                'Response' => ['refund_order_id' => 'REF003', 'refund_status' => 'REFUND_SUCCESS'],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $gateway->refund(new RefundRequest(
            transactionId: 'ORD003',
            amount: 500,
            reason: 'Customer requested cancellation',
        ));

        Http::assertSent(function (Request $request): bool {
            $biz = (($request->data())['Request'] ?? [])['biz_content'] ?? [];

            return ($biz['refund_request_no'] ?? null) === 'ORD003-refund'
                && ($biz['refund_reason'] ?? null) === 'Customer requested cancellation';
        });
    });

    it('supports KBZ full refunds without refund_amount and with is_last_refund', function (): void {
        Http::fake([
            'https://api.test/refund' => Http::response([
                'Response' => ['refund_order_id' => 'REF004', 'refund_status' => 'REFUND_SUCCESS'],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $gateway->refund(new RefundRequest(
            transactionId: 'ORD004',
            amount: null,
            reason: 'Full refund',
            metadata: ['is_last_refund' => true],
        ));

        Http::assertSent(function (Request $request): bool {
            $biz = (($request->data())['Request'] ?? [])['biz_content'] ?? [];

            return ($biz['is_last_refund'] ?? null) === 'Y'
                && ! array_key_exists('refund_amount', $biz);
        });
    });
});

describe('KBZPayGateway::verifyCallback()', function (): void {
    it('returns true for valid callback signature', function (): void {
        [$gateway, $config] = buildGateway();
        $signature = new KBZPaySignature;

        $fields = [
            'appid' => 'TEST_APP_ID',
            'merch_code' => 'TEST_MERCH',
            'trade_status' => 'PAY_SUCCESS',
            'merch_order_id' => 'ORD001',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = $sign;

        $payload = new CallbackPayload(payload: ['Request' => $fields], signature: $sign);

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('returns false for invalid callback signature', function (): void {
        [$gateway] = buildGateway();

        $fields = [
            'appid' => 'TEST_APP_ID',
            'trade_status' => 'PAY_SUCCESS',
            'sign' => 'INVALID_SIGNATURE_AABBCC',
        ];

        $payload = new CallbackPayload(payload: ['Request' => $fields], signature: 'INVALID_SIGNATURE_AABBCC');

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('returns false when payload request is not an array', function (): void {
        [$gateway] = buildGateway();

        $payload = new CallbackPayload(payload: ['Request' => 'not_an_array'], signature: '');

        expect($gateway->verifyCallback($payload))->toBeFalse();
    });

    it('uses payload signature when callback sign field is not scalar', function (): void {
        [$gateway, $config] = buildGateway();
        $signature = new KBZPaySignature;

        $fields = [
            'appid' => 'TEST_APP_ID',
            'merch_code' => 'TEST_MERCH',
            'trade_status' => 'PAY_SUCCESS',
            'merch_order_id' => 'ORD001',
            'sign_type' => 'SHA256',
        ];
        $sign = $signature->sign($fields, $config['secret']);
        $fields['sign'] = ['unexpected'];

        $payload = new CallbackPayload(payload: ['Request' => $fields], signature: $sign);

        expect($gateway->verifyCallback($payload))->toBeTrue();
    });

    it('returns success as the callback success response', function (): void {
        [$gateway] = buildGateway();

        expect($gateway->callbackSuccessResponse())->toBe('success');
    });
});

describe('KBZPayGateway::createMmqr()', function (): void {
    it('creates MMQR payment and returns MmqrResponse', function (): void {
        Http::fake([
            'https://api.test/precreate' => Http::response([
                'Response' => [
                    'merch_order_id' => 'MMQR001',
                    'trade_status' => 'WAIT_PAY',
                    'qr_code' => '00020101...',
                    'result' => 'SUCCESS',
                ],
            ], 200),
        ]);

        [$gateway] = buildGateway();

        $response = $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'MMQR001',
            amount: 3000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
        ));

        expect($response)->toBeInstanceOf(MmqrResponse::class)
            ->and($response->provider)->toBe('kbzpay')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('00020101...');

        Http::assertSent(function (Request $request): bool {
            $payload = ($request->data())['Request'] ?? [];
            $biz = $payload['biz_content'] ?? [];

            return ($biz['trade_type'] ?? null) === 'PAY_BY_QRCODE'
                && ($payload['notify_url'] ?? null) === 'https://example.test/notify'
                && ! array_key_exists('notify_url', is_array($biz) ? $biz : []);
        });
    });
});
