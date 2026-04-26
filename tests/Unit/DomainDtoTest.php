<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\Exceptions\ValidationException;

describe('PaymentRequest', function (): void {
    it('creates a valid payment request', function (): void {
        $request = new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
            metadata: ['title' => 'Test'],
        );

        expect($request->merchantReference)->toBe('ORD001')
            ->and($request->amount)->toBe(1000)
            ->and($request->currency)->toBe('MMK')
            ->and($request->metadata)->toBe(['title' => 'Test']);
    });

    it('throws ValidationException when merchantReference is empty', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: '',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        ))->toThrow(ValidationException::class, 'merchantReference is required');
    });

    it('throws ValidationException when amount is zero', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 0,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        ))->toThrow(ValidationException::class, 'amount must be greater than zero');
    });

    it('throws ValidationException when amount is negative', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: 'ORD001',
            amount: -100,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        ))->toThrow(ValidationException::class, 'amount must be greater than zero');
    });

    it('throws ValidationException when currency is empty', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: '',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        ))->toThrow(ValidationException::class, 'currency is required');
    });

    it('throws ValidationException when callbackUrl is invalid', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'not-a-url',
            redirectUrl: 'https://example.test/return',
        ))->toThrow(ValidationException::class, 'callbackUrl must be a valid URL');
    });

    it('throws ValidationException when redirectUrl is invalid', function (): void {
        expect(fn (): PaymentRequest => new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 1000,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'bad-redirect',
        ))->toThrow(ValidationException::class, 'redirectUrl must be a valid URL');
    });

    it('serializes to array correctly', function (): void {
        $request = new PaymentRequest(
            merchantReference: 'ORD001',
            amount: 500,
            currency: 'MMK',
            callbackUrl: 'https://example.test/callback',
            redirectUrl: 'https://example.test/return',
        );

        expect($request->toArray())->toBe([
            'merchant_reference' => 'ORD001',
            'amount' => 500,
            'currency' => 'MMK',
            'callback_url' => 'https://example.test/callback',
            'redirect_url' => 'https://example.test/return',
            'metadata' => [],
        ]);
    });
});

describe('RefundRequest', function (): void {
    it('creates a valid refund request', function (): void {
        $request = new RefundRequest(transactionId: 'ORD001', amount: 500, reason: 'Customer request');
        expect($request->transactionId)->toBe('ORD001')
            ->and($request->amount)->toBe(500)
            ->and($request->reason)->toBe('Customer request');
    });

    it('stores optional metadata for provider-specific refund fields', function (): void {
        $request = new RefundRequest(
            transactionId: 'ORD001',
            amount: 500,
            metadata: ['reference_number' => 'REF-001'],
        );

        expect($request->metadata)->toBe(['reference_number' => 'REF-001']);
    });

    it('allows empty reason defaulting to empty string', function (): void {
        $request = new RefundRequest(transactionId: 'ORD001', amount: 100);
        expect($request->reason)->toBe('');
    });

    it('throws ValidationException when transactionId is empty', function (): void {
        expect(fn (): RefundRequest => new RefundRequest(transactionId: '', amount: 100))
            ->toThrow(ValidationException::class, 'transactionId is required');
    });

    it('throws ValidationException when amount is zero', function (): void {
        expect(fn (): RefundRequest => new RefundRequest(transactionId: 'ORD001', amount: 0))
            ->toThrow(ValidationException::class, 'amount must be greater than zero');
    });
});

describe('MmqrRequest', function (): void {
    it('creates a valid MMQR request', function (): void {
        $request = new MmqrRequest(
            merchantReference: 'MMQR001',
            amount: 2000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
        );

        expect($request->merchantReference)->toBe('MMQR001')
            ->and($request->amount)->toBe(2000)
            ->and($request->currency)->toBe('MMK')
            ->and($request->notifyUrl)->toBe('https://example.test/notify');
    });

    it('throws ValidationException for empty merchantReference', function (): void {
        expect(fn (): MmqrRequest => new MmqrRequest(
            merchantReference: '',
            amount: 100,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
        ))->toThrow(ValidationException::class, 'merchantReference is required');
    });

    it('throws ValidationException for zero amount', function (): void {
        expect(fn (): MmqrRequest => new MmqrRequest(
            merchantReference: 'M001',
            amount: 0,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
        ))->toThrow(ValidationException::class, 'amount must be greater than zero');
    });

    it('throws ValidationException for empty currency', function (): void {
        expect(fn (): MmqrRequest => new MmqrRequest(
            merchantReference: 'M001',
            amount: 100,
            currency: '',
            notifyUrl: 'https://example.test/notify',
        ))->toThrow(ValidationException::class, 'currency is required');
    });

    it('throws ValidationException for invalid notifyUrl', function (): void {
        expect(fn (): MmqrRequest => new MmqrRequest(
            merchantReference: 'M001',
            amount: 100,
            currency: 'MMK',
            notifyUrl: 'not-a-url',
        ))->toThrow(ValidationException::class, 'notifyUrl must be a valid URL');
    });

    it('serializes to array correctly', function (): void {
        $request = new MmqrRequest(
            merchantReference: 'MMQR002',
            amount: 3000,
            currency: 'MMK',
            notifyUrl: 'https://example.test/notify',
            metadata: ['timeout_express' => '60m'],
        );

        expect($request->toArray())->toBe([
            'merchantReference' => 'MMQR002',
            'amount' => 3000,
            'currency' => 'MMK',
            'notifyUrl' => 'https://example.test/notify',
            'metadata' => ['timeout_express' => '60m'],
        ]);
    });
});

describe('CallbackPayload', function (): void {
    it('constructs with payload, signature and optional timestamp', function (): void {
        $payload = new CallbackPayload(
            payload: ['Request' => ['trade_status' => 'PAY_SUCCESS']],
            signature: 'ABCDEF123',
            timestamp: 1700000000,
        );

        expect($payload->payload)->toBe(['Request' => ['trade_status' => 'PAY_SUCCESS']])
            ->and($payload->signature)->toBe('ABCDEF123')
            ->and($payload->timestamp)->toBe(1700000000);
    });

    it('constructs with null timestamp by default', function (): void {
        $payload = new CallbackPayload(payload: [], signature: '');
        expect($payload->timestamp)->toBeNull();
    });
});
