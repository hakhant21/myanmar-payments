<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function waveMoneyClient(array $config = []): WaveMoneyClient
{
    $defaults = [
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
    ];

    return new WaveMoneyClient(new HttpClient, array_replace_recursive($defaults, $config));
}

describe('WaveMoneyClient::createPayment()', function (): void {
    it('posts x-www-form-urlencoded data and parses json response', function (): void {
        Http::fake([
            'https://testpayments.wavemoney.io:8107/payment' => Http::response([
                'message' => 'success',
                'transaction_id' => 'enc_tx_123',
            ], 200),
        ]);

        $client = waveMoneyClient();

        $response = $client->createPayment([
            'time_to_live_in_seconds' => '600',
            'merchant_id' => 'WAVE_MERCHANT',
            'order_id' => 'ORDER-1',
            'merchant_reference_id' => 'REF-1',
            'frontend_result_url' => 'https://merchant.test/return',
            'backend_result_url' => 'https://merchant.test/callback',
            'amount' => '1000',
            'payment_description' => 'Order 1',
            'merchant_name' => 'Wave Merchant',
            'items' => '[{"name":"Order 1","amount":1000}]',
            'hash' => 'abc',
        ]);

        expect($response['message'])->toBe('success')
            ->and($response['transaction_id'])->toBe('enc_tx_123');

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->url() === 'https://testpayments.wavemoney.io:8107/payment'
                && $request->header('Content-Type') === ['application/x-www-form-urlencoded']
                && str_contains($body, 'merchant_id=WAVE_MERCHANT')
                && str_contains($body, 'order_id=ORDER-1');
        });
    });

    it('throws ProviderException when provider response is not json', function (): void {
        Http::fake([
            'https://testpayments.wavemoney.io:8107/payment' => Http::response('<html>bad</html>', 200),
        ]);

        $client = waveMoneyClient();

        expect(fn (): array => $client->createPayment(['merchant_id' => 'X']))
            ->toThrow(ProviderException::class, 'WaveMoney response is not valid JSON.');
    });
});

describe('WaveMoneyClient::authenticateUrl()', function (): void {
    it('builds authenticate redirect url with transaction id', function (): void {
        $client = waveMoneyClient();

        expect($client->authenticateUrl('enc_tx_123'))->toBe('https://testpayments.wavemoney.io/authenticate?transaction_id=enc_tx_123');
    });
});
