<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\ValueObjects;

use Hakhant\Payments\Domain\Exceptions\ValidationException;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        if ($this->amount <= 0) {
            throw new ValidationException('Amount must be greater than zero.');
        }

        if (mb_strlen($this->currency) !== 3) {
            throw new ValidationException('Currency must be ISO-4217 3-letter code.');
        }
    }
}
