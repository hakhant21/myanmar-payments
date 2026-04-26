<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\WaveMoney;

use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class WaveMoneyMapper
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function toCreatePaymentResponse(array $response, string $paymentUrl): PaymentResponse
    {
        $transactionId = (string) ($response['transaction_id'] ?? '');
        $message = strtolower((string) ($response['message'] ?? ''));

        return new PaymentResponse(
            provider: 'wavemoney',
            transactionId: $transactionId,
            status: $this->mapCreateStatus($message, $transactionId),
            paymentUrl: $transactionId !== '' ? $paymentUrl : null,
            raw: $response,
        );
    }

    public function mapCallbackStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'PAYMENT_CONFIRMED' => PaymentStatus::SUCCESS,
            'INSUFFICIENT_BALANCE' => PaymentStatus::PENDING,
            'TRANSACTION_TIMED_OUT',
            'ACCOUNT_LOCKED',
            'BILL_COLLECTION_FAILED',
            'PAYMENT_REQUEST_CANCELLED',
            'SCHEDULER_TRANSACTION_TIMED_OUT' => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function toMmqrResponse(array $response, string $qrCode): MmqrResponse
    {
        $transactionId = (string) ($response['transaction_id'] ?? '');
        $message = strtolower((string) ($response['message'] ?? ''));
        $rawQrImage = $response['qr_image'] ?? null;

        return new MmqrResponse(
            provider: 'wavemoney',
            transactionId: $transactionId,
            status: $this->mapCreateStatus($message, $transactionId),
            qrCode: $transactionId !== '' ? $qrCode : '',
            qrImage: is_string($rawQrImage) && $rawQrImage !== '' ? $rawQrImage : null,
            raw: $response,
        );
    }

    private function mapCreateStatus(string $message, string $transactionId): PaymentStatus
    {
        return match (true) {
            $message === 'success' && $transactionId !== '' => PaymentStatus::PENDING,
            $message === 'record already exists' && $transactionId !== '' => PaymentStatus::PENDING,
            $message === 'invalid_hash',
            $message === 'no record found' => PaymentStatus::FAILED,
            default => PaymentStatus::UNKNOWN,
        };
    }
}
