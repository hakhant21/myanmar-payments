<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\DTO;

final readonly class CallbackPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $signature,
        public ?int $timestamp = null,
    ) {}
}
