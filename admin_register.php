<?php
require 'db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$username = getenv('SAM_ADMIN_USERNAME');
$plainPassword = getenv('SAM_ADMIN_PASSWORD');

if (!$username || !$plainPassword) {
    fwrite(STDERR, "Set SAM_ADMIN_USERNAME and SAM_ADMIN_PASSWORD before running this script.\n");
    exit(1);
}

$password = password_hash($plainPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO user (username, password) VALUES (?, ?)");
$stmt->execute([$username, $password]);

echo "Admin user created.";










