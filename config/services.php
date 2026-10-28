<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'exchange_api' => [
        'default' => 'exchange_rates',

        'fixer' => [
            'key' => env('FIXER_API_KEY'),
            'class' => 'Webkul\Core\Helpers\Exchange\FixerExchange',
        ],

        'exchange_rates' => [
            'key' => env('EXCHANGE_RATES_API_KEY'),
            'class' => 'Webkul\Core\Helpers\Exchange\ExchangeRates',
        ],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_CALLBACK_URL'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_CALLBACK_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALLBACK_URL'),
    ],

    'linkedin-openid' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_CALLBACK_URL'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_CALLBACK_URL'),
    ],
    'tipax' => [
        'base_url'                   => env('TIPAX_BASE_URL', 'https://omtestapi.tipax.ir'),
        'username'                   => env('TIPAX_USERNAME'),
        'password'                   => env('TIPAX_PASSWORD'),
        'timeout'                    => (int) env('TIPAX_TIMEOUT', 15),
        'origin_city_id'              => (int) env('TIPAX_ORIGIN_CITY_ID', 1911),
        'service_id'                  => (int) env('TIPAX_SERVICE_ID', 1),
        'packing_id'                  => (int) env('TIPAX_PACKING_ID', 7),
        'package_content_id'          => (int) env('TIPAX_PACKAGE_CONTENT_ID', 520),
        'pack_type'                   => (int) env('TIPAX_PACK_TYPE', 20),
        'payment_type'                => (int) env('TIPAX_PAYMENT_TYPE', 20),
        'pickup_type'                 => (int) env('TIPAX_PICKUP_TYPE', 0),
        'distribution_type'           => (int) env('TIPAX_DISTRIBUTION_TYPE', 10),
        'product_weight_unit'         => env('TIPAX_PRODUCT_WEIGHT_UNIT', 'kg'),
        'default_item_weight_kg'      => (float) env('TIPAX_DEFAULT_ITEM_WEIGHT_KG', 0.25),
        'package_value_multiplier'    => (float) env('TIPAX_PACKAGE_VALUE_MULTIPLIER', 1),
        'amount_multiplier'           => (float) env('TIPAX_AMOUNT_MULTIPLIER', 1),
        'small_box_max_kg'             => (float) env('TIPAX_SMALL_BOX_MAX_KG', 2),
        'small_length'                => (int) env('TIPAX_SMALL_LENGTH', 30),
        'small_width'                 => (int) env('TIPAX_SMALL_WIDTH', 20),
        'small_height'                => (int) env('TIPAX_SMALL_HEIGHT', 15),
        'medium_box_max_kg'            => (float) env('TIPAX_MEDIUM_BOX_MAX_KG', 5),
        'medium_length'               => (int) env('TIPAX_MEDIUM_LENGTH', 40),
        'medium_width'                => (int) env('TIPAX_MEDIUM_WIDTH', 30),
        'medium_height'               => (int) env('TIPAX_MEDIUM_HEIGHT', 20),
        'large_length'                => (int) env('TIPAX_LARGE_LENGTH', 60),
        'large_width'                 => (int) env('TIPAX_LARGE_WIDTH', 40),
        'large_height'                => (int) env('TIPAX_LARGE_HEIGHT', 30),
    ],
];
