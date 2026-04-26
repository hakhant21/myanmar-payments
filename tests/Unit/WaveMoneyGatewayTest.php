<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyClient;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyGateway;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyHash;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyMapper;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function waveMoneyGatewayConfig(array $overrides = []): array
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

function buildWaveMoneyGateway(array $config = []): WaveMoneyGateway
{
    $finalConfig = waveMoneyGatewayConfig($config);

    return new WaveMoneyGateway(
        new WaveMoneyClient(new HttpClient, $finalConfig),
        new WaveMoneyMapper,
        new WaveMoneyHash,
        $finalConfig,
    );
}

describe('WaveMoneyGateway::createPayment()', function (): void {
    it('creates payment request and returns authenticate url', function (): void {
        Http::fake([
            'https://testpayments.wavemoney.io:8107/payment' => Http::response([
                'message' => 'success',
                'transaction_id' => 'enc_tx_123',
            ], 200),
        ]);

        $gateway = buildWaveMoneyGateway();

        $response = $gateway->createPayment(new PaymentRequest(
            merchantReference: 'REF-1',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://merchant.test/callback',
            redirectUrl: 'https://merchant.test/return',
            metadata: [
                'order_id' => 'ORDER-1',
                'items' => [['name' => 'Product 1', 'amount' => 1000]],
            ],
        ));

        expect($response->provider)->toBe('wavemoney')
            ->and($response->transactionId)->toBe('enc_tx_123')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->paymentUrl)->toBe('https://testpayments.wavemoney.io/authenticate?transaction_id=enc_tx_123');

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'merchant_reference_id=REF-1')
                && str_contains($body, 'order_id=ORDER-1')
                && str_contains($body, 'hash=');
        });
    });

    it('throws ProviderException when items metadata cannot be encoded', function (): void {
        $gateway = buildWaveMoneyGateway();

        expect(fn (): mixed => $gateway->createPayment(new PaymentRequest(
            merchantReference: 'REF-1',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://merchant.test/callback',
            redirectUrl: 'https://merchant.test/return',
            metadata: ['items' => [tmpfile()]],
        )))->toThrow(ProviderException::class, 'WaveMoney items metadata is invalid.');
    });
});

describe('WaveMoneyGateway::queryStatus()', function (): void {
    it('throws unsupported exception because docs do not define status inquiry api', function (): void {
        $gateway = buildWaveMoneyGateway();

        expect(fn (): mixed => $gateway->queryStatus('enc_tx_123'))
            ->toThrow(ProviderException::class, 'WaveMoney queryStatus is not supported by this provider.');
    });
});

describe('WaveMoneyGateway::createMmqr()', function (): void {
    it('creates MMQR request and returns authenticate url as qr code', function (): void {
        Http::fake([
            'https://testpayments.wavemoney.io:8107/payment' => Http::response([
                'message' => 'success',
                'transaction_id' => 'enc_mmqr_123',
            ], 200),
        ]);

        $gateway = buildWaveMoneyGateway();

        $response = $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'WMMQR-1',
            amount: 1500,
            currency: 'MMK',
            notifyUrl: 'https://merchant.test/wave/mmqr/callback',
            metadata: [
                'order_id' => 'WMMQR-ORDER-1',
                'frontend_result_url' => 'https://merchant.test/wave/mmqr/return',
                'items' => [['name' => 'MMQR Item', 'amount' => 1500]],
            ],
        ));

        expect($response->provider)->toBe('wavemoney')
            ->and($response->transactionId)->toBe('enc_mmqr_123')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('https://testpayments.wavemoney.io/authenticate?transaction_id=enc_mmqr_123')
            ->and($response->qrImage)->toBeNull();

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return str_contains($body, 'merchant_reference_id=WMMQR-1')
                && str_contains($body, 'order_id=WMMQR-ORDER-1')
                && str_contains($body, 'backend_result_url=https%3A%2F%2Fmerchant.test%2Fwave%2Fmmqr%2Fcallback')
                && str_contains($body, 'hash=');
        });
    });

    it('throws ProviderException when MMQR items metadata cannot be encoded', function (): void {
        $gateway = buildWaveMoneyGateway();

        expect(fn (): mixed => $gateway->createMmqr(new MmqrRequest(
            merchantReference: 'WMMQR-2',
            amount: 1000,
            currency: 'MMK',
            notifyUrl: 'https://merchant.test/wave/mmqr/callback',
            metadata: ['items' => [tmpfile()]],
        )))->toThrow(ProviderException::class, 'WaveMoney MMQR items metadata is invalid.');
    });
});

describe('WaveMoneyGateway::verifyCallback()', function (): void {
    it('verifies callback hash and merchant id', function (): void {
        $gateway = buildWaveMoneyGateway();
        $hash = new WaveMoneyHash;

        $payload = [
            'status' => 'PAYMENT_CONFIRMED',
            'merchantId' => 'WAVE_MERCHANT',
            'orderId' => 'ORDER-1',
            'merchantReferenceId' => 'REF-1',
            'frontendResultUrl' => 'https://merchant.test/return',
            'backendResultUrl' => 'https://merchant.test/callback',
            'initiatorMsisdn' => '9791000000',
            'amount' => '1000',
            'timeToLiveSeconds' => 600,
            'paymentDescription' => 'Payment',
            'currency' => 'MMK',
            'transactionId' => 'tx-123',
            'paymentRequestId' => 'pr-123',
            'requestTime' => '2026-04-24T12:00:00',
        ];

        $payload['hashValue'] = $hash->sign([
            $payload['status'],
            $payload['timeToLiveSeconds'],
            $payload['merchantId'],
            $payload['orderId'],
            $payload['amount'],
            $payload['backendResultUrl'],
            $payload['merchantReferenceId'],
            $payload['initiatorMsisdn'],
            $payload['transactionId'],
            $payload['paymentRequestId'],
            $payload['requestTime'],
        ], 'wave_secret');

        expect($gateway->verifyCallback(new CallbackPayload($payload, '')))->toBeTrue();
    });

    it('uses signature fallback and rejects invalid callbacks', function (): void {
        $gateway = buildWaveMoneyGateway();
        $hash = new WaveMoneyHash;

        $payload = [
            'status' => 'PAYMENT_CONFIRMED',
            'merchantId' => 'WAVE_MERCHANT',
            'orderId' => 'ORDER-1',
            'merchantReferenceId' => 'REF-1',
            'backendResultUrl' => 'https://merchant.test/callback',
            'initiatorMsisdn' => '9791000000',
            'amount' => '1000',
            'timeToLiveSeconds' => 600,
            'transactionId' => 'tx-123',
            'paymentRequestId' => 'pr-123',
            'requestTime' => '2026-04-24T12:00:00',
        ];

        $signature = $hash->sign([
            $payload['status'],
            $payload['timeToLiveSeconds'],
            $payload['merchantId'],
            $payload['orderId'],
            $payload['amount'],
            $payload['backendResultUrl'],
            $payload['merchantReferenceId'],
            $payload['initiatorMsisdn'],
            $payload['transactionId'],
            $payload['paymentRequestId'],
            $payload['requestTime'],
        ], 'wave_secret');

        expect($gateway->verifyCallback(new CallbackPayload($payload, $signature)))->toBeTrue()
            ->and($gateway->verifyCallback(new CallbackPayload(array_merge($payload, ['merchantId' => 'OTHER']), $signature)))->toBeFalse()
            ->and($gateway->verifyCallback(new CallbackPayload($payload, 'bad-signature')))->toBeFalse()
            ->and($gateway->verifyCallback(new CallbackPayload($payload, '')))->toBeFalse();
    });
});
