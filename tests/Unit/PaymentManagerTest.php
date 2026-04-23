<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Contracts\PaymentGateway;

afterEach(fn () => Mockery::close());

describe('PaymentManager::provider()', function (): void {
    it('returns gateway for the default provider when no argument given', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        $result = $manager->provider();

        expect($result)->toBe($gateway);
    });

    it('returns gateway for an explicit provider argument', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        $result = $manager->provider('kbzpay');

        expect($result)->toBe($gateway);
    });

    it('uses explicit provider argument over default', function (): void {
        $gatewayKbz = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gatewayKbz);

        $manager = new PaymentManager($factory, 'some_default');
        $result = $manager->provider('kbzpay');

        expect($result)->toBe($gatewayKbz);
    });

    it('delegates to factory with null defaulting to default provider', function (): void {
        $gateway = Mockery::mock(PaymentGateway::class);
        $factory = Mockery::mock(GatewayFactory::class);
        $factory->shouldReceive('make')->with('kbzpay')->once()->andReturn($gateway);

        $manager = new PaymentManager($factory, 'kbzpay');
        expect($manager->provider(null))->toBe($gateway);
    });
});
