<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\DTO\CallbackPayload;

interface CanVerifyCallback
{
    public function verifyCallback(CallbackPayload $payload): bool;
}
