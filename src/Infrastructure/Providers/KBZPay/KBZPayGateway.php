<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\KBZPay;

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
            'appid' => (string) ($this->config['app_id'] ?? ''),
            'merch_code' => (string) ($this->config['merchant_code'] ?? $this->config['merchant_id'] ?? ''),
            'merch_order_id' => $request->merchantReference,
            'trade_type' => (string) ($this->config['trade_type'] ?? 'APP'),
            'title' => (string) ($request->metadata['title'] ?? 'Payment'),
            'total_amount' => (string) $request->amount,
            'trans_currency' => $request->currency,
            'timeout_express' => (string) ($request->metadata['timeout_express'] ?? '120m'),
            'callback_info' => (string) ($request->metadata['callback_info'] ?? ''),
        ];

        return $this->mapper->toPaymentResponse($this->client->precreate($bizContent, $this->signature));
    }

    public function queryStatus(string $transactionId): PaymentResponse
    {
        $bizContent = [
            'appid' => (string) ($this->config['app_id'] ?? ''),
            'merch_code' => (string) ($this->config['merchant_code'] ?? $this->config['merchant_id'] ?? ''),
            'merch_order_id' => $transactionId,
        ];

        return $this->mapper->toPaymentResponse($this->client->queryOrder($bizContent, $this->signature));
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $bizContent = [
            'appid' => (string) ($this->config['app_id'] ?? ''),
            'merch_code' => (string) ($this->config['merchant_code'] ?? $this->config['merchant_id'] ?? ''),
            'merch_order_id' => $request->transactionId,
            'refund_request_no' => $request->reason !== '' ? $request->reason : $request->transactionId.'-refund',
            'refund_reason' => $request->reason !== '' ? $request->reason : 'merchant_refund',
            'refund_amount' => (string) $request->amount,
            'sub_type' => (string) ($this->config['sub_type'] ?? ''),
            'sub_identifier_type' => (string) ($this->config['sub_identifier_type'] ?? ''),
            'sub_identifier' => (string) ($this->config['sub_identifier'] ?? ''),
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
            'appid' => (string) ($this->config['app_id'] ?? ''),
            'merch_code' => (string) ($this->config['merchant_code'] ?? $this->config['merchant_id'] ?? ''),
            'merch_order_id' => $request->merchantReference,
            'trade_type' => 'MMQR',
            'total_amount' => (string) $request->amount,
            'trans_currency' => $request->currency,
            'timeout_express' => (string) ($request->metadata['timeout_express'] ?? '120m'),
            'notify_url' => $request->notifyUrl,
        ];

        return $this->mapper->toMmqrResponse($this->client->mmqrPrecreate($bizContent, $this->signature));
    }
}
