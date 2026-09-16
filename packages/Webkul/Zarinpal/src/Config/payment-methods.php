<?php

return [
    'zarinpal' => [
        'code'              => 'zarinpal',
        'title'             => 'پرداخت امن زرین پال',
        'description'       => 'پرداخت توسط کلیه کارتهای عضو شبکه شتاب با پرداخت امن زرین پال',
        'class'             => 'Webkul\\Zarinpal\\Payment\\ZarinpalPayment',

        'active'            => true,
        'sandbox'           => false,
        'sort'              => 1,

        'api_base_url'      => 'https://api.zarinpal.com/pg/v4/payment',
        'sandbox_base_url'  => 'https://payment.zarinpal.com/pg/v4/payment',

        'request_url'       => 'request.json',
        'verify_url'        => 'verify.json',

        'redirect_url'      => 'https://www.zarinpal.com/pg/StartPay/',
        'callback_url'      => '',
    ],
];