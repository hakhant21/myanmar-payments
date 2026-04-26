<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class RefundPayment
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(RefundRequest $request, Provider|string|null $provider = null): RefundResponse
    {
        $gateway = $this->paymentManager->provider($provider);

        if (! $gateway instanceof CanRefundPayment) {
            throw new ProviderException('Selected provider does not support refunds.');
        }

        return $gateway->refund($request);
    }
}
