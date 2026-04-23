<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\ValueObjects;

use Hakhant\Payments\Domain\Exceptions\ValidationException;

final readonly class MerchantReference
{
    public function __construct(public string $value)
    {
        if ($this->value === '') {
            throw new ValidationException('Merchant reference is required.');
        }
    }
}
