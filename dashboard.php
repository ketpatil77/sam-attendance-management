<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}

require_once 'attendance_rules.php';
require_once 'app_ui.php';

if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'kpis' => sam_get_today_attendance_kpis($pdo),
        'recent_in' => sam_get_recent_in_activity($pdo, 6),
        'recent_out' => sam_get_recent_out_activity($pdo, 6),
        'still_inside' => sam_get_students_still_inside($pdo, 8),
        'exception_used' => sam_get_exception_used_rows($pdo, 6),
    ]);
    exit;
}

$kpis = sam_get_today_attendance_kpis($pdo);
$recentIn = sam_get_recent_in_activity($pdo, 6);
$recentOut = sam_get_recent_out_activity($pdo, 6);
$stillInside = sam_get_students_still_inside($pdo, 8);
$exceptionUsed = sam_get_exception_used_rows($pdo, 6);

$buttons = [
    ['href' => 'student_register.php', 'title' => 'Student Registration', 'desc' => 'Create, search, update, and remove student records.', 'icon' => 'bi bi-person-vcard-fill'],
    ['href' => 'student_analytics.php', 'title' => 'Student Analytics', 'desc' => 'Search one student and view charts, streaks, and export tools.', 'icon' => 'bi bi-search-heart-fill'],
    ['href' => 'staff.php', 'title' => 'Staff Registration', 'desc' => 'Add staff master records with photo and profile details.', 'icon' => 'bi bi-person-badge-fill'],
    ['href' => 'early_out_settings.php', 'title' => 'Early OUT Settings', 'desc' => 'Toggle 4 PM rule and manage exception ERNs.', 'icon' => 'bi bi-sliders'],
    ['href' => 'attendance_in.php', 'title' => 'Start IN Scanner', 'desc' => 'Open QR IN scanner for students.', 'icon' => 'bi bi-box-arrow-in-right'],
    ['href' => 'attendance_out.php', 'title' => 'Start OUT Scanner', 'desc' => 'Open QR OUT scanner with early-leave control.', 'icon' => 'bi bi-box-arrow-right'],
    ['href' => 'report.php', 'title' => 'Reports Menu', 'desc' => 'Jump to attendance and analytics reports.', 'icon' => 'bi bi-bar-chart-line-fill'],
];

sam_render_head('Admin Dashboard');
sam_page_start(
    'Control Room',
    'Welcome, ' . $_SESSION['admin_user'],
    'Live command center for scanners, rules, exceptions, and daily attendance signals.'
);
?>

<?php if (!$kpis['rule_enabled']): ?>
    <div class="sam-rule-banner mb-4">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Early OUT rule is OFF.</strong>
        Any student with valid IN can mark OUT before 4 PM until you enable the rule again.
    </div>
<?php endif; ?>

<?php if ($kpis['exception_count'] > 10): ?>
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-diamond-fill me-2"></i>High exception count. Review early OUT list now.
    </div>
<?php endif; ?>

<div class="sam-grid sam-grid-3 mb-4" id="kpiGrid">
    <div class="sam-stat" data-kpi="total_in"><div class="sam-stat-label">Total IN Today</div><div class="sam-stat-value"><?= $kpis['total_in'] ?></div></div>
    <div class="sam-stat" data-kpi="total_out"><div class="sam-stat-label">Total OUT Today</div><div class="sam-stat-value"><?= $kpis['total_out'] ?></div></div>
    <div class="sam-stat" data-kpi="still_inside"><div class="sam-stat-label">Still Inside</div><div class="sam-stat-value"><?= $kpis['still_inside'] ?></div></div>
    <div class="sam-stat" data-kpi="rule_enabled"><div class="sam-stat-label">Rule Status</div><div class="sam-stat-value"><?= $kpis['rule_enabled'] ? 'ON' : 'OFF' ?></div></div>
    <div class="sam-stat" data-kpi="exception_count"><div class="sam-stat-label">Active Exceptions</div><div class="sam-stat-value"><?= $kpis['exception_count'] ?></div></div>
    <div class="sam-stat" data-kpi="exception_used_today"><div class="sam-stat-label">Exception Exits Today</div><div class="sam-stat-value"><?= $kpis['exception_used_today'] ?></div></div>
</div>

