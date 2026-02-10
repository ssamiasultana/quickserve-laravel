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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'api_url' => env('SSLCOMMERZ_API_URL', 'https://sandbox.sslcommerz.com'),
        'success_url' => env('SSLCOMMERZ_SUCCESS_URL', url('/api/payments/sslcommerz/success')),
        'fail_url' => env('SSLCOMMERZ_FAIL_URL', url('/api/payments/sslcommerz/fail')),
        'cancel_url' => env('SSLCOMMERZ_CANCEL_URL', url('/api/payments/sslcommerz/cancel')),
        'ipn_url' => env('SSLCOMMERZ_IPN_URL', url('/api/payments/sslcommerz/ipn')),
    ],

];
