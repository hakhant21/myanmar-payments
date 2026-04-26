<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\AYA;

use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class AYAMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function toPaymentResponse(array $payload, ?PaymentStatus $fallbackStatus = null): PaymentResponse
    {
        $data = $this->data($payload);
        $status = $this->paymentStatus($payload, $fallbackStatus ?? PaymentStatus::UNKNOWN);

        return new PaymentResponse(
            provider: 'aya',
            transactionId: (string) ($data['externalTransactionId'] ?? $payload['externalTransactionId'] ?? $payload['transRefId'] ?? ''),
            status: $status,
            paymentUrl: null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMmqrResponse(array $payload, ?PaymentStatus $fallbackStatus = null): MmqrResponse
    {
        $data = $this->data($payload);
        $status = $this->paymentStatus($payload, $fallbackStatus ?? PaymentStatus::UNKNOWN);

        return new MmqrResponse(
            provider: 'aya',
            transactionId: (string) ($data['externalTransactionId'] ?? ''),
            status: $status,
            qrCode: (string) ($data['qrdata'] ?? ''),
            qrImage: null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toRefundResponse(array $payload, string $refundId = '', ?PaymentStatus $fallbackStatus = null): RefundResponse
    {
        return new RefundResponse(
            provider: 'aya',
            refundId: $refundId,
            status: $this->paymentStatus($payload, $fallbackStatus ?? PaymentStatus::UNKNOWN),
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function data(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentStatus(array $payload, PaymentStatus $fallbackStatus): PaymentStatus
    {
        $data = $this->data($payload);
        $status = $data['status'] ?? $payload['status'] ?? null;

        if (is_int($status) || is_float($status) || (is_string($status) && is_numeric($status))) {
            return (int) $status === 0 ? PaymentStatus::PENDING : $fallbackStatus;
        }

        if (! is_string($status) || $status === '') {
            return $fallbackStatus;
        }

        return match (strtolower($status)) {
            'success', 'succeeded', 'paid', 'completed' => PaymentStatus::SUCCESS,
            'pending', 'processing', 'requested', 'created' => PaymentStatus::PENDING,
            'failed', 'error', 'rejected', 'declined' => PaymentStatus::FAILED,
            'cancelled', 'canceled', 'expired', 'timeout' => PaymentStatus::CANCELLED,
            'refunded', 'refund_success' => PaymentStatus::REFUNDED,
            default => $fallbackStatus,
        };
    }
}
