<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\DTO\PaymentResponse;

interface CanQueryPayment
{
    public function queryStatus(string $transactionId): PaymentResponse;
}
