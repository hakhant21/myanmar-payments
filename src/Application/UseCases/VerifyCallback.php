<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\Enums\Provider;

final readonly class VerifyCallback
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(CallbackPayload $payload, Provider|string|null $provider = null): bool
    {
        return $this->paymentManager->verifyCallback($payload, $provider);
    }
}
