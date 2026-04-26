<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application\UseCases;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class CreateMmqr
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function handle(MmqrRequest $request, Provider|string|null $provider = null): MmqrResponse
    {
        $gateway = $this->paymentManager->provider($provider);

        if (! $gateway instanceof CanInitiateMmqr) {
            throw new ProviderException('Selected provider does not support MMQR.');
        }

        return $gateway->createMmqr($request);
    }
}
