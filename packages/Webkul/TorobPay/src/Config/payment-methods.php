<?php

return [
    'torobpay' => [
        /*
        |--------------------------------------------------------------------------
        | Basic Payment Method Configuration
        |--------------------------------------------------------------------------
        */

        'code' => 'torobpay',

        'title' => 'پرداخت اعتباری ترب‌پی',

        'description' => 'پرداخت اعتباری از طریق ترب‌پی',

        'class' => 'Webkul\\TorobPay\\Payment\\TorobPayPayment',

        'active' => true,

        'sort' => 2,

        /*
        |--------------------------------------------------------------------------
        | TorobPay API
        |--------------------------------------------------------------------------
        */

        'api_url' => 'https://cpg.torobpay.com',

        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        'client_id' => '',

        'client_secret' => '',

        'username' => 'z',

        'password' => '',

        /*
        |--------------------------------------------------------------------------
        | Currency
        |--------------------------------------------------------------------------
        |
        | T = Toman in Bagisto
        | R = Rial in Bagisto
        |
        | TorobPay API always expects Rial.
        | The service/controller converts Toman to Rial when necessary.
        |
        */

        'currency' => 'T',

        'callback_url' => 'https://www.zephyras.ir//torobpay/callback',
    ],
];