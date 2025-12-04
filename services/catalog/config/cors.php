<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration allows the Vue frontend dev server (http://localhost:5173)
    | to call the Catalog JSON APIs exposed via Nginx at http://localhost:8080.
    | In production, you can set FRONTEND_URL to your real SPA origin.
    |
    */

    'paths' => ['catalog/api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
