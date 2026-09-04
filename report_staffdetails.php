<?php
require_once 'db.php';
require_once 'app_ui.php';
sam_require_admin();

// Initialize search parameters
$search = '';
$department = '';
$designation = '';

// Check if search form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $designation = isset($_GET['designation']) ? $_GET['designation'] : '';
}

// Build SQL query with filters
try {
    $sql = "SELECT * FROM staff_information WHERE 1=1";
    $params = [];
    
    // Add search conditions
    if (!empty($search)) {
        $sql .= " AND (NAME LIKE ? OR EID = ? OR MOBILE LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $search; // For EID exact match
        $params[] = $searchTerm;
    }
    
    if (!empty($department)) {
        $sql .= " AND DEPARTMENT = ?";
        $params[] = $department;
    }
    
    if (!empty($designation)) {
        $sql .= " AND DESIGNATION = ?";
        $params[] = $designation;
    }
    
    $sql .= " ORDER BY NAME ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching staff data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Information</title>
    <?= sam_theme_boot_script_tag() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <style>
        .photo-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }
        .search-card {
            background-color: transparent;
            border-radius: 16px;
            padding: 10px;
            margin-bottom: 16px;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body class="sam-body">
    <div class="sam-shell">
        <div class="sam-page">
        <div class="sam-topbar">
            <div class="sam-title-block">
                <div class="sam-kicker">Staff Report</div>
                <h1 class="sam-title">Staff Information</h1>
                <p class="sam-subtitle">Search by staff name, EID, department, or designation and browse all stored staff photo records.</p>
            </div>
            <div class="sam-actions">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house-door me-2"></i>Home</a>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Dashboard</a>
                <a href="staff.php" class="btn btn-outline-primary"><i class="bi bi-person-badge me-2"></i>Staff Registration</a>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                <?= sam_theme_toggle_html() ?>
            </div>
        </div>
        
        <!-- Search Form -->
        <div class="card search-card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search (Name/EID/Mobile)</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" placeholder="Search...">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <option value="AI" <?php echo $department == 'AI' ? 'selected' : ''; ?>>AI</option>
                            <option value="CE" <?php echo $department == 'CE' ? 'selected' : ''; ?>>CE</option>
                            <option value="EE" <?php echo $department == 'EE' ? 'selected' : ''; ?>>EE</option>
                            <option value="ME" <?php echo $department == 'ME' ? 'selected' : ''; ?>>ME</option>
                            <option value="EJ" <?php echo $department == 'EJ' ? 'selected' : ''; ?>>EJ</option>
                            <option value="CT" <?php echo $department == 'CT' ? 'selected' : ''; ?>>CT</option>
                            <option value="APPLIED SCIENCE" <?php echo $department == 'APPLIED SCIENCE' ? 'selected' : ''; ?>>Applied Science</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="designation" class="form-label">Designation</label>
                        <select class="form-select" id="designation" name="designation">
                            <option value="">All Designations</option>
                            <option value="Professor" <?php echo $designation == 'Professor' ? 'selected' : ''; ?>>Professor</option>
                            <option value="Assistant Professor" <?php echo $designation == 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                            <option value="Lab Technician" <?php echo $designation == 'Lab Technician' ? 'selected' : ''; ?>>Lab Technician</option>
                            <option value="Peon" <?php echo $designation == 'Peon' ? 'selected' : ''; ?>>Peon</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Staff Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>EID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Mobile</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($staff) > 0): ?>
                        <?php foreach ($staff as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['EID']); ?></td>
                                <td>
                                    <?php if (!empty($member['PHOTO'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($member['PHOTO']); ?>" 
                                             class="photo-thumbnail" alt="Staff Photo">
                                    <?php else: ?>
                                        <div class="photo-thumbnail bg-secondary text-white d-flex align-items-center justify-content-center">
                                            No Photo
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($member['NAME']); ?></td>
                                <td><?php echo htmlspecialchars($member['DESIGNATION']); ?></td>
                                <td><?php echo htmlspecialchars($member['DEPARTMENT']); ?></td>
                                <td><?php echo htmlspecialchars($member['MOBILE']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No staff members found matching your criteria</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3 text-end">
            <p>Total Staff: <?php echo count($staff); ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <?= sam_theme_controller_script_tag() ?>
        </div>
    </div>
</body>
</html>












