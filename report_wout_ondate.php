<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report - Left Without Out Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/sam-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        th, td { text-align: center; vertical-align: middle; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="sam-body">
<div class="sam-shell"><div class="sam-page">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3>ðŸ“† Attendance Report - Left Without Out Attendance</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <label>Select Date</label>
            <input type="date" name="date" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label>Department</label>
            <select name="dept" class="form-select" required>
                <option value="">-- Select Department --</option>
                   <option>CT</option><option>EE</option><option>ME</option><option>EJ</option><option>AI</option><option>CE</option>
            </select>
        </div>
        <div class="col-md-3">
            <label>Academic Year</label>
            <select name="year" class="form-select" required>
                <option value="">-- Select Year --</option>
                 <option>FY</option><option>SY</option><option>TY</option>
            </select>
        </div>
        <div class="col-md-3 d-grid align-items-end">
            <button class="btn btn-primary">Generate Report</button>
        </div>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $date = $_POST['date'];
        $dept = $_POST['dept'];
        $year = $_POST['year'];

        $stmt = $pdo->prepare("SELECT * FROM student_daily WHERE EDATE = ? AND OUTTIME IS NULL AND DEPARTMENT = ? AND AYEAR = ?");
        $stmt->execute([$date, $dept, $year]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'><tr><th>Name</th><th>ERN</th><th>Department</th><th>Year</th><th>In Time</th><th>Status</th></tr></thead><tbody>";

        foreach ($students as $student) {
            echo "<tr>";
            echo "<td>{$student['STUDENT_NAME']}</td>";
            echo "<td>{$student['ERN_NO']}</td>";
            echo "<td>{$student['DEPARTMENT']}</td>";
            echo "<td>{$student['AYEAR']}</td>";
            echo "<td>{$student['INTIME']}</td>";
            echo "<td class='text-warning'><strong>Left Without Out Attendance</strong></td>";
            echo "</tr>";
        }

        echo "</tbody></table></div>";
        echo "<button class='btn btn-dark no-print mt-3' onclick='window.print()'>ðŸ–¨ï¸ Print</button>";
    }
    ?>
</div></div>
</body>
</html>