<div class="sam-menu-grid mb-4">
    <?php foreach ($buttons as $button): ?>
        <a class="sam-menu-link" href="<?= htmlspecialchars($button['href']) ?>">
            <div class="fs-3 text-primary"><i class="<?= htmlspecialchars($button['icon']) ?>"></i></div>
            <strong><?= htmlspecialchars($button['title']) ?></strong>
            <span><?= htmlspecialchars($button['desc']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="sam-dashboard-grid">
    <div class="sam-dashboard-stack">
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-arrow-down-right-circle me-2"></i>Latest IN</h4>
                <?= sam_chip('Live', 'success', 'bi bi-lightning-charge-fill') ?>
            </div>
            <div id="recentInFeed" class="sam-feed">
                <?php if ($recentIn): foreach ($recentIn as $row): ?>
                    <?= sam_feed_item($row['STUDENT_NAME'], $row['ERN_NO'] . ' â€¢ ' . $row['DEPARTMENT'] . ' â€¢ IN ' . $row['INTIME'], 'success') ?>
                <?php endforeach; else: ?>
                    <div class="sam-empty-state">No IN activity yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-box-arrow-right me-2"></i>Latest OUT</h4>
                <?= sam_chip('Updated', 'default', 'bi bi-clock-history') ?>
            </div>
            <div id="recentOutFeed" class="sam-feed">
                <?php if ($recentOut): foreach ($recentOut as $row): ?>
                    <?= sam_feed_item($row['STUDENT_NAME'], $row['ERN_NO'] . ' â€¢ ' . $row['DEPARTMENT'] . ' â€¢ OUT ' . $row['OUTTIME'], 'default') ?>
                <?php endforeach; else: ?>
                    <div class="sam-empty-state">No OUT activity yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sam-dashboard-stack">
        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-door-open me-2"></i>Students Still Inside</h4>
                <a href="report_inside_today.php" class="btn btn-sm btn-outline-primary">Open Report</a>
            </div>
            <div id="stillInsideFeed" class="sam-feed">
                <?php if ($stillInside): foreach ($stillInside as $row): ?>
                    <?= sam_feed_item($row['STUDENT_NAME'], $row['ERN_NO'] . ' â€¢ ' . $row['DEPARTMENT'] . ' â€¢ IN ' . $row['INTIME'], 'warning') ?>
                <?php endforeach; else: ?>
                    <div class="sam-empty-state">Nobody waiting inside.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sam-card">
            <div class="sam-section-head">
                <h4 class="sam-section-title"><i class="bi bi-unlock me-2"></i>Exception Usage Today</h4>
                <a href="report_exception_usage.php" class="btn btn-sm btn-outline-primary">Open Report</a>
            </div>
            <div id="exceptionFeed" class="sam-feed">
                <?php if ($exceptionUsed): foreach ($exceptionUsed as $row): ?>
                    <?= sam_feed_item($row['STUDENT_NAME'], $row['ERN_NO'] . ' â€¢ OUT ' . $row['OUTTIME'] . ' â€¢ ' . ($row['reason'] ?: 'No reason'), 'danger') ?>
                <?php endforeach; else: ?>
                    <div class="sam-empty-state">No exception exits today.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$scripts = <<<'HTML'
<script>
function renderFeed(containerId, rows, mode) {
    const target = document.getElementById(containerId);
    if (!rows.length) {
        target.innerHTML = `<div class="sam-empty-state">${mode.empty}</div>`;
        return;
    }
    target.innerHTML = rows.map((row) => {
        const meta = mode.meta(row);
        return `<div class="sam-feed-item border-${mode.variant}-subtle"><div class="sam-feed-title">${row.STUDENT_NAME}</div><div class="sam-feed-meta">${meta}</div></div>`;
    }).join('');
}

function pulseKpi(key, value) {
    const card = document.querySelector(`[data-kpi="${key}"]`);
    if (!card) return;
    const valueEl = card.querySelector('.sam-stat-value');
    if (String(valueEl.textContent).trim() !== String(value)) {
        valueEl.textContent = value;
        card.dataset.pulse = 'true';
        setTimeout(() => { card.dataset.pulse = 'false'; }, 850);
    }
}

function refreshDashboard() {
    fetch('?refresh=1')
        .then((response) => response.json())
        .then((data) => {
            pulseKpi('total_in', data.kpis.total_in);
            pulseKpi('total_out', data.kpis.total_out);
            pulseKpi('still_inside', data.kpis.still_inside);
            pulseKpi('rule_enabled', data.kpis.rule_enabled ? 'ON' : 'OFF');
            pulseKpi('exception_count', data.kpis.exception_count);
            pulseKpi('exception_used_today', data.kpis.exception_used_today);

            renderFeed('recentInFeed', data.recent_in, {
                empty: 'No IN activity yet.',
                variant: 'success',
                meta: (row) => `${row.ERN_NO} â€¢ ${row.DEPARTMENT} â€¢ IN ${row.INTIME}`
            });
            renderFeed('recentOutFeed', data.recent_out, {
                empty: 'No OUT activity yet.',
                variant: 'primary',
                meta: (row) => `${row.ERN_NO} â€¢ ${row.DEPARTMENT} â€¢ OUT ${row.OUTTIME}`
            });
            renderFeed('stillInsideFeed', data.still_inside, {
                empty: 'Nobody waiting inside.',
                variant: 'warning',
                meta: (row) => `${row.ERN_NO} â€¢ ${row.DEPARTMENT} â€¢ IN ${row.INTIME}`
            });
            renderFeed('exceptionFeed', data.exception_used, {
                empty: 'No exception exits today.',
                variant: 'danger',
                meta: (row) => `${row.ERN_NO} â€¢ OUT ${row.OUTTIME} â€¢ ${row.reason || 'No reason'}`
            });
        })
        .catch(() => {});
}

setInterval(refreshDashboard, 45000);
</script>
HTML;
sam_page_end($scripts);
?>











