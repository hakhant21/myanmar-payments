<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\WaveMoney;

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
        $items = $request->metadata['items'] ?? [['name' => (string) ($request->metadata['item_name'] ?? 'Order'), 'amount' => $request->amount]];

        $payload = [
            'time_to_live_in_seconds' => (string) ($request->metadata['time_to_live_in_seconds'] ?? $this->config['time_to_live_in_seconds'] ?? 600),
            'merchant_id' => $this->merchantId(),
            'order_id' => (string) ($request->metadata['order_id'] ?? $request->merchantReference),
            'merchant_reference_id' => $request->merchantReference,
            'frontend_result_url' => $request->redirectUrl,
            'backend_result_url' => $request->callbackUrl,
            'amount' => (string) $request->amount,
            'payment_description' => (string) ($request->metadata['payment_description'] ?? $request->metadata['description'] ?? $this->config['payment_description'] ?? 'Payment'),
            'merchant_name' => (string) ($request->metadata['merchant_name'] ?? $this->config['merchant_name'] ?? 'Merchant'),
            'items' => json_encode($items, JSON_UNESCAPED_SLASHES),
        ];

        if (! is_string($payload['items'])) {
            throw new ProviderException('WaveMoney items metadata is invalid.');
        }

        $payload['hash'] = $this->hash->sign([
            $payload['time_to_live_in_seconds'],
            $payload['merchant_id'],
            $payload['order_id'],
            $payload['amount'],
            $payload['backend_result_url'],
            $payload['merchant_reference_id'],
        ], $this->secretKey());

        $response = $this->client->createPayment($payload);
        $transactionId = (string) ($response['transaction_id'] ?? '');

        return $this->mapper->toCreatePaymentResponse(
            $response,
            $transactionId !== '' ? $this->client->authenticateUrl($transactionId) : '',
        );
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        throw new ProviderException(
            'WaveMoney queryStatus is not supported by this provider. Use callback verification and callback status mapping.',
        );
    }

    public function createMmqr(MmqrRequest $request): MmqrResponse
    {
        $items = $request->metadata['items'] ?? [['name' => (string) ($request->metadata['item_name'] ?? 'MMQR Payment'), 'amount' => $request->amount]];

        $payload = [
            'time_to_live_in_seconds' => (string) ($request->metadata['time_to_live_in_seconds'] ?? $this->config['time_to_live_in_seconds'] ?? 600),
            'merchant_id' => $this->merchantId(),
            'order_id' => (string) ($request->metadata['order_id'] ?? $request->merchantReference),
            'merchant_reference_id' => $request->merchantReference,
            'frontend_result_url' => (string) ($request->metadata['frontend_result_url'] ?? $request->notifyUrl),
            'backend_result_url' => $request->notifyUrl,
            'amount' => (string) $request->amount,
            'payment_description' => (string) ($request->metadata['payment_description'] ?? $request->metadata['description'] ?? $this->config['payment_description'] ?? 'Payment'),
            'merchant_name' => (string) ($request->metadata['merchant_name'] ?? $this->config['merchant_name'] ?? 'Merchant'),
            'items' => json_encode($items, JSON_UNESCAPED_SLASHES),
        ];

        if (! is_string($payload['items'])) {
            throw new ProviderException('WaveMoney MMQR items metadata is invalid.');
        }

        $payload['hash'] = $this->hash->sign([
            $payload['time_to_live_in_seconds'],
            $payload['merchant_id'],
            $payload['order_id'],
            $payload['amount'],
            $payload['backend_result_url'],
            $payload['merchant_reference_id'],
        ], $this->secretKey());

        $response = $this->client->createPayment($payload);
        $transactionId = (string) ($response['transaction_id'] ?? '');

        return $this->mapper->toMmqrResponse(
            $response,
            $transactionId !== '' ? $this->client->authenticateUrl($transactionId) : '',
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
}
