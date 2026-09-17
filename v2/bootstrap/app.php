<?php
declare(strict_types=1);

const V2_ROOT = __DIR__ . '/..';

require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'GymPro\\V2\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = V2_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/** Load a small dotenv-compatible file without requiring Composer. */
function load_environment_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read environment file: ' . $path);
    }

    foreach ($lines as $number => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        if (!str_contains($line, '=')) {
            throw new RuntimeException(sprintf('Invalid .env entry on line %d.', $number + 1));
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            throw new RuntimeException(sprintf('Invalid environment key on line %d.', $number + 1));
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

load_environment_file(V2_ROOT . '/.env');

date_default_timezone_set((string) config('app.timezone', 'UTC'));

if (PHP_SAPI !== 'cli') {
    $sameSite = (string) config('app.session.same_site', 'Lax');
    if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
        throw new RuntimeException('SESSION_SAME_SITE must be Lax, Strict, or None.');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name((string) config('app.session.name', 'gym_v2_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (bool) config('app.session.secure', false),
        'httponly' => true,
        'samesite' => $sameSite,
    ]);
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
