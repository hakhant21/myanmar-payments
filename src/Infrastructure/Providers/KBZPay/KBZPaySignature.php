<?php

declare(strict_types=1);

namespace Hakhant\Payments\Infrastructure\Providers\KBZPay;

final readonly class KBZPaySignature
{
    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $fields
     */
    public function sign(array $fields, string $secret): string
    {
        $signingBase = $this->signingBase($fields);

        return strtoupper(hash('sha256', $signingBase.'&key='.$secret));
    }

    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $fields
     */
    public function verify(array $fields, string $providedSignature, string $secret): bool
    {
        $computed = $this->sign($fields, $secret);

        return hash_equals($computed, strtoupper($providedSignature));
    }

    /**
     * KBZ spec: sort non-empty scalar parameters by ASCII key and join key=value.
     * `sign` and `sign_type` are excluded.
     *
     * @param  array<string, scalar|array<array-key, mixed>|null>  $fields
     */
    private function signingBase(array $fields): string
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type') {
                continue;
            }

            if (is_array($value)) {
                // KBZ docs: JSONArray fields are excluded from signing.
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        ksort($normalized, SORT_STRING);

        $pairs = [];
        foreach ($normalized as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }
}
