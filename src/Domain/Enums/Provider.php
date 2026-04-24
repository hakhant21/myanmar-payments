<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\Enums;

enum Provider: string
{
    case TWOC2P = '2c2p';
    case KBZPAY = 'kbzpay';
    case WAVEMONEY = 'wavemoney';
}
