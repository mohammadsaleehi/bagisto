<?php

namespace Webkul\Zarinpal\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\Controller;

class ZarinpalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__ . '/../Http/routes.php';

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'zarinpal');

        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'zarinpal');

        $this->app->register(EventServiceProvider::class);

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    $this->registerConfig();

    $this->mergeConfigFrom(
        dirname(__DIR__) . '/Config/system.php',
        'core'
    );
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
    $config = require dirname(__DIR__) . '/Config/payment-methods.php';

    config([
        'payment_methods' => array_merge(
            config('payment_methods', []),
            $config
        ),
    ]);
    }
}
