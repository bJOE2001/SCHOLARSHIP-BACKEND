<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:9001',
        'http://127.0.0.1:9001',
        'http://localhost:900',
        'http://127.0.0.1:900',
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => false,
];
