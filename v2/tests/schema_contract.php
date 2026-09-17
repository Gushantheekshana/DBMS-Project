<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/app.php';
$migration = $root . '/database/migrations/001_create_core_schema.sql';
$trainerReviewMigration = $root . '/database/migrations/002_add_trainer_application_review.sql';
$expected = [
    'USER_ACCOUNT' => ['UserAccountID','Email','PasswordHash','Role','Status','EmailVerifiedAt','LastLoginAt','FailedLoginCount','LockedUntil','CreatedAt','UpdatedAt'],
    'MEMBER' => ['MemberID','UserAccountID','MemberNumber','FirstName','LastName','DateOfBirth','Phone','EmergencyContactName','EmergencyContactPhone','JoinedOn','Status','CreatedAt','UpdatedAt'],
    'TRAINER' => ['TrainerID','UserAccountID','TrainerNumber','FirstName','LastName','Phone','Specialization','Bio','HireDate','Status','CreatedAt','UpdatedAt'],
    'MEMBERSHIP_PLAN' => ['MembershipPlanID','Name','Description','DurationMonths','Price','Currency','ClassLimit','IsActive','CreatedAt','UpdatedAt'],
    'MEMBERSHIP' => ['MembershipID','MemberID','MembershipPlanID','PlanNameSnapshot','PriceSnapshot','CurrencySnapshot','StartsOn','EndsOn','Status','CancelledAt','CreatedAt','UpdatedAt'],
    'MEMBERSHIP_PAYMENT' => ['MembershipPaymentID','MembershipID','Amount','Currency','Status','Method','TransactionReference','IdempotencyKey','PaidAt','RecordedByUserAccountID','Notes','CreatedAt','UpdatedAt'],
    'GYM_CLASS' => ['GymClassID','TrainerID','Name','Description','StartsAt','EndsAt','Capacity','Location','Status','CreatedAt','UpdatedAt'],
    'CLASS_ENROLLMENT' => ['ClassEnrollmentID','GymClassID','MemberID','Status','EnrolledAt','CancelledAt','CreatedAt','UpdatedAt'],
    'CLASS_ATTENDANCE' => ['ClassAttendanceID','ClassEnrollmentID','GymClassID','Status','CheckedInAt','MarkedByUserAccountID','Notes','CreatedAt','UpdatedAt'],
    'GYM_VISIT' => ['GymVisitID','MemberID','CheckedInAt','CheckedOutAt','CheckedInByUserAccountID','CheckedOutByUserAccountID','Notes','OpenVisitMarker','CreatedAt','UpdatedAt'],
];
$foreignKeys = [
    ['MEMBER','UserAccountID','USER_ACCOUNT','UserAccountID'], ['TRAINER','UserAccountID','USER_ACCOUNT','UserAccountID'],
    ['MEMBERSHIP','MemberID','MEMBER','MemberID'], ['MEMBERSHIP','MembershipPlanID','MEMBERSHIP_PLAN','MembershipPlanID'],
    ['MEMBERSHIP_PAYMENT','MembershipID','MEMBERSHIP','MembershipID'], ['MEMBERSHIP_PAYMENT','RecordedByUserAccountID','USER_ACCOUNT','UserAccountID'],
    ['GYM_CLASS','TrainerID','TRAINER','TrainerID'], ['CLASS_ENROLLMENT','GymClassID','GYM_CLASS','GymClassID'],
    ['CLASS_ENROLLMENT','MemberID','MEMBER','MemberID'],
    ['CLASS_ATTENDANCE','MarkedByUserAccountID','USER_ACCOUNT','UserAccountID'], ['GYM_VISIT','MemberID','MEMBER','MemberID'],
    ['GYM_VISIT','CheckedInByUserAccountID','USER_ACCOUNT','UserAccountID'], ['GYM_VISIT','CheckedOutByUserAccountID','USER_ACCOUNT','UserAccountID'],
];
$compositeForeignKeys = [
    ['CLASS_ATTENDANCE', ['ClassEnrollmentID','GymClassID'], 'CLASS_ENROLLMENT', ['ClassEnrollmentID','GymClassID']],
];
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };

