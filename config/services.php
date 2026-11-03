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

    'forecasting' => [
        'enabled' => (bool) env('FORECASTING_ML_ENABLED', true),
        'driver' => env('FORECASTING_DRIVER', 'auto'),
        'python_path' => env('FORECASTING_PYTHON_PATH', 'python'),
        'script_path' => env('FORECASTING_SCRIPT_PATH', base_path('../SCHOLARSHIP-FORECASTING/forecast_service.py')),
        'risk_script_path' => env('FORECASTING_RISK_SCRIPT_PATH', base_path('../SCHOLARSHIP-FORECASTING/risk_service.py')),
        'service_url' => env('FORECASTING_SERVICE_URL', 'http://127.0.0.1:8001'),
        'timeout_seconds' => (int) env('FORECASTING_TIMEOUT_SECONDS', 5),
    ],

];
