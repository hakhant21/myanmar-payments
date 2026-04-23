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
            'kbzpay' => new KBZPayGateway(
                new KBZPayClient($this->httpClient, $providerConfig),
                new KBZPayMapper,
                new KBZPaySignature,
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
