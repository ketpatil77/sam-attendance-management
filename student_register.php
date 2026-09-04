<?php
require 'db.php'; // Include your database configuration file
require_once 'app_ui.php';
sam_require_admin();

// Initialize variables
$message = '';
$error = '';
$search_results = [];
$search_performed = false;
$student_data = null;
$active_tab = (string)($_GET['tab'] ?? '');
$bulk_result = $_SESSION['bulk_result'] ?? null;
unset($_SESSION['bulk_result']);

// Check if we're viewing/editing a specific student
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_information WHERE RID = ?");
        $stmt->execute([$_GET['id']]);
        $student_data = $stmt->fetch();
        
        if (!$student_data) {
            $error = "Student not found.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        // Registration/Insert form submitted
        try {
            // Prepare SQL statement
            $sql = "INSERT INTO student_information 
                    (STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, PHOTO, MOBILE, PARENT_MOB, CITY, ADDRESS)
                    VALUES (:student_name, :student_id, :ERN_NO, :batch, :department, :ayear, :photo, :mobile, :parent_mob, :city, :address)";
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':student_name', $_POST['student_name']);
            $stmt->bindParam(':student_id', $_POST['student_id']);
            $stmt->bindParam(':ERN_NO', $_POST['ERN_NO']);
            $stmt->bindParam(':batch', $_POST['batch']);
            $stmt->bindParam(':department', $_POST['department']);
            $stmt->bindParam(':ayear', $_POST['ayear']);
            $stmt->bindParam(':mobile', $_POST['mobile']);
            $stmt->bindParam(':parent_mob', $_POST['parent_mob']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':address', $_POST['address']);
            
            // Handle photo upload
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $photo = file_get_contents($_FILES['photo']['tmp_name']);
            }
            $stmt->bindParam(':photo', $photo, PDO::PARAM_LOB);
            
            // Execute the statement
            if ($stmt->execute()) {
                $message = "Student registered successfully!";
                // Clear form
                unset($_POST);
            } else {
                $error = "Error registering student.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['update'])) {
        // Update form submitted
        try {
            $sql = "UPDATE student_information SET
                    STUDENT_NAME = :student_name,
                    STUDENT_ID = :student_id,
                    ERN_NO = :ERN_NO,
                    BATCH = :batch,
                    DEPARTMENT = :department,
                    AYEAR = :ayear,
                    MOBILE = :mobile,
                    PARENT_MOB = :parent_mob,
                    CITY = :city,
                    ADDRESS = :address
                    WHERE RID = :rid";
            
            // Handle photo update separately if new photo was uploaded
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $photo = file_get_contents($_FILES['photo']['tmp_name']);
                $sql = "UPDATE student_information SET
                        STUDENT_NAME = :student_name,
                        STUDENT_ID = :student_id,
                        ERN_NO = :ERN_NO,
                        BATCH = :batch,
                        DEPARTMENT = :department,
                        AYEAR = :ayear,
                        PHOTO = :photo,
                        MOBILE = :mobile,
                        PARENT_MOB = :parent_mob,
                        CITY = :city,
                        ADDRESS = :address
                        WHERE RID = :rid";
            }
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':student_name', $_POST['student_name']);
            $stmt->bindParam(':student_id', $_POST['student_id']);
            $stmt->bindParam(':ERN_NO', $_POST['ERN_NO']);
            $stmt->bindParam(':batch', $_POST['batch']);
            $stmt->bindParam(':department', $_POST['department']);
            $stmt->bindParam(':ayear', $_POST['ayear']);
            $stmt->bindParam(':mobile', $_POST['mobile']);
            $stmt->bindParam(':parent_mob', $_POST['parent_mob']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':address', $_POST['address']);
            $stmt->bindParam(':rid', $_POST['rid']);
            
            if (isset($photo)) {
                $stmt->bindParam(':photo', $photo, PDO::PARAM_LOB);
            }
            
            if ($stmt->execute()) {
                $message = "Student updated successfully!";
                // Refresh student data
                $stmt = $pdo->prepare("SELECT * FROM student_information WHERE RID = ?");
                $stmt->execute([$_POST['rid']]);
                $student_data = $stmt->fetch();
            } else {
                $error = "Error updating student.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete'])) {
        // Delete request submitted
        try {
            $stmt = $pdo->prepare("DELETE FROM student_information WHERE RID = ?");
            if ($stmt->execute([$_POST['rid']])) {
                $message = "Student deleted successfully!";
                $student_data = null; // Clear the student data
            } else {
                $error = "Error deleting student.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (isset($_POST['search'])) {
        // Search form submitted
        $search_performed = true;
        try {
            $search_term = isset($_POST['search_term']) ? '%' . $_POST['search_term'] . '%' : '%';
            $department = isset($_POST['department']) ? $_POST['department'] : '%';
            $ayear = isset($_POST['ayear']) ? $_POST['ayear'] : '%';
            $batch = isset($_POST['batch']) ? '%' . $_POST['batch'] . '%' : '%';
            
            $sql = "SELECT RID, STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, PHOTO 
                    FROM student_information 
                    WHERE (STUDENT_NAME LIKE :search_term 
                           OR STUDENT_ID LIKE :search_term 
                           OR ERN_NO LIKE :search_term)
                    AND DEPARTMENT LIKE :department
                    AND AYEAR LIKE :ayear
                    AND BATCH LIKE :batch
                    ORDER BY STUDENT_NAME";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':search_term', $search_term);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':ayear', $ayear);
            $stmt->bindParam(':batch', $batch);
            $stmt->execute();
            
            $search_results = $stmt->fetchAll();
            
            if (empty($search_results)) {
                $message = "No students found matching your criteria.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
    <?= sam_theme_boot_script_tag() ?>
    <!-- Bootstrap CSS -->
    <link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <!-- Font Awesome -->
    <style>
        .required:after {
            content: " *";
            color: #fb7185;
        }
        .student-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
        .tab-content {
            padding: 18px;
            border-left: 1px solid var(--sam-line);
            border-right: 1px solid var(--sam-line);
            border-bottom: 1px solid var(--sam-line);
            border-radius: 0 0 14px 14px;
        }
        .action-btns .btn {
            margin: 2px;
        }
        .photo-preview {
            max-width: 150px;
            max-height: 150px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="sam-body">
    <div class="sam-shell">
        <div class="sam-page">
        <div class="sam-topbar">
            <div class="sam-title-block">
                <div class="sam-kicker">Student Master</div>
                <h1 class="sam-title">Student Management System</h1>
                <p class="sam-subtitle">Register new students, search existing records, and maintain ERN-based student data from one place.</p>
            </div>
            <div class="sam-actions">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house-door me-2"></i>Home</a>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Dashboard</a>
                <a href="early_out_settings.php" class="btn btn-outline-primary"><i class="bi bi-sliders me-2"></i>Early OUT Settings</a>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                <?= sam_theme_toggle_html() ?>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo !isset($_GET['id']) && $active_tab !== 'bulk' ? 'active' : ''; ?>" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">
                    <?php echo isset($student_data) ? 'Add New Student' : 'Register Student'; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo isset($_GET['id']) ? 'active' : ''; ?>" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab">Search Students</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'bulk' ? 'active' : ''; ?>" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab">Bulk Add Students</button>
            </li>
            <?php if (isset($student_data)): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">View/Edit Student</button>
                </li>
            <?php endif; ?>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Registration Tab -->
            <div class="tab-pane fade <?php echo !isset($_GET['id']) && $active_tab !== 'bulk' ? 'show active' : ''; ?>" id="register" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form action="student_register.php" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="student_name" class="form-label required">Student Name</label>
                                    <input type="text" class="form-control" id="student_name" name="student_name" 
                                           value="<?php echo isset($_POST['student_name']) ? htmlspecialchars($_POST['student_name']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="student_id" class="form-label required">Student ID</label>
                                    <input type="number" class="form-control" id="student_id" name="student_id" 
                                           value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ERN_NO" class="form-label required">ERN No</label>
                                    <input type="text" class="form-control" id="ERN_NO" name="ERN_NO" 
                                           value="<?php echo isset($_POST['ERN_NO']) ? htmlspecialchars($_POST['ERN_NO']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="batch" class="form-label required">Batch</label>
                                    <input type="text" class="form-control" id="batch" name="batch" 
                                           value="<?php echo isset($_POST['batch']) ? htmlspecialchars($_POST['batch']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="department" class="form-label required">Department</label>
                                    <select class="form-select" id="department" name="department" required>
                                        <option value="" selected disabled>Select Department</option>
                                        <option value="AI" <?php echo (isset($_POST['department']) && $_POST['department'] == 'AI') ? 'selected' : ''; ?>>AI</option>
                                        <option value="CE" <?php echo (isset($_POST['department']) && $_POST['department'] == 'CE') ? 'selected' : ''; ?>>CE</option>
                                        <option value="EE" <?php echo (isset($_POST['department']) && $_POST['department'] == 'EE') ? 'selected' : ''; ?>>EE</option>
                                        <option value="ME" <?php echo (isset($_POST['department']) && $_POST['department'] == 'ME') ? 'selected' : ''; ?>>ME</option>
                                        <option value="EJ" <?php echo (isset($_POST['department']) && $_POST['department'] == 'EJ') ? 'selected' : ''; ?>>EJ</option>
                                        <option value="CT" <?php echo (isset($_POST['department']) && $_POST['department'] == 'CT') ? 'selected' : ''; ?>>CT</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="ayear" class="form-label required">Academic Year</label>
                                    <select class="form-select" id="ayear" name="ayear" required>
                                        <option value="" selected disabled>Select Year</option>
                                        <option value="FY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'FY') ? 'selected' : ''; ?>>FY</option>
                                        <option value="SY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'SY') ? 'selected' : ''; ?>>SY</option>
                                        <option value="TY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'TY') ? 'selected' : ''; ?>>TY</option>
                                    
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="photo" class="form-label">Student Photo</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="mobile" class="form-label required">Mobile Number</label>
                                    <input type="text" class="form-control" id="mobile" name="mobile" 
                                           value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="parent_mob" class="form-label">Parent's Mobile Number</label>
                                    <input type="text" class="form-control" id="parent_mob" name="parent_mob" 
                                           value="<?php echo isset($_POST['parent_mob']) ? htmlspecialchars($_POST['parent_mob']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="register" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Register
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo $active_tab === 'bulk' ? 'show active' : ''; ?>" id="bulk" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <h4>Mass Student Add</h4>
                        <p class="text-muted">Download template, keep column names unchanged, add students, then upload Excel or CSV. Existing ERN or Student ID rows are skipped.</p>
                        <?php if (is_array($bulk_result) && isset($bulk_result['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($bulk_result['error']) ?></div>
                        <?php elseif (is_array($bulk_result)): ?>
                            <div class="alert alert-success">
                                Added <?= (int)$bulk_result['inserted'] ?> student(s); skipped <?= (int)$bulk_result['skipped'] ?> duplicate(s).
                            </div>
                            <?php if (!empty($bulk_result['errors'])): ?>
                                <div class="alert alert-warning"><strong>Rows needing correction:</strong><ul class="mb-0">
                                    <?php foreach (array_slice($bulk_result['errors'], 0, 20) as $row_error): ?>
                                        <li><?= htmlspecialchars($row_error) ?></li>
                                    <?php endforeach; ?>
                                </ul></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mb-3">
                            <a class="btn btn-outline-success" href="assets/student_bulk_template.xlsx" download><i class="fas fa-file-excel"></i> Download Sample Excel</a>
                            <a class="btn btn-outline-secondary" href="assets/student_bulk_template.csv" download><i class="fas fa-file-csv"></i> Download Sample CSV</a>
                        </div>
                        <form action="bulk_student_import.php" method="post" enctype="multipart/form-data">
                            <label for="student_file" class="form-label required">Student file</label>
                            <input type="file" id="student_file" name="student_file" class="form-control mb-3" accept=".xlsx,.csv" required>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-users"></i> Import Students</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Search Tab -->
            <div class="tab-pane fade <?php echo isset($_GET['id']) && !isset($student_data) ? 'show active' : ''; ?>" id="search" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <form action="student_register.php" method="post">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="search_term" class="form-label">Search (Name, ID, or ERN No)</label>
                                    <input type="text" class="form-control" id="search_term" name="search_term" 
                                           placeholder="Enter name, student ID, or ERN no" 
                                           value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="batch" class="form-label">Batch</label>
                                    <input type="text" class="form-control" id="batch" name="batch" 
                                           placeholder="Enter batch" 
                                           value="<?php echo isset($_POST['batch']) ? htmlspecialchars($_POST['batch']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="department" class="form-label">Department</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="" selected>All Departments</option>
                                        <option value="AI" <?php echo (isset($_POST['department']) && $_POST['department'] == 'AI') ? 'selected' : ''; ?>>AI</option>
                                        <option value="CE" <?php echo (isset($_POST['department']) && $_POST['department'] == 'CE') ? 'selected' : ''; ?>>CE</option>
                                        <option value="EE" <?php echo (isset($_POST['department']) && $_POST['department'] == 'EE') ? 'selected' : ''; ?>>EE</option>
                                        <option value="ME" <?php echo (isset($_POST['department']) && $_POST['department'] == 'ME') ? 'selected' : ''; ?>>ME</option>
                                        <option value="EJ" <?php echo (isset($_POST['department']) && $_POST['department'] == 'EJ') ? 'selected' : ''; ?>>EJ</option>
                                        <option value="CT" <?php echo (isset($_POST['department']) && $_POST['department'] == 'CT') ? 'selected' : ''; ?>>CT</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="ayear" class="form-label">Academic Year</label>
                                    <select class="form-select" id="ayear" name="ayear">
                                        <option value="" selected>All Years</option>
                                        <option value="FY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'FY') ? 'selected' : ''; ?>>FY</option>
                                        <option value="SY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'SY') ? 'selected' : ''; ?>>SY</option>
                                        <option value="TY" <?php echo (isset($_POST['ayear']) && $_POST['ayear'] == 'TY') ? 'selected' : ''; ?>>TY</option>
					
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" name="search" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($search_performed): ?>
                            <?php if (!empty($search_results)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Photo</th>
                                                <th>Name</th>
                                                <th>Student ID</th>
                                                <th>ERN No</th>
                                                <th>Batch</th>
                                                <th>Department</th>
                                                <th>Year</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_results as $student): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($student['PHOTO'])): ?>
                                                            <img src="data:image/jpeg;base64,<?php echo base64_encode($student['PHOTO']); ?>" class="student-photo" alt="Student Photo">
                                                        <?php else: ?>
                                                            <div class="student-photo bg-light text-center pt-3">
                                                                <i class="fas fa-user text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($student['STUDENT_NAME']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['STUDENT_ID']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['ERN_NO']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['BATCH']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['DEPARTMENT']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['AYEAR']); ?></td>
                                                    <td class="action-btns">
                                                        <a href="student_register.php?id=<?php echo $student['RID']; ?>" class="btn btn-sm btn-info" title="View/Edit">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <form method="post" action="student_register.php" style="display:inline;">
                                                            <input type="hidden" name="rid" value="<?php echo $student['RID']; ?>">
                                                            <button type="submit" name="delete" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this student?');">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($message): ?>
                                <div class="alert alert-info"><?php echo $message; ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- View/Edit Tab (only shown when viewing a specific student) -->
            <?php if (isset($student_data)): ?>
                <div class="tab-pane fade show active" id="view" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <?php if ($message): ?>
                                <div class="alert alert-success"><?php echo $message; ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form action="student_register.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="rid" value="<?php echo $student_data['RID']; ?>">
                                
                                <div class="row mb-4">
                                    <div class="col-md-3 text-center">
                                        <?php if (!empty($student_data['PHOTO'])): ?>
                                            <img src="data:image/jpeg;base64,<?php echo base64_encode($student_data['PHOTO']); ?>" class="photo-preview img-thumbnail" id="photoPreview">
                                        <?php else: ?>
                                            <div class="photo-preview img-thumbnail bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user fa-5x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <label for="photo" class="form-label">Update Photo</label>
                                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*" onchange="previewPhoto(this)">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="student_name" class="form-label required">Student Name</label>
                                                <input type="text" class="form-control" id="student_name" name="student_name" 
                                                       value="<?php echo htmlspecialchars($student_data['STUDENT_NAME']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="student_id" class="form-label required">Student ID</label>
                                                <input type="number" class="form-control" id="student_id" name="student_id" 
                                                       value="<?php echo htmlspecialchars($student_data['STUDENT_ID']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="ERN_NO" class="form-label required">ERN No</label>
                                                <input type="text" class="form-control" id="ERN_NO" name="ERN_NO" 
                                                       value="<?php echo htmlspecialchars($student_data['ERN_NO']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="batch" class="form-label required">Batch</label>
                                                <input type="text" class="form-control" id="batch" name="batch" 
                                                       value="<?php echo htmlspecialchars($student_data['BATCH']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="department" class="form-label required">Department</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="" disabled>Select Department</option>
                                            <option value="AI" <?php echo ($student_data['DEPARTMENT'] == 'AI') ? 'selected' : ''; ?>>AI</option>
                                            <option value="CE" <?php echo ($student_data['DEPARTMENT'] == 'CE') ? 'selected' : ''; ?>>CE</option>
                                            <option value="EE" <?php echo ($student_data['DEPARTMENT'] == 'EE') ? 'selected' : ''; ?>>EE</option>
                                            <option value="ME" <?php echo ($student_data['DEPARTMENT'] == 'ME') ? 'selected' : ''; ?>>ME</option>
                                            <option value="EJ" <?php echo ($student_data['DEPARTMENT'] == 'EJ') ? 'selected' : ''; ?>>EJ</option>
                                            <option value="CT" <?php echo ($student_data['DEPARTMENT'] == 'CT') ? 'selected' : ''; ?>>CT</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ayear" class="form-label required">Academic Year</label>
                                        <select class="form-select" id="ayear" name="ayear" required>
                                            <option value="" disabled>Select Year</option>
                                            <option value="FY" <?php echo ($student_data['AYEAR'] == 'FY') ? 'selected' : ''; ?>>FY</option>
                                            <option value="SY" <?php echo ($student_data['AYEAR'] == 'SY') ? 'selected' : ''; ?>>SY</option>
                                            <option value="TY" <?php echo ($student_data['AYEAR'] == 'TY') ? 'selected' : ''; ?>>TY</option>
                                             
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="mobile" class="form-label required">Mobile Number</label>
                                        <input type="text" class="form-control" id="mobile" name="mobile" 
                                               value="<?php echo htmlspecialchars($student_data['MOBILE']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="parent_mob" class="form-label">Parent's Mobile Number</label>
                                        <input type="text" class="form-control" id="parent_mob" name="parent_mob" 
                                               value="<?php echo htmlspecialchars($student_data['PARENT_MOB']); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($student_data['CITY']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($student_data['ADDRESS']); ?>">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="student_register.php" class="btn btn-secondary me-md-2">
                                        <i class="fas fa-arrow-left"></i> Back to Search
                                    </a>
                                    <button type="submit" name="update" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                    <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this student?');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        // Photo preview function
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('photoPreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'photoPreview';
                        preview.className = 'photo-preview img-thumbnail';
                        input.parentNode.insertBefore(preview, input);
                    }
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Activate tab if coming from search results
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['id'])): ?>
                var searchTab = new bootstrap.Tab(document.getElementById('search-tab'));
                searchTab.show();
                
                var viewTab = new bootstrap.Tab(document.getElementById('view-tab'));
                viewTab.show();
            <?php endif; ?>
        });
    </script>
    <?= sam_theme_controller_script_tag() ?>
        </div>
    </div>
</body>
</html>












