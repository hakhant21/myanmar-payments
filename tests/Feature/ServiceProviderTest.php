<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Infrastructure\Factories\DefaultGatewayFactory;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyGateway;
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

    it('resolves 2c2p gateway via PaymentManager', function (): void {
        config()->set('myanmar-payments.providers.2c2p', [
            'merchant_id' => 'JT01',
            'secret_key' => '0123456789abcdef0123456789abcdef',
            'merchant_private_key' => 'merchant-private',
            'two_c2p_public_key' => '2c2p-public',
            'locale' => 'en',
            'payment_description' => 'Payment',
            'maintenance_version' => '4.3',
            'endpoints' => [
                'payment_token' => 'https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken',
                'transaction_status' => 'https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus',
                'refund' => 'https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action',
            ],
            'timeout' => 10,
        ]);

        $manager = app(PaymentManager::class);
        expect($manager->provider('2c2p'))->toBeInstanceOf(TwoC2PGateway::class);
    });

    it('resolves wavemoney gateway via PaymentManager', function (): void {
        config()->set('myanmar-payments.providers.wavemoney', [
            'merchant_id' => 'WAVE_MERCHANT',
            'secret_key' => 'wave_secret',
            'merchant_name' => 'Wave Merchant',
            'payment_description' => 'Payment',
            'time_to_live_in_seconds' => 600,
            'endpoints' => [
                'payment' => 'https://testpayments.wavemoney.io:8107/payment',
                'authenticate' => 'https://testpayments.wavemoney.io/authenticate',
            ],
            'timeout' => 10,
        ]);

        $manager = app(PaymentManager::class);
        expect($manager->provider('wavemoney'))->toBeInstanceOf(WaveMoneyGateway::class);
    });
});
