<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'mercadopago' => [
        'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
        'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
        'access_token' => env('MERCADO_PAGO_TEST_USER_TOKEN', env('MERCADO_PAGO_ACCESS_TOKEN')),
        'user_id' => env('MERCADO_PAGO_TEST_USER_ID', env('MERCADO_PAGO_USER_ID')),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'pos_id' => env('MERCADO_PAGO_DEFAULT_POS_ID', 'SUC01POS01'),
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
    ],

];
