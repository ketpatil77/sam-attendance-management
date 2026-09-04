<?php
function sam_resolve_request_timezone(): string
{
    static $validTimezones = null;
    if ($validTimezones === null) {
        $validTimezones = array_fill_keys(timezone_identifiers_list(), true);
    }

    $candidate = trim((string)($_COOKIE['sam_tz'] ?? ''));
    if ($candidate !== '' && isset($validTimezones[$candidate])) {
        return $candidate;
    }

    return 'Asia/Kolkata';
}

date_default_timezone_set(sam_resolve_request_timezone());

$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$dbPath = $dataDir . DIRECTORY_SEPARATOR . 'sam.sqlite';
$schemaPath = __DIR__ . DIRECTORY_SEPARATOR . 'schema_sqlite.sql';

if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
    die('Failed to create database directory.');
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO("sqlite:$dbPath", null, null, $options);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    if (!file_exists($schemaPath)) {
        throw new RuntimeException('Missing schema_sqlite.sql');
    }

    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Unable to read schema_sqlite.sql');
    }
    $pdo->exec($schema);

    $columns = $pdo->query("PRAGMA table_info(student_information)")->fetchAll();
    $hasParentMob = false;
    $hasParentMovile = false;
    foreach ($columns as $column) {
        if (isset($column['name']) && $column['name'] === 'PARENT_MOB') {
            $hasParentMob = true;
        }
        if (isset($column['name']) && $column['name'] === 'PARENT_MOVILE') {
            $hasParentMovile = true;
        }
    }

    if (!$hasParentMob) {
        $pdo->exec('ALTER TABLE student_information ADD COLUMN PARENT_MOB TEXT;');
    }
    if ($hasParentMovile) {
        $pdo->exec("UPDATE student_information SET PARENT_MOB = COALESCE(PARENT_MOB, PARENT_MOVILE);");
    }
} catch (\Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>










