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
use Hakhant\Payments\Support\Idempotency\CallbackIdempotencyGuard;

final readonly class PaymentManager
{
    public function __construct(
        private GatewayContract $gatewayFactory,
        private string $defaultProvider,
        private ?CallbackIdempotencyGuard $callbackIdempotencyGuard = null,
        private int $callbackTimestampToleranceSeconds = 300,
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

        if (! $this->isCallbackTimestampValid($payload)) {
            return false;
        }

        if (! $this->lockCallback($payload, $provider)) {
            return false;
        }

        return $gateway->verifyCallback($payload);
    }

    public function callbackSuccessResponse(Provider|string|null $provider = null): string
    {
        $gateway = $this->provider($provider);

        if (method_exists($gateway, 'callbackSuccessResponse')) {
            /** @var callable(): string $callback */
            $callback = [$gateway, 'callbackSuccessResponse'];

            return $callback();
        }

        return 'success';
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

    private function isCallbackTimestampValid(CallbackPayload $payload): bool
    {
        if ($payload->timestamp === null) {
            return true;
        }

        return abs(time() - $payload->timestamp) <= $this->callbackTimestampToleranceSeconds;
    }

    private function lockCallback(CallbackPayload $payload, Provider|string|null $provider): bool
    {
        if (! $this->callbackIdempotencyGuard instanceof CallbackIdempotencyGuard) {
            return true;
        }

        return $this->callbackIdempotencyGuard->lock(
            $this->callbackLockKey($payload, $provider),
            max($this->callbackTimestampToleranceSeconds, 1),
        );
    }

    private function callbackLockKey(CallbackPayload $payload, Provider|string|null $provider): string
    {
        $providerName = Provider::normalize($provider ?? $this->defaultProvider);
        $encodedPayload = json_encode($payload->payload, JSON_UNESCAPED_SLASHES);

        return hash('sha256', implode('|', [
            $providerName,
            $payload->signature,
            (string) ($payload->timestamp ?? ''),
            is_string($encodedPayload) ? $encodedPayload : '',
        ]));
    }
}
