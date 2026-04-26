<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAMapper;

describe('AYAMapper', function (): void {
    it('maps pending-like statuses', function (): void {
        $mapper = new AYAMapper;

        expect($mapper->toPaymentResponse(['status' => 'requested'])->status)->toBe(PaymentStatus::PENDING)
            ->and($mapper->toMmqrResponse(['data' => ['status' => 'processing']])->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps failed status', function (): void {
        $mapper = new AYAMapper;

        expect($mapper->toPaymentResponse(['status' => 'failed'])->status)->toBe(PaymentStatus::FAILED);
    });

    it('maps cancelled status', function (): void {
        $mapper = new AYAMapper;

        expect($mapper->toPaymentResponse(['status' => 'timeout'])->status)->toBe(PaymentStatus::CANCELLED);
    });

    it('maps refunded status', function (): void {
        $mapper = new AYAMapper;

        expect($mapper->toRefundResponse(['status' => 'refund_success'])->status)->toBe(PaymentStatus::REFUNDED);
    });
});
