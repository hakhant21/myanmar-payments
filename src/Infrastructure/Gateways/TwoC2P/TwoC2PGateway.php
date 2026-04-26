<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\TwoC2P;

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
            'description' => $this->paymentDescription($request),
            'amount' => (string) $request->amount,
            'currencyCode' => $request->currency,
            'frontendReturnUrl' => $request->redirectUrl,
            'backendReturnUrl' => $request->callbackUrl,
            'locale' => $this->locale($request),
        ];

        foreach ($this->metadataAliases() as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $request->metadata)) {
                    $payload[$field] = $request->metadata[$alias];

                    break;
                }
            }
        }

        return $this->mapper->toPaymentResponse($this->client->paymentToken($payload, $this->jwt));
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        $payload = [
            'paymentToken' => $transactionId,
            'locale' => $this->locale(),
            'additionalInfo' => false,
        ];

        return $this->mapper->toPaymentResponse($this->client->transactionStatus($payload, $this->jwt));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            'version' => $this->maintenanceVersion(),
            'timeStamp' => date('ymdHis'),
            'merchantID' => $this->merchantId(),
            'processType' => 'R',
            'invoiceNo' => $request->transactionId,
            'actionAmount' => number_format((float) $request->amount, 2, '.', ''),
        ];

        if ($request->reason !== '') {
            $payload['userDefined1'] = $request->reason;
        }

        foreach ($this->refundConfigAliases() as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $this->config)) {
                    $payload[$field] = $this->config[$alias];

                    break;
                }
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

    private function paymentDescription(PaymentRequest $request): string
    {
        return (string) ($request->metadata['description']
            ?? $request->metadata['title']
            ?? $this->config['payment_description']
            ?? 'Payment');
    }

    private function locale(?PaymentRequest $request = null): string
    {
        return (string) ($request?->metadata['locale'] ?? $this->config['locale'] ?? 'en');
    }

    private function maintenanceVersion(): string
    {
        return (string) ($this->config['maintenance_version'] ?? '4.3');
    }

    /**
     * @return array<string, list<string>>
     */
    private function metadataAliases(): array
    {
        return [
            'paymentChannel' => ['paymentChannel', 'payment_channel'],
            'agentChannel' => ['agentChannel', 'agent_channel'],
            'request3DS' => ['request3DS', 'request_3ds'],
            'nonceStr' => ['nonceStr', 'nonce_str'],
            'paymentExpiry' => ['paymentExpiry', 'payment_expiry'],
            'userDefined1' => ['userDefined1', 'user_defined_1'],
            'userDefined2' => ['userDefined2', 'user_defined_2'],
            'userDefined3' => ['userDefined3', 'user_defined_3'],
            'userDefined4' => ['userDefined4', 'user_defined_4'],
            'userDefined5' => ['userDefined5', 'user_defined_5'],
            'immediatePayment' => ['immediatePayment', 'immediate_payment'],
            'iframeMode' => ['iframeMode', 'iframe_mode'],
            'idempotencyID' => ['idempotencyID', 'idempotency_id'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function refundConfigAliases(): array
    {
        return [
            'notifyURL' => ['notifyURL', 'notify_url'],
            'idempotencyID' => ['idempotencyID', 'idempotency_id'],
            'bankCode' => ['bankCode', 'bank_code'],
            'accountName' => ['accountName', 'account_name'],
            'accountNumber' => ['accountNumber', 'account_number'],
            'userDefined2' => ['userDefined2', 'user_defined_2'],
            'userDefined3' => ['userDefined3', 'user_defined_3'],
            'userDefined4' => ['userDefined4', 'user_defined_4'],
            'userDefined5' => ['userDefined5', 'user_defined_5'],
        ];
    }
}
