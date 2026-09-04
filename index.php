<?php
require_once 'app_ui.php';

$menuItems = [
    ['href' => 'login.php', 'title' => 'Admin Login', 'desc' => 'Manage students, reports, and rule controls.', 'icon' => 'bi bi-shield-lock-fill'],
    ['href' => 'staff_in.php', 'title' => 'Staff In', 'desc' => 'Capture staff in-time with photo proof.', 'icon' => 'bi bi-person-check-fill'],
    ['href' => 'staff_out.php', 'title' => 'Staff Out', 'desc' => 'Capture staff out-time with verification.', 'icon' => 'bi bi-person-dash-fill'],
    ['href' => 'attendance_in.php', 'title' => 'Student IN Scanner', 'desc' => 'Scan QR and register student entry instantly.', 'icon' => 'bi bi-box-arrow-in-right'],
    ['href' => 'attendance_out.php', 'title' => 'Student OUT Scanner', 'desc' => 'Scan QR and enforce 4 PM early-exit rules.', 'icon' => 'bi bi-box-arrow-right'],
    ['href' => 'report.php', 'title' => 'Attendance Reports', 'desc' => 'Admin login required before any report opens.', 'icon' => 'bi bi-bar-chart-line-fill'],
];

sam_render_head('SAM QR Attendance System');
sam_page_start(
    'Smart Attendance',
    'SAM QR Attendance System',
    'One entry point for student QR scans, staff attendance capture, admin operations, and live reporting.'
);
?>

<div class="sam-grid sam-grid-2 mb-4">
    <div class="sam-highlight">
        <h4><i class="bi bi-lightning-charge-fill me-2"></i>Live flow</h4>
        <p class="mb-0">Students scan QR for IN and OUT. Staff pages capture photo proof. Admin panel handles master data, reports, and early-leave exceptions.</p>
    </div>
    <div class="sam-grid sam-grid-3">
        <div class="sam-stat">
            <div class="sam-stat-label">Scanner policy</div>
            <div class="sam-stat-value">4 PM</div>
        </div>
        <div class="sam-stat">
            <div class="sam-stat-label">Storage</div>
            <div class="sam-stat-value">SQLite</div>
        </div>
        <div class="sam-stat">
            <div class="sam-stat-label">Access</div>
            <div class="sam-stat-value">QR + Web</div>
        </div>
    </div>
</div>

<div class="sam-menu-grid">
    <?php foreach ($menuItems as $item): ?>
        <a class="sam-menu-link" href="<?= htmlspecialchars($item['href']) ?>">
            <div class="fs-3 text-primary"><i class="<?= htmlspecialchars($item['icon']) ?>"></i></div>
            <strong><?= htmlspecialchars($item['title']) ?></strong>
            <span><?= htmlspecialchars($item['desc']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php sam_page_end(); ?>










