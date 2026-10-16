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

    // API MOREH: consulta de planes funerarios (tabla de contratos) usados como garantía
    'moreh' => [
        'url' => env('MOREH_API_URL'),
        'key' => env('MOREH_SERVICE_KEY'),
        'sucursal' => env('MOREH_SUCURSAL', 'CULIACAN'),
        'timeout' => env('MOREH_TIMEOUT', 20),
        // Ruta a un cacert.pem (útil en Windows/XAMPP cuando PHP no trae certificados raíz)
        'ca_bundle' => env('MOREH_CA_BUNDLE'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
