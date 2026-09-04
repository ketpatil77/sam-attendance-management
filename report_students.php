<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Master Report</title>
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
        <h3>ðŸ§‘â€ðŸŽ“ Student Information Report</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <!-- Filter Form -->
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

        $stmt = $pdo->prepare("SELECT * FROM student_information WHERE DEPARTMENT = ? AND AYEAR = ? ORDER BY STUDENT_NAME");
        $stmt->execute([$dept, $year]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered table-striped'>";
        echo "<thead class='table-dark'><tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Student ID</th>
            <th>ERN</th>
            <th>Department</th>
            <th>Year</th>
            <th>Batch</th>
            <th>Mobile</th>
            <th>Parent Mobile</th>
            <th>City</th>
            <th>Address</th>
        </tr></thead><tbody>";

        foreach ($students as $row) {
            echo "<tr>";
            echo "<td>";
            if ($row['PHOTO']) {
                echo "<img src='data:image/jpeg;base64," . base64_encode($row['PHOTO']) . "' height='50'>";
            } else {
                echo "<span class='text-muted'>No Photo</span>";
            }
            echo "</td>";
            echo "<td>{$row['STUDENT_NAME']}</td>";
            echo "<td>{$row['STUDENT_ID']}</td>";
            echo "<td>{$row['ERN_NO']}</td>";
            echo "<td>{$row['DEPARTMENT']}</td>";
            echo "<td>{$row['AYEAR']}</td>";
            echo "<td>{$row['BATCH']}</td>";
            echo "<td>{$row['MOBILE']}</td>";
            echo "<td>{$row['PARENT_MOB']}</td>";
            echo "<td>{$row['CITY']}</td>";
            echo "<td>{$row['ADDRESS']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
        echo "</div>";
        echo "<button class='btn btn-dark no-print mt-2' onclick='window.print()'>ðŸ–¨ï¸ Print</button>";
    }
    ?>
</div></div>
</body>
</html>











