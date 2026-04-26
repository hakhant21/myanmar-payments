<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\TwoC2P;

use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class TwoC2PMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function toPaymentResponse(array $payload): PaymentResponse
    {
        return new PaymentResponse(
            provider: '2c2p',
            transactionId: (string) ($payload['paymentToken'] ?? $payload['paymentID'] ?? $payload['invoiceNo'] ?? ''),
            status: $this->mapStatus($payload),
            paymentUrl: isset($payload['webPaymentUrl']) ? (string) $payload['webPaymentUrl'] : null,
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toRefundResponse(array $payload): RefundResponse
    {
        return new RefundResponse(
            provider: '2c2p',
            refundId: (string) ($payload['referenceNo'] ?? $payload['invoiceNo'] ?? ''),
            status: $this->mapRefundStatus($payload),
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapStatus(array $payload): PaymentStatus
    {
        $respCode = (string) ($payload['respCode'] ?? '');
        $result = $payload['paymentResultDetails']['code'] ?? null;
        $resultCode = is_scalar($result) ? (string) $result : '';

        return match (true) {
            $resultCode === '00' => PaymentStatus::SUCCESS,
            $resultCode === '01' => PaymentStatus::FAILED,
            $resultCode === '02' => PaymentStatus::PENDING,
            $respCode === '0000' => PaymentStatus::PENDING,
            in_array($respCode, ['1000', '1001', '1002', '1003'], true) => PaymentStatus::PENDING,
            in_array($respCode, ['2001', '4000', '4001', '5001'], true) => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapRefundStatus(array $payload): PaymentStatus
    {
        $respCode = (string) ($payload['respCode'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));

        return match (true) {
            $respCode === '00' && in_array($status, ['RF', 'A', 'S'], true) => PaymentStatus::REFUNDED,
            in_array($status, ['RP', 'AP'], true) || $respCode === '42' => PaymentStatus::PENDING,
            in_array($status, ['RFF', 'RR', 'RR1', 'RR2', 'RR3', 'PF'], true) => PaymentStatus::FAILED,
            in_array($respCode, ['40', '41', '43', '44', '45', '46', '47', '48', '49', '52', '54'], true) => PaymentStatus::FAILED,
            $respCode === '00' => PaymentStatus::PENDING,
            default => PaymentStatus::UNKNOWN,
        };
    }
}
