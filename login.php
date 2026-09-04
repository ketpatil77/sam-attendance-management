<?php
session_start();
require 'db.php';
require_once 'app_ui.php';

$error = '';
$redirect = trim((string)($_REQUEST['redirect'] ?? ''));
if ($redirect !== '' && !preg_match('/^[A-Za-z0-9._\\/-]+(?:\\?[A-Za-z0-9._=&%-]+)?$/', $redirect)) {
    $redirect = '';
}

if (!empty($_SESSION['admin_user'])) {
    header('Location: ' . ($redirect !== '' ? $redirect : 'dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM user WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_user'] = $user['username'];
        header('Location: ' . ($redirect !== '' ? $redirect : 'dashboard.php'));
        exit;
    }

    $error = 'Invalid username or password';
}

sam_render_head('Admin Login');
sam_page_start(
    'Secure Access',
    'Admin Login',
    'Use admin credentials to manage students, reports, and early OUT exception controls.',
    [
        ['href' => 'index.php', 'label' => 'Back Home', 'class' => 'btn btn-outline-secondary', 'icon' => 'bi bi-house-door'],
    ]
);
sam_render_alerts([
    ['type' => 'danger', 'message' => $error],
]);
?>

<div class="sam-grid sam-grid-2 align-items-stretch">
    <div class="sam-highlight d-flex flex-column justify-content-center">
        <h4><i class="bi bi-shield-lock-fill me-2"></i>Admin control center</h4>
        <p class="mb-3">After login you can register students, control early leave permissions, open reports, and monitor attendance activity.</p>
        <div class="sam-inline-note">Use configured administrator credentials to continue.</div>
    </div>

    <div class="sam-card">
        <form method="POST" class="sam-grid">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <div>
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            <div>
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
        </form>
    </div>
</div>

<?php sam_page_end(); ?>










