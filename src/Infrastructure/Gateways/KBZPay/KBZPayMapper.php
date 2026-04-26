<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\KBZPay;

use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class KBZPayMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function toPaymentResponse(array $payload): PaymentResponse
    {
        $response = $this->unwrap($payload);
        $status = (string) ($response['trade_status'] ?? $response['result'] ?? 'UNKNOWN');

        return new PaymentResponse(
            provider: 'kbzpay',
            transactionId: (string) ($response['merch_order_id'] ?? $response['prepay_id'] ?? ''),
            status: $this->mapStatus($status),
            paymentUrl: isset($response['prepay_id']) ? (string) $response['prepay_id'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMmqrResponse(array $payload): MmqrResponse
    {
        $response = $this->unwrap($payload);
        $status = (string) ($response['trade_status'] ?? $response['result'] ?? 'UNKNOWN');

        return new MmqrResponse(
            provider: 'kbzpay',
            transactionId: (string) ($response['merch_order_id'] ?? ''),
            status: $this->mapStatus($status),
            qrCode: (string) ($response['qr_code'] ?? ''),
            qrImage: isset($response['qr_image']) ? (string) $response['qr_image'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toRefundResponse(array $payload): RefundResponse
    {
        $response = $this->unwrap($payload);
        $status = (string) ($response['refund_status'] ?? $response['result'] ?? 'UNKNOWN');

        return new RefundResponse(
            provider: 'kbzpay',
            refundId: (string) ($response['refund_order_id'] ?? $response['refund_request_no'] ?? ''),
            status: $this->mapStatus($status),
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        $response = $payload['Response'] ?? $payload;

        return is_array($response) ? $response : [];
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'PAY_SUCCESS', 'COMPLETED' => PaymentStatus::SUCCESS,
            'PENDING', 'WAIT_PAY', 'PAYING', 'REFUNDING' => PaymentStatus::PENDING,
            'FAILED', 'PAY_FAILED', 'FAIL', 'REFUND_FAILED' => PaymentStatus::FAILED,
            'ORDER_CLOSED', 'ORDER_EXPIRED', 'CANCELLED', 'CANCELED' => PaymentStatus::CANCELLED,
            'REFUND_SUCCESS', 'REFUNDED', 'REFUND_DUPLICATED' => PaymentStatus::REFUNDED,
            default => PaymentStatus::UNKNOWN,
        };
    }
}
