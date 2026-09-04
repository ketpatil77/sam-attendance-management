<?php 
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();

// Function to calculate time difference
function calculateDuration($inTime, $outTime, $date) {
    if (!$inTime || !$outTime) return '-';
    
    $start = new DateTime("$date $inTime");
    $end = new DateTime("$date $outTime");
    
    if ($end < $start) $end->modify('+1 day');
    
    $interval = $start->diff($end);
    return sprintf("%dh %02dm", $interval->h, $interval->i);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report with Duration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <style>
        th, td { text-align: center; vertical-align: middle; }
        .present { background-color: #e8f5e9; }
        .absent { background-color: #ffebee; }
        .duration { font-weight: bold; color: #14b8a6; }
        @media print {
            .no-print { display: none !important; }
            .table { font-size: 11px; }
            .present { background-color: #e8f5e9 !important; }
        }
    </style>
</head>
<body class="sam-body">
<div class="sam-shell"><div class="sam-page">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3>â± Attendance Duration Report</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <select name="dept" class="form-select" required>
                <option value="">-- Select Department --</option>
                <?php foreach(['AI','CE','EE','ME','CT','EJ'] as $dept): ?>
                <option value="<?= $dept ?>" <?= ($_POST['dept']??'')==$dept?'selected':'' ?>><?= $dept ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="year" class="form-select" required>
                <option value="">-- Select Year --</option>
                <?php foreach(['FY','SY','TY'] as $year): ?>
                <option value="<?= $year ?>" <?= ($_POST['year']??'')==$year?'selected':'' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?= $_POST['date']??date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4 d-grid">
            <button class="btn btn-primary">Generate Report</button>
        </div>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): 
        $dept = $_POST['dept'];
        $year = $_POST['year'];
        $date = $_POST['date'];
        $formattedDate = date('d M Y', strtotime($date));

        // Get students
        $stmt = $pdo->prepare("SELECT * FROM student_information 
                              WHERE DEPARTMENT = ? AND AYEAR = ? 
                              ORDER BY STUDENT_NAME");
        $stmt->execute([$dept, $year]);
        $students = $stmt->fetchAll();

        // Process attendance
        $results = [];
        $presentCount = 0;
        $totalDuration = 0;

        foreach ($students as $student) {
            $attendance = $pdo->prepare("SELECT * FROM student_daily 
                                        WHERE ERN_NO = ? AND EDATE = ? 
                                        ORDER BY INTIME");
            $attendance->execute([$student['ERN_NO'], $date]);
            $records = $attendance->fetchAll();

            $isPresent = !empty($records);
            if ($isPresent) {
                $presentCount++;
                $first = $records[0];
                $last = end($records);
                $duration = calculateDuration($first['INTIME']??null, $last['OUTTIME']??null, $date);
                
                if ($duration != '-') {
                    $parts = explode(' ', $duration);
                    $totalDuration += (int)$parts[0] + ((int)$parts[1] / 60);
                }
            }

            $results[] = [
                'student' => $student,
                'present' => $isPresent,
                'inTime' => $isPresent ? ($first['INTIME']??'-') : '-',
                'outTime' => $isPresent ? ($last['OUTTIME']??'-') : '-',
                'duration' => $isPresent ? $duration : '-'
            ];
        }
    ?>

    <h4 class="mb-3">Attendance for <?= $formattedDate ?></h4>
    
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>ERN</th>
                    <th>Department</th>
                    <th>Year</th>
                    <th>IN Time</th>
                    <th>OUT Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): 
                    $s = $row['student'];
                ?>
                <tr class="<?= $row['present'] ? 'present' : 'absent' ?>">
                    <td>
                        <?php if ($s['PHOTO']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($s['PHOTO']) ?>" height="50">
                        <?php else: ?>
                        <span class="text-muted">No Photo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['STUDENT_NAME']) ?></td>
                    <td><?= htmlspecialchars($s['ERN_NO']) ?></td>
                    <td><?= htmlspecialchars($s['DEPARTMENT']) ?></td>
                    <td><?= htmlspecialchars($s['AYEAR']) ?></td>
                    <td><?= $row['inTime'] ?></td>
                    <td><?= $row['outTime'] ?></td>
                    <td class="duration"><?= $row['duration'] ?></td>
                    <td class="<?= $row['present'] ? 'text-success' : 'text-danger' ?>">
                        <strong><?= $row['present'] ? 'P' : 'A' ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card mb-4">
        <div class="card-body text-center">
            <div class="row">
                <div class="col-md-4">
                    <h5>Total Students: <strong><?= count($students) ?></strong></h5>
                </div>
                <div class="col-md-4">
                    <h5 class="text-success">Present: <strong><?= $presentCount ?></strong> 
                    (<?= round(($presentCount/count($students))*100, 1) ?>%)</h5>
                </div>
                <div class="col-md-4">
                    <h5 class="text-danger">Absent: <strong><?= count($students)-$presentCount ?></strong> 
                    (<?= round(((count($students)-$presentCount)/count($students))*100, 1) ?>%)</h5>
                </div>
            </div>
            
            <?php if ($presentCount > 0): 
                $avgHours = floor($totalDuration/$presentCount);
                $avgMins = round(($totalDuration/$presentCount - $avgHours) * 60);
            ?>
            <div class="mt-3">
                <h5>Average Duration: <strong><?= "$avgHours h $avgMins m" ?></strong></h5>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="no-print text-center">
        <button class="btn btn-dark me-2" onclick="window.print()">ðŸ–¨ï¸ Print Report</button>
        <button class="btn btn-outline-primary" onclick="exportToExcel()">ðŸ“ Export to Excel</button>
    </div>

    <script>
    function exportToExcel() {
        const html = `
        <html>
            <head>
                <style>
                    th, td { text-align: center; border: 1px solid #ddd; padding: 5px; }
                    .present { background-color: #e8f5e9; }
                    .duration { font-weight: bold; color: #14b8a6; }
                </style>
            </head>
            <body>
                <h2>Attendance Report for <?= $formattedDate ?></h2>
                ${document.querySelector('.table').outerHTML}
            </body>
        </html>`;
        
        const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'Attendance_<?= $date ?>.xls';
        link.click();
    }
    </script>
    <?php endif; ?>
</div></div>
</body>
</html>











