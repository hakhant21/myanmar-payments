<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application;

use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Contracts\PaymentGateway;

final readonly class PaymentManager
{
    public function __construct(
        private GatewayFactory $gatewayFactory,
        private string $defaultProvider,
    ) {}

    public function provider(?string $provider = null): PaymentGateway
    {
        return $this->gatewayFactory->make($provider ?? $this->defaultProvider);
    }
}
