<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Application\UseCases\CreateMmqr;
use Hakhant\Payments\Application\UseCases\CreatePayment;
use Hakhant\Payments\Application\UseCases\QueryPaymentStatus;
use Hakhant\Payments\Application\UseCases\RefundPayment;
use Hakhant\Payments\Application\UseCases\VerifyCallback;
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
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\MyanmarPayments;

function makePaymentManagerWithGateway(PaymentGateway $gateway): PaymentManager
{
    $factory = Mockery::mock(GatewayContract::class);
    $factory->shouldReceive('make')->andReturn($gateway);

    return new PaymentManager($factory, 'kbzpay');
}

afterEach(function (): void {
    Mockery::close();
});

describe('Application use cases', function (): void {
    it('CreatePayment delegates to gateway createPayment', function (): void {
        $request = new PaymentRequest('ORD001', 1000, 'MMK', 'https://a.test/cb', 'https://a.test/rt');
        $expected = new PaymentResponse('kbzpay', 'ORD001', PaymentStatus::PENDING, 'PRE001', []);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createPayment')->once()->with($request)->andReturn($expected);
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $result = (new CreatePayment(makePaymentManagerWithGateway($gateway)))->handle($request);

        expect($result)->toBe($expected);
    });

    it('QueryPaymentStatus delegates to gateway queryStatus', function (): void {
        $expected = new PaymentResponse('kbzpay', 'ORD002', PaymentStatus::SUCCESS, null, []);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('queryStatus')->once()->with('ORD002')->andReturn($expected);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();

        $result = (new QueryPaymentStatus(makePaymentManagerWithGateway($gateway)))->handle('ORD002');

        expect($result)->toBe($expected);
    });

    it('CreateMmqr delegates when provider supports MMQR', function (): void {
        $request = new MmqrRequest('MMQR001', 1000, 'MMK', 'https://a.test/cb');
        $expected = new MmqrResponse('kbzpay', 'MMQR001', PaymentStatus::PENDING, 'QR001', null, []);

        $gateway = Mockery::mock(PaymentGateway::class, CanInitiateMmqr::class);
        $gateway->shouldReceive('createMmqr')->once()->with($request)->andReturn($expected);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $result = (new CreateMmqr(makePaymentManagerWithGateway($gateway)))->handle($request);

        expect($result)->toBe($expected);
    });

    it('CreateMmqr throws when provider does not support MMQR', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $useCase = new CreateMmqr(makePaymentManagerWithGateway($gateway));

        expect(fn (): MmqrResponse => $useCase->handle(new MmqrRequest('MMQR002', 1000, 'MMK', 'https://a.test/cb')))
            ->toThrow(ProviderException::class, 'Selected provider does not support MMQR.');
    });

    it('RefundPayment delegates when provider supports refunds', function (): void {
        $request = new RefundRequest('ORD003', 200, 'test');
        $expected = new RefundResponse('kbzpay', 'REF001', PaymentStatus::REFUNDED, []);

        $gateway = Mockery::mock(PaymentGateway::class, CanRefundPayment::class);
        $gateway->shouldReceive('refund')->once()->with($request)->andReturn($expected);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $result = (new RefundPayment(makePaymentManagerWithGateway($gateway)))->handle($request);

        expect($result)->toBe($expected);
    });

    it('RefundPayment throws when provider does not support refunds', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $useCase = new RefundPayment(makePaymentManagerWithGateway($gateway));

        expect(fn (): RefundResponse => $useCase->handle(new RefundRequest('ORD004', 100)))
            ->toThrow(ProviderException::class, 'Selected provider does not support refunds.');
    });

    it('VerifyCallback delegates when provider supports callbacks', function (): void {
        $payload = new CallbackPayload(['Request' => ['x' => 'y']], 'sig');

        $gateway = Mockery::mock(PaymentGateway::class, CanVerifyCallback::class);
        $gateway->shouldReceive('verifyCallback')->once()->with($payload)->andReturn(true);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $result = (new VerifyCallback(makePaymentManagerWithGateway($gateway)))->handle($payload);

        expect($result)->toBeTrue();
    });

    it('VerifyCallback throws when provider does not support callbacks', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createPayment')->zeroOrMoreTimes();
        $gateway->shouldReceive('queryStatus')->zeroOrMoreTimes();

        $useCase = new VerifyCallback(makePaymentManagerWithGateway($gateway));

        expect(fn (): bool => $useCase->handle(new CallbackPayload([], '')))
            ->toThrow(ProviderException::class, 'Selected provider does not support callback verification.');
    });

    it('passes Provider enum values through use cases', function (): void {
        $request = new PaymentRequest('ORD007', 1000, 'MMK', 'https://a.test/cb', 'https://a.test/rt');
        $payload = new CallbackPayload(['Request' => ['x' => 'y']], 'sig');
        $mmqrRequest = new MmqrRequest('MMQR007', 1000, 'MMK', 'https://a.test/cb');
        $refundRequest = new RefundRequest('ORD007', 100, 'test');
        $paymentResponse = new PaymentResponse('kbzpay', 'ORD007', PaymentStatus::PENDING, 'PRE007', []);
        $mmqrResponse = new MmqrResponse('kbzpay', 'MMQR007', PaymentStatus::PENDING, 'QR007', null, []);
        $refundResponse = new RefundResponse('kbzpay', 'REF007', PaymentStatus::REFUNDED, []);

        $gateway = Mockery::mock(PaymentGateway::class, CanInitiateMmqr::class, CanRefundPayment::class, CanVerifyCallback::class);
        $gateway->shouldReceive('createPayment')->once()->with($request)->andReturn($paymentResponse);
        $gateway->shouldReceive('createMmqr')->once()->with($mmqrRequest)->andReturn($mmqrResponse);
        $gateway->shouldReceive('queryStatus')->once()->with('ORD007')->andReturn($paymentResponse);
        $gateway->shouldReceive('refund')->once()->with($refundRequest)->andReturn($refundResponse);
        $gateway->shouldReceive('verifyCallback')->once()->with($payload)->andReturn(true);

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->times(5)->with(Provider::KBZPAY)->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        expect((new CreatePayment($manager))->handle($request, Provider::KBZPAY))->toBe($paymentResponse)
            ->and((new CreateMmqr($manager))->handle($mmqrRequest, Provider::KBZPAY))->toBe($mmqrResponse)
            ->and((new QueryPaymentStatus($manager))->handle('ORD007', Provider::KBZPAY))->toBe($paymentResponse)
            ->and((new RefundPayment($manager))->handle($refundRequest, Provider::KBZPAY))->toBe($refundResponse)
            ->and((new VerifyCallback($manager))->handle($payload, Provider::KBZPAY))->toBeTrue();
    });
});

