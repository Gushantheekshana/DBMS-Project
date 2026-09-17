<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../bootstrap/app.php';

const MIGRATION_LOCK_NAME = 'gym_system_v2_schema_migrator';
const MIGRATION_LOCK_TIMEOUT_SECONDS = 10;

function output(string $message, bool $error = false): void
{
    fwrite($error ? STDERR : STDOUT, $message . PHP_EOL);
}

function usage(): never
{
    output('Usage: php cli/migrate.php [migrate|status] [--dry-run]');
    exit(64);
}

/** @return array{command: string, dryRun: bool} */
function parse_arguments(array $arguments): array
{
    $command = 'migrate';
    $dryRun = false;

    foreach (array_slice($arguments, 1) as $argument) {
        if ($argument === '--dry-run') {
            $dryRun = true;
        } elseif ($argument === '--help' || $argument === '-h') {
            usage();
        } elseif (in_array($argument, ['migrate', 'status'], true)) {
            $command = $argument;
        } else {
            output('Unknown argument: ' . $argument, true);
            usage();
        }
    }

    if ($command === 'status' && $dryRun) {
        output('--dry-run is only valid with migrate.', true);
        usage();
    }

    return ['command' => $command, 'dryRun' => $dryRun];
}

function ensure_metadata_table(mysqli $database): void
{
    $database->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS SCHEMA_MIGRATION (
    MigrationID VARCHAR(190) NOT NULL,
    Checksum CHAR(64) NOT NULL,
    StartedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AppliedAt DATETIME NULL,
    ExecutionMilliseconds INT UNSIGNED NULL,
    IsDirty TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (MigrationID),
    KEY idx_schema_migration_applied (IsDirty, AppliedAt),
    CONSTRAINT chk_schema_migration_dirty CHECK (IsDirty IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

/** @return list<array{id: string, path: string, checksum: string, sql: string}> */
function discover_migrations(string $directory): array
{
    $paths = glob($directory . '/*.sql') ?: [];
    sort($paths, SORT_STRING);
    $migrations = [];
    $previousId = null;

    foreach ($paths as $path) {
        $id = basename($path);
        if (!preg_match('/^[0-9]{3,}_[a-z0-9_]+\.sql$/', $id)) {
            throw new RuntimeException('Invalid migration filename: ' . $id);
        }
        if ($previousId !== null && strcmp($id, $previousId) <= 0) {
            throw new RuntimeException('Migration filenames must be uniquely ordered.');
        }
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migration is empty or unreadable: ' . $id);
        }
        $migrations[] = [
            'id' => $id,
            'path' => $path,
            'checksum' => hash('sha256', $sql),
            'sql' => $sql,
        ];
        $previousId = $id;
    }

    return $migrations;
}

/** @return array<string, array{Checksum: string, IsDirty: int}> */
function applied_migrations(mysqli $database): array
{
    $result = $database->query('SELECT MigrationID, Checksum, IsDirty FROM SCHEMA_MIGRATION ORDER BY MigrationID');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[$row['MigrationID']] = [
            'Checksum' => $row['Checksum'],
            'IsDirty' => (int) $row['IsDirty'],
        ];
    }
    $result->free();

    return $rows;
}

function execute_sql_batch(mysqli $database, string $sql): void
{
    $database->multi_query($sql);
    do {
        $result = $database->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        if (!$database->more_results()) {
            break;
        }
    } while ($database->next_result());

    if ($database->errno !== 0) {
        throw new mysqli_sql_exception($database->error, $database->errno);
    }
}

function mark_started(mysqli $database, string $id, string $checksum): void
{
    $statement = $database->prepare(<<<'SQL'
INSERT INTO SCHEMA_MIGRATION (MigrationID, Checksum, StartedAt, AppliedAt, ExecutionMilliseconds, IsDirty)
VALUES (?, ?, CURRENT_TIMESTAMP, NULL, NULL, 1)
SQL);
    $statement->bind_param('ss', $id, $checksum);
    $statement->execute();
}

function mark_completed(mysqli $database, string $id, int $milliseconds): void
{
    $statement = $database->prepare(<<<'SQL'
UPDATE SCHEMA_MIGRATION
SET AppliedAt = CURRENT_TIMESTAMP, ExecutionMilliseconds = ?, IsDirty = 0
WHERE MigrationID = ?
SQL);
    $statement->bind_param('is', $milliseconds, $id);
    $statement->execute();
}

function acquire_lock(mysqli $database): void
{
    $statement = $database->prepare('SELECT GET_LOCK(?, ?) AS Acquired');
    $name = MIGRATION_LOCK_NAME;
    $timeout = MIGRATION_LOCK_TIMEOUT_SECONDS;
    $statement->bind_param('si', $name, $timeout);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    if ((int) ($row['Acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Could not acquire the migration lock; another migrator may be running.');
    }
}

function release_lock(mysqli $database): void
{
    $statement = $database->prepare('SELECT RELEASE_LOCK(?)');
    $name = MIGRATION_LOCK_NAME;
    $statement->bind_param('s', $name);
    $statement->execute();
}

$options = parse_arguments($argv);
$database = null;
$locked = false;

try {
    $database = db();
    $database->query("SET SESSION sql_mode = CONCAT_WS(',', @@sql_mode, 'STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION')");
    ensure_metadata_table($database);
    acquire_lock($database);
    $locked = true;

    $migrations = discover_migrations(V2_ROOT . '/database/migrations');
    $applied = applied_migrations($database);

    foreach ($applied as $id => $record) {
        if ($record['IsDirty'] === 1) {
            throw new RuntimeException("Migration {$id} is marked dirty. Restore the database and resolve it before retrying.");
        }
    }

    $knownIds = array_column($migrations, 'id');
    foreach (array_keys($applied) as $id) {
        if (!in_array($id, $knownIds, true)) {
            throw new RuntimeException("Applied migration {$id} is missing from disk.");
        }
    }

    $pending = 0;
    foreach ($migrations as $migration) {
        $record = $applied[$migration['id']] ?? null;
        if ($record !== null) {
            if (!hash_equals($record['Checksum'], $migration['checksum'])) {
                throw new RuntimeException("Checksum mismatch for applied migration {$migration['id']}; migrations are immutable.");
            }
            output(sprintf('[applied] %s', $migration['id']));
            continue;
        }

        $pending++;
        if ($options['command'] === 'status') {
            output(sprintf('[pending] %s', $migration['id']));
            continue;
        }
        if ($options['dryRun']) {
            output(sprintf('[would apply] %s (%s)', $migration['id'], $migration['checksum']));
            continue;
        }

        output('[applying] ' . $migration['id']);
        mark_started($database, $migration['id'], $migration['checksum']);
        $startedAt = hrtime(true);
        execute_sql_batch($database, $migration['sql']);
        $milliseconds = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        mark_completed($database, $migration['id'], $milliseconds);
        output(sprintf('[applied] %s (%d ms)', $migration['id'], $milliseconds));
    }

    if ($options['command'] === 'status') {
        output(sprintf('%d applied, %d pending.', count($migrations) - $pending, $pending));
    } elseif ($options['dryRun']) {
        output(sprintf('%d migration(s) would be applied.', $pending));
    } else {
        output($pending === 0 ? 'Schema is up to date.' : 'Migration complete.');
    }
} catch (Throwable $exception) {
    output('[error] ' . $exception->getMessage(), true);
    $exitCode = 1;
} finally {
    if ($locked && $database instanceof mysqli) {
        try {
            release_lock($database);
        } catch (Throwable) {
            // The server also releases advisory locks when the connection closes.
        }
    }
}

exit($exitCode ?? 0);
