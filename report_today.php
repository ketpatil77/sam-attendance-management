<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Today's Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/sam-ui.css">
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
        <h3>ðŸ“† Today's Attendance Report (<?= date("d M Y") ?>)</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-4">
            <label>Department</label>
            <select name="dept" class="form-select" required>
                <option value="">-- Select Department --</option>
                         <option>CT</option><option>EE</option><option>ME</option><option>EJ</option><option>AI</option><option>CE</option>
            </select>
        </div>
        <div class="col-md-4">
            <label>Academic Year</label>
            <select name="year" class="form-select" required>
                <option value="">-- Select Year --</option>
                  <option>FY</option><option>SY</option><option>TY</option>
            </select>
        </div>
        <div class="col-md-4 d-grid align-items-end">
            <button class="btn btn-primary">Generate Report</button>
        </div>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dept = $_POST['dept'];
        $year = $_POST['year'];
        $today = date('Y-m-d');

        $stmt = $pdo->prepare("SELECT * FROM student_information WHERE DEPARTMENT = ? AND AYEAR = ? ORDER BY STUDENT_NAME");
        $stmt->execute([$dept, $year]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStudents = count($students);
        $presentStudents = 0;
        
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'><tr>
                <th>Photo</th>
                <th>Name</th>
                <th>ERN</th>
                <th>Department</th>
                <th>Year</th>
                <th>Status</th>
              </tr></thead><tbody>";

        foreach ($students as $student) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM student_daily WHERE ERN_NO = ? AND EDATE = ?");
            $check->execute([$student['ERN_NO'], $today]);
            $isPresent = $check->fetchColumn() > 0;
            if ($isPresent) $presentStudents++;

            echo "<tr>";
            echo "<td>";
            echo $student['PHOTO'] ? "<img src='data:image/jpeg;base64," . base64_encode($student['PHOTO']) . "' height='50'>" : "<span class='text-muted'>No Photo</span>";
            echo "</td>";
            echo "<td>{$student['STUDENT_NAME']}</td>";
            echo "<td>{$student['ERN_NO']}</td>";
            echo "<td>{$student['DEPARTMENT']}</td>";
            echo "<td>{$student['AYEAR']}</td>";
            echo "<td class='" . ($isPresent ? "text-success" : "text-danger") . "'><strong>" . ($isPresent ? "P" : "A") . "</strong></td>";
            echo "</tr>";
        }
        
        $absentStudents = $totalStudents - $presentStudents;

        echo "</tbody></table>";
        echo "</div>";
        
        echo "<div class='mt-3 p-3 border rounded bg-white text-center'>";
        echo "<h5>Total Students: <strong>$totalStudents</strong></h5>";
        echo "<h5 class='text-success'>Present Students: <strong>$presentStudents</strong></h5>";
        echo "<h5 class='text-danger'>Absent Students: <strong>$absentStudents</strong></h5>";
        echo "</div>";
        
        echo "<button class='btn btn-dark no-print mt-3' onclick='window.print()'>ðŸ–¨ï¸ Print</button>";
    }
    ?>
</div></div>
</body>
</html>











