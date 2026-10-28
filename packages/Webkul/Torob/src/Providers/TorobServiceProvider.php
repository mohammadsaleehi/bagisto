<?php

namespace Webkul\Torob\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class TorobServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(
            __DIR__.'/../Routes/api.php'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../Config/system.php',
            'torob'
        );

        RateLimiter::for(
            'torob-webhook',
            fn () => Limit::perMinute(20)->by('zephyras.ir')
        );
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->register(
            EventServiceProvider::class
        );
    }
}