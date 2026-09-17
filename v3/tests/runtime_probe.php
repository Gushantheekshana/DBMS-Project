<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$reflection = new ReflectionClass(GymPro\V2\Services\AuthService::class);
echo json_encode([
    'frontend' => GYMPRO_FRONTEND,
    'backend_root' => str_replace('\\', '/', GYMPRO_BACKEND_ROOT),
    'ui_root' => str_replace('\\', '/', ui_root()),
    'base_url' => base_url('admin/index.php'),
    'session_name' => config('app.session.name'),
    'database' => config('database.database'),
    'auth_service' => str_replace('\\', '/', (string) $reflection->getFileName()),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
