<?php

namespace Hak\Payments\Tests;

use Hak\Payments\Providers\GatewayServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GatewayServiceProvider::class
        ];
    }
}
