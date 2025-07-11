<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Docker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to Docker deployment
    |
    */

    'health_check' => [
        'enabled' => env('HEALTH_CHECK_ENABLED', true),
        'endpoints' => [
            'database' => true,
            'redis' => true,
            'queue' => true,
        ],
    ],

    'secrets' => [
        'db_password_file' => env('DB_PASSWORD_FILE'),
        'redis_password_file' => env('REDIS_PASSWORD_FILE'),
        'jwt_secret_file' => env('JWT_SECRET_FILE'),
    ],

    'monitoring' => [
        'metrics_enabled' => env('METRICS_ENABLED', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // ms
    ],
];