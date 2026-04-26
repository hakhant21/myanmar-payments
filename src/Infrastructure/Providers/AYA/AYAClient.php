<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\AYA;

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;

final readonly class AYAClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requestPushPayment(array $payload, bool $useV2 = false): array
    {
        return $this->httpClient->post(
            $useV2 ? $this->pushPaymentV2Url() : $this->pushPaymentUrl(),
            $payload,
            $this->bearerHeaders(),
            $this->timeout(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkRequestPayment(array $payload): array
    {
        return $this->httpClient->post(
            $this->queryPaymentUrl(),
            $payload,
            $this->bearerHeaders(),
            $this->timeout(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requestQrPayment(array $payload): array
    {
        return $this->httpClient->post(
            $this->qrPaymentUrl(),
            $payload,
            $this->bearerHeaders(),
            $this->timeout(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refundPayment(array $payload): array
    {
        return $this->httpClient->post(
            $this->refundPaymentUrl(),
            $payload,
            $this->bearerHeaders(),
            $this->timeout(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->loginToken(),
            'Content-Type' => 'application/json',
        ];
    }

    private function loginToken(): string
    {
        $response = $this->httpClient->post(
            $this->loginUrl(),
            [
                'phone' => $this->phone(),
                'password' => $this->password(),
            ],
            [
                'Accept' => 'application/json',
                'apikey' => 'Basic '.$this->basicToken(),
                'Content-Type' => 'application/json',
            ],
            $this->timeout(),
        );

        $token = $response['token']['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ProviderException('AYA login failed: token missing from response.');
        }

        return $token;
    }

    private function loginUrl(): string
    {
        return (string) ($this->config['endpoints']['login'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/login');
    }

    private function pushPaymentUrl(): string
    {
        return (string) ($this->config['endpoints']['push_payment'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestPushPayment');
    }

    private function pushPaymentV2Url(): string
    {
        return (string) ($this->config['endpoints']['push_payment_v2'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/v2/requestPushPayment');
    }

    private function queryPaymentUrl(): string
    {
        return (string) ($this->config['endpoints']['query_payment'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/checkRequestPayment');
    }

    private function qrPaymentUrl(): string
    {
        return (string) ($this->config['endpoints']['qr_payment'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestQRPayment');
    }

    private function refundPaymentUrl(): string
    {
        return (string) ($this->config['endpoints']['refund_payment'] ?? 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/refundPayment');
    }

    private function basicToken(): string
    {
        return (string) ($this->config['basic_token'] ?? '');
    }

    private function phone(): string
    {
        return (string) ($this->config['phone'] ?? '');
    }

    private function password(): string
    {
        return (string) ($this->config['password'] ?? '');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }
}
