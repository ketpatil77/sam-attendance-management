<?php
require_once 'attendance_rules.php';
require_once 'app_ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ern'])) {
    $ern = trim((string)$_POST['ern']);
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    $response = ['status' => 'error', 'message' => 'Unknown error'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT sd.*, si.PHOTO, si.STUDENT_NAME, si.DEPARTMENT
             FROM student_daily sd
             JOIN student_information si ON sd.RID = si.RID
             WHERE sd.ERN_NO = ?
             AND sd.EDATE = ?
             AND sd.STATUS = 'IN'"
        );
        $stmt->execute([$ern, $today]);
        $record = $stmt->fetch();

        if ($record) {
            $policy = sam_can_mark_out_now($pdo, $ern, $currentTime);
            if (!$policy['allowed']) {
                $pdo->rollBack();
                $response = [
                    'status' => 'blocked_early_out',
                    'message' => $policy['message'],
                    'student' => [
                        'name' => $record['STUDENT_NAME'],
                        'ern' => $record['ERN_NO'],
                        'dept' => $record['DEPARTMENT'],
                        'photo' => $record['PHOTO'] ? base64_encode($record['PHOTO']) : null,
                        'in_time' => $record['INTIME'],
                    ],
                ];
                echo json_encode($response);
                exit;
            }

            $update = $pdo->prepare(
                "UPDATE student_daily
                 SET OUTTIME = ?, STATUS = 'PRESENT'
                 WHERE ERN_NO = ? AND EDATE = ? AND STATUS = 'IN'"
            );
            $update->execute([$currentTime, $ern, $today]);

            $response = [
                'status' => 'success',
                'message' => 'OUT time marked successfully',
                'student' => [
                    'name' => $record['STUDENT_NAME'],
                    'ern' => $record['ERN_NO'],
                    'dept' => $record['DEPARTMENT'],
                    'photo' => $record['PHOTO'] ? base64_encode($record['PHOTO']) : null,
                    'in_time' => $record['INTIME'],
                ],
            ];
            $pdo->commit();
        } else {
            $check = $pdo->prepare(
                "SELECT 1 FROM student_daily
                 WHERE ERN_NO = ?
                 AND EDATE = ?
                 AND STATUS = 'PRESENT'"
            );
            $check->execute([$ern, $today]);

            if ($check->fetch()) {
                $response = ['status' => 'already_out', 'message' => 'Already marked OUT today'];
            } else {
                $response = ['status' => 'no_in', 'message' => 'IN record not found'];
            }
            $pdo->rollBack();
        }
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
    try {
        $stmt = $pdo->prepare(
            "SELECT sd.*, si.PHOTO
             FROM student_daily sd
             JOIN student_information si ON sd.RID = si.RID
             WHERE sd.EDATE = ? AND sd.STATUS = 'PRESENT'
             ORDER BY sd.OUTTIME DESC"
        );
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            echo '<tr><td colspan="7" class="sam-empty-state">No OUT attendance marked yet today.</td></tr>';
            exit;
        }

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . ($row['PHOTO'] ? "<img src='data:image/jpeg;base64," . base64_encode($row['PHOTO']) . "' height='56' width='56' class='sam-photo-thumb'>" : "<span class='text-muted'>No Photo</span>") . '</td>';
            echo '<td>' . htmlspecialchars($row['STUDENT_NAME']) . '</td>';
            echo '<td>' . htmlspecialchars($row['ERN_NO']) . '</td>';
            echo '<td>' . htmlspecialchars($row['INTIME']) . '</td>';
            echo '<td>' . htmlspecialchars($row['OUTTIME']) . '</td>';
            echo '<td>' . htmlspecialchars($row['DEPARTMENT']) . '</td>';
            echo '<td>' . htmlspecialchars($row['AYEAR']) . '</td>';
            echo '</tr>';
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='7' class='text-center text-danger'>Error loading data</td></tr>";
    }
    exit;
}

$ruleEnabled = sam_is_early_out_rule_enabled($pdo);

sam_render_head(
    'QR Attendance (OUT)',
    '<script src="assets/vendor/html5-qrcode.min.js"></script>'
);
sam_page_start(
    'Student Exit',
    'Mark Attendance (OUT)',
    'Scan fast. Rule state, blocked attempts, and recent exits stay on one operator screen.',
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
                <?= $ruleEnabled ? sam_chip('Early OUT rule ON', 'warning', 'bi bi-lock-fill') : sam_chip('Early OUT rule OFF', 'success', 'bi bi-unlock-fill') ?>
            </div>
            <div id="reader"></div>
            <div class="sam-toolbar mt-3">
                <div class="sam-toolbar-group">
                    <button id="cameraSwitch" class="btn btn-sm btn-secondary" style="display:none;">
                        <i class="bi bi-camera-reverse me-2"></i>Switch Camera
                    </button>
                </div>
                <div class="sam-inline-note">Blocked before 4 PM unless rule OFF or ERN allowed.</div>
            </div>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-list-check me-2"></i>Today's OUT Attendance</h4>
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
                            <th>OUT Time</th>
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
                <?= sam_chip('Operator View', 'default', 'bi bi-person-badge') ?>
            </div>
            <div class="text-center">
                <img id="studentPhoto" src="" class="rounded-circle mb-3" height="92" width="92" alt="Student photo">
                <h4 id="studentName" class="mb-2"></h4>
                <div class="text-muted mb-3">
                    <span id="studentPrn" class="badge bg-primary me-2"></span>
                    <span id="studentDept" class="badge bg-secondary"></span>
                </div>
                <div class="d-flex justify-content-center flex-wrap gap-2 mb-2">
                    <span class="badge bg-success"><i class="bi bi-box-arrow-in-right me-1"></i>IN: <span id="studentInTime"></span></span>
                    <span class="badge bg-danger"><i class="bi bi-box-arrow-right me-1"></i>OUT: <span id="studentOutTime"></span></span>
                </div>
                <div id="markingStatus" class="mt-2 sam-inline-note"></div>
            </div>
        </div>

        <div class="sam-highlight">
            <h5><i class="bi bi-shield-check me-2"></i>Status</h5>
            <p class="mb-1">Green: marked.</p>
            <p class="mb-1">Yellow: already out.</p>
            <p class="mb-0">Red: missing IN or blocked by rule.</p>
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

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-ban me-2"></i>Blocked Attempts</h4>
                <span class="sam-inline-note">Current session</span>
            </div>
            <div id="blockedFeed" class="sam-feed">
                <div class="sam-empty-state">No blocked attempts.</div>
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
const blockedFeed = [];

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
    markOutAttendance(decodedText.trim());
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
    if (type === 'danger' && message.includes('not allowed')) {
        statusEl.addClass('sam-status-blocked');
    }
    statusEl.html(icon ? `<i class="bi ${icon} me-2"></i>${message}` : message);
}

