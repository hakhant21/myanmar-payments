<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\KBZPay;

use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;

final readonly class KBZPayGateway implements CanInitiateMmqr, CanRefundPayment, CanVerifyCallback, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private KBZPayClient $client,
        private KBZPayMapper $mapper,
        private KBZPaySignature $signature,
        private array $config,
    ) {}

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $bizContent = [
            'appid' => $this->appId(),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $request->merchantReference,
            'trade_type' => $this->tradeType(),
            'title' => $this->paymentTitle($request),
            'total_amount' => (string) $request->amount,
            'trans_currency' => $request->currency,
            'timeout_express' => $this->timeoutExpress($request->metadata),
            'callback_info' => $this->callbackInfo($request),
        ];

        return $this->mapper->toPaymentResponse($this->client->precreate($bizContent, $this->signature));
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        $bizContent = [
            'appid' => $this->appId(),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $transactionId,
        ];

        return $this->mapper->toPaymentResponse($this->client->queryOrder($bizContent, $this->signature));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $bizContent = [
            'appid' => $this->appId(),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $request->transactionId,
            'refund_request_no' => $this->refundRequestNo($request),
            'refund_reason' => $request->reason !== '' ? $request->reason : 'merchant_refund',
            'refund_amount' => (string) $request->amount,
            'sub_type' => $this->stringConfig('sub_type'),
            'sub_identifier_type' => $this->stringConfig('sub_identifier_type'),
            'sub_identifier' => $this->stringConfig('sub_identifier'),
        ];

        return $this->mapper->toRefundResponse($this->client->refund($bizContent, $this->signature));
    }

    public function verifyCallback(CallbackPayload $payload): bool
    {
        $request = $payload->payload['Request'] ?? $payload->payload;
        if (! is_array($request)) {
            return false;
        }

        /** @var array<string, scalar|array<array-key, mixed>|null> $request */
        $rawSign = $request['sign'] ?? null;
        $providedSign = is_scalar($rawSign) ? (string) $rawSign : $payload->signature;

        return $this->signature->verify($request, $providedSign, (string) ($this->config['secret'] ?? ''));
    }

    public function createMmqr(MmqrRequest $request): MmqrResponse
    {
        $bizContent = [
            'appid' => $this->appId(),
            'merch_code' => $this->merchantCode(),
            'merch_order_id' => $request->merchantReference,
            'trade_type' => 'PAY_BY_QRCODE',
            'total_amount' => (string) $request->amount,
            'trans_currency' => $request->currency,
            'timeout_express' => $this->timeoutExpress($request->metadata),
            'notify_url' => $request->notifyUrl,
        ];

        return $this->mapper->toMmqrResponse($this->client->mmqrPrecreate($bizContent, $this->signature));
    }

    private function appId(): string
    {
        return $this->stringConfig('app_id');
    }

    private function merchantCode(): string
    {
        return (string) ($this->config['merchant_code'] ?? $this->config['merchant_id'] ?? '');
    }

    private function tradeType(): string
    {
        return $this->stringConfig('trade_type', 'APP');
    }

    private function paymentTitle(PaymentRequest $request): string
    {
        return (string) ($request->metadata['title'] ?? 'Payment');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function timeoutExpress(array $metadata): string
    {
        return (string) ($metadata['timeout_express'] ?? '120m');
    }

    private function callbackInfo(PaymentRequest $request): string
    {
        return (string) ($request->metadata['callback_info'] ?? '');
    }

    private function refundRequestNo(RefundRequest $request): string
    {
        $configured = $request->metadata['refund_request_no'] ?? $request->metadata['refundRequestNo'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $request->transactionId.'-refund';
    }

    private function stringConfig(string $key, string $default = ''): string
    {
        return (string) ($this->config[$key] ?? $default);
    }
}
