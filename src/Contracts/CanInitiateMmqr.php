<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;

interface CanInitiateMmqr
{
    public function createMmqr(MmqrRequest $request): MmqrResponse;
}
