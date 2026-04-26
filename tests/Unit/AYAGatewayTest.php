<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAClient;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAGateway;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ayaGatewayConfig(array $overrides = []): array
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
 * @param  array<string, mixed>  $config
 */
function buildAyaGateway(array $config = []): AYAGateway
{
    $finalConfig = ayaGatewayConfig($config);

    return new AYAGateway(
        new AYAClient(new HttpClient, $finalConfig),
        new AYAMapper,
        $finalConfig,
    );
}

describe('AYAGateway::createPayment()', function (): void {
    it('creates push payment and returns PaymentResponse using v2 endpoint when service code exists', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'err' => 200,
                'message' => 'Success',
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/v2/requestPushPayment' => Http::response([
                'err' => 200,
                'message' => 'Success',
                'data' => [
                    'externalTransactionId' => 'ORD001',
                    'referenceNumber' => 'REF001',
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway();

        $response = $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: [
                'customer_phone' => '09111111111',
                'description' => 'Invoice ORD001',
            ],
        ));

        expect($response)->toBeInstanceOf(PaymentResponse::class)
            ->and($response->provider)->toBe('aya')
            ->and($response->transactionId)->toBe('ORD001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->paymentUrl)->toBeNull();

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/login'
                && $request->hasHeader('apikey', 'Basic BASE64_BASIC')
                && $request['phone'] === '09999999999'
                && $request['password'] === '123456';
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/v2/requestPushPayment'
                && $request->hasHeader('Authorization', 'Bearer TOKEN123')
                && $request['amount'] === 1000
                && $request['currency'] === 'MMK'
                && $request['customerPhone'] === '09111111111'
                && $request['externalTransactionId'] === 'ORD001'
                && $request['externalAdditionalData'] === 'Invoice ORD001'
                && $request['serviceCode'] === 'SERVICE_CODE'
                && $request['timelimit'] === 2;
        });
    });

    it('uses v1 push payment when no service code is configured', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/requestPushPayment' => Http::response([
                'data' => [
                    'externalTransactionId' => 'ORD002',
                    'referenceNumber' => 'REF002',
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway(['service_code' => '']);

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD002',
            amount: 2000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: [
                'customer_phone' => '09222222222',
                'message' => 'Pay now',
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/requestPushPayment'
                && $request['amount'] === '2000'
                && $request['customerPhone'] === '09222222222'
                && $request['message'] === 'Pay now';
        });
    });

    it('throws when customer phone metadata is missing', function (): void {
        $gateway = buildAyaGateway();

        expect(fn (): mixed => $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD003',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        )))->toThrow(ProviderException::class, 'AYA customer_phone metadata is required.');
    });

    it('accepts camelCase customer phone metadata', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/requestPushPayment' => Http::response([
                'data' => [
                    'externalTransactionId' => 'ORD003A',
                    'referenceNumber' => 'REF003A',
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway(['service_code' => '']);

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD003A',
            amount: 1500,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: [
                'customerPhone' => '09333333333',
                'title' => 'Camel Case Title',
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/requestPushPayment'
                && $request['customerPhone'] === '09333333333'
                && $request['externalAdditionalData'] === 'Camel Case Title';
        });
    });

    it('throws when push payment v2 is selected but service code is empty', function (): void {
        $gateway = buildAyaGateway(['service_code' => '']);

        expect(fn (): mixed => $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD003B',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: [
                'customer_phone' => '09111111111',
                'service_code' => '',
            ],
        )))->toThrow(ProviderException::class, 'AYA service_code is required for push payment v2.');
    });

    it('does not send timelimit when time limit is not numeric', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/v2/requestPushPayment' => Http::response([
                'data' => [
                    'externalTransactionId' => 'ORD003C',
                    'referenceNumber' => 'REF003C',
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway(['time_limit' => 'later']);

        $gateway->createPayment(new PaymentRequest(
            merchantReference: 'ORD003C',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: [
                'customer_phone' => '09111111111',
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/v2/requestPushPayment'
                && ! array_key_exists('timelimit', $request->data());
        });
    });
});

