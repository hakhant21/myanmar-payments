<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\TwoC2P;

use Hakhant\Payments\Domain\Exceptions\ProviderException;
use JsonException;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use Throwable;

final class TwoC2PKeyJwt
{
    public function encode(string $payload, string $merchantPrivateKey, string $twoC2pPublicKey, ?string $keyId = null): string
    {
        try {
            $jwe = $this->encryptJwe($payload, $twoC2pPublicKey);

            return $this->signJws($jwe, $merchantPrivateKey, $keyId);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();

            if (! str_starts_with($message, '2C2P key JWT encode failed:')) {
                $message = '2C2P key JWT encode failed: '.$message;
            }

            throw new ProviderException($message, 0, $throwable);
        }
    }

    public function decode(string $token, string $twoC2pPublicKey, string $merchantPrivateKey): string
    {
        try {
            $jwe = $this->verifyJws($token, $twoC2pPublicKey);

            return $this->decryptJwe($jwe, $merchantPrivateKey);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();

            if (! str_starts_with($message, '2C2P key JWT decode failed:')) {
                $message = '2C2P key JWT decode failed: '.$message;
            }

            throw new ProviderException($message, 0, $throwable);
        }
    }

    private function encryptJwe(string $payload, string $publicKeyPem): string
    {
        $protected = $this->base64UrlEncode($this->jsonEncode([
            'alg' => 'RSA-OAEP-256',
            'enc' => 'A256GCM',
            'typ' => 'JWT',
        ]));

        $contentEncryptionKey = random_bytes(32);
        $iv = random_bytes(12);
        $publicKey = $this->publicKey($publicKeyPem);
        $encryptedKey = $publicKey->encrypt($contentEncryptionKey);
        $encryptedKey = is_string($encryptedKey) ? $encryptedKey : '';

        $cipher = new AES('gcm');
        $cipher->setKey($contentEncryptionKey);
        $cipher->setNonce($iv);
        $cipher->setAAD($protected);
        $ciphertext = $cipher->encrypt($payload);
        $tag = $cipher->getTag();

        return implode('.', [
            $protected,
            $this->base64UrlEncode($encryptedKey),
            $this->base64UrlEncode($iv),
            $this->base64UrlEncode($ciphertext),
            $this->base64UrlEncode($tag),
        ]);
    }

    private function decryptJwe(string $token, string $privateKeyPem): string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 5) {
            throw new ProviderException('2C2P key JWT decode failed: Invalid JWE token.');
        }

        [$protected, $encryptedKey, $iv, $ciphertext, $tag] = $parts;
        $privateKey = $this->privateKey($privateKeyPem);
        try {
            $contentEncryptionKey = $privateKey->decrypt($this->base64UrlDecode($encryptedKey));
        } catch (Throwable) {
            $contentEncryptionKey = false;
        }

        if (! is_string($contentEncryptionKey)) {
            throw new ProviderException('2C2P key JWT decode failed: Unable to decrypt content key.');
        }

        $cipher = new AES('gcm');
        $cipher->setKey($contentEncryptionKey);
        $cipher->setNonce($this->base64UrlDecode($iv));
        $cipher->setAAD($protected);
        $cipher->setTag($this->base64UrlDecode($tag));
        try {
            $plaintext = $cipher->decrypt($this->base64UrlDecode($ciphertext));
        } catch (Throwable) {
            $plaintext = false;
        }

        if (! is_string($plaintext)) {
            throw new ProviderException('2C2P key JWT decode failed: Unable to decrypt payload.');
        }

        return $plaintext;
    }

    private function signJws(string $payload, string $privateKeyPem, ?string $keyId): string
    {
        $headers = [
            'alg' => 'PS256',
            'typ' => 'JWT',
        ];

        if ($keyId !== null && $keyId !== '') {
            $headers['kid'] = $keyId;
        }

        $protected = $this->base64UrlEncode($this->jsonEncode($headers));
        $encodedPayload = $this->base64UrlEncode($payload);
        $signingInput = $protected.'.'.$encodedPayload;
        $privateKey = $this->privateKey($privateKeyPem);
        $signature = $privateKey->sign($signingInput);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function verifyJws(string $token, string $publicKeyPem): string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new ProviderException('2C2P key JWT decode failed: Invalid JWS token.');
        }

        [$protected, $payload, $signature] = $parts;
        $publicKey = $this->publicKey($publicKeyPem);

        if (! $publicKey->verify($protected.'.'.$payload, $this->base64UrlDecode($signature))) {
            throw new ProviderException('2C2P key JWT decode failed: Invalid signature.');
        }

        return $this->base64UrlDecodeToString($payload);
    }

    private function privateKey(string $privateKeyPem): PrivateKey
    {
        try {
            $loaded = PublicKeyLoader::loadPrivateKey($privateKeyPem);

            if (! $loaded instanceof PrivateKey) {
                throw new ProviderException('2C2P key JWT operation failed: Invalid private key.');
            }

            $key = $loaded
                ->withPadding(RSA::SIGNATURE_PSS | RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
        } catch (Throwable $throwable) {
            throw new ProviderException('2C2P key JWT operation failed: Invalid private key.', 0, $throwable);
        }

        return $key;
    }

    private function publicKey(string $publicKeyPem): PublicKey
    {
        try {
            $loaded = PublicKeyLoader::load($publicKeyPem);

            if (! $loaded instanceof PublicKey) {
                throw new ProviderException('2C2P key JWT operation failed: Invalid public key.');
            }

            $key = $loaded
                ->withPadding(RSA::SIGNATURE_PSS | RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
        } catch (Throwable $throwable) {
            throw new ProviderException('2C2P key JWT operation failed: Invalid public key.', 0, $throwable);
        }

        return $key;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function jsonEncode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ProviderException('2C2P key JWT operation failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        if (! is_string($decoded)) {
            throw new ProviderException('2C2P key JWT operation failed: Invalid base64url payload.');
        }

        return $decoded;
    }

    private function base64UrlDecodeToString(string $value): string
    {
        return $this->base64UrlDecode($value);
    }
}
