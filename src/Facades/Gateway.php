<?php

namespace Hak\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class Gateway extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'gateway';
    }
}