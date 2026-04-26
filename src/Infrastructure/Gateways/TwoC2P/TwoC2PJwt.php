<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\TwoC2P;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Throwable;

final readonly class TwoC2PJwt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload, string $secret): string
    {
        try {
            return JWT::encode($payload, $secret, 'HS256');
        } catch (Throwable $throwable) {
            throw new ProviderException(
                sprintf('2C2P JWT encode failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $jwt, string $secret): array
    {
        try {
            $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));
        } catch (Throwable $throwable) {
            throw new ProviderException(
                sprintf('2C2P JWT decode failed: %s', $throwable->getMessage()),
                (int) $throwable->getCode(),
                $throwable,
            );
        }

        return json_decode((string) json_encode($decoded), true) ?? [];
    }
}
