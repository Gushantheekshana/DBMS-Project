<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap/app.php';

// Compatibility for legacy pages while they are migrated to prepared repositories.
$conn = db();
require_role('SUPER_ADMIN');
function sanitize(mysqli $conn, mixed $val): string { return $conn->real_escape_string(trim((string)$val)); }
