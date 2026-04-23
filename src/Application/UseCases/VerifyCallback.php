<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class VerifyCallback
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(CallbackPayload $payload, ?string $provider = null): bool
    {
        $gateway = $this->paymentManager->provider($provider);

        if (! $gateway instanceof CanVerifyCallback) {
            throw new ProviderException('Selected provider does not support callback verification.');
        }

        return $gateway->verifyCallback($payload);
    }
}
