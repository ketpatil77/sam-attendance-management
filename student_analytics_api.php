<?php
require_once 'app_ui.php';
require_once 'student_analytics_lib.php';

sam_require_admin();

$action = (string)($_GET['action'] ?? 'student');

try {
    if ($action === 'suggest') {
        header('Content-Type: application/json; charset=UTF-8');
        $query = (string)($_GET['q'] ?? '');
        echo json_encode([
            'items' => sam_get_student_search_matches($pdo, $query),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $rid = (int)($_GET['rid'] ?? 0);
    if ($rid <= 0) {
        throw new RuntimeException('Student RID is required.');
    }

    $payload = sam_get_student_analytics_payload($pdo, $rid);

    if ($action === 'csv') {
        $month = trim((string)($_GET['month'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $rows = sam_filter_student_log($payload['log'], $month, $status);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student-attendance-' . $rid . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Date', 'Day', 'Status', 'Check-in Time', 'Check-out Time', 'Late Minutes']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['date'],
                $row['day'],
                $row['status'],
                $row['check_in'],
                $row['check_out'],
                $row['late_minutes'],
            ]);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => $e->getMessage()]);
}










