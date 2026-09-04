<?php
require_once 'attendance_rules.php';
require_once 'app_ui.php';
sam_require_admin();

$rows = sam_get_students_still_inside($pdo, 500);
sam_render_head('Students Still Inside Today');
sam_page_start('Daily Ops', 'Students Still Inside Today', 'Students who marked IN today but have not marked OUT yet.');
?>
<div class="sam-card">
    <div class="sam-toolbar">
        <div class="sam-toolbar-group"><?= sam_chip('Count: ' . count($rows), count($rows) ? 'warning' : 'success', 'bi bi-people') ?></div>
        <div class="sam-toolbar-group"><button class="btn btn-dark" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button></div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark"><tr><th>Name</th><th>ERN</th><th>Department</th><th>Year</th><th>IN Time</th></tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['STUDENT_NAME']) ?></td>
                    <td><?= htmlspecialchars($row['ERN_NO']) ?></td>
                    <td><?= htmlspecialchars($row['DEPARTMENT']) ?></td>
                    <td><?= htmlspecialchars($row['AYEAR']) ?></td>
                    <td><?= htmlspecialchars($row['INTIME']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="sam-empty-state">Nobody waiting inside.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php sam_page_end(); ?>











