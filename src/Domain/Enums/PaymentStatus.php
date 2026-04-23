<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case UNKNOWN = 'unknown';
}
