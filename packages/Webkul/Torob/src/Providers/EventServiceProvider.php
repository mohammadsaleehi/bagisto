<?php

namespace Webkul\Torob\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Webkul\Torob\Listeners\SendProductWebhook;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'catalog.product.create.after' => [
            [SendProductWebhook::class, 'handle'],
        ],

        'catalog.product.update.after' => [
            [SendProductWebhook::class, 'handle'],
        ],
    ];
}
