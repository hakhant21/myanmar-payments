<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\WaveMoney;

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use JsonException;

final readonly class WaveMoneyClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $config,
    ) {}

    /**
     * @param  array<string, scalar>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        $raw = $this->httpClient->postRaw(
            $this->paymentUrl(),
            http_build_query($payload),
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            $this->timeout(),
        );

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProviderException('WaveMoney response is not valid JSON.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function authenticateUrl(string $transactionId): string
    {
        $base = (string) ($this->config['endpoints']['authenticate'] ?? 'https://testpayments.wavemoney.io/authenticate');

        return $base.'?transaction_id='.urlencode($transactionId);
    }

    private function paymentUrl(): string
    {
        return (string) ($this->config['endpoints']['payment'] ?? 'https://testpayments.wavemoney.io:8107/payment');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }
}