describe('MyanmarPayments wrapper', function (): void {
    it('delegates provider selection to PaymentManager', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->once()->with('kbzpay')->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        $wrapper = new MyanmarPayments($manager);

        expect($wrapper->provider('kbzpay'))->toBe($gateway);
    });

    it('delegates higher-level helper methods to PaymentManager', function (): void {
        $paymentRequest = new PaymentRequest('ORD200', 1000, 'MMK', 'https://a.test/cb', 'https://a.test/rt');
        $mmqrRequest = new MmqrRequest('MMQR200', 1000, 'MMK', 'https://a.test/cb');
        $refundRequest = new RefundRequest('ORD200', 100, 'test');
        $callbackPayload = new CallbackPayload(['Request' => ['x' => 'y']], 'sig');
        $paymentResponse = new PaymentResponse('kbzpay', 'ORD200', PaymentStatus::PENDING, 'PRE200', []);
        $mmqrResponse = new MmqrResponse('kbzpay', 'MMQR200', PaymentStatus::PENDING, 'QR200', null, []);
        $refundResponse = new RefundResponse('kbzpay', 'REF200', PaymentStatus::REFUNDED, []);

        $gateway = Mockery::mock(PaymentGateway::class, CanInitiateMmqr::class, CanRefundPayment::class, CanVerifyCallback::class);
        $gateway->shouldReceive('createPayment')->once()->with($paymentRequest)->andReturn($paymentResponse);
        $gateway->shouldReceive('queryStatus')->once()->with('ORD200')->andReturn($paymentResponse);
        $gateway->shouldReceive('createMmqr')->once()->with($mmqrRequest)->andReturn($mmqrResponse);
        $gateway->shouldReceive('refund')->once()->with($refundRequest)->andReturn($refundResponse);
        $gateway->shouldReceive('verifyCallback')->once()->with($callbackPayload)->andReturn(true);

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->times(8)->with(Provider::KBZPAY)->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        $wrapper = new MyanmarPayments($manager);

        expect($wrapper->createPayment($paymentRequest, Provider::KBZPAY))->toBe($paymentResponse)
            ->and($wrapper->queryStatus('ORD200', Provider::KBZPAY))->toBe($paymentResponse)
            ->and($wrapper->createMmqr($mmqrRequest, Provider::KBZPAY))->toBe($mmqrResponse)
            ->and($wrapper->refund($refundRequest, Provider::KBZPAY))->toBe($refundResponse)
            ->and($wrapper->verifyCallback($callbackPayload, Provider::KBZPAY))->toBeTrue()
            ->and($wrapper->supportsMmqr(Provider::KBZPAY))->toBeTrue()
            ->and($wrapper->supportsRefunds(Provider::KBZPAY))->toBeTrue()
            ->and($wrapper->supportsCallbackVerification(Provider::KBZPAY))->toBeTrue();
    });

    it('accepts Provider enum values', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);

        $factory = Mockery::mock(GatewayContract::class);
        $factory->shouldReceive('make')->once()->with(Provider::KBZPAY)->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        $wrapper = new MyanmarPayments($manager);

        expect($wrapper->provider(Provider::KBZPAY))->toBe($gateway);
    });
});