function addFeed(feed, result, student = {}, detail = '') {
    const entry = {
        result,
        name: student.name || detail || 'Unknown',
        ern: student.ern || '-',
        time: new Date().toLocaleTimeString()
    };
    feed.unshift(entry);
    if (feed.length > 10) feed.pop();
}

function renderFeed(feed, target, emptyText) {
    const feedEl = $(target);
    if (!feed.length) {
        feedEl.html(`<div class="sam-empty-state">${emptyText}</div>`);
        return;
    }
    feedEl.html(feed.map((entry) => {
        const variant = entry.result === 'success' ? 'success' : (entry.result === 'warning' ? 'warning' : 'danger');
        return `
            <div class="sam-feed-item border-${variant}-subtle">
                <div class="sam-feed-title">${entry.name}</div>
                <div class="sam-feed-meta">${entry.ern} â€¢ ${entry.result.toUpperCase()} â€¢ ${entry.time}</div>
            </div>
        `;
    }).join(''));
}

function markOutAttendance(ern) {
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
        addFeed(scanFeed, 'error', {}, 'Server communication error');
        renderFeed(scanFeed, '#scanFeed', 'No scans yet.');
        completeProcessing();
    }).always(function() {
        refreshAttendanceTable();
        updateLastUpdated();
    });
}

function handleResponse(response) {
    switch (response.status) {
        case 'success':
            showStatus('OUT time marked successfully!', 'success', 'bi-check-circle-fill');
            showStudentInfo(response.student, false);
            addFeed(scanFeed, 'success', response.student);
            break;
        case 'blocked_early_out':
            showStatus(response.message, 'danger', 'bi-slash-circle-fill');
            showStudentInfo(response.student, true);
            addFeed(scanFeed, 'error', response.student);
            addFeed(blockedFeed, 'danger', response.student);
            renderFeed(blockedFeed, '#blockedFeed', 'No blocked attempts.');
            break;
        case 'already_out':
            showStatus('Already marked OUT today', 'warning', 'bi-exclamation-circle-fill');
            addFeed(scanFeed, 'warning', { name: 'Already marked out', ern: '-' });
            break;
        case 'no_in':
            showStatus('IN record not found', 'danger', 'bi-x-circle-fill');
            addFeed(scanFeed, 'error', { name: 'Missing IN record', ern: '-' });
            break;
        default:
            showStatus(response.message || 'Error processing request', 'danger', 'bi-exclamation-triangle-fill');
            addFeed(scanFeed, 'error', { name: 'Processing error', ern: '-' }, response.message || 'Error');
    }
    renderFeed(scanFeed, '#scanFeed', 'No scans yet.');
    completeProcessing(response.status === 'blocked_early_out' ? 2200 : 1100);
}

function showStudentInfo(student, blocked) {
    if (!student) return;
    $('#studentName').text(student.name);
    $('#studentPrn').text(student.ern);
    $('#studentDept').text(student.dept);
    $('#studentInTime').text(student.in_time || '-');
    $('#studentOutTime').text(blocked ? 'Blocked' : new Date().toLocaleTimeString());
    $('#markingStatus').text(blocked ? 'Blocked by early OUT rule before 4 PM.' : 'Attendance updated successfully.');
    if (student.photo) {
        $('#studentPhoto').attr('src', `data:image/jpeg;base64,${student.photo}`).show();
    } else {
        $('#studentPhoto').attr('src', '').hide();
    }
    $('#studentInfo').stop(true, true).fadeIn(250);
    setTimeout(() => $('#studentInfo').fadeOut(250), blocked ? 5000 : 3200);
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
            $('#attendanceTable').html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>').removeClass('refreshing');
        });
}

function updateLastUpdated() {
    $('#lastUpdated').text('Last updated: ' + new Date().toLocaleTimeString());
}

$(document).ready(function() {
    initScanner();
    refreshAttendanceTable();
    updateLastUpdated();
    renderFeed(scanFeed, '#scanFeed', 'No scans yet.');
    renderFeed(blockedFeed, '#blockedFeed', 'No blocked attempts.');
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











