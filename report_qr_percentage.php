<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Students Attendance % Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        th, td { text-align: center; vertical-align: middle; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="sam-body">
<div class="sam-shell"><div class="sam-page">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3>ðŸ“Š Attendance % Report for All Students</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <!-- Filter Form -->
    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <label>Department</label>
            <select name="dept" class="form-select" required>
                <option value="">Select</option>
              <option>CT</option><option>EE</option><option>ME</option><option>EJ</option><option>AI</option><option>CE</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>Academic Year</label>
            <select name="year" class="form-select" required>
                <option value="">Select</option>
              <option>FY</option><option>SY</option><option>TY</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>From Date</label>
            <input type="date" name="from" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label>To Date</label>
            <input type="date" name="to" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label>Exclusion Dates (comma separated)</label>
            <input type="text" name="exclude" class="form-control" placeholder="YYYY-MM-DD,YYYY-MM-DD">
        </div>
        <div class="col-md-12 d-grid">
            <button class="btn btn-primary">Generate Report</button>
        </div>
    </form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'):
    $dept = $_POST['dept'];
    $year = $_POST['year'];
    $from = $_POST['from'];
    $to = $_POST['to'];
    $exclude = array_filter(array_map('trim', explode(',', $_POST['exclude'] ?? '')));

    // Build working dates list
    $start = new DateTime($from);
    $end = new DateTime($to);
    $end->modify('+1 day');
    $range = new DatePeriod($start, new DateInterval('P1D'), $end);

    $workingDates = [];
    foreach ($range as $date) {
        $d = $date->format('Y-m-d');
        if ($date->format('N') != 7 && !in_array($d, $exclude)) {
            $workingDates[] = $d;
        }
    }

    $totalDays = count($workingDates);

    $stmt = $pdo->prepare("SELECT * FROM student_information WHERE DEPARTMENT = ? AND AYEAR = ?");
    $stmt->execute([$dept, $year]);
    $students = $stmt->fetchAll();

    $labels = [];
    $percentages = [];

    echo "<div class='table-responsive'>";
    echo "<table class='table table-bordered'>";
    echo "<thead class='table-dark'><tr>
        <th>Photo</th><th>Name</th><th>ERN</th><th>Present</th><th>Absent</th><th>Percentage</th>
    </tr></thead><tbody>";

    foreach ($students as $student) {
        $present = 0;
        foreach ($workingDates as $date) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE ERN_NO = ? AND EDATE = ?");
            $chk->execute([$student['ERN_NO'], $date]);
            if ($chk->fetchColumn() > 0) {
                $present++;
            }
        }
        $absent = $totalDays - $present;
        $percent = $totalDays > 0 ? round(($present / $totalDays) * 100, 2) : 0;

        $labels[] = $student['STUDENT_NAME'];
        $percentages[] = $percent;

        echo "<tr>";
        echo "<td>" . ($student['PHOTO'] ? "<img src='data:image/jpeg;base64," . base64_encode($student['PHOTO']) . "' height='50'>" : "No Photo") . "</td>";
        echo "<td>{$student['STUDENT_NAME']}</td>";
        echo "<td>{$student['ERN_NO']}</td>";
        echo "<td>$present</td>";
        echo "<td>$absent</td>";
        echo "<td><strong>$percent%</strong></td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
?>

    <!-- Bar Chart -->
    <div class="mt-4">
        <canvas id="percentChart" height="100"></canvas>
    </div>

    <script>
    const ctx = document.getElementById('percentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Attendance %',
                data: <?= json_encode($percentages) ?>,
                backgroundColor: '#14b8a6'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
    </script>

    <button onclick="window.print()" class="btn btn-dark mt-3 no-print">ðŸ–¨ï¸ Print</button>
<?php endif; ?>
</div></div>
</body>
</html>











