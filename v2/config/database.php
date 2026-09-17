<?php
declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env_int('DB_PORT', 3306),
    'database' => env('DB_NAME', 'gym_system_v2'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'connect_timeout' => env_int('DB_CONNECT_TIMEOUT', 5),
];
