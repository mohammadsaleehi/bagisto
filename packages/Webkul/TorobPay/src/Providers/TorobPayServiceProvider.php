<?php

namespace Webkul\TorobPay\Providers;

use Illuminate\Support\ServiceProvider;

class TorobPayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php',
            'core'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        /*
         * Register package routes.
         */
        $this->loadRoutesFrom(
            dirname(__DIR__) . '/Routes/routes.php'
        );

        /*
         * Register package translations.
         */
        $this->loadTranslationsFrom(
            dirname(__DIR__) . '/Resources/lang',
            'torobpay'
        );

        /*
         * Register package migrations.
         */
        $this->loadMigrationsFrom(
            dirname(__DIR__) . '/Database/Migrations'
        );

        /*
         * Register package event and Concord providers.
         */
        $this->app->register(
            EventServiceProvider::class
        );

        $this->app->register(
            ModuleServiceProvider::class
        );
    }

    /**
     * Register payment method configuration.
     */
    protected function registerConfig(): void
    {
        $config = require dirname(__DIR__)
            . '/Config/payment-methods.php';

        config([
            'payment_methods' => array_merge(
                config('payment_methods', []),
                $config
            ),
        ]);
    }
}