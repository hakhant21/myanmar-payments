<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;

beforeEach(function (): void {
    $this->mapper = new KBZPayMapper;
});

describe('KBZPayMapper::toPaymentResponse()', function (): void {
    it('maps PAY_SUCCESS status to SUCCESS', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD001', 'trade_status' => 'PAY_SUCCESS', 'prepay_id' => 'PRE001', 'result' => 'SUCCESS']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::SUCCESS)
            ->and($response->transactionId)->toBe('ORD001')
            ->and($response->provider)->toBe('kbzpay');
    });

    it('maps WAIT_PAY status to PENDING', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD002', 'trade_status' => 'WAIT_PAY']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps PAYING status to PENDING', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD003', 'trade_status' => 'PAYING']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps PAY_FAILED status to FAILED', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD004', 'trade_status' => 'PAY_FAILED']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::FAILED);
    });

    it('maps ORDER_CLOSED status to CANCELLED', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD005', 'trade_status' => 'ORDER_CLOSED']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::CANCELLED);
    });

    it('maps ORDER_EXPIRED status to CANCELLED', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD006', 'trade_status' => 'ORDER_EXPIRED']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::CANCELLED);
    });

    it('maps unknown status to UNKNOWN', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD007', 'trade_status' => 'SOME_NEW_STATUS']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::UNKNOWN);
    });

    it('unwraps top-level Response key', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD008', 'trade_status' => 'PAY_SUCCESS']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->raw)->toBe($payload);
    });

    it('handles flat payload without Response wrapper', function (): void {
        $payload = ['merch_order_id' => 'ORD009', 'trade_status' => 'PAY_SUCCESS'];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->status)->toBe(PaymentStatus::SUCCESS)
            ->and($response->transactionId)->toBe('ORD009');
    });

    it('uses prepay_id as paymentUrl when present', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD010', 'prepay_id' => 'PRE_123', 'trade_status' => 'PAY_SUCCESS']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->paymentUrl)->toBe('PRE_123');
    });

    it('returns null paymentUrl when prepay_id is absent', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'ORD011', 'trade_status' => 'PAY_SUCCESS']];
        $response = $this->mapper->toPaymentResponse($payload);
        expect($response->paymentUrl)->toBeNull();
    });
});

describe('KBZPayMapper::toRefundResponse()', function (): void {
    it('maps REFUND_SUCCESS to REFUNDED', function (): void {
        $payload = ['Response' => ['refund_order_id' => 'REF001', 'refund_status' => 'REFUND_SUCCESS']];
        $response = $this->mapper->toRefundResponse($payload);
        expect($response->status)->toBe(PaymentStatus::REFUNDED)
            ->and($response->refundId)->toBe('REF001')
            ->and($response->provider)->toBe('kbzpay');
    });

    it('maps REFUNDING to PENDING', function (): void {
        $payload = ['Response' => ['refund_order_id' => 'REF002', 'refund_status' => 'REFUNDING']];
        $response = $this->mapper->toRefundResponse($payload);
        expect($response->status)->toBe(PaymentStatus::PENDING);
    });

    it('maps REFUND_FAILED to FAILED', function (): void {
        $payload = ['Response' => ['refund_order_id' => 'REF003', 'refund_status' => 'REFUND_FAILED']];
        $response = $this->mapper->toRefundResponse($payload);
        expect($response->status)->toBe(PaymentStatus::FAILED);
    });

    it('maps REFUND_DUPLICATED to REFUNDED', function (): void {
        $payload = ['Response' => ['refund_order_id' => 'REF004', 'refund_status' => 'REFUND_DUPLICATED']];
        $response = $this->mapper->toRefundResponse($payload);
        expect($response->status)->toBe(PaymentStatus::REFUNDED);
    });

    it('uses refund_request_no as fallback refundId', function (): void {
        $payload = ['Response' => ['refund_request_no' => 'REQ001', 'refund_status' => 'REFUND_SUCCESS']];
        $response = $this->mapper->toRefundResponse($payload);
        expect($response->refundId)->toBe('REQ001');
    });
});

describe('KBZPayMapper::toMmqrResponse()', function (): void {
    it('maps MMQR response with qr_code', function (): void {
        $payload = ['Response' => [
            'merch_order_id' => 'MMQR001',
            'trade_status' => 'WAIT_PAY',
            'qr_code' => '00020101021226500016...',
            'result' => 'SUCCESS',
        ]];
        $response = $this->mapper->toMmqrResponse($payload);
        expect($response->transactionId)->toBe('MMQR001')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('00020101021226500016...')
            ->and($response->qrImage)->toBeNull()
            ->and($response->provider)->toBe('kbzpay');
    });

    it('maps MMQR response with qr_image', function (): void {
        $payload = ['Response' => [
            'merch_order_id' => 'MMQR002',
            'trade_status' => 'WAIT_PAY',
            'qr_code' => 'QR_DATA',
            'qr_image' => 'data:image/png;base64,ABC123',
        ]];
        $response = $this->mapper->toMmqrResponse($payload);
        expect($response->qrImage)->toBe('data:image/png;base64,ABC123');
    });

    it('maps PAY_SUCCESS MMQR status to SUCCESS', function (): void {
        $payload = ['Response' => ['merch_order_id' => 'MMQR003', 'trade_status' => 'PAY_SUCCESS', 'qr_code' => '']];
        $response = $this->mapper->toMmqrResponse($payload);
        expect($response->status)->toBe(PaymentStatus::SUCCESS);
    });
});
