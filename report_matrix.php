<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Matrix Report</title>
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
        <h3>ðŸ“… Attendance Matrix Report</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <!-- Filter Form -->
    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <label>From Date</label>
            <input type="date" name="from" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label>To Date</label>
            <input type="date" name="to" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label>Department</label>
            <select name="dept" class="form-select">
                <option value="">All</option>
                         <option>CT</option><option>EE</option><option>ME</option><option>EJ</option><option>AI</option><option>CE</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>Academic Year</label>
            <select name="year" class="form-select">
                <option value="">All</option>
                   <option>FY</option><option>SY</option><option>TY</option>
            </select>
        </div>
        <div class="col-md-2 d-grid align-items-end">
            <button class="btn btn-primary">Generate Report</button>
        </div>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $from = $_POST['from'];
        $to = $_POST['to'];
        $dept = $_POST['dept'];
        $year = $_POST['year'];

        // Generate date range
        $dates = [];
        $start = new DateTime($from);
        $end = new DateTime($to);
        while ($start <= $end) {
            $dates[] = clone $start;
            $start->modify('+1 day');
        }

        // Build student filter
        $filter = "WHERE 1=1";
        $params = [];

        if ($dept) {
            $filter .= " AND si.DEPARTMENT = ?";
            $params[] = $dept;
        }

        if ($year) {
            $filter .= " AND si.AYEAR = ?";
            $params[] = $year;
        }

        $query = "SELECT si.STUDENT_NAME, si.ERN_NO 
                  FROM student_information si 
                  $filter 
                  ORDER BY si.STUDENT_NAME";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'><tr><th>NAME</th><th>ERN</th>";

        foreach ($dates as $date) {
            echo "<th>" . $date->format('j-M') . "</th>";
        }
        echo "</tr></thead><tbody>";

        foreach ($students as $student) {
            echo "<tr><td>{$student['STUDENT_NAME']}</td><td>{$student['ERN_NO']}</td>";

            foreach ($dates as $date) {
                $formatted = $date->format('Y-m-d');
                $day = $date->format('l');

                if ($day === 'Sunday') {
                    echo "<td class='text-muted'>SUNDAY</td>";
                    continue;
                }

                $check = $pdo->prepare("SELECT COUNT(*) FROM student_daily 
                                        WHERE ERN_NO = ? AND EDATE = ?");
                $check->execute([$student['ERN_NO'], $formatted]);
                $present = $check->fetchColumn();

                echo "<td>" . ($present ? 'P' : 'A') . "</td>";
            }

            echo "</tr>";
        }

        echo "</tbody></table>";
        echo "</div>";
        echo "<button class='btn btn-dark no-print' onclick='window.print()'>ðŸ–¨ï¸ Print</button>";
    }
    ?>
</div></div>
</body>
</html>











