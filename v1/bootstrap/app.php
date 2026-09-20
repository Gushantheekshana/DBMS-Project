<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';

$vendor = ROOT_PATH . '/vendor/autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'GymPro\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require_once $path;
});

function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv("$key=$value");
        $_ENV[$key] ??= $value;
    }
}
load_env(ROOT_PATH . '/.env');

function env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') return $default;
    return match (strtolower((string)$value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function base_url(string $path = ''): string {
    return rtrim((string)env('APP_URL', 'http://localhost/gym_system/v1'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never {
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : base_url($path)));
    exit;
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function set_flash(string $type, string $msg): void { $_SESSION['flash'] = compact('type', 'msg'); }
function get_flash(): ?array { $value = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $value; }
function old(string $key, string $default = ''): string { return e($_SESSION['_old'][$key] ?? $default); }
function flash_input(array $input): void { $_SESSION['_old'] = $input; }
function clear_old_input(): void { unset($_SESSION['_old']); }

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['_csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('Invalid or expired form token. Please go back and try again.');
    }
}

function db(): mysqli {
    static $db;
    if ($db instanceof mysqli) return $db;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)env('DB_HOST', '127.0.0.1'), (string)env('DB_USER', 'root'), (string)env('DB_PASS', ''), (string)env('DB_NAME', 'gym_membership_db'), (int)env('DB_PORT', 3306));
    $db->set_charset('utf8mb4');
    return $db;
}
function db_all(string $sql, string $types = '', array $params = []): array {
    $stmt = db()->prepare($sql); if ($types !== '') $stmt->bind_param($types, ...$params); $stmt->execute(); return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
function db_one(string $sql, string $types = '', array $params = []): ?array { return db_all($sql, $types, $params)[0] ?? null; }
function db_execute(string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = db()->prepare($sql); if ($types !== '') $stmt->bind_param($types, ...$params); $stmt->execute(); return $stmt;
}
function transaction(callable $callback): mixed {
    $db = db(); $db->begin_transaction();
    try { $result = $callback($db); $db->commit(); return $result; }
    catch (Throwable $e) { $db->rollback(); throw $e; }
}

function current_user(): ?array {
    static $cached = false;
    if ($cached !== false) return $cached;
    $id = (int)($_SESSION['user_id'] ?? 0);
    $cached = $id ? db_one('SELECT * FROM USER_ACCOUNT WHERE UserID=? AND AnonymizedAt IS NULL', 'i', [$id]) : null;
    return $cached;
}
function is_role(string ...$roles): bool { $user = current_user(); return $user && in_array($user['Role'], $roles, true); }
function require_login(): void { if (!current_user()) { set_flash('danger', 'Please sign in to continue.'); redirect('auth/login.php'); } }
function require_guest(): void { if (current_user()) redirect('index.php'); }
function require_role(string ...$roles): void { require_login(); if (!is_role(...$roles)) { http_response_code(403); exit('You are not authorized to access this page.'); } }
function require_any_role(string ...$roles): void { require_role(...$roles); }
function require_active_account(): void { require_login(); if ((current_user()['Status'] ?? '') !== 'ACTIVE') { set_flash('info', 'Your account is not active yet.'); redirect('index.php'); } }
function logout_user(): void { $_SESSION = []; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); }

function audit(string $action, string $entityType, ?int $entityId = null, array $metadata = []): void {
    $user = current_user();
    db_execute('INSERT INTO AUDIT_LOG (ActorUserID,Action,EntityType,EntityID,Metadata,IPAddress,CreatedAt) VALUES (?,?,?,?,?,?,NOW())', 'ississ', [$user['UserID'] ?? null,$action,$entityType,$entityId,json_encode($metadata, JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR'] ?? null]);
}
function notify_users(array $userIds, string $title, string $body, string $type='INFO', ?string $entityType=null, ?int $entityId=null): void {
    $userIds = array_values(array_unique(array_filter(array_map('intval',$userIds)))); if (!$userIds) return;
    transaction(function() use ($userIds,$title,$body,$type,$entityType,$entityId) {
        $sender=current_user()['UserID'] ?? null;
        db_execute('INSERT INTO NOTIFICATION (SenderUserID,Title,Body,Type,EntityType,EntityID,CreatedAt) VALUES (?,?,?,?,?,?,NOW())','issssi',[$sender,$title,$body,$type,$entityType,$entityId]);
        $nid=db()->insert_id;
        foreach($userIds as $uid) db_execute('INSERT IGNORE INTO NOTIFICATION_RECIPIENT (NotificationID,UserID,CreatedAt) VALUES (?,?,NOW())','ii',[$nid,$uid]);
    });
}
function gmail_address(string $email): bool { $email=strtolower(trim($email)); return filter_var($email,FILTER_VALIDATE_EMAIL)!==false && substr(strrchr($email,'@') ?: '',1)==='gmail.com'; }
function account_home(array $user): string { return match($user['Role']) { 'MEMBER'=>'member/index.php','TRAINER'=>'trainer/index.php','RECEPTIONIST'=>'reception/index.php','SUPER_ADMIN'=>'admin/index.php',default=>'auth/login.php' }; }

$timezone = (string)env('APP_TIMEZONE', 'Asia/Colombo');
date_default_timezone_set($timezone);
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (env('SESSION_SECURE', false)) ini_set('session.cookie_secure', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > 3600) logout_user();
$_SESSION['last_activity'] = time();
