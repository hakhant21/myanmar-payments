<?php

declare(strict_types=1);

namespace Hakhant\Payments\Providers;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayContract;
use Hakhant\Payments\Infrastructure\Factories\GatewayFactory;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
use Hakhant\Payments\Infrastructure\Providers\AYA\AYAMapper;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPayMapper;
use Hakhant\Payments\Infrastructure\Providers\KBZPay\KBZPaySignature;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PKeyJwt;
use Hakhant\Payments\Infrastructure\Providers\TwoC2P\TwoC2PMapper;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyHash;
use Hakhant\Payments\Infrastructure\Providers\WaveMoney\WaveMoneyMapper;
use Hakhant\Payments\Support\Idempotency\CallbackIdempotencyGuard;
use Hakhant\Payments\Support\Logging\PaymentLogger;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class MyanmarPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/myanmar-payments.php', 'myanmar-payments');

        $this->app->singleton(HttpClient::class, static fn (): HttpClient => new HttpClient);

        $this->app->singleton(GatewayContract::class, function (): GatewayContract {
            /** @var array<string, mixed> $config */
            $config = (array) config('myanmar-payments', []);

            return new GatewayFactory(
                app(HttpClient::class),
                $config,
                app(TwoC2PMapper::class),
                app(TwoC2PJwt::class),
                app(TwoC2PKeyJwt::class),
                app(AYAMapper::class),
                app(KBZPayMapper::class),
                app(KBZPaySignature::class),
                app(WaveMoneyMapper::class),
                app(WaveMoneyHash::class),
            );
        });

        $this->app->singleton(PaymentManager::class, function (): PaymentManager {
            $defaultProvider = (string) config('myanmar-payments.default', 'kbzpay');

            return new PaymentManager(app(GatewayContract::class), $defaultProvider);
        });

        $this->app->singleton(PaymentLogger::class, static fn (): PaymentLogger => new PaymentLogger(app(LoggerInterface::class)));

        $this->app->singleton(CallbackIdempotencyGuard::class, static fn (): CallbackIdempotencyGuard => new CallbackIdempotencyGuard(app(CacheRepository::class)));

        $this->app->alias(PaymentManager::class, 'myanmar-payments');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/myanmar-payments.php' => config_path('myanmar-payments.php'),
        ], 'myanmar-payments-config');
    }
}
