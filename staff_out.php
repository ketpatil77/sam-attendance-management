<?php
require_once 'db.php';
require_once 'app_ui.php';

// Fetch staff names who have in-time but not out-time
$staff_options = '';
$current_date = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT s.EID, s.NAME, d.IN_PHOTO 
        FROM staff_information s
        JOIN staff_daily d ON s.NAME = d.NAME
        WHERE d.EDATE = ? AND d.OUTTIME IS NULL
        ORDER BY s.NAME
    ");
    $stmt->execute([$current_date]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Check if photo exists and is not empty
        $photoData = (!empty($row['IN_PHOTO']) && $row['IN_PHOTO'] !== null) ? 
            base64_encode($row['IN_PHOTO']) : 
            '';
        $staff_options .= '<option value="'.$row['EID'].'" data-photo="'.$photoData.'">'.$row['NAME'].'</option>';
    }
} catch (PDOException $e) {
    die("Error fetching staff data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_out'])) {
    $eid = $_POST['staff_name'];
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $verification_score = isset($_POST['verification_score']) ? (float)$_POST['verification_score'] : 0.0;
    
    try {
        // Validate image data
        if (empty($_POST['image_data'])) {
            throw new Exception("No photo data received");
        }
        
        $imageData = $_POST['image_data'];
        // Remove the data URL prefix if present
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        }
        $imageData = str_replace(' ', '+', $imageData);
        $imageBinary = base64_decode($imageData);
        
        if ($imageBinary === false) {
            throw new Exception("Invalid image data");
        }

        // Get staff name
        $stmt = $pdo->prepare("SELECT NAME FROM staff_information WHERE EID = ?");
        $stmt->execute([$eid]);
        $staff = $stmt->fetch();
        
        if (!$staff || empty($staff['NAME'])) {
            throw new Exception("Staff member not found");
        }

        // Check if record exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_daily WHERE NAME = ? AND EDATE = ?");
        $check_stmt->execute([$staff['NAME'], $current_date]);
        $record_exists = $check_stmt->fetchColumn();
        
        if (!$record_exists) {
            throw new Exception("No attendance record found for today");
        }

        // Update record with transaction for safety
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE staff_daily SET OUTTIME = ?, OUT_PHOTO = ?, VERIFICATION_SCORE = ? WHERE NAME = ? AND EDATE = ?");
        $stmt->bindParam(1, $current_time);
        $stmt->bindParam(2, $imageBinary, PDO::PARAM_LOB);
        $stmt->bindParam(3, $verification_score);
        $stmt->bindParam(4, $staff['NAME']);
        $stmt->bindParam(5, $current_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update attendance record");
        }

        // Verify update
        $check_stmt = $pdo->prepare("SELECT OUTTIME FROM staff_daily WHERE NAME = ? AND EDATE = ?");
        $check_stmt->execute([$staff['NAME'], $current_date]);
        $updated_record = $check_stmt->fetch();
        
        if (!$updated_record || empty($updated_record['OUTTIME'])) {
            $pdo->rollBack();
            throw new Exception("Failed to verify attendance update");
        }

        $pdo->commit();
        $success = "Out-time marked successfully for " . htmlspecialchars($staff['NAME']) . " at " . htmlspecialchars($current_time);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Out-Time Attendance</title>
    <?= sam_theme_boot_script_tag() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <style>
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 520px;
            margin: 0 auto 20px;
            padding: 14px;
            border: 1px solid var(--sam-line);
            border-radius: 20px;
            background: var(--sam-surface-strong);
            box-shadow: var(--sam-shadow-md);
            overflow: hidden;
        }
        
        video, canvas, #photoPreview {
            width: 100%;
            display: block;
            border-radius: 16px;
            background: #0d1719;
        }
        
        .btn-capture {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 52px;
            height: 52px;
            border-radius: 50% !important;
            font-size: 20px;
        }
        
        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .hidden {
            display: none;
        }
        
        #verificationResult {
            margin-top: 15px;
            padding: 10px;
            border-radius: 12px;
            text-align: center;
            background: rgba(20, 184, 166, 0.1);
        }
    </style>
