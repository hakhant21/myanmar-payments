<?php

declare(strict_types=1);

namespace Hakhant\Payments\Application;

use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\GatewayContract;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Exceptions\ProviderException;

final readonly class PaymentManager
{
    public function __construct(
        private GatewayContract $gatewayFactory,
        private string $defaultProvider,
    ) {}

    public function provider(Provider|string|null $provider = null): PaymentGateway
    {
        return $this->gatewayFactory->make($provider ?? $this->defaultProvider);
    }

    public function createPayment(PaymentRequest $request, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->provider($provider)->createPayment($request);
    }

    public function queryStatus(string $transactionId, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->provider($provider)->queryStatus($transactionId);
    }

    public function createMmqr(MmqrRequest $request, Provider|string|null $provider = null): MmqrResponse
    {
        $gateway = $this->provider($provider);

        if (! $gateway instanceof CanInitiateMmqr) {
            throw new ProviderException('Selected provider does not support MMQR.');
        }

        return $gateway->createMmqr($request);
    }

    public function refund(RefundRequest $request, Provider|string|null $provider = null): RefundResponse
    {
        $gateway = $this->provider($provider);

        if (! $gateway instanceof CanRefundPayment) {
            throw new ProviderException('Selected provider does not support refunds.');
        }

        return $gateway->refund($request);
    }

    public function verifyCallback(CallbackPayload $payload, Provider|string|null $provider = null): bool
    {
        $gateway = $this->provider($provider);

        if (! $gateway instanceof CanVerifyCallback) {
            throw new ProviderException('Selected provider does not support callback verification.');
        }

        return $gateway->verifyCallback($payload);
    }

    public function supportsMmqr(Provider|string|null $provider = null): bool
    {
        return $this->provider($provider) instanceof CanInitiateMmqr;
    }

    public function supportsRefunds(Provider|string|null $provider = null): bool
    {
        return $this->provider($provider) instanceof CanRefundPayment;
    }

    public function supportsCallbackVerification(Provider|string|null $provider = null): bool
    {
        return $this->provider($provider) instanceof CanVerifyCallback;
    }
}
