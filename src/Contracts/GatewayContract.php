<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

use Hakhant\Payments\Domain\Enums\Provider;

interface GatewayContract
{
    public function make(Provider|string $provider): PaymentGateway;
}
