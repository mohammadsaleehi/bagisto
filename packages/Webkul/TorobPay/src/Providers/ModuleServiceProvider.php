<?php

namespace Webkul\TorobPay\Providers;

use Konekt\Concord\BaseModuleServiceProvider;
use Webkul\TorobPay\Models\TorobPay;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        TorobPay::class,
    ];
}