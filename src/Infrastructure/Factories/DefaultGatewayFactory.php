<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Factories;

use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PClient;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PKeyJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PMapper;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyClient;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyGateway;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyHash;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyMapper;

final readonly class DefaultGatewayFactory implements GatewayFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $config,
    ) {}

    public function make(string $provider): PaymentGateway
    {
        $providerConfig = $this->providerConfig($provider);

        return match ($provider) {
            '2c2p' => new TwoC2PGateway(
                new TwoC2PClient($this->httpClient, $providerConfig),
                new TwoC2PMapper,
                new TwoC2PJwt,
                new TwoC2PKeyJwt,
                $providerConfig,
            ),
            'kbzpay' => new KBZPayGateway(
                new KBZPayClient($this->httpClient, $providerConfig),
                new KBZPayMapper,
                new KBZPaySignature,
                $providerConfig,
            ),
            'wavemoney' => new WaveMoneyGateway(
                new WaveMoneyClient($this->httpClient, $providerConfig),
                new WaveMoneyMapper,
                new WaveMoneyHash,
                $providerConfig,
            ),
            default => throw new ProviderException(sprintf('Unsupported provider: %s', $provider)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider): array
    {
        $providers = $this->config['providers'] ?? [];

        if (! is_array($providers) || ! array_key_exists($provider, $providers)) {
            throw new ProviderException(sprintf('Provider config missing for: %s', $provider));
        }

        $config = $providers[$provider];

        if (! is_array($config)) {
            throw new ProviderException(sprintf('Provider config invalid for: %s', $provider));
        }

        return $config;
    }
}
