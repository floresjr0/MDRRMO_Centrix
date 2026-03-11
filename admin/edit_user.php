<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

header('Content-Type: application/json');
$pdo = db();

$id       = (int)($_POST['id'] ?? 0);
$name     = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = $_POST['role'] ?? 'citizen';
$active   = (int)($_POST['is_active'] ?? 1);
$password = $_POST['password'] ?? '';

if (!$id || !$name || !$email) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
    exit;
}

try {
    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=?, password_hash=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $active, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $active, $id]);
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}