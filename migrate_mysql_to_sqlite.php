<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$dumpPath = __DIR__ . DIRECTORY_SEPARATOR . 'sam.sql';
if (!file_exists($dumpPath)) {
    fwrite(STDERR, "MySQL dump not found: $dumpPath\n");
    exit(1);
}

$tableColumns = [
    'staff_daily' => ['RID', 'NAME', 'DESIGNATION', 'DEPARTMENT', 'INTIME', 'OUTTIME', 'IN_PHOTO', 'OUT_PHOTO', 'VERIFICATION_SCORE', 'EDATE'],
    'staff_information' => ['EID', 'NAME', 'DESIGNATION', 'DEPARTMENT', 'MOBILE', 'PHOTO'],
    'student_daily' => ['INTIME', 'OUTTIME', 'STATUS', 'RID', 'EDATE', 'STUDENT_NAME', 'ERN_NO', 'DEPARTMENT', 'BATCH', 'AYEAR', 'STUDENT_ID'],
    'student_information' => ['STUDENT_NAME', 'STUDENT_ID', 'ERN_NO', 'BATCH', 'DEPARTMENT', 'AYEAR', 'RID', 'PHOTO', 'MOBILE', 'PARENT_MOVILE', 'CITY', 'ADDRESS'],
    'user' => ['user_id', 'username', 'password'],
];

$targetTables = ['staff_daily', 'staff_information', 'student_daily', 'student_information', 'user'];
$insertSql = [];
foreach ($targetTables as $table) {
    $cols = $tableColumns[$table];
    if ($table === 'student_information') {
        $cols = ['STUDENT_NAME', 'STUDENT_ID', 'ERN_NO', 'BATCH', 'DEPARTMENT', 'AYEAR', 'RID', 'PHOTO', 'MOBILE', 'PARENT_MOB', 'CITY', 'ADDRESS'];
    }
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $quotedCols = implode(',', array_map(function ($c) {
        return '"' . $c . '"';
    }, $cols));
    $insertSql[$table] = 'INSERT INTO "' . $table . '" (' . $quotedCols . ') VALUES (' . $placeholders . ')';
}

function mysqlTokenToValue($token)
{
    $token = trim($token);
    if ($token === '' || strcasecmp($token, 'NULL') === 0) {
        return null;
    }

    if (preg_match('/^0x([0-9A-Fa-f]*)$/', $token, $m)) {
        $hex = $m[1];
        return $hex === '' ? '' : hex2bin($hex);
    }

    if (strlen($token) >= 2 && $token[0] === "'" && substr($token, -1) === "'") {
        $inner = substr($token, 1, -1);
        return stripcslashes($inner);
    }

    if (is_numeric($token)) {
        if (strpos($token, '.') !== false) {
            return (float)$token;
        }
        return (int)$token;
    }

    return $token;
}

function parseValuesTuples($valuesPart)
{
    $tuples = [];
    $tuple = [];
    $token = '';
    $depth = 0;
    $inString = false;
    $escape = false;
    $len = strlen($valuesPart);

    for ($i = 0; $i < $len; $i++) {
        $ch = $valuesPart[$i];

        if ($inString) {
            $token .= $ch;
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === "'") {
                $inString = false;
            }
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $token .= $ch;
            continue;
        }

        if ($ch === '(') {
            if ($depth === 0) {
                $tuple = [];
                $token = '';
            } else {
                $token .= $ch;
            }
            $depth++;
            continue;
        }

        if ($ch === ')') {
            if ($depth > 1) {
                $token .= $ch;
            }
            $depth--;
            if ($depth === 0) {
                $tuple[] = trim($token);
                $tuples[] = $tuple;
                $tuple = [];
                $token = '';
            }
            continue;
        }

        if ($ch === ',' && $depth === 1) {
            $tuple[] = trim($token);
            $token = '';
            continue;
        }

        if ($depth >= 1) {
            $token .= $ch;
        }
    }

    return $tuples;
}

function processInsertBuffer($buffer, $pdo, $insertSql, &$sourceCounts)
{
    if (!preg_match('/^\s*INSERT INTO `([^`]+)` VALUES\s*(.*);\s*$/s', $buffer, $m)) {
        return;
    }

    $table = $m[1];
    if (!isset($insertSql[$table])) {
        return;
    }

    $valuesPart = $m[2];
    $tuples = parseValuesTuples($valuesPart);
    if (!$tuples) {
        return;
    }

    $stmt = $pdo->prepare($insertSql[$table]);
    foreach ($tuples as $rowTokens) {
        $row = array_map('mysqlTokenToValue', $rowTokens);

        if ($table === 'student_information') {
            // Input has PARENT_MOVILE; destination uses PARENT_MOB in same index.
            // Row layout remains positional so no reordering required.
        }

        $stmt->execute($row);
        $sourceCounts[$table]++;
    }
}

try {
    $pdo->exec('PRAGMA foreign_keys = OFF;');
    $pdo->exec('PRAGMA synchronous = OFF;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    $pdo->beginTransaction();

    foreach ($targetTables as $table) {
        $pdo->exec('DELETE FROM "' . $table . '"');
    }
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('staff_daily','staff_information','student_information','user')");

    $sourceCounts = array_fill_keys($targetTables, 0);
    $handle = fopen($dumpPath, 'rb');
    if (!$handle) {
        throw new RuntimeException("Unable to open dump: $dumpPath");
    }

    $collecting = false;
    $buffer = '';
    while (($line = fgets($handle)) !== false) {
        if (!$collecting) {
            if (preg_match('/^\s*INSERT INTO `[^`]+` VALUES /', $line)) {
                $collecting = true;
                $buffer = $line;
                if (strpos($line, ';') !== false) {
                    processInsertBuffer($buffer, $pdo, $insertSql, $sourceCounts);
                    $buffer = '';
                    $collecting = false;
                }
            }
        } else {
            $buffer .= $line;
            if (strpos($line, ';') !== false) {
                processInsertBuffer($buffer, $pdo, $insertSql, $sourceCounts);
                $buffer = '';
                $collecting = false;
            }
        }
    }
    fclose($handle);

    $pdo->commit();
    $pdo->exec('PRAGMA foreign_keys = ON;');

    echo "Migration complete.\n";
    foreach ($targetTables as $table) {
        $destCount = (int)$pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
        echo $table . ': source=' . $sourceCounts[$table] . ' sqlite=' . $destCount . "\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}












