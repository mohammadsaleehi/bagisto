<?php

namespace Webkul\TipaxShipping\Providers;

use Illuminate\Support\ServiceProvider;

class TipaxShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/carriers.php', 'carriers');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/system.php', 'core');
    }
}
