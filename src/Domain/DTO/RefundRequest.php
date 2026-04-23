<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

use Hakhant\Payments\Domain\Exceptions\ValidationException;

final readonly class RefundRequest
{
    public function __construct(
        public string $transactionId,
        public int $amount,
        public string $reason = '',
    ) {
        if ($this->transactionId === '') {
            throw new ValidationException('transactionId is required.');
        }

        if ($this->amount <= 0) {
            throw new ValidationException('amount must be greater than zero.');
        }
    }
}
