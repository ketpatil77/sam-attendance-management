<?php
require 'db.php';
require_once 'app_ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ern'])) {
    $ern = trim((string)$_POST['ern']);
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    $response = ['status' => 'error', 'message' => 'Unknown error'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM student_information WHERE ERN_NO = ?');
        $stmt->execute([$ern]);
        $student = $stmt->fetch();

        if (!$student) {
            $response = ['status' => 'not_found', 'message' => 'Student not found'];
            $pdo->rollBack();
            echo json_encode($response);
            exit;
        }

        $check = $pdo->prepare('SELECT 1 FROM student_daily WHERE ERN_NO = ? AND EDATE = ? LIMIT 1');
        $check->execute([$ern, $today]);
        if ($check->fetchColumn()) {
            $response = ['status' => 'already', 'message' => 'Already marked IN today'];
            $pdo->rollBack();
            echo json_encode($response);
            exit;
        }

        $insert = $pdo->prepare(
            "INSERT INTO student_daily
             (INTIME, STATUS, RID, EDATE, STUDENT_NAME, ERN_NO, DEPARTMENT, BATCH, AYEAR, STUDENT_ID)
             VALUES (?, 'IN', ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $currentTime,
            $student['RID'],
            $today,
            $student['STUDENT_NAME'],
            $student['ERN_NO'],
            $student['DEPARTMENT'],
            $student['BATCH'],
            $student['AYEAR'],
            $student['STUDENT_ID'],
        ]);

        $pdo->commit();
        $response = [
            'status' => 'success',
            'message' => 'Attendance marked successfully',
            'student' => [
                'name' => $student['STUDENT_NAME'],
                'ern' => $student['ERN_NO'],
                'dept' => $student['DEPARTMENT'],
                'photo' => $student['PHOTO'] ? base64_encode($student['PHOTO']) : null,
            ],
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT sd.*, si.PHOTO
         FROM student_daily sd
         JOIN student_information si ON sd.RID = si.RID
         WHERE sd.EDATE = ? AND sd.STATUS = 'IN'
         ORDER BY sd.INTIME DESC"
    );
    $stmt->execute([$today]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        echo '<tr><td colspan="6" class="sam-empty-state">No attendance marked yet today.</td></tr>';
        exit;
    }

    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . ($row['PHOTO'] ? '<img src="data:image/jpeg;base64,' . base64_encode($row['PHOTO']) . '" height="56" width="56" class="sam-photo-thumb" alt="Student photo">' : '<span class="text-muted">No Photo</span>') . '</td>';
        echo '<td>' . htmlspecialchars($row['STUDENT_NAME']) . '</td>';
        echo '<td>' . htmlspecialchars($row['ERN_NO']) . '</td>';
        echo '<td>' . htmlspecialchars($row['INTIME']) . '</td>';
        echo '<td>' . htmlspecialchars($row['DEPARTMENT']) . '</td>';
        echo '<td>' . htmlspecialchars($row['AYEAR']) . '</td>';
        echo '</tr>';
    }
    exit;
}

sam_render_head(
    'QR Attendance (IN)',
    '<script src="assets/vendor/html5-qrcode.min.js"></script>'
);
sam_page_start(
    'Student Entry',
    'Mark Attendance (IN)',
    'Scan fast. See status, last student, and live session activity in one operator screen.',
    [
        ['href' => 'report.php', 'label' => 'Reports', 'class' => 'btn btn-outline-danger', 'icon' => 'bi bi-file-earmark-text'],
    ]
);
?>

