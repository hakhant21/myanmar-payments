<?php

declare(strict_types=1);

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PKeyJwt;

function twoC2pB64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function twoC2pB64UrlDecode(string $value): string
{
    return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}

/**
 * @return array{private_key: string, public_key: string}
 */
function twoC2pDsaKeyFixture(): array
{
    static $fixture;

    if ($fixture !== null) {
        return $fixture;
    }

    $dsa = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_DSA]);

    if ($dsa === false) {
        throw new RuntimeException('Unable to generate DSA keys for 2C2P tests.');
    }

    openssl_pkey_export($dsa, $privateKey);
    $publicKey = openssl_pkey_get_details($dsa)['key'] ?? null;

    if (! is_string($publicKey)) {
        throw new RuntimeException('Unable to export DSA public key for 2C2P tests.');
    }

    return $fixture = [
        'private_key' => $privateKey,
        'public_key' => $publicKey,
    ];
}

/**
 * @param  array<int, mixed>  $arguments
 */
function twoC2pInvokePrivate(object $instance, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($instance, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($instance, $arguments);
}

function twoC2pJoseKeyFixture(): array
{
    static $fixture;

    if ($fixture !== null) {
        return $fixture;
    }

    $merchant = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $twoC2p = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    if ($merchant === false || $twoC2p === false) {
        throw new RuntimeException('Unable to generate RSA keys for 2C2P tests.');
    }

    openssl_pkey_export($merchant, $merchantPrivateKey);
    openssl_pkey_export($twoC2p, $twoC2pPrivateKey);

    $merchantPublicKey = openssl_pkey_get_details($merchant)['key'] ?? null;
    $twoC2pPublicKey = openssl_pkey_get_details($twoC2p)['key'] ?? null;

    if (! is_string($merchantPublicKey) || ! is_string($twoC2pPublicKey)) {
        throw new RuntimeException('Unable to export RSA public keys for 2C2P tests.');
    }

    return $fixture = [
        'merchant_private_key' => $merchantPrivateKey,
        'merchant_public_key' => $merchantPublicKey,
        'two_c2p_private_key' => $twoC2pPrivateKey,
        'two_c2p_public_key' => $twoC2pPublicKey,
    ];
}

describe('TwoC2PKeyJwt', function (): void {
    it('encodes requests and decodes responses using key-based JOSE', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();
        $xml = '<PaymentProcessRequest><version>4.3</version><merchantID>JT01</merchantID><invoiceNo>INV-1001</invoiceNo><actionAmount>1000.00</actionAmount><processType>R</processType></PaymentProcessRequest>';

        $requestToken = $jwt->encode($xml, $keys['merchant_private_key'], $keys['two_c2p_public_key'], '3');
        $responseToken = $jwt->encode($xml, $keys['two_c2p_private_key'], $keys['merchant_public_key']);

        expect($jwt->decode($requestToken, $keys['merchant_public_key'], $keys['two_c2p_private_key']))->toBe($xml)
            ->and($jwt->decode($responseToken, $keys['two_c2p_public_key'], $keys['merchant_private_key']))->toBe($xml);

        [$encodedHeader] = explode('.', $requestToken);
        $header = json_decode(base64_decode(strtr($encodedHeader, '-_', '+/').str_repeat('=', (4 - strlen($encodedHeader) % 4) % 4), true), true);

        expect($header)->toMatchArray(['alg' => 'PS256', 'kid' => '3']);
    });

    it('wraps invalid private key errors during encode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        expect(fn (): string => $jwt->encode('<x/>', 'invalid-private-key', $keys['two_c2p_public_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT encode failed:');
    });

    it('wraps invalid token errors during decode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        expect(fn (): string => $jwt->decode('not-a-token', $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed:');
    });

    it('wraps invalid public key errors during encode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        expect(fn (): string => $jwt->encode('<x/>', $keys['merchant_private_key'], 'invalid-public-key'))
            ->toThrow(ProviderException::class, '2C2P key JWT encode failed:');
    });

    it('wraps content key decryption failures during decode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();
        $otherMerchant = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($otherMerchant === false) {
            throw new RuntimeException('Unable to generate alternate merchant key.');
        }

        openssl_pkey_export($otherMerchant, $otherMerchantPrivateKey);

        $token = $jwt->encode('<x/>', $keys['two_c2p_private_key'], $keys['merchant_public_key']);

        expect(fn (): string => $jwt->decode($token, $keys['two_c2p_public_key'], $otherMerchantPrivateKey))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed:');
    });

    it('wraps invalid base64url payload errors during decode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        expect(fn (): string => $jwt->decode('a.b?.c', $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed:');
    });

    it('wraps invalid key type errors for private and public key loaders', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();
        $dsa = twoC2pDsaKeyFixture();

        expect(fn (): string => $jwt->encode('<x/>', $dsa['private_key'], $keys['two_c2p_public_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT encode failed:')
            ->and(fn (): string => $jwt->encode('<x/>', $keys['merchant_private_key'], $dsa['public_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT encode failed:');
    });

    it('wraps invalid signature errors during decode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();
        $token = $jwt->encode('<x/>', $keys['two_c2p_private_key'], $keys['merchant_public_key']);

        [$protected, $payload, $signature] = explode('.', $token);
        $signature[0] = $signature[0] === 'A' ? 'B' : 'A';
        $tamperedToken = implode('.', [$protected, $payload, $signature]);

        expect(fn (): string => $jwt->decode($tamperedToken, $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed: Invalid signature.');
    });

    it('wraps invalid jwe payload shape during decode', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        $token = twoC2pInvokePrivate($jwt, 'signJws', ['bad-jwe', $keys['two_c2p_private_key'], null]);

        expect(fn (): string => $jwt->decode($token, $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed: Invalid JWE token.');
    });

    it('handles encrypted content key and payload decryption failures', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        $jwe = twoC2pInvokePrivate($jwt, 'encryptJwe', ['<x/>', $keys['merchant_public_key']]);
        $parts = explode('.', $jwe);

        $invalidContentKey = $parts;
        $invalidContentKey[1] = '';

        expect(fn (): string => twoC2pInvokePrivate($jwt, 'decryptJwe', [implode('.', $invalidContentKey), $keys['merchant_private_key']]))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed: Unable to decrypt content key.');

        $invalidPayload = $parts;
        $invalidPayload[4] = twoC2pB64UrlEncode(str_repeat("\0", 16));

        expect(fn (): string => twoC2pInvokePrivate($jwt, 'decryptJwe', [implode('.', $invalidPayload), $keys['merchant_private_key']]))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed: Unable to decrypt payload.');

        $tokenWithBadContentKey = twoC2pInvokePrivate($jwt, 'signJws', [implode('.', $invalidContentKey), $keys['two_c2p_private_key'], null]);
        $tokenWithBadPayload = twoC2pInvokePrivate($jwt, 'signJws', [implode('.', $invalidPayload), $keys['two_c2p_private_key'], null]);

        expect(fn (): string => $jwt->decode($tokenWithBadContentKey, $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed:')
            ->and(fn (): string => $jwt->decode($tokenWithBadPayload, $keys['two_c2p_public_key'], $keys['merchant_private_key']))
            ->toThrow(ProviderException::class, '2C2P key JWT decode failed:');
    });

    it('wraps json encoding errors for invalid utf-8 key ids', function (): void {
        $jwt = new TwoC2PKeyJwt;
        $keys = twoC2pJoseKeyFixture();

        expect(fn (): string => $jwt->encode('<x/>', $keys['merchant_private_key'], $keys['two_c2p_public_key'], "\xB1\x31"))
            ->toThrow(ProviderException::class, '2C2P key JWT encode failed:');
    });
});
