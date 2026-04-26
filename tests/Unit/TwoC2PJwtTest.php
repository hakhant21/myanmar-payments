<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PJwt;

describe('TwoC2PJwt', function (): void {
    it('encodes and decodes HS256 payloads', function (): void {
        $jwt = new TwoC2PJwt;
        $secret = '0123456789abcdef0123456789abcdef';

        $token = $jwt->encode([
            'merchantID' => 'JT01',
            'invoiceNo' => 'INV-1001',
            'respCode' => '0000',
        ], $secret);

        expect($jwt->decode($token, $secret))->toBe([
            'merchantID' => 'JT01',
            'invoiceNo' => 'INV-1001',
            'respCode' => '0000',
        ]);
    });

    it('wraps JWT encode failures in ProviderException', function (): void {
        $jwt = new TwoC2PJwt;

        expect(fn (): string => $jwt->encode(['merchantID' => 'JT01'], 'short'))
            ->toThrow(ProviderException::class, '2C2P JWT encode failed:');
    });

    it('wraps JWT decode failures in ProviderException', function (): void {
        $jwt = new TwoC2PJwt;

        expect(fn (): array => $jwt->decode('not-a-jwt', '0123456789abcdef0123456789abcdef'))
            ->toThrow(ProviderException::class, '2C2P JWT decode failed:');
    });
});
