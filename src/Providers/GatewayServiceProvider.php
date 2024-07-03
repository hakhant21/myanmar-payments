<?php

namespace Hak\Payments\Providers;

use Hak\Payments\Gateway;
use Illuminate\Support\ServiceProvider;

class GatewayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'convert');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'convert');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('gateway.php'),
            ], 'gateway');
        }
    }

     /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'gateway');

        // Register the main class to use with the facade
        $this->app->singleton('gateway', function () {
            return new Gateway;
        });
    }
}