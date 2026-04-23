<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

use Hakhant\Payments\Domain\Exceptions\ValidationException;

final readonly class MmqrRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws ValidationException
     */
    public function __construct(
        public string $merchantReference,
        public int $amount,
        public string $currency,
        public string $notifyUrl,
        public array $metadata = [],
    ) {
        if ($this->merchantReference === '') {
            throw new ValidationException('merchantReference is required.');
        }

        if ($this->amount <= 0) {
            throw new ValidationException('amount must be greater than zero.');
        }

        if ($this->currency === '') {
            throw new ValidationException('currency is required.');
        }

        if ($this->notifyUrl === '' || filter_var($this->notifyUrl, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException('notifyUrl must be a valid URL.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchantReference' => $this->merchantReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'notifyUrl' => $this->notifyUrl,
            'metadata' => $this->metadata,
        ];
    }
}
