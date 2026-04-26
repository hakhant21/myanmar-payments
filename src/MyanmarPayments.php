<?php

declare(strict_types=1);

namespace Hakhant\Payments;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\Provider;

final readonly class MyanmarPayments
{
    public function __construct(private PaymentManager $paymentManager) {}

    public function provider(Provider|string|null $provider = null): PaymentGateway
    {
        return $this->paymentManager->provider($provider);
    }

    public function createPayment(PaymentRequest $request, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->paymentManager->createPayment($request, $provider);
    }

    public function queryStatus(string $transactionId, Provider|string|null $provider = null): PaymentResponse
    {
        return $this->paymentManager->queryStatus($transactionId, $provider);
    }

    public function createMmqr(MmqrRequest $request, Provider|string|null $provider = null): MmqrResponse
    {
        return $this->paymentManager->createMmqr($request, $provider);
    }

    public function refund(RefundRequest $request, Provider|string|null $provider = null): RefundResponse
    {
        return $this->paymentManager->refund($request, $provider);
    }

    public function verifyCallback(CallbackPayload $payload, Provider|string|null $provider = null): bool
    {
        return $this->paymentManager->verifyCallback($payload, $provider);
    }

    public function callbackSuccessResponse(Provider|string|null $provider = null): string
    {
        return $this->paymentManager->callbackSuccessResponse($provider);
    }

    public function supportsMmqr(Provider|string|null $provider = null): bool
    {
        return $this->paymentManager->supportsMmqr($provider);
    }

    public function supportsRefunds(Provider|string|null $provider = null): bool
    {
        return $this->paymentManager->supportsRefunds($provider);
    }

    public function supportsCallbackVerification(Provider|string|null $provider = null): bool
    {
        return $this->paymentManager->supportsCallbackVerification($provider);
    }
}
