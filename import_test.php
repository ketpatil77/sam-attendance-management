<?php
require 'db.php';
require 'bulk_students_lib.php';
try {
    $rows = sam_bulk_read_xlsx('C:\Users\Engineer\Downloads\student_bulk_template (4).xlsx');
    $result = sam_bulk_import_students($pdo, $rows);
    print_r($result);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}










