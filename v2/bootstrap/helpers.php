<?php
declare(strict_types=1);

/** Escape untrusted output for an HTML text or attribute context. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);

    return ($value === false || $value === null) ? $default : (string) $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null || trim($value) === '') {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($parsed === null) {
        throw new RuntimeException(sprintf('Environment variable %s must be a boolean.', $key));
    }

    return $parsed;
}

function env_int(string $key, int $default): int
{
    $value = env($key);
    if ($value === null || trim($value) === '') {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false) {
        throw new RuntimeException(sprintf('Environment variable %s must be an integer.', $key));
    }

    return $parsed;
}

function config(string $key, mixed $default = null): mixed
{
    static $files = [];
    $segments = explode('.', $key);
    $file = array_shift($segments);

    if ($file === null || $file === '') {
        return $default;
    }

    if (!array_key_exists($file, $files)) {
        $path = V2_ROOT . '/config/' . $file . '.php';
        $files[$file] = is_file($path) ? require $path : [];
    }

    $value = $files[$file];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function base_url(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path, int $status = 302): never
{
    $location = preg_match('~^https?://~i', $path) ? $path : base_url($path);
    header('Location: ' . $location, true, $status);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    $stored = $_SESSION['_csrf'] ?? '';
    if (!is_string($submitted) || !is_string($stored) || $stored === '' || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        exit('Invalid or expired request token.');
    }
}

function db(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = mysqli_init();
    if ($connection === false) {
        throw new RuntimeException('Unable to initialize the database client.');
    }
    $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) config('database.connect_timeout', 5));
    $connection->real_connect(
        (string) config('database.host'),
        (string) config('database.username'),
        (string) config('database.password'),
        (string) config('database.database'),
        (int) config('database.port')
    );
    $connection->set_charset((string) config('database.charset', 'utf8mb4'));

    return $connection;
}

function db_all(string $sql, string $types = '', array $parameters = []): array
{
    $statement = db()->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$parameters);
    }
    $statement->execute();

    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function db_one(string $sql, string $types = '', array $parameters = []): ?array
{
    return db_all($sql, $types, $parameters)[0] ?? null;
}

function db_execute(string $sql, string $types = '', array $parameters = []): mysqli_stmt
{
    $statement = db()->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$parameters);
    }
    $statement->execute();

    return $statement;
}

function transaction(callable $callback): mixed
{
    $connection = db();
    $connection->begin_transaction();
    try {
        $result = $callback($connection);
        $connection->commit();
        return $result;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}

/** Return the authenticated account, or null for a guest. */
function current_user(): ?array
{
    static $cachedId = null;
    static $cachedUser = null;

    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id <= 0) {
        $cachedId = 0;
        $cachedUser = null;
        return null;
    }
    if ($cachedId !== $id) {
        $cachedId = $id;
        $cachedUser = db_one(
            "SELECT UserAccountID,Email,Role,Status,EmailVerifiedAt,LastLoginAt,CreatedAt FROM USER_ACCOUNT WHERE UserAccountID=?",
            'i',
            [$id]
        );
        if ($cachedUser === null || ($cachedUser['Status'] ?? '') !== 'ACTIVE') {
            unset($_SESSION['user_id']);
            $cachedUser = null;
        }
    }

    return $cachedUser;
}

function require_login(): void
{
    if (current_user() === null) {
        flash('warning', 'Sign in to continue.');
        redirect('auth/login.php');
    }
}

function require_guest(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(account_home($user));
    }
}

/** Require one of the canonical account roles. */
function require_role(string ...$roles): void
{
    require_login();
    $allowed = ['ADMIN', 'RECEPTIONIST', 'TRAINER', 'MEMBER'];
    foreach ($roles as $role) {
        if (!in_array($role, $allowed, true)) {
            throw new InvalidArgumentException('Unknown account role: ' . $role);
        }
    }
    if (!in_array((string) current_user()['Role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        $pageSubtitle = 'This area is not available to your account.';
        if (is_file(V2_ROOT . '/includes/header.php')) {
            include V2_ROOT . '/includes/header.php';
            echo '<section class="empty-state"><h2>Access denied</h2><p>Return to your dashboard to continue.</p><a class="btn btn-primary" href="' . e(base_url(account_home(current_user()))) . '">Go to dashboard</a></section>';
            include V2_ROOT . '/includes/footer.php';
        } else {
            echo 'Access denied.';
        }
        exit;
    }
}

/** Set a one-time message, or retrieve and consume all messages. */
function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $allowed = ['success', 'danger', 'warning', 'info'];
        $_SESSION['_flash'][] = [
            'type' => in_array($type, $allowed, true) ? $type : 'info',
            'message' => $message,
        ];
        return null;
    }

    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? $messages : [];
}

function set_flash(string $type, string $message): void
{
    flash($type, $message);
}

function get_flash(): array
{
    return flash() ?? [];
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function flash_input(array $input): void
{
    unset($input['password'], $input['password_confirmation'], $input['_csrf']);
    $_SESSION['_old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function account_home(array $user): string
{
    return match ((string) ($user['Role'] ?? '')) {
        'ADMIN' => 'admin/index.php',
        'RECEPTIONIST' => 'reception/index.php',
        'TRAINER' => 'trainer/index.php',
        'MEMBER' => 'member/index.php',
        default => 'auth/login.php',
    };
}

/** True when the current request targets a navigation path. */
function nav_is_active(string $path): bool
{
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $appPath = parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: '';
    if ($appPath !== '' && str_starts_with($requestPath, rtrim($appPath, '/'))) {
        $requestPath = substr($requestPath, strlen(rtrim($appPath, '/')));
    }
    return trim($requestPath, '/') === trim($path, '/');
}
