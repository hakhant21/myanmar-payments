<?php

declare(strict_types=1);

namespace Hakhant\Payments\Facades;

use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\Provider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Hakhant\Payments\Contracts\PaymentGateway provider(Provider|string|null $provider = null)
 * @method static PaymentResponse createPayment(PaymentRequest $request, Provider|string|null $provider = null)
 * @method static PaymentResponse queryStatus(string $transactionId, Provider|string|null $provider = null)
 * @method static MmqrResponse createMmqr(MmqrRequest $request, Provider|string|null $provider = null)
 * @method static RefundResponse refund(RefundRequest $request, Provider|string|null $provider = null)
 * @method static bool verifyCallback(CallbackPayload $payload, Provider|string|null $provider = null)
 * @method static string callbackSuccessResponse(Provider|string|null $provider = null)
 * @method static bool supportsMmqr(Provider|string|null $provider = null)
 * @method static bool supportsRefunds(Provider|string|null $provider = null)
 * @method static bool supportsCallbackVerification(Provider|string|null $provider = null)
 */
final class MyanmarPayments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'myanmar-payments';
    }
}
