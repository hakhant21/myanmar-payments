<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Infrastructure\Factories\DefaultGatewayFactory;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayGateway;
use Hakhant\Payments\Laravel\Facades\MyanmarPayments;

describe('ServiceProvider bindings', function (): void {
    it('binds PaymentManager in the container', function (): void {
        expect(app(PaymentManager::class))->toBeInstanceOf(PaymentManager::class);
    });

    it('binds GatewayFactory as DefaultGatewayFactory', function (): void {
        expect(app(GatewayFactory::class))->toBeInstanceOf(DefaultGatewayFactory::class);
    });

    it('aliases myanmar-payments to PaymentManager', function (): void {
        expect(app('myanmar-payments'))->toBeInstanceOf(PaymentManager::class);
    });

    it('facade accessor resolves correctly', function (): void {
        expect(MyanmarPayments::getFacadeRoot())->toBeInstanceOf(PaymentManager::class);
    });

    it('resolves kbzpay gateway via PaymentManager', function (): void {
        $manager = app(PaymentManager::class);
        expect($manager->provider('kbzpay'))->toBeInstanceOf(KBZPayGateway::class);
    });
});
