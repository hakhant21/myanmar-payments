<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Factories;

use Hakhant\Payments\Contracts\GatewayContract;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAClient;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAGateway;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayClient;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PClient;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PKeyJwt;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PMapper;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyClient;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyGateway;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyHash;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyMapper;
use Hakhant\Payments\Infrastructure\Http\HttpClient;

final readonly class GatewayFactory implements GatewayContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $config,
        private ?TwoC2PMapper $twoC2PMapper = null,
        private ?TwoC2PJwt $twoC2PJwt = null,
        private ?TwoC2PKeyJwt $twoC2PKeyJwt = null,
        private ?AYAMapper $ayaMapper = null,
        private ?KBZPayMapper $kbzPayMapper = null,
        private ?KBZPaySignature $kbzPaySignature = null,
        private ?WaveMoneyMapper $waveMoneyMapper = null,
        private ?WaveMoneyHash $waveMoneyHash = null,
    ) {}

    public function make(Provider|string $provider): PaymentGateway
    {
        $provider = $this->normalizeProvider($provider);
        $providerConfig = $this->providerConfig($provider);

        return match ($provider) {
            '2c2p' => new TwoC2PGateway(
                new TwoC2PClient($this->httpClient, $providerConfig),
                $this->twoC2PMapper ?? new TwoC2PMapper,
                $this->twoC2PJwt ?? new TwoC2PJwt,
                $this->twoC2PKeyJwt ?? new TwoC2PKeyJwt,
                $providerConfig,
            ),
            'aya' => new AYAGateway(
                new AYAClient($this->httpClient, $providerConfig),
                $this->ayaMapper ?? new AYAMapper,
                $providerConfig,
            ),
            'kbzpay' => new KBZPayGateway(
                new KBZPayClient($this->httpClient, $providerConfig),
                $this->kbzPayMapper ?? new KBZPayMapper,
                $this->kbzPaySignature ?? new KBZPaySignature,
                $providerConfig,
            ),
            'wavemoney' => new WaveMoneyGateway(
                new WaveMoneyClient($this->httpClient, $providerConfig),
                $this->waveMoneyMapper ?? new WaveMoneyMapper,
                $this->waveMoneyHash ?? new WaveMoneyHash,
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

    private function normalizeProvider(Provider|string $provider): string
    {
        return Provider::normalize($provider);
    }
}
