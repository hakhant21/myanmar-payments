<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

interface GatewayFactory
{
    public function make(string $provider): PaymentGateway;
}
