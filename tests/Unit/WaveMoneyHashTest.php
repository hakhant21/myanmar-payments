<?php

declare(strict_types=1);

use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyHash;

describe('WaveMoneyHash', function (): void {
    it('generates hmac sha256 hashes for payload parts', function (): void {
        $hash = new WaveMoneyHash;

        $result = $hash->sign(['600', 'MERCHANT', 'ORDER-1', '1000', 'https://example.com/cb', 'REF-1'], 'secret');

        expect($result)->toBe(hash_hmac('sha256', '600MERCHANTORDER-11000https://example.com/cbREF-1', 'secret'));
    });

    it('treats null values as literal null strings in hash source', function (): void {
        $hash = new WaveMoneyHash;

        $result = $hash->sign(['9791000000', null, 'merchant'], 'secret');

        expect($result)->toBe(hash_hmac('sha256', '9791000000nullmerchant', 'secret'));
    });

    it('verifies hashes case-insensitively for provided signature', function (): void {
        $hash = new WaveMoneyHash;
        $expected = $hash->sign(['A', 'B', 'C'], 'secret');

        expect($hash->verify(['A', 'B', 'C'], strtoupper($expected), 'secret'))->toBeTrue()
            ->and($hash->verify(['A', 'B', 'D'], $expected, 'secret'))->toBeFalse();
    });
});
