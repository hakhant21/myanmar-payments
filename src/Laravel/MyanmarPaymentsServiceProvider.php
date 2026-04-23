<?php

declare(strict_types=1);

namespace Hakhant\Payments\Laravel;

use Hakhant\Payments\Application\PaymentManager;
use Hakhant\Payments\Contracts\GatewayFactory;
use Hakhant\Payments\Infrastructure\Factories\DefaultGatewayFactory;
use Hakhant\Payments\Infrastructure\Http\HttpClient;
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

        $this->app->singleton(GatewayFactory::class, function (): GatewayFactory {
            /** @var array<string, mixed> $config */
            $config = (array) config('myanmar-payments', []);

            return new DefaultGatewayFactory(
                app(HttpClient::class),
                $config,
            );
        });

        $this->app->singleton(PaymentManager::class, function (): PaymentManager {
            $defaultProvider = (string) config('myanmar-payments.default', 'kbzpay');

            return new PaymentManager(app(GatewayFactory::class), $defaultProvider);
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
