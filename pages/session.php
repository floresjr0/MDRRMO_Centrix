<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_base_path(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $script = str_replace('\\', '/', $script);

    foreach (['/admin/', '/coordinator/', '/pages/'] as $seg) {
        $pos = strpos($script, $seg);
        if ($pos !== false) {
            return rtrim(substr($script, 0, $pos), '/');
        }
    }

    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir, '/');
}

function app_url(string $path): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.*, b.name AS barangay_name, b.municipality, b.province
                           FROM users u
                           JOIN barangays b ON b.id = u.barangay_id
                           WHERE u.id = ? AND u.is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $cached = $stmt->fetch() ?: null;
    if (!$cached) {
        // User no longer valid
        session_destroy();
        return null;
    }
    return $cached;
}

function require_login(?string $role = null): void
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_url('index.php'));
        exit;
    }
    if ($role !== null && $user['role'] !== $role) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function redirect_by_role(): void
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_url('index.php'));
        exit;
    }
    switch ($user['role']) {
        case 'admin':
            header('Location: ' . app_url('admin/index.php'));
            break;
        case 'coordinator':
            header('Location: ' . app_url('coordinator/index.php'));
            break;
        default:
            header('Location: ' . app_url('pages/citizen_dashboard.php'));
            break;
    }
    exit;
}

