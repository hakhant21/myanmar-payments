<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAClient;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ayaClientConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'basic_token' => 'BASE64_BASIC',
        'phone' => '09999999999',
        'password' => '123456',
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
function ayaClient(array $config = []): AYAClient
{
    return new AYAClient(new HttpClient, ayaClientConfig($config));
}

describe('AYAClient', function (): void {
    it('throws when login response does not contain a token', function (): void {
        Http::fake([
            'https://opensandbox.test/login' => Http::response([
                'err' => 200,
                'message' => 'Success',
            ], 200),
        ]);

        expect(fn (): array => ayaClient()->requestPushPayment(['amount' => '1000']))
            ->toThrow(ProviderException::class, 'AYA login failed: token missing from response.');
    });
});
