<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\CanInitiateMmqr;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\GatewayContract;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\MmqrRequest;
use Hakhant\Payments\Domain\DTO\MmqrResponse;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\Provider;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Support\Idempotency\CallbackIdempotencyGuard;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

afterEach(fn () => Mockery::close());

describe('PaymentManager::provider()', function (): void {
    it('returns gateway for the default provider when no argument given', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        $result = $manager->provider();

        expect($result)->toBe($gateway);
    });

    it('returns gateway for an explicit provider argument', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        $result = $manager->provider('kbzpay');

        expect($result)->toBe($gateway);
    });

    it('uses explicit provider argument over default', function (): void {
        $gatewayKbz = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gatewayKbz);

        $manager = new PaymentManager($factory, 'some_default');
        $result = $manager->provider('kbzpay');

        expect($result)->toBe($gatewayKbz);
    });

    it('delegates to factory with null defaulting to default provider', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        expect($manager->provider())->toBe($gateway);
    });

    it('accepts Provider enum values', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->with(Provider::KBZPAY)->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        expect($manager->provider(Provider::KBZPAY))->toBe($gateway);
    });

    it('provides a higher-level developer API for common flows', function (): void {
        $paymentRequest = new PaymentRequest('ORD100', 1000, 'MMK', 'https://a.test/cb', 'https://a.test/rt');
        $mmqrRequest = new MmqrRequest('MMQR100', 1000, 'MMK', 'https://a.test/cb');
        $refundRequest = new RefundRequest('ORD100', 100, 'test');
        $callbackPayload = new CallbackPayload(['Request' => ['x' => 'y']], 'sig');
        $paymentResponse = new PaymentResponse('kbzpay', 'ORD100', PaymentStatus::PENDING, 'PRE100', []);
        $mmqrResponse = new MmqrResponse('kbzpay', 'MMQR100', PaymentStatus::PENDING, 'QR100', null, []);
        $refundResponse = new RefundResponse('kbzpay', 'REF100', PaymentStatus::REFUNDED, []);

        $gateway = Mockery::mock(PaymentGateway::class, CanInitiateMmqr::class, CanRefundPayment::class, CanVerifyCallback::class);
        $gateway->shouldReceive('createPayment')->once()->with($paymentRequest)->andReturn($paymentResponse);
        $gateway->shouldReceive('queryStatus')->once()->with('ORD100')->andReturn($paymentResponse);
        $gateway->shouldReceive('createMmqr')->once()->with($mmqrRequest)->andReturn($mmqrResponse);
        $gateway->shouldReceive('refund')->once()->with($refundRequest)->andReturn($refundResponse);
        $gateway->shouldReceive('verifyCallback')->once()->with($callbackPayload)->andReturn(true);

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->times(8)->with(Provider::KBZPAY)->andReturn($gateway);

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')->once()->andReturn(true);
        $guard = new CallbackIdempotencyGuard($cache);

        $manager = new PaymentManager($factory, 'kbzpay', $guard, 300);

        expect($manager->createPayment($paymentRequest, Provider::KBZPAY))->toBe($paymentResponse)
            ->and($manager->queryStatus('ORD100', Provider::KBZPAY))->toBe($paymentResponse)
            ->and($manager->createMmqr($mmqrRequest, Provider::KBZPAY))->toBe($mmqrResponse)
            ->and($manager->refund($refundRequest, Provider::KBZPAY))->toBe($refundResponse)
            ->and($manager->verifyCallback($callbackPayload, Provider::KBZPAY))->toBeTrue()
            ->and($manager->supportsMmqr(Provider::KBZPAY))->toBeTrue()
            ->and($manager->supportsRefunds(Provider::KBZPAY))->toBeTrue()
            ->and($manager->supportsCallbackVerification(Provider::KBZPAY))->toBeTrue();
    });

    it('throws from higher-level methods when capability is missing', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->times(6)->with('kbzpay')->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        expect(fn (): MmqrResponse => $manager->createMmqr(new MmqrRequest('MMQR101', 1000, 'MMK', 'https://a.test/cb')))
            ->toThrow(ProviderException::class, 'Selected provider does not support MMQR.')
            ->and(fn (): RefundResponse => $manager->refund(new RefundRequest('ORD101', 100)))
            ->toThrow(ProviderException::class, 'Selected provider does not support refunds.')
            ->and(fn (): bool => $manager->verifyCallback(new CallbackPayload([], '')))
            ->toThrow(ProviderException::class, 'Selected provider does not support callback verification.')
            ->and($manager->supportsMmqr())->toBeFalse()
            ->and($manager->supportsRefunds())->toBeFalse()
            ->and($manager->supportsCallbackVerification())->toBeFalse();
    });

    it('rejects callbacks outside the configured timestamp tolerance', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class, CanVerifyCallback::class);
        $gateway->shouldNotReceive('verifyCallback');

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->once()->with('kbzpay')->andReturn($gateway);

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldNotReceive('add');
        $guard = new CallbackIdempotencyGuard($cache);

        $manager = new PaymentManager($factory, 'kbzpay', $guard, 300);

        expect($manager->verifyCallback(new CallbackPayload([], 'sig', time() - 301)))->toBeFalse();
    });

    it('rejects duplicate callbacks before gateway verification', function (): void {
        $payload = new CallbackPayload(['Request' => ['x' => 'y']], 'sig', time());

        $gateway = Mockery::mock(PaymentGateway::class, CanVerifyCallback::class);
        $gateway->shouldNotReceive('verifyCallback');

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->once()->with('kbzpay')->andReturn($gateway);

        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')->once()->andReturn(false);
        $guard = new CallbackIdempotencyGuard($cache);

        $manager = new PaymentManager($factory, 'kbzpay', $guard, 300);

        expect($manager->verifyCallback($payload))->toBeFalse();
    });
});
