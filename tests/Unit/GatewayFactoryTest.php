<?php

declare(strict_types=1);

use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Factories\GatewayFactory;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\AYA\AYAGateway;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyGateway;
use Hakhant\Payments\Tests\Support\ProviderConfig;

/**
 * @param  array<string, mixed>  $config
 */
function makeFactory(array $config = []): GatewayFactory
{
    $http = new HttpClient;
    $defaults = [
        'providers' => [
            '2c2p' => ProviderConfig::twoC2p(),
            'aya' => ProviderConfig::aya(),
            'kbzpay' => ProviderConfig::kbzpay([
                'app_id' => 'TEST_APP',
            ]),
            'wavemoney' => ProviderConfig::waveMoney(),
        ],
    ];

    return new GatewayFactory($http, array_replace_recursive($defaults, $config));
}

describe('GatewayFactory::make()', function (): void {
    it('resolves 2c2p provider as TwoC2PGateway', function (): void {
        $gateway = makeFactory()->make('2c2p');
        expect($gateway)->toBeInstanceOf(TwoC2PGateway::class);
    });

    it('resolves kbzpay provider as KBZPayGateway', function (): void {
        $gateway = makeFactory()->make('kbzpay');
        expect($gateway)->toBeInstanceOf(KBZPayGateway::class);
    });

    it('resolves aya provider as AYAGateway', function (): void {
        $gateway = makeFactory()->make('aya');
        expect($gateway)->toBeInstanceOf(AYAGateway::class)
            ->and($gateway)->toBeInstanceOf(CanInitiateMmqr::class);
    });

    it('resolves wavemoney provider as WaveMoneyGateway', function (): void {
        $gateway = makeFactory()->make('wavemoney');
        expect($gateway)->toBeInstanceOf(WaveMoneyGateway::class)
            ->and($gateway)->toBeInstanceOf(CanInitiateMmqr::class);
    });

    it('resolves providers from enum values', function (): void {
        $gateway = makeFactory()->make(Provider::KBZPAY);

        expect($gateway)->toBeInstanceOf(KBZPayGateway::class);
    });

    it('normalizes provider strings before lookup', function (): void {
        $gateway = makeFactory()->make(' KBZPAY ');

        expect($gateway)->toBeInstanceOf(KBZPayGateway::class);
    });

    it('throws ProviderException for unsupported provider', function (): void {
        expect(fn (): PaymentGateway => makeFactory()->make('unknown_provider'))
            ->toThrow(ProviderException::class, 'Provider config missing for: unknown_provider');
    });

    it('throws ProviderException when provider config is missing', function (): void {
        $factory = new GatewayFactory(
            new HttpClient,
            ['providers' => []],
        );

        expect(fn (): PaymentGateway => $factory->make('kbzpay'))
            ->toThrow(ProviderException::class, 'Provider config missing for: kbzpay');
    });

    it('throws ProviderException when providers key is absent', function (): void {
        $factory = new GatewayFactory(new HttpClient, []);

        expect(fn (): PaymentGateway => $factory->make('kbzpay'))
            ->toThrow(ProviderException::class);
    });

    it('throws unsupported provider when config exists but provider is not implemented', function (): void {
        $factory = new GatewayFactory(
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
        $factory = new GatewayFactory(
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
