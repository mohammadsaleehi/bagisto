<?php

use Illuminate\Support\Facades\Route;
use Webkul\TorobPay\Http\Controllers\TorobPayController;

Route::middleware('web')->prefix('torobpay')->group(function () {
    Route::get(
        'redirect',
        [TorobPayController::class, 'redirect']
    )->name('torobpay.payment.redirect');

    Route::match(
        ['GET', 'POST'],
        'callback',
        [TorobPayController::class, 'callback']
    )->name('torobpay.payment.callback');
});