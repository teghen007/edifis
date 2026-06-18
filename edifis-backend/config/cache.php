<?php

return [
    'cache' => [
        'default' => env('CACHE_STORE', 'redis'),
        'stores' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
                'lock_connection' => 'default',
            ],
        ],
    ],
];
