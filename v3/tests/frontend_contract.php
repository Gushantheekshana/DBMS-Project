<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$expected = [
    'index.php',
    'auth/login.php', 'auth/logout.php', 'auth/register-member.php', 'auth/register-trainer.php',
    'admin/index.php', 'admin/members.php', 'admin/plans.php', 'admin/trainers.php', 'admin/classes.php', 'admin/payments.php', 'admin/staff.php',
    'reception/index.php', 'reception/history.php',
    'trainer/index.php', 'trainer/classes.php', 'trainer/enrollments.php', 'trainer/attendance.php',
    'member/index.php', 'member/membership.php', 'member/checkout.php', 'member/classes.php', 'member/attendance.php',
];
$errors = [];

foreach ($expected as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = "Missing route: {$relative}";
        continue;
    }
    $source = file_get_contents($path) ?: '';
    if ($relative !== 'index.php' && $relative !== 'auth/login.php' && $relative !== 'auth/logout.php' && $relative !== 'auth/register-member.php' && $relative !== 'auth/register-trainer.php' && !str_contains($source, 'require_role(')) {
        $errors[] = "Protected route has no role guard: {$relative}";
    }
    if (preg_match('~(?:href|action)=["\'][^"\']*/v2/~i', $source)) {
        $errors[] = "Hardcoded V2 navigation found: {$relative}";
    }
    if (str_contains($source, "V2_ROOT . '/includes/")) {
        $errors[] = "V2 presentation include found: {$relative}";
    }
    if (str_contains($source, "REQUEST_METHOD") && str_contains($source, "=== 'POST'") && !str_contains($source, 'verify_csrf()')) {
        $errors[] = "POST route has no CSRF verification: {$relative}";
    }
}

foreach (['app', 'database', 'cli'] as $forbidden) {
    if (is_dir($root . '/' . $forbidden)) {
        $errors[] = "V3 must not copy backend directory: {$forbidden}";
    }
}

$formContracts = [
    'auth/login.php' => ['role', 'email', 'password'],
    'auth/register-member.php' => ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'password', 'password_confirmation'],
    'auth/register-trainer.php' => ['first_name', 'last_name', 'email', 'phone', 'specialization', 'bio', 'password', 'password_confirmation'],
    'admin/trainers.php' => ['trainer_id', 'decision', 'reason'],
    'admin/classes.php' => ['class_id', 'decision', 'notes'],
    'reception/index.php' => ['visit_id', 'action', 'member_id'],
    'trainer/classes.php' => ['name', 'description', 'starts_at', 'ends_at', 'capacity', 'location'],
    'trainer/enrollments.php' => ['enrollment_id', 'decision', 'reason'],
    'trainer/attendance.php' => ['class_id'],
    'member/membership.php' => ['plan_id'],
    'member/checkout.php' => ['membership_id', 'method', 'transaction_reference', 'notes'],
    'member/classes.php' => ['class_id'],
];
foreach ($formContracts as $relative => $fields) {
    $source = file_get_contents($root . '/' . $relative) ?: '';
    foreach ($fields as $field) {
        if (!str_contains($source, 'name="' . $field . '"') && !str_contains($source, "name='" . $field . "'")) {
            $errors[] = "Missing form field contract {$field}: {$relative}";
        }
    }
}

$confirmationRoutes = ['admin/trainers.php', 'admin/classes.php', 'trainer/enrollments.php'];
$confirmationHooks = ['data-confirm-dialog', 'data-confirm-open', 'data-confirm-form', 'data-confirm-record', 'data-confirm-decision', 'data-confirm-submit', 'data-confirm-cancel'];
foreach ($confirmationRoutes as $relative) {
    $source = file_get_contents($root . '/' . $relative) ?: '';
    foreach ($confirmationHooks as $hook) {
        if (!str_contains($source, $hook)) {
            $errors[] = "Missing confirmation hook {$hook}: {$relative}";
        }
    }
}

$header = file_get_contents($root . '/includes/header.php') ?: '';
$footer = file_get_contents($root . '/includes/footer.php') ?: '';
foreach (['Skip to main content', 'primary-navigation', 'data-nav-toggle', 'data-theme'] as $shellHook) {
    if (!str_contains($header, $shellHook)) {
        $errors[] = "Missing shared shell hook: {$shellHook}";
    }
}
if (!str_contains($header, "auth/logout.php") || !str_contains($header, 'csrf_field()')) {
    $errors[] = 'Shared shell must keep the CSRF-protected logout form.';
}
if (preg_match('~https?://[^\"\']*(?:tailwind|flowbite|lucide|cdn)~i', $header . $footer)) {
    $errors[] = 'Frontend dependencies must be served locally.';
}
foreach (['package.json', 'package-lock.json', 'assets/css/input.css', 'assets/icons/lucide.svg', 'assets/vendor/flowbite/flowbite.min.js', 'includes/icons.php'] as $asset) {
    if (!is_file($root . '/' . $asset)) {
        $errors[] = "Missing local frontend asset: {$asset}";
    }
}
if (!str_contains($header, 'assets/css/app.css') || !str_contains($footer, 'assets/vendor/flowbite/flowbite.min.js')) {
    $errors[] = 'Shared templates must load committed local production assets.';
}
if (!str_contains($header, 'gympro-v3-theme') || !str_contains($header, "classList.toggle('dark'")) {
    $errors[] = 'Shared shell must initialize the persisted theme before paint.';
}
if (preg_match('~(?:assets/vendor/bootstrap|data-bs-|bootstrap\\.(?:bundle|min))~i', $header . $footer)) {
    $errors[] = 'Shared templates must not load Bootstrap.';
}

$css = file_get_contents($root . '/assets/css/app.css') ?: '';
$javascript = file_get_contents($root . '/assets/js/app.js') ?: '';
$motionContracts = [
    'CSS loading button hook' => str_contains($css, '.btn.is-loading'),
    'CSS toast exit hook' => str_contains($css, '.toast.is-leaving'),
    'CSS dialog exit hook' => str_contains($css, '.review-dialog.is-closing'),
    'CSS reduced motion behavior' => str_contains($css, 'prefers-reduced-motion:reduce'),
    'JavaScript reduced motion query' => str_contains($javascript, 'prefers-reduced-motion: reduce'),
    'JavaScript animation fallback' => str_contains($javascript, 'afterMotion'),
    'JavaScript page restore reset' => str_contains($javascript, "addEventListener('pageshow', resetSubmitState)"),
    'JavaScript mobile breakpoint reset' => str_contains($javascript, 'desktopMedia.addEventListener'),
];
if (str_contains($javascript, 'entranceSelectors') || str_contains($css, '.motion-surface')) {
    $errors[] = 'Decorative page entrance sequencing must not be restored.';
}
foreach ($motionContracts as $label => $satisfied) {
    if (!$satisfied) {
        $errors[] = "Missing motion contract: {$label}";
    }
}
if (preg_match('/tbody\s+tr[^,{]*[,{][^}]*animation\s*:/is', $css)) {
    $errors[] = 'Table rows must not use entrance animation.';
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'V3 frontend contract passed.' . PHP_EOL;
