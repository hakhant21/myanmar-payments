<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;

interface CanInitiatePayment
{
    public function createPayment(PaymentRequest $request): PaymentResponse;
}
