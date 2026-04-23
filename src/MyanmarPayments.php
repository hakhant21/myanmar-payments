<?php

declare(strict_types=1);

namespace Hakhant\Payments;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\PaymentGateway;

final readonly class MyanmarPayments
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function provider(?string $provider = null): PaymentGateway
    {
        return $this->paymentManager->provider($provider);
    }
}
