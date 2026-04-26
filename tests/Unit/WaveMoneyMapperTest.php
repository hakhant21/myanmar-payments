<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyMapper;

beforeEach(function (): void {
    $this->mapper = new WaveMoneyMapper;
});

describe('WaveMoneyMapper::toCreatePaymentResponse()', function (): void {
    it('maps success and duplicate create responses to pending with payment url', function (): void {
        $success = $this->mapper->toCreatePaymentResponse([
            'message' => 'success',
            'transaction_id' => 'tx-1',
        ], 'https://wave.test/auth?transaction_id=tx-1');

        $duplicate = $this->mapper->toCreatePaymentResponse([
            'message' => 'record already exists',
            'transaction_id' => 'tx-2',
        ], 'https://wave.test/auth?transaction_id=tx-2');

        expect($success->status)->toBe(PaymentStatus::PENDING)
            ->and($success->paymentUrl)->toBe('https://wave.test/auth?transaction_id=tx-1')
            ->and($duplicate->status)->toBe(PaymentStatus::PENDING)
            ->and($duplicate->paymentUrl)->toBe('https://wave.test/auth?transaction_id=tx-2');
    });

    it('maps known failed create responses to failed', function (): void {
        $invalidHash = $this->mapper->toCreatePaymentResponse([
            'message' => 'invalid_hash',
            'transaction_id' => 'tx-1',
        ], 'https://wave.test/auth?transaction_id=tx-1');

        $missing = $this->mapper->toCreatePaymentResponse([
            'message' => 'no record found',
            'transaction_id' => 'tx-2',
        ], 'https://wave.test/auth?transaction_id=tx-2');

        expect($invalidHash->status)->toBe(PaymentStatus::FAILED)
            ->and($missing->status)->toBe(PaymentStatus::FAILED);
    });

    it('maps unknown create responses to unknown and omits payment url without transaction id', function (): void {
        $response = $this->mapper->toCreatePaymentResponse([
            'message' => 'unexpected_status',
        ], 'https://wave.test/auth?transaction_id=missing');

        expect($response->status)->toBe(PaymentStatus::UNKNOWN)
            ->and($response->transactionId)->toBe('')
            ->and($response->paymentUrl)->toBeNull();
    });
});

describe('WaveMoneyMapper::mapCallbackStatus()', function (): void {
    it('maps callback statuses to domain statuses', function (): void {
        expect($this->mapper->mapCallbackStatus('PAYMENT_CONFIRMED'))->toBe(PaymentStatus::SUCCESS)
            ->and($this->mapper->mapCallbackStatus('INSUFFICIENT_BALANCE'))->toBe(PaymentStatus::PENDING)
            ->and($this->mapper->mapCallbackStatus('TRANSACTION_TIMED_OUT'))->toBe(PaymentStatus::FAILED)
            ->and($this->mapper->mapCallbackStatus('ACCOUNT_LOCKED'))->toBe(PaymentStatus::FAILED)
            ->and($this->mapper->mapCallbackStatus('BILL_COLLECTION_FAILED'))->toBe(PaymentStatus::FAILED)
            ->and($this->mapper->mapCallbackStatus('PAYMENT_REQUEST_CANCELLED'))->toBe(PaymentStatus::FAILED)
            ->and($this->mapper->mapCallbackStatus('SCHEDULER_TRANSACTION_TIMED_OUT'))->toBe(PaymentStatus::FAILED)
            ->and($this->mapper->mapCallbackStatus('anything-else'))->toBe(PaymentStatus::UNKNOWN);
    });
});

describe('WaveMoneyMapper::toMmqrResponse()', function (): void {
    it('maps successful MMQR create responses with qr image', function (): void {
        $response = $this->mapper->toMmqrResponse([
            'message' => 'success',
            'transaction_id' => 'wm-qr-1',
            'qr_image' => 'https://cdn.wave.test/qr/wm-qr-1.png',
        ], 'https://wave.test/auth?transaction_id=wm-qr-1');

        expect($response->provider)->toBe('wavemoney')
            ->and($response->transactionId)->toBe('wm-qr-1')
            ->and($response->status)->toBe(PaymentStatus::PENDING)
            ->and($response->qrCode)->toBe('https://wave.test/auth?transaction_id=wm-qr-1')
            ->and($response->qrImage)->toBe('https://cdn.wave.test/qr/wm-qr-1.png');
    });

    it('maps missing transaction id to unknown with empty qr code', function (): void {
        $response = $this->mapper->toMmqrResponse([
            'message' => 'invalid_hash',
        ], 'https://wave.test/auth?transaction_id=missing');

        expect($response->status)->toBe(PaymentStatus::FAILED)
            ->and($response->transactionId)->toBe('')
            ->and($response->qrCode)->toBe('')
            ->and($response->qrImage)->toBeNull();
    });
});
