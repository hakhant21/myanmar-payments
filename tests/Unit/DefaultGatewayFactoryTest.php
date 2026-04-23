<?php

declare(strict_types=1);

use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Factories\DefaultGatewayFactory;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayGateway;

function makeFactory(array $config = []): DefaultGatewayFactory
{
    $http = new HttpClient;
    $defaults = [
        'providers' => [
            'kbzpay' => [
                'app_id' => 'TEST_APP',
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
            ],
        ],
    ];

    return new DefaultGatewayFactory($http, array_replace_recursive($defaults, $config));
}

describe('DefaultGatewayFactory::make()', function (): void {
    it('resolves kbzpay provider as KBZPayGateway', function (): void {
        $gateway = makeFactory()->make('kbzpay');
        expect($gateway)->toBeInstanceOf(KBZPayGateway::class);
    });

    it('throws ProviderException for unsupported provider', function (): void {
        expect(fn (): PaymentGateway => makeFactory()->make('unknown_provider'))
            ->toThrow(ProviderException::class, 'Provider config missing for: unknown_provider');
    });

    it('throws ProviderException when provider config is missing', function (): void {
        $factory = new DefaultGatewayFactory(
            new HttpClient,
            ['providers' => []],
        );

        expect(fn (): PaymentGateway => $factory->make('kbzpay'))
            ->toThrow(ProviderException::class, 'Provider config missing for: kbzpay');
    });

    it('throws ProviderException when providers key is absent', function (): void {
        $factory = new DefaultGatewayFactory(new HttpClient, []);

        expect(fn (): PaymentGateway => $factory->make('kbzpay'))
            ->toThrow(ProviderException::class);
    });

    it('throws unsupported provider when config exists but provider is not implemented', function (): void {
        $factory = new DefaultGatewayFactory(
            new HttpClient,
            [
                'providers' => [
                    'not-implemented' => ['foo' => 'bar'],
                ],
            ],
        );

        expect(fn (): PaymentGateway => $factory->make('not-implemented'))
            ->toThrow(ProviderException::class, 'Unsupported provider: not-implemented');
    });

    it('throws ProviderException when provider config is not an array', function (): void {
        $factory = new DefaultGatewayFactory(
            new HttpClient,
            [
                'providers' => [
                    'kbzpay' => 'invalid',
                ],
            ],
        );

        expect(fn (): PaymentGateway => $factory->make('kbzpay'))
            ->toThrow(ProviderException::class, 'Provider config invalid for: kbzpay');
    });
});
