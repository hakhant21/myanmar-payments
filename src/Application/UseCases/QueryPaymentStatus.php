<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\DTO\PaymentResponse;

final readonly class QueryPaymentStatus
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(string $transactionId, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->paymentManager->provider($provider)->queryStatus($transactionId);
    }
}
