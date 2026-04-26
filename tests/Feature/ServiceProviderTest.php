<?php

declare(strict_types=1);

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayContract;
use Hakhant\Payments\Facades\MyanmarPayments;
use Hakhant\Payments\Infrastructure\Factories\GatewayFactory;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAGateway;
use Hakhant\Payments\Infrastructure\Gateways\AYA\AYAMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayGateway;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Gateways\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PGateway;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PKeyJwt;
use Hakhant\Payments\Infrastructure\Gateways\TwoC2P\TwoC2PMapper;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyGateway;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyHash;
use Hakhant\Payments\Infrastructure\Gateways\WaveMoney\WaveMoneyMapper;
use Hakhant\Payments\Support\Idempotency\CallbackIdempotencyGuard;
use Hakhant\Payments\Support\Logging\PaymentLogger;
use Hakhant\Payments\Tests\Support\ProviderConfig;

describe('ServiceProvider bindings', function (): void {
    it('binds PaymentManager in the container', function (): void {
        expect(app(PaymentManager::class))->toBeInstanceOf(PaymentManager::class);
    });

    it('binds GatewayContract to the GatewayFactory implementation', function (): void {
        expect(app(GatewayContract::class))->toBeInstanceOf(GatewayFactory::class);
    });

    it('resolves shared stateless provider collaborators from the container', function (): void {
        expect(app(AYAMapper::class))->toBeInstanceOf(AYAMapper::class)
            ->and(app(KBZPayMapper::class))->toBeInstanceOf(KBZPayMapper::class)
            ->and(app(KBZPaySignature::class))->toBeInstanceOf(KBZPaySignature::class)
            ->and(app(TwoC2PMapper::class))->toBeInstanceOf(TwoC2PMapper::class)
            ->and(app(TwoC2PJwt::class))->toBeInstanceOf(TwoC2PJwt::class)
            ->and(app(TwoC2PKeyJwt::class))->toBeInstanceOf(TwoC2PKeyJwt::class)
            ->and(app(WaveMoneyMapper::class))->toBeInstanceOf(WaveMoneyMapper::class)
            ->and(app(WaveMoneyHash::class))->toBeInstanceOf(WaveMoneyHash::class)
            ->and(app(PaymentLogger::class))->toBeInstanceOf(PaymentLogger::class)
            ->and(app(CallbackIdempotencyGuard::class))->toBeInstanceOf(CallbackIdempotencyGuard::class);
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

    it('resolves aya gateway via PaymentManager', function (): void {
        config()->set('myanmar-payments.providers.aya', ProviderConfig::aya());

        $manager = app(PaymentManager::class);
        expect($manager->provider('aya'))->toBeInstanceOf(AYAGateway::class);
    });

    it('resolves 2c2p gateway via PaymentManager', function (): void {
        config()->set('myanmar-payments.providers.2c2p', ProviderConfig::twoC2p());

        $manager = app(PaymentManager::class);
        expect($manager->provider('2c2p'))->toBeInstanceOf(TwoC2PGateway::class);
    });

    it('resolves wavemoney gateway via PaymentManager', function (): void {
        config()->set('myanmar-payments.providers.wavemoney', ProviderConfig::waveMoney());

        $manager = app(PaymentManager::class);
        expect($manager->provider('wavemoney'))->toBeInstanceOf(WaveMoneyGateway::class);
    });
});
