<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        ...array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:9001,http://127.0.0.1:9001,http://localhost:9000,http://127.0.0.1:9000')))),
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => false,
];