<div class="sam-dashboard-grid mb-4">
    <div class="sam-dashboard-stack">
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-qr-code-scan me-2"></i>Scanner</h4>
                <?= sam_chip('IN flow live', 'success', 'bi bi-lightning-charge-fill') ?>
            </div>
            <div id="reader"></div>
            <div class="sam-toolbar mt-3">
                <div class="sam-toolbar-group">
                    <button id="cameraSwitch" class="btn btn-sm btn-secondary" style="display:none;">
                        <i class="bi bi-camera-reverse me-2"></i>Switch Camera
                    </button>
                </div>
                <div class="sam-inline-note">Hold QR steady. One scan enough.</div>
            </div>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-list-check me-2"></i>Today's IN Attendance</h4>
                <small id="lastUpdated" class="sam-inline-note"></small>
            </div>
            <div class="table-responsive sam-attendance-table-wrap">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>ERN</th>
                            <th>IN Time</th>
                            <th>Department</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="sam-dashboard-stack">
        <div id="status" class="alert alert-info status-pulse">
            <i class="bi bi-camera me-2"></i>Ready to scan QR code...
        </div>

        <div id="studentInfo" class="sam-card" style="display:none;">
            <div class="sam-section-head mb-3">
                <h4 class="sam-section-title">Last Student</h4>
                <?= sam_chip('Waiting', 'default', 'bi bi-person-badge') ?>
            </div>
            <div class="text-center">
                <img id="studentPhoto" src="" class="rounded-circle mb-3" height="92" width="92" alt="Student photo">
                <h4 id="studentName" class="mb-2"></h4>
                <div class="text-muted mb-2">
                    <span id="studentPrn" class="badge bg-primary me-2"></span>
                    <span id="studentDept" class="badge bg-secondary"></span>
                </div>
                <div id="markingStatus" class="sam-inline-note"></div>
            </div>
        </div>

        <div class="sam-highlight">
            <h5><i class="bi bi-signpost-split me-2"></i>Status</h5>
            <p class="mb-1">Green: marked.</p>
            <p class="mb-1">Yellow: already today.</p>
            <p class="mb-0">Red: bad QR or server issue.</p>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-activity me-2"></i>Session Feed</h4>
                <span class="sam-inline-note">Last 10 scans</span>
            </div>
            <div id="scanFeed" class="sam-feed">
                <div class="sam-empty-state">No scans yet.</div>
            </div>
        </div>
    </div>
</div>

<?php
$scripts = <<<'HTML'
<script>
let html5QrCode;
const qrRegionId = "reader";
let isProcessing = false;
let currentCameraId = null;
let availableCameras = [];
let isBackCameraActive = true;
const scanFeed = [];

function initScanner() {
    html5QrCode = new Html5Qrcode(qrRegionId);
    Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length) {
            availableCameras = cameras;
            const backCamera = cameras.find(c => c.facingMode === 'environment');
            const frontCamera = cameras.find(c => c.facingMode === 'user');
            const preferredCamera = backCamera || frontCamera || cameras[0];
            currentCameraId = preferredCamera.id;
            isBackCameraActive = preferredCamera.facingMode === 'environment';
            startCamera(currentCameraId);
            if (cameras.length > 1) {
                $('#cameraSwitch').show();
            }
        } else {
            showStatus('No cameras found', 'danger', 'bi-camera-video-off');
        }
    }).catch(onCameraError);
}

function startCamera(cameraId) {
    html5QrCode.start(
        cameraId,
        { fps: 10, qrbox: { width: 210, height: 210 }, aspectRatio: 1.0 },
        onScanSuccess,
        onScanError
    ).then(() => {
        currentCameraId = cameraId;
        const activeCamera = availableCameras.find(c => c.id === cameraId);
        isBackCameraActive = activeCamera?.facingMode === 'environment';
        showStatus('Ready to scan QR code...', 'info', 'bi-camera');
    }).catch(onStartError);
}

function switchCamera() {
    if (isProcessing || availableCameras.length < 2) return;
    isProcessing = true;
    html5QrCode.stop().then(() => {
        let newCameraId = null;
        if (isBackCameraActive) {
            const frontCamera = availableCameras.find(c => c.facingMode === 'user');
            if (frontCamera) newCameraId = frontCamera.id;
        } else {
            const backCamera = availableCameras.find(c => c.facingMode === 'environment');
            if (backCamera) newCameraId = backCamera.id;
        }
        if (!newCameraId) {
            newCameraId = availableCameras.find(c => c.id !== currentCameraId)?.id || availableCameras[0].id;
        }
        startCamera(newCameraId);
        isProcessing = false;
    }).catch(err => {
        console.error('Failed to stop camera:', err);
        isProcessing = false;
    });
}

function onScanSuccess(decodedText) {
    if (isProcessing) return;
    isProcessing = true;
    markAttendance(decodedText.trim());
}

function onScanError(error) {
    console.log('Scan error:', error);
}

function onStartError(error) {
    showStatus('Scanner error: ' + error, 'danger', 'bi-exclamation-triangle');
}

function onCameraError(error) {
    showStatus('Camera error: ' + error, 'danger', 'bi-exclamation-triangle');
}

