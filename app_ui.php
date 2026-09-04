<?php

function sam_render_head(string $title, string $extraHead = ''): void
{
    $themeBoot = sam_theme_boot_script_tag();
    $cssVersion = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'sam-ui.css') ?: time();
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    {$themeBoot}
    <link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/sam-ui.css?v={$cssVersion}">
    {$extraHead}
</head>
HTML;
}

function sam_page_start(string $kicker, string $title, string $subtitle = '', array $actions = []): void
{
    echo '<body class="sam-body">';
    echo '<div class="sam-shell"><div class="sam-page">';
    echo '<div class="sam-topbar">';
    echo '<div class="sam-title-block">';
    if ($kicker !== '') {
        echo '<div class="sam-kicker">' . $kicker . '</div>';
    }
    echo '<h1 class="sam-title">' . htmlspecialchars($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="sam-subtitle">' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</div>';

    echo '<div class="sam-actions">';
    $hasHomeAction = false;
    $hasLogoutAction = false;
    foreach ($actions as $action) {
        $href = (string)($action['href'] ?? '');
        if ($href === 'index.php' || $href === './index.php' || $href === '/') {
            $hasHomeAction = true;
        }
        if ($href === 'logout.php' || $href === './logout.php') {
            $hasLogoutAction = true;
        }
    }
    if (!$hasHomeAction) {
        echo '<a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house-door me-2"></i>Home</a>';
    }
    foreach ($actions as $action) {
        $href = htmlspecialchars($action['href'] ?? '#');
        $label = htmlspecialchars($action['label'] ?? 'Action');
        $class = htmlspecialchars($action['class'] ?? 'btn btn-outline-secondary');
        $icon = $action['icon'] ?? '';
        $iconHtml = $icon !== '' ? '<i class="' . htmlspecialchars($icon) . ' me-2"></i>' : '';
        echo '<a href="' . $href . '" class="' . $class . '">' . $iconHtml . $label . '</a>';
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_user']) && !$hasLogoutAction) {
        echo '<a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>';
    }
    echo sam_theme_toggle_html();
    echo '</div>';

    echo '</div>';
}

function sam_page_end(string $extraScripts = ''): void
{
    $themeController = sam_theme_controller_script_tag();
    echo '</div></div>';
    echo '<script src="assets/vendor/jquery.min.js"></script>';
    echo '<script src="assets/vendor/bootstrap.bundle.min.js"></script>';
    echo $themeController;
    echo $extraScripts;
    echo '</body></html>';
}

function sam_render_alerts(array $alerts): void
{
    foreach ($alerts as $alert) {
        $message = trim((string)($alert['message'] ?? ''));
        if ($message === '') {
            continue;
        }
        $type = htmlspecialchars($alert['type'] ?? 'info');
        echo '<div class="alert alert-' . $type . '">' . htmlspecialchars($message) . '</div>';
    }
}

function sam_theme_toggle_html(): string
{
    return <<<HTML
<div class="sam-theme-toggle" role="group" aria-label="Theme switch">
    <button type="button" class="sam-theme-btn" data-theme-switch="light" aria-pressed="false" title="Light theme">
        <i class="bi bi-sun-fill"></i>
    </button>
    <button type="button" class="sam-theme-btn" data-theme-switch="dark" aria-pressed="false" title="Dark theme">
        <i class="bi bi-moon-stars-fill"></i>
    </button>
</div>
HTML;
}

function sam_chip(string $label, string $variant = 'default', string $icon = ''): string
{
    $map = [
        'default' => 'sam-chip',
        'success' => 'sam-chip sam-chip-success',
        'danger' => 'sam-chip sam-chip-danger',
        'warning' => 'sam-chip sam-chip-warning',
    ];
    $class = $map[$variant] ?? $map['default'];
    $iconHtml = $icon !== '' ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '';
    return '<span class="' . $class . '">' . $iconHtml . htmlspecialchars($label) . '</span>';
}

function sam_feed_item(string $title, string $meta = '', string $variant = 'default'): string
{
    $class = 'sam-feed-item';
    if ($variant === 'danger') {
        $class .= ' border-danger-subtle';
    } elseif ($variant === 'success') {
        $class .= ' border-success-subtle';
    } elseif ($variant === 'warning') {
        $class .= ' border-warning-subtle';
    }

    $metaHtml = $meta !== '' ? '<div class="sam-feed-meta">' . htmlspecialchars($meta) . '</div>' : '';
    return '<div class="' . $class . '"><div class="sam-feed-title">' . htmlspecialchars($title) . '</div>' . $metaHtml . '</div>';
}

function sam_admin_login_url(): string
{
    return 'admin-login.php';
}

function sam_is_admin_authenticated(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return !empty($_SESSION['admin_user']);
}

function sam_require_admin(): void
{
    if (sam_is_admin_authenticated()) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = trim((string)parse_url($requestUri, PHP_URL_PATH), '/');
    $query = (string)parse_url($requestUri, PHP_URL_QUERY);
    $target = $path !== '' ? $path . ($query !== '' ? '?' . $query : '') : 'report.php';

    header('Location: ' . sam_admin_login_url() . '?redirect=' . rawurlencode($target));
    exit;
}

function sam_theme_boot_script_tag(): string
{
    return <<<'HTML'
<script>
(() => {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Kolkata';
        document.cookie = 'sam_tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
        document.documentElement.dataset.theme = localStorage.getItem('samTheme') || 'light';
    } catch (e) {
        document.documentElement.dataset.theme = 'light';
    }
})();
</script>
HTML;
}

function sam_theme_controller_script_tag(): string
{
    return <<<'HTML'
<script>
(() => {
    const root = document.documentElement;
    const applyTheme = (theme) => {
        root.dataset.theme = theme;
        try {
            localStorage.setItem('samTheme', theme);
        } catch (e) {}
        document.querySelectorAll('[data-theme-switch]').forEach((button) => {
            const active = button.dataset.themeSwitch === theme;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    applyTheme(root.dataset.theme || 'light');

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-switch]');
        if (!button) return;
        applyTheme(button.dataset.themeSwitch);
    });
})();
</script>
HTML;
}