$check(is_file($migration), "Migration not found: {$migration}");
$sql = is_file($migration) ? (string) file_get_contents($migration) : '';
$withoutComments = preg_replace('~/\*.*?\*/|--[^\r\n]*|#[^\r\n]*~s', '', $sql) ?? $sql;
preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $withoutComments, $matches);
$actualTables = array_map('strtoupper', $matches[1] ?? []);
$businessTables = array_values(array_filter($actualTables, static fn(string $table): bool => $table !== 'SCHEMA_MIGRATION'));
$check(count($businessTables) === 10, 'Migration must contain exactly 10 business CREATE TABLE statements; found ' . count($businessTables) . '.');
$check(count($actualTables) === count(array_unique($actualTables)), 'Migration creates at least one table more than once.');
$missing = array_diff(array_keys($expected), $businessTables);
$extra = array_diff($businessTables, array_keys($expected));
$check($missing === [], 'Missing tables: ' . implode(', ', $missing));
$check($extra === [], 'Unexpected business tables: ' . implode(', ', $extra));
$check(in_array('SCHEMA_MIGRATION', $actualTables, true), 'Missing SCHEMA_MIGRATION metadata table.');

foreach ($expected as $table => $columns) {
    if (!preg_match('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB\b[^;]*;/is', $withoutComments, $tableMatch)) {
        $failures[] = "{$table}: no complete InnoDB CREATE TABLE block found.";
        continue;
    }
    $block = $tableMatch[1];
    foreach ($columns as $column) {
        $check((bool) preg_match('/(?:^|,)\s*`?' . preg_quote($column, '/') . '`?\s+[A-Za-z]/im', $block), "{$table}: missing column {$column}.");
    }
    $check((bool) preg_match('/\bPRIMARY\s+KEY\b/i', $block), "{$table}: missing primary key.");
    $check((bool) preg_match('/DEFAULT\s+CHARSET\s*=\s*utf8mb4/i', $tableMatch[0]), "{$table}: must use utf8mb4.");
}

foreach ($foreignKeys as [$table, $column, $referencedTable, $referencedColumn]) {
    $pattern = '/FOREIGN\s+KEY\s*\(\s*`?' . preg_quote($column, '/') . '`?\s*\)\s*REFERENCES\s+`?' . preg_quote($referencedTable, '/') . '`?\s*\(\s*`?' . preg_quote($referencedColumn, '/') . '`?\s*\)/i';
    $check((bool) preg_match($pattern, $withoutComments), "{$table}.{$column}: missing foreign key to {$referencedTable}.{$referencedColumn}.");
}
foreach ($compositeForeignKeys as [$table, $columns, $referencedTable, $referencedColumns]) {
    $columnPattern = implode('\s*,\s*', array_map(static fn(string $column): string => '`?' . preg_quote($column, '/') . '`?', $columns));
    $referencedPattern = implode('\s*,\s*', array_map(static fn(string $column): string => '`?' . preg_quote($column, '/') . '`?', $referencedColumns));
    $pattern = '/FOREIGN\s+KEY\s*\(\s*' . $columnPattern . '\s*\)\s*REFERENCES\s+`?' . preg_quote($referencedTable, '/') . '`?\s*\(\s*' . $referencedPattern . '\s*\)/i';
    $check((bool) preg_match($pattern, $withoutComments), "{$table}: missing composite foreign key to {$referencedTable}.");
}
$check((bool) preg_match('/\bEmail\b[^,\n]*(?:UNIQUE)|UNIQUE[^\n]*\(\s*`?Email`?\s*\)/i', $withoutComments), 'USER_ACCOUNT.Email must be unique.');
$check((bool) preg_match('/UNIQUE[^\n]*\(\s*`?GymClassID`?\s*,\s*`?MemberID`?\s*\)/i', $withoutComments), 'CLASS_ENROLLMENT must uniquely constrain GymClassID + MemberID.');
$check((bool) preg_match('/\bOpenVisitMarker\b[^,\n]*GENERATED\s+ALWAYS/i', $withoutComments), 'GYM_VISIT.OpenVisitMarker must be generated.');

