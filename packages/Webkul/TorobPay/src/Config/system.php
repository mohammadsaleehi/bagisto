<?php

return [
    [
        'key'  => 'sales.payment_methods.torobpay',
        'name' => 'TorobPay',
        'info' => 'تنظیمات پرداخت اعتباری ترب‌پی',
        'sort' => 2,

        'fields' => [
            [
                'name'          => 'active',
                'title'         => 'فعال',
                'type'          => 'boolean',
                'default_value' => true,
                'channel_based' => true,
                'locale_based'  => false,
            ],

            [
                'name'          => 'title',
                'title'         => 'عنوان',
                'type'          => 'text',
                'default_value' => 'پرداخت اعتباری ترب‌پی',
                'channel_based' => true,
                'locale_based'  => true,
            ],

            [
                'name'          => 'description',
                'title'         => 'توضیحات',
                'type'          => 'textarea',
                'default_value' => 'پرداخت اعتباری از طریق ترب‌پی',
                'channel_based' => true,
                'locale_based'  => true,
            ],

            [
                'name'          => 'image',
                'title'         => 'لوگو',
                'type'          => 'image',
                'channel_based' => true,
                'locale_based'  => false,
                'validation'    => 'mimes:bmp,jpeg,jpg,png,webp',
            ],

            [
                'name'          => 'client_id',
                'title'         => 'Client ID',
                'type'          => 'text',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'client_secret',
                'title'         => 'Client Secret',
                'type'          => 'password',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'username',
                'title'         => 'نام کاربری',
                'type'          => 'text',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'password',
                'title'         => 'رمز عبور',
                'type'          => 'password',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'api_url',
                'title'         => 'آدرس API',
                'type'          => 'text',
                'default_value' => 'https://cpg.torobpay.com',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1|url',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'currency',
                'title'         => 'واحد مبلغ فروشگاه',
                'type'          => 'select',
                'default_value' => 'T',
                'options'       => [
                    [
                        'title' => 'تومان',
                        'value' => 'T',
                    ],
                    [
                        'title' => 'ریال',
                        'value' => 'R',
                    ],
                ],
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => false,
                'locale_based'  => false,
            ],

            [
                'name'          => 'sort',
                'title'         => 'ترتیب نمایش',
                'type'          => 'text',
                'default_value' => '2',
                'validation'    => 'required|numeric|min:0',
                'channel_based' => false,
                'locale_based'  => false,
            ],
        ],
    ],
];