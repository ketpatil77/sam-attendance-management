<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}

require_once 'attendance_rules.php';
require_once 'app_ui.php';

$success = '';
$error = '';
$search = trim((string)($_GET['search'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['toggle_rule'])) {
            $enabled = isset($_POST['rule_enabled']) ? '1' : '0';
            sam_set_setting($pdo, SAM_EARLY_OUT_SETTING_KEY, $enabled, $_SESSION['admin_user']);
            $success = $enabled === '1'
                ? 'Early OUT rule enabled. Students need exception or 4 PM.'
                : 'Early OUT rule disabled. All students can mark OUT before 4 PM.';
        } elseif (isset($_POST['add_exception'])) {
            $ern = trim((string)($_POST['ern_no'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($ern === '') {
                throw new RuntimeException('ERN is required.');
            }
            $student = sam_add_early_out_exception($pdo, $ern, $reason, $_SESSION['admin_user']);
            $success = 'Exception saved for ' . $student['STUDENT_NAME'] . ' (' . $student['ERN_NO'] . ').';
        } elseif (isset($_POST['remove_exception'])) {
            $ern = trim((string)($_POST['ern_no'] ?? ''));
            sam_remove_early_out_exception($pdo, $ern);
            $success = 'Exception removed for ERN ' . $ern . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ruleEnabled = sam_is_early_out_rule_enabled($pdo);
$exceptions = sam_get_early_out_exceptions($pdo, $search);

sam_render_head('Early OUT Exception Settings');
sam_page_start(
    'Admin Control',
    'Early OUT Rule Settings',
    'Manage the 4 PM cutoff, exception ERNs, and quick student permissions from one page.',
    [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'class' => 'btn btn-outline-secondary', 'icon' => 'bi bi-grid'],
        ['href' => 'attendance_out.php', 'label' => 'OUT Scanner', 'class' => 'btn btn-outline-primary', 'icon' => 'bi bi-qr-code-scan'],
    ]
);
sam_render_alerts([
    ['type' => 'success', 'message' => $success],
    ['type' => 'danger', 'message' => $error],
]);
?>

<div class="sam-grid sam-grid-2 mb-4">
    <div class="sam-card">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h4 class="mb-2">Rule Status</h4>
                <p class="sam-inline-note mb-0">Default: no student can mark OUT before 4:00 PM unless ERN is in exception list.</p>
            </div>
            <span class="badge <?= $ruleEnabled ? 'bg-success' : 'bg-danger' ?>">
                <?= $ruleEnabled ? 'Rule ON' : 'Rule OFF' ?>
            </span>
        </div>

        <?php if (!$ruleEnabled): ?>
            <div class="sam-rule-banner mb-3">
                <strong>Warning:</strong> Early OUT rule is OFF. Any student with valid IN can mark OUT before 4 PM.
            </div>
        <?php endif; ?>

        <form method="post" class="d-flex flex-column gap-3">
            <div class="form-check form-switch fs-5">
                <input class="form-check-input" type="checkbox" role="switch" id="rule_enabled" name="rule_enabled" <?= $ruleEnabled ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="rule_enabled">Enable early OUT block before 4:00 PM</label>
            </div>
            <button type="submit" name="toggle_rule" class="btn <?= $ruleEnabled ? 'btn-danger' : 'btn-success' ?>">
                <i class="bi <?= $ruleEnabled ? 'bi-lock-fill' : 'bi-unlock-fill' ?> me-2"></i>
                <?= $ruleEnabled ? 'Save Rule As ON' : 'Save Rule As OFF' ?>
            </button>
        </form>
    </div>

    <div class="sam-card">
        <h4 class="mb-3">Add Exception ERN</h4>
        <form method="post" class="sam-grid">
            <div>
                <label for="ern_no" class="form-label">Student ERN</label>
                <input type="text" class="form-control" id="ern_no" name="ern_no" placeholder="Enter exact ERN" required>
            </div>
            <div>
                <label for="reason" class="form-label">Reason</label>
                <input type="text" class="form-control" id="reason" name="reason" placeholder="Bus / medical / permission">
            </div>
            <button type="submit" name="add_exception" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add Or Update Exception
            </button>
        </form>
    </div>
</div>

<div class="sam-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h4 class="mb-1">Exception List</h4>
            <p class="sam-inline-note mb-0">Students here can mark OUT before 4 PM even while rule stays ON.</p>
        </div>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search ERN, student, reason">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-2"></i>Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ERN</th>
                    <th>Student</th>
                    <th>Reason</th>
                    <th>Added On</th>
                    <th>Added By</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($exceptions): ?>
                <?php foreach ($exceptions as $row): ?>
                    <tr>
                        <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= htmlspecialchars($row['ern_no']) ?></span></td>
                        <td><?= htmlspecialchars($row['student_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['reason'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['created_at'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['created_by'] ?: '-') ?></td>
                        <td class="text-center">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="ern_no" value="<?= htmlspecialchars($row['ern_no']) ?>">
                                <button type="submit" name="remove_exception" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash3 me-1"></i>Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="sam-empty-state">No exception ERNs found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php sam_page_end(); ?>











