<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\Enums;

enum Provider: string
{
    case TWOC2P = '2c2p';
    case AYA = 'aya';
    case KBZPAY = 'kbzpay';
    case WAVEMONEY = 'wavemoney';

    public static function normalize(self|string $provider): string
    {
        return $provider instanceof self
            ? $provider->value
            : strtolower(trim($provider));
    }
}