function showStatus(message, type = 'info', icon = '') {
    const statusEl = $('#status');
    statusEl.removeClass().addClass(`alert alert-${type} status-pulse`);
    statusEl.html(icon ? `<i class="bi ${icon} me-2"></i>${message}` : message);
}

function addFeed(result, student = {}, detail = '') {
    const entry = {
        result,
        name: student.name || detail || 'Unknown',
        ern: student.ern || '-',
        time: new Date().toLocaleTimeString()
    };
    scanFeed.unshift(entry);
    if (scanFeed.length > 10) scanFeed.pop();
    renderFeed();
}

function renderFeed() {
    const feedEl = $('#scanFeed');
    if (!scanFeed.length) {
        feedEl.html('<div class="sam-empty-state">No scans yet.</div>');
        return;
    }
    feedEl.html(scanFeed.map((entry) => {
        const variant = entry.result === 'success' ? 'success' : (entry.result === 'warning' ? 'warning' : 'danger');
        return `
            <div class="sam-feed-item border-${variant}-subtle">
                <div class="sam-feed-title">${entry.name}</div>
                <div class="sam-feed-meta">${entry.ern} â€¢ ${entry.result.toUpperCase()} â€¢ ${entry.time}</div>
            </div>
        `;
    }).join(''));
}

function markAttendance(ern) {
    showStatus('Processing attendance...', 'secondary', 'bi-hourglass-split');
    $('#attendanceTable').addClass('refreshing');
    $.ajax({
        url: '',
        type: 'POST',
        data: { ern: ern },
        dataType: 'json'
    }).done(function(response) {
        handleResponse(response);
    }).fail(function() {
        showStatus('Server communication error', 'danger', 'bi-exclamation-triangle');
        addFeed('error', {}, 'Server communication error');
        completeProcessing();
    }).always(function() {
        refreshAttendanceTable();
        updateLastUpdated();
    });
}

function handleResponse(response) {
    switch (response.status) {
        case 'success':
            showStatus('Attendance marked successfully!', 'success', 'bi-check-circle-fill');
            showStudentInfo(response.student, 'Student entry recorded.');
            addFeed('success', response.student);
            break;
        case 'already':
            showStatus('Already marked IN today', 'warning', 'bi-exclamation-circle-fill');
            addFeed('warning', { ern: '-', name: 'Already marked' }, response.message);
            break;
        case 'not_found':
            showStatus('Student not found', 'danger', 'bi-x-circle-fill');
            addFeed('error', { ern: '-', name: 'Unknown student' }, response.message);
            break;
        default:
            showStatus(response.message || 'Error processing request', 'danger', 'bi-exclamation-triangle-fill');
            addFeed('error', { ern: '-', name: 'Error' }, response.message || 'Error');
    }
    completeProcessing();
}

function showStudentInfo(student, note) {
    $('#studentName').text(student.name);
    $('#studentPrn').text(student.ern);
    $('#studentDept').text(student.dept);
    $('#markingStatus').text(note);
    if (student.photo) {
        $('#studentPhoto').attr('src', `data:image/jpeg;base64,${student.photo}`).show();
    } else {
        $('#studentPhoto').attr('src', '').hide();
    }
    $('#studentInfo').stop(true, true).fadeIn(250);
    setTimeout(() => $('#studentInfo').fadeOut(250), 3000);
}

function completeProcessing(delay = 1000) {
    setTimeout(() => {
        isProcessing = false;
        showStatus('Ready to scan QR code...', 'info', 'bi-camera');
    }, delay);
}

function refreshAttendanceTable() {
    $.get('?refresh=1')
        .done(function(html) {
            $('#attendanceTable').html(html).removeClass('refreshing');
        })
        .fail(function() {
            $('#attendanceTable').html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>').removeClass('refreshing');
        });
}

function updateLastUpdated() {
    $('#lastUpdated').text('Last updated: ' + new Date().toLocaleTimeString());
}

$(document).ready(function() {
    initScanner();
    refreshAttendanceTable();
    updateLastUpdated();
    renderFeed();
    $('#cameraSwitch').click(switchCamera);
    setInterval(function() {
        if (!isProcessing) {
            refreshAttendanceTable();
            updateLastUpdated();
        }
    }, 30000);
});
</script>
HTML;
sam_page_end($scripts);
?>











