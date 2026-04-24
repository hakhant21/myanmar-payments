<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\TwoC2P;

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Http\HttpClient;

final readonly class TwoC2PClient
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
    public function paymentToken(array $payload, TwoC2PJwt $jwt): array
    {
        return $this->send($this->paymentTokenUrl(), $payload, $jwt);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function transactionStatus(array $payload, TwoC2PJwt $jwt): array
    {
        return $this->send($this->transactionStatusUrl(), $payload, $jwt);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload, TwoC2PKeyJwt $jwt): array
    {
        $xml = $this->buildRefundRequestXml($payload);
        $response = trim($this->httpClient->postRaw(
            $this->refundUrl(),
            $jwt->encode($xml, $this->merchantPrivateKey(), $this->twoC2pPublicKey(), $this->keyId()),
            $this->refundHeaders(),
            $this->timeout(),
        ));

        if ($response === '') {
            throw new ProviderException('2C2P refund response payload is missing.');
        }

        return $this->parsePaymentProcessResponseXml($jwt->decode($response, $this->twoC2pPublicKey(), $this->merchantPrivateKey()));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $url, array $payload, TwoC2PJwt $jwt): array
    {
        $response = $this->httpClient->post(
            $url,
            ['payload' => $jwt->encode($payload, $this->secretKey())],
            $this->headers(),
            $this->timeout(),
        );

        return $this->decodePayload($response, $jwt);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function decodePayload(array $response, TwoC2PJwt $jwt): array
    {
        $payload = $response['payload'] ?? null;

        if (! is_string($payload) || $payload === '') {
            throw new ProviderException('2C2P response payload is missing.');
        }

        return $jwt->decode($payload, $this->secretKey());
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

    /**
     * @return array<string, string>
     */
    private function refundHeaders(): array
    {
        return [
            'Accept' => 'text/plain',
            'Content-Type' => 'text/plain',
        ];
    }

    private function paymentTokenUrl(): string
    {
        return (string) ($this->config['endpoints']['payment_token'] ?? 'https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken');
    }

    private function transactionStatusUrl(): string
    {
        return (string) ($this->config['endpoints']['transaction_status'] ?? 'https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus');
    }

    private function refundUrl(): string
    {
        return (string) ($this->config['endpoints']['refund'] ?? 'https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action');
    }

    private function secretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }

    private function merchantPrivateKey(): string
    {
        return (string) ($this->config['merchant_private_key'] ?? '');
    }

    private function twoC2pPublicKey(): string
    {
        return (string) ($this->config['two_c2p_public_key'] ?? '');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    private function keyId(): ?string
    {
        $keyId = $this->config['key_id'] ?? null;

        return is_string($keyId) && $keyId !== '' ? $keyId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildRefundRequestXml(array $payload): string
    {
        $xml = new \SimpleXMLElement('<PaymentProcessRequest/>');

        foreach ($payload as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }

            $xml->addChild($key, htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }

        $serialized = $xml->asXML();

        return (string) $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePaymentProcessResponseXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $document instanceof \SimpleXMLElement) {
            throw new ProviderException('2C2P refund response XML is invalid.');
        }

        $result = [];

        foreach ($document->children() as $child) {
            $result[$child->getName()] = trim((string) $child);
        }

        return $result;
    }
}
