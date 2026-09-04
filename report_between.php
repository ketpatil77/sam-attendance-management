<?php
require 'db.php';
require_once 'app_ui.php';
sam_require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report - Between Dates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/sam-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            table { font-size: 12px; }
        }
    </style>
</head>
<body class="sam-body">
<div class="sam-shell"><div class="sam-page">
    <div class="d-flex justify-content-between align-items-center no-print mb-4">
        <h3>ðŸ“… Attendance Report - Between Two Dates</h3>
        <a href="index.php" class="btn btn-outline-secondary">âŒ‚ Home</a>
    </div>

    <!-- Search Form -->
    <form method="post" class="row g-3 mb-4 no-print">
        <div class="col-md-3">
            <label>From Date</label>
            <input type="date" name="from" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label>To Date</label>
            <input type="date" name="to" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label>Department</label>
            <select name="dept" class="form-select">
                <option value="">All</option>
                          <option>CT</option><option>EE</option><option>ME</option><option>EJ</option><option>AI</option><option>CE</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>Year</label>
            <select name="year" class="form-select">
                <option value="">All</option>
           <option>FY</option><option>SY</option><option>TY</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <label>&nbsp;</label>
            <button class="btn btn-primary">ðŸ” Search</button>
        </div>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php
        $from = $_POST['from'];
        $to = $_POST['to'];
        $dept = $_POST['dept'];
        $year = $_POST['year'];

        $query = "SELECT * FROM student_daily WHERE EDATE BETWEEN ? AND ?";
        $params = [$from, $to];

        if (!empty($dept)) {
            $query .= " AND DEPARTMENT = ?";
            $params[] = $dept;
        }

        if (!empty($year)) {
            $query .= " AND AYEAR = ?";
            $params[] = $year;
        }

        $query .= " ORDER BY EDATE DESC, INTIME";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        ?>

        <div class="mb-3">
            <h5>Showing records from <strong><?= $from ?></strong> to <strong><?= $to ?></strong></h5>
            <button class="btn btn-outline-dark no-print" onclick="window.print()">ðŸ–¨ï¸ Print</button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>ERN</th>
                        <th>IN Time</th>
                        <th>OUT Time</th>
                        <th>Department</th>
                        <th>Year</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['EDATE']) ?></td>
                            <td><?= htmlspecialchars($row['STUDENT_NAME']) ?></td>
                            <td><?= htmlspecialchars($row['ERN_NO']) ?></td>
                            <td><?= htmlspecialchars($row['INTIME']) ?></td>
                            <td><?= htmlspecialchars($row['OUTTIME'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['DEPARTMENT']) ?></td>
                            <td><?= htmlspecialchars($row['AYEAR']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">No records found for selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div></div>
</body>
</html>