describe('AYAGateway::queryStatus()', function (): void {
    it('queries payment status and maps success response', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/checkRequestPayment' => Http::response([
                'err' => 200,
                'message' => 'Success',
                'status' => 'success',
                'transRefId' => 'TRANS-001',
            ], 200),
        ]);

        $gateway = buildAyaGateway();
        $response = $gateway->queryStatus('ORD004');

        expect($response)->toBeInstanceOf(PaymentResponse::class)
            ->and($response->status)->toBe(PaymentStatus::SUCCESS)
            ->and($response->transactionId)->toBe('TRANS-001');
    });
});

describe('AYAGateway::refund()', function (): void {
    it('refunds payment when reference number metadata is provided', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/refundPayment' => Http::response([
                'err' => 200,
                'message' => 'Success',
            ], 200),
        ]);

        $gateway = buildAyaGateway();

        $response = $gateway->refund(new RefundRequest(
            transactionId: 'ORD005',
            amount: 1000,
            metadata: ['reference_number' => 'REF005'],
        ));

        expect($response)->toBeInstanceOf(RefundResponse::class)
            ->and($response->provider)->toBe('aya')
            ->and($response->refundId)->toBe('REF005')
            ->and($response->status)->toBe(PaymentStatus::REFUNDED);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/refundPayment'
                && $request['externalTransactionId'] === 'ORD005'
                && $request['referenceNumber'] === 'REF005';
        });
    });

    it('throws when refund reference number metadata is missing', function (): void {
        $gateway = buildAyaGateway();

        expect(fn (): mixed => $gateway->refund(new RefundRequest(
            transactionId: 'ORD006',
            amount: 1000,
        )))->toThrow(ProviderException::class, 'AYA refund metadata reference_number is required.');
    });

    it('accepts camelCase reference number metadata', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/refundPayment' => Http::response([
                'err' => 200,
                'message' => 'Success',
            ], 200),
        ]);

        $gateway = buildAyaGateway();

        $gateway->refund(new RefundRequest(
            transactionId: 'ORD006A',
            amount: 1000,
            metadata: ['referenceNumber' => 'REF006A'],
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/refundPayment'
                && $request['referenceNumber'] === 'REF006A';
        });
    });
});

describe('AYAGateway::createMmqr()', function (): void {
    it('creates mmqr and maps qrdata response', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/requestQRPayment' => Http::response([
                'err' => 200,
                'message' => 'Success',
                'data' => [
                    'externalTransactionId' => 'MMQR001',
                    'referenceNumber' => 'QRM4041416',
                    'qrdata' => '000203010212...',
                    'status' => 0,
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway();

        $response = $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'MMQR001',
            amount: 3000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
            metadata: ['description' => 'Invoice MMQR001'],
        ));

        expect($response)->toBeInstanceOf(MmqrResponse::class)
            ->and($response->provider)->toBe('aya')
            ->and($response->transactionId)->toBe('MMQR001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('000203010212...');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/requestQRPayment'
                && $request['amount'] === '3000'
                && $request['currency'] === 'MMK'
                && $request['externalTransactionId'] === 'MMQR001'
                && $request['externalAdditionalData'] === 'Invoice MMQR001';
        });
    });

    it('prefers external_additional_data for mmqr description', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'token' => ['token' => 'TOKEN123'],
            ], 200),
            'https://opensandbox.test/requestQRPayment' => Http::response([
                'data' => [
                    'externalTransactionId' => 'MMQR002',
                    'qrdata' => 'QRDATA002',
                    'status' => 0,
                ],
            ], 200),
        ]);

        $gateway = buildAyaGateway();

        $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'MMQR002',
            amount: 4000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
            metadata: [
                'external_additional_data' => 'External Note',
                'description' => 'Ignored Description',
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://opensandbox.test/requestQRPayment'
                && $request['externalAdditionalData'] === 'External Note';
        });
    });
});
