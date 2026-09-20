<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../bootstrap/app.php';

$db = db();
$db->query("CREATE TABLE IF NOT EXISTS SCHEMA_MIGRATION (MigrationID VARCHAR(100) PRIMARY KEY, AppliedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $id = basename($file);
    if (db_one('SELECT MigrationID FROM SCHEMA_MIGRATION WHERE MigrationID=?','s',[$id])) {
        echo "[skip] $id\n"; continue;
    }
    echo "[run ] $id\n";
    $sql = file_get_contents($file);
    try {
        $db->begin_transaction();
        if (!$db->multi_query($sql)) throw new RuntimeException($db->error);
        do { if ($result=$db->store_result()) $result->free(); } while ($db->more_results() && $db->next_result());
        if ($db->errno) throw new RuntimeException($db->error);
        $stmt=$db->prepare('INSERT INTO SCHEMA_MIGRATION (MigrationID) VALUES (?)'); $stmt->bind_param('s',$id); $stmt->execute();
        $db->commit();
        echo "[done] $id\n";
    } catch (Throwable $e) {
        if ($db->errno || $db->thread_id) { try { $db->rollback(); } catch(Throwable) {} }
        fwrite(STDERR,"[fail] $id: {$e->getMessage()}\n"); exit(1);
    }
}
