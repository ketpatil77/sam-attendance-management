<?php

require_once 'db.php';

const SAM_EARLY_OUT_SETTING_KEY = 'early_out_rule_enabled';
const SAM_EARLY_OUT_CUTOFF = '16:00:00';

function sam_get_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function sam_set_setting(PDO $pdo, string $key, string $value, ?string $updatedBy = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value, updated_at, updated_by)
         VALUES (?, ?, ?, ?)
         ON CONFLICT(setting_key) DO UPDATE SET
            setting_value = excluded.setting_value,
            updated_at = excluded.updated_at,
            updated_by = excluded.updated_by'
    );
    $stmt->execute([$key, $value, date('Y-m-d H:i:s'), $updatedBy]);
}

function sam_is_early_out_rule_enabled(PDO $pdo): bool
{
    return sam_get_setting($pdo, SAM_EARLY_OUT_SETTING_KEY, '1') !== '0';
}

function sam_student_has_early_out_exception(PDO $pdo, string $ern): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM early_out_exceptions WHERE ern_no = ? LIMIT 1');
    $stmt->execute([$ern]);
    return (bool)$stmt->fetchColumn();
}

function sam_get_early_out_exceptions(PDO $pdo, string $search = ''): array
{
    $sql = 'SELECT e.ern_no, e.student_name, e.reason, e.created_at, e.created_by
            FROM early_out_exceptions e
            WHERE 1 = 1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (e.ern_no LIKE ? OR e.student_name LIKE ? OR e.reason LIKE ?)';
        $term = '%' . $search . '%';
        $params = [$term, $term, $term];
    }

    $sql .= ' ORDER BY e.student_name, e.ern_no';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sam_find_student_for_exception(PDO $pdo, string $ern): ?array
{
    $stmt = $pdo->prepare('SELECT ERN_NO, STUDENT_NAME, DEPARTMENT, AYEAR FROM student_information WHERE ERN_NO = ? LIMIT 1');
    $stmt->execute([$ern]);
    $student = $stmt->fetch();
    return $student ?: null;
}

function sam_add_early_out_exception(PDO $pdo, string $ern, string $reason = '', ?string $createdBy = null): array
{
    $student = sam_find_student_for_exception($pdo, $ern);
    if (!$student) {
        throw new RuntimeException('Student ERN not found.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO early_out_exceptions (ern_no, student_name, reason, created_at, created_by)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT(ern_no) DO UPDATE SET
            student_name = excluded.student_name,
            reason = excluded.reason,
            created_at = excluded.created_at,
            created_by = excluded.created_by'
    );
    $stmt->execute([
        $student['ERN_NO'],
        $student['STUDENT_NAME'],
        $reason,
        date('Y-m-d H:i:s'),
        $createdBy,
    ]);

    return $student;
}

function sam_remove_early_out_exception(PDO $pdo, string $ern): void
{
    $stmt = $pdo->prepare('DELETE FROM early_out_exceptions WHERE ern_no = ?');
    $stmt->execute([$ern]);
}

function sam_can_mark_out_now(PDO $pdo, string $ern, ?string $currentTime = null): array
{
    $now = $currentTime ?? date('H:i:s');
    $ruleEnabled = sam_is_early_out_rule_enabled($pdo);
    $hasException = sam_student_has_early_out_exception($pdo, $ern);
    $afterCutoff = $now >= SAM_EARLY_OUT_CUTOFF;

    if ($afterCutoff || !$ruleEnabled || $hasException) {
        return [
            'allowed' => true,
            'rule_enabled' => $ruleEnabled,
            'has_exception' => $hasException,
            'cutoff' => SAM_EARLY_OUT_CUTOFF,
        ];
    }

    return [
        'allowed' => false,
        'rule_enabled' => true,
        'has_exception' => false,
        'cutoff' => SAM_EARLY_OUT_CUTOFF,
        'message' => 'You are not allowed to leave before 4 PM. Go to your class.',
    ];
}

function sam_get_today_attendance_kpis(PDO $pdo, ?string $date = null): array
{
    $today = $date ?? date('Y-m-d');

    $inCount = (int)$pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE EDATE = ?")->execute([$today]) ?: 0;
    $stmtIn = $pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE EDATE = ?");
    $stmtIn->execute([$today]);
    $totalIn = (int)$stmtIn->fetchColumn();

    $stmtOut = $pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE EDATE = ? AND OUTTIME IS NOT NULL");
    $stmtOut->execute([$today]);
    $totalOut = (int)$stmtOut->fetchColumn();

    $stmtInside = $pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE EDATE = ? AND STATUS = 'IN'");
    $stmtInside->execute([$today]);
    $stillInside = (int)$stmtInside->fetchColumn();

    $stmtExceptionCount = $pdo->query("SELECT COUNT(*) FROM early_out_exceptions");
    $exceptionCount = (int)$stmtExceptionCount->fetchColumn();

    $stmtExceptionUsed = $pdo->prepare(
        "SELECT COUNT(*)
         FROM student_daily sd
         JOIN early_out_exceptions e ON e.ern_no = sd.ERN_NO
         WHERE sd.EDATE = ?
         AND sd.OUTTIME IS NOT NULL
         AND sd.OUTTIME < ?"
    );
    $stmtExceptionUsed->execute([$today, SAM_EARLY_OUT_CUTOFF]);
    $exceptionUsedToday = (int)$stmtExceptionUsed->fetchColumn();

    return [
        'date' => $today,
        'total_in' => $totalIn,
        'total_out' => $totalOut,
        'still_inside' => $stillInside,
        'rule_enabled' => sam_is_early_out_rule_enabled($pdo),
        'exception_count' => $exceptionCount,
        'exception_used_today' => $exceptionUsedToday,
    ];
}

function sam_get_recent_in_activity(PDO $pdo, int $limit = 6, ?string $date = null): array
{
    $today = $date ?? date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT STUDENT_NAME, ERN_NO, DEPARTMENT, AYEAR, INTIME
         FROM student_daily
         WHERE EDATE = ?
         ORDER BY INTIME DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $today);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function sam_get_recent_out_activity(PDO $pdo, int $limit = 6, ?string $date = null): array
{
    $today = $date ?? date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT STUDENT_NAME, ERN_NO, DEPARTMENT, AYEAR, OUTTIME
         FROM student_daily
         WHERE EDATE = ? AND OUTTIME IS NOT NULL
         ORDER BY OUTTIME DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $today);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function sam_get_students_still_inside(PDO $pdo, int $limit = 20, ?string $date = null): array
{
    $today = $date ?? date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT STUDENT_NAME, ERN_NO, DEPARTMENT, AYEAR, INTIME
         FROM student_daily
         WHERE EDATE = ? AND STATUS = 'IN'
         ORDER BY INTIME ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $today);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function sam_get_exception_used_rows(PDO $pdo, int $limit = 50, ?string $date = null): array
{
    $today = $date ?? date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT sd.STUDENT_NAME, sd.ERN_NO, sd.DEPARTMENT, sd.AYEAR, sd.INTIME, sd.OUTTIME, e.reason
         FROM student_daily sd
         JOIN early_out_exceptions e ON e.ern_no = sd.ERN_NO
         WHERE sd.EDATE = ? AND sd.OUTTIME IS NOT NULL AND sd.OUTTIME < ?
         ORDER BY sd.OUTTIME DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $today);
    $stmt->bindValue(2, SAM_EARLY_OUT_CUTOFF);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}











