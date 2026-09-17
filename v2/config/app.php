<?php
declare(strict_types=1);

return [
    'env' => env('APP_ENV', 'production'),
    'debug' => env_bool('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost/gym_system/v2'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY'),
    'session' => [
        'name' => env('SESSION_NAME', 'gym_v2_session'),
        'secure' => env_bool('SESSION_SECURE', false),
        'same_site' => env('SESSION_SAME_SITE', 'Lax'),
    ],
];
