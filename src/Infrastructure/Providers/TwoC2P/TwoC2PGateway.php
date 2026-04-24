<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\TwoC2P;

use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Throwable;

final readonly class TwoC2PGateway implements CanRefundPayment, CanVerifyCallback, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private TwoC2PClient $client,
        private TwoC2PMapper $mapper,
        private TwoC2PJwt $jwt,
        private TwoC2PKeyJwt $keyJwt,
        private array $config,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $payload = [
            'merchantID' => $this->merchantId(),
            'invoiceNo' => $request->merchantReference,
            'description' => (string) ($request->metadata['description'] ?? $request->metadata['title'] ?? $this->config['payment_description'] ?? 'Payment'),
            'amount' => (string) $request->amount,
            'currencyCode' => $request->currency,
            'frontendReturnUrl' => $request->redirectUrl,
            'backendReturnUrl' => $request->callbackUrl,
            'locale' => (string) ($request->metadata['locale'] ?? $this->config['locale'] ?? 'en'),
        ];

        foreach ([
            'paymentChannel',
            'agentChannel',
            'request3DS',
            'nonceStr',
            'paymentExpiry',
            'userDefined1',
            'userDefined2',
            'userDefined3',
            'userDefined4',
            'userDefined5',
            'immediatePayment',
            'iframeMode',
            'idempotencyID',
        ] as $field) {
            if (array_key_exists($field, $request->metadata)) {
                $payload[$field] = $request->metadata[$field];
            }
        }

        return $this->mapper->toPaymentResponse($this->client->paymentToken($payload, $this->jwt));
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        $payload = [
            'paymentToken' => $transactionId,
            'locale' => (string) ($this->config['locale'] ?? 'en'),
            'additionalInfo' => false,
        ];

        return $this->mapper->toPaymentResponse($this->client->transactionStatus($payload, $this->jwt));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            'version' => (string) ($this->config['maintenance_version'] ?? '4.3'),
            'timeStamp' => date('ymdHis'),
            'merchantID' => $this->merchantId(),
            'processType' => 'R',
            'invoiceNo' => $request->transactionId,
            'actionAmount' => number_format((float) $request->amount, 2, '.', ''),
        ];

        if ($request->reason !== '') {
            $payload['userDefined1'] = $request->reason;
        }

        foreach ([
            'notifyURL',
            'idempotencyID',
            'bankCode',
            'accountName',
            'accountNumber',
            'userDefined2',
            'userDefined3',
            'userDefined4',
            'userDefined5',
        ] as $field) {
            if (array_key_exists($field, $this->config)) {
                $payload[$field] = $this->config[$field];
            }
        }

        return $this->mapper->toRefundResponse($this->client->refund($payload, $this->keyJwt));
    }

    public function verifyCallback(CallbackPayload $payload): bool
    {
        $jwt = $this->extractPayloadJwt($payload);

        if ($jwt === '') {
            return false;
        }

        try {
            $decoded = $this->jwt->decode($jwt, $this->secretKey());
        } catch (Throwable) {
            return false;
        }

        return (string) ($decoded['merchantID'] ?? '') === $this->merchantId();
    }

    private function extractPayloadJwt(CallbackPayload $payload): string
    {
        $rawPayload = $payload->payload['payload'] ?? $payload->payload['Payload'] ?? null;

        if (is_string($rawPayload) && $rawPayload !== '') {
            return $rawPayload;
        }

        return $payload->signature;
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
