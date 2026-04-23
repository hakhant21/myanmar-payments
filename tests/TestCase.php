<?php

declare(strict_types=1);

namespace Hakhant\Payments\Tests;

use Hakhant\Payments\Laravel\MyanmarPaymentsServiceProvider;
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
        $app['config']->set('myanmar-payments.providers.kbzpay', [
            'merchant_code' => 'TEST_MERCH',
            'merchant_id' => 'TEST_MERCH',
            'app_id' => 'TEST_APP_ID',
            'secret' => 'TEST_SECRET',
            'trade_type' => 'APP',
            'notify_url' => 'https://example.test/callback',
            'sub_type' => '',
            'sub_identifier_type' => '',
            'sub_identifier' => '',
            'endpoints' => [
                'precreate' => 'https://api.test/precreate',
                'queryorder' => 'https://api.test/queryorder',
                'refund' => 'https://api.test/refund',
                'mmqr' => 'https://api.test/mmqr',
            ],
            'versions' => [
                'precreate' => '1.0',
                'queryorder' => '3.0',
                'refund' => '1.0',
                'mmqr' => '1.0',
            ],
            'timeout' => 10,
        ]);
    }
}
