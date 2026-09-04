<?php

require_once 'db.php';

const SAM_LATE_CUTOFF = '07:30:00';

function sam_get_student_search_matches(PDO $pdo, string $query, int $limit = 8): array
{
    $term = trim($query);
    if ($term === '') {
        return [];
    }

    $like = '%' . $term . '%';
    $stmt = $pdo->prepare(
        "SELECT RID, STUDENT_NAME, STUDENT_ID, ERN_NO, AYEAR, BATCH, DEPARTMENT
         FROM student_information
         WHERE STUDENT_NAME LIKE ?
            OR STUDENT_ID LIKE ?
            OR ERN_NO LIKE ?
         ORDER BY
            CASE
                WHEN STUDENT_ID = ? THEN 0
                WHEN ERN_NO = ? THEN 1
                WHEN STUDENT_NAME LIKE ? THEN 2
                ELSE 3
            END,
            STUDENT_NAME ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $like);
    $stmt->bindValue(4, $term);
    $stmt->bindValue(5, $term);
    $stmt->bindValue(6, $term . '%');
    $stmt->bindValue(7, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sam_get_student_for_analytics(PDO $pdo, int $rid): ?array
{
    $stmt = $pdo->prepare(
        "SELECT RID, STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, PHOTO, MOBILE, PARENT_MOB
         FROM student_information
         WHERE RID = ?
         LIMIT 1"
    );
    $stmt->execute([$rid]);
    $student = $stmt->fetch();

    return $student ?: null;
}

function sam_get_student_analytics_payload(PDO $pdo, int $rid): array
{
    $student = sam_get_student_for_analytics($pdo, $rid);
    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    $workingDates = sam_get_student_working_dates($pdo, $student);
    $attendanceRows = sam_get_student_attendance_rows($pdo, $student);

    return sam_build_student_analytics($student, $workingDates, $attendanceRows);
}

function sam_get_student_working_dates(PDO $pdo, array $student): array
{
    $sql = "SELECT DISTINCT EDATE
            FROM student_daily
            WHERE DEPARTMENT = ?
              AND AYEAR = ?";
    $params = [
        $student['DEPARTMENT'] ?? '',
        $student['AYEAR'] ?? '',
    ];

    $batch = trim((string)($student['BATCH'] ?? ''));
    if ($batch !== '') {
        $sql .= " AND BATCH = ?";
        $params[] = $batch;
    }

    $sql .= " ORDER BY EDATE ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($dates) {
        return array_values(array_filter(array_map('strval', $dates)));
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT EDATE
         FROM student_daily
         WHERE RID = ? OR ERN_NO = ?
         ORDER BY EDATE ASC"
    );
    $stmt->execute([(int)$student['RID'], (string)$student['ERN_NO']]);

    return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function sam_get_student_attendance_rows(PDO $pdo, array $student): array
{
    $stmt = $pdo->prepare(
        "SELECT EDATE, INTIME, OUTTIME, STATUS, ERN_NO, STUDENT_NAME
         FROM student_daily
         WHERE RID = ? OR ERN_NO = ?
         ORDER BY EDATE ASC, INTIME ASC"
    );
    $stmt->execute([(int)$student['RID'], (string)$student['ERN_NO']]);

    return $stmt->fetchAll();
}

function sam_build_student_analytics(array $student, array $workingDates, array $attendanceRows): array
{
    $byDate = [];
    foreach ($attendanceRows as $row) {
        $date = (string)$row['EDATE'];
        if (!isset($byDate[$date])) {
            $byDate[$date] = $row;
        }
    }

    $logRows = [];
    $presentDays = 0;
    $absentDays = 0;
    $lateDays = 0;
    $lateMinutesSum = 0;
    $lateDaysOfWeek = [];

    foreach ($workingDates as $date) {
        $record = $byDate[$date] ?? null;
        $dayName = (new DateTimeImmutable($date))->format('D');
        $monthKey = substr($date, 0, 7);

        if ($record) {
            $recordStatus = trim((string)$record['STATUS']);
            if ($recordStatus === 'Absent') {
                $status = 'Absent';
                $lateMinutes = 0;
            } else {
                $lateMinutes = sam_calculate_late_minutes((string)$record['INTIME']);
                $status = $lateMinutes > 0 ? 'Late' : 'Present';
            }

            if ($status === 'Absent') {
                $absentDays++;
            } elseif ($status === 'Late') {
                $lateDays++;
                $lateMinutesSum += $lateMinutes;
                $lateDaysOfWeek[$dayName] = ($lateDaysOfWeek[$dayName] ?? 0) + 1;
            } else {
                $presentDays++;
            }

            $logRows[] = [
                'date' => $date,
                'day' => $dayName,
                'status' => $status,
                'check_in' => $status === 'Absent' ? '-' : (string)$record['INTIME'],
                'check_out' => $status === 'Absent' ? '-' : ($record['OUTTIME'] !== null ? (string)$record['OUTTIME'] : '-'),
                'month' => $monthKey,
                'late_minutes' => $lateMinutes,
            ];
        } else {
            $absentDays++;
            $logRows[] = [
                'date' => $date,
                'day' => $dayName,
                'status' => 'Absent',
                'check_in' => '-',
                'check_out' => '-',
                'month' => $monthKey,
                'late_minutes' => 0,
            ];
        }
    }

    $totalWorkingDays = count($workingDates);
    $attendedDays = $presentDays + $lateDays;
    $attendancePercent = $totalWorkingDays > 0 ? round(($attendedDays / $totalWorkingDays) * 100, 2) : 0.0;
    $monthly = sam_build_monthly_summary($logRows);
    $weeklyTrend = sam_build_weekly_trend($logRows);
    $lateTrend = sam_build_late_trend($logRows);
    $heatmap = sam_build_heatmap($logRows);
    $streaks = sam_calculate_streaks($logRows);

    arsort($lateDaysOfWeek);
    $frequentLateDay = $lateDaysOfWeek ? array_key_first($lateDaysOfWeek) : 'No repeated late pattern';

    return [
        'profile' => [
            'rid' => (int)$student['RID'],
            'name' => (string)$student['STUDENT_NAME'],
            'student_id' => (string)$student['STUDENT_ID'],
            'class' => (string)$student['AYEAR'],
            'division' => (string)($student['BATCH'] ?: '-'),
            'roll_no' => (string)$student['ERN_NO'],
            'department' => (string)($student['DEPARTMENT'] ?: '-'),
            'contact' => (string)($student['MOBILE'] ?: '-'),
            'parent_contact' => (string)($student['PARENT_MOB'] ?: '-'),
            'photo' => !empty($student['PHOTO']) ? base64_encode($student['PHOTO']) : null,
        ],
        'summary' => [
            'late_cutoff' => SAM_LATE_CUTOFF,
            'total_working_days' => $totalWorkingDays,
            'days_present' => $presentDays,
            'days_absent' => $absentDays,
            'days_late' => $lateDays,
            'attendance_percent' => $attendancePercent,
            'attendance_band' => sam_attendance_band($attendancePercent),
        ],
        'log' => array_reverse($logRows),
        'months' => array_values(array_keys($monthly)),
        'charts' => [
            'monthly' => $monthly,
            'ratio' => [
                'Present' => $presentDays,
                'Absent' => $absentDays,
                'Late' => $lateDays,
            ],
            'heatmap' => $heatmap,
            'trend' => $weeklyTrend,
            'late_trend' => $lateTrend,
        ],
        'late_analysis' => [
            'average_late_minutes' => $lateDays > 0 ? round($lateMinutesSum / $lateDays, 2) : 0,
            'most_frequent_late_day' => $frequentLateDay,
            'current_absence_streak' => $streaks['current_absence_streak'],
            'longest_absence_streak' => $streaks['longest_absence_streak'],
            'current_late_streak' => $streaks['current_late_streak'],
        ],
        'meta' => [
            'generated_at' => date('Y-m-d H:i:s'),
        ],
    ];
}

function sam_calculate_late_minutes(string $checkInTime): int
{
    if ($checkInTime === '') {
        return 0;
    }

    $cutoff = DateTimeImmutable::createFromFormat('H:i:s', SAM_LATE_CUTOFF);
    $checkIn = DateTimeImmutable::createFromFormat('H:i:s', $checkInTime);
    if (!$cutoff || !$checkIn) {
        return 0;
    }

    $cutoffTs = (int)$cutoff->format('U');
    $checkInTs = (int)$checkIn->format('U');

    if ($checkInTs <= $cutoffTs) {
        return 0;
    }

    return (int)floor(($checkInTs - $cutoffTs) / 60);
}

function sam_build_monthly_summary(array $logRows): array
{
    $months = [];
    foreach ($logRows as $row) {
        $month = (string)$row['month'];
        if (!isset($months[$month])) {
            $months[$month] = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
        }
        $months[$month][$row['status']]++;
    }

    return $months;
}

function sam_build_weekly_trend(array $logRows): array
{
    $weeks = [];
    foreach ($logRows as $row) {
        $date = new DateTimeImmutable($row['date']);
        $weekKey = $date->format('o-\WW');
        if (!isset($weeks[$weekKey])) {
            $weeks[$weekKey] = ['working' => 0, 'attended' => 0];
        }
        $weeks[$weekKey]['working']++;
        if ($row['status'] !== 'Absent') {
            $weeks[$weekKey]['attended']++;
        }
    }

    $result = [];
    foreach ($weeks as $label => $values) {
        $result[] = [
            'label' => $label,
            'percent' => $values['working'] > 0 ? round(($values['attended'] / $values['working']) * 100, 2) : 0,
        ];
    }

    return $result;
}

function sam_build_late_trend(array $logRows): array
{
    $result = [];
    foreach ($logRows as $row) {
        if ($row['late_minutes'] > 0) {
            $result[] = [
                'date' => $row['date'],
                'minutes' => $row['late_minutes'],
            ];
        }
    }

    return $result;
}

function sam_build_heatmap(array $logRows): array
{
    $statusMap = [];
    $maxYear = null;
    foreach ($logRows as $row) {
        $year = (int)substr($row['date'], 0, 4);
        $maxYear = $maxYear === null ? $year : max($maxYear, $year);
        $statusMap[$row['date']] = $row['status'];
    }

    $year = $maxYear ?: (int)date('Y');
    $start = new DateTimeImmutable($year . '-01-01');
    $end = new DateTimeImmutable($year . '-12-31');
    $days = [];

    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $days[] = [
            'date' => $key,
            'status' => $statusMap[$key] ?? 'None',
        ];
    }

    return [
        'year' => $year,
        'days' => $days,
    ];
}

function sam_calculate_streaks(array $logRows): array
{
    $ordered = array_values($logRows);
    $longestAbsence = 0;
    $currentAbsence = 0;
    $currentLate = 0;
    $runningAbsence = 0;

    foreach ($ordered as $row) {
        if ($row['status'] === 'Absent') {
            $runningAbsence++;
            $longestAbsence = max($longestAbsence, $runningAbsence);
        } else {
            $runningAbsence = 0;
        }
    }

    for ($i = count($ordered) - 1; $i >= 0; $i--) {
        if ($ordered[$i]['status'] === 'Absent') {
            $currentAbsence++;
        } else {
            break;
        }
    }

    for ($i = count($ordered) - 1; $i >= 0; $i--) {
        if ($ordered[$i]['status'] === 'Late') {
            $currentLate++;
        } else {
            break;
        }
    }

    return [
        'current_absence_streak' => $currentAbsence,
        'longest_absence_streak' => $longestAbsence,
        'current_late_streak' => $currentLate,
    ];
}

function sam_attendance_band(float $percent): string
{
    if ($percent >= 75) {
        return 'success';
    }
    if ($percent >= 60) {
        return 'warning';
    }
    return 'danger';
}

function sam_filter_student_log(array $logRows, string $month = '', string $status = ''): array
{
    return array_values(array_filter($logRows, static function (array $row) use ($month, $status): bool {
        if ($month !== '' && $row['month'] !== $month) {
            return false;
        }
        if ($status !== '' && !hash_equals($status, $row['status'])) {
            return false;
        }
        return true;
    }));
}











