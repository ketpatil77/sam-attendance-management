<?php
require 'db.php';
require_once 'app_ui.php';
require_once 'bulk_students_lib.php';
sam_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['student_file'])) {
    header('Location: student_register.php?tab=bulk');
    exit;
}

$file = $_FILES['student_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['bulk_result'] = ['error' => 'Upload failed. Select a valid .xlsx or .csv file.'];
    header('Location: student_register.php?tab=bulk');
    exit;
}

try {
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'csv'], true)) {
        throw new RuntimeException('Only .xlsx and .csv files are supported.');
    }
    if ((int)$file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('File exceeds 10 MB limit.');
    }
    $rows = $extension === 'xlsx'
        ? sam_bulk_read_xlsx($file['tmp_name'])
        : sam_bulk_read_csv($file['tmp_name']);
    $_SESSION['bulk_result'] = sam_bulk_import_students($pdo, $rows);
} catch (Throwable $e) {
    $_SESSION['bulk_result'] = ['error' => $e->getMessage()];
}

header('Location: student_register.php?tab=bulk');
exit;










