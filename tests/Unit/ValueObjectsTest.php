<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ValidationException;
use Hakhant\Payments\Domain\ValueObjects\MerchantReference;
use Hakhant\Payments\Domain\ValueObjects\Money;
use Hakhant\Payments\Domain\ValueObjects\Signature;

describe('Money value object', function (): void {
    it('creates valid money with positive amount and 3-letter currency', function (): void {
        $money = new Money(1000, 'MMK');

        expect($money->amount)->toBe(1000)
            ->and($money->currency)->toBe('MMK');
    });

    it('throws when amount is zero or less', function (): void {
        expect(fn (): Money => new Money(0, 'MMK'))
            ->toThrow(ValidationException::class, 'Amount must be greater than zero.');
    });

    it('throws when currency is not 3 letters', function (): void {
        expect(fn (): Money => new Money(100, 'MM'))
            ->toThrow(ValidationException::class, 'Currency must be ISO-4217 3-letter code.');
    });
});

describe('MerchantReference value object', function (): void {
    it('creates valid merchant reference', function (): void {
        $reference = new MerchantReference('INV-1001');

        expect($reference->value)->toBe('INV-1001');
    });

    it('throws when merchant reference is empty', function (): void {
        expect(fn (): MerchantReference => new MerchantReference(''))
            ->toThrow(ValidationException::class, 'Merchant reference is required.');
    });
});

describe('Signature value object', function (): void {
    it('stores signature value', function (): void {
        $signature = new Signature('ABC123');

        expect($signature->value)->toBe('ABC123');
    });
});
