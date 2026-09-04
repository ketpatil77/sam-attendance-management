<?php
require 'db.php';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ern'])) {
    $ern = trim($_POST['ern']);
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    $response = ['status' => 'error', 'message' => 'Unknown error'];

    try {
        $pdo->beginTransaction();
        
        // Check for existing IN record
        $stmt = $pdo->prepare("SELECT sd.*, si.PHOTO, si.STUDENT_NAME, si.DEPARTMENT 
                              FROM student_daily sd
                              JOIN student_information si ON sd.RID = si.RID
                              WHERE sd.ERN_NO = ? 
                              AND sd.EDATE = ? 
                              AND sd.STATUS = 'IN'");
        $stmt->execute([$ern, $today]);
        $record = $stmt->fetch();

        if ($record) {
            // Update to mark OUT
            $update = $pdo->prepare("UPDATE student_daily 
                                    SET OUTTIME = ?, 
                                        STATUS = 'PRESENT' 
                                    WHERE ERN_NO = ? 
                                    AND EDATE = ? 
                                    AND STATUS = 'IN'");
            $update->execute([$currentTime, $ern, $today]);
            
            $response = [
                'status' => 'success', 
                'message' => 'OUT time marked successfully',
                'student' => [
                    'name' => $record['STUDENT_NAME'],
                    'ern' => $record['ERN_NO'],
                    'dept' => $record['DEPARTMENT'],
                    'photo' => $record['PHOTO'] ? base64_encode($record['PHOTO']) : null,
                    'in_time' => $record['INTIME']
                ]
            ];
            $pdo->commit();
        } else {
            // Check if already marked OUT
            $check = $pdo->prepare("SELECT * FROM student_daily 
                                   WHERE ERN_NO = ? 
                                   AND EDATE = ? 
                                   AND STATUS = 'PRESENT'");
            $check->execute([$ern, $today]);
            
            if ($check->fetch()) {
                $response = ['status' => 'already_out', 'message' => 'Already marked OUT today'];
            } else {
                $response = ['status' => 'no_in', 'message' => 'IN record not found'];
            }
            $pdo->rollBack();
        }
    } catch (PDOException $e) { 
        $pdo->rollBack();
        $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

// Handle table refresh
if (isset($_POST['action']) && $_POST['action'] === 'fetch_out') {
    $today = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("
            SELECT sd.*, si.PHOTO 
            FROM student_daily sd
            JOIN student_information si ON sd.RID = si.RID
            WHERE sd.EDATE = ? 
            AND sd.STATUS = 'PRESENT'
            ORDER BY sd.OUTTIME DESC
        ");
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();

        if (count($rows) === 0) {
            echo '<tr><td colspan="7" class="text-center">No attendance marked yet today</td></tr>';
            exit;
        }

        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . ($row['PHOTO'] ? "<img src='data:image/jpeg;base64," . base64_encode($row['PHOTO']) . "' height='50' class='rounded'>" : "<span class='text-muted'>No Photo</span>") . "</td>";
            echo "<td>" . htmlspecialchars($row['STUDENT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($row['ERN_NO']) . "</td>";
            echo "<td>" . htmlspecialchars($row['INTIME']) . "</td>";
            echo "<td>" . htmlspecialchars($row['OUTTIME']) . "</td>";
            echo "<td>" . htmlspecialchars($row['DEPARTMENT']) . "</td>";
            echo "<td>" . htmlspecialchars($row['AYEAR']) . "</td>";
            echo "</tr>";
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='7' class='text-center text-danger'>Error loading data</td></tr>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance (OUT)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .refreshing {
            position: relative;
            opacity: 0.8;
        }
        .refreshing::after {
            content: "Updating...";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 255, 255, 0.9);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
        }
        .status-pulse {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        #studentInfo {
            transition: all 0.3s ease;
        }
        #cameraSwitch {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .scanner-container {
            position: relative;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">
            <i class="bi bi-qr-code-scan"></i> Mark Attendance (OUT)
        </h3>
        <a href="report.php" class="btn btn-outline-danger">
            <i class="bi bi-file-earmark-text"></i> Reports
        </a>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="scanner-container">
                <div id="reader"></div>
                <button id="cameraSwitch" class="btn btn-sm btn-secondary" style="display: none;">
                    <i class="bi bi-camera-reverse"></i> Switch Camera
                </button>
            </div>
        </div>
        <div class="col-md-6">
            <div id="status" class="alert alert-info status-pulse">
                <i class="bi bi-camera"></i> Ready to scan QR code...
            </div>
            <div id="studentInfo" class="card" style="display: none;">
                <div class="card-body text-center">
                    <img id="studentPhoto" src="" class="rounded-circle mb-3" height="80">
                    <h5 id="studentName" class="card-title"></h5>
                    <div class="text-muted mb-2">
                        <span id="studentPrn" class="badge bg-primary me-2"></span>
                        <span id="studentDept" class="badge bg-secondary"></span>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-success">
                            <i class="bi bi-box-arrow-in-right"></i> IN: <span id="studentInTime"></span>
                        </span>
                        <span class="badge bg-danger ms-2">
                            <i class="bi bi-box-arrow-right"></i> OUT: <span id="studentOutTime"></span>
                        </span>
                    </div>
                    <div id="markingStatus" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-check"></i> Today's Attendance
                </h5>
                <small id="lastUpdated" class="text-light"></small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>ERN NO</th>
                            <th>IN Time</th>
                            <th>OUT Time</th>
                            <th>Department</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody id="outTableBody" class="refreshing">
                        <!-- Rows will be loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let html5QrCode;
const qrRegionId = "reader";
let isProcessing = false;
let currentCameraId = null;
let availableCameras = [];
let isBackCameraActive = true;

// Initialize scanner
function initScanner() {
    html5QrCode = new Html5Qrcode(qrRegionId);
    
    Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length) {
            availableCameras = cameras;
            
            // Find back and front cameras
            const backCamera = cameras.find(c => c.facingMode === 'environment');
            const frontCamera = cameras.find(c => c.facingMode === 'user');
            
            // Try to start with back camera first, then front, then first available
            const preferredCamera = backCamera || frontCamera || cameras[0];
            currentCameraId = preferredCamera.id;
            isBackCameraActive = preferredCamera.facingMode === 'environment';
            
            startCamera(currentCameraId);
            
            // Show switch button if multiple cameras available
            if (cameras.length > 1) {
                $('#cameraSwitch').show();
            }
        } else {
            showStatus("No cameras found", 'danger');
        }
    }).catch(onCameraError);
}

// Start camera with given ID
function startCamera(cameraId) {
    html5QrCode.start(
        cameraId,
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        },
        onScanSuccess,
        onScanError
    ).then(() => {
        currentCameraId = cameraId;
        const activeCamera = availableCameras.find(c => c.id === cameraId);
        isBackCameraActive = activeCamera?.facingMode === 'environment';
        showStatus("Ready to scan QR code...", 'info', 'bi-camera');
    }).catch(onStartError);
}

// Switch between front and back cameras
function switchCamera() {
    if (isProcessing || !availableCameras.length || availableCameras.length < 2) return;
    
    isProcessing = true;
    html5QrCode.stop().then(() => {
        // Find the opposite camera
        let newCameraId = null;
        
        if (isBackCameraActive) {
            // Switch to front camera
            const frontCamera = availableCameras.find(c => c.facingMode === 'user');
            if (frontCamera) {
                newCameraId = frontCamera.id;
            }
        } else {
            // Switch to back camera
            const backCamera = availableCameras.find(c => c.facingMode === 'environment');
            if (backCamera) {
                newCameraId = backCamera.id;
            }
        }
        
        // If no opposite camera found, just switch to the first available that's not current
        if (!newCameraId) {
            newCameraId = availableCameras.find(c => c.id !== currentCameraId)?.id || availableCameras[0].id;
        }
        
        startCamera(newCameraId);
        isProcessing = false;
    }).catch(err => {
        console.error("Failed to stop camera:", err);
        isProcessing = false;
    });
}

// Scanner callbacks
function onScanSuccess(decodedText) {
    if (isProcessing) return;
    isProcessing = true;
    html5QrCode.pause();
    markOutAttendance(decodedText.trim());
}

function onScanError(error) {
    console.log("Scan error:", error);
}

function onStartError(error) {
    showStatus("Scanner error: " + error, 'danger');
}

function onCameraError(error) {
    showStatus("Camera error: " + error, 'danger');
}

// Show status message
function showStatus(message, type = 'info', icon = '') {
    const statusEl = $('#status');
    statusEl.removeClass().addClass(`alert alert-${type} status-pulse`);
    
    if (icon) {
        statusEl.html(`<i class="bi ${icon}"></i> ${message}`);
    } else {
        statusEl.text(message);
    }
}

// Mark OUT attendance
function markOutAttendance(ern) {
    showStatus("Processing attendance...", 'secondary', 'bi-hourglass');
    $('#outTableBody').addClass('refreshing');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: { ern: ern },
        dataType: 'json'
    }).done(function(response) {
        handleResponse(response, ern);
    }).fail(function() {
        showStatus("Server communication error", 'danger', 'bi-exclamation-triangle');
        completeProcessing();
    }).always(function() {
        refreshOutTable();
        updateLastUpdated();
    });
}

// Handle server response
function handleResponse(response, ern) {
    switch(response.status) {
        case 'success':
            showStatus("OUT time marked successfully!", 'success', 'bi-check-circle');
            showStudentInfo(response.student);
            break;
        case 'already_out':
            showStatus("Already marked OUT today", 'warning', 'bi-exclamation-circle');
            break;
        case 'no_in':
            showStatus("IN record not found", 'danger', 'bi-x-circle');
            break;
        default:
            showStatus(response.message || "Error processing request", 'danger', 'bi-exclamation-triangle');
    }
    
    completeProcessing();
}

// Show student info card
function showStudentInfo(student) {
    const infoCard = $('#studentInfo');
    $('#studentName').text(student.name);
    $('#studentPrn').text(student.ern);
    $('#studentDept').text(student.dept);
    $('#studentInTime').text(student.in_time);
    $('#studentOutTime').text(new Date().toLocaleTimeString());
    
    if (student.photo) {
        $('#studentPhoto').attr('src', `data:image/jpeg;base64,${student.photo}`);
    } else {
        $('#studentPhoto').attr('src', '');
    }
    
    infoCard.fadeIn(300);
    setTimeout(() => {
        infoCard.fadeOut(300);
    }, 3000);
}

// Complete processing and resume scanning
function completeProcessing(delay = 1000) {
    setTimeout(() => {
        isProcessing = false;
        if (html5QrCode && !html5QrCode.isScanning) {
            html5QrCode.resume();
            showStatus("Ready to scan QR code...", 'info', 'bi-camera');
        }
    }, delay);
}

// Refresh OUT table
function refreshOutTable() {
    $.post('', { action: 'fetch_out' })
        .done(function(html) {
            $('#outTableBody').html(html).removeClass('refreshing');
        })
        .fail(function() {
            $('#outTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>')
                             .removeClass('refreshing');
        });
}

// Update last updated timestamp
function updateLastUpdated() {
    const now = new Date();
    $('#lastUpdated').text('Last updated: ' + now.toLocaleTimeString());
}

// Initialize on page load
$(document).ready(function() {
    initScanner();
    refreshOutTable();
    updateLastUpdated();
    
    // Camera switch button event
    $('#cameraSwitch').click(switchCamera);
    
    // Auto-refresh every 15 seconds
    setInterval(function() {
        if (!isProcessing) {
            refreshOutTable();
            updateLastUpdated();
        }
    }, 15000);
});
</script>
</body>
</html>











