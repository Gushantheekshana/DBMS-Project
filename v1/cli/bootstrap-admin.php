<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../bootstrap/app.php';

$email = strtolower(trim($argv[1] ?? ''));
$password = $argv[2] ?? '';
if (!gmail_address($email) || strlen($password) < 10) {
    fwrite(STDERR, "Usage: php cli/bootstrap-admin.php admin@gmail.com 'password-at-least-10-chars'\n"); exit(1);
}
if (db_one('SELECT UserID FROM USER_ACCOUNT WHERE Email=?','s',[$email])) {
    fwrite(STDERR,"Account already exists.\n"); exit(1);
}
$hash=password_hash($password,PASSWORD_DEFAULT);
db_execute("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,'SUPER_ADMIN','ACTIVE',NOW())",'ss',[$email,$hash]);
echo "Super admin created: $email\n";
