<?php

return [
    'paths'                    => ['*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => ['*'], // En prod: ['https://tu-frontend.com']
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];