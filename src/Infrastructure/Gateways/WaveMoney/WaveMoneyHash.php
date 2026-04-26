<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Gateways\WaveMoney;

final readonly class WaveMoneyHash
{
    /**
     * @param  array<int, scalar|null>  $parts
     */
    public function sign(array $parts, string $secret): string
    {
        return hash_hmac('sha256', $this->message($parts), $secret);
    }

    /**
     * @param  array<int, scalar|null>  $parts
     */
    public function verify(array $parts, string $providedHash, string $secret): bool
    {
        return hash_equals($this->sign($parts, $secret), strtolower($providedHash));
    }

    /**
     * @param  array<int, scalar|null>  $parts
     */
    private function message(array $parts): string
    {
        $string = '';

        foreach ($parts as $part) {
            $string .= $part === null ? 'null' : (string) $part;
        }

        return $string;
    }
}
