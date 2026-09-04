<?php
require_once 'db.php';
require_once 'app_ui.php';

// Initialize variables
$success = $error = '';
$name = $designation = $department = $mobile = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['name'])) {
    $name = trim((string)($_POST['name'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $mobile = trim((string)($_POST['mobile'] ?? ''));

    try {
        $checkStmt = $pdo->prepare("SELECT EID FROM staff_information WHERE NAME = ? AND MOBILE = ? LIMIT 1");
        $checkStmt->execute([$name, $mobile]);

        if ($checkStmt->fetch()) {
            $error = "Staff member with this name and mobile already exists!";
        } else {
            $photo = null;
            $photoUploaded = false;

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($fileInfo, $_FILES['photo']['tmp_name']);
                finfo_close($fileInfo);

                if (in_array($fileType, $allowedTypes, true)) {
                    $photo = file_get_contents($_FILES['photo']['tmp_name']);
                    $photoUploaded = true;
                } else {
                    $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                }
            }

            if (empty($error)) {
                if ($photoUploaded) {
                    $stmt = $pdo->prepare("INSERT INTO staff_information (NAME, DESIGNATION, DEPARTMENT, MOBILE, PHOTO) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bindValue(1, $name);
                    $stmt->bindValue(2, $designation);
                    $stmt->bindValue(3, $department);
                    $stmt->bindValue(4, $mobile);
                    $stmt->bindValue(5, $photo, PDO::PARAM_LOB);
                    $ok = $stmt->execute();
                } else {
                    $stmt = $pdo->prepare("INSERT INTO staff_information (NAME, DESIGNATION, DEPARTMENT, MOBILE) VALUES (?, ?, ?, ?)");
                    $ok = $stmt->execute([$name, $designation, $department, $mobile]);
                }

                if ($ok) {
                    $success = "Staff registered successfully!";
                    $name = $designation = $department = $mobile = '';
                } else {
                    $error = "Error while saving staff.";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration</title>
    <?= sam_theme_boot_script_tag() ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 24px auto 0;
            padding: 22px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 22px;
            color: var(--sam-ink);
        }
        .photo-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--sam-line-strong);
            background: var(--sam-surface-soft);
            display: block;
            margin: 0 auto 20px;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-input-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
        }
    </style>
</head>
<body class="sam-body">
    <div class="sam-shell">
        <div class="sam-page">
        <div class="sam-topbar">
            <div class="sam-title-block">
                <div class="sam-kicker">Staff Master</div>
                <h1 class="sam-title">Staff Registration</h1>
                <p class="sam-subtitle">Create staff records with department, designation, mobile, and photo in one clean workflow.</p>
            </div>
            <div class="sam-actions">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house-door me-2"></i>Home</a>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-grid me-2"></i>Dashboard</a>
                <a href="report_staffdetails.php" class="btn btn-outline-primary"><i class="bi bi-people me-2"></i>Staff Report</a>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                <?= sam_theme_toggle_html() ?>
            </div>
        </div>
        <div class="form-container">
            <h2 class="form-title"><i class="fas fa-user-plus me-2"></i>Staff Registration</h2>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form id="staffForm" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="designation" class="form-label">Designation</label>
                    <select class="form-select" id="designation" name="designation" required>
                        <option value="" disabled <?php echo empty($designation) ? 'selected' : ''; ?>>Select Designation</option>
                        <option value="Professor" <?php echo $designation == 'Professor' ? 'selected' : ''; ?>>Professor</option>
                        <option value="Assistant Professor" <?php echo $designation == 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                        <option value="Admin" <?php echo $designation == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Lab Technician" <?php echo $designation == 'Lab Technician' ? 'selected' : ''; ?>>Lab Technician</option>
                        <option value="Peon" <?php echo $designation == 'Peon' ? 'selected' : ''; ?>>Peon</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department" required>
                        <option value="" disabled <?php echo empty($department) ? 'selected' : ''; ?>>Select Department</option>
                        <option value="AI" <?php echo $department == 'AI' ? 'selected' : ''; ?>>AI</option>
                        <option value="CE" <?php echo $department == 'CE' ? 'selected' : ''; ?>>CE</option>
                        <option value="EE" <?php echo $department == 'EE' ? 'selected' : ''; ?>>EE</option>
                        <option value="ME" <?php echo $department == 'ME' ? 'selected' : ''; ?>>ME</option>
                        <option value="EJ" <?php echo $department == 'EJ' ? 'selected' : ''; ?>>EJ</option>
                        <option value="CT" <?php echo $department == 'CT' ? 'selected' : ''; ?>>CT</option>
                        <option value="APPLIED SCIENCE" <?php echo $department == 'APPLIED SCIENCE' ? 'selected' : ''; ?>>Applied Science</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="mobile" class="form-label">Mobile Number</label>
                    <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" pattern="[0-9]{10}" required>
                    <div class="form-text">10-digit mobile number only</div>
                </div>
                
                <div class="mb-4">
                    <label for="photo" class="form-label">Staff Photo</label>
                    <div class="text-center">
                        <img id="photoPreview" src="https://via.placeholder.com/150" class="photo-preview" alt="Photo Preview">
                        <div class="file-input-wrapper">
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-camera me-2"></i>Choose Photo
                            </button>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        </div>
                        <div class="form-text">Max size: 2MB (JPG, PNG, GIF)</div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Register Staff
                    </button>
                </div>
            </form>
        </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <?= sam_theme_controller_script_tag() ?>
    
    <script>
        $(document).ready(function() {
            // Photo preview functionality
            $('#photo').change(function(e) {
                if (this.files && this.files[0]) {
                    // Check file size (max 2MB)
                    if (this.files[0].size > 2 * 1024 * 1024) {
                        alert('File size must be less than 2MB');
                        $(this).val('');
                        return;
                    }
                    
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#photoPreview').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });

        function validateForm() {
            // Validate mobile number
            const mobile = $('#mobile').val();
            if (!/^\d{10}$/.test(mobile)) {
                alert('Please enter a valid 10-digit mobile number');
                return false;
            }
            
            // Check if photo is selected
            const photo = $('#photo')[0].files[0];
            if (photo && photo.size > 2 * 1024 * 1024) {
                alert('Photo size must be less than 2MB');
                return false;
            }
            
            // Disable submit button to prevent duplicate submissions
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
            
            return true;
        }
    </script>
</body>
</html>












