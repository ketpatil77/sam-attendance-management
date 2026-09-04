<?php
require_once 'attendance_rules.php';
require_once 'app_ui.php';
sam_require_admin();

$kpis = sam_get_today_attendance_kpis($pdo);
$reports = [
    'Daily Ops' => [
        ['href' => 'report_today.php', 'title' => 'Today Summary', 'desc' => 'Current day present and absent overview.', 'icon' => 'bi bi-calendar-check-fill'],
        ['href' => 'report_inside_today.php', 'title' => 'Students Still Inside', 'desc' => 'List students who marked IN but not OUT today.', 'icon' => 'bi bi-door-open-fill'],
        ['href' => 'report_wout.php', 'title' => 'Without OUT Today', 'desc' => 'Find students who did not mark OUT today.', 'icon' => 'bi bi-person-x-fill'],
        ['href' => 'report_wout_ondate.php', 'title' => 'Without OUT On Date', 'desc' => 'Check left-without-OUT records by date.', 'icon' => 'bi bi-calendar-x-fill'],
    ],
    'Analysis' => [
        ['href' => 'report_between.php', 'title' => 'Between Dates', 'desc' => 'Attendance records across a selected date range.', 'icon' => 'bi bi-calendar-range'],
        ['href' => 'report_matrix.php', 'title' => 'Attendance Matrix', 'desc' => 'Day-wise P/A matrix for a full class.', 'icon' => 'bi bi-grid-3x3-gap-fill'],
        ['href' => 'report_late.php', 'title' => 'Late Students', 'desc' => 'Filter students marked IN after a selected time.', 'icon' => 'bi bi-alarm-fill'],
        ['href' => 'report_time.php', 'title' => 'Time Duration', 'desc' => 'Review IN, OUT, and stay duration by day.', 'icon' => 'bi bi-stopwatch-fill'],
        ['href' => 'report_exit_summary.php', 'title' => 'Exit Summary', 'desc' => 'See OUT flow, still-inside, and early-exit behavior.', 'icon' => 'bi bi-graph-up-arrow'],
        ['href' => 'report_exception_usage.php', 'title' => 'Exception Usage', 'desc' => 'See early exits that used exception permission.', 'icon' => 'bi bi-unlock-fill'],
        ['href' => 'report_qr_percentage.php', 'title' => 'Attendance Percentage', 'desc' => 'Calculate class-wise percentage charts and stats.', 'icon' => 'bi bi-bar-chart-fill'],
    ],
    'Master Data' => [
        ['href' => 'report_students.php', 'title' => 'Student Details', 'desc' => 'Master student data report.', 'icon' => 'bi bi-mortarboard-fill'],
        ['href' => 'student_analytics.php', 'title' => 'Student Analytics', 'desc' => 'Search one student and open the full detail analytics dashboard.', 'icon' => 'bi bi-search-heart-fill'],
        ['href' => 'report_staffdetails.php', 'title' => 'Staff Details', 'desc' => 'Search staff master details and photo records.', 'icon' => 'bi bi-person-lines-fill'],
    ],
];

sam_render_head('Attendance Reports Menu');
sam_page_start(
    'Report Center',
    'Attendance Reports Menu',
    'Open daily operations, analysis, and master reports from one command-style report hub.',
    [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'class' => 'btn btn-outline-primary', 'icon' => 'bi bi-grid'],
    ]
);
?>

<div class="sam-grid sam-grid-3 mb-4">
    <div class="sam-stat"><div class="sam-stat-label">Present / IN Today</div><div class="sam-stat-value"><?= $kpis['total_in'] ?></div></div>
    <div class="sam-stat"><div class="sam-stat-label">OUT Today</div><div class="sam-stat-value"><?= $kpis['total_out'] ?></div></div>
    <div class="sam-stat"><div class="sam-stat-label">Still Inside</div><div class="sam-stat-value"><?= $kpis['still_inside'] ?></div></div>
</div>

<div class="sam-highlight mb-4">
    <h4><i class="bi bi-clipboard-data-fill me-2"></i>Today Snapshot</h4>
    <p class="mb-1">Rule: <strong><?= $kpis['rule_enabled'] ? 'ON' : 'OFF' ?></strong></p>
    <p class="mb-1">Exception exits today: <strong><?= $kpis['exception_used_today'] ?></strong></p>
    <p class="mb-0">Use this page to jump fast between live ops and deeper analysis.</p>
</div>

<?php foreach ($reports as $group => $items): ?>
    <div class="sam-section-head mt-4">
        <h4 class="sam-section-title"><?= htmlspecialchars($group) ?></h4>
    </div>
    <div class="sam-menu-grid">
        <?php foreach ($items as $report): ?>
            <a class="sam-menu-link" href="<?= htmlspecialchars($report['href']) ?>">
                <div class="fs-3 text-primary"><i class="<?= htmlspecialchars($report['icon']) ?>"></i></div>
                <strong><?= htmlspecialchars($report['title']) ?></strong>
                <span><?= htmlspecialchars($report['desc']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php sam_page_end(); ?>










