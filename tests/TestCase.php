<?php

declare(strict_types=1);

namespace Hakhant\Payments\Tests;

use Hakhant\Payments\Providers\MyanmarPaymentsServiceProvider;
use Hakhant\Payments\Tests\Support\ProviderConfig;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MyanmarPaymentsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('myanmar-payments.default', 'kbzpay');
        $app['config']->set('myanmar-payments.providers.aya', ProviderConfig::aya());
        $app['config']->set('myanmar-payments.providers.kbzpay', ProviderConfig::kbzpay());
    }
}
