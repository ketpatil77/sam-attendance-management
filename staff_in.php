<?php
require_once 'db.php';
require_once 'app_ui.php';

// Fetch staff names for dropdown
$staff_options = '';
$stmt = $pdo->query("SELECT EID, NAME FROM staff_information ORDER BY NAME");
while ($row = $stmt->fetch()) {
    $staff_options .= '<option value="'.$row['EID'].'">'.$row['NAME'].'</option>';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_in'])) {
    $eid = $_POST['staff_name'];
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    try {
        // Check if already marked in today
        $stmt = $pdo->prepare("SELECT RID FROM staff_daily WHERE NAME = (SELECT NAME FROM staff_information WHERE EID = ?) AND EDATE = ? LIMIT 1");
        $stmt->execute([$eid, $current_date]);
        
        if ($stmt->fetch()) {
            $error = "Attendance already marked for today!";
        } else {
            // Get staff details
            $stmt = $pdo->prepare("SELECT NAME, DESIGNATION, DEPARTMENT FROM staff_information WHERE EID = ?");
            $stmt->execute([$eid]);
            $staff = $stmt->fetch();
            
            // Handle image data
            $imageData = $_POST['image_data'];
            $imageData = str_replace('data:image/png;base64,', '', $imageData);
            $imageData = str_replace(' ', '+', $imageData);
            $imageBinary = base64_decode($imageData);
            
            // Insert record
            $stmt = $pdo->prepare("INSERT INTO staff_daily (NAME, DESIGNATION, DEPARTMENT, INTIME, EDATE, IN_PHOTO) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $staff['NAME']);
            $stmt->bindParam(2, $staff['DESIGNATION']);
            $stmt->bindParam(3, $staff['DEPARTMENT']);
            $stmt->bindParam(4, $current_time);
            $stmt->bindParam(5, $current_date);
            $stmt->bindParam(6, $imageBinary, PDO::PARAM_LOB);
            
            if ($stmt->execute()) {
                $success = "In-time marked successfully!";
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
    <title>In-Time Attendance</title>
    <?= sam_theme_boot_script_tag() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/sam-ui.css">
    <style>
        .container { max-width: 800px; margin-top: 24px; }
        #video { 
            background: #0d1719;
            width: 100%; 
            height: auto;
            border-radius: 16px;
            transform: scaleX(-1); /* Mirror effect */
        }
        #canvas { 
            display: none; 
        }
        #photoPreview {
            width: 100%;
            height: auto;
            border: 1px solid var(--sam-line-strong);
            border-radius: 16px;
            background: var(--sam-surface-strong);
            margin-top: 10px;
            transform: scaleX(-1); /* Mirror effect to match video */
        }
        .camera-container { 
            position: relative; 
            max-width: 520px;
            margin: 0 auto 20px;
            padding: 14px;
            border: 1px solid var(--sam-line);
            border-radius: 20px;
            background: var(--sam-surface-strong);
            box-shadow: var(--sam-shadow-md);
        }
        .capture-btn { 
            position: absolute; 
            bottom: 20px; 
            left: 50%; 
            transform: translateX(-50%);
            z-index: 10;
        }
        .btn-capture {
            width: 52px;
            height: 52px;
            border-radius: 50% !important;
            font-size: 20px;
            padding: 0;
        }
        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body class="sam-body">
    <div class="sam-shell">
        <div class="sam-page">
        <div class="sam-topbar">
            <div class="sam-title-block">
                <div class="sam-kicker">Staff Entry</div>
                <h1 class="sam-title">In-Time Attendance</h1>
                <p class="sam-subtitle">Capture staff check-in with live camera photo and save proof directly into the attendance log.</p>
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
                <button type="button" id="capture" class="btn btn-primary btn-capture capture-btn">
                    <i class="fas fa-camera"></i>
                </button>
                <canvas id="canvas"></canvas>
                <img id="photoPreview" class="hidden" alt="Captured photo preview">
                <input type="hidden" id="image_data" name="image_data">
                
                <div class="camera-controls">
                    <button type="button" id="retake" class="btn btn-warning hidden">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                    <button type="button" id="confirmPhoto" class="btn btn-success hidden">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <button type="submit" name="submit_in" class="btn btn-success btn-lg" id="submitBtn" disabled>
                    <i class="fas fa-check-circle"></i> Mark In-Time
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
            const context = canvas.getContext('2d');
            
            let stream = null;
            let photoConfirmed = false;
            
            // Access webcam with optimal settings
            async function initCamera() {
                try {
                    const constraints = {
                        video: {
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                            facingMode: 'user',
                            frameRate: { ideal: 30 }
                        },
                        audio: false
                    };
                    
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                    video.srcObject = stream;
                    
                    // Wait for video to be ready
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
            
            // Initialize camera when page loads
            initCamera().catch(error => {
                console.error("Camera initialization failed:", error);
            });
            
            // Capture image
            captureBtn.addEventListener('click', function() {
                if (!stream) return;
                
                // Pause the video temporarily
                video.pause();
                
                // Set canvas dimensions to match video
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                
                // Draw current video frame to canvas
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Convert to base64 and show preview
                const image = canvas.toDataURL('image/jpeg', 0.8); // Use JPEG for smaller size
                photoPreview.src = image;
                
                // Show preview and controls
                photoPreview.classList.remove('hidden');
                video.classList.add('hidden');
                captureBtn.classList.add('hidden');
                retakeBtn.classList.remove('hidden');
                confirmBtn.classList.remove('hidden');
                
                // Resume video (for retake functionality)
                video.play();
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
            });
            
            // Confirm photo
            confirmBtn.addEventListener('click', function() {
                // Store the confirmed image data
                imageData.value = photoPreview.src;
                
                // Update UI
                retakeBtn.classList.add('hidden');
                confirmBtn.classList.add('hidden');
                submitBtn.disabled = false;
                photoConfirmed = true;
                
                // Show success message
                alert("Photo confirmed successfully!");
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
                
                return true;
            });
            
            // Clean up camera stream when leaving page
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










