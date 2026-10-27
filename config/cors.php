<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:2000',
        'http://127.0.0.1:2000',
        'http://localhost:2050',
        'http://127.0.0.1:2050',
        'https://printexpress.com.eg',
        'https://admin.printexpress.com.eg',
        'https://api.printexpress.com.eg',
        'http://printexpress.com.eg',
        'http://admin.printexpress.com.eg',
        'http://api.printexpress.com.eg',
        env('FRONTEND_URL', 'http://localhost:2000'),
        str_replace('localhost', '127.0.0.1', env('FRONTEND_URL', 'http://localhost:2000')),
        env('DASHBOARD_URL', 'http://localhost:2050'),
        str_replace('localhost', '127.0.0.1', env('DASHBOARD_URL', 'http://localhost:2050'))
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*', 'X-Session-ID', 'X-Store-ID'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
