<?php

declare(strict_types=1);

namespace Hakhant\Payments\Domain\ValueObjects;

final readonly class Signature
{
    public function __construct(public string $value) {}
}
