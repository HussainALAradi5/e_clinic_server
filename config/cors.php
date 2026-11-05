<?php

/*
| Cross-Origin Resource Sharing for the Angular client (served from another origin in development).
| `exposed_headers` lets the browser read the download file name, the request id and Retry-After.
*/

return [

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    // Comma-separated; deployments set the web client's origin (`*` only for local development).
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'X-Request-Id', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
