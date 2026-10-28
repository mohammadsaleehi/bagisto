<?php

return [
    'enabled' => true,

    'api_version' => 3,

    /*
    |--------------------------------------------------------------------------
    | Product API
    |--------------------------------------------------------------------------
    |
    | Base64 encoded Ed25519 public key provided by Torob.
    |
    */
    'api_public_key' => env('TOROB_API_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Product Webhook
    |--------------------------------------------------------------------------
    |
    | Dedicated webhook token provided by Torob support.
    |
    */
    'webhook_token' => env('TOROB_WEBHOOK_TOKEN'),
];