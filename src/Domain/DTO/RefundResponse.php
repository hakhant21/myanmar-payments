<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class RefundResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public string $refundId,
        public PaymentStatus $status,
        public array $raw = [],
    ) {}
}
