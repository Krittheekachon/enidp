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

    'kku_sso' => [
        'enabled' => (bool) env('KKU_SSO_ENABLED', false),
        'app_id' => env('KKU_SSO_APP_ID'),
        'client_id' => env('KKU_SSO_CLIENT_ID'),
        'client_secret' => env('KKU_SSO_CLIENT_SECRET'),
        'login_url' => env('KKU_SSO_LOGIN_URL', 'https://ssonext.kku.ac.th/login'),
        'token_url' => env('KKU_SSO_TOKEN_URL', 'https://ssonext-api.kku.ac.th/auth.token'),
        'redirect_url' => env(
            'KKU_SSO_REDIRECT_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/kku/callback'
        ),
        'logout_url' => env('KKU_SSO_LOGOUT_URL', 'https://ssonext.kku.ac.th/logout'),
        'logout_redirect_url' => env(
            'KKU_SSO_LOGOUT_REDIRECT_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/')
        ),
    ],

];
