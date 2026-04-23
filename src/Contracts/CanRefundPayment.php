<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;

interface CanRefundPayment
{
    public function refund(RefundRequest $request): RefundResponse;
}
