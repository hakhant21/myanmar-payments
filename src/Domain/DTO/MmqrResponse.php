<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

use Hakhant\Payments\Domain\Enums\PaymentStatus;

final readonly class MmqrResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public string $transactionId,
        public PaymentStatus $status,
        public string $qrCode,
        public ?string $qrImage,
        public array $raw,
    ) {}
}
