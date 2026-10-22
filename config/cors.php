<?php

/*
| Cross-Origin Resource Sharing for the Angular client (served from another origin in development).
| `exposed_headers` lets the browser read the download file name, the request id and Retry-After.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'X-Request-Id', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
