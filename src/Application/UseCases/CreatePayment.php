<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\Enums\Provider;

final readonly class CreatePayment
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(PaymentRequest $request, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->paymentManager->provider($provider)->createPayment($request);
    }
}
