<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\WaveMoney;

use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class WaveMoneyGateway implements CanInitiateMmqr, CanVerifyCallback, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private WaveMoneyClient $client,
        private WaveMoneyMapper $mapper,
        private WaveMoneyHash $hash,
        private array $config,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $response = $this->createTransaction(
            merchantReference: $request->merchantReference,
            amount: $request->amount,
            metadata: $request->metadata,
            frontendResultUrl: $request->redirectUrl,
            backendResultUrl: $request->callbackUrl,
            defaultItemName: 'Order',
            itemsErrorMessage: 'WaveMoney items metadata is invalid.',
        );

        return $this->mapper->toCreatePaymentResponse($response, $this->authenticateUrl($response));
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        throw new ProviderException(
            'WaveMoney queryStatus is not supported by this provider. Use callback verification and callback status mapping.',
        );
    }

    public function createMmqr(MmqrRequest $request): MmqrResponse
    {
        $response = $this->createTransaction(
            merchantReference: $request->merchantReference,
            amount: $request->amount,
            metadata: $request->metadata,
            frontendResultUrl: (string) ($request->metadata['frontend_result_url'] ?? $request->notifyUrl),
            backendResultUrl: $request->notifyUrl,
            defaultItemName: 'MMQR Payment',
            itemsErrorMessage: 'WaveMoney MMQR items metadata is invalid.',
        );

        return $this->mapper->toMmqrResponse(
            $response,
            $this->authenticateUrl($response),
        );
    }

    public function verifyCallback(CallbackPayload $payload): bool
    {
        $body = $payload->payload;
        $providedHash = (string) ($body['hashValue'] ?? $payload->signature);

        if ($providedHash === '') {
            return false;
        }

        if ((string) ($body['merchantId'] ?? '') !== $this->merchantId()) {
            return false;
        }

        return $this->hash->verify([
            $body['status'] ?? null,
            $body['timeToLiveSeconds'] ?? null,
            $body['merchantId'] ?? null,
            $body['orderId'] ?? null,
            $body['amount'] ?? null,
            $body['backendResultUrl'] ?? null,
            $body['merchantReferenceId'] ?? null,
            $body['initiatorMsisdn'] ?? null,
            $body['transactionId'] ?? null,
            $body['paymentRequestId'] ?? null,
            $body['requestTime'] ?? null,
        ], $providedHash, $this->secretKey());
    }

    private function merchantId(): string
    {
        return (string) ($this->config['merchant_id'] ?? '');
    }

    private function secretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function createTransaction(
        string $merchantReference,
        int $amount,
        array $metadata,
        string $frontendResultUrl,
        string $backendResultUrl,
        string $defaultItemName,
        string $itemsErrorMessage,
    ): array {
        $payload = [
            'time_to_live_in_seconds' => $this->timeToLive($metadata),
            'merchant_id' => $this->merchantId(),
            'order_id' => $this->orderId($merchantReference, $metadata),
            'merchant_reference_id' => $merchantReference,
            'frontend_result_url' => $frontendResultUrl,
            'backend_result_url' => $backendResultUrl,
            'amount' => (string) $amount,
            'payment_description' => $this->paymentDescription($metadata),
            'merchant_name' => $this->merchantName($metadata),
            'items' => $this->itemsJson($metadata, $amount, $defaultItemName, $itemsErrorMessage),
        ];

        $payload['hash'] = $this->hash->sign([
            $payload['time_to_live_in_seconds'],
            $payload['merchant_id'],
            $payload['order_id'],
            $payload['amount'],
            $payload['backend_result_url'],
            $payload['merchant_reference_id'],
        ], $this->secretKey());

        return $this->client->createPayment($payload);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function timeToLive(array $metadata): string
    {
        return (string) ($metadata['time_to_live_in_seconds'] ?? $this->config['time_to_live_in_seconds'] ?? 600);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function orderId(string $merchantReference, array $metadata): string
    {
        return (string) ($metadata['order_id'] ?? $merchantReference);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function paymentDescription(array $metadata): string
    {
        return (string) ($metadata['payment_description'] ?? $metadata['description'] ?? $this->config['payment_description'] ?? 'Payment');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function merchantName(array $metadata): string
    {
        return (string) ($metadata['merchant_name'] ?? $this->config['merchant_name'] ?? 'Merchant');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function itemsJson(array $metadata, int $amount, string $defaultItemName, string $errorMessage): string
    {
        $items = $metadata['items'] ?? [['name' => (string) ($metadata['item_name'] ?? $defaultItemName), 'amount' => $amount]];
        $encoded = json_encode($items, JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            throw new ProviderException($errorMessage);
        }

        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function authenticateUrl(array $response): string
    {
        $transactionId = (string) ($response['transaction_id'] ?? '');

        return $transactionId !== '' ? $this->client->authenticateUrl($transactionId) : '';
    }
}