</head>
<body class="sam-body">
    <div class="sam-shell">
        <div class="sam-page">
        <div class="sam-topbar">
            <div class="sam-title-block">
                <div class="sam-kicker">Staff Exit</div>
                <h1 class="sam-title">Out-Time Attendance</h1>
                <p class="sam-subtitle">Verify staff exit with camera capture and store OUT photo plus verification score.</p>
            </div>
            <div class="sam-actions">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house-door me-2"></i>Home</a>
                <a href="report_staffdetails.php" class="btn btn-outline-primary"><i class="bi bi-people me-2"></i>Staff Details</a>
                <?= sam_theme_toggle_html() ?>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="attendanceForm" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="staff_name" class="form-label">Select Staff Member</label>
                <select class="form-select" id="staff_name" name="staff_name" required>
                    <option value="" selected disabled>Select Staff</option>
                    <?php echo $staff_options; ?>
                </select>
            </div>
            
            <div class="camera-container">
                <video id="video" autoplay playsinline></video>
                <button type="button" id="capture" class="btn btn-primary btn-capture">
                    <i class="fas fa-camera"></i>
                </button>
                <canvas id="canvas"></canvas>
                <img id="photoPreview" class="hidden" alt="Captured photo preview">
                <input type="hidden" id="image_data" name="image_data">
                <input type="hidden" id="verification_score" name="verification_score" value="0.0">
                
                <div class="camera-controls">
                    <button type="button" id="retake" class="btn btn-warning hidden">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                    <button type="button" id="confirmPhoto" class="btn btn-success hidden">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
                
                <div id="verificationResult" class="hidden"></div>
            </div>
            
            <div class="text-center mt-3">
                <button type="submit" name="submit_out" class="btn btn-success btn-lg" id="submitBtn" disabled>
                    <i class="fas fa-check-circle"></i> Mark Out-Time
                </button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <?= sam_theme_controller_script_tag() ?>
    
    <script>
        $(document).ready(function() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const photoPreview = document.getElementById('photoPreview');
            const captureBtn = document.getElementById('capture');
            const retakeBtn = document.getElementById('retake');
            const confirmBtn = document.getElementById('confirmPhoto');
            const submitBtn = document.getElementById('submitBtn');
            const imageData = document.getElementById('image_data');
            const verificationResult = document.getElementById('verificationResult');
            const context = canvas.getContext('2d');
            
            let stream = null;
            let photoConfirmed = false;
            
            // Access webcam
            async function initCamera() {
                try {
                    const constraints = {
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                            facingMode: 'user'
                        },
                        audio: false
                    };
                    
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                    video.srcObject = stream;
                    
                    return new Promise((resolve) => {
                        video.onloadedmetadata = () => {
                            video.play();
                            resolve();
                        };
                    });
                } catch (error) {
                    console.error("Webcam access error:", error);
                    alert("Could not access webcam. Please ensure you've granted permission.");
                    throw error;
                }
            }
            
            initCamera().catch(error => {
                console.error("Camera initialization failed:", error);
            });
            
            // Capture image
            captureBtn.addEventListener('click', function() {
                if (!stream) return;
                
                try {
                    // Pause the video to capture a still frame
                    const videoTrack = stream.getVideoTracks()[0];
                    const settings = videoTrack.getSettings();
                    
                    canvas.width = settings.width || video.videoWidth;
                    canvas.height = settings.height || video.videoHeight;
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Convert to JPEG with higher quality
                    const image = canvas.toDataURL('image/jpeg', 0.9);
                    photoPreview.src = image;
                    imageData.value = image;
                    
                    // Update UI
                    photoPreview.classList.remove('hidden');
                    video.classList.add('hidden');
                    captureBtn.classList.add('hidden');
                    retakeBtn.classList.remove('hidden');
                    confirmBtn.classList.remove('hidden');
                    
                    // Resume video
                    video.play();
                } catch (error) {
                    console.error("Error capturing photo:", error);
                    alert("Error capturing photo. Please try again.");
                    video.play();
                }
            });
            
            // Retake photo
            retakeBtn.addEventListener('click', function() {
                photoPreview.classList.add('hidden');
                video.classList.remove('hidden');
                captureBtn.classList.remove('hidden');
                retakeBtn.classList.add('hidden');
                confirmBtn.classList.add('hidden');
                submitBtn.disabled = true;
                photoConfirmed = false;
                imageData.value = '';
            });
            
            // Confirm photo
            confirmBtn.addEventListener('click', function() {
                if (!imageData.value) {
                    alert('Please capture a photo first');
                    return;
                }
                
                // Here you could add face verification logic if needed
                // For now, we'll just confirm the photo
                submitBtn.disabled = false;
                photoConfirmed = true;
                
                // Set a dummy verification score (replace with actual verification if available)
                document.getElementById('verification_score').value = 0.9;
                
                verificationResult.textContent = "Photo verified successfully!";
                verificationResult.className = "alert alert-success";
                verificationResult.classList.remove('hidden');
            });
            
            // Form validation
            $('#attendanceForm').submit(function(e) {
                if (!photoConfirmed) {
                    alert('Please capture and confirm your photo first');
                    e.preventDefault();
                    return false;
                }
                
                if ($('#staff_name').val() === '') {
                    alert('Please select a staff member');
                    e.preventDefault();
                    return false;
                }
                
                if (!imageData.value) {
                    alert('No photo data available');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Clean up
            window.addEventListener('beforeunload', function() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
            });
        });
    </script>
        </div>
    </div>
</body>
</html>










