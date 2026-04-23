<?php

declare(strict_types=1);

namespace Hakhant\Payments\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

final class MyanmarPayments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'myanmar-payments';
    }
}