$check(is_file($trainerReviewMigration), "Migration not found: {$trainerReviewMigration}");
$trainerReviewSql = is_file($trainerReviewMigration) ? (string) file_get_contents($trainerReviewMigration) : '';
$check((bool) preg_match("/MODIFY\s+Status\s+ENUM\s*\([^)]*'REJECTED'/is", $trainerReviewSql), 'Trainer review migration must add REJECTED to TRAINER.Status.');
foreach (['ReviewedAt', 'ReviewedByUserAccountID', 'RejectionReason'] as $column) {
    $check((bool) preg_match('/ADD\s+COLUMN\s+`?' . preg_quote($column, '/') . '`?/i', $trainerReviewSql), "Trainer review migration must add {$column}.");
}
$check((bool) preg_match('/FOREIGN\s+KEY\s*\(\s*`?ReviewedByUserAccountID`?\s*\)\s*REFERENCES\s+`?USER_ACCOUNT`?\s*\(\s*`?UserAccountID`?\s*\)/i', $trainerReviewSql), 'TRAINER.ReviewedByUserAccountID must reference USER_ACCOUNT.UserAccountID.');
$check((bool) preg_match('/KEY\s+`?idx_trainer_application_queue`?\s*\(\s*`?Status`?\s*,\s*`?CreatedAt`?\s*\)/i', $trainerReviewSql), 'Trainer review migration must index the pending application queue.');

$dbConfigured = getenv('DB_HOST') !== false && getenv('DB_NAME') !== false;
$requireDb = filter_var(getenv('SCHEMA_CONTRACT_REQUIRE_DB') ?: false, FILTER_VALIDATE_BOOL);
if ($dbConfigured && extension_loaded('mysqli')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db = new mysqli((string) getenv('DB_HOST'), (string) (getenv('DB_USER') ?: 'root'), (string) (getenv('DB_PASSWORD') ?: ''), (string) getenv('DB_NAME'), (int) (getenv('DB_PORT') ?: 3306));
        $database = (string) getenv('DB_NAME');
        $stmt = $db->prepare('SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION');
        $stmt->bind_param('s', $database); $stmt->execute();
        $live = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $live[strtoupper($row['TABLE_NAME'])][] = $row['COLUMN_NAME'];
        $liveBusinessTables = array_values(array_filter(array_keys($live), static fn(string $table): bool => $table !== 'SCHEMA_MIGRATION'));
        $expectedTables = array_keys($expected);
        sort($liveBusinessTables, SORT_STRING);
        sort($expectedTables, SORT_STRING);
        $check($liveBusinessTables === $expectedTables, 'Live database business table set does not match the exact ten-table contract.');
        $check(isset($live['SCHEMA_MIGRATION']), 'Live database is missing SCHEMA_MIGRATION.');
        foreach ($expected as $table => $columns) foreach ($columns as $column) $check(in_array($column, $live[$table] ?? [], true), "Live {$table}: missing {$column}.");
        echo "Database checks completed.\n";
    } catch (Throwable $error) {
        $failures[] = 'Database check failed: ' . $error->getMessage();
    }
} elseif ($requireDb) {
    $failures[] = 'Database checks required, but DB_HOST/DB_NAME or mysqli is unavailable.';
} else {
    echo "Database checks skipped (configure DB_HOST and DB_NAME to enable).\n";
}

if ($failures !== []) {
    fwrite(STDERR, "Schema contract failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Schema contract passed.\n";
