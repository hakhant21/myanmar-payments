<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PMapper;

beforeEach(function (): void {
    $this->mapper = new TwoC2PMapper;
});

describe('TwoC2PMapper::toPaymentResponse()', function (): void {
    it('maps payment token response to pending redirect payment', function (): void {
        $response = $this->mapper->toPaymentResponse([
            'webPaymentUrl' => 'https://sandbox-ui.test/token/abc',
            'paymentToken' => 'TOKEN_123',
            'respCode' => '0000',
            'respDesc' => 'Success',
        ]);

        expect($response->provider)->toBe('2c2p')
            ->and($response->transactionId)->toBe('TOKEN_123')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->paymentUrl)->toBe('https://sandbox-ui.test/token/abc');
    });

    it('maps transaction success from payment result details', function (): void {
        $response = $this->mapper->toPaymentResponse([
            'invoiceNo' => 'INV-1001',
            'paymentToken' => 'TOKEN_123',
            'paymentResultDetails' => ['code' => '00', 'description' => 'Approved'],
            'respCode' => '2000',
            'respDesc' => 'Completed',
        ]);

        expect($response->status)->toBe(PaymentStatus::SUCCESS);
    });

    it('maps transaction failure from payment result details', function (): void {
        $response = $this->mapper->toPaymentResponse([
            'invoiceNo' => 'INV-1001',
            'paymentResultDetails' => ['code' => '01', 'description' => 'Declined'],
            'respCode' => '2000',
            'respDesc' => 'Completed',
        ]);

        expect($response->status)->toBe(PaymentStatus::FAILED)
            ->and($response->transactionId)->toBe('INV-1001');
    });

    it('maps processing responses to pending', function (): void {
        $response = $this->mapper->toPaymentResponse([
            'paymentToken' => 'TOKEN_123',
            'respCode' => '1001',
            'respDesc' => 'Processing',
        ]);

        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps unknown responses to unknown', function (): void {
        $response = $this->mapper->toPaymentResponse([
            'paymentToken' => 'TOKEN_123',
            'respCode' => '9999',
            'respDesc' => 'Unknown',
        ]);

        expect($response->status)->toBe(PaymentStatus::UNKNOWN);
    });
});

describe('TwoC2PMapper::toRefundResponse()', function (): void {
    it('maps confirmed refunds to refunded', function (): void {
        $response = $this->mapper->toRefundResponse([
            'referenceNo' => 'REF-1001',
            'respCode' => '00',
            'status' => 'RF',
        ]);

        expect($response->refundId)->toBe('REF-1001')
            ->and($response->status)->toBe(PaymentStatus::REFUNDED);
    });

    it('maps pending refunds to pending', function (): void {
        $response = $this->mapper->toRefundResponse([
            'referenceNo' => 'REF-1002',
            'respCode' => '42',
            'status' => 'RP',
        ]);

        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps failed refunds to failed', function (): void {
        $response = $this->mapper->toRefundResponse([
            'referenceNo' => 'REF-1003',
            'respCode' => '45',
            'status' => 'RFF',
        ]);

        expect($response->status)->toBe(PaymentStatus::FAILED);
    });

    it('maps successful maintenance responses without refund status to pending', function (): void {
        $response = $this->mapper->toRefundResponse([
            'referenceNo' => 'REF-1004',
            'respCode' => '00',
            'status' => 'UNKNOWN',
        ]);

        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps unknown refund responses to unknown', function (): void {
        $response = $this->mapper->toRefundResponse([
            'referenceNo' => 'REF-1005',
            'respCode' => '98',
            'status' => 'ZZ',
        ]);

        expect($response->status)->toBe(PaymentStatus::UNKNOWN);
    });
});
