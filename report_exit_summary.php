<?php
require_once 'attendance_rules.php';
require_once 'app_ui.php';
sam_require_admin();

$kpis = sam_get_today_attendance_kpis($pdo);
$recentOut = sam_get_recent_out_activity($pdo, 20);
$rows = sam_get_exception_used_rows($pdo, 20);
sam_render_head('Exit Summary Today');
sam_page_start('Analysis', 'Exit Summary Today', 'Quick view of OUT flow, still-inside count, and early-exit behavior.');
?>
<div class="sam-grid sam-grid-3 mb-4">
    <div class="sam-stat"><div class="sam-stat-label">OUT Today</div><div class="sam-stat-value"><?= $kpis['total_out'] ?></div></div>
    <div class="sam-stat"><div class="sam-stat-label">Still Inside</div><div class="sam-stat-value"><?= $kpis['still_inside'] ?></div></div>
    <div class="sam-stat"><div class="sam-stat-label">Exception Exits</div><div class="sam-stat-value"><?= $kpis['exception_used_today'] ?></div></div>
</div>

<div class="sam-dashboard-grid">
    <div class="sam-card">
        <div class="sam-section-head">
            <h4 class="sam-section-title">Recent OUT Records</h4>
            <button class="btn btn-dark btn-sm" onclick="window.print()">Print</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark"><tr><th>Name</th><th>ERN</th><th>Department</th><th>Year</th><th>OUT Time</th></tr></thead>
                <tbody>
                <?php if ($recentOut): foreach ($recentOut as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['STUDENT_NAME']) ?></td>
                        <td><?= htmlspecialchars($row['ERN_NO']) ?></td>
                        <td><?= htmlspecialchars($row['DEPARTMENT']) ?></td>
                        <td><?= htmlspecialchars($row['AYEAR']) ?></td>
                        <td><?= htmlspecialchars($row['OUTTIME']) ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="sam-empty-state">No OUT records today.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sam-card">
        <div class="sam-section-head">
            <h4 class="sam-section-title">Exception Exit Rows</h4>
        </div>
        <div class="sam-feed">
            <?php if ($rows): foreach ($rows as $row): ?>
                <?= sam_feed_item($row['STUDENT_NAME'], $row['ERN_NO'] . ' â€¢ OUT ' . $row['OUTTIME'] . ' â€¢ ' . ($row['reason'] ?: 'No reason'), 'danger') ?>
            <?php endforeach; else: ?>
                <div class="sam-empty-state">No exception exits today.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php sam_page_end(); ?>











