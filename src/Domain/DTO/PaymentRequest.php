<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

use Hakhant\Payments\Domain\Exceptions\ValidationException;

final readonly class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $merchantReference,
        public int $amount,
        public string $currency,
        public string $callbackUrl,
        public string $redirectUrl,
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

        if (! filter_var($this->callbackUrl, FILTER_VALIDATE_URL)) {
            throw new ValidationException('callbackUrl must be a valid URL.');
        }

        if (parse_url($this->callbackUrl, PHP_URL_QUERY) !== null) {
            throw new ValidationException('callbackUrl must not contain query parameters.');
        }

        if (! filter_var($this->redirectUrl, FILTER_VALIDATE_URL)) {
            throw new ValidationException('redirectUrl must be a valid URL.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchant_reference' => $this->merchantReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'callback_url' => $this->callbackUrl,
            'redirect_url' => $this->redirectUrl,
            'metadata' => $this->metadata,
        ];
    }
}
