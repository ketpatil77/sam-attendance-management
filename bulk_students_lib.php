<?php

function sam_bulk_normalize_header(string $value): string
{
    return strtolower((string)preg_replace('/[^a-z0-9]+/i', '_', trim($value)));
}

function sam_bulk_column_index(string $letters): int
{
    $index = 0;
    foreach (str_split(strtoupper($letters)) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }
    return $index - 1;
}

function sam_bulk_read_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open Excel workbook.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml !== false) {
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xml->xpath('//x:si') ?: [] as $item) {
                $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $parts = [];
                foreach ($item->xpath('.//x:t') ?: [] as $text) {
                    $parts[] = (string)$text;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Workbook has no readable first worksheet.');
    }

    $xml = simplexml_load_string($sheetXml);
    if ($xml === false) {
        throw new RuntimeException('Invalid worksheet XML.');
    }
    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach ($xml->xpath('//x:sheetData/x:row') ?: [] as $row) {
        $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];
        foreach ($row->xpath('./x:c') ?: [] as $cell) {
            $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $reference = (string)$cell['r'];
            preg_match('/^[A-Z]+/', $reference, $match);
            $column = sam_bulk_column_index($match[0] ?? 'A');
            $type = (string)$cell['t'];
            if ($type === 'inlineStr') {
                $inlineParts = [];
                foreach ($cell->xpath('.//x:is//x:t') ?: [] as $text) {
                    $inlineParts[] = (string)$text;
                }
                $value = implode('', $inlineParts);
            } else {
                $valueNodes = $cell->xpath('./x:v') ?: [];
                $value = isset($valueNodes[0]) ? (string)$valueNodes[0] : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }
            }
            $values[$column] = trim($value);
        }
        if ($values !== []) {
            ksort($values);
            $max = max(array_keys($values));
            $rows[] = array_map('strval', array_replace(array_fill(0, $max + 1, ''), $values));
        }
    }
    return $rows;
}

function sam_bulk_read_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open CSV file.');
    }
    $rows = [];
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
        }
        $rows[] = array_map(static fn($value) => trim((string)$value), $row);
    }
    fclose($handle);
    return $rows;
}

function sam_bulk_import_students(PDO $pdo, array $rows): array
{
    $fields = [
        'student_name' => 'STUDENT_NAME', 'student_id' => 'STUDENT_ID',
        'ern_no' => 'ERN_NO', 'batch' => 'BATCH', 'department' => 'DEPARTMENT',
        'academic_year' => 'AYEAR', 'mobile' => 'MOBILE',
        'parent_mobile' => 'PARENT_MOB', 'city' => 'CITY', 'address' => 'ADDRESS',
    ];
    if (count($rows) < 2) {
        throw new RuntimeException('File must contain header and at least one student row.');
    }

    $headerRowIndex = null;
    $headerMap = [];
    foreach ($rows as $rowIndex => $candidateRow) {
        $candidateMap = [];
        foreach ($candidateRow as $index => $header) {
            $candidateMap[sam_bulk_normalize_header((string)$header)] = $index;
        }
        if (isset($candidateMap['student_name'], $candidateMap['student_id'], $candidateMap['ern_no'])) {
            $headerRowIndex = $rowIndex;
            $headerMap = $candidateMap;
            break;
        }
    }
    if ($headerRowIndex === null) {
        throw new RuntimeException('Header row not found. Use the downloadable template.');
    }
    foreach (array_keys($fields) as $required) {
        if (!array_key_exists($required, $headerMap)) {
            throw new RuntimeException('Missing column: ' . $required);
        }
    }

    $inserted = 0;
    $skipped = 0;
    $errors = [];
    $seenPrn = [];
    $seenId = [];
    $check = $pdo->prepare('SELECT 1 FROM student_information WHERE ERN_NO = ? OR STUDENT_ID = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO student_information
        (STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, MOBILE, PARENT_MOB, CITY, ADDRESS)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $pdo->beginTransaction();
    try {
        foreach (array_slice($rows, $headerRowIndex + 1) as $offset => $row) {
            $line = $headerRowIndex + $offset + 2;
            $record = [];
            foreach ($fields as $header => $column) {
                $record[$column] = trim((string)($row[$headerMap[$header]] ?? ''));
            }
            if (implode('', $record) === '') {
                continue;
            }
            $record['MOBILE'] = '1234567890';
            $record['PARENT_MOB'] = '1234567890';
            $requiredValues = ['STUDENT_NAME', 'STUDENT_ID', 'ERN_NO', 'BATCH', 'DEPARTMENT', 'AYEAR', 'MOBILE'];
            $missing = array_values(array_filter($requiredValues, static fn($key) => $record[$key] === ''));
            if ($missing !== []) {
                $errors[] = "Row $line missing: " . implode(', ', $missing);
                continue;
            }
            $allowedDepartments = ['AI', 'CT', 'EE', 'ME', 'CE', 'EJ'];
            $record['DEPARTMENT'] = strtoupper($record['DEPARTMENT']);
            if (!in_array($record['DEPARTMENT'], $allowedDepartments, true)) {
                $errors[] = "Row $line has invalid department. Allowed: " . implode(', ', $allowedDepartments);
                continue;
            }
            if (isset($seenPrn[$record['ERN_NO']]) || isset($seenId[$record['STUDENT_ID']])) {
                $errors[] = "Row $line duplicates earlier ERN or Student ID.";
                $skipped++;
                continue;
            }
            $seenPrn[$record['ERN_NO']] = true;
            $seenId[$record['STUDENT_ID']] = true;
            $check->execute([$record['ERN_NO'], $record['STUDENT_ID']]);
            if ($check->fetchColumn()) {
                $skipped++;
                continue;
            }
            $insert->execute(array_values($record));
            $inserted++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
}











