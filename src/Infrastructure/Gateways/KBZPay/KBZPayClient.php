<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\KBZPay;

use Hakhant\Payments\Infrastructure\Http\HttpClient;

final readonly class KBZPayClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $config,
    ) {}

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $bizContent
     * @return array<string, mixed>
     */
    public function precreate(array $bizContent, KBZPaySignature $signature, ?string $notifyUrl = null): array
    {
        $request = $this->makeRequest(
            method: 'kbz.payment.precreate',
            version: (string) ($this->config['versions']['precreate'] ?? '1.0'),
            bizContent: $bizContent,
            signature: $signature,
            notifyUrl: $notifyUrl ?? $this->configuredNotifyUrl(),
        );

        return $this->httpClient->post($this->precreateUrl(), ['Request' => $request], $this->headers(), $this->timeout());
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $bizContent
     * @return array<string, mixed>
     */
    public function queryOrder(array $bizContent, KBZPaySignature $signature): array
    {
        $request = $this->makeRequest(
            method: 'kbz.payment.queryorder',
            version: (string) ($this->config['versions']['queryorder'] ?? '3.0'),
            bizContent: $bizContent,
            signature: $signature,
        );

        return $this->httpClient->post($this->queryOrderUrl(), ['Request' => $request], $this->headers(), $this->timeout());
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $bizContent
     * @return array<string, mixed>
     */
    public function refund(array $bizContent, KBZPaySignature $signature): array
    {
        $request = $this->makeRequest(
            method: 'kbz.payment.refund',
            version: (string) ($this->config['versions']['refund'] ?? '1.0'),
            bizContent: $bizContent,
            signature: $signature,
        );

        return $this->httpClient->postWithOptions(
            $this->refundUrl(),
            ['Request' => $request],
            $this->headers(),
            $this->timeout(),
            $this->refundHttpOptions(),
        );
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $bizContent
     * @return array<string, mixed>
     */
    public function mmqrPrecreate(array $bizContent, KBZPaySignature $signature): array
    {
        $notifyUrl = '';
        if (array_key_exists('notify_url', $bizContent)) {
            $rawNotifyUrl = $bizContent['notify_url'];
            $notifyUrl = is_scalar($rawNotifyUrl) ? (string) $rawNotifyUrl : '';
            unset($bizContent['notify_url']);
        }

        $request = $this->makeRequest(
            method: 'kbz.payment.precreate',
            version: (string) ($this->config['versions']['mmqr'] ?? '1.0'),
            bizContent: $bizContent,
            signature: $signature,
            notifyUrl: $notifyUrl,
        );

        return $this->httpClient->post($this->mmqrUrl(), ['Request' => $request], $this->headers(), $this->timeout());
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $bizContent
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    private function makeRequest(
        string $method,
        string $version,
        array $bizContent,
        KBZPaySignature $signature,
        string $notifyUrl = '',
    ): array {
        $request = [
            'timestamp' => (string) time(),
            'method' => $method,
            'nonce_str' => $this->nonce(),
            'sign_type' => 'SHA256',
            'version' => $version,
            'biz_content' => $bizContent,
        ];

        if ($notifyUrl !== '') {
            $request['notify_url'] = $notifyUrl;
        }

        $request['sign'] = $signature->sign($this->signableFields($request), (string) ($this->config['secret'] ?? ''));

        return $request;
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $request
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    private function signableFields(array $request): array
    {
        $fields = [];

        foreach ($request as $key => $value) {
            if ($key === 'biz_content') {
                continue;
            }

            $fields[$key] = $value;
        }

        $bizContent = $request['biz_content'] ?? [];
        if (is_array($bizContent)) {
            foreach ($bizContent as $key => $value) {
                $fields[(string) $key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function precreateUrl(): string
    {
        return (string) ($this->config['endpoints']['precreate'] ?? 'https://api.kbzpay.com/payment/gateway/precreate');
    }

    private function queryOrderUrl(): string
    {
        return (string) ($this->config['endpoints']['queryorder'] ?? 'https://api.kbzpay.com/payment/gateway/queryorder');
    }

    private function refundUrl(): string
    {
        return (string) ($this->config['endpoints']['refund'] ?? 'https://api.kbzpay.com:8008/payment/gateway/refund');
    }

    private function mmqrUrl(): string
    {
        return (string) ($this->config['endpoints']['mmqr'] ?? $this->precreateUrl());
    }

    private function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array<string, mixed>
     */
    private function refundHttpOptions(): array
    {
        $options = [];

        $certificatePath = (string) ($this->config['client_certificate_path'] ?? '');
        if ($certificatePath !== '') {
            $options['withOptions'] = ['cert' => $certificatePath];
        }

        $privateKeyPath = (string) ($this->config['client_certificate_key_path'] ?? '');
        if ($privateKeyPath !== '') {
            $withOptions = $options['withOptions'] ?? [];
            $passphrase = (string) ($this->config['client_certificate_key_passphrase'] ?? '');
            $withOptions['ssl_key'] = $passphrase !== '' ? [$privateKeyPath, $passphrase] : $privateKeyPath;
            $options['withOptions'] = $withOptions;
        }

        return $options;
    }

    private function configuredNotifyUrl(): string
    {
        return (string) ($this->config['notify_url'] ?? '');
    }
}
