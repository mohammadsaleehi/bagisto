<?php

use Illuminate\Support\Facades\Route;
use Webkul\Torob\Http\Controllers\ProductController;
use Webkul\Torob\Http\Middleware\TorobAuthenticate;

Route::prefix('torob_api/v3')
    ->middleware(TorobAuthenticate::class)
    ->group(function () {
        Route::post('products', [ProductController::class, 'index']);
    });