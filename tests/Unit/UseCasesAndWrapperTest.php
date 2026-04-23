<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Application\UseCases\CreatePayment;
use Hakhant\Payments\Application\UseCases\QueryPaymentStatus;
use Hakhant\Payments\Application\UseCases\RefundPayment;
use Hakhant\Payments\Application\UseCases\VerifyCallback;
use Hakhant\Payments\Contracts\CanRefundPayment;
use Hakhant\Payments\Contracts\CanVerifyCallback;
use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Contracts\PaymentGateway;
use Hakhant\Payments\Domain\DTO\CallbackPayload;
use Hakhant\Payments\Domain\DTO\PaymentRequest;
use Hakhant\Payments\Domain\DTO\PaymentResponse;
use Hakhant\Payments\Domain\DTO\RefundRequest;
use Hakhant\Payments\Domain\DTO\RefundResponse;
use Hakhant\Payments\Domain\Enums\PaymentStatus;
use Hakhant\Payments\Domain\Exceptions\ProviderException;
use Hakhant\Payments\MyanmarPayments;

function makePaymentManagerWithGateway(PaymentGateway $gateway): PaymentManager
{
    $factory = Mockery::mock(GatewayFactory::class);
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
});

describe('MyanmarPayments wrapper', function (): void {
    it('delegates provider selection to PaymentManager', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);

        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('make')->once()->with('kbzpay')->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');

        $wrapper = new MyanmarPayments($manager);

        expect($wrapper->provider('kbzpay'))->toBe($gateway);
    });
});
